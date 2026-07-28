package com.frontback.dualcam

import android.Manifest
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
import android.media.projection.MediaProjection
import android.media.projection.MediaProjectionManager
import android.os.Binder
import android.os.Build
import android.os.Environment
import android.os.Handler
import android.os.IBinder
import android.os.Looper
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageCapture
import androidx.camera.core.ImageCaptureException
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.video.FileOutputOptions
import androidx.camera.video.Quality
import androidx.camera.video.QualitySelector
import androidx.camera.video.Recorder
import androidx.camera.video.Recording
import androidx.camera.video.VideoCapture
import androidx.camera.video.VideoRecordEvent
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import androidx.lifecycle.LifecycleService
import com.frontback.dualcam.montage.MontageBuilder
import com.frontback.dualcam.montage.MontageSegment
import com.frontback.dualcam.net.ApiClient
import com.frontback.dualcam.net.GeoStore
import com.frontback.dualcam.net.LocationTracker
import com.frontback.dualcam.net.SettingsStore
import com.frontback.dualcam.net.UploadResult
import java.io.File
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.concurrent.Executors

/**
 * Service de premier plan qui possède la caméra et enregistre en arrière-plan
 * (l'enregistrement continue même écran éteint / app minimisée).
 *
 * Reprend la machine à états segmentée : filme la caméra principale, bascule
 * périodiquement sur l'autre pour une photo, puis à l'arrêt recolle + incruste (montage).
 */
class RecordingService : LifecycleService() {

    companion object {
        const val ACTION_START = "com.frontback.dualcam.START"
        const val ACTION_STOP = "com.frontback.dualcam.STOP"
        const val ACTION_SWITCH_CAM = "com.frontback.dualcam.SWITCH_CAM"
        const val EXTRA_FACING = "facing"
        const val EXTRA_INTERVAL = "interval"
        const val EXTRA_DURATION = "duration"
        const val EXTRA_CAM_ID = "camId"     // ID d'objectif précis (facultatif)
        const val EXTRA_OPP_PHOTO = "oppPhoto" // true = photo caméra opposée à chaque fragment
        const val EXTRA_APP_AUDIO = "appAudio"  // true = capter le son des autres applications
        const val EXTRA_PROJ_CODE = "projCode"  // resultCode de l'autorisation de capture
        const val EXTRA_PROJ_DATA = "projData"  // Intent de l'autorisation de capture

        private const val CHANNEL_ID = "recording"
        private const val NOTIF_ID = 42

        /** État partagé lisible par l'activité même sans binding. */
        @Volatile var isRecording = false; private set
        @Volatile var isMontaging = false; private set
        @Volatile var photoCount = 0; private set
        @Volatile var startedAtMs = 0L; private set
        /** Orientation de la caméra en cours de tournage (suit les bascules à chaud). */
        @Volatile var currentFacing = CameraSelector.LENS_FACING_BACK; private set

        /**
         * Démarre l'enregistrement depuis un déclencheur (son, secousse, tuile…),
         * en utilisant les réglages sauvegardés. Caméra arrière par défaut.
         *
         * Le son des autres applications n'est pas capté ici : il exige une autorisation
         * de capture obtenue depuis une activité (voir [MainActivity]). Micro seul.
         *
         * @param facingOverride force l'orientation au lieu du dernier choix mémorisé.
         */
        fun startFromTrigger(context: android.content.Context, facingOverride: Int? = null) {
            if (isRecording) return
            val p = context.getSharedPreferences("dualcam_settings", android.content.Context.MODE_PRIVATE)
            val interval = (p.getInt("interval", 30).coerceAtLeast(3)) * 1000L
            val duration = (p.getInt("duration", 3).coerceAtLeast(1)) * 1_000_000L
            val oppPhoto = p.getInt("cam_mode_v2", 1) == 1
            // Reprend le dernier mode caméra choisi dans l'app (avant/arrière/objectif).
            val facing = facingOverride ?: p.getInt("launch_facing", CameraSelector.LENS_FACING_BACK)
            val camId = if (facingOverride != null) null
                        else p.getString("launch_cam_id", "")?.ifBlank { null }
            val intent = Intent(context, RecordingService::class.java).apply {
                action = ACTION_START
                putExtra(EXTRA_FACING, facing)
                putExtra(EXTRA_INTERVAL, interval)
                putExtra(EXTRA_DURATION, duration)
                putExtra(EXTRA_OPP_PHOTO, oppPhoto)
                camId?.let { putExtra(EXTRA_CAM_ID, it) }
            }
            ContextCompat.startForegroundService(context, intent)
        }

        /** Bascule la caméra sans interrompre l'enregistrement (fragment suivant sur l'autre objectif). */
        fun requestSwitch(context: android.content.Context, facing: Int) {
            if (!isRecording) return
            context.startService(
                Intent(context, RecordingService::class.java)
                    .setAction(ACTION_SWITCH_CAM)
                    .putExtra(EXTRA_FACING, facing)
            )
        }
    }

