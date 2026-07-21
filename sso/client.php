<?php
/* ═══════════════════════════════════════════════════════════════════════
   LUVUMBU ID — CLIENT à inclure dans n'importe quelle application.
   Usage minimal dans une page d'app :
       require '/chemin/vers/sso/client.php';
       $user = luvumbu_require_login();     // redirige vers le hub si non connecté
       echo "Bonjour " . htmlspecialchars($user['name']);
   Ou, sans forcer la connexion :
       $user = luvumbu_user();              // tableau ou null
   ═══════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/lib.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* URL du hub SSO (surchargée par une constante LUVUMBU_HUB si définie avant l'include). */
function luvumbu_hub(): string {
    return defined('LUVUMBU_HUB') ? LUVUMBU_HUB : 'https://luvumbu.com/sso/';
}

/* URL courante complète (pour le retour après login). */
function luvumbu_here(): string {
    $scheme = sso_https() ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
}

/* Consomme un ?sso=<jwt> de retour du hub : le vérifie, ouvre la session locale,
   puis nettoie l'URL (retire le paramètre sso). */
function luvumbu_consume(): void {
    if (!isset($_GET['sso'])) return;
    $p = sso_jwt_verify((string)$_GET['sso']);
    if ($p) $_SESSION['luvid'] = $p;
    // reconstruit l'URL sans le paramètre sso
    $q = $_GET; unset($q['sso']);
    $path = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');
    $clean = $path . ($q ? '?' . http_build_query($q) : '');
    header('Location: ' . $clean);
    exit;
}

/* Identité courante (session locale → jeton de retour → cookie partagé), ou null. */
function luvumbu_user(): ?array {
    luvumbu_consume();
    if (!empty($_SESSION['luvid']) && is_array($_SESSION['luvid'])) return $_SESSION['luvid'];
    $c = sso_current();                          // cookie partagé (même domaine)
    if ($c) { $_SESSION['luvid'] = $c; return $c; }
    return null;
}

/* Force la connexion : renvoie l'utilisateur, ou redirige vers le hub. */
function luvumbu_require_login(?string $appName = null): array {
    $u = luvumbu_user();
    if ($u) return $u;
    $app = $appName ?? basename(dirname((string)($_SERVER['SCRIPT_NAME'] ?? 'app')));
    header('Location: ' . luvumbu_hub()
        . '?app=' . rawurlencode($app)
        . '&return=' . rawurlencode(luvumbu_here()));
    exit;
}

/* Déconnexion locale (+ redirection éventuelle vers le hub pour couper la session globale). */
function luvumbu_logout(bool $global = false, string $returnTo = ''): void {
    unset($_SESSION['luvid']);
    if ($global) {
        $r = $returnTo !== '' ? '?return=' . rawurlencode($returnTo) : '';
        header('Location: ' . rtrim(luvumbu_hub(), '/') . '/logout.php' . $r);
        exit;
    }
}
