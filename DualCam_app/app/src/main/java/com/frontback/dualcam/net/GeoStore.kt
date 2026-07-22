package com.frontback.dualcam.net

import android.content.Context

/**
 * Mémorise localement la position GPS de chaque vidéo (le fichier .mp4 ne la contient pas).
 * Clé = nom du fichier ; valeur = "latitude,longitude". Permet à la galerie de l'app
 * d'afficher « Plus d'infos » (coordonnées, carte, adresse) sans dépendre du serveur.
 */
object GeoStore {

    private const val PREFS = "dualcam_geo"

    /** Enregistre la position d'un fichier (ignoré si coordonnées absentes). */
    fun save(context: Context, fileName: String, lat: Double?, lng: Double?) {
        if (lat == null || lng == null) return
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit().putString(fileName, "$lat,$lng").apply()
    }

    /** Position d'un fichier en (latitude, longitude), ou null si inconnue. */
    fun get(context: Context, fileName: String): Pair<Double, Double>? {
        val v = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .getString(fileName, null) ?: return null
        val p = v.split(",")
        val lat = p.getOrNull(0)?.toDoubleOrNull()
        val lng = p.getOrNull(1)?.toDoubleOrNull()
        return if (lat != null && lng != null) lat to lng else null
    }
}
