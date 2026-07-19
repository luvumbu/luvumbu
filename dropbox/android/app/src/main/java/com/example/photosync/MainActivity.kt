package com.example.photosync

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Intent
import android.content.pm.PackageManager
import android.database.ContentObserver
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.provider.MediaStore
import android.widget.Toast
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import androidx.work.WorkInfo
import androidx.work.WorkManager
import com.example.photosync.databinding.ActivityMainBinding
import kotlinx.coroutines.launch

class MainActivity : AppCompatActivity() {

    private lateinit var b: ActivityMainBinding
    private lateinit var settings: SettingsStore

    // Évite que le changement programmatique de l'interrupteur déclenche son écouteur.
    private var suppressAutoListener = false
    // Anti-rafale pour la détection (MediaStore notifie parfois plusieurs fois).
    private var lastDetectMs = 0L
    // Observateur de galerie actuellement enregistré ?
    private var observing = false

    /** Surveille la galerie : signale tout ajout/changement (et envoie si la synchro auto est active). */
    private val galleryObserver = object : ContentObserver(Handler(Looper.getMainLooper())) {
        override fun onChange(selfChange: Boolean) {
            onGalleryChanged()
        }
    }

    private val permLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { _ ->
        // On lance dès qu'au moins un type activé a sa permission accordée.
        if (hasAnyEnabledMediaPermission()) startSync()
        else Toast.makeText(this, "Accès aux fichiers refusé", Toast.LENGTH_LONG).show()
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        settings = SettingsStore(this)

        // Pas connecté → écran de connexion.
        if (!settings.isLoggedIn) {
            startActivity(Intent(this, AuthActivity::class.java))
            finish()
            return
        }

        b = ActivityMainBinding.inflate(layoutInflater)
        setContentView(b.root)

        b.accountText.text = getString(R.string.connected_as, settings.username)
        b.wifiSwitch.isChecked = settings.wifiOnly

        // État initial des interrupteurs (sans déclencher les écouteurs).
        suppressAutoListener = true
        b.autoSyncSwitch.isChecked = settings.autoSync
        b.watchSwitch.isChecked = settings.watchGallery
        suppressAutoListener = false
        if (settings.maxPerSync > 0) b.maxPerSyncInput.setText(settings.maxPerSync.toString())

        // Types de fichiers à synchroniser : état initial puis persistance immédiate.
        b.cbPhotos.isChecked = settings.uploadPhotos
        b.cbVideos.isChecked = settings.uploadVideos
        b.cbAudio.isChecked = settings.uploadAudio
        b.cbPhotos.setOnCheckedChangeListener { _, v -> settings.uploadPhotos = v }
        b.cbVideos.setOnCheckedChangeListener { _, v -> settings.uploadVideos = v }
        b.cbAudio.setOnCheckedChangeListener { _, v -> settings.uploadAudio = v }

        b.saveButton.setOnClickListener {
            if (!ensureTypeSelected()) return@setOnClickListener
            settings.wifiOnly = b.wifiSwitch.isChecked
            settings.autoSync = b.autoSyncSwitch.isChecked
            settings.watchGallery = b.watchSwitch.isChecked
            settings.maxPerSync = b.maxPerSyncInput.text.toString().trim().toIntOrNull() ?: 0
            if (settings.watchGallery) registerGalleryObserver() else unregisterGalleryObserver()
            requestPermissionsThenSync()
        }
        b.syncNowButton.setOnClickListener {
            if (!ensureTypeSelected()) return@setOnClickListener
            settings.wifiOnly = b.wifiSwitch.isChecked
            requestPermissionsThenSync()
        }
        b.stopButton.setOnClickListener { stopSync() }

        // Interrupteur 1 : SYNCHRO AUTOMATIQUE (envoi périodique en arrière-plan).
        b.autoSyncSwitch.setOnCheckedChangeListener { _, checked ->
            if (suppressAutoListener) return@setOnCheckedChangeListener
            settings.autoSync = checked
            if (checked) {
                requestPermissionsThenSync()
            } else {
                UploadWorker.cancelPeriodic(this)
                Toast.makeText(this, R.string.auto_sync_off, Toast.LENGTH_SHORT).show()
            }
        }

        // Interrupteur 2 : SURVEILLER LA GALERIE (détection temps réel + alerte).
        b.watchSwitch.setOnCheckedChangeListener { _, checked ->
            if (suppressAutoListener) return@setOnCheckedChangeListener
            settings.watchGallery = checked
            if (checked) {
                registerGalleryObserver()
                Toast.makeText(this, R.string.watch_on, Toast.LENGTH_SHORT).show()
            } else {
                unregisterGalleryObserver()
                Toast.makeText(this, R.string.watch_off, Toast.LENGTH_SHORT).show()
            }
        }
        b.galleryButton.setOnClickListener {
            startActivity(Intent(this, GalleryActivity::class.java))
        }
        b.cleanupButton.setOnClickListener {
            startActivity(Intent(this, CleanupActivity::class.java))
        }
        b.logoutButton.setOnClickListener { logout() }

        observeWork()
        refreshDbTotal()
    }

