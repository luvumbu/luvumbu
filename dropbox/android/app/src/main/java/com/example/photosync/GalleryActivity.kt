package com.example.photosync

import android.content.ActivityNotFoundException
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.view.View
import android.widget.PopupMenu
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.GridLayoutManager
import com.example.photosync.databinding.ActivityGalleryBinding
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/** Affiche les fichiers stockés sur le serveur, filtrables par type et triables. */
class GalleryActivity : AppCompatActivity() {

    private lateinit var b: ActivityGalleryBinding
    private lateinit var api: ApiClient
    private lateinit var adapter: PhotoAdapter

    private var page = 1
    private var pages = 1
    private var loading = false

    // Filtre de catégorie et tri courants (appliqués côté serveur).
    private var currentType = "all"
    private var currentSort = "date_desc"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        b = ActivityGalleryBinding.inflate(layoutInflater)
        setContentView(b.root)

        api = ApiClient(this, SettingsStore(this))
        adapter = PhotoAdapter(
            thumbUrl = { id -> api.thumbUrl(id) },
            onClick = { item -> openItem(item) },
        )
        b.recycler.layoutManager = GridLayoutManager(this, 3)
        b.recycler.adapter = adapter

        b.loadMoreButton.setOnClickListener { loadPage(page + 1) }

        // Onglets de catégories : recharge depuis la page 1 au changement.
        b.categoryChips.setOnCheckedStateChangeListener { _, checkedIds ->
            val id = checkedIds.firstOrNull() ?: return@setOnCheckedStateChangeListener
            currentType = typeForChip(id)
            loadPage(1)
        }

        b.sortButton.setOnClickListener { showSortMenu() }

        loadPage(1)
    }

    private fun typeForChip(chipId: Int): String = when (chipId) {
        R.id.chipPhoto -> "photo"
        R.id.chipVideo -> "video"
        R.id.chipAudio -> "audio"
        R.id.chipDocument -> "document"
        R.id.chipOther -> "other"
        else -> "all"
    }

    private fun showSortMenu() {
        val popup = PopupMenu(this, b.sortButton)
        popup.menuInflater.inflate(R.menu.sort_menu, popup.menu)
        // Coche l'option active.
        val activeItem = when (currentSort) {
            "date_asc" -> R.id.sort_date_asc
            "name_asc" -> R.id.sort_name_asc
            "name_desc" -> R.id.sort_name_desc
            "size_desc" -> R.id.sort_size_desc
            "size_asc" -> R.id.sort_size_asc
            "type" -> R.id.sort_type
            else -> R.id.sort_date_desc
        }
        popup.menu.findItem(activeItem)?.isChecked = true
        popup.setOnMenuItemClickListener { item ->
            currentSort = when (item.itemId) {
                R.id.sort_date_asc -> "date_asc"
                R.id.sort_name_asc -> "name_asc"
                R.id.sort_name_desc -> "name_desc"
                R.id.sort_size_desc -> "size_desc"
                R.id.sort_size_asc -> "size_asc"
                R.id.sort_type -> "type"
                else -> "date_desc"
            }
            loadPage(1)
            true
        }
        popup.show()
    }

    /** Ouvre une photo en plein écran ; les autres fichiers dans une application externe. */
    private fun openItem(item: ServerPhoto) {
        if (item.category == "photo") {
            startActivity(
                Intent(this, FullImageActivity::class.java)
                    .putExtra(FullImageActivity.EXTRA_URL, api.fullUrl(item.id))
                    .putExtra(FullImageActivity.EXTRA_NAME, item.name)
            )
        } else {
            try {
                startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(api.fullUrl(item.id))))
            } catch (e: ActivityNotFoundException) {
                Toast.makeText(this, R.string.open_external_failed, Toast.LENGTH_LONG).show()
            }
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
                val result = withContext(Dispatchers.IO) { api.fetchPhotos(p, currentType, currentSort) }
                if (p == 1) {
                    adapter.clear()
                    b.recycler.scrollToPosition(0)
                }
                adapter.addAll(result.photos)
                page = result.page
                pages = result.pages
                if (result.total == 0) {
                    b.emptyText.text = getString(
                        if (currentType == "all") R.string.gallery_empty else R.string.gallery_empty_filtered
                    )
                    b.emptyText.visibility = View.VISIBLE
                }
                b.loadMoreButton.visibility = if (page < pages) View.VISIBLE else View.GONE
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
}