    interface Listener {
        fun onState(recording: Boolean, photoCount: Int)
        fun onMontage(active: Boolean)
        fun onFinished(message: String)
    }

    inner class LocalBinder : Binder() {
        fun service(): RecordingService = this@RecordingService
    }

    private val binder = LocalBinder()
    var listener: Listener? = null

    private var cameraProvider: ProcessCameraProvider? = null
    private var videoCapture: VideoCapture<Recorder>? = null
    private var imageCapture: ImageCapture? = null
    private var previewUseCase: Preview? = null

    /** Fournisseur de surface de l'aperçu, fourni par l'activité quand elle est visible. */
    @Volatile private var surfaceProvider: Preview.SurfaceProvider? = null

    /** L'activité appelle ceci pour afficher (ou masquer) l'aperçu de l'enregistrement. */
    fun setSurfaceProvider(sp: Preview.SurfaceProvider?) {
        surfaceProvider = sp
        main.post { previewUseCase?.setSurfaceProvider(sp) }
    }

    private var mainFacing = CameraSelector.LENS_FACING_BACK
    private val otherFacing: Int
        get() = if (mainFacing == CameraSelector.LENS_FACING_BACK)
            CameraSelector.LENS_FACING_FRONT else CameraSelector.LENS_FACING_BACK

    private var intervalMs = 10_000L
    private var overlayDurationUs = 3_000_000L
    private var mainCameraId: String? = null
    /** true = capturer une photo de la caméra opposée à chaque fragment (incrustée au montage). */
    private var oppositePhoto = false

    private var stopRequested = false
    private var photoPending = false
    /** Une bascule de caméra est demandée : le fragment en cours se ferme puis on rebascule. */
    private var switchPending = false

    // --- Son des autres applications (TikTok, musique…) mixé au micro ---
    /** Demandé par l'appelant (nécessite une autorisation de capture valide). */
    private var appAudio = false
    private var projCode = 0
    private var projData: Intent? = null
    /** Réellement actif : la caméra filme alors SANS son, l'audio est capté à part puis fusionné. */
    private var appAudioActive = false
    private var projection: MediaProjection? = null
    private var audioRecorder: InternalAudioRecorder? = null
    private var stoppedAudio: InternalAudioRecorder? = null
    private var audioStopThread: Thread? = null
    private var sessionAudioFile: File? = null

    private var currentRecording: Recording? = null
    private var currentSegmentFile: File? = null
    private val segments = mutableListOf<File>()
    private val overlayForSegment = HashMap<Int, File>()

    private val main = Handler(Looper.getMainLooper())
    private val tickRunnable = Runnable { onIntervalTick() }

    // --- Sauvegarde de sécurité : envoi automatique au serveur, en tâche de fond ---
    private val settings by lazy { SettingsStore(this) }
    private val uploadExec = Executors.newSingleThreadExecutor()
    /** Position GPS de la session (facultative) attachée à chaque envoi. */
    private val locationTracker by lazy { LocationTracker(this) }
    /** Identifiant unique de la session d'enregistrement (regroupe les fragments côté serveur). */
    private var sessionId = ""

    override fun onBind(intent: Intent): IBinder {
        super.onBind(intent)
        return binder
    }

    override fun onCreate() {
        super.onCreate()
        createChannel()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        super.onStartCommand(intent, flags, startId)
        when (intent?.action) {
            ACTION_START -> {
                mainFacing = intent.getIntExtra(EXTRA_FACING, CameraSelector.LENS_FACING_BACK)
                currentFacing = mainFacing
                intervalMs = intent.getLongExtra(EXTRA_INTERVAL, 10_000L)
                overlayDurationUs = intent.getLongExtra(EXTRA_DURATION, 3_000_000L)
                mainCameraId = intent.getStringExtra(EXTRA_CAM_ID)
                oppositePhoto = intent.getBooleanExtra(EXTRA_OPP_PHOTO, false)
                @Suppress("DEPRECATION")
                projData = intent.getParcelableExtra(EXTRA_PROJ_DATA)
                projCode = intent.getIntExtra(EXTRA_PROJ_CODE, 0)
                appAudio = intent.getBooleanExtra(EXTRA_APP_AUDIO, false) &&
                    Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q && projData != null
                startAsForeground("Enregistrement en cours…")
                startRecording()
            }
            ACTION_SWITCH_CAM ->
                switchFacing(intent.getIntExtra(EXTRA_FACING, otherFacing))
            ACTION_STOP -> stopByUser()
        }
        return START_NOT_STICKY
    }

