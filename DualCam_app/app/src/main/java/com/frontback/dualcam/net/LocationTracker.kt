package com.frontback.dualcam.net

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import android.os.Looper
import androidx.core.content.ContextCompat
import com.google.android.gms.location.LocationCallback
import com.google.android.gms.location.LocationRequest
import com.google.android.gms.location.LocationResult
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority

/**
 * Suivi léger de la position pour géolocaliser les vidéos/photos DualCam.
 *
 * Pensé pour un enregistrement : [start] au début, [stop] à la fin. Entre les deux,
 * la dernière position connue est conservée et lue par les envois via [latLng].
 *
 * Tout est facultatif : sans permission de localisation, [start] ne fait rien et
 * [latLng] reste null — la vidéo est simplement envoyée sans coordonnées.
 */
class LocationTracker(context: Context) {

    private val appContext = context.applicationContext
    private val client = LocationServices.getFusedLocationProviderClient(appContext)

    @Volatile private var lastLocation: Location? = null

    private val callback = object : LocationCallback() {
        override fun onLocationResult(result: LocationResult) {
            result.lastLocation?.let { lastLocation = it }
        }
    }

    /** Dernière position connue en (latitude, longitude), ou null si indisponible. */
    val latLng: Pair<Double, Double>?
        get() = lastLocation?.let { it.latitude to it.longitude }

    /** La permission de localisation (fine OU approximative) est-elle accordée ? */
    fun hasPermission(): Boolean =
        ContextCompat.checkSelfPermission(appContext, Manifest.permission.ACCESS_FINE_LOCATION) ==
            PackageManager.PERMISSION_GRANTED ||
        ContextCompat.checkSelfPermission(appContext, Manifest.permission.ACCESS_COARSE_LOCATION) ==
            PackageManager.PERMISSION_GRANTED

    /** Démarre le suivi si la permission est accordée ; sinon ne fait rien. */
    fun start() {
        if (!hasPermission()) return
        // Amorce immédiate : réutilise la dernière position connue du système.
        try {
            client.lastLocation.addOnSuccessListener { loc ->
                if (loc != null && lastLocation == null) lastLocation = loc
            }
        } catch (_: SecurityException) {}
        // Rafraîchissements périodiques (basse conso), suffisants pour situer une prise.
        val request = LocationRequest.Builder(Priority.PRIORITY_BALANCED_POWER_ACCURACY, 15_000L)
            .setMinUpdateIntervalMillis(5_000L)
            .build()
        try {
            client.requestLocationUpdates(request, callback, Looper.getMainLooper())
        } catch (_: SecurityException) {}
    }

    /** Arrête le suivi. Sans effet si aucun suivi n'était en cours. */
    fun stop() {
        try { client.removeLocationUpdates(callback) } catch (_: Throwable) {}
    }
}
