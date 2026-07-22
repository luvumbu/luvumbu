package com.frontback.dualcam.gallery

import android.content.Intent
import android.graphics.BitmapFactory
import android.net.Uri
import android.os.Bundle
import android.widget.ImageButton
import android.widget.ImageView
import android.widget.MediaController
import android.widget.Toast
import android.widget.VideoView
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.FileProvider
import com.frontback.dualcam.R
import java.io.File

/** Affiche une photo en plein écran ou lit une vidéo, avec possibilité de supprimer. */
class ViewerActivity : AppCompatActivity() {

    companion object {
        const val EXTRA_PATH = "path"
        const val EXTRA_IS_VIDEO = "is_video"
    }

    private lateinit var path: String
    private var isVideo = false

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_viewer)

        path = intent.getStringExtra(EXTRA_PATH) ?: run { finish(); return }
        isVideo = intent.getBooleanExtra(EXTRA_IS_VIDEO, false)

        val imageView = findViewById<ImageView>(R.id.imageView)
        val videoView = findViewById<VideoView>(R.id.videoView)
        val deleteButton = findViewById<ImageButton>(R.id.deleteButton)
        val shareButton = findViewById<ImageButton>(R.id.shareButton)

        if (isVideo) {
            videoView.visibility = android.view.View.VISIBLE
            val controller = MediaController(this)
            controller.setAnchorView(videoView)
            videoView.setMediaController(controller)
            videoView.setVideoURI(Uri.fromFile(File(path)))
            videoView.setOnPreparedListener { it.isLooping = false; videoView.start() }
            videoView.setOnErrorListener { _, _, _ ->
                Toast.makeText(this, "Lecture impossible", Toast.LENGTH_SHORT).show(); true
            }
        } else {
            imageView.visibility = android.view.View.VISIBLE
            val bmp = BitmapFactory.decodeFile(path)
            imageView.setImageBitmap(bmp)
        }

        deleteButton.setOnClickListener { confirmDelete() }
        shareButton.setOnClickListener { share() }
    }

    private fun share() {
        val file = File(path)
        if (!file.exists()) {
            Toast.makeText(this, "Fichier introuvable", Toast.LENGTH_SHORT).show(); return
        }
        try {
            val uri = FileProvider.getUriForFile(this, "$packageName.fileprovider", file)
            val intent = Intent(Intent.ACTION_SEND).apply {
                type = if (isVideo) "video/mp4" else "image/jpeg"
                putExtra(Intent.EXTRA_STREAM, uri)
                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            }
            startActivity(Intent.createChooser(intent, getString(R.string.share)))
        } catch (t: Throwable) {
            Toast.makeText(this, "Partage impossible", Toast.LENGTH_SHORT).show()
        }
    }

    private fun confirmDelete() {
        AlertDialog.Builder(this)
            .setMessage("Supprimer cet enregistrement ?")
            .setPositiveButton(R.string.delete) { _, _ ->
                if (File(path).delete()) {
                    Toast.makeText(this, "Supprimé", Toast.LENGTH_SHORT).show()
                }
                finish()
            }
            .setNegativeButton(android.R.string.cancel, null)
            .show()
    }
}