    // ---------------------------------------------------------------------------------------------
    // Enregistrement segmenté (caméra liée au cycle de vie du SERVICE → survit à l'écran éteint)
    // ---------------------------------------------------------------------------------------------

    private fun startRecording() {
        if (isRecording) return
        val future = ProcessCameraProvider.getInstance(this)
        future.addListener({
            val provider = future.get()
            cameraProvider = provider
            videoCapture = VideoCapture.withOutput(
                Recorder.Builder().setQualitySelector(QualitySelector.from(Quality.HD)).build()
            )
            imageCapture = ImageCapture.Builder()
                .setCaptureMode(ImageCapture.CAPTURE_MODE_MINIMIZE_LATENCY).build()

            segments.clear(); overlayForSegment.clear(); clearSegmentsDir()
            photoCount = 0; stopRequested = false; photoPending = false; switchPending = false
            isRecording = true
            startedAtMs = System.currentTimeMillis()
            sessionId = timeStamp()   // identifiant de session pour l'envoi serveur
            notifyState()
            locationTracker.start()   // suivi GPS de la session (si permission accordée)
            startAppAudioCapture()    // son des autres apps + micro (si autorisé)
            bindVideo()
            startSegment()
        }, ContextCompat.getMainExecutor(this))
    }

    private fun bindVideo() {
        val provider = cameraProvider ?: return
        provider.unbindAll()
        val pv = Preview.Builder().build()
        previewUseCase = pv
        try {
            provider.bindToLifecycle(this, mainSelector(), pv, videoCapture)
        } catch (t: Throwable) {
            // Objectif précis non ouvrable → repli sur l'orientation.
            mainCameraId = null
            provider.bindToLifecycle(this, mainSelector(), pv, videoCapture)
        }
        pv.setSurfaceProvider(surfaceProvider)
    }

    /** Sélecteur caméra : par ID d'objectif précis si fourni, sinon par orientation (avant/arrière). */
    private fun mainSelector(): CameraSelector {
        val id = mainCameraId
        return if (id != null) {
            CameraSelector.Builder().addCameraFilter { infos ->
                infos.filter { androidx.camera.camera2.interop.Camera2CameraInfo.from(it).cameraId == id }
            }.build()
        } else {
            CameraSelector.Builder().requireLensFacing(mainFacing).build()
        }
    }

    private fun bindPhoto() {
        val provider = cameraProvider ?: return
        provider.unbindAll()
        val pv = Preview.Builder().build()
        previewUseCase = pv
        provider.bindToLifecycle(
            this,
            CameraSelector.Builder().requireLensFacing(otherFacing).build(),
            pv, imageCapture
        )
        pv.setSurfaceProvider(surfaceProvider)
    }

    /**
     * Démarre la capture du SON DES AUTRES APPLICATIONS (mixé au micro) pour toute la session.
     * L'audio est écrit dans un .m4a unique et continu — il traverse donc les coupures entre
     * fragments (bascule de caméra, photo opposée) sans trou — puis fusionné à la vidéo finale.
     */
    private fun startAppAudioCapture() {
        appAudioActive = false
        if (!appAudio || Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) return
        val data = projData ?: return
        try {
            val proj = getSystemService(MediaProjectionManager::class.java)
                .getMediaProjection(projCode, data) ?: return
            projection = proj
            proj.registerCallback(object : MediaProjection.Callback() {
                override fun onStop() {}
            }, main)
            val file = File(cacheDir, "session_audio_${timeStamp()}.m4a")
            val rec = InternalAudioRecorder(proj, file, withMic = true)
            rec.start()
            audioRecorder = rec
            sessionAudioFile = file
            appAudioActive = true
        } catch (t: Throwable) {
            android.util.Log.e("RecordingService", "capture son des apps", t)
            try { projection?.stop() } catch (_: Throwable) {}
            projection = null
            audioRecorder = null
            sessionAudioFile = null
        }
    }

