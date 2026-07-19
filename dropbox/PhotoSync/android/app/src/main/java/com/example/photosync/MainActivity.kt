package com.example.photosync

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Intent
import android.content.pm.PackageManager
import android.database.ContentObserver
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.provider.MediaStore
import android.provider.OpenableColumns
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
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class MainActivity : AppCompatActivity() {

    private lateinit var b: ActivityMainBinding
    private lateinit var settings: SettingsStore
    private lateinit var api: ApiClient

    // Évite que le changement programmatique de l'interrupteur déclenche son écouteur.
    private var suppressAutoListener = false
    // Anti-rafale pour la détection (MediaStore notifie parfois plusieurs fois).
    private var lastDetectMs = 0L
    // Observateur de galerie actuellement enregistré ?
    private var observing = false
    // Dernier état connu des tâches (manuelle / périodique) pour l'affichage.
    private var oneShotInfo: WorkInfo? = null
    private var periodicInfo: WorkInfo? = null

    /** Surveille la galerie : signale tout ajout/changement (et envoie si la synchro auto est active). */
    private val galleryObserver = object : ContentObserver(Handler(Looper.getMainLooper())) {
        override fun onChange(selfChange: Boolean) {
            onGalleryChanged()
        }
    }

    // Sélecteur de fichiers à envoyer manuellement (depuis la galerie / les fichiers du téléphone).
    private val pickFilesLauncher = registerForActivityResult(
        ActivityResultContracts.GetMultipleContents()
    ) { uris ->
        if (!uris.isNullOrEmpty()) uploadPickedFiles(uris)
    }

    private val permLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { result ->
        val photoGranted = result[Manifest.permission.READ_MEDIA_IMAGES] == true ||
            result[Manifest.permission.READ_EXTERNAL_STORAGE] == true
        if (photoGranted) startSync()
        else Toast.makeText(this, "Accès aux photos refusé", Toast.LENGTH_LONG).show()
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

        api = ApiClient(this, settings)
        val ver = try { packageManager.getPackageInfo(packageName, 0).versionName } catch (e: Exception) { "?" }
        b.accountText.text = getString(R.string.connected_as, settings.username) + "  ·  v$ver"
        b.wifiSwitch.isChecked = settings.wifiOnly

        // État initial des interrupteurs (sans déclencher les écouteurs).
        suppressAutoListener = true
        b.autoSyncSwitch.isChecked = settings.autoSync
        b.watchSwitch.isChecked = settings.watchGallery
        b.videoSwitch.isChecked = settings.includeVideos
        suppressAutoListener = false
        if (settings.maxPerSync > 0) b.maxPerSyncInput.setText(settings.maxPerSync.toString())

        b.saveButton.setOnClickListener {
            settings.wifiOnly = b.wifiSwitch.isChecked
            settings.autoSync = b.autoSyncSwitch.isChecked
            settings.watchGallery = b.watchSwitch.isChecked
            settings.includeVideos = b.videoSwitch.isChecked
            settings.maxPerSync = b.maxPerSyncInput.text.toString().trim().toIntOrNull() ?: 0
            if (settings.watchGallery) registerGalleryObserver() else unregisterGalleryObserver()
            requestPermissionsThenSync()
        }
        // Bouton « Envoyer tout type de fichier » : coché = photos + vidéos ; décoché = photos seulement.
        b.videoSwitch.setOnCheckedChangeListener { _, checked ->
            if (suppressAutoListener) return@setOnCheckedChangeListener
            settings.includeVideos = checked
            // Réactive l'observateur pour (dés)inclure les vidéos dans la surveillance.
            if (observing) { unregisterGalleryObserver(); if (settings.watchGallery) registerGalleryObserver() }
        }
        b.syncNowButton.setOnClickListener {
            settings.wifiOnly = b.wifiSwitch.isChecked
            requestPermissionsThenSync()
        }
        // Sélection manuelle : ouvre le sélecteur de fichiers du téléphone (tout type).
        b.pickUploadButton.setOnClickListener { pickFilesLauncher.launch("*/*") }
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
        b.photosButton.setOnClickListener {
            startActivity(Intent(this, GalleryActivity::class.java)
                .putExtra(GalleryActivity.EXTRA_KIND, GalleryActivity.KIND_PHOTOS))
        }
        b.videosButton.setOnClickListener {
            startActivity(Intent(this, GalleryActivity::class.java)
                .putExtra(GalleryActivity.EXTRA_KIND, GalleryActivity.KIND_VIDEOS))
        }
        b.cleanupButton.setOnClickListener {
            startActivity(Intent(this, CleanupActivity::class.java))
        }
        b.matrixButton.setOnClickListener {
            startActivity(Intent(this, MatrixActivity::class.java))
        }
        b.deletionsButton.setOnClickListener {
            startActivity(Intent(this, ServerDeletionsActivity::class.java))
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
            // Contrôle léger : des photos envoyées ont-elles été supprimées du serveur ?
            checkServerDeletions()
        }
    }

    /** Détecte les photos envoyées puis supprimées du serveur → alerte + notification. */
    private fun checkServerDeletions() {
        if (!settings.isLoggedIn || !hasPhotoPermission()) return
        lifecycleScope.launch {
            val count = withContext(Dispatchers.IO) {
                val names = api.fetchNames() ?: return@withContext -1   // échec réseau : on ne conclut rien
                val server = names.mapTo(HashSet()) { it.lowercase() }
                val uploaded = SyncApp.instance.db.uploadedDao().allIds().toHashSet()
                val ignored = settings.ignoredDeletions.mapTo(HashSet()) { it.lowercase() }
                MediaScanner.queryImages(this@MainActivity, settings.includeVideos).count {
                    it.id in uploaded && it.name.lowercase() !in server && it.name.lowercase() !in ignored
                }
            }
            if (count > 0) {
                b.statusText.text = getString(R.string.deletions_alert, count)
                notifyDeletions(count)
            }
        }
    }

    /** Notification d'alerte de suppression serveur (touche → écran de décision). */
    private fun notifyDeletions(count: Int) {
        val mgr = NotificationManagerCompat.from(this)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            mgr.createNotificationChannel(
                NotificationChannel(CHANNEL_DETECT, "Détection de photos", NotificationManager.IMPORTANCE_DEFAULT)
            )
        }
        val pi = PendingIntent.getActivity(
            this, 0, Intent(this, ServerDeletionsActivity::class.java),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )
        val notif = NotificationCompat.Builder(this, CHANNEL_DETECT)
            .setSmallIcon(android.R.drawable.stat_sys_warning)
            .setContentTitle(getString(R.string.app_name))
            .setContentText(getString(R.string.deletions_alert, count))
            .setContentIntent(pi)
            .setAutoCancel(true)
            .build()
        try {
            mgr.notify(NOTIF_DELETION_ID, notif)
        } catch (e: SecurityException) {
            // Permission notifications non accordée : l'alerte reste visible dans l'app.
        }
    }

    override fun onPause() {
        super.onPause()
        unregisterGalleryObserver()
    }

    /** Active la surveillance de la galerie (photos, + vidéos si « tout type de fichier » est coché). */
    private fun registerGalleryObserver() {
        if (observing) return
        contentResolver.registerContentObserver(
            MediaStore.Images.Media.EXTERNAL_CONTENT_URI, true, galleryObserver
        )
        // On ne surveille les vidéos que si elles sont incluses dans la sauvegarde.
        if (settings.includeVideos) {
            contentResolver.registerContentObserver(
                MediaStore.Video.Media.EXTERNAL_CONTENT_URI, true, galleryObserver
            )
        }
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
            needed += Manifest.permission.READ_MEDIA_IMAGES
            needed += Manifest.permission.READ_MEDIA_VIDEO
            needed += Manifest.permission.POST_NOTIFICATIONS
        } else {
            needed += Manifest.permission.READ_EXTERNAL_STORAGE
        }
        val missing = needed.filter {
            ContextCompat.checkSelfPermission(this, it) != PackageManager.PERMISSION_GRANTED
        }
        if (missing.isEmpty()) startSync() else permLauncher.launch(missing.toTypedArray())
    }

    private fun startSync() {
        // La synchro auto (périodique en arrière-plan) n'est activée que si l'interrupteur l'est.
        if (settings.autoSync) UploadWorker.schedulePeriodic(this, settings.wifiOnly)
        else UploadWorker.cancelPeriodic(this)
        UploadWorker.runNow(this, settings.wifiOnly)
        Toast.makeText(this, "Synchro lancée", Toast.LENGTH_SHORT).show()
    }

    /** Envoie directement les fichiers choisis manuellement dans le sélecteur du téléphone. */
    private fun uploadPickedFiles(uris: List<Uri>) {
        Toast.makeText(this, getString(R.string.upload_picked_start, uris.size), Toast.LENGTH_SHORT).show()
        lifecycleScope.launch {
            var ok = 0
            var failed = 0
            withContext(Dispatchers.IO) {
                for (uri in uris) {
                    val (name, size) = queryFileMeta(uri)
                    val photo = LocalPhoto(
                        id = 0L, uri = uri, name = name,
                        dateTakenMs = System.currentTimeMillis(), size = size,
                    )
                    if (api.upload(photo).ok) ok++ else failed++
                }
            }
            b.statusText.text = getString(R.string.upload_picked_done, ok, failed)
            Toast.makeText(this@MainActivity, getString(R.string.upload_picked_done, ok, failed), Toast.LENGTH_LONG).show()
            refreshDbTotal()
        }
    }

    /** Récupère le nom et la taille d'un fichier choisi (URI de contenu). */
    private fun queryFileMeta(uri: Uri): Pair<String, Long> {
        var name = "fichier"
        var size = 0L
        contentResolver.query(uri, null, null, null, null)?.use { c ->
            if (c.moveToFirst()) {
                val ni = c.getColumnIndex(OpenableColumns.DISPLAY_NAME)
                val si = c.getColumnIndex(OpenableColumns.SIZE)
                if (ni >= 0) c.getString(ni)?.let { name = it }
                if (si >= 0 && !c.isNull(si)) size = c.getLong(si)
            }
        }
        return name to size
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
        val wm = WorkManager.getInstance(this)
        // On suit la synchro MANUELLE et la synchro AUTO (arrière-plan) → l'état est toujours visible.
        wm.getWorkInfosForUniqueWorkLiveData(UploadWorker.ONESHOT).observe(this) { infos ->
            oneShotInfo = infos.firstOrNull()
            renderStatus()
            refreshDbTotal()
        }
        wm.getWorkInfosForUniqueWorkLiveData(UploadWorker.PERIODIC).observe(this) { infos ->
            periodicInfo = infos.firstOrNull()
            renderStatus()
        }
    }

    /** Choisit l'info la plus parlante à afficher (en cours > tentative > échec > résultat > repos). */
    private fun renderStatus() {
        val all = listOfNotNull(oneShotInfo, periodicInfo)
        val chosen =
            all.firstOrNull { it.state == WorkInfo.State.RUNNING }
                ?: all.firstOrNull { it.state == WorkInfo.State.ENQUEUED && it.runAttemptCount > 0 }
                ?: all.firstOrNull { it.state == WorkInfo.State.FAILED }
                ?: oneShotInfo?.takeIf { it.state == WorkInfo.State.ENQUEUED }
                ?: oneShotInfo?.takeIf { it.state == WorkInfo.State.SUCCEEDED }
                ?: periodicInfo?.takeIf { it.state == WorkInfo.State.SUCCEEDED }
        b.statusText.text = if (chosen != null) describe(chosen) else idleStatus()
    }

    /** État au repos (aucune tâche active) : reflète les options activées. */
    private fun idleStatus(): String = when {
        settings.autoSync && settings.watchGallery -> "🟢 Synchro auto + surveillance actives (en veille)."
        settings.autoSync -> "🟢 Synchro auto active (en veille ~15 min)."
        settings.watchGallery -> "👁️ Surveillance de la galerie active."
        else -> getString(R.string.status_idle)
    }

    private fun describe(info: WorkInfo): String = when (info.state) {
        WorkInfo.State.ENQUEUED -> {
            if (info.runAttemptCount > 0) {
                "🔁 Nouvelle tentative (essai ${info.runAttemptCount + 1})…"
            } else {
                val net = if (settings.wifiOnly) " (en attente du Wi-Fi)" else " (en attente du réseau)"
                "⏳ En attente$net…"
            }
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
        private const val NOTIF_DELETION_ID = 3001
    }
}
