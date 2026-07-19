package com.example.photosync

import android.content.Context
import android.net.Uri
import android.provider.MediaStore

/** Une photo trouvée localement dans la galerie. */
data class LocalPhoto(
    val id: Long,
    val uri: Uri,
    val name: String,
    val dateTakenMs: Long,
    val size: Long,
)

/** Lecture des images ET vidéos via MediaStore (toute la galerie de l'appareil). */
object MediaScanner {

    /** Extensions reconnues comme vidéos (pour distinguer photo/vidéo d'après un nom de fichier). */
    private val VIDEO_EXTENSIONS = setOf(
        "mp4", "mov", "mkv", "avi", "3gp", "3gpp", "webm", "m4v", "wmv", "flv",
        "ts", "mts", "m2ts", "mpg", "mpeg", "ogv",
    )

    /** Le nom de fichier désigne-t-il une vidéo ? (d'après son extension) */
    fun isVideoFileName(name: String): Boolean =
        name.substringAfterLast('.', "").lowercase() in VIDEO_EXTENSIONS

    /**
     * Renvoie les photos (+ vidéos si demandé), triées des plus anciennes aux plus récentes.
     * @param includeVideos true = envoyer aussi les vidéos ; false = photos uniquement.
     */
    fun queryImages(context: Context, includeVideos: Boolean = true): List<LocalPhoto> {
        val all = ArrayList<LocalPhoto>()
        all += queryCollection(context, MediaStore.Images.Media.EXTERNAL_CONTENT_URI, "img")
        if (includeVideos) all += queryCollection(context, MediaStore.Video.Media.EXTERNAL_CONTENT_URI, "vid")
        // On garde un ordre chronologique global (ancien -> récent).
        all.sortBy { it.dateTakenMs }
        return all
    }

    /** Interroge une collection MediaStore (images ou vidéos) — colonnes communes aux deux. */
    private fun queryCollection(context: Context, collection: Uri, kind: String): List<LocalPhoto> {
        val out = mutableListOf<LocalPhoto>()
        val projection = arrayOf(
            MediaStore.MediaColumns._ID,
            MediaStore.MediaColumns.DISPLAY_NAME,
            MediaStore.MediaColumns.DATE_TAKEN,
            MediaStore.MediaColumns.DATE_ADDED,
            MediaStore.MediaColumns.SIZE,
        )
        val sortOrder = "${MediaStore.MediaColumns.DATE_ADDED} ASC"

        context.contentResolver.query(collection, projection, null, null, sortOrder)?.use { c ->
            val idCol = c.getColumnIndexOrThrow(MediaStore.MediaColumns._ID)
            val nameCol = c.getColumnIndexOrThrow(MediaStore.MediaColumns.DISPLAY_NAME)
            val takenCol = c.getColumnIndexOrThrow(MediaStore.MediaColumns.DATE_TAKEN)
            val addedCol = c.getColumnIndexOrThrow(MediaStore.MediaColumns.DATE_ADDED)
            val sizeCol = c.getColumnIndexOrThrow(MediaStore.MediaColumns.SIZE)

            while (c.moveToNext()) {
                val id = c.getLong(idCol)
                val takenRaw = c.getLong(takenCol)
                val taken = if (takenRaw > 0) takenRaw else c.getLong(addedCol) * 1000L
                val fallback = if (kind == "vid") "video_$id.mp4" else "photo_$id.jpg"
                out.add(
                    LocalPhoto(
                        id = id,
                        uri = Uri.withAppendedPath(collection, id.toString()),
                        name = c.getString(nameCol) ?: fallback,
                        dateTakenMs = taken,
                        size = c.getLong(sizeCol),
                    )
                )
            }
        }
        return out
    }
}