    /** Coupe la capture audio sans bloquer le thread principal (la finalisation du .m4a prend ~1 s). */
    private fun stopAppAudioCapture() {
        val rec = audioRecorder ?: return
        audioRecorder = null
        stoppedAudio = rec
        val proj = projection
        projection = null
        audioStopThread = Thread({
            try { rec.stop() } catch (_: Throwable) {}
            try { proj?.stop() } catch (_: Throwable) {}
        }, "app-audio-stop").apply { start() }
    }

    private fun startSegment() {
        val vc = videoCapture ?: return
        val file = File(segmentsDir(), "seg_${segments.size}_${timeStamp()}.mp4")
        currentSegmentFile = file
        val pending = vc.output
            .prepareRecording(this, FileOutputOptions.Builder(file).build())
            .apply {
                // Avec la capture du son des apps, la piste audio est produite à part :
                // la caméra filme sans son (sinon deux pistes se disputeraient le micro).
                if (!appAudioActive &&
                    ContextCompat.checkSelfPermission(this@RecordingService, Manifest.permission.RECORD_AUDIO)
                    == PackageManager.PERMISSION_GRANTED
                ) withAudioEnabled()
            }
        currentRecording = pending.start(ContextCompat.getMainExecutor(this)) { event ->
            when (event) {
                is VideoRecordEvent.Start -> main.postDelayed(tickRunnable, intervalMs)
                is VideoRecordEvent.Finalize -> onSegmentFinalized(event)
            }
        }
    }

    private fun onIntervalTick() {
        if (!isRecording) return
        // Mode « photo opposée » : on prendra une photo de l'autre caméra après la clôture.
        // Mode « continu » : on enchaîne directement le fragment suivant (sans coupure).
        if (oppositePhoto) photoPending = true
        currentRecording?.stop()
    }

    private fun onSegmentFinalized(event: VideoRecordEvent.Finalize) {
        currentRecording = null
        val file = currentSegmentFile
        if (file != null && file.exists() && file.length() > 0 && !event.hasError()) {
            val index = segments.size
            segments.add(file)
            // SÉCURITÉ : envoi immédiat du fragment au serveur (survit à une coupure).
            uploadSegment(file, index)
        }
        when {
            stopRequested -> { isRecording = false; finishAndMontage() }
            // Bascule demandée (3 clics) : le fragment suivant est filmé par l'autre caméra.
            switchPending -> { switchPending = false; photoPending = false; continueRecording() }
            // Mode « photo opposée » : capture l'autre caméra puis reprend (petite coupure).
            photoPending -> { photoPending = false; doPhotoThenContinue() }
            // Mode « continu » : chunk suivant immédiatement, caméra liée (coupure mini).
            else -> startSegment()
        }
    }

    /**
     * Bascule avant ↔ arrière SANS arrêter l'enregistrement : on clôt le fragment en cours,
     * puis le suivant est filmé par l'autre objectif. Le montage recolle le tout à la fin.
     */
    fun switchFacing(facing: Int) {
        if (!isRecording || switchPending) return
        if (facing == mainFacing && mainCameraId == null) return
        mainFacing = facing
        mainCameraId = null           // bascule sur la caméra par défaut de cette orientation
        currentFacing = facing
        switchPending = true
        main.removeCallbacks(tickRunnable)
        updateNotification("Enregistrement — ${if (facing == CameraSelector.LENS_FACING_FRONT) "caméra avant" else "caméra arrière"}")
        val rec = currentRecording
        if (rec != null) rec.stop() else { switchPending = false; continueRecording() }
    }

    private fun doPhotoThenContinue() {
        val ic = imageCapture ?: run { continueRecording(); return }
        try { bindPhoto() } catch (t: Throwable) { continueRecording(); return }

        val photoFile = File(photosDir(), "photo_${timeStamp()}.jpg")
        val options = ImageCapture.OutputFileOptions.Builder(photoFile).build()
        ic.takePicture(options, ContextCompat.getMainExecutor(this),
            object : ImageCapture.OnImageSavedCallback {
                override fun onImageSaved(output: ImageCapture.OutputFileResults) {
                    photoCount++
                    overlayForSegment[segments.size] = photoFile
                    // SÉCURITÉ : envoi direct de la photo au serveur (comme les fragments vidéo).
                    uploadPhoto(photoFile, photoCount)
                    notifyState()
                    continueRecording()
                }
                override fun onError(exc: ImageCaptureException) { continueRecording() }
            })
    }

