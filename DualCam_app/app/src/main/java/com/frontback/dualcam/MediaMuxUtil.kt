package com.frontback.dualcam

import android.media.MediaCodec
import android.media.MediaExtractor
import android.media.MediaFormat
import android.media.MediaMuxer
import android.util.Log
import java.nio.ByteBuffer

/**
 * Recombine une vidéo (piste vidéo seule) et un fichier audio (.m4a) dans un seul mp4,
 * sans réencoder (simple copie des paquets).
 *
 * Utilisé par [ScreenRecordService] (capture d'écran) et par [RecordingService]
 * (caméra + son des autres applications).
 */
object MediaMuxUtil {

    private const val TAG = "MediaMuxUtil"

    /** Renvoie true si le fichier [outPath] a bien été produit. */
    fun muxVideoAudio(videoPath: String, audioPath: String, outPath: String): Boolean {
        var muxer: MediaMuxer? = null
        val vEx = MediaExtractor()
        val aEx = MediaExtractor()
        try {
            vEx.setDataSource(videoPath)
            aEx.setDataSource(audioPath)

            val vTrack = selectTrack(vEx, "video/") ?: return false
            val aTrack = selectTrack(aEx, "audio/") ?: return false
            vEx.selectTrack(vTrack); aEx.selectTrack(aTrack)

            muxer = MediaMuxer(outPath, MediaMuxer.OutputFormat.MUXER_OUTPUT_MPEG_4)
            val outV = muxer.addTrack(vEx.getTrackFormat(vTrack))
            val outA = muxer.addTrack(aEx.getTrackFormat(aTrack))
            muxer.start()

            copyTrack(vEx, muxer, outV)
            copyTrack(aEx, muxer, outA)

            muxer.stop()
            return true
        } catch (t: Throwable) {
            Log.e(TAG, "fusion audio/vidéo", t)
            return false
        } finally {
            try { vEx.release() } catch (_: Throwable) {}
            try { aEx.release() } catch (_: Throwable) {}
            try { muxer?.release() } catch (_: Throwable) {}
        }
    }

    private fun selectTrack(ex: MediaExtractor, prefix: String): Int? {
        for (i in 0 until ex.trackCount) {
            val mime = ex.getTrackFormat(i).getString(MediaFormat.KEY_MIME) ?: continue
            if (mime.startsWith(prefix)) return i
        }
        return null
    }

    private fun copyTrack(ex: MediaExtractor, muxer: MediaMuxer, outTrack: Int) {
        val buffer = ByteBuffer.allocate(1 shl 20) // 1 Mo
        val info = MediaCodec.BufferInfo()
        while (true) {
            val size = ex.readSampleData(buffer, 0)
            if (size < 0) break
            info.offset = 0
            info.size = size
            info.presentationTimeUs = ex.sampleTime
            info.flags = ex.sampleFlags
            muxer.writeSampleData(outTrack, buffer, info)
            ex.advance()
        }
    }
}
