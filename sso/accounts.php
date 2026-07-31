<?php
/* ═══════════════════════════════════════════════════════════════════════
   LUVUMBU ID — ANNUAIRE CENTRAL DES COMPTES.

   Le hub était jusqu'ici un simple relais Google + un mot de passe unique.
   Pour être un vrai point d'entrée unique, il lui faut savoir QUI a le droit
   d'entrer, et AVEC QUEL RÔLE dans chaque application. C'est le rôle de ce
   fichier : un annuaire de comptes, sans base de données.

   Stockage : sso/accounts.local.php — hors dépôt (gitignoré), comme le
   secret. Quelques dizaines de comptes tiennent sans peine dans un tableau
   PHP, et rien ne dépend de MySQL (qui n'est pas déployé partout).

   Modèle d'un compte :
       'jean@exemple.fr' => [
           'email'      => 'jean@exemple.fr',
           'name'       => 'Jean',
           'pass_hash'  => '$2y$…',          // null si connexion Google seule
           'roles'      => ['*' => 'admin'], // ou ['blog' => 'user', …]
           'disabled'   => false,
           'created_at' => 1753900000,
           'last_login' => 1753900000,
       ]

   Rôles : la clé est le nom d'application transmis par le client SSO
   (`?app=blog`), '*' valant pour toutes. Valeurs : 'admin' ou 'user'.
   ABSENCE D'ENTRÉE = AUCUN ACCÈS : un compte Google inconnu ne peut donc
   pas se servir du hub comme passe-partout.
   ═══════════════════════════════════════════════════════════════════════ */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

const LUVID_ROLE_NONE  = 'none';
const LUVID_ROLE_USER  = 'user';
const LUVID_ROLE_ADMIN = 'admin';

/* Blocage après échecs répétés (protection contre les essais en force). */
const LUVID_MAX_ATTEMPTS = 5;
const LUVID_LOCK_SECONDS = 900;   // 15 minutes

function luvid_store_file(): string   { return __DIR__ . '/accounts.local.php'; }
function luvid_attempts_file(): string { return __DIR__ . '/.attempts.json'; }

/* ─────────────────────────────────────────────────────────────────────────
   Lecture / écriture de l'annuaire
   ───────────────────────────────────────────────────────────────────────── */

/** Normalise une adresse e-mail (c'est la clé de l'annuaire). */
function luvid_norm_email(string $email): string {
    return strtolower(trim($email));
}

/** Complète un compte lu du disque avec les valeurs par défaut manquantes. */
function luvid_account_normalize(array $a, string $email): array {
    return [
        'email'      => luvid_norm_email((string)($a['email'] ?? $email)),
        'name'       => (string)($a['name'] ?? '') !== '' ? (string)$a['name'] : $email,
        'pass_hash'  => isset($a['pass_hash']) && $a['pass_hash'] !== '' ? (string)$a['pass_hash'] : null,
        'roles'      => is_array($a['roles'] ?? null) ? $a['roles'] : [],
        'disabled'   => !empty($a['disabled']),
        'created_at' => (int)($a['created_at'] ?? time()),
        'last_login' => (int)($a['last_login'] ?? 0),
    ];
}

/**
 * Charge l'annuaire complet, indexé par e-mail.
 *
 * Amorçage : tant qu'aucun fichier n'existe, on reprend le compte de secours
 * déclaré dans secret.local.php ('password' + 'local_user'). L'accès existant
 * continue donc de fonctionner sans aucune manipulation.
 */
function luvid_accounts_load(): array {
    $f   = luvid_store_file();
    $raw = is_file($f) ? (@include $f) : null;

    if (!is_array($raw) || !$raw) {
        return luvid_accounts_seed();
    }

    $out = [];
    foreach ($raw as $email => $a) {
        if (!is_array($a)) continue;
        $key = luvid_norm_email((string)$email);
        if ($key === '') continue;
        $out[$key] = luvid_account_normalize($a, $key);
    }
    return $out;
}

/** Compte d'amorçage déduit de secret.local.php (aucun fichier annuaire encore). */
function luvid_accounts_seed(): array {
    $cfg   = sso_config();
    $local = is_array($cfg['local_user'] ?? null) ? $cfg['local_user'] : [];
    $email = luvid_norm_email((string)($local['email'] ?? ''));
    if ($email === '') return [];

    $pw = (string)($cfg['password'] ?? '');
    return [$email => luvid_account_normalize([
        'email'     => $email,
        'name'      => (string)($local['name'] ?? $email),
        'pass_hash' => $pw !== '' ? password_hash($pw, PASSWORD_DEFAULT) : null,
        'roles'     => ['*' => LUVID_ROLE_ADMIN],   // le compte d'origine est administrateur
    ], $email)];
}

