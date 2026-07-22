package com.frontback.dualcam.gallery

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.view.View
import android.widget.Button
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.FileProvider
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.GridLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.frontback.dualcam.R
import com.frontback.dualcam.net.ApiClient
import com.frontback.dualcam.net.GeoStore
import com.frontback.dualcam.net.GoogleAuth
import com.frontback.dualcam.net.SettingsStore
import com.frontback.dualcam.net.ShareState
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File

class GalleryActivity : AppCompatActivity() {

    private lateinit var recycler: RecyclerView
    private lateinit var emptyText: TextView
    private lateinit var sendAllBtn: Button
    private lateinit var cancelBtn: Button
    private lateinit var selectBtn: Button
    private lateinit var shareBtn: Button
    private lateinit var accountBtn: Button

    // Barre du mode sélection
    private lateinit var selectionBar: View
    private lateinit var selCount: TextView
    private lateinit var selectAllBtn: Button
    private lateinit var shareSelBtn: Button
    private lateinit var deleteSelBtn: Button
    private lateinit var cancelSelBtn: Button

    /** Tâche d'envoi en cours (permet de l'annuler). */
    private var uploadJob: Job? = null
    private lateinit var accountText: TextView
    private lateinit var adapter: MediaAdapter
    private lateinit var settings: SettingsStore

