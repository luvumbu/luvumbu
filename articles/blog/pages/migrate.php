<?php
// Script de migration. À lancer après chaque évolution du schéma.
// Idempotent : tu peux le lancer plusieurs fois sans danger.

require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

function column_exists(PDO $pdo, $table, $column) {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function table_exists(PDO $pdo, $table) {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

$applied = [];
$skipped = [];

// --- Colonnes ---
$columnMigrations = [
    ['articles', 'sources',    'ALTER TABLE articles ADD COLUMN sources TEXT DEFAULT NULL'],
    ['articles', 'updated_at', 'ALTER TABLE articles ADD COLUMN updated_at DATETIME DEFAULT NULL'],
    ['articles', 'layout',     'ALTER TABLE articles ADD COLUMN layout VARCHAR(255) DEFAULT NULL'],
    ['articles', 'visible',    'ALTER TABLE articles ADD COLUMN visible TINYINT(1) NOT NULL DEFAULT 1'],
    ['articles', 'publish_at', publish_at_alter_sql()],
    ['articles', 'parent_id',  'ALTER TABLE articles ADD COLUMN parent_id INT DEFAULT NULL, ADD CONSTRAINT fk_articles_parent FOREIGN KEY (parent_id) REFERENCES articles(id) ON DELETE CASCADE, ADD INDEX idx_articles_parent (parent_id)'],
    ['users',    'is_admin',   'ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0'],
];

foreach ($columnMigrations as [$table, $column, $sql]) {
    if (column_exists($pdo, $table, $column)) {
        $skipped[] = "colonne $table.$column (déjà présente)";
    } else {
        $pdo->exec($sql);
        $applied[] = "colonne $table.$column ajoutée";
    }
}

// --- Tables ---
$tableMigrations = [
    ['settings', '
        CREATE TABLE settings (
            `key` VARCHAR(64) PRIMARY KEY,
            value TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    '],
    ['article_views', '
        CREATE TABLE article_views (
            id INT AUTO_INCREMENT PRIMARY KEY,
            article_id INT NOT NULL,
            ip_hash CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_article_ip (article_id, ip_hash),
            KEY idx_views_article (article_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    '],
    ['article_images', '
        CREATE TABLE article_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            article_id INT NOT NULL,
            path VARCHAR(255) NOT NULL,
            caption VARCHAR(255) DEFAULT NULL,
            position INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_images_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    '],
    ['api_tokens', '
        CREATE TABLE api_tokens (
            token CHAR(64) PRIMARY KEY,
            user_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            last_used_at DATETIME NULL DEFAULT NULL,
            INDEX idx_tokens_user (user_id),
            INDEX idx_tokens_expires (expires_at),
            CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    '],
];

foreach ($tableMigrations as [$table, $sql]) {
    if (table_exists($pdo, $table)) {
        $skipped[] = "table $table (déjà présente)";
    } else {
        $pdo->exec($sql);
        $applied[] = "table $table créée";
    }
}

$pageTitle = 'Migration';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-card auth-card-wide">
    <h1>Migration de la base</h1>
    <?php if (!empty($applied)): ?>
        <div class="flash flash-success">
            <strong>Appliqué :</strong>
            <ul><?php foreach ($applied as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <?php if (!empty($skipped)): ?>
        <div class="flash">
            <strong>Ignoré :</strong>
            <ul><?php foreach ($skipped as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <p><a class="btn-primary" href="<?= e(base_url('index.php')) ?>">Retour à l'accueil</a></p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
