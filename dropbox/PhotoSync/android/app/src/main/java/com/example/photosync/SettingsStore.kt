package com.example.photosync

import android.content.Context

/** Réglages persistés : serveur, compte (identifiant + jeton), option Wi-Fi. */
class SettingsStore(context: Context) {

    private val prefs = context.getSharedPreferences("photosync", Context.MODE_PRIVATE)

    var serverUrl: String
        get() = prefs.getString("server_url", DEFAULT_URL).orEmpty()
        set(v) { prefs.edit().putString("server_url", v).apply() }

    /** Nom de domaine saisi au 1er lancement (ex. "luvumbu.com"). */
    var domain: String
        get() = prefs.getString("domain", DEFAULT_DOMAIN).orEmpty()
        set(v) { prefs.edit().putString("domain", v).apply() }

    /** Sous-dossier où est installé le serveur. Vide (par défaut) = racine du domaine. */
    var subPath: String
        get() = prefs.getString("sub_path", DEFAULT_SUBPATH).orEmpty()
        set(v) { prefs.edit().putString("sub_path", v).apply() }

    /** Jeton du compte connecté (obtenu après login/inscription). */
    var token: String
        get() = prefs.getString("token", "").orEmpty()
        set(v) { prefs.edit().putString("token", v).apply() }

    var username: String
        get() = prefs.getString("username", "").orEmpty()
        set(v) { prefs.edit().putString("username", v).apply() }

    var wifiOnly: Boolean
        get() = prefs.getBoolean("wifi_only", false)
        set(v) { prefs.edit().putBoolean("wifi_only", v).apply() }

    /**
     * Envoyer tout type de fichier (photos + vidéos). Décoché = photos seulement.
     * Désactivé par défaut (on n'envoie que les photos).
     */
    var includeVideos: Boolean
        get() = prefs.getBoolean("include_videos", false)
        set(v) { prefs.edit().putBoolean("include_videos", v).apply() }

    /** Synchro automatique : envoi périodique en arrière-plan (~15 min), même app fermée. */
    var autoSync: Boolean
        get() = prefs.getBoolean("auto_sync", false)
        set(v) { prefs.edit().putBoolean("auto_sync", v).apply() }

    /** Surveillance de la galerie : détecte les ajouts en temps réel (app ouverte) et alerte. */
    var watchGallery: Boolean
        get() = prefs.getBoolean("watch_gallery", false)
        set(v) { prefs.edit().putBoolean("watch_gallery", v).apply() }

    /** Nombre maximum de photos envoyées par synchro (0 = illimité). */
    var maxPerSync: Int
        get() = prefs.getInt("max_per_sync", 0)
        set(v) { prefs.edit().putInt("max_per_sync", if (v < 0) 0 else v).apply() }

    /** Noms de fichiers supprimés sur le serveur que l'utilisateur a choisi de NE PAS renvoyer. */
    var ignoredDeletions: Set<String>
        get() = prefs.getStringSet("ignored_deletions", emptySet()) ?: emptySet()
        set(v) { prefs.edit().putStringSet("ignored_deletions", HashSet(v)).apply() }

    /** Jeton pour lequel la vérification de configuration initiale a déjà réussi. */
    var setupVerifiedToken: String
        get() = prefs.getString("setup_token", "").orEmpty()
        set(v) { prefs.edit().putString("setup_token", v).apply() }

    /** La config a-t-elle déjà été vérifiée pour LE compte actuellement connecté ? */
    val isSetupVerified: Boolean
        get() = token.isNotBlank() && setupVerifiedToken == token

    /** Connecté = serveur défini + jeton de compte présent. */
    val isLoggedIn: Boolean
        get() = serverUrl.isNotBlank() && token.isNotBlank()

    fun logout() {
        prefs.edit().remove("token").remove("username").remove("setup_token").apply()
    }

    companion object {
        const val DEFAULT_DOMAIN = "luvumbu.com"
        // Vide = racine du domaine. Le serveur PHP (api/, uploads/, lib/…) est déposé
        // directement à la racine, pas dans un sous-dossier "photos".
        const val DEFAULT_SUBPATH = ""
        const val DEFAULT_URL = "https://luvumbu.com"

        /**
         * Construit l'URL du serveur à partir d'un nom de domaine et d'un sous-dossier optionnel.
         * - tolère un schéma collé ("https://luvumbu.com"), des espaces, des "/" en trop ;
         * - si l'utilisateur a mis un chemin dans le domaine ("luvumbu.com/apk") et laissé le
         *   sous-dossier vide, ce chemin est récupéré comme sous-dossier.
         * Exemples : ("luvumbu.com", "")    -> "https://luvumbu.com"
         *            ("luvumbu.com", "apk") -> "https://luvumbu.com/apk"
         */
        fun buildServerUrl(domainRaw: String, subRaw: String): String {
            var d = domainRaw.trim()
            var scheme = "https"
            when {
                d.startsWith("http://", true)  -> { scheme = "http";  d = d.substring(7) }
                d.startsWith("https://", true) -> { scheme = "https"; d = d.substring(8) }
            }
            d = d.trim().trim('/')
            val slash = d.indexOf('/')
            val host = if (slash >= 0) d.substring(0, slash) else d
            val pathFromDomain = if (slash >= 0) d.substring(slash + 1).trim('/') else ""
            val sub = subRaw.trim().trim('/').ifEmpty { pathFromDomain }
            return if (sub.isEmpty()) "$scheme://$host" else "$scheme://$host/$sub"
        }
    }
}
