package com.example.photosync

import android.Manifest
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.GridLayoutManager
import com.example.photosync.databinding.ActivityCleanupBinding
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/**
 * Compare les photos du serveur à celles présentes sur le téléphone et affiche
 * celles qui sont SUR LE SERVEUR mais ABSENTES du téléphone (donc supprimées
 * localement). L'utilisateur choisit lesquelles supprimer du serveur, ou garder.
 */
class CleanupActivity : AppCompatActivity() {

    private lateinit var b: ActivityCleanupBinding
    private lateinit var api: ApiClient
    private lateinit var settings: SettingsStore
    private lateinit var adapter: CleanupAdapter
    private var allSelected = false

    private val permLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { result ->
        val granted = result[Manifest.permission.READ_MEDIA_IMAGES] == true ||
            result[Manifest.permission.READ_EXTERNAL_STORAGE] == true
        if (granted) load()
        else showEmpty(getString(R.string.cleanup_need_permission))
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        b = ActivityCleanupBinding.inflate(layoutInflater)
        setContentView(b.root)
        title = getString(R.string.cleanup_title)

        settings = SettingsStore(this)
        api = ApiClient(this, settings)

        adapter = CleanupAdapter(thumbUrl = { id -> api.thumbUrl(id) })
        adapter.onSelectionChanged = { updateDeleteButton() }
        b.recycler.layoutManager = GridLayoutManager(this, 3)
        b.recycler.adapter = adapter

        b.selectAllButton.setOnClickListener {
            allSelected = !allSelected
            adapter.selectAll(allSelected)
            b.selectAllButton.text = getString(
                if (allSelected) R.string.select_none else R.string.select_all
            )
        }
        b.deleteButton.setOnClickListener { confirmDelete() }

        if (hasPhotoPermission()) load() else requestPermission()
    }

    private fun updateDeleteButton() {
        val n = adapter.selectedCount()
        b.deleteButton.isEnabled = n > 0
        b.deleteButton.text = if (n > 0)
            getString(R.string.delete_from_server_n, n) else getString(R.string.delete_from_server)
    }

    /** Charge la liste serveur, scanne la galerie locale, calcule les absentes. */
    private fun load() {
        b.progress.visibility = View.VISIBLE
        b.emptyText.visibility = View.GONE
        lifecycleScope.launch {
            try {
                val missing = withContext(Dispatchers.IO) {
                    val server = api.fetchAllPhotos()
                    // Noms présents sur le téléphone (comparaison insensible à la casse).
                    val localNames = MediaScanner.queryImages(this@CleanupActivity)
                        .map { it.name.lowercase() }
                        .toHashSet()
                    server.filter { it.name.lowercase() !in localNames }
                }
                adapter.submit(missing)
                allSelected = false
                b.selectAllButton.text = getString(R.string.select_all)
                if (missing.isEmpty()) showEmpty(getString(R.string.cleanup_empty))
            } catch (e: Exception) {
                showEmpty(getString(R.string.gallery_error, e.message ?: ""))
            } finally {
                b.progress.visibility = View.GONE
                updateDeleteButton()
            }
        }
    }

    private fun confirmDelete() {
        val ids = adapter.selectedIds()
        if (ids.isEmpty()) return
        AlertDialog.Builder(this)
            .setTitle(getString(R.string.cleanup_confirm_title))
            .setMessage(getString(R.string.cleanup_confirm_msg, ids.size))
            .setNegativeButton(R.string.cleanup_keep, null)
            .setPositiveButton(R.string.cleanup_delete) { _, _ -> doDelete(ids) }
            .show()
    }

    private fun doDelete(ids: List<Long>) {
        b.progress.visibility = View.VISIBLE
        b.deleteButton.isEnabled = false
        lifecycleScope.launch {
            val ok = withContext(Dispatchers.IO) { api.deletePhotos(ids) }
            b.progress.visibility = View.GONE
            if (ok) {
                adapter.removeByIds(ids)
                Toast.makeText(this@CleanupActivity,
                    getString(R.string.cleanup_deleted, ids.size), Toast.LENGTH_SHORT).show()
                if (adapter.itemCount0() == 0) showEmpty(getString(R.string.cleanup_empty))
            } else {
                Toast.makeText(this@CleanupActivity, R.string.cleanup_delete_failed, Toast.LENGTH_LONG).show()
            }
            updateDeleteButton()
        }
    }

    private fun showEmpty(msg: String) {
        b.emptyText.text = msg
        b.emptyText.visibility = View.VISIBLE
    }

    private fun hasPhotoPermission(): Boolean {
        val perm = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU)
            Manifest.permission.READ_MEDIA_IMAGES else Manifest.permission.READ_EXTERNAL_STORAGE
        return ContextCompat.checkSelfPermission(this, perm) == PackageManager.PERMISSION_GRANTED
    }

    private fun requestPermission() {
        val perms = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU)
            arrayOf(Manifest.permission.READ_MEDIA_IMAGES, Manifest.permission.READ_MEDIA_VIDEO)
        else arrayOf(Manifest.permission.READ_EXTERNAL_STORAGE)
        permLauncher.launch(perms)
    }
}
