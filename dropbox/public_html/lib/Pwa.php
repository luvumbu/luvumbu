<?php
/**
 * === PhotoSync en « application installable » (PWA) ===
 *
 * Permet d'ajouter PhotoSync à l'écran d'accueil d'un iPhone, d'un Android ou
 * d'un ordinateur — sans App Store ni Play Store. L'app lancée depuis l'icône
 * s'ouvre en plein écran, sans barre d'adresse.
 *
 * Usage : remplacer la ligne `<link rel="icon" …>` d'une page par
 *     <?= Pwa::head('..') ?>          (pages de web/)
 *     <?= Pwa::head('.') ?>           (pages de la racine : install.php, diag.php)
 * $base = chemin relatif vers la racine de l'app, jamais d'URL en dur : le même
 * code marche en local (/luvumbu/dropbox/) et en ligne (luvumbu.com/dropbox/).
 *
 * $installable = false sur les pages destinées à des visiteurs extérieurs
 * (album partagé) : ils gardent l'icône et les couleurs, mais on ne leur propose
 * pas d'installer une application dans laquelle ils n'ont pas de compte.
 */
final class Pwa
{
    public const NAME  = 'PhotoSync';
    public const THEME = '#0b1220';

    /** Balises <head> : icônes, manifeste, réglages iOS, service worker. */
    public static function head(string $base = '.', bool $installable = true): string
    {
        $b = rtrim($base, '/');
        $v = '?v=1';   // cassage de cache des icônes ; incrémenter si on les redessine

        $html = <<<HTML
<link rel="icon" href="{$b}/favicon.svg" type="image/svg+xml">
<link rel="icon" type="image/png" sizes="192x192" href="{$b}/assets/icon-192.png{$v}">
<link rel="apple-touch-icon" href="{$b}/assets/apple-touch-icon.png{$v}">
<meta name="theme-color" content="#0b1220">
<meta name="application-name" content="PhotoSync">
HTML;
        if (!$installable) return $html;

        return $html . <<<HTML

<link rel="manifest" href="{$b}/manifest.php">
<!-- iPhone / iPad : l'icône et le mode plein écran passent par ces balises,
     Safari n'utilisant pas encore le manifeste pour « Sur l'écran d'accueil ». -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="apple-mobile-web-app-title" content="PhotoSync">
<script>
(function () {
  // Enregistrement du service worker (obligatoire pour l'installation sur
  // Android/ordinateur ; sur iPhone il sert surtout à la page hors connexion).
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('{$b}/sw.js', { scope: '{$b}/' }).catch(function () {});
    });
  }
  // iOS lancé depuis l'écran d'accueil : un lien target="_blank" ferait sortir
  // vers Safari et perdrait la session. On garde la navigation dans l'app.
  if (navigator.standalone) {
    document.addEventListener('click', function (e) {
      var a = e.target && e.target.closest ? e.target.closest('a[target="_blank"]') : null;
      if (!a || a.hasAttribute('download')) return;
      if (a.origin !== location.origin) return;
      e.preventDefault();
      location.href = a.href;
    });
  }
})();
</script>
HTML;
    }

    /** L'app tourne-t-elle déjà depuis l'écran d'accueil ? (test côté client) */
    public static function isStandaloneJs(): string
    {
        return "(window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true)";
    }

    /**
     * Bandeau d'installation, à poser juste avant </body>.
     *
     * Raison d'être : sur iPhone, **aucun site ne peut afficher un bouton qui
     * installe** — Safari n'implémente pas `beforeinstallprompt`, l'installation
     * passe obligatoirement par son menu Partager. Un simple lien dans la page
     * ne se trouve pas. On affiche donc un bandeau en bas de l'écran, avec une
     * flèche qui désigne le bouton Partager de la barre Safari juste en dessous.
     *
     * Il ne s'affiche que là où il sert :
     *   - iPhone/iPad uniquement, et seulement dans Safari (Chrome/Firefox iOS
     *     ne savent pas installer : on leur affiche « ouvre ceci dans Safari ») ;
     *   - jamais si l'app tourne déjà depuis l'écran d'accueil ;
     *   - plus jamais après un « J'ai compris » (mémorisé dans localStorage).
     * Sur Android/ordinateur il ne s'affiche pas : le navigateur propose lui-même
     * l'installation, et `web/appli.php` a un vrai bouton.
     */
    public static function iosBanner(): string
    {
        return <<<HTML
<div id="a2hs" class="a2hs" hidden>
  <button class="a2hs-x" type="button" aria-label="Fermer">×</button>
  <div class="a2hs-row">
    <img class="a2hs-ico" src="" alt="" id="a2hsIco">
    <div>
      <div class="a2hs-t">Mettre PhotoSync sur l'écran d'accueil</div>
      <div class="a2hs-s" id="a2hsSteps">
        Touche <b>Partager</b> <span class="a2hs-share">⬆</span> dans la barre du bas,
        fais défiler, puis <b>« Sur l'écran d'accueil »</b>.
      </div>
    </div>
  </div>
  <div class="a2hs-arrow" id="a2hsArrow">▼</div>
</div>
<style>
  .a2hs { position:fixed; left:10px; right:10px; bottom:10px; z-index:9999;
          background:rgba(17,28,51,.97); -webkit-backdrop-filter:blur(10px); backdrop-filter:blur(10px);
          border:1px solid rgba(148,163,184,.3); border-radius:18px; padding:16px 42px 10px 16px;
          box-shadow:0 18px 50px rgba(0,0,0,.65); color:#e6edf7;
          font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
          animation:a2hsUp .35s ease-out; }
  @keyframes a2hsUp { from { transform:translateY(120%); opacity:0 } to { transform:none; opacity:1 } }
  .a2hs-row { display:flex; gap:13px; align-items:center; }
  .a2hs-ico { width:48px; height:48px; border-radius:12px; flex:0 0 auto; }
  .a2hs-t { font-size:15px; font-weight:800; margin-bottom:3px; }
  .a2hs-s { font-size:13px; line-height:1.5; color:#b6c6e0; }
  .a2hs-s b { color:#e6edf7; }
  .a2hs-share { display:inline-block; border:1px solid #4f8cff; border-radius:5px;
                padding:0 5px; color:#8ab4ff; font-weight:700; }
  .a2hs-x { position:absolute; top:8px; right:10px; width:30px; height:30px; line-height:1;
            background:none; border:0; color:#8da2c0; font-size:24px; cursor:pointer; }
  .a2hs-arrow { text-align:center; color:#4f8cff; font-size:19px; margin-top:2px;
                animation:a2hsBob 1.4s ease-in-out infinite; }
  @keyframes a2hsBob { 0%,100% { transform:translateY(0) } 50% { transform:translateY(5px) } }
</style>
<script>
(function () {
  var KEY = 'photosync_a2hs_vu';
  var el  = document.getElementById('a2hs');
  if (!el) return;

  var ua = navigator.userAgent;
  // iPad récent : se déclare comme un Mac, on le reconnaît à l'écran tactile.
  var iOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  // Sur iOS tous les navigateurs utilisent le moteur de Safari : on les distingue
  // à leur suffixe (CriOS = Chrome, FxiOS = Firefox, EdgiOS = Edge, OPiOS = Opera).
  var vraiSafari = !/CriOS|FxiOS|EdgiOS|OPiOS|GSA/.test(ua);

  if (!iOS || standalone) return;
  try { if (localStorage.getItem(KEY)) return; } catch (e) {}

  // L'icône est déjà déclarée dans le <head> : on réutilise son URL, sans
  // supposer la profondeur du dossier courant.
  var lien = document.querySelector('link[rel="apple-touch-icon"]');
  if (lien) document.getElementById('a2hsIco').src = lien.getAttribute('href');

  if (!vraiSafari) {
    // Hors Safari l'entrée « Sur l'écran d'accueil » n'existe pas : inutile
    // d'expliquer une manœuvre impossible, on redirige vers le bon navigateur.
    document.getElementById('a2hsSteps').innerHTML =
      'Ouvre cette page dans <b>Safari</b> : les autres navigateurs iPhone ne savent pas installer une application.';
    document.getElementById('a2hsArrow').hidden = true;
  }
  el.hidden = false;

  el.querySelector('.a2hs-x').addEventListener('click', function () {
    el.hidden = true;
    try { localStorage.setItem(KEY, '1'); } catch (e) {}
  });
})();
</script>
HTML;
    }
}
