package com.frontback.dualcam

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.frontback.dualcam.net.GoogleAuth
import com.frontback.dualcam.net.SettingsStore
import kotlinx.coroutines.launch

/**
 * Écran de connexion OBLIGATOIRE (lancé en premier).
 * Tant que l'utilisateur n'est pas connecté avec Google, il ne peut pas accéder
 * à la caméra. Une fois connecté, on passe à [MainActivity].
 */
class AuthActivity : AppCompatActivity() {

    private lateinit var settings: SettingsStore
    private lateinit var signInButton: Button
    private lateinit var status: TextView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        settings = SettingsStore(this)

        // Déjà connecté → on va directement à la caméra.
        if (settings.isLoggedIn) {
            goToMain()
            return
        }

        setContentView(R.layout.activity_auth)
        signInButton = findViewById(R.id.authSignInButton)
        status = findViewById(R.id.authStatus)
        signInButton.setOnClickListener { signIn() }
    }

    private fun signIn() {
        signInButton.isEnabled = false
        status.text = ""
        lifecycleScope.launch {
            val auth = GoogleAuth.ensureLoggedIn(this@AuthActivity, settings)
            if (auth.ok) {
                goToMain()
            } else {
                status.text = auth.error ?: "Connexion échouée"
                signInButton.isEnabled = true
            }
        }
    }

    private fun goToMain() {
        startActivity(Intent(this, MainActivity::class.java))
        finish()
    }
}
