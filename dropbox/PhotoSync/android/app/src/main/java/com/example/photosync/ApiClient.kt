package com.example.photosync

import android.content.Context
import okhttp3.FormBody
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import okio.BufferedSink
import okio.source
import org.json.JSONArray
import org.json.JSONObject
import java.io.IOException
import java.net.URLEncoder
import java.security.MessageDigest
import java.util.concurrent.TimeUnit

/** Résultat d'un envoi : succès + éventuel message d'erreur lisible. */
data class UploadResult(val ok: Boolean, val code: Int, val error: String?)

/**
 * Un média listé par le serveur. Le type vient du serveur (`serverVideo`, fiable car basé
 * sur le contenu réel) ; à défaut (vieux serveur), on retombe sur l'extension du nom.
 */
data class ServerPhoto(
    val id: Long,
    val name: String,
    val date: String,
    val serverVideo: Boolean? = null,
    val source: String = "phone", // 'phone' (envoyé depuis l'app) ou 'web' (ajouté depuis le site)
) {
    val isVideo: Boolean get() = serverVideo ?: MediaScanner.isVideoFileName(name)
    val isFromWeb: Boolean get() = source.equals("web", ignoreCase = true)
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
        try {
            val mime = context.contentResolver.getType(photo.uri) ?: "application/octet-stream"

            // Envoi DIRECT depuis la galerie (streaming) : plus de copie dans le cache.
            // C'est ce qui ralentissait — surtout pour les vidéos.
            val fileBody = object : RequestBody() {
                override fun contentType() = mime.toMediaTypeOrNull()
                override fun contentLength() = if (photo.size > 0) photo.size else -1L
                override fun writeTo(sink: BufferedSink) {
                    val input = context.contentResolver.openInputStream(photo.uri)
                        ?: throw IOException("Photo illisible")
                    input.use { sink.writeAll(it.source()) }
                }
            }

            val body = MultipartBody.Builder()
                .setType(MultipartBody.FORM)
                .addFormDataPart("taken_at", photo.dateTakenMs.toString())
                .addFormDataPart("photo", photo.name, fileBody)
                .build()

            val request = Request.Builder()
                .url(base() + "/upload.php")
                .header("X-Auth-Token", settings.token)
                .post(body)
                .build()

            client.newCall(request).execute().use { resp ->
                if (resp.isSuccessful) return UploadResult(true, resp.code, null)
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
        }
    }

    private fun encToken(): String = URLEncoder.encode(settings.token, "UTF-8")
    // Les endpoints de l'app sont regroupés dans le sous-dossier api/ du serveur.
    private fun base(): String = settings.serverUrl.trimEnd('/') + "/api"

    /** URL de la miniature d'une photo (avec jeton). */
    fun thumbUrl(id: Long): String = "${base()}/media.php?id=$id&thumb=1&token=${encToken()}"

    /** URL de l'image complète d'une photo (avec jeton). */
    fun fullUrl(id: Long): String = "${base()}/media.php?id=$id&token=${encToken()}"

    /** Récupère une page de la liste des photos du serveur. Lève une exception en cas d'échec. */
    fun fetchPhotos(page: Int): FeedPage {
        val url = "${base()}/feed.php?page=$page"
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
                            serverVideo = if (o.has("video")) o.optBoolean("video") else null,
                            source = o.optString("source", "phone"),
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

    /**
     * Récupère l'ensemble des noms de fichiers présents pour le compte (1 requête).
     * Renvoie null en cas d'échec réseau/serveur (à distinguer d'un compte réellement vide).
     */
    fun fetchNames(): Set<String>? {
        return try {
            val request = Request.Builder()
                .url(base() + "/names.php")
                .header("X-Auth-Token", settings.token)
                .get()
                .build()
            client.newCall(request).execute().use { resp ->
                if (!resp.isSuccessful) return null
                val arr = JSONObject(resp.body?.string().orEmpty()).optJSONArray("names")
                    ?: return null
                val set = HashSet<String>(arr.length())
                for (i in 0 until arr.length()) set.add(arr.getString(i))
                set
            }
        } catch (e: Exception) {
            null
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

    /** Connexion à un compte existant. */
    fun login(username: String, password: String): AuthResult {
        val form = FormBody.Builder()
            .add("username", username)
            .add("password", password)
            .build()
        return auth(base() + "/login.php", form, null)
    }

    /** Création d'un compte (gardée par le code d'inscription = mot de passe du serveur). */
    fun register(username: String, password: String, invite: String): AuthResult {
        val form = FormBody.Builder()
            .add("username", username)
            .add("password", password)
            .build()
        return auth(base() + "/register.php", form, invite)
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
