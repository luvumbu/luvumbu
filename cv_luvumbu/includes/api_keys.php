<?php
/**
 * Gestion des clés API.
 *
 * - La clé s'auto-génère (format cvk_<40 hex>) à la création.
 * - On ne stocke jamais la clé en clair : seul son hachage SHA-256 est enregistré.
 * - La clé complète n'est affichée qu'une seule fois, à sa création.
 * - Chaque clé porte un jeu de permissions (scopes) définissant ce qu'elle peut faire.
 */

require_once __DIR__ . '/db.php';

/**
 * Liste des permissions disponibles : identifiant => libellé affiché.
 * Ajustez cette liste selon les besoins de l'application.
 */
function available_scopes(): array
{
    return [
        'cv:read'       => 'Lire les CV',
        'cv:write'      => 'Créer et modifier les CV',
        'cv:delete'     => 'Supprimer les CV',
        'profile:read'  => 'Lire le profil',
        'profile:write' => 'Modifier le profil',
    ];
}

/** Ne conserve que les scopes valides parmi ceux fournis. */
function sanitize_scopes(array $scopes): array
{
    $allowed = array_keys(available_scopes());
    return array_values(array_intersect($allowed, $scopes));
}

/** Crée la table des clés API si besoin, et applique les migrations. */
function ensure_api_keys_table(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id      INT UNSIGNED NOT NULL,
        label        VARCHAR(100) NOT NULL,
        scopes       VARCHAR(255) NOT NULL DEFAULT '',
        key_prefix   VARCHAR(12)  NOT NULL,
        key_hash     CHAR(64)     NOT NULL,
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME     NULL,
        revoked_at   DATETIME     NULL,
        UNIQUE KEY uniq_hash (key_hash),
        KEY idx_user (user_id),
        CONSTRAINT fk_api_keys_user FOREIGN KEY (user_id)
            REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Migration : ajoute la colonne scopes si la table date d'avant les permissions.
    $col = db()->query("SHOW COLUMNS FROM api_keys LIKE 'scopes'")->fetch();
    if (!$col) {
        db()->exec("ALTER TABLE api_keys ADD COLUMN scopes VARCHAR(255) NOT NULL DEFAULT '' AFTER label");
    }
}

/**
 * Génère (auto) une nouvelle clé API pour un utilisateur.
 * Renvoie la clé EN CLAIR (à afficher une seule fois).
 */
function create_api_key(int $userId, string $label, array $scopes): string
{
    ensure_api_keys_table();

    $key    = 'cvk_' . bin2hex(random_bytes(20)); // auto-génération
    $prefix = substr($key, 0, 12);
    $hash   = hash('sha256', $key);
    $scopes = implode(',', sanitize_scopes($scopes));

    $stmt = db()->prepare(
        "INSERT INTO api_keys (user_id, label, scopes, key_prefix, key_hash)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $label, $scopes, $prefix, $hash]);

    return $key;
}

/** Liste les clés (actives d'abord) d'un utilisateur. */
function list_api_keys(int $userId): array
{
    ensure_api_keys_table();
    $stmt = db()->prepare(
        "SELECT * FROM api_keys WHERE user_id = ?
         ORDER BY revoked_at IS NOT NULL, created_at DESC"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Récupère une clé précise appartenant à l'utilisateur (ou null). */
function get_api_key(int $userId, int $keyId): ?array
{
    ensure_api_keys_table();
    $stmt = db()->prepare("SELECT * FROM api_keys WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$keyId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Modifie le nom et les permissions d'une clé (le secret reste inchangé). */
function update_api_key(int $userId, int $keyId, string $label, array $scopes): void
{
    ensure_api_keys_table();
    $scopes = implode(',', sanitize_scopes($scopes));
    $stmt = db()->prepare(
        "UPDATE api_keys SET label = ?, scopes = ? WHERE id = ? AND user_id = ?"
    );
    $stmt->execute([$label, $scopes, $keyId, $userId]);
}

/** Révoque une clé appartenant à l'utilisateur. */
function revoke_api_key(int $userId, int $keyId): void
{
    ensure_api_keys_table();
    $stmt = db()->prepare(
        "UPDATE api_keys SET revoked_at = NOW()
         WHERE id = ? AND user_id = ? AND revoked_at IS NULL"
    );
    $stmt->execute([$keyId, $userId]);
}

/** Transforme la chaîne de scopes stockée en tableau. */
function scopes_to_array(?string $scopes): array
{
    if (!$scopes) {
        return [];
    }
    return array_filter(array_map('trim', explode(',', $scopes)));
}

/**
 * Vérifie une clé API fournie.
 * Renvoie ['user_id' => int, 'scopes' => array] si valide, sinon null.
 */
function verify_api_key(string $key): ?array
{
    ensure_api_keys_table();
    $hash = hash('sha256', $key);
    $stmt = db()->prepare(
        "SELECT user_id, scopes FROM api_keys
         WHERE key_hash = ? AND revoked_at IS NULL LIMIT 1"
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }
    $upd = db()->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE key_hash = ?");
    $upd->execute([$hash]);

    return [
        'user_id' => (int) $row['user_id'],
        'scopes'  => scopes_to_array($row['scopes']),
    ];
}

/** La clé vérifiée possède-t-elle la permission demandée ? */
function key_has_scope(array $verified, string $scope): bool
{
    return in_array($scope, $verified['scopes'] ?? [], true);
}
