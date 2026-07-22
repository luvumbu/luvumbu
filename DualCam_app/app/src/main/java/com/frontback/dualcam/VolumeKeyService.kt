package com.frontback.dualcam

import android.accessibilityservice.AccessibilityServiceInfo
import android.content.Context
import android.content.Intent
import android.os.Handler
import android.os.Looper
import android.view.KeyEvent
import android.view.accessibility.AccessibilityEvent
import android.view.accessibility.AccessibilityManager
import android.accessibilityservice.AccessibilityService

/**
 * Service d'accessibilité : capte les boutons de VOLUME même écran éteint / app fermée.
 *
 * Deux schémas de commande, au choix de l'utilisateur (réglage « vol_scheme ») :
 *
 *  - [SCHEME_SIMPLE] (0) — un seul bouton, celui configuré dans « Bouton » :
 *      · 2 clics = démarrer / arrêter
 *      · 3 clics = changer de caméra (avant ↔ arrière)
 *
 *  - [SCHEME_UP_DOWN] (1) — haut et bas séparés :
 *      · 2 clics sur VOLUME HAUT = démarrer (à l'arrêt) / arrêter (pendant l'enregistrement)
 *      · 1 clic sur VOLUME BAS, pendant l'enregistrement = changer de caméra
 *      · 2 clics sur VOLUME BAS = arrêter
 *      · hors enregistrement, VOLUME BAS règle le volume normalement
 *
 * On ne décide qu'à la FIN de la rafale (aucun appui pendant [GAP_MS]) : sinon le 1er clic
 * d'un double-clic déclencherait déjà l'action à 1 clic. Au maximum défini (2 ou 3 clics
 * selon le cas), on agit sans attendre.
 *
 * Nécessite que l'utilisateur active « DualCam » dans Réglages > Accessibilité (une seule fois).
 */
class VolumeKeyService : AccessibilityService() {

    private val prefs by lazy { getSharedPreferences("dualcam_settings", Context.MODE_PRIVATE) }
    private val handler = Handler(Looper.getMainLooper())
    private val dispatchRunnable = Runnable { dispatchClicks() }

    private var clickCount = 0
    private var lastKeyCode = -1
    private var lastDownTime = 0L

    override fun onAccessibilityEvent(event: AccessibilityEvent?) {}
    override fun onInterrupt() {}

    override fun onKeyEvent(event: KeyEvent): Boolean {
        if (!prefs.getBoolean("vol_ctrl", false)) return false
        if (event.action != KeyEvent.ACTION_DOWN) return false
        val code = event.keyCode
        if (code != KeyEvent.KEYCODE_VOLUME_UP && code != KeyEvent.KEYCODE_VOLUME_DOWN) return false

        val scheme = prefs.getInt("vol_scheme", SCHEME_SIMPLE)
        if (scheme == SCHEME_SIMPLE) {
            // Bouton configuré : 0 = Volume bas, 1 = Volume haut, 2 = n'importe lequel.
            val want = prefs.getInt("vol_button", 2)
            val matches = when (want) {
                0 -> code == KeyEvent.KEYCODE_VOLUME_DOWN
                1 -> code == KeyEvent.KEYCODE_VOLUME_UP
                else -> true
            }
            if (!matches) return false
        }

        val now = event.eventTime
        // Rafale rompue (autre bouton, ou trop lent) → on repart de zéro.
        if (code != lastKeyCode || now - lastDownTime > GAP_MS) clickCount = 0
        clickCount++
        lastKeyCode = code
        lastDownTime = now

        val max = maxClicks(scheme, code)
        handler.removeCallbacks(dispatchRunnable)
        if (clickCount >= max) dispatchClicks() else handler.postDelayed(dispatchRunnable, GAP_MS)

        return consumes(scheme, code, clickCount)
    }

    /** Nombre de clics au-delà duquel plus rien de nouveau ne peut être déclenché. */
    private fun maxClicks(scheme: Int, code: Int): Int = when {
        scheme == SCHEME_SIMPLE -> 3
        else -> 2   // haut/bas séparés : rien n'est défini au-delà de 2 clics
    }

