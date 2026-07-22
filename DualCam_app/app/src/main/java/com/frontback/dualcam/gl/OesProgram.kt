package com.frontback.dualcam.gl

import android.opengl.GLES11Ext
import android.opengl.GLES20
import java.nio.ByteBuffer
import java.nio.ByteOrder
import java.nio.FloatBuffer

/**
 * Programme GLSL dessinant une texture externe (OES : SurfaceTexture de l'écran ou de la caméra)
 * sur un quad plein cadre. Le placement (plein écran ou incrustation dans un coin) se fait via
 * glViewport par l'appelant. [texMatrix] oriente l'image (rotation caméra + transform SurfaceTexture).
 */
class OesProgram {

    companion object {
        private const val VS = """
            attribute vec4 aPosition;
            attribute vec4 aTexCoord;
            uniform mat4 uPosMatrix;
            uniform mat4 uTexMatrix;
            varying vec2 vTex;
            void main() {
                gl_Position = uPosMatrix * aPosition;
                vTex = (uTexMatrix * aTexCoord).xy;
            }
        """
        private const val FS = """
            #extension GL_OES_EGL_image_external : require
            precision mediump float;
            varying vec2 vTex;
            uniform samplerExternalOES uTex;
            void main() { gl_FragColor = texture2D(uTex, vTex); }
        """
        private val QUAD = floatArrayOf(-1f, -1f, 1f, -1f, -1f, 1f, 1f, 1f)
        private val TEX = floatArrayOf(0f, 0f, 1f, 0f, 0f, 1f, 1f, 1f)
    }

    private val program: Int
    private val aPos: Int
    private val aTex: Int
    private val uMat: Int
    private val uPos: Int
    private val uTexLoc: Int
    private val identity = FloatArray(16).also { android.opengl.Matrix.setIdentityM(it, 0) }
    private val quadBuf: FloatBuffer = QUAD.toBuf()
    private val texBuf: FloatBuffer = TEX.toBuf()

    init {
        val vs = compile(GLES20.GL_VERTEX_SHADER, VS)
        val fs = compile(GLES20.GL_FRAGMENT_SHADER, FS)
        program = GLES20.glCreateProgram()
        GLES20.glAttachShader(program, vs)
        GLES20.glAttachShader(program, fs)
        GLES20.glLinkProgram(program)
        aPos = GLES20.glGetAttribLocation(program, "aPosition")
        aTex = GLES20.glGetAttribLocation(program, "aTexCoord")
        uMat = GLES20.glGetUniformLocation(program, "uTexMatrix")
        uPos = GLES20.glGetUniformLocation(program, "uPosMatrix")
        uTexLoc = GLES20.glGetUniformLocation(program, "uTex")
    }

    fun createOesTexture(): Int {
        val t = IntArray(1)
        GLES20.glGenTextures(1, t, 0)
        GLES20.glBindTexture(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, t[0])
        GLES20.glTexParameteri(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, GLES20.GL_TEXTURE_MIN_FILTER, GLES20.GL_LINEAR)
        GLES20.glTexParameteri(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, GLES20.GL_TEXTURE_MAG_FILTER, GLES20.GL_LINEAR)
        GLES20.glTexParameteri(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, GLES20.GL_TEXTURE_WRAP_S, GLES20.GL_CLAMP_TO_EDGE)
        GLES20.glTexParameteri(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, GLES20.GL_TEXTURE_WRAP_T, GLES20.GL_CLAMP_TO_EDGE)
        return t[0]
    }

    fun draw(textureId: Int, texMatrix: FloatArray, posMatrix: FloatArray = identity) {
        GLES20.glUseProgram(program)
        GLES20.glActiveTexture(GLES20.GL_TEXTURE0)
        GLES20.glBindTexture(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, textureId)
        GLES20.glUniform1i(uTexLoc, 0)
        GLES20.glUniformMatrix4fv(uMat, 1, false, texMatrix, 0)
        GLES20.glUniformMatrix4fv(uPos, 1, false, posMatrix, 0)
        GLES20.glEnableVertexAttribArray(aPos)
        GLES20.glVertexAttribPointer(aPos, 2, GLES20.GL_FLOAT, false, 0, quadBuf)
        GLES20.glEnableVertexAttribArray(aTex)
        GLES20.glVertexAttribPointer(aTex, 2, GLES20.GL_FLOAT, false, 0, texBuf)
        GLES20.glDrawArrays(GLES20.GL_TRIANGLE_STRIP, 0, 4)
        GLES20.glDisableVertexAttribArray(aPos)
        GLES20.glDisableVertexAttribArray(aTex)
        GLES20.glBindTexture(GLES11Ext.GL_TEXTURE_EXTERNAL_OES, 0)
    }

    private fun compile(type: Int, src: String): Int {
        val s = GLES20.glCreateShader(type)
        GLES20.glShaderSource(s, src)
        GLES20.glCompileShader(s)
        return s
    }
}

private fun FloatArray.toBuf(): FloatBuffer {
    val bb = ByteBuffer.allocateDirect(size * 4).order(ByteOrder.nativeOrder())
    return bb.asFloatBuffer().apply { put(this@toBuf); position(0) }
}