    private fun continueRecording() {
        if (stopRequested || !isRecording) { isRecording = false; finishAndMontage(); return }
        bindVideo()
        startSegment()
    }

    fun stopByUser() {
        if (!isRecording) return
        stopRequested = true
        main.removeCallbacks(tickRunnable)
        val rec = currentRecording
        if (rec != null) rec.stop()
        else { isRecording = false; finishAndMontage() }
    }

    private fun finishAndMontage() {
        main.removeCallbacks(tickRunnable)
        stopAppAudioCapture()   // finalise le .m4a en tâche de fond pendant le montage
        notifyState()

        if (segments.isEmpty()) { finishService("Rien enregistré"); return }

        if (segments.size == 1 && overlayForSegment.isEmpty()) {
            val out = File(videosDir(), "DualCam_${timeStamp()}.mp4")
            try { segments[0].copyTo(out, overwrite = true) } catch (_: Throwable) {}
            clearSegmentsDir()
            deliverFinal(out, "Vidéo enregistrée ✔")
            return
        }

        isMontaging = true
        listener?.onMontage(true)
        updateNotification("Montage en cours…")
        val montageSegs = segments.mapIndexed { i, f -> MontageSegment(f, overlayForSegment[i]) }
        val out = File(videosDir(), "DualCam_${timeStamp()}.mp4")
        MontageBuilder(this).build(montageSegs, out, overlayDurationUs, object : MontageBuilder.Callback {
            override fun onSuccess(output: File) {
                clearSegmentsDir(); isMontaging = false
                listener?.onMontage(false)
                deliverFinal(output, "Montage prêt ✔ (voir galerie)")
            }
            override fun onFailure(message: String) {
                segments.forEachIndexed { i, f ->
                    try { f.copyTo(File(videosDir(), "Segment_${i}_${timeStamp()}.mp4"), true) } catch (_: Throwable) {}
                }
                clearSegmentsDir(); isMontaging = false
                listener?.onMontage(false)
                finishService("Montage impossible, segments conservés")
            }
        })
    }

    /**
     * Dernière étape : si le son des autres applications a été capté, on le fusionne dans la
     * vidéo (qui n'a alors aucune piste audio), puis on envoie au serveur et on s'arrête.
     */
    private fun deliverFinal(out: File, message: String) {
        // Mémorise la position de la vidéo finale (pour « Plus d'infos » dans la galerie de l'app).
        locationTracker.latLng?.let { GeoStore.save(this, out.name, it.first, it.second) }
        val audioFile = sessionAudioFile
        if (audioFile == null) {
            uploadFinal(out)   // reconstitution → envoi + dédup serveur
            finishService(message)
            return
        }
        sessionAudioFile = null
        isMontaging = true
        listener?.onMontage(true)
        updateNotification("Ajout du son des applications…")
        val stopper = audioStopThread
        Thread({
            try { stopper?.join(5_000) } catch (_: InterruptedException) {}
            val hasAudio = stoppedAudio?.hasData == true && audioFile.exists() && audioFile.length() > 0
            if (hasAudio) {
                val merged = File(out.parentFile, out.nameWithoutExtension + "_snd.mp4")
                val ok = MediaMuxUtil.muxVideoAudio(out.absolutePath, audioFile.absolutePath, merged.absolutePath)
                if (ok && merged.exists() && merged.length() > 0) {
                    if (out.delete()) merged.renameTo(out) else merged.delete()
                } else {
                    merged.delete()   // échec : on garde la vidéo muette plutôt que rien
                }
            }
            audioFile.delete()
            main.post {
                isMontaging = false
                listener?.onMontage(false)
                uploadFinal(out)
                finishService(message)
            }
        }, "audio-mux").start()
    }

    private fun finishService(message: String) {
        listener?.onFinished(message)
        locationTracker.stop()
        try { cameraProvider?.unbindAll() } catch (_: Throwable) {}
        stopAppAudioCapture()
        sessionAudioFile?.delete()
        sessionAudioFile = null
        stopForeground(STOP_FOREGROUND_REMOVE)
        stopSelf()
    }

    private fun notifyState() {
        listener?.onState(isRecording, photoCount)
    }

    // ---------------------------------------------------------------------------------------------
    // Envoi automatique au serveur (sauvegarde de sécurité)
    // ---------------------------------------------------------------------------------------------

