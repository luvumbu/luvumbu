package com.example.photosync

import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.GridLayoutManager
import com.example.photosync.databinding.ActivityServerDeletionsBinding
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/**
 * Liste les photos qui étaient envoyées mais qui ont été SUPPRIMÉES côté serveur.
 * L'utilisateur choisit, pour la sélection : les RENVOYER, ou NE PAS les renvoyer (oublier).
 */
class ServerDeletionsActivity : AppCompatActivity() {

    private lateinit var b: ActivityServerDeletionsBinding
    private lateinit var settings: SettingsStore
    private lateinit var api: ApiClient
    private lateinit var adapter: LocalSelectAdapter
    private var allSelected = false

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        b = ActivityServerDeletionsBinding.inflate(layoutInflater)
        setContentView(b.root)
        title = getString(R.string.deletions_title)
        settings = SettingsStore(this)
        api = ApiClient(this, settings)

        adapter = LocalSelectAdapter()
        adapter.onSelectionChanged = { updateButtons() }
        b.recycler.layoutManager = GridLayoutManager(this, 3)
        b.recycler.adapter = adapter

        b.selectAllButton.setOnClickListener {
            allSelected = !allSelected
            adapter.selectAll(allSelected)
            b.selectAllButton.text = getString(if (allSelected) R.string.select_none else R.string.select_all)
        }
        b.resendButton.setOnClickListener { resendSelected() }
        b.ignoreButton.setOnClickListener { ignoreSelected() }

        load()
    }

    private fun updateButtons() {
        val n = adapter.selectedCount()
        b.resendButton.isEnabled = n > 0
        b.ignoreButton.isEnabled = n > 0
        b.resendButton.text = if (n > 0) getString(R.string.deletions_resend_n, n) else getString(R.string.deletions_resend)
    }

    private fun load() {
        b.progress.visibility = View.VISIBLE
        b.emptyText.visibility = View.GONE
        lifecycleScope.launch {
            val deleted = withContext(Dispatchers.IO) { detect() }
            b.progress.visibility = View.GONE
            allSelected = false
            b.selectAllButton.text = getString(R.string.select_all)
            if (deleted == null) {
                showEmpty(getString(R.string.deletions_no_server))
            } else if (deleted.isEmpty()) {
                showEmpty(getString(R.string.deletions_none))
            } else {
                adapter.submit(deleted)
            }
            updateButtons()
        }
    }

    /** Photos encore sur le téléphone, déjà envoyées, mais ABSENTES du serveur (hors ignorées). */
    private suspend fun detect(): List<LocalPhoto>? {
        val names = api.fetchNames() ?: return null  // null = échec réseau → on ne conclut rien
        val serverNames = names.mapTo(HashSet()) { it.lowercase() }
        val uploaded = SyncApp.instance.db.uploadedDao().allIds().toHashSet()
        val ignored = settings.ignoredDeletions.mapTo(HashSet()) { it.lowercase() }
        return MediaScanner.queryImages(this).filter {
            it.id in uploaded && it.name.lowercase() !in serverNames && it.name.lowercase() !in ignored
        }
    }

    /** Renvoyer : on retire ces photos du suivi local → la prochaine synchro les ré-envoie. */
    private fun resendSelected() {
        val sel = adapter.selectedItems()
        if (sel.isEmpty()) return
        lifecycleScope.launch {
            withContext(Dispatchers.IO) {
                val dao = SyncApp.instance.db.uploadedDao()
                sel.forEach { dao.delete(it.id) }
            }
            UploadWorker.runNow(this@ServerDeletionsActivity, settings.wifiOnly)
            adapter.removeByIds(sel.map { it.id })
            Toast.makeText(this@ServerDeletionsActivity,
                getString(R.string.deletions_resent, sel.size), Toast.LENGTH_SHORT).show()
            if (adapter.count() == 0) showEmpty(getString(R.string.deletions_none))
            updateButtons()
        }
    }

    /** Ne pas renvoyer : on mémorise leurs noms pour ne plus jamais les signaler. */
    private fun ignoreSelected() {
        val sel = adapter.selectedItems()
        if (sel.isEmpty()) return
        val newIgnored = HashSet(settings.ignoredDeletions)
        sel.forEach { newIgnored.add(it.name.lowercase()) }
        settings.ignoredDeletions = newIgnored
        adapter.removeByIds(sel.map { it.id })
        Toast.makeText(this, getString(R.string.deletions_ignored, sel.size), Toast.LENGTH_SHORT).show()
        if (adapter.count() == 0) showEmpty(getString(R.string.deletions_none))
        updateButtons()
    }

    private fun showEmpty(msg: String) {
        b.emptyText.text = msg
        b.emptyText.visibility = View.VISIBLE
    }
}
