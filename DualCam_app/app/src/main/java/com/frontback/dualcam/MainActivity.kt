package com.frontback.dualcam

import android.Manifest
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.content.ServiceConnection
import android.content.pm.PackageManager
import android.graphics.Color
import android.hardware.camera2.CameraCharacteristics
import android.hardware.camera2.CameraManager
import android.media.projection.MediaProjectionManager
import android.os.Build
import androidx.camera.camera2.interop.Camera2CameraInfo
import android.os.Bundle
import android.os.Handler
import android.os.IBinder
import android.os.Looper
import android.view.KeyEvent
import android.view.View
import android.view.WindowManager
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.camera.core.CameraSelector
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import com.frontback.dualcam.databinding.ActivityMainBinding
import com.frontback.dualcam.gallery.GalleryActivity
import com.frontback.dualcam.net.GoogleAuth
import com.frontback.dualcam.net.SettingsStore
import kotlinx.coroutines.launch
import java.util.Locale

/**
 * UI : choix caméra, réglages, aperçu (à l'arrêt), mode discret, galerie.
 * L'enregistrement lui-même tourne dans [RecordingService] → continue écran éteint / en arrière-plan.
 */
class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private val settings by lazy { SettingsStore(this) }

    private var cameraProvider: ProcessCameraProvider? = null
    private var preview: Preview? = null
    private var mainFacing = CameraSelector.LENS_FACING_BACK
    private var chosen = false
    private var selectedMainCameraId: String? = null
    private var currentCamLabel = ""
    private val cameraEntries = mutableListOf<Triple<String, Int, String>>()  // id, facing, label

    private var service: RecordingService? = null

    private var pendingCamFacing = ScreenRecordService.CAM_NONE
    private var cornerIndex = 0
    private val cornerNames = arrayOf("bas-droit", "bas-gauche", "haut-droit", "haut-gauche")
    private var camRotIndex = 1   // 90° par défaut (orientation correcte confirmée)
    private val camRotValues = intArrayOf(0, 90, 180, 270)
    private var camModeIndex = 0   // 0 = 1 caméra continu, 1 = photo caméra opposée toutes les 30 s
    private val camModeNames = arrayOf("1 caméra (continu)", "Photo caméra opposée (30 s)")
    private var themeIndex = 0
    private val themeNames = arrayOf("Sombre", "Gris foncé", "Gris clair")
    private val themeBg = intArrayOf(0xFF000000.toInt(), 0xFF1C1C1E.toInt(), 0xFF3A3A3C.toInt())
    private val themeCard = intArrayOf(0xFF222222.toInt(), 0xFF2E2E30.toInt(), 0xFF4A4A4C.toInt())

    private val ui = Handler(Looper.getMainLooper())
    private var tapCount = 0
    private val tapDispatchRunnable = Runnable { dispatchTaps() }
    /** Anti-rebond : une bascule de mode est en cours, on ignore les tapes suivantes. */
    private var switchBusy = false
    /**
     * Aperçu live pendant l'enregistrement. Activé d'office quand on lance depuis l'app ;
     * à l'ouverture d'un enregistrement déjà en cours, il faut appuyer sur « Aperçu »
     * (l'écran reste donc noir tant qu'on ne le demande pas).
     */
    private var livePreviewOn = false

    private val serviceListener = object : RecordingService.Listener {
        override fun onState(recording: Boolean, photoCount: Int) { ui.post { syncUi() } }
        override fun onMontage(active: Boolean) {
            ui.post { binding.processing.visibility = if (active) View.VISIBLE else View.GONE }
        }
        override fun onFinished(message: String) {
            ui.post {
                Toast.makeText(this@MainActivity, message, Toast.LENGTH_LONG).show()
                syncUi()
            }
        }
    }

    private val connection = object : ServiceConnection {
        override fun onServiceConnected(name: ComponentName?, b: IBinder?) {
            service = (b as RecordingService.LocalBinder).service()
            service?.listener = serviceListener
            applySurfaceProvider()
            syncUi()
        }
        override fun onServiceDisconnected(name: ComponentName?) { service = null }
    }

    private val screenLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { res ->
        val data = res.data
        if (res.resultCode == RESULT_OK && data != null) startScreenRecording(res.resultCode, data)
        else Toast.makeText(this, "Autorisation de capture refusée", Toast.LENGTH_SHORT).show()
    }

    /**
     * Autorisation de capture demandée pour un tournage CAMÉRA : elle sert uniquement à
     * capter le son des autres applications (l'écran n'est pas filmé). Refus = micro seul.
     */
    private val camAudioLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { res ->
        val data = res.data
        if (res.resultCode == RESULT_OK && data != null) {
            launchRecording(res.resultCode, data)
        } else {
            Toast.makeText(this, "Son des apps refusé : micro seul", Toast.LENGTH_SHORT).show()
            launchRecording(0, null)
        }
    }

    private val permissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { result ->
        val essential = result[Manifest.permission.CAMERA] == true &&
            result[Manifest.permission.RECORD_AUDIO] == true
        if (essential) onReady()
        else Toast.makeText(this, R.string.permission_needed, Toast.LENGTH_LONG).show()
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)
        binding.preview.implementationMode = PreviewView.ImplementationMode.COMPATIBLE

        binding.chooseBack.setOnClickListener { choose(CameraSelector.LENS_FACING_BACK) }
        binding.chooseFront.setOnClickListener { choose(CameraSelector.LENS_FACING_FRONT) }
        binding.chooseScreen.setOnClickListener { requestScreenCapture() }
        binding.stopScreen.setOnClickListener { stopScreenRecording() }
        binding.recordButton.setOnClickListener { toggleRecording() }
        binding.liveStopButton.setOnClickListener { stopAnyRecording() }
        binding.livePreviewButton.setOnClickListener { toggleLivePreview() }
        binding.switchButton.setOnClickListener { switchCameraIdle() }
        binding.galleryButton.setOnClickListener { startActivity(Intent(this, GalleryActivity::class.java)) }
        binding.signInButton.setOnClickListener { onAccountClicked() }
        binding.settingsButton.setOnClickListener { binding.settingsPanel.visibility = View.VISIBLE }
        binding.activationButton.setOnClickListener { startActivity(Intent(this, ActivationActivity::class.java)) }
        updateAccountStatus()
        binding.closeSettings.setOnClickListener {
            saveSettings()
            binding.settingsPanel.visibility = View.GONE
        }
        binding.discreetCover.setOnLongClickListener { exitDiscreet(); true }
        binding.discreetCover.setOnClickListener { onScreenTap() }
        binding.preview.setOnClickListener { onScreenTap() }
        binding.cornerButton.setOnClickListener {
            cornerIndex = (cornerIndex + 1) % 4
            binding.cornerButton.text = "Coin caméra (écran+cam) : ${cornerNames[cornerIndex]}"
        }
        binding.camRotButton.setOnClickListener {
            camRotIndex = (camRotIndex + 1) % 4
            binding.camRotButton.text = "Rotation caméra incrustée : ${camRotValues[camRotIndex]}°"
        }
        binding.camModeButton.setOnClickListener {
            camModeIndex = (camModeIndex + 1) % camModeNames.size
            binding.camModeButton.text = "Mode caméra : ${camModeNames[camModeIndex]}"
        }
        binding.themeButton.setOnClickListener {
            themeIndex = (themeIndex + 1) % themeNames.size
            applyTheme()
        }
        binding.discreetCheck.setOnCheckedChangeListener { _, checked ->
            if (RecordingService.isRecording) {
                if (checked) enterDiscreet(binding.whiteCheck.isChecked) else exitDiscreet()
            }
        }
        binding.whiteCheck.setOnCheckedChangeListener { _, _ ->
            if (binding.discreetCover.visibility == View.VISIBLE) enterDiscreet(binding.whiteCheck.isChecked)
        }
        // Bascule figé / live pendant l'enregistrement (s'applique en direct).
        binding.freezeCheck.setOnCheckedChangeListener { _, _ -> applySurfaceProvider() }

        loadSettings()
        requestPermissions()
    }

    private val prefs by lazy { getSharedPreferences("dualcam_settings", MODE_PRIVATE) }

    private fun loadSettings() {
        binding.intervalInput.setText(prefs.getInt("interval", 30).toString())
        binding.durationInput.setText(prefs.getInt("duration", 3).toString())
        binding.discreetCheck.isChecked = prefs.getBoolean("discreet", false)
        binding.whiteCheck.isChecked = prefs.getBoolean("white", false)
        binding.freezeCheck.isChecked = prefs.getBoolean("freeze", false)
        binding.volumeCheck.isChecked = prefs.getBoolean("volume", false)
        binding.appAudioCheck.isChecked = prefs.getBoolean("app_audio", false)
        cornerIndex = prefs.getInt("corner", 0).coerceIn(0, 3)
        camRotIndex = prefs.getInt("camrot", 1).coerceIn(0, 3)   // défaut 90°
        // Nouvelle clé (v2) : ignore tout ancien réglage « sans photo » resté bloqué → photo activée d'office.
        camModeIndex = prefs.getInt("cam_mode_v2", 1).coerceIn(0, camModeNames.size - 1)
        themeIndex = prefs.getInt("theme", 0).coerceIn(0, themeNames.size - 1)
        binding.cornerButton.text = "Coin caméra (écran+cam) : ${cornerNames[cornerIndex]}"
        binding.camRotButton.text = "Rotation caméra incrustée : ${camRotValues[camRotIndex]}°"
        binding.camModeButton.text = "Mode caméra : ${camModeNames[camModeIndex]}"
        applyTheme()
    }

    /** Applique l'apparence choisie (fond + carte des réglages). Texte reste blanc et lisible. */
    private fun applyTheme() {
        val bg = themeBg[themeIndex]
        binding.root.setBackgroundColor(bg)
        binding.chooser.setBackgroundColor(bg)
        binding.settingsCard.setBackgroundColor(themeCard[themeIndex])
        binding.themeButton.text = "Apparence : ${themeNames[themeIndex]}"
    }

    private fun saveSettings() {
        prefs.edit()
            .putInt("interval", binding.intervalInput.text.toString().toIntOrNull() ?: 30)
            .putInt("duration", binding.durationInput.text.toString().toIntOrNull() ?: 3)
            .putBoolean("discreet", binding.discreetCheck.isChecked)
            .putBoolean("white", binding.whiteCheck.isChecked)
            .putBoolean("freeze", binding.freezeCheck.isChecked)
            .putBoolean("volume", binding.volumeCheck.isChecked)
            .putBoolean("app_audio", binding.appAudioCheck.isChecked)
            .putInt("corner", cornerIndex)
            .putInt("camrot", camRotIndex)
            .putInt("cam_mode_v2", camModeIndex)
            .putInt("theme", themeIndex)
            .apply()
    }

    private fun requestPermissions() {
        val perms = mutableListOf(Manifest.permission.CAMERA, Manifest.permission.RECORD_AUDIO)
        if (Build.VERSION.SDK_INT >= 33) perms.add(Manifest.permission.POST_NOTIFICATIONS)
        // Localisation (facultative) : géolocalise les vidéos. Un refus ne bloque pas l'app.
        perms.add(Manifest.permission.ACCESS_FINE_LOCATION)
        perms.add(Manifest.permission.ACCESS_COARSE_LOCATION)
        val missing = perms.any {
            ContextCompat.checkSelfPermission(this, it) != PackageManager.PERMISSION_GRANTED
        }
        if (missing) permissionLauncher.launch(perms.toTypedArray()) else onReady()
    }

    private fun onReady() {
        val future = ProcessCameraProvider.getInstance(this)
        future.addListener({
            cameraProvider = future.get()
            binding.chooseBack.isEnabled =
                cameraProvider?.hasCamera(CameraSelector.DEFAULT_BACK_CAMERA) == true
            binding.chooseFront.isEnabled =
                cameraProvider?.hasCamera(CameraSelector.DEFAULT_FRONT_CAMERA) == true
            enumerateCameras()
            if (chosen) syncUi()
        }, ContextCompat.getMainExecutor(this))
    }

    /** Liste TOUS les objectifs, y compris les sous-objectifs physiques cachés. */
    private fun enumerateCameras() {
        cameraEntries.clear()
        val cm = getSystemService(CameraManager::class.java) ?: return
        val seen = HashSet<String>()
        try {
            for (id in cm.cameraIdList) {
                addCam(cm, id, seen, sub = false)
                // Sous-objectifs physiques cachés derrière la caméra logique (API 28+).
                if (Build.VERSION.SDK_INT >= 28) {
                    try {
                        for (pid in cm.getCameraCharacteristics(id).physicalCameraIds) {
                            addCam(cm, pid, seen, sub = true)
                        }
                    } catch (_: Throwable) {}
                }
            }
        } catch (_: Throwable) {}
    }

    private fun addCam(cm: CameraManager, id: String, seen: HashSet<String>, sub: Boolean) {
        if (!seen.add(id)) return
        try {
            val ch = cm.getCameraCharacteristics(id)
            val facing = ch.get(CameraCharacteristics.LENS_FACING)
            val focal = ch.get(CameraCharacteristics.LENS_INFO_AVAILABLE_FOCAL_LENGTHS)?.firstOrNull()
            val facingStr = when (facing) {
                CameraCharacteristics.LENS_FACING_BACK -> "arrière"
                CameraCharacteristics.LENS_FACING_FRONT -> "avant"
                else -> "externe"
            }
            val focalStr = if (focal != null) " · %.1f mm".format(focal) else ""
            val prefix = if (sub) "Sous-objectif" else "Objectif"
            cameraEntries.add(Triple(id, facing ?: -1, "$prefix $id · $facingStr$focalStr"))
        } catch (_: Throwable) {}
    }

    private fun showLensDialog() {
        if (cameraEntries.isEmpty()) {
            Toast.makeText(this, "Aucun objectif détecté", Toast.LENGTH_SHORT).show()
            return
        }
        val labels = cameraEntries.map { it.third }.toTypedArray()
        androidx.appcompat.app.AlertDialog.Builder(this)
            .setTitle(R.string.choose_lens)
            .setItems(labels) { _, which ->
                val (id, facing, label) = cameraEntries[which]
                selectedMainCameraId = id
                mainFacing = if (facing == CameraCharacteristics.LENS_FACING_FRONT)
                    CameraSelector.LENS_FACING_FRONT else CameraSelector.LENS_FACING_BACK
                currentCamLabel = "🎥 $label"
                chosen = true
                saveLaunchMode()
                binding.chooser.visibility = View.GONE
                binding.recordButton.visibility = View.VISIBLE
                binding.switchButton.visibility = View.VISIBLE
                binding.statusText.visibility = View.VISIBLE
                binding.statusText.text = currentCamLabel
                bindPreview()
            }
            .show()
    }

    private fun choose(facing: Int) {
        mainFacing = facing
        selectedMainCameraId = null
        currentCamLabel = if (facing == CameraSelector.LENS_FACING_BACK) "🎥 Caméra arrière" else "🎥 Caméra avant"
        chosen = true
        saveLaunchMode()
        binding.chooser.visibility = View.GONE
        binding.recordButton.visibility = View.VISIBLE
        binding.switchButton.visibility = View.VISIBLE
        binding.statusText.visibility = View.VISIBLE
        syncUi()
    }

    private fun switchCameraIdle() {
        if (RecordingService.isRecording) return
        mainFacing = otherFacing()
        selectedMainCameraId = null   // retour à la caméra par défaut de l'orientation
        currentCamLabel = if (mainFacing == CameraSelector.LENS_FACING_BACK) "🎥 Caméra arrière" else "🎥 Caméra avant"
        saveLaunchMode()
        bindPreview()
        binding.statusText.text = currentCamLabel
    }

    /** Mémorise la caméra/objectif choisi pour que le lancement rapide (tuile + déclencheurs) le reprenne. */
    private fun saveLaunchMode() {
        prefs.edit()
            .putInt("launch_facing", mainFacing)
            .putString("launch_cam_id", selectedMainCameraId ?: "")
            .apply()
    }

    /** Sélecteur : par ID d'objectif si choisi, sinon par orientation. */
    private fun mainSelector(): CameraSelector {
        val id = selectedMainCameraId
        return if (id != null) {
            CameraSelector.Builder().addCameraFilter { infos ->
                infos.filter { Camera2CameraInfo.from(it).cameraId == id }
            }.build()
        } else {
            CameraSelector.Builder().requireLensFacing(mainFacing).build()
        }
    }

    private fun otherFacing() =
        if (mainFacing == CameraSelector.LENS_FACING_BACK)
            CameraSelector.LENS_FACING_FRONT else CameraSelector.LENS_FACING_BACK

    /** Aperçu : uniquement quand on n'enregistre pas (le service possède la caméra sinon). */
    private fun bindPreview() {
        if (RecordingService.isRecording || !chosen) return
        val provider = cameraProvider ?: return
        preview = Preview.Builder().build().also { it.setSurfaceProvider(binding.preview.surfaceProvider) }
        try {
            provider.unbindAll()
            provider.bindToLifecycle(this, mainSelector(), preview)
        } catch (t: Throwable) {
            if (selectedMainCameraId != null) {
                // Objectif non ouvrable directement (souvent un sous-objectif) → caméra par défaut.
                Toast.makeText(this, "Objectif non ouvrable directement, retour caméra par défaut", Toast.LENGTH_LONG).show()
                selectedMainCameraId = null
                currentCamLabel = if (mainFacing == CameraSelector.LENS_FACING_BACK) "🎥 Caméra arrière" else "🎥 Caméra avant"
                binding.statusText.text = currentCamLabel
                try {
                    provider.unbindAll()
                    provider.bindToLifecycle(this, mainSelector(), preview)
                } catch (_: Throwable) {}
            } else {
                Toast.makeText(this, "Caméra indisponible : ${t.message}", Toast.LENGTH_LONG).show()
            }
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Démarrage / arrêt (délégués au service)
    // ---------------------------------------------------------------------------------------------

    private fun toggleRecording() {
        if (RecordingService.isRecording) stopRecording() else startRecording()
    }

    /**
     * Démarre le tournage caméra. Si l'option « son des autres apps » est cochée, il faut
     * d'abord obtenir l'autorisation de capture (c'est elle qui donne accès au son joué par
     * TikTok & co) ; l'enregistrement démarre ensuite dans le callback.
     */
    private fun startRecording() {
        binding.settingsPanel.visibility = View.GONE
        if (binding.appAudioCheck.isChecked && Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            val mpm = getSystemService(MediaProjectionManager::class.java)
            camAudioLauncher.launch(mpm.createScreenCaptureIntent())
            return
        }
        launchRecording(0, null)
    }

    /** @param projData autorisation de capture (son des apps), ou null pour un micro seul. */
    private fun launchRecording(projCode: Int, projData: Intent?) {
        // Coupe la surveillance (son) pour libérer le micro pendant l'enregistrement.
        startService(Intent(this, TriggerService::class.java).setAction(TriggerService.ACTION_STOP))
        // Libère la caméra de l'aperçu pour la confier au service.
        try { cameraProvider?.unbindAll() } catch (_: Throwable) {}

        val intent = Intent(this, RecordingService::class.java).apply {
            action = RecordingService.ACTION_START
            putExtra(RecordingService.EXTRA_FACING, mainFacing)
            putExtra(RecordingService.EXTRA_INTERVAL, currentIntervalMs())
            putExtra(RecordingService.EXTRA_DURATION, readOverlayDurationUs())
            putExtra(RecordingService.EXTRA_CAM_ID, selectedMainCameraId)
            putExtra(RecordingService.EXTRA_OPP_PHOTO, camModeIndex == 1)
            if (projData != null) {
                putExtra(RecordingService.EXTRA_APP_AUDIO, true)
                putExtra(RecordingService.EXTRA_PROJ_CODE, projCode)
                putExtra(RecordingService.EXTRA_PROJ_DATA, projData)
            }
        }
        ContextCompat.startForegroundService(this, intent)
        // Lancement depuis l'app : on montre l'image d'emblée (sauf case « figer »).
        livePreviewOn = true
        binding.livePreviewButton.setText(R.string.live_preview_on)
        applySurfaceProvider()

        if (binding.discreetCheck.isChecked) enterDiscreet(binding.whiteCheck.isChecked)
        ui.postDelayed({ syncUi() }, 400)
    }

    private fun stopRecording() {
        val s = service
        if (s != null) s.stopByUser()
        else startService(Intent(this, RecordingService::class.java).setAction(RecordingService.ACTION_STOP))
    }

    /** Arrête ce qui tourne : tournage caméra ou capture d'écran. */
    private fun stopAnyRecording() {
        when {
            RecordingService.isRecording -> stopRecording()
            ScreenRecordService.isRecording -> stopScreenRecording()
        }
        syncUi()
    }

    /** Bouton « Aperçu » du bandeau : affiche ou masque l'image live pendant l'enregistrement. */
    private fun toggleLivePreview() {
        livePreviewOn = !livePreviewOn
        binding.livePreviewButton.setText(
            if (livePreviewOn) R.string.live_preview_on else R.string.live_preview_off
        )
        applySurfaceProvider()
    }

    /**
     * Donne (ou retire) l'aperçu de l'enregistrement au service :
     * - bouton « Aperçu » non activé, ou case « figer » cochée → aucune surface → l'écran
     *   reste figé sur la dernière image
     * - sinon → aperçu live pendant l'enregistrement
     */
    private fun applySurfaceProvider() {
        val sp = if (binding.freezeCheck.isChecked || !livePreviewOn) null
                 else binding.preview.surfaceProvider
        service?.setSurfaceProvider(sp)
    }

    // ---------------------------------------------------------------------------------------------
    // Capture d'écran (screencast)
    // ---------------------------------------------------------------------------------------------

    private fun requestScreenCapture() {
        val options = arrayOf(
            getString(R.string.screen_only),
            getString(R.string.screen_front),
            getString(R.string.screen_back)
        )
        androidx.appcompat.app.AlertDialog.Builder(this)
            .setTitle(R.string.film_screen)
            .setItems(options) { _, which ->
                pendingCamFacing = when (which) {
                    1 -> CameraSelector.LENS_FACING_FRONT
                    2 -> CameraSelector.LENS_FACING_BACK
                    else -> ScreenRecordService.CAM_NONE
                }
                val mpm = getSystemService(MediaProjectionManager::class.java)
                screenLauncher.launch(mpm.createScreenCaptureIntent())
            }
            .show()
    }

    private fun startScreenRecording(code: Int, data: Intent) {
        val m = resources.displayMetrics
        val intent = Intent(this, ScreenRecordService::class.java).apply {
            action = ScreenRecordService.ACTION_START
            putExtra(ScreenRecordService.EXTRA_CODE, code)
            putExtra(ScreenRecordService.EXTRA_DATA, data)
            putExtra(ScreenRecordService.EXTRA_W, m.widthPixels)
            putExtra(ScreenRecordService.EXTRA_H, m.heightPixels)
            putExtra(ScreenRecordService.EXTRA_DPI, m.densityDpi)
            putExtra(ScreenRecordService.EXTRA_CAM_FACING, pendingCamFacing)
            putExtra(ScreenRecordService.EXTRA_CORNER, cornerIndex)
            putExtra(ScreenRecordService.EXTRA_CAM_ROT, camRotValues[camRotIndex])
        }
        ContextCompat.startForegroundService(this, intent)
        Toast.makeText(this, "Enregistrement de l'écran démarré", Toast.LENGTH_SHORT).show()
        binding.chooser.visibility = View.GONE
        binding.screenRecPanel.visibility = View.VISIBLE
    }

    private fun stopScreenRecording() {
        startService(Intent(this, ScreenRecordService::class.java).setAction(ScreenRecordService.ACTION_STOP))
        binding.screenRecPanel.visibility = View.GONE
        Toast.makeText(this, "Capture d'écran enregistrée ✔ (voir galerie)", Toast.LENGTH_LONG).show()
        if (!chosen) binding.chooser.visibility = View.VISIBLE
    }

    /** Reflète l'état courant du service dans l'UI. */
    private fun syncUi() {
        val screenRec = ScreenRecordService.isRecording
        val camRec = RecordingService.isRecording

        // Enregistrement lancé hors de l'app (tuile, secousse, volume…) : on adopte sa caméra
        // pour que l'aperçu et la bascule repartent du bon état.
        if (camRec && !chosen) {
            mainFacing = RecordingService.currentFacing
            selectedMainCameraId = null
            currentCamLabel = "🎥 ${CamCycle.label(CamCycle.current(this))}"
            chosen = true
        }

        // Bandeau « en direct » : visible dès l'ouverture de l'app si un enregistrement tourne
        // (même lancé depuis la tuile, une secousse ou les boutons de volume).
        binding.livePanel.visibility = if (camRec || screenRec) View.VISIBLE else View.GONE
        // L'aperçu n'a de sens que pour un tournage caméra.
        binding.livePreviewButton.visibility = if (camRec) View.VISIBLE else View.GONE
        if (camRec || screenRec) binding.chooser.visibility = View.GONE

        if (screenRec) {
            binding.screenRecPanel.visibility = View.VISIBLE
            startTimer()
            return
        }
        binding.screenRecPanel.visibility = View.GONE
        binding.timer.visibility = if (camRec) View.VISIBLE else View.INVISIBLE
        binding.recordButton.isSelected = camRec
        binding.recordButton.visibility = if (chosen || camRec) View.VISIBLE else View.INVISIBLE
        binding.switchButton.visibility = if (camRec || !chosen) View.GONE else View.VISIBLE
        binding.statusText.visibility = View.VISIBLE
        binding.statusText.text =
            if (camRec) getString(R.string.photo_count, RecordingService.photoCount)
            else currentCamLabel.ifEmpty { getString(R.string.photo_count, 0) }
        binding.processing.visibility = if (RecordingService.isMontaging) View.VISIBLE else View.GONE
        if (camRec) startTimer() else bindPreview()
    }

    // ---------------------------------------------------------------------------------------------
    // Tapes sur l'écran : 3 = bascule avant → arrière → écran ; 5 = mode discret
    // ---------------------------------------------------------------------------------------------

    /**
     * Compte les tapes d'une même rafale. On ne décide qu'à la FIN de la rafale (aucune tape
     * pendant [TAP_GAP_MS]) : sinon les 3 premières tapes d'une série de 5 déclencheraient la
     * bascule de caméra. 5 étant le maximum défini, on peut agir sans attendre à la 5e.
     */
    private fun onScreenTap() {
        tapCount++
        ui.removeCallbacks(tapDispatchRunnable)
        if (tapCount >= 5) dispatchTaps() else ui.postDelayed(tapDispatchRunnable, TAP_GAP_MS)
    }

    private fun dispatchTaps() {
        val count = tapCount
        tapCount = 0
        ui.removeCallbacks(tapDispatchRunnable)
        when {
            count >= 5 -> toggleDiscreet()
            count == 3 -> cycleMode()
        }
    }

    /** 5 tapes = masquer / rétablir l'écran (l'enregistrement CONTINUE). */
    private fun toggleDiscreet() {
        if (!RecordingService.isRecording) return
        if (binding.discreetCover.visibility == View.VISIBLE) {
            exitDiscreet()
            Toast.makeText(this, "Écran rétabli ✔", Toast.LENGTH_SHORT).show()
        } else {
            enterDiscreet(binding.whiteCheck.isChecked)
        }
    }

    /**
     * 3 tapes = caméra suivante : avant ↔ arrière.
     *
     * - pendant un tournage : bascule à chaud, l'enregistrement CONTINUE (les fragments sont
     *   recollés au montage) ;
     * - à l'arrêt : change simplement la caméra sélectionnée et l'aperçu.
     *
     * La capture d'écran est hors du cycle (Android réclamerait son autorisation à chaque
     * bascule) : pendant une capture d'écran, les 3 tapes ne font rien.
     */
    private fun cycleMode() {
        if (switchBusy || ScreenRecordService.isRecording) return
        switchBusy = true
        ui.postDelayed({ switchBusy = false }, SWITCH_COOLDOWN_MS)

        val next = CamCycle.next(this)
        val facing = CamCycle.facingOf(next)
        Toast.makeText(this, "→ ${CamCycle.label(next)}", Toast.LENGTH_SHORT).show()
        if (RecordingService.isRecording) {
            mainFacing = facing
            selectedMainCameraId = null
            saveLaunchMode()
            RecordingService.requestSwitch(this, facing)
        } else {
            choose(facing)   // à l'arrêt : simple changement de caméra + aperçu
        }
    }

    private fun enterDiscreet(white: Boolean) {
        val wasHidden = binding.discreetCover.visibility != View.VISIBLE
        binding.discreetCover.setBackgroundColor(if (white) Color.WHITE else Color.BLACK)
        binding.discreetCover.visibility = View.VISIBLE
        val lp = window.attributes
        lp.screenBrightness = if (white) 1.0f else 0.02f
        window.attributes = lp
        if (wasHidden) Toast.makeText(this, R.string.discreet_hint, Toast.LENGTH_LONG).show()
    }

    private fun exitDiscreet() {
        if (binding.discreetCover.visibility != View.VISIBLE) return
        binding.discreetCover.visibility = View.GONE
        val lp = window.attributes
        lp.screenBrightness = WindowManager.LayoutParams.BRIGHTNESS_OVERRIDE_NONE
        window.attributes = lp
    }

    /** Chronomètre + point rouge clignotant du bandeau « en direct ». */
    private val timerRunnable = object : Runnable {
        override fun run() {
            val camRec = RecordingService.isRecording
            if (!camRec && !ScreenRecordService.isRecording) {
                binding.liveDot.alpha = 1f
                return
            }
            binding.liveDot.alpha = if (binding.liveDot.alpha > 0.5f) 0.3f else 1f
            if (camRec) {
                val s = (System.currentTimeMillis() - RecordingService.startedAtMs) / 1000
                binding.timer.text = String.format(Locale.US, "● %02d:%02d", s / 60, s % 60)
            }
            ui.postDelayed(this, 500)
        }
    }

    private fun startTimer() {
        ui.removeCallbacks(timerRunnable)   // une seule boucle à la fois
        ui.post(timerRunnable)
    }

    private fun currentIntervalMs(): Long {
        val seconds = binding.intervalInput.text.toString().toLongOrNull()?.coerceAtLeast(3) ?: 30
        return seconds * 1000
    }

    private fun readOverlayDurationUs(): Long {
        val seconds = binding.durationInput.text.toString().toLongOrNull()?.coerceAtLeast(1) ?: 3
        return seconds * 1_000_000
    }

    /** Boutons de volume = démarrer/arrêter (si activé dans les réglages, mode caméra). */
    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        // Si le double-clic volume (service d'accessibilité) est actif, on le laisse tout gérer
        // pour éviter un double déclenchement.
        if (prefs.getBoolean("vol_ctrl", false)) return super.onKeyDown(keyCode, event)
        if (binding.volumeCheck.isChecked && chosen &&
            (keyCode == KeyEvent.KEYCODE_VOLUME_UP || keyCode == KeyEvent.KEYCODE_VOLUME_DOWN)
        ) {
            if (event?.repeatCount == 0) toggleRecording()
            return true  // consomme l'événement (n'ajuste pas le volume)
        }
        return super.onKeyDown(keyCode, event)
    }

    override fun onStart() {
        super.onStart()
        bindService(Intent(this, RecordingService::class.java), connection, Context.BIND_AUTO_CREATE)
    }

    override fun onResume() {
        super.onResume()
        if (chosen || ScreenRecordService.isRecording) syncUi()
        updateAccountStatus()
        // Relance la surveillance (son/secousse) si activée et qu'on ne filme pas.
        if (!RecordingService.isRecording) TriggerService.sync(this)
    }

    /** Connexion Google (si non connecté) ou proposition de déconnexion (si connecté). */
    private fun onAccountClicked() {
        if (settings.isLoggedIn) {
            androidx.appcompat.app.AlertDialog.Builder(this)
                .setMessage(getString(R.string.connected_as, settings.username.ifBlank { "compte Google" }))
                .setPositiveButton(R.string.sign_out) { _, _ ->
                    settings.logout()
                    Toast.makeText(this, "Déconnecté", Toast.LENGTH_SHORT).show()
                    // Retour à l'écran de connexion obligatoire.
                    startActivity(Intent(this, AuthActivity::class.java))
                    finish()
                }
                .setNegativeButton(R.string.close, null)
                .show()
            return
        }
        binding.signInButton.isEnabled = false
        lifecycleScope.launch {
            val auth = GoogleAuth.ensureLoggedIn(this@MainActivity, settings)
            Toast.makeText(this@MainActivity,
                if (auth.ok) "Connecté ✓" else (auth.error ?: "Connexion échouée"),
                Toast.LENGTH_LONG).show()
            binding.signInButton.isEnabled = true
            updateAccountStatus()
        }
    }

    /** Met à jour le libellé du bouton et le statut du compte sur l'écran d'accueil. */
    private fun updateAccountStatus() {
        if (settings.isLoggedIn) {
            binding.signInButton.text = getString(R.string.sign_out)
            val base = getString(R.string.connected_as, settings.username.ifBlank { "compte Google" })
            // Les envois se font en tâche de fond : on affiche le dernier échec ici, sinon
            // rien n'arrive sur le serveur sans que l'écran d'accueil ne le signale.
            val err = settings.lastUploadError
            binding.accountStatus.text = if (err.isBlank()) base else "$base\n⚠️ Dernier envoi : $err"
        } else {
            binding.signInButton.text = getString(R.string.sign_in_google)
            binding.accountStatus.text = getString(R.string.not_connected)
        }
    }

    override fun onStop() {
        super.onStop()
        saveSettings()
        exitDiscreet()
        service?.setSurfaceProvider(null)   // la surface de l'aperçu est détruite
        service?.listener = null
        try { unbindService(connection) } catch (_: Throwable) {}
        service = null
    }

    companion object {
        /** Fin de rafale : au-delà de ce délai sans tape, on décide de l'action (3 ou 5 tapes). */
        private const val TAP_GAP_MS = 600L
        private const val SWITCH_COOLDOWN_MS = 1_500L
    }
}
