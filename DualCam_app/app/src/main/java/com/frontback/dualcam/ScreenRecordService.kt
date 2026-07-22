package com.frontback.dualcam

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Intent
import android.content.pm.ServiceInfo
import android.hardware.display.DisplayManager
import android.hardware.display.VirtualDisplay
import android.media.MediaRecorder
import android.media.projection.MediaProjection
import android.media.projection.MediaProjectionManager
import android.os.Build
import android.os.Environment
import android.os.Handler
import android.os.IBinder
import android.os.Looper
import android.util.Log
import androidx.core.app.NotificationCompat
import java.io.File
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import kotlin.math.roundToInt

/**
 * Enregistre l'écran du téléphone (screencast) via MediaProjection, dans un service de
 * premier plan → continue même quand on navigue dans d'autres applications.
 */
class ScreenRecordService : Service() {

    companion object {
        const val ACTION_START = "com.frontback.dualcam.SCREEN_START"
        const val ACTION_STOP = "com.frontback.dualcam.SCREEN_STOP"
        const val EXTRA_CODE = "code"
        const val EXTRA_DATA = "data"
        const val EXTRA_W = "w"
        const val EXTRA_H = "h"
        const val EXTRA_DPI = "dpi"
        const val EXTRA_CAM_FACING = "camFacing"   // -1 = aucune caméra (écran seul)
        const val EXTRA_CORNER = "corner"
        const val EXTRA_CAM_ROT = "camRot"
        const val CAM_NONE = -1

        private const val TAG = "ScreenRecordService"
        private const val CHANNEL_ID = "screen_recording"
        private const val NOTIF_ID = 43

        @Volatile var isRecording = false; private set
    }

    private var projection: MediaProjection? = null
    private var virtualDisplay: VirtualDisplay? = null
    private var recorder: MediaRecorder? = null
    private var outputPath: String? = null
    private var compositor: ScreenCameraCompositor? = null
    private var camFacing = CAM_NONE
    private var corner = 0
    private var camRot = 0
    private val main = Handler(Looper.getMainLooper())

    // Capture du son des autres applications (Android 10+). En dessous : micro seul via MediaRecorder.
    private val captureAppAudio = Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q
    private var audioRecorder: InternalAudioRecorder? = null
    private var videoTmpPath: String? = null   // vidéo seule (sans audio) quand captureAppAudio
    private var audioTmpPath: String? = null   // piste audio seule (.m4a)

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onCreate() {
        super.onCreate()
        createChannel()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        when (intent?.action) {
            ACTION_START -> start(intent)
            ACTION_STOP -> stopRecording()
        }
        return START_NOT_STICKY
    }

    private fun start(intent: Intent) {
        if (isRecording) return
        camFacing = intent.getIntExtra(EXTRA_CAM_FACING, CAM_NONE)
        corner = intent.getIntExtra(EXTRA_CORNER, 0)
        camRot = intent.getIntExtra(EXTRA_CAM_ROT, 0)
        // Doit passer en premier plan (type mediaProjection) AVANT de créer la projection.
        startAsForeground(withCamera = camFacing != CAM_NONE)

        val code = intent.getIntExtra(EXTRA_CODE, 0)
        @Suppress("DEPRECATION")
        val data: Intent? = intent.getParcelableExtra(EXTRA_DATA)
        if (data == null) { stopSelf(); return }

        var w = intent.getIntExtra(EXTRA_W, 720)
        var h = intent.getIntExtra(EXTRA_H, 1280)
        val dpi = intent.getIntExtra(EXTRA_DPI, 320)

        // Limite la résolution pour rester dans les capacités de l'encodeur.
        val maxDim = 1920
        val longest = maxOf(w, h)
        if (longest > maxDim) {
            val scale = maxDim.toFloat() / longest
            w = (w * scale).roundToInt(); h = (h * scale).roundToInt()
        }
        w -= w % 2; h -= h % 2  // dimensions paires

        val mpm = getSystemService(MediaProjectionManager::class.java)
        val proj = mpm.getMediaProjection(code, data) ?: run { stopSelf(); return }
        projection = proj
        proj.registerCallback(object : MediaProjection.Callback() {
            override fun onStop() { stopRecording() }
        }, main)

        try {
            recorder = buildRecorder(w, h)
            if (camFacing == CAM_NONE) {
                // Écran seul : le VirtualDisplay projette directement dans le MediaRecorder.
                virtualDisplay = proj.createVirtualDisplay(
                    "DualCamScreen", w, h, dpi,
                    DisplayManager.VIRTUAL_DISPLAY_FLAG_AUTO_MIRROR,
                    recorder!!.surface, null, null
                )
                recorder!!.start()
                startAppAudioCapture(proj)
                isRecording = true
            } else {
                // Écran + caméra : OpenGL compose l'écran + la caméra dans le MediaRecorder.
                val comp = ScreenCameraCompositor(this, recorder!!.surface, w, h, camFacing, corner, camRot)
                compositor = comp
                isRecording = true
                comp.start { screenSurface ->
                    virtualDisplay = proj.createVirtualDisplay(
                        "DualCamScreen", w, h, dpi,
                        DisplayManager.VIRTUAL_DISPLAY_FLAG_AUTO_MIRROR,
                        screenSurface, null, null
                    )
                    try { recorder!!.start() } catch (t: Throwable) { Log.e(TAG, "recorder.start", t) }
                    startAppAudioCapture(proj)
                }
            }
        } catch (t: Throwable) {
            Log.e(TAG, "démarrage capture écran", t)
            stopRecording()
        }
    }

