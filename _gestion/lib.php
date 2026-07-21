<?php
/* ═══════════════════════════════════════════════════════════════════════
   GESTIONNAIRE DE FICHIERS — bibliothèque commune (auth + sécurité).
   Partagé par index.php (interface) et api.php (backend JSON).

   Sécurité :
   - Authentification par session (même logique qu'admin.php).
   - Confinement strict à la racine du site via realpath (anti path-traversal).
   - Jeton CSRF sur toutes les actions modifiantes.
   - Anti-bruteforce (verrouillage IP après trop d'échecs).
   - Cookies de session durcis.
   À NE PAS exposer sans mot de passe fort. Voir README.txt.
   ═══════════════════════════════════════════════════════════════════════ */

declare(strict_types=1);

/* ─── Racine gérée ────────────────────────────────────────────────────────
   Par défaut : le dossier PARENT de _gestion/ = la racine du site.
   Tous les projets (anniversaire, cv_luvumbu, bokonzi, dropbox…) sont dessous.
   Pour restreindre, remplacez par un chemin absolu (realpath). */
if (!defined('FS_ROOT')) {
    define('FS_ROOT', realpath(dirname(__DIR__)) ?: dirname(__DIR__));
}

/* Taille maxi d'un fichier éditable en texte (2 Mo) et d'un envoi (64 Mo). */
if (!defined('FS_MAX_EDIT'))   define('FS_MAX_EDIT', 2 * 1024 * 1024);
if (!defined('FS_MAX_UPLOAD')) define('FS_MAX_UPLOAD', 64 * 1024 * 1024);

/* Anti-bruteforce : n essais puis verrou. */
const FS_MAX_TRIES = 6;
const FS_LOCK_SECS = 900;   // 15 min

/* ─── Session durcie ─────────────────────────────────────────────────────── */
function fs_boot(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $https,
    ]);
    // Session PAR DÉFAUT (PHPSESSID) → partagée avec admin.php : être connecté
    // à l'espace admin donne aussi accès au gestionnaire, sans 2e mot de passe.
    session_start();
    if (empty($_SESSION['fs_csrf'])) {
        $_SESSION['fs_csrf'] = bin2hex(random_bytes(32));
    }
}

// Authentifié si connecté au gestionnaire (fs_admin) OU à l'espace admin du
// portfolio (pf_admin, posé par admin.php) — session partagée.
function fs_authed(): bool { return !empty($_SESSION['fs_admin']) || !empty($_SESSION['pf_admin']); }

/* Requête en HTTPS ? (les clés d'API ne transitent jamais en clair hors local). */
function fs_is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/* Clé d'API (accès par programme, ex. depuis un script). Stockée dans
   _gestion/apikey.local.php (gitignoré) : <?php return 'longue_cle_aleatoire'; */
function fs_apikey(): string {
    $f = __DIR__ . '/apikey.local.php';
    if (is_file($f)) { $k = require $f; if (is_string($k)) return trim($k); }
    return '';
}

/* Auth par clé d'API : en-tête X-Api-Key ou paramètre key. Exige HTTPS (sauf local)
   et une clé d'au moins 24 caractères. Ne dépend PAS des cookies → pas de CSRF requis. */
