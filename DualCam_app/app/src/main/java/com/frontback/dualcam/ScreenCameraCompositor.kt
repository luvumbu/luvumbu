package com.frontback.dualcam

import android.annotation.SuppressLint
import android.content.Context
import android.graphics.SurfaceTexture
import android.hardware.camera2.CameraCharacteristics
import android.hardware.camera2.CameraDevice
import android.hardware.camera2.CameraManager
import android.opengl.EGLSurface
import android.opengl.GLES20
import android.opengl.Matrix
import android.os.Handler
import android.os.HandlerThread
import android.util.Log
import android.view.Surface
import com.frontback.dualcam.gl.EglCore
import com.frontback.dualcam.gl.OesProgram

/**
 * Compose l'ÉCRAN (plein cadre) + la CAMÉRA (incrustée dans un coin) dans une seule vidéo,
 * en dessinant les deux via OpenGL sur la Surface d'entrée d'un MediaRecorder.
 *
 * @param encoderSurface  surface d'entrée du MediaRecorder
 * @param outW/outH       dimensions de la vidéo (= écran)
 * @param cameraFacing    LENS_FACING_FRONT ou LENS_FACING_BACK
 * @param corner          0=bas-droit, 1=bas-gauche, 2=haut-droit, 3=haut-gauche
 */
