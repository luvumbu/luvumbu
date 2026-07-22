package com.frontback.dualcam.gallery

import android.content.Context
import android.os.Environment
import java.io.File

/** Un élément enregistré (vidéo ou photo). */
data class MediaItem(
    val path: String,
    val isVideo: Boolean,
    val lastModified: Long
)

/** Accès aux vidéos et photos enregistrées par l'application. */
object MediaRepository {

    fun videosDir(context: Context): File =
        File(context.getExternalFilesDir(Environment.DIRECTORY_MOVIES) ?: context.filesDir, "")

    fun photosDir(context: Context): File =
        File(context.getExternalFilesDir(Environment.DIRECTORY_PICTURES) ?: context.filesDir, "DualCam")

    /** Liste tous les enregistrements, du plus récent au plus ancien. */
    fun listAll(context: Context): List<MediaItem> {
        val items = mutableListOf<MediaItem>()

        videosDir(context).listFiles { f -> f.isFile && f.name.endsWith(".mp4", true) }
            ?.forEach { items.add(MediaItem(it.absolutePath, true, it.lastModified())) }

        photosDir(context).listFiles { f -> f.isFile && f.name.endsWith(".jpg", true) }
            ?.forEach { items.add(MediaItem(it.absolutePath, false, it.lastModified())) }

        return items.sortedByDescending { it.lastModified }
    }
}
