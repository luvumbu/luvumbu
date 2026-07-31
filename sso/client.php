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

/* URL du hub SSO.
   1) constante LUVUMBU_HUB si elle est définie avant l'include (prioritaire) ;
   2) sinon, si ce dossier sso/ est servi par l'hôte courant (cas d'une app du
      même serveur : localhost comme luvumbu.com), on déduit son URL du chemin
      disque — aucune configuration à faire, ni en local ni en ligne ;
   3) sinon (copie du dossier sso/ sur un autre serveur), le hub de référence. */
function luvumbu_hub(): string {
    if (defined('LUVUMBU_HUB')) return LUVUMBU_HUB;
    $root = str_replace('\\', '/', (string)realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')));
    $here = str_replace('\\', '/', (string)realpath(__DIR__));
    if ($root !== '' && $here !== '' && strpos($here . '/', rtrim($root, '/') . '/') === 0) {
        $path = substr($here, strlen(rtrim($root, '/')));          // ex. /luvumbu/sso
        return (sso_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path . '/';
    }
    return 'https://luvumbu.com/sso/';
}

/* URL courante complète (pour le retour après login). */
function luvumbu_here(): string {
    $scheme = sso_https() ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
}

/* URL absolue d'une page voisine de la page courante (ex. 'admin.php'),
   pratique pour indiquer au hub où revenir après une déconnexion. */
function luvumbu_url(string $relative = ''): string {
    $scheme = sso_https() ? 'https' : 'http';
    $dir    = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/' . ltrim($relative, '/');
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

/* ─── Rôles ────────────────────────────────────────────────────────────────
   Le hub place les rôles de l'utilisateur dans le jeton (`roles`), sous la
   forme ['*' => 'admin', 'blog' => 'user', …]. L'application n'a donc rien à
   interroger pour savoir à quoi elle a affaire. */

/** Rôle de l'utilisateur dans une app : 'admin', 'user' ou 'none'. */
function luvumbu_role(?array $user, string $app = ''): string {
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    $app   = trim($app);
    if ($app !== '' && isset($roles[$app])) return (string)$roles[$app];
    if (isset($roles['*']))                 return (string)$roles['*'];
    return 'none';
}

/** L'utilisateur est-il administrateur de cette app (ou de tout l'écosystème) ? */
function luvumbu_is_admin(?array $user, string $app = ''): bool {
    return luvumbu_role($user, $app) === 'admin';
}

/** Force la connexion ET le rôle administrateur ; sinon 403. */
function luvumbu_require_admin(?string $appName = null): array {
    $app  = $appName ?? basename(dirname((string)($_SERVER['SCRIPT_NAME'] ?? 'app')));
    $user = luvumbu_require_login($app);
    if (!luvumbu_is_admin($user, $app)) {
        http_response_code(403);
        exit('Accès réservé à l\'administrateur.');
    }
    return $user;
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
