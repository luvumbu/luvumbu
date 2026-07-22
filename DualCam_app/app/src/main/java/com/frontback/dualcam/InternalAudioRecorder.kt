package com.frontback.dualcam

import android.annotation.SuppressLint
import android.media.AudioAttributes
import android.media.AudioFormat
import android.media.AudioPlaybackCaptureConfiguration
import android.media.AudioRecord
import android.media.MediaCodec
import android.media.MediaCodecInfo
import android.media.MediaFormat
import android.media.MediaMuxer
import android.media.MediaRecorder
import android.media.projection.MediaProjection
import android.os.Build
import android.util.Log
import androidx.annotation.RequiresApi
import java.io.File

/**
 * Capture le SON DES AUTRES APPLICATIONS (musique TikTok, audio YouTube…) via
 * [AudioPlaybackCaptureConfiguration] (Android 10+), optionnellement mélangé avec le MICRO,
 * encode le tout en AAC et l'écrit dans un fichier .m4a séparé.
 *
 * Le fichier produit est ensuite fusionné avec la vidéo par [ScreenRecordService].
 *
 * @param projection  la même MediaProjection que celle utilisée pour l'écran
 * @param outputFile  fichier .m4a de sortie (piste audio seule)
 * @param withMic     true = mixe aussi le micro (voix par-dessus) ; false = son des apps seul
 */
