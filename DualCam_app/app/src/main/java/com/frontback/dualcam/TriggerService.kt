package com.frontback.dualcam

import android.Manifest
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
import android.database.ContentObserver
import android.hardware.Sensor
import android.hardware.SensorEvent
import android.hardware.SensorEventListener
import android.hardware.SensorManager
import android.media.AudioFormat
import android.media.AudioRecord
import android.media.MediaRecorder
import android.net.Uri
import android.os.BatteryManager
import android.os.Build
import android.os.Handler
import android.os.HandlerThread
import android.os.IBinder
import android.os.Looper
import android.provider.MediaStore
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import com.frontback.dualcam.net.ApiClient
import com.frontback.dualcam.net.SettingsStore
import kotlin.math.abs
import kotlin.math.sqrt

/**
 * Service de premier plan qui SURVEILLE le téléphone pour DÉCLENCHER l'enregistrement
 * automatiquement : bruit fort (cri) et/ou secousse. Tourne même app fermée.
 * Dès qu'un déclenchement se produit, il lance [RecordingService] et s'arrête
 * (pour libérer le micro).
 */
class TriggerService : Service(), SensorEventListener {

    companion object {
        const val ACTION_START = "com.frontback.dualcam.WATCH_START"
        const val ACTION_STOP = "com.frontback.dualcam.WATCH_STOP"
        private const val CHANNEL_ID = "watching"
        private const val NOTIF_ID = 43

        /** Cadence d'interrogation du serveur pour le déclenchement à distance. */
        private const val REMOTE_POLL_MS = 10_000L

        @Volatile var isWatching = false; private set

        /** (Re)démarre ou arrête la surveillance selon les réglages activés. */
        fun sync(context: Context) {
            val p = context.getSharedPreferences("dualcam_settings", Context.MODE_PRIVATE)
            val on = p.getBoolean("trig_sound", false) || p.getBoolean("trig_shake", false) ||
                p.getBoolean("trig_screenshot", false) || p.getBoolean("trig_remote", false)
            val intent = Intent(context, TriggerService::class.java)
                .setAction(if (on) ACTION_START else ACTION_STOP)
            if (on) ContextCompat.startForegroundService(context, intent)
            else context.startService(intent)
        }
    }

    private lateinit var prefs: android.content.SharedPreferences
    private var sensorManager: SensorManager? = null
    private var soundThread: Thread? = null
    @Volatile private var running = false

    private var soundEnabled = false
    private var shakeEnabled = false
    private var screenshotEnabled = false
    private var remoteEnabled = false
    private var saveBattery = false
    private var lowBattCut = false
    private var sensitivity = 1   // 0 faible, 1 moyen, 2 élevé

    // Déclenchement à distance : boucle d'interrogation du serveur (option `trig_remote`).
    private var pollThread: Thread? = null
    @Volatile private var polling = false
    private val main = Handler(Looper.getMainLooper())

    // Détection de capture d'écran (observateur MediaStore, actif écran verrouillé/éteint).
    private var contentObserver: ContentObserver? = null
    private var observerThread: HandlerThread? = null
    private var watchStartSec = 0L       // début de surveillance (s) : ignore les images antérieures
    private var lastHandledId = -1L      // anti-doublon

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onCreate() {
        super.onCreate()
        prefs = getSharedPreferences("dualcam_settings", Context.MODE_PRIVATE)
        createChannel()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action == ACTION_STOP) { stopEverything(); return START_NOT_STICKY }

        soundEnabled = prefs.getBoolean("trig_sound", false)
        shakeEnabled = prefs.getBoolean("trig_shake", false)
        screenshotEnabled = prefs.getBoolean("trig_screenshot", false)
        remoteEnabled = prefs.getBoolean("trig_remote", false)
        saveBattery = prefs.getBoolean("trig_savebatt", true)
        lowBattCut = prefs.getBoolean("trig_lowbatt", true)
        sensitivity = prefs.getInt("trig_sensitivity", 1).coerceIn(0, 2)

