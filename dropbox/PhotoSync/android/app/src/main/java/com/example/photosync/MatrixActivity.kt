package com.example.photosync

import android.database.ContentObserver
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.provider.MediaStore
import android.view.View
import androidx.appcompat.app.AppCompatActivity
import androidx.work.WorkInfo
import androidx.work.WorkManager
import com.example.photosync.databinding.ActivityMatrixBinding
import java.util.Calendar

/**
 * Moniteur « temps réel » (style Matrix, vert sur noir) : journalise en direct
 * ce que fait l'app — détections galerie, état des envois, tentatives, erreurs.
 */
class MatrixActivity : AppCompatActivity() {

    private lateinit var b: ActivityMatrixBinding
    private lateinit var settings: SettingsStore
    private val lastLine = HashMap<String, String>()
    private var lastDetectMs = 0L
    private var observing = false

    private val galleryObserver = object : ContentObserver(Handler(Looper.getMainLooper())) {
        override fun onChange(selfChange: Boolean) {
            val now = System.currentTimeMillis()
            if (now - lastDetectMs < 1200) return
            lastDetectMs = now
            append("◉ galerie modifiée — nouvel élément détecté")
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        b = ActivityMatrixBinding.inflate(layoutInflater)
        setContentView(b.root)
        title = "Moniteur"
        settings = SettingsStore(this)

        append("> initialisation du moniteur…")
        append("> compte : ${settings.username.ifBlank { "—" }}")
        append("> serveur : ${settings.serverUrl}")
        append("> surveillance galerie : ${if (settings.watchGallery) "ON" else "OFF"} | auto : ${if (settings.autoSync) "ON" else "OFF"}")
        append("> en attente d'activité…")

        val wm = WorkManager.getInstance(this)
        wm.getWorkInfosForUniqueWorkLiveData(UploadWorker.ONESHOT).observe(this) { infos ->
            infos.firstOrNull()?.let { onWork("MANUEL", it) }
        }
        wm.getWorkInfosForUniqueWorkLiveData(UploadWorker.PERIODIC).observe(this) { infos ->
            infos.firstOrNull()?.let { onWork("AUTO", it) }
        }

        b.runButton.setOnClickListener {
            UploadWorker.runNow(this, settings.wifiOnly)
            append("» commande : LANCER SYNCHRO envoyée")
        }
    }

    override fun onResume() {
        super.onResume()
        if (!observing) {
            contentResolver.registerContentObserver(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, true, galleryObserver)
            contentResolver.registerContentObserver(MediaStore.Video.Media.EXTERNAL_CONTENT_URI, true, galleryObserver)
            observing = true
        }
    }

    override fun onPause() {
        super.onPause()
        if (observing) {
            contentResolver.unregisterContentObserver(galleryObserver)
            observing = false
        }
    }

    /** Transforme un changement d'état de tâche en ligne de log (sans répéter la même). */
    private fun onWork(tag: String, info: WorkInfo) {
        val p = info.progress
        val done = p.getInt(UploadWorker.KEY_DONE, 0)
        val total = p.getInt(UploadWorker.KEY_TOTAL, 0)
        val failed = p.getInt(UploadWorker.KEY_FAILED, 0)

        val line = when (info.state) {
            WorkInfo.State.ENQUEUED ->
                if (info.runAttemptCount > 0) "[$tag] ⟳ nouvelle tentative #${info.runAttemptCount + 1}"
                else "[$tag] … en attente du réseau"
            WorkInfo.State.RUNNING -> {
                val f = if (failed > 0) " | echecs:$failed" else ""
                "[$tag] ▲ envoi $done/$total$f"
            }
            WorkInfo.State.SUCCEEDED -> {
                val up = info.outputData.getInt(UploadWorker.KEY_UPLOADED, 0)
                val fl = info.outputData.getInt(UploadWorker.KEY_FAILED, 0)
                if (up == 0 && fl == 0) "[$tag] ✓ rien de nouveau" else "[$tag] ✓ terminé : $up envoyée(s)" + if (fl > 0) " ($fl echec)" else ""
            }
            WorkInfo.State.FAILED ->
                "[$tag] ✗ ECHEC : ${info.outputData.getString(UploadWorker.KEY_ERROR) ?: "erreur inconnue"}"
            WorkInfo.State.CANCELLED -> "[$tag] ⏹ annulé"
            WorkInfo.State.BLOCKED -> "[$tag] … en file d'attente"
        }
        if (lastLine[tag] != line) {
            lastLine[tag] = line
            append(line)
        }
    }

    /** Ajoute une ligne horodatée et fait défiler vers le bas. */
    private fun append(text: String) {
        val c = Calendar.getInstance()
        val ts = "%02d:%02d:%02d".format(
            c.get(Calendar.HOUR_OF_DAY), c.get(Calendar.MINUTE), c.get(Calendar.SECOND)
        )
        b.log.append("[$ts] $text\n")
        b.scroll.post { b.scroll.fullScroll(View.FOCUS_DOWN) }
    }
}