    private fun buildRecorder(w: Int, h: Int): MediaRecorder {
        val dir = File(getExternalFilesDir(Environment.DIRECTORY_MOVIES) ?: filesDir, "").apply { mkdirs() }
        val stamp = timeStamp()
        val finalFile = File(dir, "Ecran_$stamp.mp4")
        outputPath = finalFile.absolutePath

        // Avec capture du son des apps : le MediaRecorder ne produit QUE la vidéo (fichier temporaire),
        // l'audio est capté à part puis fusionné. Sinon, l'audio micro est encodé directement.
        val recorderFile = if (captureAppAudio) File(dir, "Ecran_${stamp}_video.tmp").also { videoTmpPath = it.absolutePath }
                           else finalFile
        if (captureAppAudio) audioTmpPath = File(dir, "Ecran_${stamp}_audio.tmp").absolutePath

        val rec = if (Build.VERSION.SDK_INT >= 31) MediaRecorder(this) else @Suppress("DEPRECATION") MediaRecorder()
        rec.apply {
            if (!captureAppAudio) setAudioSource(MediaRecorder.AudioSource.MIC)
            setVideoSource(MediaRecorder.VideoSource.SURFACE)
            setOutputFormat(MediaRecorder.OutputFormat.MPEG_4)
            setOutputFile(recorderFile.absolutePath)
            setVideoSize(w, h)
            setVideoEncoder(MediaRecorder.VideoEncoder.H264)
            if (!captureAppAudio) setAudioEncoder(MediaRecorder.AudioEncoder.AAC)
            setVideoEncodingBitRate(8_000_000)
            setVideoFrameRate(30)
            prepare()
        }
        return rec
    }

    /** Démarre la capture du son des autres applications (+ micro), si supporté. */
    private fun startAppAudioCapture(proj: MediaProjection) {
        if (!captureAppAudio) return
        val path = audioTmpPath ?: return
        try {
            val ar = InternalAudioRecorder(proj, File(path), withMic = true)
            audioRecorder = ar
            ar.start()
        } catch (t: Throwable) {
            Log.e(TAG, "démarrage capture audio interne", t)
            audioRecorder = null
        }
    }

    private fun stopRecording() {
        if (!isRecording && recorder == null) { stopSelf(); return }
        isRecording = false
        try { compositor?.stop() } catch (_: Throwable) {}
        compositor = null
        // Arrêter l'audio AVANT la projection (l'AudioRecord dépend de la MediaProjection).
        val audio = audioRecorder
        try { audio?.stop() } catch (_: Throwable) {}
        audioRecorder = null
        try { recorder?.stop() } catch (t: Throwable) { Log.w(TAG, "recorder.stop", t) }
        try { recorder?.reset() } catch (_: Throwable) {}
        try { recorder?.release() } catch (_: Throwable) {}
        recorder = null
        try { virtualDisplay?.release() } catch (_: Throwable) {}
        virtualDisplay = null
        try { projection?.stop() } catch (_: Throwable) {}
        projection = null

        val vTmp = videoTmpPath
        val aTmp = audioTmpPath
        if (captureAppAudio && vTmp != null && aTmp != null) {
            // Fusion vidéo (sans son) + audio (apps + micro) → mp4 final, sur un thread de fond.
            val hasAudio = audio?.hasData == true
            Thread {
                val ok = if (hasAudio) MediaMuxUtil.muxVideoAudio(vTmp, aTmp, outputPath!!) else false
                if (!ok) {
                    // Pas d'audio exploitable : on garde la vidéo seule comme fichier final.
                    try { File(vTmp).renameTo(File(outputPath!!)) } catch (_: Throwable) {}
                }
                try { File(vTmp).delete() } catch (_: Throwable) {}
                try { File(aTmp).delete() } catch (_: Throwable) {}
                Log.i(TAG, "capture écran enregistrée: $outputPath (audio=$hasAudio)")
                main.post {
                    stopForeground(STOP_FOREGROUND_REMOVE)
                    stopSelf()
                }
            }.start()
            return
        }

        Log.i(TAG, "capture écran enregistrée: $outputPath")
        stopForeground(STOP_FOREGROUND_REMOVE)
        stopSelf()
    }

    override fun onDestroy() {
        if (isRecording) stopRecording()
        super.onDestroy()
    }

    // --- Notification ---

    private fun createChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            getSystemService(NotificationManager::class.java).createNotificationChannel(
                NotificationChannel(CHANNEL_ID, "Capture d'écran", NotificationManager.IMPORTANCE_LOW)
            )
        }
    }

    private fun buildNotification(): Notification {
        val open = PendingIntent.getActivity(
            this, 0, Intent(this, MainActivity::class.java),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )
        val stop = PendingIntent.getService(
            this, 2, Intent(this, ScreenRecordService::class.java).setAction(ACTION_STOP),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )
        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("DualCam")
            .setContentText("Enregistrement de l'écran…")
            .setSmallIcon(R.drawable.ic_launcher)
            .setOngoing(true)
            .setContentIntent(open)
            .addAction(0, "Arrêter", stop)
            .build()
    }

    private fun startAsForeground(withCamera: Boolean) {
        val notif = buildNotification()
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            var types = ServiceInfo.FOREGROUND_SERVICE_TYPE_MEDIA_PROJECTION or
                ServiceInfo.FOREGROUND_SERVICE_TYPE_MICROPHONE
            if (withCamera) types = types or ServiceInfo.FOREGROUND_SERVICE_TYPE_CAMERA
            startForeground(NOTIF_ID, notif, types)
        } else {
            startForeground(NOTIF_ID, notif)
        }
    }

    private fun timeStamp(): String =
        SimpleDateFormat("yyyyMMdd_HHmmss_SSS", Locale.US).format(Date())
}