    override fun onResume() {
        super.onResume()
        if (::b.isInitialized) {
            refreshDbTotal()
            // On surveille la galerie seulement si l'option est activée.
            if (settings.watchGallery) registerGalleryObserver() else unregisterGalleryObserver()
        }
    }

    override fun onPause() {
        super.onPause()
        unregisterGalleryObserver()
    }

    /** Active la surveillance de la galerie (images + vidéos). */
    private fun registerGalleryObserver() {
        if (observing) return
        contentResolver.registerContentObserver(
            MediaStore.Images.Media.EXTERNAL_CONTENT_URI, true, galleryObserver
        )
        contentResolver.registerContentObserver(
            MediaStore.Video.Media.EXTERNAL_CONTENT_URI, true, galleryObserver
        )
        if (settings.uploadAudio) contentResolver.registerContentObserver(
            MediaStore.Audio.Media.EXTERNAL_CONTENT_URI, true, galleryObserver
        )
        observing = true
    }

    /** Coupe la surveillance de la galerie. */
    private fun unregisterGalleryObserver() {
        if (!observing) return
        contentResolver.unregisterContentObserver(galleryObserver)
        observing = false
    }

    private fun logout() {
        // On vide la mémoire locale des photos envoyées (pour repartir propre).
        lifecycleScope.launch {
            SyncApp.instance.db.uploadedDao().clearAll()
            settings.logout()
            startActivity(Intent(this@MainActivity, AuthActivity::class.java))
            finish()
        }
    }