    private var uploading = false

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_gallery)
        settings = SettingsStore(this)

        recycler = findViewById(R.id.recycler)
        emptyText = findViewById(R.id.emptyText)
        sendAllBtn = findViewById(R.id.sendAllBtn)
        cancelBtn = findViewById(R.id.cancelBtn)
        selectBtn = findViewById(R.id.selectBtn)
        shareBtn = findViewById(R.id.shareBtn)
        accountBtn = findViewById(R.id.accountBtn)
        accountText = findViewById(R.id.accountText)
        selectionBar = findViewById(R.id.selectionBar)
        selCount = findViewById(R.id.selCount)
        selectAllBtn = findViewById(R.id.selectAllBtn)
        shareSelBtn = findViewById(R.id.shareSelBtn)
        deleteSelBtn = findViewById(R.id.deleteSelBtn)
        cancelSelBtn = findViewById(R.id.cancelSelBtn)

        recycler.layoutManager = GridLayoutManager(this, 2)
        adapter = MediaAdapter(
            items = emptyList(),
            isUploaded = { settings.isUploaded(it.path) },
            onUpload = { item -> upload(listOf(item)) },
            onClick = { item -> openMediaDialog(item) },
            onSelectionChanged = { count -> updateSelectionBar(count) }
        )
        recycler.adapter = adapter

        selectBtn.setOnClickListener { enterSelection() }
        selectAllBtn.setOnClickListener { adapter.selectAll() }
        cancelSelBtn.setOnClickListener { exitSelection() }
        deleteSelBtn.setOnClickListener { deleteSelected() }
        shareSelBtn.setOnClickListener { shareSelected() }

        sendAllBtn.setOnClickListener {
            val pending = MediaRepository.listAll(this).filterNot { settings.isUploaded(it.path) }
            if (pending.isEmpty()) {
                toast("Tout est déjà envoyé ✓")
            } else {
                upload(pending)
            }
        }

        accountBtn.setOnClickListener {
            if (settings.isLoggedIn) confirmSignOut() else signIn()
        }

        shareBtn.setOnClickListener { openShareDialog() }

        // Annule tout l'envoi en cours (les vidéos déjà envoyées le restent).
        cancelBtn.setOnClickListener {
            uploadJob?.cancel()
            toast("Envoi annulé")
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Partage public (interrupteur global du compte, synchronisé avec le serveur)
    // ---------------------------------------------------------------------------------------------

    /** Récupère l'état du partage depuis le serveur puis ouvre la boîte de dialogue. */
    private fun openShareDialog() {
        if (!settings.isLoggedIn) { toast("Connecte-toi d'abord pour partager"); return }
        shareBtn.isEnabled = false
        lifecycleScope.launch {
            val state = withContext(Dispatchers.IO) { ApiClient(settings).getShare() }
            shareBtn.isEnabled = true
            if (state.ok) showShareDialog(state)
            else toast(state.error ?: "Partage indisponible")
        }
    }

    private fun showShareDialog(state: ShareState) {
        val url = state.url ?: ""
        val msg = if (state.enabled)
            "Le partage public est ACTIVÉ.\n\nToute personne disposant de ce lien peut voir tes vidéos, sans connexion :\n\n$url"
        else
            "Le partage public est désactivé.\nPersonne ne peut voir tes vidéos via un lien public."

        val b = androidx.appcompat.app.AlertDialog.Builder(this)
            .setTitle("🌐 Partage public")
            .setMessage(msg)
        if (state.enabled) {
            b.setPositiveButton("Partager le lien") { _, _ -> shareLink(url) }
            b.setNeutralButton("Désactiver") { _, _ -> toggleShare(false) }
            b.setNegativeButton(R.string.close, null)
        } else {
            b.setPositiveButton("Activer") { _, _ -> toggleShare(true) }
            b.setNegativeButton(R.string.close, null)
        }
        b.show()
    }

    private fun toggleShare(enable: Boolean) {
        lifecycleScope.launch {
            val state = withContext(Dispatchers.IO) { ApiClient(settings).setShare(enable) }
            if (!state.ok) { toast(state.error ?: "Échec du changement"); return@launch }
            toast(if (state.enabled) "Partage activé 🌐" else "Partage désactivé 🔒")
            showShareDialog(state)
        }
    }

    /** Ouvre la feuille de partage Android (WhatsApp, SMS, e-mail…) avec le lien. */
    private fun shareLink(url: String) {
        val send = Intent(Intent.ACTION_SEND).apply {
            type = "text/plain"
            putExtra(Intent.EXTRA_TEXT, url)
        }
        startActivity(Intent.createChooser(send, "Partager le lien DualCam"))
    }

    // ---------------------------------------------------------------------------------------------
    // Sélection multiple : supprimer (téléphone + serveur) ou partager la sélection
    // ---------------------------------------------------------------------------------------------

    private fun enterSelection() {
        adapter.setSelectionMode(true)
        selectionBar.visibility = View.VISIBLE
        selectBtn.visibility = View.GONE
    }

    private fun exitSelection() {
        adapter.setSelectionMode(false)
        selectionBar.visibility = View.GONE
        selectBtn.visibility = View.VISIBLE
    }

    private fun updateSelectionBar(count: Int) {
        selCount.text = "$count sélectionné(s)"
    }

    override fun onBackPressed() {
        if (adapter.isSelectionMode()) exitSelection() else super.onBackPressed()
    }

    /** Supprime la sélection : fichier local + position mémorisée + corbeille serveur. */
    private fun deleteSelected() {
        val paths = adapter.selectedPaths()
        if (paths.isEmpty()) { toast("Coche au moins une vidéo"); return }
        androidx.appcompat.app.AlertDialog.Builder(this)
            .setTitle("🗑 Supprimer")
            .setMessage("Supprimer ${paths.size} vidéo(s) du téléphone ET du serveur ?\n(récupérable 30 jours côté serveur)")
            .setPositiveButton("Supprimer") { _, _ -> doDelete(paths) }
            .setNegativeButton(R.string.close, null)
            .show()
    }

    private fun doDelete(paths: List<String>) {
        val names = paths.map { File(it).name }
        lifecycleScope.launch {
            // Serveur : mise à la corbeille (si connecté).
            val serverOk = if (settings.isLoggedIn)
                withContext(Dispatchers.IO) { ApiClient(settings).deleteByNames(names) }
            else true
            // Téléphone : fichier local + position mémorisée.
            withContext(Dispatchers.IO) {
                paths.forEach { p ->
                    try { File(p).delete() } catch (_: Exception) {}
                    settings.unmarkUploaded(p)
                }
            }
            exitSelection()
            refresh()
            toast(if (serverOk) "Supprimé ✓" else "Supprimé du téléphone (serveur injoignable)")
        }
    }

    /** Partage la sélection via la feuille Android (WhatsApp, SMS, e-mail…). */
    private fun shareSelected() {
        val paths = adapter.selectedPaths()
        if (paths.isEmpty()) { toast("Coche au moins une vidéo"); return }
        val uris = ArrayList<Uri>()
        paths.forEach { p ->
            val f = File(p)
            if (f.exists()) uris.add(FileProvider.getUriForFile(this, "$packageName.fileprovider", f))
        }
        if (uris.isEmpty()) { toast("Fichiers introuvables"); return }
        val send = Intent(Intent.ACTION_SEND_MULTIPLE).apply {
            type = if (paths.all { it.endsWith(".jpg", true) }) "image/*" else "video/*"
            putParcelableArrayListExtra(Intent.EXTRA_STREAM, uris)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        startActivity(Intent.createChooser(send, "Partager la sélection"))
        exitSelection()
    }

    // ---------------------------------------------------------------------------------------------
    // Clic sur une vidéo : choix « Voir » / « Plus d'infos » (position GPS, carte, adresse)
    // ---------------------------------------------------------------------------------------------

    private fun openMediaDialog(item: MediaItem) {
        androidx.appcompat.app.AlertDialog.Builder(this)
            .setTitle(File(item.path).name)
            .setItems(arrayOf("▶ Voir", "ℹ️ Plus d'infos")) { _, which ->
                if (which == 0) openViewer(item) else showInfo(item)
            }
            .show()
    }

    private fun openViewer(item: MediaItem) {
        val intent = Intent(this, ViewerActivity::class.java).apply {
            putExtra(ViewerActivity.EXTRA_PATH, item.path)
            putExtra(ViewerActivity.EXTRA_IS_VIDEO, item.isVideo)
        }
        startActivity(intent)
    }

    /** « Plus d'infos » : coordonnées GPS + choix « Ouvrir la carte » / « Voir l'adresse ». */
    private fun showInfo(item: MediaItem) {
        val geo = GeoStore.get(this, File(item.path).name)
        if (geo == null) {
            androidx.appcompat.app.AlertDialog.Builder(this)
                .setTitle("ℹ️ Plus d'infos")
                .setMessage("📍 Position inconnue pour cette vidéo.\n(filmée sans GPS ou avant l'activation de la localisation)")
                .setPositiveButton(R.string.close, null)
                .show()
            return
        }
        val (lat, lng) = geo
        androidx.appcompat.app.AlertDialog.Builder(this)
            .setTitle("ℹ️ Plus d'infos")
            .setMessage("📍 Latitude : $lat\n📍 Longitude : $lng")
            .setPositiveButton("🗺️ Ouvrir la carte") { _, _ -> openMap(lat, lng) }
            .setNeutralButton("🏠 Voir l'adresse") { _, _ -> showAddress(lat, lng) }
            .setNegativeButton(R.string.close, null)
            .show()
    }

    /** Ouvre l'application de cartes sur la position (repli : Google Maps dans le navigateur). */
    private fun openMap(lat: Double, lng: Double) {
        val geoUri = Uri.parse("geo:$lat,$lng?q=$lat,$lng(Vidéo DualCam)")
        val mapIntent = Intent(Intent.ACTION_VIEW, geoUri)
        if (mapIntent.resolveActivity(packageManager) != null) {
            startActivity(mapIntent)
        } else {
            startActivity(Intent(Intent.ACTION_VIEW,
                Uri.parse("https://www.google.com/maps/search/?api=1&query=$lat,$lng")))
        }
    }

    /** Convertit la position en adresse lisible (Geocoder du téléphone, hors ligne si dispo). */
    private fun showAddress(lat: Double, lng: Double) {
        toast("Recherche de l'adresse…")
        lifecycleScope.launch {
            val addr = withContext(Dispatchers.IO) {
                try {
                    @Suppress("DEPRECATION")
                    val list = android.location.Geocoder(this@GalleryActivity, java.util.Locale.FRANCE)
                        .getFromLocation(lat, lng, 1)
                    list?.firstOrNull()?.getAddressLine(0)
                } catch (e: Exception) { null }
            }
            androidx.appcompat.app.AlertDialog.Builder(this@GalleryActivity)
                .setTitle("🏠 Adresse")
                .setMessage(addr ?: "Adresse introuvable (hors ligne ou position imprécise).")
                .setPositiveButton(R.string.close, null)
                .show()
        }
    }

    override fun onResume() {
        super.onResume()
        refresh()
        updateAccountUi()
    }

    /** Met à jour le libellé du compte + le bouton connexion/déconnexion. */
    private fun updateAccountUi() {
        if (settings.isLoggedIn) {
            val name = settings.username.ifBlank { "compte Google" }
            accountText.text = getString(R.string.connected_as, name)
            accountBtn.text = getString(R.string.sign_out)
        } else {
            accountText.text = getString(R.string.not_connected)
            accountBtn.text = getString(R.string.sign_in_google)
        }
    }

    /** Lance la connexion Google (fenêtre système) puis met à jour l'affichage. */
    private fun signIn() {
        accountBtn.isEnabled = false
        lifecycleScope.launch {
            val auth = GoogleAuth.ensureLoggedIn(this@GalleryActivity, settings)
            toast(if (auth.ok) "Connecté ✓" else (auth.error ?: "Connexion échouée"))
            accountBtn.isEnabled = true
            updateAccountUi()
        }
    }

    private fun confirmSignOut() {
        androidx.appcompat.app.AlertDialog.Builder(this)
            .setMessage(getString(R.string.connected_as, settings.username.ifBlank { "compte Google" }))
            .setPositiveButton(R.string.sign_out) { _, _ ->
                settings.logout()
                updateAccountUi()
                toast("Déconnecté")
            }
            .setNegativeButton(R.string.close, null)
            .show()
    }

    private fun refresh() {
        val items = MediaRepository.listAll(this)
        adapter.update(items)
        val empty = items.isEmpty()
        emptyText.visibility = if (empty) View.VISIBLE else View.GONE
        recycler.visibility = if (empty) View.GONE else View.VISIBLE
    }

    /** Connecte le compte Google si besoin, puis envoie les fichiers au serveur PhotoSync. */
    private fun upload(items: List<MediaItem>) {
        if (uploading) { toast(getString(R.string.sending)); return }
        uploading = true
        sendAllBtn.isEnabled = false
        cancelBtn.visibility = View.VISIBLE   // le bouton « Annuler » apparaît pendant l'envoi

        uploadJob = lifecycleScope.launch {
            var okCount = 0
            var failMsg: String? = null
            try {
                // 1) S'assurer d'être connecté (fenêtre Google si nécessaire).
                val auth = GoogleAuth.ensureLoggedIn(this@GalleryActivity, settings)
                updateAccountUi()
                if (!auth.ok) {
                    toast(auth.error ?: "Connexion nécessaire pour envoyer")
                    return@launch
                }

                // 2) Envoyer les fichiers un par un (interrompable via « Annuler »).
                val api = ApiClient(settings)
                for (item in items) {
                    toast("${getString(R.string.sending)} (${okCount + 1}/${items.size})")
                    val res = withContext(Dispatchers.IO) { api.uploadFile(File(item.path)) }
                    if (res.ok) {
                        settings.markUploaded(item.path)
                        okCount++
                        refresh()
                    } else {
                        failMsg = res.error
                        if (res.code == 401) { settings.logout() }
                        break
                    }
                }

                toast(
                    when {
                        failMsg == null -> "$okCount vidéo(s) envoyée(s) ✓"
                        okCount > 0 -> "$okCount envoyée(s), puis erreur : $failMsg"
                        else -> "Échec : $failMsg"
                    }
                )
            } finally {
                // Réinitialise l'UI dans TOUS les cas (fin normale, erreur ou annulation).
                uploading = false
                sendAllBtn.isEnabled = true
                cancelBtn.visibility = View.GONE
                refresh()
                updateAccountUi()
            }
        }
    }

    private fun toast(msg: String) = Toast.makeText(this, msg, Toast.LENGTH_SHORT).show()
}
