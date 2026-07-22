package com.frontback.dualcam

import android.content.Context
import androidx.camera.core.CameraSelector

/**
 * Cycle de bascule des caméras : AVANT ↔ ARRIÈRE, déclenché par 3 clics (tapes sur l'écran
 * ou boutons de volume) — voir [MainActivity.onScreenTap] et [VolumeKeyService].
 *
 * La capture d'ÉCRAN est volontairement HORS du cycle : Android exige une autorisation
 * système à chaque démarrage de capture (impossible à contourner), donc la faire entrer dans
 * le cycle ferait surgir une boîte de dialogue à chaque bascule. Elle se lance depuis l'app.
 * Pendant une capture d'écran, les 3 clics ne font donc rien.
 *
 * Le mode « courant » est déduit de ce qui tourne réellement ; à l'arrêt, du dernier choix
 * mémorisé.
 */
object CamCycle {

    const val FRONT = 0
    const val BACK = 1
    const val SCREEN = 2

    /** Mode actuellement actif (ou, à l'arrêt, celui qui serait utilisé au prochain lancement). */
    fun current(context: Context): Int = when {
        ScreenRecordService.isRecording -> SCREEN
        RecordingService.isRecording ->
            if (RecordingService.currentFacing == CameraSelector.LENS_FACING_FRONT) FRONT else BACK
        else -> {
            val p = context.getSharedPreferences("dualcam_settings", Context.MODE_PRIVATE)
            if (p.getInt("launch_facing", CameraSelector.LENS_FACING_BACK) ==
                CameraSelector.LENS_FACING_FRONT
            ) FRONT else BACK
        }
    }

    /** Caméra suivante : avant ↔ arrière (l'écran n'entre pas dans le cycle). */
    fun next(context: Context): Int =
        if (current(context) == FRONT) BACK else FRONT

    /** Orientation CameraX correspondant à un mode caméra (indéfini pour [SCREEN]). */
    fun facingOf(mode: Int): Int =
        if (mode == FRONT) CameraSelector.LENS_FACING_FRONT else CameraSelector.LENS_FACING_BACK

    fun label(mode: Int): String = when (mode) {
        FRONT -> "Caméra avant"
        BACK -> "Caméra arrière"
        else -> "Écran"
    }
}