    private fun requestPermissionsThenSync() {
        val needed = mutableListOf<String>()
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            // On ne demande que les permissions des types réellement activés.
            if (settings.uploadPhotos) needed += Manifest.permission.READ_MEDIA_IMAGES
            if (settings.uploadVideos) needed += Manifest.permission.READ_MEDIA_VIDEO
            if (settings.uploadAudio) needed += Manifest.permission.READ_MEDIA_AUDIO
            needed += Manifest.permission.POST_NOTIFICATIONS
        } else {
            needed += Manifest.permission.READ_EXTERNAL_STORAGE
        }
        val missing = needed.filter {
            ContextCompat.checkSelfPermission(this, it) != PackageManager.PERMISSION_GRANTED
        }
        if (missing.isEmpty()) startSync() else permLauncher.launch(missing.toTypedArray())
    }

    /** Vérifie qu'au moins un type de fichier est sélectionné (sinon avertit). */
    private fun ensureTypeSelected(): Boolean {
        if (b.cbPhotos.isChecked || b.cbVideos.isChecked || b.cbAudio.isChecked) return true
        Toast.makeText(this, R.string.upload_none_selected, Toast.LENGTH_LONG).show()
        return false
    }

    /** Au moins une permission média correspondant aux types activés est-elle accordée ? */
    private fun hasAnyEnabledMediaPermission(): Boolean {
        fun granted(p: String) = ContextCompat.checkSelfPermission(this, p) == PackageManager.PERMISSION_GRANTED
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
            return granted(Manifest.permission.READ_EXTERNAL_STORAGE)
        }
        return (settings.uploadPhotos && granted(Manifest.permission.READ_MEDIA_IMAGES)) ||
            (settings.uploadVideos && granted(Manifest.permission.READ_MEDIA_VIDEO)) ||
            (settings.uploadAudio && granted(Manifest.permission.READ_MEDIA_AUDIO))
    }

    private fun startSync() {
        // La synchro auto (périodique en arrière-plan) n'est activée que si l'interrupteur l'est.
        if (settings.autoSync) UploadWorker.schedulePeriodic(this, settings.wifiOnly)
        else UploadWorker.cancelPeriodic(this)
        UploadWorker.runNow(this, settings.wifiOnly)
        Toast.makeText(this, "Synchro lancée", Toast.LENGTH_SHORT).show()
    }

    /** Arrête l'envoi en cours et désactive la synchro automatique. */
    private fun stopSync() {
        UploadWorker.cancelAll(this)
        settings.autoSync = false
        suppressAutoListener = true
        b.autoSyncSwitch.isChecked = false
        suppressAutoListener = false
        b.statusText.text = getString(R.string.status_stopped)
        Toast.makeText(this, R.string.status_stopped, Toast.LENGTH_SHORT).show()
    }

    /**
     * Appelé quand la galerie change : preuve VISIBLE que l'app a vu un nouvel élément,
     * même si aucune synchro n'est lancée (toast + statut + notification).
     */
    private fun onGalleryChanged() {
        if (!settings.watchGallery) return // surveillance désactivée
        val now = System.currentTimeMillis()
        if (now - lastDetectMs < 1500) return // anti-rafale
        lastDetectMs = now

        b.statusText.text = getString(R.string.detected_change)
        Toast.makeText(this, R.string.detected_change, Toast.LENGTH_SHORT).show()
        showDetectionNotification()

        // Si la synchro automatique est aussi active, on envoie tout de suite la nouveauté.
        if (settings.autoSync && hasPhotoPermission()) {
            UploadWorker.runNow(this, settings.wifiOnly)
        }
    }

    /** Notification système prouvant la détection (visible hors de l'écran courant). */
    private fun showDetectionNotification() {
        val mgr = NotificationManagerCompat.from(this)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            mgr.createNotificationChannel(
                NotificationChannel(CHANNEL_DETECT, "Détection de photos", NotificationManager.IMPORTANCE_DEFAULT)
            )
        }
        val notif = NotificationCompat.Builder(this, CHANNEL_DETECT)
            .setSmallIcon(android.R.drawable.ic_menu_camera)
            .setContentTitle(getString(R.string.app_name))
            .setContentText(getString(R.string.detected_change))
            .setAutoCancel(true)
            .build()
        try {
            mgr.notify(NOTIF_DETECT_ID, notif)
        } catch (e: SecurityException) {
            // Permission notifications non accordée : le toast/statut suffisent comme preuve.
        }
    }

    /** La permission de lecture des photos est-elle accordée ? */
    private fun hasPhotoPermission(): Boolean {
        val perm = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU)
            Manifest.permission.READ_MEDIA_IMAGES else Manifest.permission.READ_EXTERNAL_STORAGE
        return ContextCompat.checkSelfPermission(this, perm) == PackageManager.PERMISSION_GRANTED
    }

    private fun observeWork() {
        WorkManager.getInstance(this)
            .getWorkInfosForUniqueWorkLiveData(UploadWorker.ONESHOT)
            .observe(this) { infos ->
                val info = infos.firstOrNull() ?: return@observe
                b.statusText.text = describe(info)
                refreshDbTotal()
            }
    }

    private fun describe(info: WorkInfo): String = when (info.state) {
        WorkInfo.State.ENQUEUED -> {
            val net = if (settings.wifiOnly) " (en attente du Wi-Fi)" else " (en attente du réseau)"
            "⏳ En attente$net…"
        }
        WorkInfo.State.RUNNING -> {
            val done = info.progress.getInt(UploadWorker.KEY_DONE, 0)
            val total = info.progress.getInt(UploadWorker.KEY_TOTAL, 0)
            val failed = info.progress.getInt(UploadWorker.KEY_FAILED, 0)
            val skipped = info.progress.getInt(UploadWorker.KEY_SKIPPED, 0)
            when (info.progress.getString(UploadWorker.KEY_PHASE)) {
                UploadWorker.PHASE_SETUP -> "⚙️ Vérification de la configuration…"
                UploadWorker.PHASE_VERIFY -> "🔍 Vérification des photos… $done / $total"
                UploadWorker.PHASE_UPLOAD -> {
                    var s = "📤 Envoi : $done / $total"
                    if (skipped > 0) s += "  ·  $skipped déjà présente(s)"
                    if (failed > 0) s += "  ($failed échec)"
                    s
                }
                else -> "⏳ Préparation…"
            }
        }
        WorkInfo.State.SUCCEEDED -> {
            val up = info.outputData.getInt(UploadWorker.KEY_UPLOADED, 0)
            val skipped = info.outputData.getInt(UploadWorker.KEY_SKIPPED, 0)
            val failed = info.outputData.getInt(UploadWorker.KEY_FAILED, 0)
            val suffix = if (skipped > 0) "  ·  $skipped déjà présente(s) (ignorée(s))" else ""
            when {
                up == 0 && failed == 0 && skipped == 0 -> "✅ Tout est déjà synchronisé"
                up == 0 && failed == 0 -> "✅ À jour$suffix"
                failed > 0 -> "✅ $up envoyée(s) — ⚠️ $failed échec(s)$suffix"
                else -> "✅ Terminé : $up envoyée(s)$suffix"
            }
        }
        WorkInfo.State.FAILED -> {
            val err = info.outputData.getString(UploadWorker.KEY_ERROR)
            "❌ Échec : ${err ?: "vérifie l'URL, le compte et la connexion"}"
        }
        WorkInfo.State.BLOCKED -> "⏳ En attente…"
        WorkInfo.State.CANCELLED -> "⏹️ Annulé"
    }

    private fun refreshDbTotal() {
        lifecycleScope.launch {
            val count = SyncApp.instance.db.uploadedDao().count()
            b.totalText.text = getString(R.string.status_sent, count)
        }
    }

    companion object {
        private const val CHANNEL_DETECT = "photosync_detect"
        private const val NOTIF_DETECT_ID = 1001
    }
}