@RequiresApi(Build.VERSION_CODES.Q)
class InternalAudioRecorder(
    private val projection: MediaProjection,
    private val outputFile: File,
    private val withMic: Boolean
) {
    companion object {
        private const val TAG = "InternalAudioRec"
        private const val SAMPLE_RATE = 44_100
        private const val BIT_RATE = 128_000
        private const val FRAMES_PER_READ = 1024
    }

    @Volatile private var running = false
    private var thread: Thread? = null

    /** true si au moins un échantillon audio a été effectivement encodé. */
    @Volatile var hasData = false; private set

    fun start() {
        if (running) return
        running = true
        thread = Thread({ runLoop() }, "internal-audio").apply { start() }
    }

    /** Arrête la capture et finalise le fichier .m4a. Bloquant (max ~2 s). */
    fun stop() {
        running = false
        try { thread?.join(2_000) } catch (_: InterruptedException) {}
        thread = null
    }

    @SuppressLint("MissingPermission")
    private fun runLoop() {
        var playback: AudioRecord? = null
        var mic: AudioRecord? = null
        var codec: MediaCodec? = null
        var muxer: MediaMuxer? = null
        var trackIndex = -1
        var muxerStarted = false
        var presentationUs = 0L
        val usPerFrame = 1_000_000.0 / SAMPLE_RATE

        try {
            // --- Source « son des autres apps » ---
            val config = AudioPlaybackCaptureConfiguration.Builder(projection)
                .addMatchingUsage(AudioAttributes.USAGE_MEDIA)
                .addMatchingUsage(AudioAttributes.USAGE_GAME)
                .addMatchingUsage(AudioAttributes.USAGE_UNKNOWN)
                .build()

            val pbFormat = AudioFormat.Builder()
                .setEncoding(AudioFormat.ENCODING_PCM_16BIT)
                .setSampleRate(SAMPLE_RATE)
                .setChannelMask(AudioFormat.CHANNEL_IN_STEREO)
                .build()

            var pbMin = AudioRecord.getMinBufferSize(
                SAMPLE_RATE, AudioFormat.CHANNEL_IN_STEREO, AudioFormat.ENCODING_PCM_16BIT
            )
            if (pbMin <= 0) pbMin = SAMPLE_RATE * 2 * 2 // ~1 s stéréo 16-bit

            playback = AudioRecord.Builder()
                .setAudioPlaybackCaptureConfig(config)
                .setAudioFormat(pbFormat)
                .setBufferSizeInBytes(pbMin * 2)
                .build()

            if (playback.state != AudioRecord.STATE_INITIALIZED) {
                Log.e(TAG, "AudioRecord (playback capture) non initialisé")
                return
            }

            // --- Micro (optionnel), mixé par-dessus ---
            if (withMic) {
                var micMin = AudioRecord.getMinBufferSize(
                    SAMPLE_RATE, AudioFormat.CHANNEL_IN_MONO, AudioFormat.ENCODING_PCM_16BIT
                )
                if (micMin <= 0) micMin = SAMPLE_RATE * 2
                try {
                    val m = AudioRecord(
                        MediaRecorder.AudioSource.MIC, SAMPLE_RATE,
                        AudioFormat.CHANNEL_IN_MONO, AudioFormat.ENCODING_PCM_16BIT, micMin * 2
                    )
                    mic = if (m.state == AudioRecord.STATE_INITIALIZED) m else { m.release(); null }
                } catch (t: Throwable) {
                    Log.w(TAG, "micro indisponible, capture apps seule", t)
                }
            }

            // --- Encodeur AAC (stéréo) ---
            val fmt = MediaFormat.createAudioFormat(MediaFormat.MIMETYPE_AUDIO_AAC, SAMPLE_RATE, 2).apply {
                setInteger(MediaFormat.KEY_AAC_PROFILE, MediaCodecInfo.CodecProfileLevel.AACObjectLC)
                setInteger(MediaFormat.KEY_BIT_RATE, BIT_RATE)
                setInteger(MediaFormat.KEY_MAX_INPUT_SIZE, FRAMES_PER_READ * 2 * 2 * 2)
            }
            codec = MediaCodec.createEncoderByType(MediaFormat.MIMETYPE_AUDIO_AAC).apply {
                configure(fmt, null, null, MediaCodec.CONFIGURE_FLAG_ENCODE)
                start()
            }

            muxer = MediaMuxer(outputFile.absolutePath, MediaMuxer.OutputFormat.MUXER_OUTPUT_MPEG_4)

            playback.startRecording()
            mic?.startRecording()

            val pbBuf = ShortArray(FRAMES_PER_READ * 2)   // stéréo entrelacé
            val micBuf = ShortArray(FRAMES_PER_READ)      // mono
            val mixBytes = ByteArray(FRAMES_PER_READ * 2 * 2) // stéréo 16-bit -> octets
            val info = MediaCodec.BufferInfo()

            while (running) {
                val pbRead = playback.read(pbBuf, 0, pbBuf.size)
                if (pbRead <= 0) continue
                val frames = pbRead / 2

                var micRead = 0
                if (mic != null) {
                    micRead = mic.read(micBuf, 0, frames)
                    if (micRead < 0) micRead = 0
                }

                var bi = 0
                for (i in 0 until frames) {
                    var l = pbBuf[2 * i].toInt()
                    var r = pbBuf[2 * i + 1].toInt()
                    if (i < micRead) {
                        val m = micBuf[i].toInt()
                        l += m; r += m
                    }
                    if (l > 32767) l = 32767 else if (l < -32768) l = -32768
                    if (r > 32767) r = 32767 else if (r < -32768) r = -32768
                    mixBytes[bi++] = (l and 0xFF).toByte()
                    mixBytes[bi++] = ((l shr 8) and 0xFF).toByte()
                    mixBytes[bi++] = (r and 0xFF).toByte()
                    mixBytes[bi++] = ((r shr 8) and 0xFF).toByte()
                }
                val bytes = frames * 4

                val inIndex = codec.dequeueInputBuffer(10_000)
                if (inIndex >= 0) {
                    val inBuf = codec.getInputBuffer(inIndex)!!
                    inBuf.clear()
                    inBuf.put(mixBytes, 0, bytes)
                    codec.queueInputBuffer(inIndex, 0, bytes, presentationUs, 0)
                    presentationUs += (frames * usPerFrame).toLong()
                }

                // Récupère les paquets AAC prêts.
                var outIndex = codec.dequeueOutputBuffer(info, 0)
                while (outIndex >= 0) {
                    if (info.flags and MediaCodec.BUFFER_FLAG_CODEC_CONFIG != 0) info.size = 0
                    if (outIndex == MediaCodec.INFO_OUTPUT_FORMAT_CHANGED) break
                    if (info.size > 0 && muxerStarted) {
                        val outBuf = codec.getOutputBuffer(outIndex)!!
                        outBuf.position(info.offset)
                        outBuf.limit(info.offset + info.size)
                        muxer.writeSampleData(trackIndex, outBuf, info)
                        hasData = true
                    }
                    codec.releaseOutputBuffer(outIndex, false)
                    outIndex = codec.dequeueOutputBuffer(info, 0)
                }
                if (outIndex == MediaCodec.INFO_OUTPUT_FORMAT_CHANGED && !muxerStarted) {
                    trackIndex = muxer.addTrack(codec.outputFormat)
                    muxer.start()
                    muxerStarted = true
                }
            }

            // --- Fin de flux : on vide l'encodeur ---
            val inIndex = codec.dequeueInputBuffer(10_000)
            if (inIndex >= 0) {
                codec.queueInputBuffer(inIndex, 0, 0, presentationUs, MediaCodec.BUFFER_FLAG_END_OF_STREAM)
            }
            var eos = false
            while (!eos) {
                val outIndex = codec.dequeueOutputBuffer(info, 10_000)
                when {
                    outIndex == MediaCodec.INFO_OUTPUT_FORMAT_CHANGED -> {
                        if (!muxerStarted) {
                            trackIndex = muxer.addTrack(codec.outputFormat)
                            muxer.start()
                            muxerStarted = true
                        }
                    }
                    outIndex >= 0 -> {
                        if (info.flags and MediaCodec.BUFFER_FLAG_CODEC_CONFIG != 0) info.size = 0
                        if (info.size > 0 && muxerStarted) {
                            val outBuf = codec.getOutputBuffer(outIndex)!!
                            outBuf.position(info.offset)
                            outBuf.limit(info.offset + info.size)
                            muxer.writeSampleData(trackIndex, outBuf, info)
                            hasData = true
                        }
                        codec.releaseOutputBuffer(outIndex, false)
                        if (info.flags and MediaCodec.BUFFER_FLAG_END_OF_STREAM != 0) eos = true
                    }
                    else -> { /* INFO_TRY_AGAIN_LATER : on attend l'EOS */ }
                }
            }
        } catch (t: Throwable) {
            Log.e(TAG, "capture audio interne", t)
        } finally {
            try { playback?.stop() } catch (_: Throwable) {}
            try { playback?.release() } catch (_: Throwable) {}
            try { mic?.stop() } catch (_: Throwable) {}
            try { mic?.release() } catch (_: Throwable) {}
            try { codec?.stop() } catch (_: Throwable) {}
            try { codec?.release() } catch (_: Throwable) {}
            if (muxerStarted) {
                try { muxer?.stop() } catch (_: Throwable) {}
            }
            try { muxer?.release() } catch (_: Throwable) {}
        }
    }
}
