package com.example.photosync

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.Data
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import androidx.work.workDataOf
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.async
import kotlinx.coroutines.awaitAll
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.sync.Semaphore
import kotlinx.coroutines.sync.withPermit
import kotlinx.coroutines.withContext
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicBoolean
import java.util.concurrent.atomic.AtomicInteger
import java.util.concurrent.atomic.AtomicReference

/**
 * Scanne la galerie et envoie au serveur les photos pas encore uploadées.
 * Publie sa progression en direct (KEY_DONE / KEY_TOTAL) pour l'affichage temps réel.
 */
class UploadWorker(appContext: Context, params: WorkerParameters) :
    CoroutineWorker(appContext, params) {

    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        val settings = SettingsStore(applicationContext)
        if (!settings.isLoggedIn) {
            return@withContext Result.failure(workDataOf(KEY_ERROR to "Compte non connecté"))
        }

        val dao = SyncApp.instance.db.uploadedDao()
        val api = ApiClient(applicationContext, settings)

        // Seul contrôle conservé : la permission de lecture des photos (sinon rien à lire).
        // Plus AUCUNE vérification réseau (ni config, ni photos) → on va droit à l'envoi.
        if (!hasPhotoPermission()) {
            return@withContext Result.failure(
                workDataOf(KEY_ERROR to "Accès aux photos non accordé — ouvre l'app et autorise l'accès")
            )
        }

        // On se fie à la mémoire locale (photos déjà envoyées).
        // 1ère synchro -> tout part ; ensuite -> seulement les nouvelles. Gain de temps maxi.
        val alreadyUploaded = dao.allIds().toHashSet()
        val pending = MediaScanner.queryImages(applicationContext, settings.includeVideos)
            .filter { it.id !in alreadyUploaded }

        val skipped = 0
        if (pending.isEmpty()) {
            return@withContext Result.success(
                workDataOf(KEY_UPLOADED to 0, KEY_SKIPPED to 0, KEY_FAILED to 0, KEY_TOTAL to 0)
            )
        }

        // Limite d'envoi par synchro (0 = illimité).
        val maxPerSync = settings.maxPerSync
        val work = if (maxPerSync in 1 until pending.size)
            ArrayList(pending.subList(0, maxPerSync)) else pending
        val total = work.size

        // Envoi EN PARALLÈLE (plusieurs photos à la fois) pour accélérer, avec une limite
        // raisonnable. Chaque envoi réussi est noté tout de suite → reprise possible.
        val uploadedC = AtomicInteger(0)
        val failedC = AtomicInteger(0)
        val aborted = AtomicBoolean(false)
        val lastErrorRef = AtomicReference<String?>(null)
        val sem = Semaphore(PARALLEL)

        coroutineScope {
            work.map { photo ->
                async(Dispatchers.IO) {
                    sem.withPermit {
                        if (isStopped || aborted.get()) return@withPermit
                        val r = api.upload(photo)
                        if (r.ok) {
                            dao.markUploaded(UploadedPhoto(photo.id, System.currentTimeMillis()))
                            val done = uploadedC.incrementAndGet()
                            setProgress(workDataOf(
                                KEY_PHASE to PHASE_UPLOAD, KEY_DONE to done, KEY_TOTAL to total,
                                KEY_SKIPPED to skipped, KEY_FAILED to failedC.get(),
                            ))
                        } else {
                            lastErrorRef.set(r.error)
                            val f = failedC.incrementAndGet()
                            // Échec massif dès le départ (serveur/compte KO) : on arrête tout.
                            if (uploadedC.get() == 0 && f >= 3) aborted.set(true)
                        }
                    }
                }
            }.awaitAll()
        }

        val uploaded = uploadedC.get()
        val failed = failedC.get()
        val lastError = lastErrorRef.get()

        if (aborted.get() && uploaded == 0) {
            return@withContext Result.failure(
                workDataOf(KEY_UPLOADED to 0, KEY_SKIPPED to skipped, KEY_FAILED to failed, KEY_ERROR to lastError)
            )
        }

        val out = workDataOf(
            KEY_UPLOADED to uploaded,
            KEY_SKIPPED to skipped,
            KEY_FAILED to failed,
            KEY_TOTAL to total,
            KEY_ERROR to lastError,
        )
        // Notification de bilan (uniquement s'il s'est passé quelque chose).
        if (uploaded > 0 || failed > 0) notifyDone(uploaded, failed)
        if (failed > 0 && uploaded == 0) Result.retry() else Result.success(out)
    }

    /** Notification de fin de synchro : « ✅ X sauvegardée(s) ». */
    private fun notifyDone(uploaded: Int, failed: Int) {
        val mgr = NotificationManagerCompat.from(applicationContext)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            mgr.createNotificationChannel(
                NotificationChannel(CHANNEL_SYNC, "Synchronisation", NotificationManager.IMPORTANCE_LOW)
            )
        }
        val text = when {
            failed > 0 && uploaded > 0 -> "✅ $uploaded sauvegardée(s) · ⚠️ $failed échec(s)"
            failed > 0                 -> "⚠️ $failed envoi(s) en échec"
            else                       -> "✅ $uploaded photo(s)/vidéo(s) sauvegardée(s)"
        }
        val notif = NotificationCompat.Builder(applicationContext, CHANNEL_SYNC)
            .setSmallIcon(android.R.drawable.stat_sys_upload_done)
            .setContentTitle("PhotoSync")
            .setContentText(text)
            .setAutoCancel(true)
            .build()
        try {
            mgr.notify(NOTIF_SYNC_ID, notif)
        } catch (e: SecurityException) {
            // Permission notifications non accordée : sans gravité.
        }
    }

    /** La permission de lecture des photos est-elle accordée ? */
    private fun hasPhotoPermission(): Boolean {
        val perm = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU)
            Manifest.permission.READ_MEDIA_IMAGES else Manifest.permission.READ_EXTERNAL_STORAGE
        return ContextCompat.checkSelfPermission(applicationContext, perm) == PackageManager.PERMISSION_GRANTED
    }

    companion object {
        const val PERIODIC = "photosync_periodic"
        const val ONESHOT = "photosync_now"
        private const val CHANNEL_SYNC = "photosync_sync"
        private const val NOTIF_SYNC_ID = 2001
        private const val PARALLEL = 6 // envois simultanés (vitesse vs charge réseau)

        const val KEY_DONE = "done"
        const val KEY_TOTAL = "total"
        const val KEY_UPLOADED = "uploaded"
        const val KEY_SKIPPED = "skipped"
        const val KEY_FAILED = "failed"
        const val KEY_ERROR = "error"
        const val KEY_PHASE = "phase"
        const val PHASE_SETUP = "setup"
        const val PHASE_VERIFY = "verify"
        const val PHASE_UPLOAD = "upload"

        private fun constraints(wifiOnly: Boolean) = Constraints.Builder()
            .setRequiredNetworkType(if (wifiOnly) NetworkType.UNMETERED else NetworkType.CONNECTED)
            .build()

        /** Active la synchro automatique en arrière-plan (toutes les ~15 min). */
        fun schedulePeriodic(context: Context, wifiOnly: Boolean) {
            val req = PeriodicWorkRequestBuilder<UploadWorker>(15, TimeUnit.MINUTES)
                .setConstraints(constraints(wifiOnly))
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 30, TimeUnit.SECONDS)
                .build()
            WorkManager.getInstance(context)
                .enqueueUniquePeriodicWork(PERIODIC, ExistingPeriodicWorkPolicy.UPDATE, req)
        }

        /** Lance une synchro immédiate (sans attendre le cycle périodique). */
        fun runNow(context: Context, wifiOnly: Boolean) {
            val req = OneTimeWorkRequestBuilder<UploadWorker>()
                .setConstraints(constraints(wifiOnly))
                .build()
            WorkManager.getInstance(context)
                .enqueueUniqueWork(ONESHOT, ExistingWorkPolicy.REPLACE, req)
        }

        /** Coupe la synchro auto en arrière-plan (laisse l'envoi en cours se terminer). */
        fun cancelPeriodic(context: Context) {
            WorkManager.getInstance(context).cancelUniqueWork(PERIODIC)
        }

        /** Arrête TOUT : l'envoi en cours ET la synchro auto en arrière-plan. */
        fun cancelAll(context: Context) {
            val wm = WorkManager.getInstance(context)
            wm.cancelUniqueWork(ONESHOT)
            wm.cancelUniqueWork(PERIODIC)
        }
    }
}
