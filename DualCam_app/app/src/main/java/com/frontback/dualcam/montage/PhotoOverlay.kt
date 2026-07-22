package com.frontback.dualcam.montage

import android.graphics.Bitmap
import androidx.media3.effect.BitmapOverlay
import androidx.media3.effect.OverlaySettings

/**
 * Incruste une photo en petit dans le coin bas-droit, visible pendant les [durationUs]
 * premières microsecondes du segment (fenêtre relative à la première image reçue).
 *
 * Repères Media3 : (0,0) = centre, (1,1) = haut-droit, (-1,-1) = bas-gauche.
 * On ancre le coin bas-droit de la photo (1,-1) près du coin bas-droit de la vidéo.
 */
class PhotoOverlay(
    private val bitmap: Bitmap,
    private val durationUs: Long
) : BitmapOverlay() {

    private var firstUs = Long.MIN_VALUE

    private val visible: OverlaySettings = OverlaySettings.Builder()
        .setScale(0.5f, 0.5f)
        .setBackgroundFrameAnchor(0.45f, -0.45f)   // centre de la photo dans le coin bas-droit
        .build()

    private val hidden: OverlaySettings = OverlaySettings.Builder()
        .setScale(0.5f, 0.5f)
        .setBackgroundFrameAnchor(0.45f, -0.45f)
        .setAlphaScale(0f)
        .build()

    override fun getBitmap(presentationTimeUs: Long): Bitmap = bitmap

    override fun getOverlaySettings(presentationTimeUs: Long): OverlaySettings {
        if (firstUs == Long.MIN_VALUE) firstUs = presentationTimeUs
        val elapsed = presentationTimeUs - firstUs
        return if (elapsed in 0 until durationUs) visible else hidden
    }
}
