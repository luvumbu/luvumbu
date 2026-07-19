package com.tamagotchi.edu

import android.annotation.SuppressLint
import android.graphics.Bitmap
import android.os.Bundle
import android.os.Message
import android.speech.tts.TextToSpeech
import android.speech.tts.UtteranceProgressListener
import android.view.KeyEvent
import android.widget.Button
import android.webkit.JavascriptInterface
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.appcompat.app.AppCompatActivity
import androidx.credentials.CredentialManager
import androidx.credentials.CustomCredential
import androidx.credentials.GetCredentialRequest
import androidx.lifecycle.lifecycleScope
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout
import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import kotlinx.coroutines.launch
import java.util.Locale

/**
 * L'application Android n'est qu'une "coquille" (WebView) qui affiche
 * l'application web EN LIGNE. Résultat :
 *  - le rendu est EXACTEMENT le même que sur le web ;
 *  - dès que le site est mis à jour en ligne, l'app affiche la nouvelle version
 *    (rien à re-publier côté Android).
 *
 * La WebView ne sait PAS lire à voix haute toute seule (speechSynthesis muet),
 * donc on branche le moteur de synthèse vocale NATIF d'Android via un pont JS
 * exposé sous le nom `window.AndroidTTS` (voir speech.js / cours.js côté web).
 */
class MainActivity : AppCompatActivity(), TextToSpeech.OnInitListener {

    private lateinit var webView: WebView
    private lateinit var refresh: SwipeRefreshLayout
    private var lastLoadFailed = false

    private lateinit var tts: TextToSpeech
    private var ttsReady = false

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        refresh = findViewById(R.id.swipeRefresh)
        webView = findViewById(R.id.webView)

        // Bouton flottant « Actualiser » : recharge la dernière version en ligne.
        findViewById<Button>(R.id.refreshButton).setOnClickListener {
            webView.reload()
        }

        // Moteur de voix natif (français).
        tts = TextToSpeech(this, this)

        webView.settings.apply {
            javaScriptEnabled = true                 // le jeu utilise du JavaScript
            domStorageEnabled = true                 // localStorage (progression, etc.)
            mediaPlaybackRequiresUserGesture = false // laisse parler la synthèse vocale
            cacheMode = WebSettings.LOAD_NO_CACHE     // TOUJOURS la dernière version du serveur (màj directe)
            useWideViewPort = true
            loadWithOverviewMode = true              // adapte la page à la largeur de l'écran (jamais de débordement)
            builtInZoomControls = false
            allowFileAccess = true
            allowContentAccess = true
            setSupportMultipleWindows(true)               // pour window.open (bouton "Voir le cours")
            javaScriptCanOpenWindowsAutomatically = true
            userAgentString = "$userAgentString TamagotchiEduApp"
        }

        // Pont voix : le web appelle window.AndroidTTS.speak(...) / .stop().
        webView.addJavascriptInterface(TtsBridge(), "AndroidTTS")

        // Pont connexion Google native : le web appelle window.AndroidAuth.signIn().
        webView.addJavascriptInterface(GoogleAuthBridge(), "AndroidAuth")

        // « Voir le cours » utilise window.open('_blank') : on ouvre dans la MÊME WebView.
        webView.webChromeClient = object : WebChromeClient() {
            override fun onCreateWindow(
                view: WebView?, isDialog: Boolean, isUserGesture: Boolean, resultMsg: Message?
            ): Boolean {
                val temp = WebView(this@MainActivity)
                temp.webViewClient = object : WebViewClient() {
                    override fun shouldOverrideUrlLoading(v: WebView?, req: WebResourceRequest?): Boolean {
                        req?.url?.let { webView.loadUrl(it.toString()) }   // charge dans la vue principale
                        return true
                    }
                }
                (resultMsg?.obj as? WebView.WebViewTransport)?.webView = temp
                resultMsg?.sendToTarget()
                return true
            }
        }

        webView.webViewClient = object : WebViewClient() {
            override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                lastLoadFailed = false
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                refresh.isRefreshing = false
                // Si la page en ligne n'a pas pu être chargée, on affiche l'écran hors-ligne.
                if (lastLoadFailed) {
                    view?.loadUrl("file:///android_asset/offline.html")
                }
            }

            override fun onReceivedError(
                view: WebView?, request: WebResourceRequest?, error: WebResourceError?
            ) {
                // On ne bascule hors-ligne que si c'est la page principale qui échoue.
                if (request?.isForMainFrame == true) {
                    lastLoadFailed = true
                }
            }

