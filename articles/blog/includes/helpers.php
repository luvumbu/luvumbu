<?php
// Fonctions utilitaires.

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header('Location: ' . $path);
    exit;
}

function base_url($path = '') {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if (in_array(basename($scriptDir), ['pages', 'api', 'admin'], true)) {
        $scriptDir = dirname($scriptDir);
    }
    // Normalise : '/' (projet à la racine du domaine) devient '' pour éviter '//foo'
    $scriptDir = rtrim($scriptDir, '/\\');
    return $scriptDir . '/' . ltrim($path, '/');
}

function absolute_url($path = '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . base_url($path);
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check($token) {
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$token);
}

function flash_set($key, $message) {
    $_SESSION['flash'][$key] = $message;
}

function flash_get($key) {
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

// Adresse IP du visiteur (gère les reverse-proxies / CDN type Cloudflare).
function client_ip() {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// SQL de création de la table des vues (réutilisé par l'auto-réparation).
function article_views_create_sql(): string {
    // Pas de clé étrangère ici : sur certains hébergements (table articles en
    // MyISAM, collations différentes...) un FK fait échouer le CREATE. L'unicité
    // (article_id, ip_hash) suffit pour le comptage par IP.
    return 'CREATE TABLE IF NOT EXISTS article_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        article_id INT NOT NULL,
        ip_hash CHAR(64) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_article_ip (article_id, ip_hash),
        KEY idx_views_article (article_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
}

// Enregistre une vue pour un article : une seule par IP unique (INSERT IGNORE
// sur la contrainte unique). Si la table n'existe pas encore, on la crée puis
// on réessaie une fois — ainsi le compteur fonctionne même sans migration.
// article_id = 0 est réservé à la page d'accueil (voir record_home_view).
function record_article_view(PDO $pdo, int $articleId): void {
    if ($articleId < 0) return;
    $ipHash = hash('sha256', client_ip());
    $sql = 'INSERT IGNORE INTO article_views (article_id, ip_hash) VALUES (?, ?)';
    try {
        $pdo->prepare($sql)->execute([$articleId, $ipHash]);
    } catch (Throwable $e) {
        try {
            $pdo->exec(article_views_create_sql());
            $pdo->prepare($sql)->execute([$articleId, $ipHash]);
        } catch (Throwable $e2) {
            // best-effort : ne jamais casser l'affichage de l'article.
        }
    }
}

// Enregistre une vue de la PAGE D'ACCUEIL (article_id = 0), 1 par IP unique.
function record_home_view(PDO $pdo): void {
    record_article_view($pdo, 0);
}

// Compte les vues (IP uniques) d'un article. Crée la table au besoin. Retourne 0 si indisponible.
function count_article_views(PDO $pdo, int $articleId): int {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM article_views WHERE article_id = ?');
        $stmt->execute([$articleId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        try { $pdo->exec(article_views_create_sql()); } catch (Throwable $e2) {}
        return 0;
    }
}

// Ordre par défaut des blocs d'un article.
function default_layout() {
    return ['title', 'cover', 'content', 'gallery', 'sources'];
}

// Parse "title,cover,content,gallery,sources" → tableau. Garantit que tous les blocs sont présents.
function parse_layout($value) {
    $defaults = default_layout();
    if (empty($value)) return $defaults;
    $parts = array_map('trim', explode(',', (string)$value));
    $valid = [];
    foreach ($parts as $p) {
        if (in_array($p, $defaults, true) && !in_array($p, $valid, true)) {
            $valid[] = $p;
        }
    }
    // Ajoute en fin ceux qui manquent (pour rester rétrocompatible)
    foreach ($defaults as $d) {
        if (!in_array($d, $valid, true)) $valid[] = $d;
    }
    return $valid;
}

// Trie un tableau de positions [key => position] et renvoie les clés ordonnées.
function order_layout_from_positions(array $positions) {
    $defaults = default_layout();
    $clean = [];
    foreach ($defaults as $key) {
        $clean[$key] = isset($positions[$key]) ? (int)$positions[$key] : 99;
    }
    asort($clean);
    return array_keys($clean);
}
