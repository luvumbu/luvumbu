package com.frontback.dualcam

import android.service.quicksettings.TileService

/**
 * Tuile « DualCam » dans le volet Réglages rapides : 1 appui = démarrer l'enregistrement.
 * L'utilisateur ajoute la tuile via « Modifier les tuiles » de son téléphone.
 */
class QuickTileService : TileService() {
    override fun onClick() {
        super.onClick()
        // Démarre l'enregistrement avec les réglages sauvegardés.
        RecordingService.startFromTrigger(applicationContext)
    }
}