    /**
     * Envoi de fond commun aux fragments, photos et vidéo finale.
     *
     * Toute panne est REMONTÉE (notification + mémorisation dans les réglages) : un envoi
     * silencieusement avalé donnait un serveur vide sans le moindre message côté téléphone.
     * @param onDone appelé sur le fil d'envoi avec le succès réel de l'opération.
     */
    private fun upload(file: File, name: String, onDone: ((Boolean) -> Unit)? = null) {
        if (!settings.isLoggedIn) {
            val msg = "Non connecté : rien n'est envoyé au serveur"
            settings.lastUploadError = msg
            main.post { updateNotification("⚠️ $msg") }
            onDone?.invoke(false)
            return
        }
        val loc = locationTracker.latLng
        uploadExec.execute {
            val res = try {
                ApiClient(settings).uploadFile(file, name, "dualcam", loc?.first, loc?.second)
            } catch (t: Throwable) {
                UploadResult(false, -1, t.message ?: "erreur inconnue")
            }
            settings.lastUploadError = if (res.ok) "" else (res.error ?: "échec de l'envoi")
            if (!res.ok) main.post { updateNotification("⚠️ Envoi serveur : ${settings.lastUploadError}") }
            onDone?.invoke(res.ok)
        }
    }

    /** Envoie un fragment de 30 s dès qu'il est prêt (tâche de fond, tout réseau). */
    private fun uploadSegment(file: File, index: Int) {
        upload(file, "DualCam_${sessionId}_seg${index}.mp4")
    }

    /** Envoie une photo (caméra opposée) dès qu'elle est prise. Conservée sur le serveur. */
    private fun uploadPhoto(file: File, index: Int) {
        upload(file, "DualCam_${sessionId}_photo${index}.jpg")
    }

    /**
     * Fin déclarée : envoie la vidéo complète reconstituée, puis demande au serveur
     * de supprimer les fragments redondants de la session (pas de doublons).
     */
    private fun uploadFinal(output: File) {
        val session = sessionId
        upload(output, "DualCam_${session}.mp4") { ok ->
            if (ok) try { ApiClient(settings).finalizeSession(session) } catch (_: Throwable) {}
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Notification premier plan
    // ---------------------------------------------------------------------------------------------

    private fun createChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID, "Enregistrement", NotificationManager.IMPORTANCE_LOW
            )
            (getSystemService(NotificationManager::class.java)).createNotificationChannel(channel)
        }
    }

    private fun buildNotification(text: String): Notification {
        val openIntent = PendingIntent.getActivity(
            this, 0, Intent(this, MainActivity::class.java),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )
        val stopIntent = PendingIntent.getService(
            this, 1, Intent(this, RecordingService::class.java).setAction(ACTION_STOP),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )
        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("DualCam")
            .setContentText(text)
            .setSmallIcon(R.drawable.ic_launcher)
            .setOngoing(true)
            .setContentIntent(openIntent)
            .addAction(0, "Arrêter", stopIntent)
            .build()
    }

    private fun startAsForeground(text: String) {
        val notif = buildNotification(text)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            var types = ServiceInfo.FOREGROUND_SERVICE_TYPE_CAMERA or
                ServiceInfo.FOREGROUND_SERVICE_TYPE_MICROPHONE
            // La capture du son des autres apps passe par MediaProjection : le service doit
            // être déjà en premier plan AVEC ce type avant de créer la projection.
            if (appAudio) types = types or ServiceInfo.FOREGROUND_SERVICE_TYPE_MEDIA_PROJECTION
            // Type « location » ajouté seulement si la permission est accordée : sinon
            // startForeground lèverait une SecurityException (Android 14+).
            if (locationTracker.hasPermission())
                types = types or ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION
            startForeground(NOTIF_ID, notif, types)
        } else {
            startForeground(NOTIF_ID, notif)
        }
    }

    private fun updateNotification(text: String) {
        getSystemService(NotificationManager::class.java).notify(NOTIF_ID, buildNotification(text))
    }

    // ---------------------------------------------------------------------------------------------

    private fun videosDir(): File =
        File(getExternalFilesDir(Environment.DIRECTORY_MOVIES) ?: filesDir, "").apply { mkdirs() }

    private fun photosDir(): File =
        File(getExternalFilesDir(Environment.DIRECTORY_PICTURES) ?: filesDir, "DualCam").apply { mkdirs() }

    private fun segmentsDir(): File = File(cacheDir, "segments").apply { mkdirs() }

    private fun clearSegmentsDir() { segmentsDir().listFiles()?.forEach { it.delete() } }

    private fun timeStamp(): String =
        SimpleDateFormat("yyyyMMdd_HHmmss_SSS", Locale.US).format(Date())
}
