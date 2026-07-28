package com.frontback.dualcam.net

import okhttp3.FormBody
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.asRequestBody
import org.json.JSONObject
import java.io.File
import java.util.concurrent.TimeUnit

/** Résultat d'un envoi de fichier. */
data class UploadResult(val ok: Boolean, val code: Int, val error: String?)

/** Résultat d'une connexion Google. */
data class AuthResult(val ok: Boolean, val token: String?, val username: String?, val error: String?)

/** État du partage public renvoyé par api/share.php. */
data class ShareState(val ok: Boolean, val enabled: Boolean, val url: String?, val error: String?)

/**
 * Client du serveur PhotoSync — MÊMES endpoints que l'app PhotoSync
 * (api/google_login.php, api/upload.php). Une vidéo DualCam est envoyée
 * exactement comme une photo : POST multipart avec le champ « photo ».
 */
class ApiClient(private val settings: SettingsStore) {

    private val client = OkHttpClient.Builder()
        .connectTimeout(30, TimeUnit.SECONDS)
        // Délais larges : les vidéos peuvent être volumineuses.
        .writeTimeout(30, TimeUnit.MINUTES)
        .readTimeout(5, TimeUnit.MINUTES)
        .build()

    // Les endpoints sont regroupés dans le sous-dossier api/ du serveur.
    private fun base(): String = settings.serverUrl.trimEnd('/') + "/api"

    /**
     * Connexion via Google : envoie le jeton d'identité Google au serveur,
     * qui retrouve ou crée le compte et renvoie le jeton interne de l'app.
     */
    fun loginWithGoogle(idToken: String): AuthResult {
        return try {
            val form = FormBody.Builder().add("id_token", idToken).build()
            val request = Request.Builder()
                .url(base() + "/google_login.php")
                .post(form)
                .build()
            client.newCall(request).execute().use { resp ->
                val raw = resp.body?.string().orEmpty()
                val json = try { JSONObject(raw) } catch (e: Exception) { null }
                when {
                    json != null && resp.isSuccessful && json.optBoolean("ok", false) ->
                        AuthResult(true, json.optString("token"), json.optString("username"), null)
                    json != null ->
                        AuthResult(false, null, null, json.optString("error", "Erreur (HTTP ${resp.code})"))
                    resp.code == 404 ->
                        AuthResult(false, null, null, "Adresse introuvable (404) — vérifie le serveur")
                    else ->
                        AuthResult(false, null, null, "Réponse inattendue du serveur (HTTP ${resp.code})")
                }
            }
        } catch (e: Exception) {
            AuthResult(false, null, null, "Connexion impossible : ${e.message ?: "réseau"}")
        }
    }

    /**
     * Envoie un fichier (vidéo ou photo) au serveur via un POST multipart.
     * @param uploadName nom enregistré côté serveur (sert à regrouper une session).
     * @param source origine marquée ('dualcam' → page web dédiée).
     */
    fun uploadFile(
        file: File,
        uploadName: String = file.name,
        source: String = "dualcam",
        lat: Double? = null,
        lng: Double? = null
    ): UploadResult {
        if (!file.exists()) return UploadResult(false, -1, "Fichier introuvable")
        val mime = when {
            file.name.endsWith(".mp4", true) -> "video/mp4"
            file.name.endsWith(".jpg", true) || file.name.endsWith(".jpeg", true) -> "image/jpeg"
            file.name.endsWith(".png", true) -> "image/png"
            else -> "application/octet-stream"
        }
        return try {
            val builder = MultipartBody.Builder()
                .setType(MultipartBody.FORM)
                .addFormDataPart("taken_at", file.lastModified().toString())
                // Marque l'origine « dualcam » → affichée sur la page web dédiée (web/dualcam.php).
                .addFormDataPart("source", source)
            // Géolocalisation facultative : envoyée seulement si le couple est connu.
            // Double.toString() utilise toujours le point décimal (attendu par le serveur).
            if (lat != null && lng != null) {
                builder.addFormDataPart("latitude", lat.toString())
                builder.addFormDataPart("longitude", lng.toString())
            }
            val body = builder
                .addFormDataPart("photo", uploadName, file.asRequestBody(mime.toMediaTypeOrNull()))
                .build()

            val request = Request.Builder()
                .url(base() + "/upload.php")
                .header("X-Auth-Token", settings.token)
                .post(body)
                .build()

            client.newCall(request).execute().use { resp ->
                if (resp.isSuccessful) return UploadResult(true, resp.code, null)
                val serverMsg = resp.body?.string()?.take(300)?.trim().orEmpty()
                when (resp.code) {
                    // Jeton refusé : on NE déconnecte PAS automatiquement — un seul 401 passager
                    // effacerait le jeton et casserait tous les envois suivants. On signale juste.
                    401 -> UploadResult(false, 401, "Session expirée — reconnecte-toi")
                    404 -> UploadResult(false, 404, "URL introuvable (404) — vérifie le serveur")
                    413 -> UploadResult(false, 413, "Vidéo trop volumineuse pour le serveur")
                    else -> UploadResult(false, resp.code,
                        if (serverMsg.isNotEmpty()) "HTTP ${resp.code} — $serverMsg" else "Erreur serveur HTTP ${resp.code}")
                }
            }
        } catch (e: Exception) {
            UploadResult(false, -1, "Connexion impossible : ${e.message ?: "réseau"}")
        }
    }

