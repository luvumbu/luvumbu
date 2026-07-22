package com.frontback.dualcam

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

/** Relance la surveillance (son/secousse) après un redémarrage du téléphone. */
class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action == Intent.ACTION_BOOT_COMPLETED) {
            TriggerService.sync(context)
        }
    }
}
