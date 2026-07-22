package com.frontback.dualcam.net

import android.content.Context
import androidx.credentials.CredentialManager
import androidx.credentials.CustomCredential
import androidx.credentials.GetCredentialRequest
import com.frontback.dualcam.R
import com.google.android.libraries.identity.googleid.GetGoogleIdOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

/**
 * Connexion « Se connecter avec Google » — MÊME compte que l'app PhotoSync.
 * Récupère un jeton d'identité Google puis le transmet au serveur pour obtenir
 * le jeton interne, qu'on stocke dans SettingsStore.
 */
object GoogleAuth {

    /**
     * Garantit qu'un compte est connecté. Si un jeton existe déjà, ne fait rien.
     * Sinon, lance la connexion Google (fenêtre système) puis l'authentifie côté serveur.
     * Renvoie AuthResult(ok=true) si connecté, sinon un message d'erreur.
     * À appeler depuis une coroutine liée à une Activity (contexte UI valide).
     */
    suspend fun ensureLoggedIn(context: Context, settings: SettingsStore): AuthResult {
        if (settings.isLoggedIn) {
            return AuthResult(true, settings.token, settings.username, null)
        }

        val clientId = context.getString(R.string.google_web_client_id)
        if (clientId.isBlank()) {
            return AuthResult(false, null, null, "Connexion Google non configurée (client_id manquant).")
        }

        // 1) Obtenir le jeton d'identité Google via Credential Manager.
        val idToken = try {
            val googleIdOption = GetGoogleIdOption.Builder()
                .setFilterByAuthorizedAccounts(false)
                .setServerClientId(clientId)
                .build()
            val request = GetCredentialRequest.Builder()
                .addCredentialOption(googleIdOption)
                .build()
            val credentialManager = CredentialManager.create(context)
            val result = credentialManager.getCredential(context, request)
            val cred = result.credential
            if (cred is CustomCredential &&
                cred.type == GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL
            ) {
                GoogleIdTokenCredential.createFrom(cred.data).idToken
            } else {
                return AuthResult(false, null, null, "Identifiant Google non reconnu.")
            }
        } catch (e: Exception) {
            return AuthResult(false, null, null, "Connexion Google annulée ou impossible : ${e.message ?: ""}")
        }

        // 2) Échanger ce jeton contre le jeton interne du serveur.
        val res = withContext(Dispatchers.IO) {
            ApiClient(settings).loginWithGoogle(idToken)
        }
        if (res.ok && !res.token.isNullOrBlank()) {
            settings.token = res.token
            settings.username = res.username ?: ""
        }
        return res
    }
}
