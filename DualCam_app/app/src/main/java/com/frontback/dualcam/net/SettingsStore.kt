package com.frontback.dualcam.net

import android.content.Context

/**
 * Réglages persistés pour l'envoi au serveur PhotoSync.
 * MÊME serveur et MÊME compte que l'application PhotoSync : les vidéos DualCam
 * arrivent dans le même espace de stockage que les photos.
 */
class SettingsStore(context: Context) {

    private val prefs = context.getSharedPreferences("dualcam_net", Context.MODE_PRIVATE)

    init {
        // Un jeton obtenu sur un AUTRE serveur (version antérieure qui pointait sur PhotoSync)
        // n'existe pas dans la base DualCam : le serveur répondrait 401 à chaque envoi alors que
        // l'app se croit connectée. On efface le jeton dès que le serveur cible a changé.
        if (prefs.getString("server_tag", null) != DEFAULT_URL) {
            prefs.edit()
                .remove("token").remove("username")
                .putString("server_tag", DEFAULT_URL)
                .apply()
        }
    }

    /** URL du serveur DualCam. */
    var serverUrl: String
        get() = prefs.getString("server_url", DEFAULT_URL).orEmpty()
        set(v) { prefs.edit().putString("server_url", v).apply() }

    /** Jeton du compte connecté (obtenu après connexion Google). */
    var token: String
        get() = prefs.getString("token", "").orEmpty()
        set(v) { prefs.edit().putString("token", v).apply() }

    var username: String
        get() = prefs.getString("username", "").orEmpty()
        set(v) { prefs.edit().putString("username", v).apply() }

    val isLoggedIn: Boolean
        get() = serverUrl.isNotBlank() && token.isNotBlank()

    /**
     * Dernier échec d'envoi automatique (vide si le dernier envoi est passé).
     * Les envois se font en tâche de fond : sans cette trace, une panne serveur ou
     * une session expirée resterait totalement invisible.
     */
    var lastUploadError: String
        get() = prefs.getString("last_upload_error", "").orEmpty()
        set(v) { prefs.edit().putString("last_upload_error", v).apply() }

    /** Marque un fichier (chemin absolu) comme déjà envoyé, pour ne pas le renvoyer. */
    fun markUploaded(path: String) {
        val set = HashSet(uploadedPaths())
        set.add(path)
        prefs.edit().putStringSet("uploaded", set).apply()
    }

    fun isUploaded(path: String): Boolean = uploadedPaths().contains(path)

    /** Oublie le marquage « envoyé » d'un fichier (ex. après suppression). */
    fun unmarkUploaded(path: String) {
        val set = HashSet(uploadedPaths())
        if (set.remove(path)) prefs.edit().putStringSet("uploaded", set).apply()
    }

    private fun uploadedPaths(): Set<String> = prefs.getStringSet("uploaded", emptySet()) ?: emptySet()

    fun logout() {
        prefs.edit().remove("token").remove("username").apply()
    }

    companion object {
        // Serveur propre à DualCam.
        const val DEFAULT_URL = "https://luvumbu.com/DualCam"
    }
}
