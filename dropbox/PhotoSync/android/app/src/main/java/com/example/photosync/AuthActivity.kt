package com.example.photosync

import android.content.Intent
import android.os.Bundle
import android.view.View
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.example.photosync.databinding.ActivityAuthBinding
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/** Connexion ou création de compte. */
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

        // Pré-remplissage : domaine (par défaut luvumbu.com) + sous-dossier éventuel.
        b.domainInput.setText(settings.domain)
        b.subPathInput.setText(settings.subPath)
        val hasSub = settings.subPath.isNotBlank()
        b.advancedToggle.isChecked = hasSub
        setAdvancedVisible(hasSub)

        b.advancedToggle.setOnCheckedChangeListener { _, checked -> setAdvancedVisible(checked) }
        b.loginButton.setOnClickListener { submit(register = false) }
        b.registerButton.setOnClickListener { submit(register = true) }
    }

    private fun setAdvancedVisible(visible: Boolean) {
        val v = if (visible) View.VISIBLE else View.GONE
        b.subPathLayout.visibility = v
        b.subPathHelp.visibility = v
    }

    private fun submit(register: Boolean) {
        val domain = b.domainInput.text.toString().trim()
        val sub = if (b.advancedToggle.isChecked) b.subPathInput.text.toString().trim() else ""
        val user = b.usernameInput.text.toString().trim()
        val pass = b.passwordInput.text.toString()
        val invite = b.inviteInput.text.toString().trim()

        if (domain.isBlank() || user.isBlank() || pass.isBlank()) {
            b.authStatus.text = "Remplis le domaine, l'identifiant et le mot de passe."
            return
        }
        if (register && invite.isBlank()) {
            b.authStatus.text = "Indique le code d'inscription (mot de passe du serveur)."
            return
        }

        // Configuration automatique de l'URL à partir du domaine (+ sous-dossier éventuel).
        settings.domain = domain
        settings.subPath = sub
        settings.serverUrl = SettingsStore.buildServerUrl(domain, sub)
        val api = ApiClient(this, settings)

        setLoading(true)
        lifecycleScope.launch {
            val result = withContext(Dispatchers.IO) {
                if (register) api.register(user, pass, invite) else api.login(user, pass)
            }
            setLoading(false)
            if (result.ok && !result.token.isNullOrBlank()) {
                settings.token = result.token
                settings.username = result.username ?: user
                goToMain()
            } else {
                b.authStatus.text = result.error ?: "Échec de la connexion"
            }
        }
    }

    private fun setLoading(loading: Boolean) {
        b.authProgress.visibility = if (loading) View.VISIBLE else View.GONE
        b.loginButton.isEnabled = !loading
        b.registerButton.isEnabled = !loading
        if (loading) b.authStatus.text = ""
    }

    private fun goToMain() {
        startActivity(Intent(this, MainActivity::class.java))
        finish()
    }
}
