package com.example.photosync

import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import coil.load
import com.example.photosync.databinding.ActivityFullImageBinding

/** Affiche une photo en plein écran. */
class FullImageActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val b = ActivityFullImageBinding.inflate(layoutInflater)
        setContentView(b.root)

        val url = intent.getStringExtra(EXTRA_URL)
        if (url.isNullOrBlank()) {
            finish()
            return
        }

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

        // Toucher l'image pour fermer.
        b.fullImage.setOnClickListener { finish() }
    }

    companion object {
        const val EXTRA_URL = "url"
        const val EXTRA_NAME = "name"
    }
}
