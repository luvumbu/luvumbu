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
}
