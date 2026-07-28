package com.frontback.dualcam

import android.annotation.SuppressLint
import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import android.widget.Button
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import com.frontback.dualcam.net.SettingsStore

/**
 * Écran dédié « Modes d'activation » : l'utilisateur choisit (par bouton) comment
 * DualCam peut démarrer l'enregistrement tout seul en arrière-plan.
 */
class ActivationActivity : AppCompatActivity() {

    private val prefs by lazy { getSharedPreferences("dualcam_settings", MODE_PRIVATE) }
    private val sensNames = arrayOf("Faible", "Moyen", "Élevé")
    private val volBtnNames = arrayOf("Volume bas", "Volume haut", "N'importe lequel")

    private lateinit var soundBtn: Button
    private lateinit var sensBtn: Button
    private lateinit var shakeBtn: Button
    private lateinit var screenshotBtn: Button
    private lateinit var remoteBtn: Button
    private lateinit var volCtrlBtn: Button
    private lateinit var volSchemeBtn: Button
    private lateinit var volSchemeHint: android.widget.TextView
    private lateinit var volButtonBtn: Button
    private lateinit var volAccessBtn: Button
    private lateinit var saveBattBtn: Button
    private lateinit var lowBattBtn: Button

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_activation)

        soundBtn = findViewById(R.id.soundButton)
        sensBtn = findViewById(R.id.sensitivityButton)
        shakeBtn = findViewById(R.id.shakeButton)
        screenshotBtn = findViewById(R.id.screenshotButton)
        remoteBtn = findViewById(R.id.remoteButton)
        volCtrlBtn = findViewById(R.id.volCtrlButton)
        volSchemeBtn = findViewById(R.id.volSchemeButton)
        volSchemeHint = findViewById(R.id.volSchemeHint)
        volButtonBtn = findViewById(R.id.volButtonButton)
        volAccessBtn = findViewById(R.id.volAccessButton)
        saveBattBtn = findViewById(R.id.saveBattButton)
        lowBattBtn = findViewById(R.id.lowBattButton)

        soundBtn.setOnClickListener {
            val on = !prefs.getBoolean("trig_sound", false)
            prefs.edit().putBoolean("trig_sound", on).apply()
            if (on) ensureMicPermission()
            refresh(); applyWatch()
        }
        sensBtn.setOnClickListener {
            val next = (prefs.getInt("trig_sensitivity", 1) + 1) % 3
            prefs.edit().putInt("trig_sensitivity", next).apply()
            refresh(); applyWatch()
        }
        shakeBtn.setOnClickListener {
            val on = !prefs.getBoolean("trig_shake", false)
            prefs.edit().putBoolean("trig_shake", on).apply()
            refresh(); applyWatch()
        }
        screenshotBtn.setOnClickListener {
            val on = !prefs.getBoolean("trig_screenshot", false)
            prefs.edit().putBoolean("trig_screenshot", on).apply()
            if (on) ensureImagesPermission()
            refresh(); applyWatch()
        }
        remoteBtn.setOnClickListener {
            val on = !prefs.getBoolean("trig_remote", false)
            prefs.edit().putBoolean("trig_remote", on).apply()
            if (on && !SettingsStore(this).isLoggedIn) {
                Toast.makeText(this, "Connecte-toi d'abord (compte Google) pour piloter à distance.", Toast.LENGTH_LONG).show()
            }
            // Déclenchement en arrière-plan fiable : sortir l'app de l'économie de batterie,
            // sinon Android tue le service d'écoute et plus rien ne se déclenche.
            if (on) ensureBatteryExemption()
            refresh(); applyWatch()
        }
        volCtrlBtn.setOnClickListener {
            val on = !prefs.getBoolean("vol_ctrl", false)
            prefs.edit().putBoolean("vol_ctrl", on).apply()
            if (on && !VolumeKeyService.isEnabled(this)) promptAccessibility()
            refresh()
        }
        volSchemeBtn.setOnClickListener {
            val next = (prefs.getInt("vol_scheme", VolumeKeyService.SCHEME_SIMPLE) + 1) % 2
            prefs.edit().putInt("vol_scheme", next).apply()
            refresh()
        }
        volButtonBtn.setOnClickListener {
            val next = (prefs.getInt("vol_button", 2) + 1) % 3
            prefs.edit().putInt("vol_button", next).apply()
            refresh()
        }
        volAccessBtn.setOnClickListener { openAccessibilitySettings() }
        saveBattBtn.setOnClickListener {
            val on = !prefs.getBoolean("trig_savebatt", true)
            prefs.edit().putBoolean("trig_savebatt", on).apply()
            refresh(); applyWatch()
        }
        lowBattBtn.setOnClickListener {
            val on = !prefs.getBoolean("trig_lowbatt", true)
            prefs.edit().putBoolean("trig_lowbatt", on).apply()
            refresh(); applyWatch()
        }
        findViewById<Button>(R.id.closeActivation).setOnClickListener { finish() }

        refresh()
    }

    override fun onResume() {
        super.onResume()
        refresh()   // met à jour l'état des permissions au retour des réglages système
    }

    private fun refresh() {
        val sound = prefs.getBoolean("trig_sound", false)
        val shake = prefs.getBoolean("trig_shake", false)
        val screenshot = prefs.getBoolean("trig_screenshot", false)
        val remote = prefs.getBoolean("trig_remote", false)
        val volCtrl = prefs.getBoolean("vol_ctrl", false)
        val volBtn = prefs.getInt("vol_button", 2).coerceIn(0, 2)
        val saveB = prefs.getBoolean("trig_savebatt", true)
        val lowB = prefs.getBoolean("trig_lowbatt", true)
        val sens = prefs.getInt("trig_sensitivity", 1).coerceIn(0, 2)

        soundBtn.text = "Son (cri) : ${onOff(sound)}"
        sensBtn.text = "Sensibilité du son : ${sensNames[sens]}"
        shakeBtn.text = "Secousse : ${onOff(shake)}"
        screenshotBtn.text = when {
            !screenshot -> "Capture d'écran : Désactivé"
            !hasFullImagesAccess() -> "Capture d'écran : ⚠️ autorise TOUTES les photos"
            else -> "Capture d'écran : Activé ✅"
        }
        remoteBtn.text = when {
            !remote -> "Déclenchement à distance : Désactivé"
            !SettingsStore(this).isLoggedIn -> "À distance : ⚠️ connecte-toi (compte Google)"
            else -> "Déclenchement à distance : Activé ✅"
        }
        val accessOn = VolumeKeyService.isEnabled(this)
        volCtrlBtn.text = when {
            !volCtrl -> "Contrôle par volume : Désactivé"
            !accessOn -> "Contrôle par volume : ⚠️ active l'accessibilité"
            else -> "Contrôle par volume : Activé ✅"
        }

        val upDown = prefs.getInt("vol_scheme", VolumeKeyService.SCHEME_SIMPLE) ==
            VolumeKeyService.SCHEME_UP_DOWN
        volSchemeBtn.text = if (upDown) "Commandes : Haut / Bas séparés"
            else "Commandes : Un seul bouton"
        volSchemeBtn.isEnabled = volCtrl
        volSchemeHint.text = if (upDown)
            "Haut ×2 = démarrer / arrêter · Bas ×1 = changer de caméra (pendant l'enregistrement) · Bas ×2 = arrêter"
        else
            "×2 = démarrer / arrêter · ×3 = changer de caméra"

        // Le choix du bouton n'a de sens qu'avec le schéma « un seul bouton ».
        volButtonBtn.text = "Bouton : ${volBtnNames[volBtn]}"
        volButtonBtn.isEnabled = volCtrl && !upDown
        volAccessBtn.text = if (accessOn) "Service d'accessibilité : Activé ✅"
            else "▶ Activer le service d'accessibilité"
        saveBattBtn.text = "Économie batterie : ${onOff(saveB)}"
        lowBattBtn.text = "Couper si batterie < 15 % : ${onOff(lowB)}"
    }

    private fun onOff(b: Boolean) = if (b) "Activé ✅" else "Désactivé"

    /** (Re)démarre ou arrête le service de surveillance selon les modes activés. */
    private fun applyWatch() = TriggerService.sync(this)

    /**
     * Demande à sortir DualCam de l'optimisation batterie : sans ça, Android met le service
     * d'écoute en veille (ou le tue) et le déclenchement en arrière-plan devient aléatoire.
     * N'affiche la fenêtre système que si l'app n'est pas déjà exemptée.
     */
    private fun ensureBatteryExemption() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return
        val pm = getSystemService(POWER_SERVICE) as? android.os.PowerManager ?: return
        if (pm.isIgnoringBatteryOptimizations(packageName)) return
        try {
            @SuppressLint("BatteryLife")
            val i = Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS)
                .setData(Uri.parse("package:$packageName"))
            startActivity(i)
        } catch (_: Throwable) {
            // Certains constructeurs bloquent cette fenêtre : on ouvre les réglages génériques.
            try { startActivity(Intent(Settings.ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS)) } catch (_: Throwable) {}
        }
    }

    private fun ensureMicPermission() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO)
            != PackageManager.PERMISSION_GRANTED
        ) {
            ActivityCompat.requestPermissions(this, arrayOf(Manifest.permission.RECORD_AUDIO), 77)
        }
    }

    /** Accès COMPLET aux images requis (l'accès « partiel » d'Android 14+ ne suffit pas :
     *  une capture d'écran prise ensuite serait invisible pour l'app). */
    private fun hasFullImagesAccess(): Boolean {
        val perm = if (Build.VERSION.SDK_INT >= 33) Manifest.permission.READ_MEDIA_IMAGES
        else Manifest.permission.READ_EXTERNAL_STORAGE
        return ContextCompat.checkSelfPermission(this, perm) == PackageManager.PERMISSION_GRANTED
    }

    private fun ensureImagesPermission() {
        if (hasFullImagesAccess()) return
        val perm = if (Build.VERSION.SDK_INT >= 33) Manifest.permission.READ_MEDIA_IMAGES
        else Manifest.permission.READ_EXTERNAL_STORAGE
        ActivityCompat.requestPermissions(this, arrayOf(perm), 78)
    }

    /** Explique puis ouvre les réglages d'accessibilité pour activer le contrôle volume. */
    private fun promptAccessibility() {
        Toast.makeText(
            this,
            "Active « DualCam » dans la liste pour que le double-clic volume fonctionne (même écran éteint).",
            Toast.LENGTH_LONG
        ).show()
        openAccessibilitySettings()
    }

    private fun openAccessibilitySettings() {
        try {
            startActivity(Intent(Settings.ACTION_ACCESSIBILITY_SETTINGS))
        } catch (_: Throwable) {}
    }

    /** Ouvre la page « Autorisations » de l'app pour choisir « Autoriser tout ». */
    private fun openAppSettings() {
        Toast.makeText(
            this,
            "Choisis « Photos et vidéos » → « Autoriser tout » pour la détection de capture d'écran.",
            Toast.LENGTH_LONG
        ).show()
        try {
            startActivity(
                Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS)
                    .setData(Uri.fromParts("package", packageName, null))
            )
        } catch (_: Throwable) {}
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        // Capture d'écran : si l'accès complet n'est pas accordé (refus ou accès partiel),
        // on renvoie l'utilisateur vers les réglages pour « Autoriser tout ».
        if (requestCode == 78 && prefs.getBoolean("trig_screenshot", false) && !hasFullImagesAccess()) {
            openAppSettings()
        }
        refresh()
        applyWatch()
    }
}