    /**
     * Fin de session déclarée : demande au serveur de supprimer les fragments de 30 s
     * devenus redondants (la vidéo complète les remplace) → pas de doublons.
     * Le serveur ne supprime QUE si la vidéo complète est bien présente.
     */
    /**
     * Met à la corbeille (serveur) les vidéos DualCam dont on donne les noms de fichier.
     * Renvoie true si le serveur a répondu OK. Sans effet si la liste est vide.
     */
    fun deleteByNames(names: List<String>): Boolean {
        if (names.isEmpty()) return true
        return try {
            val fb = FormBody.Builder()
            names.forEach { fb.add("names[]", it) }
            val req = Request.Builder()
                .url(base() + "/dualcam_delete.php")
                .header("X-Auth-Token", settings.token)
                .post(fb.build())
                .build()
            client.newCall(req).execute().use { it.isSuccessful }
        } catch (e: Exception) {
            false
        }
    }

    /** Lit l'état du partage public (GET api/share.php). */
    fun getShare(): ShareState = shareRequest(null)

    /** Active (true) ou désactive (false) le partage public (POST enabled=1|0). */
    fun setShare(enabled: Boolean): ShareState = shareRequest(enabled)

    private fun shareRequest(enabled: Boolean?): ShareState {
        return try {
            val b = Request.Builder()
                .url(base() + "/share.php")
                .header("X-Auth-Token", settings.token)
            if (enabled == null) b.get()
            else b.post(FormBody.Builder().add("enabled", if (enabled) "1" else "0").build())
            client.newCall(b.build()).execute().use { resp ->
                val raw = resp.body?.string().orEmpty()
                val json = try { JSONObject(raw) } catch (e: Exception) { null }
                if (json != null && resp.isSuccessful && json.optBoolean("ok", false))
                    ShareState(true, json.optBoolean("public_share", false),
                        json.optString("share_url").ifBlank { null }, null)
                else
                    ShareState(false, false, null,
                        json?.optString("error") ?: "Erreur serveur (HTTP ${resp.code})")
            }
        } catch (e: Exception) {
            ShareState(false, false, null, "Connexion impossible : ${e.message ?: "réseau"}")
        }
    }

    /**
     * Relève l'ordre déposé depuis le PC (page web/remote.php).
     * Renvoie "start", "stop", ou null s'il n'y a rien (ou en cas de problème réseau).
     * L'ordre est consommé côté serveur : il ne peut se déclencher qu'une fois.
     *
     * N'est appelé QUE si l'utilisateur a coché « Déclenchement à distance » :
     * option décochée = aucune requête, donc aucun pilotage possible depuis le serveur.
     */
    fun pollRemoteCommand(recording: Boolean): String? {
        if (settings.token.isBlank()) return null
        return try {
            // On signale au serveur l'état d'enregistrement ET la dernière erreur d'envoi
            // (affichée sur la page PC pour diagnostiquer sans lire le téléphone).
            val err = java.net.URLEncoder.encode(settings.lastUploadError.take(140), "UTF-8")
            val req = Request.Builder()
                .url(base() + "/remote.php?poll=1&rec=" + (if (recording) "1" else "0") + "&err=" + err)
                .header("X-Auth-Token", settings.token)
                .get()
                .build()
            client.newCall(req).execute().use { resp ->
                if (!resp.isSuccessful) return null
                val json = JSONObject(resp.body?.string().orEmpty())
                json.optString("cmd", "").ifBlank { null }
            }
        } catch (e: Exception) {
            null
        }
    }

    fun finalizeSession(session: String): Boolean {
        return try {
            val form = FormBody.Builder().add("session", session).build()
            val request = Request.Builder()
                .url(base() + "/dualcam_finalize.php")
                .header("X-Auth-Token", settings.token)
                .post(form)
                .build()
            client.newCall(request).execute().use { it.isSuccessful }
        } catch (e: Exception) {
            false
        }
    }
}
