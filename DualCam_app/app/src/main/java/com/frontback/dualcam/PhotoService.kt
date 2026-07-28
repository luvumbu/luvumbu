package com.frontback.dualcam

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.PowerManager
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageCapture
import androidx.camera.core.ImageCaptureException
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import androidx.lifecycle.LifecycleService
import com.frontback.dualcam.net.ApiClient
import com.frontback.dualcam.net.SettingsStore
import com.frontback.dualcam.net.UploadResult
import java.io.File
import java.util.concurrent.Executors

/**
 * Capture UNE photo (caméra avant ou arrière) puis s'arrête. Déclenchable à distance
 * même écran verrouillé : service de premier plan de type « caméra » lancé par
 * [TriggerService] (qui détient déjà l'usage caméra). La photo est envoyée au serveur
 * (source « dualcam ») exactement comme les autres médias.
 */
class PhotoService : LifecycleService() {

    private val io = Executors.newSingleThreadExecutor()
    private var wakeLock: PowerManager.WakeLock? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        super.onStartCommand(intent, flags, startId)
        val facing = intent?.getIntExtra(EXTRA_FACING, CameraSelector.LENS_FACING_BACK)
            ?: CameraSelector.LENS_FACING_BACK

        createChannel()
        startAsForeground()
        acquireWake()

        // La caméra est monopolisée par un enregistrement en cours : on ne peut pas capturer.
        if (RecordingService.isRecording) { finish("Photo impossible pendant l'enregistrement"); return START_NOT_STICKY }
        if (ContextCompat.checkSelfPermission(this, android.Manifest.permission.CAMERA)
            != PackageManager.PERMISSION_GRANTED) { finish("Autorisation caméra manquante"); return START_NOT_STICKY }

        capture(facing)
        return START_NOT_STICKY
    }

    private fun capture(facing: Int) {
        val future = ProcessCameraProvider.getInstance(this)
        future.addListener({
            try {
                val provider = future.get()
                val imageCapture = ImageCapture.Builder()
                    .setCaptureMode(ImageCapture.CAPTURE_MODE_MINIMIZE_LATENCY).build()
                // Flux continu (analyse d'image) lié à la capture : sans un flux actif à côté,
                // beaucoup de téléphones refusent d'ouvrir la caméra pour une photo seule.
                val analysis = ImageAnalysis.Builder()
                    .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST).build()
                analysis.setAnalyzer(ContextCompat.getMainExecutor(this)) { it.close() }
                val selector = CameraSelector.Builder().requireLensFacing(facing).build()
                provider.unbindAll()
                provider.bindToLifecycle(this, selector, imageCapture, analysis)

                val side = if (facing == CameraSelector.LENS_FACING_FRONT) "avant" else "arriere"
                val file = File(cacheDir, "photo_${side}_${System.currentTimeMillis()}.jpg")
                val opts = ImageCapture.OutputFileOptions.Builder(file).build()

                // Laisse le flux démarrer (~700 ms) avant de déclencher, sinon la 1ʳᵉ photo peut échouer.
                android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
                    imageCapture.takePicture(opts, ContextCompat.getMainExecutor(this),
                        object : ImageCapture.OnImageSavedCallback {
                            override fun onImageSaved(output: ImageCapture.OutputFileResults) {
                                try { provider.unbindAll() } catch (_: Throwable) {}
                                upload(file, side)
                            }
                            override fun onError(exc: ImageCaptureException) {
                                try { provider.unbindAll() } catch (_: Throwable) {}
                                finish("Échec de la photo : ${exc.message}")
                            }
                        })
                }, 700L)
            } catch (t: Throwable) {
                finish("Caméra indisponible : ${t.message}")
            }
        }, ContextCompat.getMainExecutor(this))
    }

    private fun upload(file: File, side: String) {
        io.execute {
            val settings = SettingsStore(this)
            if (!settings.isLoggedIn) {
                settings.lastUploadError = "Non connecté : reconnecte-toi (compte Google)"
                finish("Photo prise, mais non connecté"); return@execute
            }
            val name = "DualCam_photo_${side}_${System.currentTimeMillis()}.jpg"
            val api = ApiClient(settings)
            var ok = false
            var lastErr = "envoi photo échoué"
            // Réessais : le réseau peut être indisponible juste après le réveil (écran verrouillé).
            for (attempt in 0 until 4) {
                val res = try { api.uploadFile(file, name, "dualcam") }
                          catch (t: Throwable) { UploadResult(false, -1, t.message ?: "réseau") }
                if (res.ok) { ok = true; break }
                lastErr = res.error ?: "échec"
                try { Thread.sleep(3000L * (attempt + 1)) } catch (_: Throwable) { break }
            }
            settings.lastUploadError = if (ok) "" else lastErr
            // On ne supprime la photo QUE si elle est bien partie (sinon on la garde pour ne rien perdre).
            if (ok) { try { file.delete() } catch (_: Throwable) {} }
            finish(if (ok) "Photo $side envoyée ✓" else "Photo prise, envoi échoué : $lastErr")
        }
    }

    private fun finish(message: String) {
        try { updateNotification(message) } catch (_: Throwable) {}
        releaseWake()
        stopForeground(STOP_FOREGROUND_REMOVE)
        stopSelf()
    }

    // --- Premier plan (type caméra, requis pour capturer écran verrouillé) ---
    private fun createChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val ch = NotificationChannel(CHANNEL_ID, "Photo à distance", NotificationManager.IMPORTANCE_LOW)
            getSystemService(NotificationManager::class.java).createNotificationChannel(ch)
        }
    }

    private fun buildNotification(text: String): Notification =
        NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("DualCam")
            .setContentText(text)
            .setSmallIcon(R.drawable.ic_launcher)
            .setOngoing(true)
            .build()

    private fun startAsForeground() {
        val notif = buildNotification("Photo en cours…")
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R)
            startForeground(NOTIF_ID, notif, ServiceInfo.FOREGROUND_SERVICE_TYPE_CAMERA)
        else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q)
            startForeground(NOTIF_ID, notif, ServiceInfo.FOREGROUND_SERVICE_TYPE_CAMERA)
        else startForeground(NOTIF_ID, notif)
    }

    private fun updateNotification(text: String) {
        getSystemService(NotificationManager::class.java).notify(NOTIF_ID, buildNotification(text))
    }

    private fun acquireWake() {
        if (wakeLock?.isHeld == true) return
        val pm = getSystemService(POWER_SERVICE) as? PowerManager ?: return
        wakeLock = pm.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "DualCam:photo").apply {
            setReferenceCounted(false)
            try { acquire(3 * 60_000L) } catch (_: Throwable) {}   // couvre capture + réessais d'envoi
        }
    }
    private fun releaseWake() {
        try { if (wakeLock?.isHeld == true) wakeLock?.release() } catch (_: Throwable) {}
        wakeLock = null
    }

    override fun onDestroy() { releaseWake(); super.onDestroy() }

    companion object {
        const val EXTRA_FACING = "facing"
        private const val CHANNEL_ID = "photo"
        private const val NOTIF_ID = 44

        /** Lance une capture photo (arrière-plan). [facing] = CameraSelector.LENS_FACING_*. */
        fun capture(context: Context, facing: Int) {
            val i = Intent(context, PhotoService::class.java).putExtra(EXTRA_FACING, facing)
            ContextCompat.startForegroundService(context, i)
        }
    }
}
