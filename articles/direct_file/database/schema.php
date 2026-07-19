<?php
/**
 * Schéma de la base de données — source unique de vérité.
 *
 * Utilisé par SetupController (/setup et /install) pour créer les tables.
 * Toutes les instructions sont idempotentes (CREATE TABLE IF NOT EXISTS).
 */

/**
 * Crée toutes les tables de l'application sur la connexion fournie.
 * La base doit déjà être sélectionnée (USE `...`) au préalable.
 */
function create_schema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS conversations (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            code          CHAR(5) NOT NULL UNIQUE,
            title         VARCHAR(120) NOT NULL DEFAULT "",
            password_hash VARCHAR(255) DEFAULT NULL,
            is_open       TINYINT(1) NOT NULL DEFAULT 1,
            creator_ip    VARCHAR(45) NOT NULL,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS participants (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            ip              VARCHAR(45) NOT NULL,
            pseudo          VARCHAR(40) NOT NULL,
            joined_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_conv_ip (conversation_id, ip),
            CONSTRAINT fk_part_conv FOREIGN KEY (conversation_id)
                REFERENCES conversations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS messages (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            ip              VARCHAR(45) NOT NULL,
            pseudo          VARCHAR(40) NOT NULL,
            content         TEXT NOT NULL,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_conv (conversation_id, id),
            CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id)
                REFERENCES conversations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS df_settings (
            k VARCHAR(64) PRIMARY KEY,
            v VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
}
