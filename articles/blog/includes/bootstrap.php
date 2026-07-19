<?php
// Point d'entrée commun à toutes les pages.
// Vérifie l'installation, démarre la session, expose $pdo et les helpers.

// Affichage temporaire des erreurs (à désactiver en production une fois debugué)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';

$configFile = __DIR__ . '/../config/config.php';

if (!file_exists($configFile)) {
    // Pas encore installé : on redirige vers l'installateur.
    // On évite la boucle si on est déjà sur install.php.
    $self = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($self, 'install.php') === false) {
        header('Location: ' . base_url('install.php'));
        exit;
    }
    return;
}

require_once $configFile;
require_once __DIR__ . '/db.php';

// === Auto-migration silencieuse ===
// Applique les changements de schéma manquants au premier passage,
// puis stocke la version pour éviter de recommencer à chaque requête.
(function () use ($pdo) {
    $CURRENT_SCHEMA_VERSION = 4;
    try {
        // Vérifie que la table settings existe avant de lire la version
        $hasSettings = (bool)$pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings'")->fetchColumn();
        if (!$hasSettings) return; // install.php pas encore passé

        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'schema_version'");
        $stmt->execute();
        $current = (int)($stmt->fetchColumn() ?: 0);
        if ($current >= $CURRENT_SCHEMA_VERSION) return;

        $columnExists = function ($table, $column) use ($pdo) {
            $s = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $s->execute([$table, $column]);
            return (bool)$s->fetchColumn();
        };
        $tableExists = function ($table) use ($pdo) {
            $s = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            $s->execute([$table]);
            return (bool)$s->fetchColumn();
        };

        $columnMigrations = [
            ['articles', 'sources',    'ALTER TABLE articles ADD COLUMN sources TEXT DEFAULT NULL'],
            ['articles', 'updated_at', 'ALTER TABLE articles ADD COLUMN updated_at DATETIME DEFAULT NULL'],
            ['articles', 'layout',     'ALTER TABLE articles ADD COLUMN layout VARCHAR(255) DEFAULT NULL'],
            ['articles', 'visible',    'ALTER TABLE articles ADD COLUMN visible TINYINT(1) NOT NULL DEFAULT 1'],
            ['articles', 'parent_id',  'ALTER TABLE articles ADD COLUMN parent_id INT DEFAULT NULL, ADD CONSTRAINT fk_articles_parent FOREIGN KEY (parent_id) REFERENCES articles(id) ON DELETE CASCADE, ADD INDEX idx_articles_parent (parent_id)'],
            ['users',    'is_admin',   'ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0'],
        ];
        foreach ($columnMigrations as [$table, $column, $sql]) {
            if (!$columnExists($table, $column)) {
                try { $pdo->exec($sql); } catch (Exception $e) { /* on continue */ }
            }
        }

        $tableMigrations = [
            ['article_views', 'CREATE TABLE article_views (
                id INT AUTO_INCREMENT PRIMARY KEY,
                article_id INT NOT NULL,
                ip_hash CHAR(64) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_article_ip (article_id, ip_hash),
                KEY idx_views_article (article_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'],
            ['article_images', 'CREATE TABLE article_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                article_id INT NOT NULL,
                path VARCHAR(255) NOT NULL,
                caption VARCHAR(255) DEFAULT NULL,
                position INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_images_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'],
            ['api_tokens', 'CREATE TABLE api_tokens (
                token CHAR(64) PRIMARY KEY,
                user_id INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                last_used_at DATETIME NULL DEFAULT NULL,
                INDEX idx_tokens_user (user_id),
                INDEX idx_tokens_expires (expires_at),
                CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'],
            ['quizzes', 'CREATE TABLE quizzes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                description TEXT,
                active TINYINT(1) NOT NULL DEFAULT 1,
                author_id INT DEFAULT NULL,
                author_name VARCHAR(150) NOT NULL DEFAULT \'\',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                KEY idx_quiz_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'],
            ['quiz_questions', 'CREATE TABLE quiz_questions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                quiz_id INT NOT NULL,
                body VARCHAR(500) NOT NULL,
                explanation VARCHAR(500) DEFAULT NULL,
                type VARCHAR(10) NOT NULL DEFAULT \'single\',
                position INT NOT NULL DEFAULT 0,
                KEY idx_qq_quiz (quiz_id),
                CONSTRAINT fk_qq_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'],
            ['quiz_options', 'CREATE TABLE quiz_options (
                id INT AUTO_INCREMENT PRIMARY KEY,
                question_id INT NOT NULL,
                label VARCHAR(300) NOT NULL,
                is_correct TINYINT(1) NOT NULL DEFAULT 0,
                position INT NOT NULL DEFAULT 0,
                KEY idx_qo_question (question_id),
                CONSTRAINT fk_qo_question FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'],
            ['article_quizzes', 'CREATE TABLE article_quizzes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                article_id INT NOT NULL,
                quiz_id INT NOT NULL,
                position INT NOT NULL DEFAULT 0,
                UNIQUE KEY uniq_aq (article_id, quiz_id),
                KEY idx_aq_article (article_id),
                CONSTRAINT fk_aq_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
                CONSTRAINT fk_aq_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'],
        ];
        foreach ($tableMigrations as [$table, $sql]) {
            if (!$tableExists($table)) {
                try { $pdo->exec($sql); } catch (Exception $e) { /* on continue */ }
            }
        }

        // Marque le schéma comme à jour
        $u = $pdo->prepare("INSERT INTO settings (`key`, value) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $u->execute([$CURRENT_SCHEMA_VERSION]);
    } catch (Exception $e) {
        // Auto-migration silencieuse : si ça plante, on laisse le code applicatif planter avec sa propre erreur
    }
})();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/settings.php';