/** Écrit l'annuaire sur le disque. Renvoie false si le dossier n'est pas inscriptible. */
function luvid_accounts_save(array $accounts): bool {
    ksort($accounts);
    $php = "<?php\n"
         . "/* LUVUMBU ID — annuaire des comptes. Fichier GÉNÉRÉ et HORS DÉPÔT.\n"
         . "   Édition recommandée par sso/accounts_admin.php. */\n"
         . 'return ' . var_export($accounts, true) . ";\n";

    $f  = luvid_store_file();
    $ok = @file_put_contents($f, $php, LOCK_EX) !== false;
    // Sans cela, PHP pourrait resservir la version précédente du fichier.
    if ($ok && function_exists('opcache_invalidate')) @opcache_invalidate($f, true);
    return $ok;
}

/** Un compte par son e-mail, ou null. */
function luvid_account_get(string $email): ?array {
    $all = luvid_accounts_load();
    return $all[luvid_norm_email($email)] ?? null;
}

/** Crée ou remplace un compte. */
function luvid_account_put(array $acc): bool {
    $email = luvid_norm_email((string)($acc['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $all = luvid_accounts_load();
    $all[$email] = luvid_account_normalize($acc, $email);
    return luvid_accounts_save($all);
}

/** Supprime un compte. Refuse de retirer le dernier administrateur. */
function luvid_account_delete(string $email): bool {
    $email = luvid_norm_email($email);
    $all   = luvid_accounts_load();
    if (!isset($all[$email])) return false;

    $rest = $all; unset($rest[$email]);
    if (!luvid_has_any_admin($rest)) return false;      // ne pas se verrouiller dehors

    return luvid_accounts_save($rest);
}

/** Y a-t-il encore au moins un administrateur actif dans ce jeu de comptes ? */
function luvid_has_any_admin(array $accounts): bool {
    foreach ($accounts as $a) {
        if (empty($a['disabled']) && in_array(LUVID_ROLE_ADMIN, (array)($a['roles'] ?? []), true)) return true;
    }
    return false;
}

/** Change (ou retire, avec '') le mot de passe d'un compte. */
function luvid_account_set_password(string $email, string $plain): bool {
    $acc = luvid_account_get($email);
    if (!$acc) return false;
    $acc['pass_hash'] = $plain !== '' ? password_hash($plain, PASSWORD_DEFAULT) : null;
    return luvid_account_put($acc);
}

/** Note la date de dernière connexion (sans échouer si le disque est en lecture seule). */
function luvid_account_touch_login(string $email): void {
    $all = luvid_accounts_load();
    $key = luvid_norm_email($email);
    if (!isset($all[$key])) return;
    $all[$key]['last_login'] = time();
    luvid_accounts_save($all);
}

/* ─────────────────────────────────────────────────────────────────────────
   Rôles
   ───────────────────────────────────────────────────────────────────────── */

/**
 * Rôle d'un compte dans une application donnée.
 * On prend l'entrée la plus précise ('blog') avant l'entrée générale ('*').
 */
function luvid_role_of(array $roles, string $app): string {
    $app = trim($app);
    if ($app !== '' && isset($roles[$app])) return (string)$roles[$app];
    if (isset($roles['*']))                 return (string)$roles['*'];
    return LUVID_ROLE_NONE;
}

/** Le compte a-t-il le droit d'entrer dans cette application ? */
function luvid_can_access(array $account, string $app): bool {
    if (!empty($account['disabled'])) return false;
    return luvid_role_of((array)($account['roles'] ?? []), $app) !== LUVID_ROLE_NONE;
}

/* ─────────────────────────────────────────────────────────────────────────
   Authentification
   ───────────────────────────────────────────────────────────────────────── */

/** Clé de comptage des échecs : l'adresse tentée + l'IP appelante. */
function luvid_attempt_key(string $email): string {
    return sha1(luvid_norm_email($email) . '|' . (string)($_SERVER['REMOTE_ADDR'] ?? ''));
}

/** Secondes restantes avant de pouvoir réessayer (0 = pas de blocage). */
function luvid_lock_remaining(string $email): int {
    $data = @json_decode((string)@file_get_contents(luvid_attempts_file()), true);
    $rec  = is_array($data) ? ($data[luvid_attempt_key($email)] ?? null) : null;
    if (!is_array($rec)) return 0;
    if ((int)($rec['n'] ?? 0) < LUVID_MAX_ATTEMPTS) return 0;
    $left = (int)($rec['t'] ?? 0) + LUVID_LOCK_SECONDS - time();
    return $left > 0 ? $left : 0;
}

/** Enregistre un échec, ou remet le compteur à zéro après une réussite. */
function luvid_attempt_record(string $email, bool $success): void {
    $f    = luvid_attempts_file();
    $data = @json_decode((string)@file_get_contents($f), true);
    if (!is_array($data)) $data = [];
    $key = luvid_attempt_key($email);

    if ($success) {
        unset($data[$key]);
    } else {
        $rec = is_array($data[$key] ?? null) ? $data[$key] : ['n' => 0, 't' => 0];
        // Un blocage écoulé repart de zéro.
        if ((int)$rec['n'] >= LUVID_MAX_ATTEMPTS && time() - (int)$rec['t'] > LUVID_LOCK_SECONDS) $rec = ['n' => 0, 't' => 0];
        $data[$key] = ['n' => (int)$rec['n'] + 1, 't' => time()];
    }

    // Purge des entrées oubliées (évite que le fichier ne grossisse indéfiniment).
    foreach ($data as $k => $v) {
        if (time() - (int)($v['t'] ?? 0) > 86400) unset($data[$k]);
    }
    @file_put_contents($f, json_encode($data), LOCK_EX);
}

/**
 * Vérifie un couple e-mail / mot de passe contre l'annuaire.
 * Renvoie le compte, ou null. $err reçoit un message affichable.
 */
function luvid_password_login(string $email, string $plain, ?string &$err = null): ?array {
    $email = luvid_norm_email($email);

    if ($wait = luvid_lock_remaining($email)) {
        $err = 'Trop de tentatives. Réessaie dans ' . ceil($wait / 60) . ' min.';
        return null;
    }

    $acc = luvid_account_get($email);

    // password_verify est exécuté même sans compte, pour que le temps de
    // réponse ne révèle pas quelles adresses existent.
    $hash = (string)($acc['pass_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidin');
    $ok   = password_verify($plain, $hash) && $acc !== null && $acc['pass_hash'] !== null;

    if (!$ok || !empty($acc['disabled'])) {
        luvid_attempt_record($email, false);
        $err = !empty($acc['disabled']) ? 'Ce compte est désactivé.' : 'E-mail ou mot de passe incorrect.';
        return null;
    }

    luvid_attempt_record($email, true);
    return $acc;
}

/**
 * Identité Google vérifiée → compte de l'annuaire.
 *
 * Si l'annuaire est vide, la toute première adresse Google qui se présente
 * l'adopte comme administrateur (même logique d'amorçage que cv_luvumbu).
 * Ensuite, une adresse inconnue est refusée : le hub n'est pas ouvert à tous.
 */
function luvid_google_login(array $g, ?string &$err = null): ?array {
    $email = luvid_norm_email((string)($g['email'] ?? ''));
    if ($email === '') { $err = 'Compte Google sans adresse e-mail.'; return null; }

    $all = luvid_accounts_load();

    if (!$all) {
        $acc = luvid_account_normalize([
            'email' => $email,
            'name'  => (string)($g['name'] ?? $email),
            'roles' => ['*' => LUVID_ROLE_ADMIN],
        ], $email);
        luvid_account_put($acc);
        return $acc;
    }

    $acc = $all[$email] ?? null;
    if (!$acc)                    { $err = "Aucun compte Luvumbu ID pour « $email ». Demande à l'administrateur de te créer un accès."; return null; }
    if (!empty($acc['disabled'])) { $err = 'Ce compte est désactivé.'; return null; }

    // Un compte créé à la main sans nom récupère celui de Google.
    if (($acc['name'] === $acc['email']) && !empty($g['name'])) {
        $acc['name'] = (string)$g['name'];
        luvid_account_put($acc);
    }
    return $acc;
}

/**
 * Les revendications à mettre dans le JWT pour un compte donné.
 * `roles` voyage dans le jeton : chaque app connaît le rôle sans rien interroger.
 */
function luvid_claims(array $acc, array $extra = []): array {
    return [
        'email'   => (string)$acc['email'],
        'name'    => (string)$acc['name'],
        'sub'     => (string)($extra['sub'] ?? ('luvid:' . sha1((string)$acc['email']))),
        'picture' => (string)($extra['picture'] ?? ''),
        'roles'   => (array)($acc['roles'] ?? []),
    ];
}
