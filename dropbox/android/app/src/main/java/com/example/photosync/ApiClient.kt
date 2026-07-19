package com.example.photosync

import android.content.Context
import okhttp3.FormBody
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.asRequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONArray
import org.json.JSONObject
import java.io.File
import java.net.URLEncoder
import java.security.MessageDigest
import java.util.concurrent.TimeUnit

/** Résultat d'un envoi : succès + éventuel message d'erreur lisible. */
data class UploadResult(val ok: Boolean, val code: Int, val error: String?)

/** Un fichier tel que listé par le serveur (photo, vidéo, audio, document…). */
data class ServerPhoto(
    val id: Long,
    val name: String,
    val date: String,
    val category: String = "photo", // photo | video | audio | document | other
    val video: Boolean = false,
    val size: Long = 0L,
) {
    /** True si une vraie miniature image existe côté serveur (sinon : icône locale). */
    val hasImageThumb: Boolean get() = category == "photo"
}

/** Une page de la liste serveur. */
data class FeedPage(val photos: List<ServerPhoto>, val page: Int, val pages: Int, val total: Int)

/** Résultat d'une connexion / inscription. */
data class AuthResult(val ok: Boolean, val token: String?, val username: String?, val error: String?)

/** Résultat de la vérification de configuration initiale. */
data class ConfigCheck(val ok: Boolean, val error: String?)

