package com.frontback.dualcam.montage

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import android.os.Handler
import android.os.Looper
import androidx.media3.common.Effect
import androidx.media3.common.MediaItem
import androidx.media3.effect.BitmapOverlay
import androidx.media3.effect.OverlayEffect
import androidx.media3.effect.TextureOverlay
import androidx.media3.transformer.Composition
import androidx.media3.transformer.EditedMediaItem
import androidx.media3.transformer.EditedMediaItemSequence
import androidx.media3.transformer.Effects
import androidx.media3.transformer.ExportException
import androidx.media3.transformer.ExportResult
import androidx.media3.transformer.Transformer
import com.google.common.collect.ImmutableList
import java.io.File
import kotlin.concurrent.thread

/**
 * Un segment de la vidéo finale, avec éventuellement une photo à incruster
 * (celle prise avec l'autre caméra juste avant ce segment).
 */
data class MontageSegment(val file: File, val overlayPhoto: File?)

/**
 * Recolle les segments en une seule vidéo continue et incruste chaque photo
 * dans un coin, au début du segment correspondant.
 */
class MontageBuilder(private val context: Context) {

    interface Callback {
        fun onSuccess(output: File)
        fun onFailure(message: String)
    }

    companion object {
        private const val THUMB_WIDTH = 260
    }

    private var transformer: Transformer? = null

    fun build(
        segments: List<MontageSegment>,
        output: File,
        overlayDurationUs: Long,
        callback: Callback
    ) {
        thread(name = "montage-prepare") {
            // Décodage des photos à incruster (miniatures) hors du thread principal.
            val prepared = segments.map { seg ->
                val overlays: List<Effect> = seg.overlayPhoto
                    ?.let { decodeScaled(it, THUMB_WIDTH) }
                    ?.let { bmp ->
                        val ov: TextureOverlay = PhotoOverlay(bmp, overlayDurationUs)
                        listOf(OverlayEffect(ImmutableList.of(ov)))
                    }
                    ?: emptyList()
                seg.file to overlays
            }
            Handler(Looper.getMainLooper()).post { startTransform(prepared, output, callback) }
        }
    }

    private fun startTransform(
        prepared: List<Pair<File, List<Effect>>>,
        output: File,
        callback: Callback
    ) {
        try {
            val items = prepared.map { (file, videoEffects) ->
                val builder = EditedMediaItem.Builder(MediaItem.fromUri(Uri.fromFile(file)))
                if (videoEffects.isNotEmpty()) {
                    builder.setEffects(Effects(emptyList(), videoEffects))
                }
                builder.build()
            }
            val sequence = EditedMediaItemSequence(items)
            val composition = Composition.Builder(listOf(sequence)).build()

            val t = Transformer.Builder(context)
                .addListener(object : Transformer.Listener {
                    override fun onCompleted(composition: Composition, result: ExportResult) {
                        callback.onSuccess(output)
                    }
                    override fun onError(
                        composition: Composition,
                        result: ExportResult,
                        exception: ExportException
                    ) {
                        callback.onFailure(exception.message ?: "erreur de montage")
                    }
                })
                .build()
            transformer = t
            t.start(composition, output.absolutePath)
        } catch (e: Throwable) {
            callback.onFailure(e.message ?: "erreur de montage")
        }
    }

    fun cancel() {
        try { transformer?.cancel() } catch (_: Throwable) {}
        transformer = null
    }

    private fun decodeScaled(file: File, targetWidth: Int): Bitmap? {
        if (!file.exists()) return null
        val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
        BitmapFactory.decodeFile(file.absolutePath, bounds)
        var sample = 1
        while (bounds.outWidth / sample > targetWidth * 2) sample *= 2
        val opts = BitmapFactory.Options().apply { inSampleSize = sample }
        val decoded = BitmapFactory.decodeFile(file.absolutePath, opts) ?: return null
        val ratio = targetWidth.toFloat() / decoded.width
        val h = (decoded.height * ratio).toInt().coerceAtLeast(1)
        return Bitmap.createScaledBitmap(decoded, targetWidth, h, true)
    }
}
