<?php
/**
 * Réglages applicatifs (table clé/valeur).
 * Utilisé notamment pour la configuration « Connexion avec Google »
 * (identifiant client + secret), modifiable depuis la page Paramètres.
 * La table est créée à la demande (migration douce).
 */

require_once __DIR__ . '/db.php';

/** Garantit l'existence de la table des réglages. */
function ensure_settings_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    db()->exec("CREATE TABLE IF NOT EXISTS settings (
        name       VARCHAR(60) NOT NULL PRIMARY KEY,
        value      TEXT        NULL,
        updated_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done = true;
}

/** Lit un réglage (ou la valeur par défaut s'il est absent). */
function get_setting(string $name, ?string $default = null): ?string
{
    ensure_settings_schema();
    $stmt = db()->prepare("SELECT value FROM settings WHERE name = ? LIMIT 1");
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}

/** Écrit un réglage (valeur vide = enregistre une chaîne vide). */
function set_setting(string $name, ?string $value): void
{
    ensure_settings_schema();
    $stmt = db()->prepare(
        "INSERT INTO settings (name, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)"
    );
    $stmt->execute([$name, $value]);
}