function fs_apikey_ok(): bool {
    $key = fs_apikey();
    if (strlen($key) < 24) return false;                 // clé absente ou trop faible
    if (!fs_is_https() && !fs_is_local()) return false;  // jamais de clé en HTTP public
    $sent = $_SERVER['HTTP_X_API_KEY'] ?? ($_REQUEST['key'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals($key, $sent);
}

/* Environnement local (XAMPP) ? Sert à autoriser le mot de passe vide seulement ici.
   On exige l'IP loopback ET un host local : impossible de forcer en prod même en
   trichant l'en-tête Host (l'IP serait publique) ou l'inverse. */
function fs_is_local(): bool {
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $host = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
    $ipLocal   = in_array($ip, ['127.0.0.1', '::1', ''], true);
    $hostLocal = in_array($host, ['localhost', '127.0.0.1', ''], true)
              || substr($host, -6) === '.local';
    return $ipLocal && $hostLocal;
}
function fs_csrf(): string { return (string)($_SESSION['fs_csrf'] ?? ''); }
function fs_csrf_ok(?string $t): bool {
    return is_string($t) && $t !== '' && hash_equals(fs_csrf(), $t);
}

/* ─── Authentification (mêmes méthodes qu'admin.php) ─────────────────────── */
function fs_check_login(string $user, string $pass): bool {
    $pass = trim($pass);
    // Mot de passe VIDE : accepté UNIQUEMENT en local (confort de dev XAMPP).
    // En ligne (production), refusé — sinon tout le site serait ouvert à tous.
    if ($pass === '') return fs_is_local();

    // 1) Mot de passe haché local (recommandé) : _gestion/password.local.php
    //    return password_hash('votre_mdp', PASSWORD_DEFAULT);
    $hashFile = __DIR__ . '/password.local.php';
    if (is_file($hashFile)) {
        $hash = require $hashFile;
        if (is_string($hash) && $hash !== '' && password_verify($pass, $hash)) return true;
    }

    // 2) Mot de passe de secours du portfolio (config/portfolio.php).
    $cfgFile = dirname(__DIR__) . '/config/portfolio.php';
    if (is_file($cfgFile)) {
        $CFG = require $cfgFile;
        $pw  = $CFG['admin']['password'] ?? '';
        if ($pw !== '' && hash_equals(trim((string)$pw), $pass)) return true;
    }

    // 3) Identifiants MySQL BOKONZI réels (utilisateur + mot de passe).
    $credFile = dirname(dirname(__DIR__)) . '/core/credentials.php';
    if ($user !== '' && function_exists('mysqli_connect') && is_file($credFile)) {
        $BK_DB = '';
        (function () use (&$BK_DB, $credFile) {
            include $credFile;
            $local = dirname($credFile) . '/credentials_local.php';
            if (is_file($local)) include $local;
            $BK_DB = $dbname ?? '';
        })();
        try {
            $c = @mysqli_connect('localhost', $user, $pass, $BK_DB ?: '');
            if ($c) { @mysqli_close($c); return true; }
        } catch (\Throwable $e) { /* refusé → échec */ }
    }
    return false;
}

/* ─── Anti-bruteforce (fichier de verrou par IP) ─────────────────────────── */
function fs_lockfile(): string { return __DIR__ . '/.lockout.json'; }

function fs_lock_read(): array {
    $f = fs_lockfile();
    if (!is_file($f)) return [];
    $d = json_decode((string)@file_get_contents($f), true);
    return is_array($d) ? $d : [];
}

function fs_client_ip(): string {
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

/** Renvoie les secondes de verrou restantes (0 si non verrouillé). */
function fs_locked_for(): int {
    $d  = fs_lock_read();
    $ip = fs_client_ip();
    $e  = $d[$ip] ?? null;
    if (!$e) return 0;
    $until = (int)($e['until'] ?? 0);
    $rem   = $until - time();
    return $rem > 0 ? $rem : 0;
}

function fs_record_fail(): void {
    $f = fs_lockfile();
    $fp = @fopen($f, 'c+');
    if (!$fp) return;
    @flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $d   = json_decode((string)$raw, true); $d = is_array($d) ? $d : [];
    $ip  = fs_client_ip();
    $e   = $d[$ip] ?? ['tries' => 0, 'until' => 0];
    $e['tries'] = (int)$e['tries'] + 1;
    if ($e['tries'] >= FS_MAX_TRIES) {
        $e['until'] = time() + FS_LOCK_SECS;
        $e['tries'] = 0;
    }
    $d[$ip] = $e;
    // purge des entrées expirées
    foreach ($d as $k => $v) {
        if (($v['until'] ?? 0) < time() && ($v['tries'] ?? 0) === 0) unset($d[$k]);
    }
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($d));
    @flock($fp, LOCK_UN); fclose($fp);
}

function fs_record_success(): void {
    $f = fs_lockfile();
    if (!is_file($f)) return;
    $d = fs_lock_read();
    unset($d[fs_client_ip()]);
    @file_put_contents($f, json_encode($d));
}

/* ─── Sécurité des chemins ───────────────────────────────────────────────
   Convertit un chemin RELATIF (fourni par le client) en chemin absolu
   garanti à l'intérieur de FS_ROOT. Lève une exception sinon. */
function fs_resolve(string $rel, bool $mustExist = true): string {
    $rel = str_replace('\\', '/', $rel);
    $rel = ltrim($rel, '/');
    // neutralise les composants dangereux
    $parts = [];
    foreach (explode('/', $rel) as $p) {
        if ($p === '' || $p === '.') continue;
        if ($p === '..') { array_pop($parts); continue; }
        $parts[] = $p;
    }
    $full = FS_ROOT . (count($parts) ? '/' . implode('/', $parts) : '');

    if ($mustExist) {
        $real = realpath($full);
        if ($real === false) throw new RuntimeException("Introuvable : /$rel");
        $full = $real;
    } else {
        // le parent doit exister et être dans la racine
        $parent = realpath(dirname($full));
        if ($parent === false) throw new RuntimeException("Dossier parent introuvable.");
        $full = $parent . '/' . basename($full);
    }
    // vérif de confinement — on NORMALISE les séparateurs (Windows renvoie des
    // « \ » via realpath alors que $full est bâti avec « / »).
    $rootReal = realpath(FS_ROOT);
    if ($rootReal === false) throw new RuntimeException("Racine introuvable.");
    $rootN  = rtrim(str_replace('\\', '/', $rootReal), '/');
    $check  = $mustExist ? $full : dirname($full);
    $checkN = rtrim(str_replace('\\', '/', $check), '/');
    if ($checkN !== $rootN && strncmp($checkN . '/', $rootN . '/', strlen($rootN) + 1) !== 0) {
        throw new RuntimeException("Accès hors racine refusé.");
    }
    return $full;
}

/** Chemin relatif (affiché) à partir d'un absolu confiné. */
function fs_relpath(string $abs): string {
    $root = realpath(FS_ROOT) ?: FS_ROOT;
    $abs  = str_replace('\\', '/', $abs);
    $root = str_replace('\\', '/', $root);
    if (strncmp($abs, $root, strlen($root)) === 0) {
        return ltrim(substr($abs, strlen($root)), '/');
    }
    return basename($abs);
}

/** Le dossier _gestion lui-même (à protéger contre suppression/déplacement). */
function fs_is_self(string $abs): bool {
    $self = realpath(__DIR__);
    $abs  = realpath($abs) ?: $abs;
    return $self !== false && ($abs === $self
        || strncmp(str_replace('\\','/',$abs).'/', str_replace('\\','/',$self).'/', strlen($self)+1) === 0);
}

/* ─── Utilitaires ────────────────────────────────────────────────────────── */
function fs_human(int $b): string {
    $u = ['o','Ko','Mo','Go','To']; $i = 0;
    $x = (float)$b;
    while ($x >= 1024 && $i < count($u) - 1) { $x /= 1024; $i++; }
    return ($i === 0 ? (string)$b : number_format($x, 1, ',', ' ')) . ' ' . $u[$i];
}

/** Type texte éditable ? (par extension) */
function fs_is_text(string $name): bool {
    static $ext = ['php','phtml','html','htm','css','js','mjs','json','txt','md','xml',
        'svg','csv','sql','ini','conf','htaccess','yml','yaml','env','log','ts','jsx','tsx',
        'py','sh','c','h','cpp','java','tpl','twig','vue','gitignore','webmanifest'];
    $e = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($e === '' && in_array(strtolower($name), ['.htaccess','.gitignore','.env'], true)) return true;
    return in_array($e, $ext, true);
}

/** Suppression récursive confinée. */
function fs_rrmdir(string $dir): bool {
    if (is_file($dir) || is_link($dir)) return @unlink($dir);
    if (!is_dir($dir)) return false;
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        fs_rrmdir($dir . '/' . $f);
    }
    return @rmdir($dir);
}

/** Réponse JSON puis arrêt. */
function fs_json($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