class ScreenCameraCompositor(
    private val context: Context,
    private val encoderSurface: Surface,
    private val outW: Int,
    private val outH: Int,
    private val cameraFacing: Int,
    private val corner: Int,
    private val camRotExtra: Int
) {
    companion object {
        private const val TAG = "ScreenCamCompositor"
        private const val CAM_W = 1280
        private const val CAM_H = 720
        private const val FRAME_MS = 33L
    }

    private val thread = HandlerThread("compositor-gl").apply { start() }
    private val handler = Handler(thread.looper)

    private lateinit var egl: EglCore
    private lateinit var program: OesProgram
    private var encSurface: EGLSurface? = null

    private var screenTexId = 0
    private var cameraTexId = 0
    private lateinit var screenST: SurfaceTexture
    private lateinit var cameraST: SurfaceTexture
    private val screenMatrix = FloatArray(16)

    private val cameraManager = context.getSystemService(Context.CAMERA_SERVICE) as CameraManager
    private var cameraId = ""
    private var sensorOrientation = 90
    private var cameraDevice: CameraDevice? = null

    @Volatile private var running = false
    private val renderLoop = object : Runnable {
        override fun run() {
            if (!running) return
            drawFrame()
            handler.postDelayed(this, FRAME_MS)
        }
    }

    /** Démarre le compositeur ; renvoie la Surface où le VirtualDisplay doit projeter l'écran. */
    fun start(onScreenSurfaceReady: (Surface) -> Unit) {
        handler.post {
            egl = EglCore()
            encSurface = egl.createWindowSurface(encoderSurface)
            egl.makeCurrent(encSurface!!)
            program = OesProgram()
            Matrix.setIdentityM(screenMatrix, 0)

            screenTexId = program.createOesTexture()
            cameraTexId = program.createOesTexture()
            screenST = SurfaceTexture(screenTexId).apply { setDefaultBufferSize(outW, outH) }
            cameraST = SurfaceTexture(cameraTexId).apply { setDefaultBufferSize(CAM_W, CAM_H) }

            onScreenSurfaceReady(Surface(screenST))
            openCamera()

            running = true
            handler.postDelayed(renderLoop, FRAME_MS)
        }
    }

    private fun drawFrame() {
        val enc = encSurface ?: return
        egl.makeCurrent(enc)
        try { screenST.updateTexImage(); screenST.getTransformMatrix(screenMatrix) } catch (_: Throwable) {}
        try { cameraST.updateTexImage() } catch (_: Throwable) {}

        GLES20.glClearColor(0f, 0f, 0f, 1f)
        GLES20.glClear(GLES20.GL_COLOR_BUFFER_BIT)

        // Écran plein cadre
        GLES20.glViewport(0, 0, outW, outH)
        program.draw(screenTexId, screenMatrix)

        // Caméra incrustée dans le coin (ratio portrait 9:16 pour éviter la déformation)
        val pipW = (outW * 0.28f).toInt()
        val pipH = (pipW * 16f / 9f).toInt()
        val m = 24
        val (x, y) = when (corner) {
            1 -> m to m                                   // bas-gauche
            2 -> (outW - pipW - m) to (outH - pipH - m)   // haut-droit
            3 -> m to (outH - pipH - m)                   // haut-gauche
            else -> (outW - pipW - m) to m                // bas-droit
        }
        GLES20.glViewport(x, y, pipW, pipH)
        program.draw(cameraTexId, camTexMatrix(), camPosMatrix())

        egl.setPresentationTime(enc, System.nanoTime())
        egl.swapBuffers(enc)
    }

    /** Coordonnées de texture : transform de la SurfaceTexture (crop + flip). */
    private fun camTexMatrix(): FloatArray {
        val stm = FloatArray(16); cameraST.getTransformMatrix(stm)
        return stm
    }

    /** Rotation de la géométrie (propre) selon l'orientation capteur + miroir pour la frontale. */
    private fun camPosMatrix(): FloatArray {
        val p = FloatArray(16); Matrix.setIdentityM(p, 0)
        val angle = ((sensorOrientation + camRotExtra) % 360).toFloat()
        Matrix.rotateM(p, 0, angle, 0f, 0f, 1f)
        if (cameraFacing == CameraCharacteristics.LENS_FACING_FRONT) Matrix.scaleM(p, 0, -1f, 1f, 1f)
        return p
    }

    @SuppressLint("MissingPermission")
    private fun openCamera() {
        for (id in cameraManager.cameraIdList) {
            val ch = cameraManager.getCameraCharacteristics(id)
            if (ch.get(CameraCharacteristics.LENS_FACING) == cameraFacing) {
                cameraId = id
                sensorOrientation = ch.get(CameraCharacteristics.SENSOR_ORIENTATION) ?: 90
                Log.i(TAG, "CAM id=$id facing=$cameraFacing sensorOrientation=$sensorOrientation")
                break
            }
        }
        if (cameraId.isEmpty()) { Log.e(TAG, "caméra $cameraFacing introuvable"); return }
        try {
            cameraManager.openCamera(cameraId, object : CameraDevice.StateCallback() {
                override fun onOpened(device: CameraDevice) {
                    cameraDevice = device
                    startCameraSession(device)
                }
                override fun onDisconnected(device: CameraDevice) { device.close() }
                override fun onError(device: CameraDevice, error: Int) {
                    Log.e(TAG, "erreur caméra: $error"); device.close()
                }
            }, handler)
        } catch (t: Throwable) { Log.e(TAG, "openCamera", t) }
    }

    @Suppress("DEPRECATION")
    private fun startCameraSession(device: CameraDevice) {
        val surface = Surface(cameraST)
        device.createCaptureSession(listOf(surface), object : android.hardware.camera2.CameraCaptureSession.StateCallback() {
            override fun onConfigured(session: android.hardware.camera2.CameraCaptureSession) {
                val req = device.createCaptureRequest(CameraDevice.TEMPLATE_PREVIEW).apply { addTarget(surface) }
                session.setRepeatingRequest(req.build(), null, handler)
            }
            override fun onConfigureFailed(session: android.hardware.camera2.CameraCaptureSession) {
                Log.e(TAG, "config session caméra échouée")
            }
        }, handler)
    }

    fun stop() {
        handler.post {
            running = false
            handler.removeCallbacks(renderLoop)
            try { cameraDevice?.close() } catch (_: Throwable) {}
            cameraDevice = null
            if (::cameraST.isInitialized) cameraST.release()
            if (::screenST.isInitialized) screenST.release()
            encSurface?.let { egl.releaseSurface(it) }
            if (::egl.isInitialized) egl.release()
        }
        thread.quitSafely()
    }
}