        if (!soundEnabled && !shakeEnabled && !screenshotEnabled && !remoteEnabled) {
            stopEverything(); return START_NOT_STICKY
        }

        startForegroundWatching()
        running = true
        isWatching = true

        if (shakeEnabled) startShake()
        if (soundEnabled && hasMicPermission()) startSound()
        if (screenshotEnabled) startScreenshotWatch()
        if (remoteEnabled) startRemotePoll()

        return START_STICKY   // revient après un kill système (surveillance persistante)
    }

    // ---------------------------------------------------------------------------------------------
    // Déclenchement
    // ---------------------------------------------------------------------------------------------

    private fun trigger(reason: String) {
        if (!running) return
        running = false
        // Lance l'enregistrement (le micro sera repris par RecordingService) puis on s'arrête.
        RecordingService.startFromTrigger(this)
        stopEverything()
    }

    // ---------------------------------------------------------------------------------------------
    // Déclenchement à distance (depuis le PC, page web/remote.php)
    // ---------------------------------------------------------------------------------------------

    /**
     * Interroge le serveur toutes les [REMOTE_POLL_MS] pour relever un ordre déposé depuis le PC.
     *
     * Cette boucle ne démarre QUE si l'option « Déclenchement à distance » est cochée
     * (pref `trig_remote`) : sans elle, l'app ne contacte jamais ce point d'entrée et
     * le serveur n'a aucun moyen de piloter le téléphone.
     *
     * Contrairement aux déclencheurs locaux, on NE s'arrête PAS après un « start » :
     * il faut rester à l'écoute pour pouvoir recevoir le « stop » ensuite.
     */
    private fun startRemotePoll() {
        if (pollThread != null) return
        polling = true
        val api = ApiClient(SettingsStore(this))
        pollThread = Thread {
            while (polling) {
                when (api.pollRemoteCommand()) {
                    "start" -> if (!RecordingService.isRecording) main.post { remoteStart() }
                    "stop"  -> if (RecordingService.isRecording) main.post { remoteStop() }
                }
                try { Thread.sleep(REMOTE_POLL_MS) } catch (e: InterruptedException) { break }
            }
        }.also { it.isDaemon = true; it.start() }
    }

    private fun remoteStart() {
        // Libère le micro avant de lancer l'enregistrement : la détection sonore le monopolise.
        soundThread?.interrupt(); soundThread = null
        RecordingService.startFromTrigger(this)
    }

    private fun remoteStop() {
        startService(Intent(this, RecordingService::class.java).setAction(RecordingService.ACTION_STOP))
    }

    // ---------------------------------------------------------------------------------------------
    // Secousse (accéléromètre)
    // ---------------------------------------------------------------------------------------------

    private fun startShake() {
        val sm = getSystemService(SENSOR_SERVICE) as? SensorManager ?: return
        sensorManager = sm
        sm.getDefaultSensor(Sensor.TYPE_ACCELEROMETER)?.let {
            sm.registerListener(this, it, SensorManager.SENSOR_DELAY_NORMAL)
        }
    }

    override fun onSensorChanged(event: SensorEvent) {
        if (!running || event.sensor.type != Sensor.TYPE_ACCELEROMETER) return
        val (x, y, z) = event.values
        val g = sqrt(x * x + y * y + z * z)
        // Secousse forte = bien au-dessus de la gravité (~9.8).
        if (g - SensorManager.GRAVITY_EARTH > 12f) {
            if (lowBattCut && batteryLow()) return
            trigger("secousse")
        }
    }

    override fun onAccuracyChanged(sensor: Sensor?, accuracy: Int) {}

    // ---------------------------------------------------------------------------------------------
    // Son (micro) — écoute de l'amplitude, avec économie de batterie (écoute intermittente)
    // ---------------------------------------------------------------------------------------------

    private fun startSound() {
        val threshold = when (sensitivity) {
            2 -> 2500    // élevé : déclenche facilement
            0 -> 12000   // faible : il faut un gros bruit
            else -> 6000 // moyen
        }
        soundThread = Thread {
            val sampleRate = 16000
            val minBuf = AudioRecord.getMinBufferSize(
                sampleRate, AudioFormat.CHANNEL_IN_MONO, AudioFormat.ENCODING_PCM_16BIT
            ).coerceAtLeast(2048)
            val buffer = ShortArray(minBuf)
            var recorder: AudioRecord? = null
            try {
                while (running) {
                    if (lowBattCut && batteryLow()) { stopEverything(); break }
                    recorder = try {
                        AudioRecord(
                            MediaRecorder.AudioSource.MIC, sampleRate,
                            AudioFormat.CHANNEL_IN_MONO, AudioFormat.ENCODING_PCM_16BIT, minBuf
                        )
                    } catch (e: SecurityException) { null }
                    if (recorder == null || recorder.state != AudioRecord.STATE_INITIALIZED) break
                    recorder.startRecording()
                    // Fenêtre d'écoute ~300 ms.
                    val start = System.nanoTime()
                    var peak = 0
                    while (running && (System.nanoTime() - start) < 300_000_000L) {
                        val n = recorder.read(buffer, 0, buffer.size)
                        for (i in 0 until n) { val a = abs(buffer[i].toInt()); if (a > peak) peak = a }
                        if (peak > threshold) break
                    }
                    try { recorder.stop() } catch (_: Throwable) {}
                    recorder.release(); recorder = null

                    if (peak > threshold) { trigger("son"); break }
                    // Pause micro OFF pour économiser la batterie (écoute intermittente).
                    if (saveBattery) { try { Thread.sleep(700) } catch (_: InterruptedException) { break } }
                }
            } catch (_: Throwable) {
            } finally {
                try { recorder?.stop() } catch (_: Throwable) {}
                try { recorder?.release() } catch (_: Throwable) {}
            }
        }.also { it.start() }
    }

    // ---------------------------------------------------------------------------------------------
    // Capture d'écran — observation de MediaStore (fonctionne écran verrouillé / éteint car le
    // service reste au premier plan). Dès qu'une nouvelle image « Screenshot » apparaît → déclenche.
    // ---------------------------------------------------------------------------------------------

    private fun startScreenshotWatch() {
        watchStartSec = System.currentTimeMillis() / 1000L
        lastHandledId = latestImageId()   // ignore la dernière capture déjà présente
        val thread = HandlerThread("screenshot-obs").also { it.start() }
        observerThread = thread
        val obs = object : ContentObserver(Handler(thread.looper)) {
            override fun onChange(selfChange: Boolean, uri: Uri?) { checkForScreenshot() }
        }
        contentObserver = obs
        try {
            contentResolver.registerContentObserver(
                MediaStore.Images.Media.EXTERNAL_CONTENT_URI, true, obs
            )
        } catch (_: Throwable) {}
    }

    private fun readImagesPermission(): String =
        if (Build.VERSION.SDK_INT >= 33) Manifest.permission.READ_MEDIA_IMAGES
        else Manifest.permission.READ_EXTERNAL_STORAGE

    private fun hasImagesPermission(): Boolean =
        ContextCompat.checkSelfPermission(this, readImagesPermission()) ==
            PackageManager.PERMISSION_GRANTED

    /** ID de l'image la plus récente (pour ne pas re-déclencher sur une capture existante). */
    private fun latestImageId(): Long {
        if (!hasImagesPermission()) return -1L
        return try {
            contentResolver.query(
                MediaStore.Images.Media.EXTERNAL_CONTENT_URI,
                arrayOf(MediaStore.Images.Media._ID),
                null, null, "${MediaStore.Images.Media.DATE_ADDED} DESC"
            )?.use { c -> if (c.moveToFirst()) c.getLong(0) else -1L } ?: -1L
        } catch (_: Throwable) { -1L }
    }

    private fun checkForScreenshot() {
        if (!running || !screenshotEnabled || !hasImagesPermission()) return
        val pathCol = if (Build.VERSION.SDK_INT >= 29)
            MediaStore.Images.Media.RELATIVE_PATH else MediaStore.Images.Media.DATA
        val projection = arrayOf(
            MediaStore.Images.Media._ID,
            MediaStore.Images.Media.DISPLAY_NAME,
            MediaStore.Images.Media.DATE_ADDED,
            pathCol
        )
        try {
            contentResolver.query(
                MediaStore.Images.Media.EXTERNAL_CONTENT_URI, projection,
                null, null, "${MediaStore.Images.Media.DATE_ADDED} DESC"
            )?.use { c ->
                if (!c.moveToFirst()) return
                val id = c.getLong(0)
                val name = c.getString(1) ?: ""
                val dateAdded = c.getLong(2)
                val path = c.getString(3) ?: ""
                if (id == lastHandledId) return
                if (dateAdded < watchStartSec) return   // image antérieure au début de la surveillance
                val looksLikeShot = path.contains("screenshot", true) ||
                    name.contains("screenshot", true) || path.contains("capture", true)
                if (!looksLikeShot) return
                lastHandledId = id
                if (lowBattCut && batteryLow()) return
                trigger("capture d'écran")
            }
        } catch (_: Throwable) {}
    }

    // ---------------------------------------------------------------------------------------------

    private fun batteryLow(): Boolean {
        val bm = getSystemService(BATTERY_SERVICE) as? BatteryManager ?: return false
        val level = bm.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY)
        return level in 1..15
    }

    private fun hasMicPermission(): Boolean =
        ContextCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO) ==
            PackageManager.PERMISSION_GRANTED

    private fun stopEverything() {
        running = false
        isWatching = false
        polling = false
        pollThread?.interrupt(); pollThread = null
        try { sensorManager?.unregisterListener(this) } catch (_: Throwable) {}
        soundThread?.interrupt(); soundThread = null
        try { contentObserver?.let { contentResolver.unregisterContentObserver(it) } } catch (_: Throwable) {}
        contentObserver = null
        observerThread?.quitSafely(); observerThread = null
        stopForeground(STOP_FOREGROUND_REMOVE)
        stopSelf()
    }

    override fun onDestroy() {
        stopEverything()
        super.onDestroy()
    }

    // ---------------------------------------------------------------------------------------------
    // Notification premier plan
    // ---------------------------------------------------------------------------------------------

    private fun createChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val ch = NotificationChannel(CHANNEL_ID, "Surveillance", NotificationManager.IMPORTANCE_MIN)
            getSystemService(NotificationManager::class.java).createNotificationChannel(ch)
        }
    }

    private fun startForegroundWatching() {
        val open = PendingIntent.getActivity(
            this, 0, Intent(this, MainActivity::class.java),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )
        val notif: Notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("DualCam")
            .setContentText(getString(R.string.watching_notif))
            .setSmallIcon(R.drawable.ic_launcher)
            .setOngoing(true)
            .setPriority(NotificationCompat.PRIORITY_MIN)
            .setContentIntent(open)
            .build()

        val useMic = soundEnabled && hasMicPermission()
        when {
            // Son actif → type micro (Android 10+).
            useMic && Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q ->
                startForeground(NOTIF_ID, notif, ServiceInfo.FOREGROUND_SERVICE_TYPE_MICROPHONE)
            // Secousse seule sur Android 14+ → type « usage spécial ».
            !useMic && Build.VERSION.SDK_INT >= 34 ->
                startForeground(NOTIF_ID, notif, ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE)
            // Sinon (Android < 10, ou secousse seule sur < 14) : sans type.
            else -> startForeground(NOTIF_ID, notif)
        }
    }
}
