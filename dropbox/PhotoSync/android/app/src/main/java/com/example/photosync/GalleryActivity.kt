package com.example.photosync

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.GridLayoutManager
import com.example.photosync.databinding.ActivityGalleryBinding
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/** Affiche les photos stockées sur le serveur (galerie en ligne). */
class GalleryActivity : AppCompatActivity() {

    private lateinit var b: ActivityGalleryBinding
    private lateinit var api: ApiClient
    private lateinit var adapter: PhotoAdapter

    private var page = 1
    private var pages = 1
    private var loading = false
    private var startedOnce = false // évite un double chargement au tout premier affichage

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        b = ActivityGalleryBinding.inflate(layoutInflater)
        setContentView(b.root)

        api = ApiClient(this, SettingsStore(this))
        adapter = PhotoAdapter(
            thumbUrl = { id -> api.thumbUrl(id) },
            onClick = { photo ->
                startActivity(
                    Intent(this, FullImageActivity::class.java)
                        .putExtra(FullImageActivity.EXTRA_URL, api.fullUrl(photo.id))
                        .putExtra(FullImageActivity.EXTRA_NAME, photo.name)
                        .putExtra(FullImageActivity.EXTRA_IS_VIDEO, photo.isVideo)
                )
            },
        )
        b.recycler.layoutManager = GridLayoutManager(this, 3)
        b.recycler.adapter = adapter

        // Galerie dédiée à un seul type (ouverte depuis « Voir les photos » ou « Voir les vidéos ») :
        // pas d'onglets, on verrouille le filtre et on titre l'écran.
        val kind = intent.getStringExtra(EXTRA_KIND)
        b.filterGroup.visibility = View.GONE
        adapter.setFilter(if (kind == KIND_VIDEOS) MediaFilter.VIDEOS else MediaFilter.IMAGES)
        title = getString(if (kind == KIND_VIDEOS) R.string.view_videos else R.string.view_photos)

        // Filtre d'affichage par origine : Tout / Téléphone / Ordinateur / Web.
        b.sourceFilterGroup.check(R.id.srcAll)
        b.sourceFilterGroup.addOnButtonCheckedListener { _, checkedId, isChecked ->
            if (!isChecked) return@addOnButtonCheckedListener
            adapter.setSourceFilter(
                when (checkedId) {
                    R.id.srcPhone    -> "phone"
                    R.id.srcComputer -> "computer"
                    R.id.srcWeb      -> "web"
                    else             -> null
                }
            )
            updateEmptyState()
        }

        b.loadMoreButton.setOnClickListener { loadPage(page + 1) }
        // Bouton « Actualiser » : recharge depuis le début → fait apparaître les nouveaux
        // éléments (dont ceux envoyés depuis le PC/web) non encore affichés.
        b.refreshButton.setOnClickListener { loadPage(1) }

        loadPage(1)
    }

    override fun onResume() {
        super.onResume()
        // Au retour sur la galerie, on recharge pour refléter les ajouts côté serveur
        // (sauf au tout premier affichage, déjà chargé dans onCreate).
        if (startedOnce) loadPage(1) else startedOnce = true
    }

    /** Affiche le message « vide » selon ce qui est réellement visible après filtre. */
    private fun updateEmptyState() {
        if (adapter.visibleCount() == 0) {
            b.emptyText.text = getString(R.string.gallery_empty)
            b.emptyText.visibility = View.VISIBLE
        } else {
            b.emptyText.visibility = View.GONE
        }
    }

    private fun loadPage(p: Int) {
        if (loading) return
        loading = true
        b.emptyText.visibility = View.GONE
        b.loadMoreButton.visibility = View.GONE
        if (p == 1) b.progress.visibility = View.VISIBLE

        lifecycleScope.launch {
            try {
                val result = withContext(Dispatchers.IO) { api.fetchPhotos(p) }
                if (p == 1) adapter.clear()
                adapter.addAll(result.photos)
                page = result.page
                pages = result.pages
                b.loadMoreButton.visibility = if (page < pages) View.VISIBLE else View.GONE
                updateEmptyState()
            } catch (e: Exception) {
                b.emptyText.text = getString(R.string.gallery_error, e.message ?: "")
                b.emptyText.visibility = View.VISIBLE
                Toast.makeText(this@GalleryActivity, e.message ?: "Erreur", Toast.LENGTH_LONG).show()
            } finally {
                b.progress.visibility = View.GONE
                loading = false
            }
        }
    }

    companion object {
        const val EXTRA_KIND = "kind"
        const val KIND_PHOTOS = "photos"
        const val KIND_VIDEOS = "videos"
    }
}
