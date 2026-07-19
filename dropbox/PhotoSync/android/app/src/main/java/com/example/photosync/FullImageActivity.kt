package com.example.photosync

import android.content.ActivityNotFoundException
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.view.View
import android.widget.MediaController
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import coil.load
import com.example.photosync.databinding.ActivityFullImageBinding

/** Affiche une photo en plein écran, ou lit une vidéo. */
class FullImageActivity : AppCompatActivity() {

    private lateinit var b: ActivityFullImageBinding

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        b = ActivityFullImageBinding.inflate(layoutInflater)
        setContentView(b.root)

        val url = intent.getStringExtra(EXTRA_URL)
        if (url.isNullOrBlank()) {
            finish()
            return
        }

        if (intent.getBooleanExtra(EXTRA_IS_VIDEO, false)) showVideo(url) else showImage(url)
    }

    /** Photo : chargement via Coil. Toucher l'image pour fermer. */
    private fun showImage(url: String) {
        b.fullVideo.visibility = View.GONE
        b.fullImage.visibility = View.VISIBLE
        b.fullImage.load(url) {
            crossfade(true)
            listener(
                onSuccess = { _, _ -> b.fullProgress.visibility = View.GONE },
                onError = { _, _ ->
                    b.fullProgress.visibility = View.GONE
                    Toast.makeText(this@FullImageActivity, "Image non chargée", Toast.LENGTH_SHORT).show()
                },
            )
        }
        b.fullImage.setOnClickListener { finish() }
    }

    /**
     * Vidéo : deux possibilités de lecture.
     *  1) En local : lecteur intégré (VideoView) avec contrôles (play/pause/seek).
     *  2) Par lien : bouton « Ouvrir en ligne » → navigateur / autre app (VLC, lecteur système),
     *     utilisé en secours si le lecteur intégré ne sait pas lire le format.
     */
    private fun showVideo(url: String) {
        b.fullImage.visibility = View.GONE
        b.fullVideo.visibility = View.VISIBLE

        // Possibilité 2 (toujours disponible) : ouvrir le lien dans une autre app.
        b.openExternalButton.visibility = View.VISIBLE
        b.openExternalButton.setOnClickListener { openExternally(url) }

        // Possibilité 1 : lecture locale.
        val controller = MediaController(this)
        controller.setAnchorView(b.fullVideo)
        b.fullVideo.setMediaController(controller)
        b.fullVideo.setVideoURI(Uri.parse(url))
        b.fullVideo.setOnPreparedListener {
            b.fullProgress.visibility = View.GONE
            it.start()
        }
        b.fullVideo.setOnErrorListener { _, _, _ ->
            // Échec du lecteur interne : on bascule sur le lien externe.
            b.fullProgress.visibility = View.GONE
            Toast.makeText(this, R.string.video_error_use_link, Toast.LENGTH_LONG).show()
            true
        }
        b.fullVideo.requestFocus()
    }

    /** Ouvre la vidéo via son lien en ligne dans le navigateur / une autre app. */
    private fun openExternally(url: String) {
        val intent = Intent(Intent.ACTION_VIEW).apply {
            setDataAndType(Uri.parse(url), "video/*")
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        try {
            startActivity(Intent.createChooser(intent, getString(R.string.open_external)))
        } catch (e: ActivityNotFoundException) {
            // Aucune app vidéo : on tente le navigateur (le serveur diffuse en streaming).
            try {
                startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
            } catch (e2: ActivityNotFoundException) {
                Toast.makeText(this, R.string.no_app_for_link, Toast.LENGTH_LONG).show()
            }
        }
    }

    override fun onPause() {
        super.onPause()
        if (b.fullVideo.isPlaying) b.fullVideo.pause()
    }

    companion object {
        const val EXTRA_URL = "url"
        const val EXTRA_NAME = "name"
        const val EXTRA_IS_VIDEO = "is_video"
    }
}
