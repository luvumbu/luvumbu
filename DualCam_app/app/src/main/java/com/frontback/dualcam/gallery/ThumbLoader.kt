package com.frontback.dualcam.gallery

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.media.MediaMetadataRetriever
import android.media.ThumbnailUtils
import android.os.Handler
import android.os.Looper
import android.util.LruCache
import android.widget.ImageView
import java.util.concurrent.Executors

/** Charge les miniatures (photo ou vidéo) en arrière-plan, avec un petit cache mémoire. */
object ThumbLoader {

    private val executor = Executors.newFixedThreadPool(3)
    private val main = Handler(Looper.getMainLooper())
    private val cache = object : LruCache<String, Bitmap>(60) {
        override fun sizeOf(key: String, value: Bitmap) = 1
    }

    fun load(path: String, isVideo: Boolean, target: ImageView) {
        cache.get(path)?.let { target.setImageBitmap(it); target.tag = path; return }

        target.setImageBitmap(null)
        target.tag = path
        executor.execute {
            val bmp = try {
                if (isVideo) videoThumb(path) else imageThumb(path)
            } catch (t: Throwable) {
                null
            }
            if (bmp != null) {
                cache.put(path, bmp)
                main.post {
                    if (target.tag == path) target.setImageBitmap(bmp)
                }
            }
        }
    }

    private fun imageThumb(path: String): Bitmap? {
        val opts = BitmapFactory.Options().apply { inJustDecodeBounds = true }
        BitmapFactory.decodeFile(path, opts)
        var sample = 1
        val target = 256
        while (opts.outWidth / sample > target || opts.outHeight / sample > target) sample *= 2
        val decodeOpts = BitmapFactory.Options().apply { inSampleSize = sample }
        return BitmapFactory.decodeFile(path, decodeOpts)
    }

    private fun videoThumb(path: String): Bitmap? {
        return try {
            @Suppress("DEPRECATION")
            ThumbnailUtils.createVideoThumbnail(path, android.provider.MediaStore.Images.Thumbnails.MINI_KIND)
        } catch (t: Throwable) {
            val r = MediaMetadataRetriever()
            try {
                r.setDataSource(path)
                r.getFrameAtTime(0)
            } finally {
                r.release()
            }
        }
    }
}