/** Envoie une photo au serveur PHP via un POST multipart. */
class ApiClient(
    private val context: Context,
    private val settings: SettingsStore,
) {
    private val client = OkHttpClient.Builder()
        .connectTimeout(30, TimeUnit.SECONDS)
        // Délais larges pour les gros fichiers (vidéos volumineuses).
        .writeTimeout(20, TimeUnit.MINUTES)
        .readTimeout(5, TimeUnit.MINUTES)
        .build()

    fun upload(photo: LocalPhoto): UploadResult {
        // content:// n'est pas un fichier : on copie d'abord dans le cache.
        val tmp = File.createTempFile("up_", ".bin", context.cacheDir)
        try {
            context.contentResolver.openInputStream(photo.uri)?.use { input ->
                tmp.outputStream().use { output -> input.copyTo(output) }
            } ?: return UploadResult(false, -1, "Photo illisible")

            val mime = context.contentResolver.getType(photo.uri) ?: "application/octet-stream"
            val body = MultipartBody.Builder()
                .setType(MultipartBody.FORM)
                .addFormDataPart("taken_at", photo.dateTakenMs.toString())
                .addFormDataPart("photo", photo.name, tmp.asRequestBody(mime.toMediaTypeOrNull()))
                .build()

            val url = base() + "/upload.php"
            val request = Request.Builder()
                .url(url)
                .header("X-Auth-Token", settings.token)
                .post(body)
                .build()

            client.newCall(request).execute().use { resp ->
                if (resp.isSuccessful) return UploadResult(true, resp.code, null)
                // Message renvoyé par le serveur (souvent un JSON {"error": "..."}).
                val serverMsg = resp.body?.string()?.take(300)?.trim().orEmpty()
                return when {
                    resp.code == 401 -> UploadResult(false, 401, "Jeton invalide (vérifie config.php)")
                    resp.code == 404 -> UploadResult(false, 404, "URL introuvable (404) — vérifie l'adresse")
                    resp.code == 413 -> UploadResult(false, 413, "Photo trop volumineuse pour le serveur")
                    serverMsg.isNotEmpty() -> UploadResult(false, resp.code, "HTTP ${resp.code} — $serverMsg")
                    else -> UploadResult(false, resp.code, "Erreur serveur HTTP ${resp.code}")
                }
            }
        } catch (e: Exception) {
            return UploadResult(false, -1, "Connexion impossible : ${e.message ?: "réseau"}")
        } finally {
            tmp.delete()
        }
    }

    private fun encToken(): String = URLEncoder.encode(settings.token, "UTF-8")
    // Les endpoints de l'app sont regroupés dans le sous-dossier api/ du serveur.
    private fun base(): String = settings.serverUrl.trimEnd('/') + "/api"

    /** URL de la miniature d'une photo (avec jeton). */
    fun thumbUrl(id: Long): String = "${base()}/media.php?id=$id&thumb=1&token=${encToken()}"

    /** URL de l'image complète d'une photo (avec jeton). */
    fun fullUrl(id: Long): String = "${base()}/media.php?id=$id&token=${encToken()}"

    /**
     * Récupère une page de la liste du serveur. Lève une exception en cas d'échec.
     * @param type 'all' | 'photo' | 'video' | 'audio' | 'document' | 'other'
     * @param sort 'date_desc' | 'date_asc' | 'name_asc' | 'name_desc' | 'size_desc' | 'size_asc' | 'type'
     */
    fun fetchPhotos(page: Int, type: String = "all", sort: String = "date_desc"): FeedPage {
        val t = URLEncoder.encode(type, "UTF-8")
        val s = URLEncoder.encode(sort, "UTF-8")
        val url = "${base()}/feed.php?page=$page&type=$t&sort=$s"
        val request = Request.Builder()
            .url(url)
            .header("X-Auth-Token", settings.token)
            .get()
            .build()

        client.newCall(request).execute().use { resp ->
            val body = resp.body?.string().orEmpty()
            if (!resp.isSuccessful) {
                throw RuntimeException(
                    if (resp.code == 401) "Jeton invalide" else "Erreur serveur HTTP ${resp.code}"
                )
            }
            val json = JSONObject(body)
            if (!json.optBoolean("ok", false)) {
                throw RuntimeException(json.optString("error", "Réponse invalide"))
            }
            val arr = json.optJSONArray("photos")
            val list = ArrayList<ServerPhoto>()
            if (arr != null) {
                for (i in 0 until arr.length()) {
                    val o = arr.getJSONObject(i)
                    list.add(
                        ServerPhoto(
                            id = o.getLong("id"),
                            name = o.optString("name", "photo"),
                            date = o.optString("date", ""),
                            category = o.optString("category", "photo"),
                            video = o.optBoolean("video", false),
                            size = o.optLong("size", 0L),
                        )
                    )
                }
            }
            return FeedPage(
                photos = list,
                page = json.optInt("page", 1),
                pages = json.optInt("pages", 1),
                total = json.optInt("total", list.size),
            )
        }
    }

    /** Récupère l'ensemble des noms de fichiers déjà présents pour le compte (1 requête). */
    fun fetchNames(): Set<String> {
        return try {
            val request = Request.Builder()
                .url(base() + "/names.php")
                .header("X-Auth-Token", settings.token)
                .get()
                .build()
            client.newCall(request).execute().use { resp ->
                if (!resp.isSuccessful) return emptySet()
                val arr = JSONObject(resp.body?.string().orEmpty()).optJSONArray("names")
                    ?: return emptySet()
                val set = HashSet<String>(arr.length())
                for (i in 0 until arr.length()) set.add(arr.getString(i))
                set
            }
        } catch (e: Exception) {
            emptySet()
        }
    }

    /** Récupère TOUTES les photos du serveur (parcourt toutes les pages). */
    fun fetchAllPhotos(): List<ServerPhoto> {
        val all = ArrayList<ServerPhoto>()
        var page = 1
        while (true) {
            val fp = fetchPhotos(page)
            all.addAll(fp.photos)
            if (page >= fp.pages) break
            page++
        }
        return all
    }

    /** Met à la corbeille (serveur) les photos indiquées. Renvoie true si OK. */
    fun deletePhotos(ids: List<Long>): Boolean {
        if (ids.isEmpty()) return true
        return try {
            val payload = JSONObject().put("ids", JSONArray(ids)).toString()
            val body = payload.toRequestBody("application/json".toMediaTypeOrNull())
            val request = Request.Builder()
                .url(base() + "/delete.php")
                .header("X-Auth-Token", settings.token)
                .post(body)
                .build()
            client.newCall(request).execute().use { it.isSuccessful }
        } catch (e: Exception) {
            false
        }
    }

    /** Calcule l'empreinte SHA-256 d'une photo (même algo que le serveur). */
    fun sha256(photo: LocalPhoto): String? {
        return try {
            val md = MessageDigest.getInstance("SHA-256")
            context.contentResolver.openInputStream(photo.uri)?.use { input ->
                val buf = ByteArray(8192)
                var n: Int
                while (input.read(buf).also { n = it } > 0) md.update(buf, 0, n)
            } ?: return null
            md.digest().joinToString("") { "%02x".format(it) }
        } catch (e: Exception) {
            null
        }
    }

    /** Demande au serveur quelles empreintes sont déjà présentes. Renvoie l'ensemble trouvé. */
    fun checkExisting(hashes: List<String>): Set<String> {
        if (hashes.isEmpty()) return emptySet()
        return try {
            val payload = JSONObject().put("hashes", JSONArray(hashes)).toString()
            val body = payload.toRequestBody("application/json".toMediaTypeOrNull())
            val request = Request.Builder()
                .url(base() + "/check.php")
                .header("X-Auth-Token", settings.token)
                .post(body)
                .build()
            client.newCall(request).execute().use { resp ->
                if (!resp.isSuccessful) return emptySet()
                val arr = JSONObject(resp.body?.string().orEmpty()).optJSONArray("exists")
                    ?: return emptySet()
                val set = HashSet<String>(arr.length())
                for (i in 0 until arr.length()) set.add(arr.getString(i))
                set
            }
        } catch (e: Exception) {
            emptySet()
        }
    }

    /**
     * Vérification de configuration (appelée une seule fois, au 1er lancement).
     * Confirme côté serveur que le compte est valide et que SON espace de stockage
     * (uploads/<user_id>) existe et est inscriptible — sans toucher aux autres membres.
     */
    fun verifySetup(): ConfigCheck {
        return try {
            val request = Request.Builder()
                .url(base() + "/setup.php")
                .header("X-Auth-Token", settings.token)
                .post(FormBody.Builder().build())
                .build()
            client.newCall(request).execute().use { resp ->
                val body = resp.body?.string().orEmpty()
                val json = try { JSONObject(body) } catch (e: Exception) { null }
                when {
                    resp.isSuccessful && json?.optBoolean("ok", false) == true -> ConfigCheck(true, null)
                    resp.code == 401 -> ConfigCheck(false, "Jeton invalide (reconnecte-toi)")
                    resp.code == 404 -> ConfigCheck(false, "setup.php introuvable — vérifie l'adresse du serveur")
                    json != null -> ConfigCheck(false, json.optString("error", "Config serveur invalide (HTTP ${resp.code})"))
                    else -> ConfigCheck(false, "Réponse serveur invalide (HTTP ${resp.code})")
                }
            }
        } catch (e: Exception) {
            ConfigCheck(false, "Connexion impossible : ${e.message ?: "réseau"}")
        }
    }

    /**
     * Connexion via Google : envoie le jeton d'identité Google au serveur, qui
     * retrouve ou crée le compte et renvoie le jeton interne de l'app.
     */
    fun loginWithGoogle(idToken: String): AuthResult {
        val form = FormBody.Builder()
            .add("id_token", idToken)
            .build()
        return auth(base() + "/google_login.php", form, null)
    }

    private fun auth(url: String, form: FormBody, invite: String?): AuthResult {
        return try {
            val builder = Request.Builder().url(url).post(form)
            if (invite != null) builder.header("X-Auth-Token", invite)
            client.newCall(builder.build()).execute().use { resp ->
                val raw = resp.body?.string().orEmpty()
                // On NE parse le JSON qu'après avoir tenté ; une page HTML (404…) ne doit pas
                // être confondue avec une coupure réseau.
                val json = try { JSONObject(raw) } catch (e: Exception) { null }
                when {
                    json != null && resp.isSuccessful && json.optBoolean("ok", false) ->
                        AuthResult(true, json.optString("token"), json.optString("username"), null)
                    json != null ->
                        AuthResult(false, null, null, json.optString("error", "Erreur (HTTP ${resp.code})"))
                    resp.code == 404 ->
                        AuthResult(false, null, null, "Adresse introuvable (404) — vérifie le domaine et le sous-dossier")
                    else ->
                        AuthResult(false, null, null, "Réponse inattendue du serveur (HTTP ${resp.code})")
                }
            }
        } catch (e: Exception) {
            AuthResult(false, null, null, "Connexion impossible : ${e.message ?: "réseau"}")
        }
    }
}