            // On garde la navigation À L'INTÉRIEUR de la WebView (nouveaux onglets = même écran).
            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                request?.url?.let { view?.loadUrl(it.toString()) }
                return true
            }
        }

        // ❌ "Tirer pour rafraîchir" DÉSACTIVÉ.
        // Il volait le geste de scroll : dans les menus (Apprendre, Boutique, cours),
        // le contenu défile dans des cadres CSS que la couche native ne "voit" pas.
        // Résultat : en glissant vers le bas pour remonter, on restait bloqué.
        // L'app récupère de toute façon la dernière version en ligne à chaque ouverture.
        refresh.isEnabled = false
        refresh.setOnRefreshListener { webView.reload() }   // gardé au cas où, mais inactif

        if (savedInstanceState == null) {
            webView.loadUrl(BuildConfig.APP_URL)
        } else {
            webView.restoreState(savedInstanceState)
        }
    }

    /** Appelé quand le moteur de voix est prêt. */
    override fun onInit(status: Int) {
        if (status == TextToSpeech.SUCCESS) {
            val res = tts.setLanguage(Locale.FRENCH)
            ttsReady = res != TextToSpeech.LANG_MISSING_DATA && res != TextToSpeech.LANG_NOT_SUPPORTED

            // Quand un morceau COMMENCE à être prononcé, on prévient le web
            // pour synchroniser la voix avec les animations (window.__ttsOnStart).
            tts.setOnUtteranceProgressListener(object : UtteranceProgressListener() {
                override fun onStart(utteranceId: String?) {
                    val id = utteranceId ?: return
                    runOnUiThread {
                        webView.evaluateJavascript(
                            "window.__ttsOnStart && window.__ttsOnStart('$id')", null
                        )
                    }
                }
                override fun onDone(utteranceId: String?) {}
                @Deprecated("deprecated in API level 21")
                override fun onError(utteranceId: String?) {}
            })
        }
    }

    /** Pont exposé au JavaScript sous le nom `AndroidTTS`. */
    inner class TtsBridge {
        @JavascriptInterface
        fun speak(text: String, rate: Double, pitch: Double, flush: Boolean, id: String) {
            if (!ttsReady) return
            tts.setSpeechRate(rate.toFloat())
            tts.setPitch(pitch.toFloat())
            val mode = if (flush) TextToSpeech.QUEUE_FLUSH else TextToSpeech.QUEUE_ADD
            tts.speak(text, mode, null, id)
        }

        @JavascriptInterface
        fun stop() {
            tts.stop()
        }

        @JavascriptInterface
        fun isReady(): Boolean = ttsReady
    }

    /**
     * Pont « Sign in with Google » NATIF (exposé au JS sous `AndroidAuth`).
     * Google refuse sa connexion dans une WebView, donc on la fait côté Android
     * (Credential Manager), puis on renvoie le jeton au jeu via window.__onGoogleToken().
     */
    inner class GoogleAuthBridge {
        @JavascriptInterface
        fun signIn() {
            runOnUiThread { startGoogleSignIn() }
        }
    }

    private fun startGoogleSignIn() {
        // Flux « bouton » : affiche toujours le sélecteur de compte Google
        // (plus fiable que GetGoogleIdOption qui échoue s'il n'y a pas de compte déjà autorisé).
        val option = GetSignInWithGoogleOption.Builder(BuildConfig.WEB_CLIENT_ID).build()
        val request = GetCredentialRequest.Builder().addCredentialOption(option).build()
        val manager = CredentialManager.create(this)

        lifecycleScope.launch {
            try {
                val result = manager.getCredential(this@MainActivity, request)
                val cred = result.credential
                if (cred is CustomCredential &&
                    cred.type == GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL
                ) {
                    val idToken = GoogleIdTokenCredential.createFrom(cred.data).idToken
                    sendToWeb("window.__onGoogleToken && window.__onGoogleToken('$idToken')")
                } else {
                    sendToWeb("window.__onGoogleError && window.__onGoogleError('type inattendu')")
                }
            } catch (e: Exception) {
                val msg = (e.message ?: "erreur").replace("'", " ")
                sendToWeb("window.__onGoogleError && window.__onGoogleError('$msg')")
            }
        }
    }

    private fun sendToWeb(js: String) {
        runOnUiThread { webView.evaluateJavascript(js, null) }
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        webView.saveState(outState)
    }

    override fun onDestroy() {
        try {
            tts.stop()
            tts.shutdown()
        } catch (e: Exception) { /* ignore */ }
        super.onDestroy()
    }

    // Le bouton "retour" navigue dans la WebView (revient à l'écran précédent du jeu).
    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        if (keyCode == KeyEvent.KEYCODE_BACK && webView.canGoBack()) {
            webView.goBack()
            return true
        }
        return super.onKeyDown(keyCode, event)
    }
}