    /**
     * Faut-il « manger » cet appui (ne pas laisser le volume bouger) ?
     * On ne consomme que les appuis qui font partie d'une commande de l'app.
     */
    private fun consumes(scheme: Int, code: Int, count: Int): Boolean {
        if (scheme == SCHEME_SIMPLE) return count >= 2   // 1er appui : volume normal
        // Schéma haut/bas : le volume BAS pilote la caméra dès le 1er appui, mais SEULEMENT
        // pendant un enregistrement — sinon il doit régler le volume comme d'habitude.
        val recording = RecordingService.isRecording || ScreenRecordService.isRecording
        return if (code == KeyEvent.KEYCODE_VOLUME_DOWN) recording else count >= 2
    }

    private fun dispatchClicks() {
        val count = clickCount
        val code = lastKeyCode
        clickCount = 0
        lastKeyCode = -1
        handler.removeCallbacks(dispatchRunnable)

        if (prefs.getInt("vol_scheme", SCHEME_SIMPLE) == SCHEME_SIMPLE) {
            when {
                count >= 3 -> cycleCamera()
                count == 2 -> toggleRecording()
            }
            return
        }

        val recording = RecordingService.isRecording || ScreenRecordService.isRecording
        when (code) {
            // VOLUME HAUT : 2 clics = démarrer (à l'arrêt) ou arrêter (en cours).
            KeyEvent.KEYCODE_VOLUME_UP -> if (count >= 2) toggleRecording()
            // VOLUME BAS : 2 clics = arrêter ; 1 clic = changer de caméra (pendant l'enregistrement).
            KeyEvent.KEYCODE_VOLUME_DOWN -> when {
                !recording -> {}                 // à l'arrêt, le volume bas ne fait rien
                count >= 2 -> stopRecording()
                else -> cycleCamera()
            }
        }
    }

    private fun toggleRecording() {
        if (RecordingService.isRecording || ScreenRecordService.isRecording) stopRecording()
        else RecordingService.startFromTrigger(this)
    }

    private fun stopRecording() {
        when {
            RecordingService.isRecording ->
                startService(Intent(this, RecordingService::class.java).setAction(RecordingService.ACTION_STOP))
            ScreenRecordService.isRecording ->
                startService(Intent(this, ScreenRecordService::class.java).setAction(ScreenRecordService.ACTION_STOP))
        }
    }

    /**
     * Caméra suivante : avant ↔ arrière, entièrement en arrière-plan.
     *
     * La capture d'écran reste HORS du cycle : Android exige son autorisation à chaque
     * démarrage, ce qui ferait surgir une boîte de dialogue à chaque bascule.
     */
    private fun cycleCamera() {
        if (ScreenRecordService.isRecording) return
        val facing = CamCycle.facingOf(CamCycle.next(this))
        if (RecordingService.isRecording) {
            // Bascule à chaud : l'enregistrement continue, le montage recolle les fragments.
            RecordingService.requestSwitch(this, facing)
        } else {
            // À l'arrêt : on mémorise simplement la caméra du prochain lancement.
            prefs.edit()
                .putInt("launch_facing", facing)
                .putString("launch_cam_id", "")
                .apply()
        }
    }

    companion object {
        /** 2 clics = start/stop, 3 clics = caméra suivante, sur le bouton configuré. */
        const val SCHEME_SIMPLE = 0
        /** Haut = start/stop (2 clics) ; bas = caméra (1 clic) / arrêt (2 clics). */
        const val SCHEME_UP_DOWN = 1

        /** Fin de rafale : au-delà de ce délai sans nouvel appui, on décide de l'action. */
        private const val GAP_MS = 450L

        /** Vrai si le service d'accessibilité DualCam est actuellement activé par l'utilisateur. */
        fun isEnabled(context: Context): Boolean {
            val am = context.getSystemService(Context.ACCESSIBILITY_SERVICE) as? AccessibilityManager
                ?: return false
            val list = am.getEnabledAccessibilityServiceList(AccessibilityServiceInfo.FEEDBACK_ALL_MASK)
            return list.any { it.id?.contains(VolumeKeyService::class.java.simpleName) == true }
        }
    }
}
