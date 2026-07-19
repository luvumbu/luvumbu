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

    /**
     * Renvoie les fichiers locaux des types demandés, triés des plus anciens aux plus récents.
     * Par défaut : photos + vidéos (comportement historique). L'audio est optionnel.
     */
    fun queryImages(
        context: Context,
        photos: Boolean = true,
        videos: Boolean = true,
        audio: Boolean = false,
    ): List<LocalPhoto> {
        val all = ArrayList<LocalPhoto>()
        if (photos) all += queryCollection(context, MediaStore.Images.Media.EXTERNAL_CONTENT_URI, "img")
        if (videos) all += queryCollection(context, MediaStore.Video.Media.EXTERNAL_CONTENT_URI, "vid")
        if (audio)  all += queryCollection(context, MediaStore.Audio.Media.EXTERNAL_CONTENT_URI, "aud")
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
            // DATE_TAKEN n'existe pas pour la collection Audio : colonne facultative.
            val takenCol = c.getColumnIndex(MediaStore.MediaColumns.DATE_TAKEN)
            val addedCol = c.getColumnIndexOrThrow(MediaStore.MediaColumns.DATE_ADDED)
            val sizeCol = c.getColumnIndexOrThrow(MediaStore.MediaColumns.SIZE)

            while (c.moveToNext()) {
                val id = c.getLong(idCol)
                val takenRaw = if (takenCol >= 0) c.getLong(takenCol) else 0L
                val taken = if (takenRaw > 0) takenRaw else c.getLong(addedCol) * 1000L
                val fallback = when (kind) {
                    "vid" -> "video_$id.mp4"
                    "aud" -> "audio_$id.mp3"
                    else -> "photo_$id.jpg"
                }
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
