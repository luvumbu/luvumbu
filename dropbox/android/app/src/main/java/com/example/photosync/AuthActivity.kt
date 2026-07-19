package com.example.photosync

import android.content.Intent
import android.os.Bundle
import android.view.View
import androidx.appcompat.app.AppCompatActivity
import androidx.credentials.CredentialManager
import androidx.credentials.CustomCredential
import androidx.credentials.GetCredentialRequest
import androidx.credentials.exceptions.GetCredentialException
import androidx.lifecycle.lifecycleScope
import com.example.photosync.databinding.ActivityAuthBinding
import com.google.android.libraries.identity.googleid.GetGoogleIdOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/** Connexion via « Se connecter avec Google ». Le compte est créé automatiquement. */
class AuthActivity : AppCompatActivity() {

    private lateinit var b: ActivityAuthBinding
    private lateinit var settings: SettingsStore

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        b = ActivityAuthBinding.inflate(layoutInflater)
        setContentView(b.root)
        settings = SettingsStore(this)

        // Déjà connecté → on va directement à l'écran principal.
        if (settings.isLoggedIn) {
            goToMain()
            return
        }

        b.googleButton.setOnClickListener { signInWithGoogle() }
    }

    private fun signInWithGoogle() {
        val clientId = getString(R.string.google_web_client_id)
        if (clientId.isBlank()) {
            b.authStatus.text = "Connexion Google non configurée (google_web_client_id manquant dans l'app)."
            return
        }

        // Serveur fixé d'office : aucun domaine/chemin à saisir.
        settings.domain = SettingsStore.DEFAULT_DOMAIN
        settings.subPath = SettingsStore.DEFAULT_SUBPATH
        settings.serverUrl = SettingsStore.DEFAULT_URL

        val googleIdOption = GetGoogleIdOption.Builder()
            // false = propose tous les comptes Google, pas seulement ceux déjà autorisés.
            .setFilterByAuthorizedAccounts(false)
            .setServerClientId(clientId)
            .build()
        val request = GetCredentialRequest.Builder()
            .addCredentialOption(googleIdOption)
            .build()
        val credentialManager = CredentialManager.create(this)

        setLoading(true)
        lifecycleScope.launch {
            try {
                val result = credentialManager.getCredential(this@AuthActivity, request)
                val cred = result.credential
                if (cred is CustomCredential &&
                    cred.type == GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL
                ) {
                    val googleCred = GoogleIdTokenCredential.createFrom(cred.data)
                    val idToken = googleCred.idToken

                    val api = ApiClient(this@AuthActivity, settings)
                    val res = withContext(Dispatchers.IO) { api.loginWithGoogle(idToken) }
                    setLoading(false)
                    if (res.ok && !res.token.isNullOrBlank()) {
                        settings.token = res.token
                        settings.username = res.username ?: ""
                        goToMain()
                    } else {
                        b.authStatus.text = res.error ?: "Échec de la connexion"
                    }
                } else {
                    setLoading(false)
                    b.authStatus.text = "Identifiant Google non reconnu."
                }
            } catch (e: GetCredentialException) {
                setLoading(false)
                b.authStatus.text = "Connexion Google annulée ou impossible : ${e.message ?: ""}"
            }
        }
    }

    private fun setLoading(loading: Boolean) {
        b.authProgress.visibility = if (loading) View.VISIBLE else View.GONE
        b.googleButton.isEnabled = !loading
        if (loading) b.authStatus.text = ""
    }

    private fun goToMain() {
        startActivity(Intent(this, MainActivity::class.java))
        finish()
    }
}
