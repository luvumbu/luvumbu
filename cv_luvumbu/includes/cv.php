<?php
/**
 * Module CV : stockage et opérations sur les CV.
 */

require_once __DIR__ . '/db.php';

/** Crée la table des CV si besoin. */
function ensure_cv_table(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS cvs (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id    INT UNSIGNED NOT NULL,
        full_name  VARCHAR(150) NOT NULL,
        title      VARCHAR(150) NOT NULL DEFAULT '',
        email      VARCHAR(150) NOT NULL DEFAULT '',
        phone      VARCHAR(50)  NOT NULL DEFAULT '',
        summary    TEXT         NULL,
        skills     TEXT         NULL,
        experience TEXT         NULL,
        education  TEXT         NULL,
        profile_json LONGTEXT    NULL,
        created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_user (user_id),
        CONSTRAINT fk_cvs_user FOREIGN KEY (user_id)
            REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Migration : ajoute profile_json aux tables créées avant l'éditeur WYSIWYG.
    $hasCol = db()->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cvs' AND COLUMN_NAME = 'profile_json'"
    )->fetchColumn();
    if (!$hasCol) {
        db()->exec("ALTER TABLE cvs ADD COLUMN profile_json LONGTEXT NULL AFTER education");
    }

    // Migration : ajoute deleted_at pour la corbeille (suppression douce).
    $hasDeleted = db()->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cvs' AND COLUMN_NAME = 'deleted_at'"
    )->fetchColumn();
    if (!$hasDeleted) {
        db()->exec("ALTER TABLE cvs ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
    }

    // Migration : jeton de partage public (lien partageable).
    $hasShare = db()->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cvs' AND COLUMN_NAME = 'share_token'"
    )->fetchColumn();
    if (!$hasShare) {
        db()->exec("ALTER TABLE cvs ADD COLUMN share_token VARCHAR(64) NULL DEFAULT NULL");
    }
}

/** Champs acceptés pour un CV. */
function cv_fields(): array
{
    return ['full_name', 'title', 'email', 'phone', 'summary', 'skills', 'experience', 'education'];
}

/** Crée un CV pour un utilisateur ; renvoie son id. */
function create_cv(int $userId, array $data): int
{
    ensure_cv_table();
    $row = ['user_id' => $userId];
    foreach (cv_fields() as $f) {
        $row[$f] = isset($data[$f]) ? (string) $data[$f] : '';
    }

    $cols = implode(', ', array_keys($row));
    $ph   = implode(', ', array_fill(0, count($row), '?'));
    $stmt = db()->prepare("INSERT INTO cvs ($cols) VALUES ($ph)");
    $stmt->execute(array_values($row));

    return (int) db()->lastInsertId();
}

/** Liste les CV actifs d'un utilisateur (hors corbeille). */
function list_cvs(int $userId): array
{
    ensure_cv_table();
    $stmt = db()->prepare(
        "SELECT * FROM cvs WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Liste les CV de la corbeille d'un utilisateur (les plus récemment supprimés en premier). */
function list_trashed_cvs(int $userId): array
{
    ensure_cv_table();
    $stmt = db()->prepare(
        "SELECT * FROM cvs WHERE user_id = ? AND deleted_at IS NOT NULL ORDER BY deleted_at DESC"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Récupère un CV actif précis de l'utilisateur (ou null). Les CV en corbeille sont ignorés. */
function get_cv(int $userId, int $cvId): ?array
{
    ensure_cv_table();
    $stmt = db()->prepare(
        "SELECT * FROM cvs WHERE id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1"
    );
    $stmt->execute([$cvId, $userId]);
    return $stmt->fetch() ?: null;
}

/** Place un CV dans la corbeille (suppression douce). Renvoie false si introuvable. */
function trash_cv(int $userId, int $cvId): bool
{
    ensure_cv_table();
    $stmt = db()->prepare(
        "UPDATE cvs SET deleted_at = NOW()
         WHERE id = ? AND user_id = ? AND deleted_at IS NULL"
    );
    $stmt->execute([$cvId, $userId]);
    return $stmt->rowCount() > 0;
}

/** Restaure un CV depuis la corbeille. Renvoie false si introuvable. */
function restore_cv(int $userId, int $cvId): bool
{
    ensure_cv_table();
    $stmt = db()->prepare(
        "UPDATE cvs SET deleted_at = NULL
         WHERE id = ? AND user_id = ? AND deleted_at IS NOT NULL"
    );
    $stmt->execute([$cvId, $userId]);
    return $stmt->rowCount() > 0;
}

/** Supprime définitivement un CV de la corbeille. Renvoie false si introuvable. */
function force_delete_cv(int $userId, int $cvId): bool
{
    ensure_cv_table();
    $stmt = db()->prepare(
        "DELETE FROM cvs WHERE id = ? AND user_id = ? AND deleted_at IS NOT NULL"
    );
    $stmt->execute([$cvId, $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * Renvoie le profil riche (tableau associatif) d'un CV, ou null si absent/invalide.
 * Le profil est la source de vérité de l'éditeur WYSIWYG (assets/js/cv-builder.js).
 */
function get_cv_profile(int $userId, int $cvId): ?array
{
    $cv = get_cv($userId, $cvId);
    if (!$cv) return null;
    if (empty($cv['profile_json'])) return null;
    $p = json_decode((string) $cv['profile_json'], true);
    return is_array($p) ? $p : null;
}

/**
 * Construit un profil de départ à partir des champs texte d'un CV existant.
 * Best-effort : pré-remplit prénom/nom et, si possible, une première expérience.
 */
function seed_profile_from_cv(array $cv): array
{
    $name  = trim((string) ($cv['full_name'] ?? ''));
    $parts = preg_split('/\s+/', $name, 2);
    $first = $parts[0] ?? '';
    $last  = $parts[1] ?? '';

    // Expérience : le titre du CV devient le rôle ; le texte « experience » nourrit les puces.
    $expDescriptions = [];
    foreach (preg_split('/\r?\n/', (string) ($cv['experience'] ?? '')) as $line) {
        $line = trim(ltrim($line, "▸•- \t"));
        if ($line !== '') $expDescriptions[] = ['text' => $line];
    }
    $experienceItems = [[
        'role'         => trim((string) ($cv['title'] ?? '')),
        'company'      => '',
        'start'        => '',
        'end'          => '',
        'descriptions' => $expDescriptions,
    ]];

    // Formation : une entrée par ligne non vide.
    $formationItems = [];
    foreach (preg_split('/\r?\n/', (string) ($cv['education'] ?? '')) as $line) {
        $line = trim($line);
        if ($line !== '') $formationItems[] = ['name' => $line, 'descriptions' => []];
    }
    if (!$formationItems) $formationItems = [[]];

    // Apparence par défaut : modèle dc2scale, bleu marine + or (style du CV de référence).
    return [
        'firstName' => $first,
        'lastName'  => $last,
        'headline'  => trim((string) ($cv['title'] ?? '')),
        'summary'   => (string) ($cv['summary'] ?? ''),
        'contact'   => [
            'location' => '',
            'phone'    => (string) ($cv['phone'] ?? ''),
            'email'    => (string) ($cv['email'] ?? ''),
            'permis'   => '',
        ],
        'birthDate' => '', 'birthDisplay' => 'none',
        'photo' => null, 'photoHidden' => true, 'photoSize' => 120,
        'photoPosition' => 'left', 'photoShape' => 'circle',
        'template' => 'dc2scale', 'singlePage' => true, 'freeLayout' => false,
        'canvasBlocks' => [],
        'colors' => ['main' => '#1d2b4d', 'secondary' => '#d4a23c'],
        'dateFormat' => 'year', 'fontScale' => 100,
        'sections' => [
            ['type' => 'experience',    'hidden' => false, 'items' => $experienceItems],
            ['type' => 'formation',     'hidden' => false, 'items' => $formationItems],
            ['type' => 'habilitations', 'hidden' => false, 'items' => [[]]],
            ['type' => 'competences',   'hidden' => false, 'items' => [[]]],
            ['type' => 'logiciels',     'hidden' => false, 'items' => [[]]],
            ['type' => 'langues',       'hidden' => false, 'items' => [[]]],
        ],
    ];
}

/**
 * Enregistre le profil riche d'un CV (propriété vérifiée) et synchronise full_name
 * à partir de firstName/lastName pour rester cohérent avec la liste des CV.
 * Renvoie false si le CV n'appartient pas à l'utilisateur.
 */
function save_cv_profile(int $userId, int $cvId, array $profile): bool
{
    ensure_cv_table();
    $cv = get_cv($userId, $cvId);
    if (!$cv) return false;

    $first = trim((string) ($profile['firstName'] ?? ''));
    $last  = trim((string) ($profile['lastName'] ?? ''));
    $fullName = trim($first . ' ' . $last);
    if ($fullName === '') $fullName = $cv['full_name']; // ne jamais vider le nom

    $json = json_encode($profile, JSON_UNESCAPED_UNICODE);
    $stmt = db()->prepare(
        "UPDATE cvs SET profile_json = ?, full_name = ? WHERE id = ? AND user_id = ?"
    );
    $stmt->execute([$json, $fullName, $cvId, $userId]);
    return true;
}

/**
 * Active le partage public d'un CV et renvoie son jeton (existant ou nouveau).
 * Renvoie null si le CV n'appartient pas à l'utilisateur.
 */
function enable_cv_share(int $userId, int $cvId): ?string
{
    ensure_cv_table();
    $cv = get_cv($userId, $cvId);
    if (!$cv) return null;
    if (!empty($cv['share_token'])) return $cv['share_token'];
    $token = bin2hex(random_bytes(16)); // 32 caractères hexadécimaux
    $stmt = db()->prepare("UPDATE cvs SET share_token = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$token, $cvId, $userId]);
    return $token;
}

/** Désactive le partage public d'un CV (le lien existant cesse de fonctionner). */
function disable_cv_share(int $userId, int $cvId): void
{
    ensure_cv_table();
    $stmt = db()->prepare("UPDATE cvs SET share_token = NULL WHERE id = ? AND user_id = ?");
    $stmt->execute([$cvId, $userId]);
}

/** Récupère un CV par son jeton de partage public (hors corbeille), ou null. */
function get_cv_by_share_token(string $token): ?array
{
    ensure_cv_table();
    if ($token === '') return null;
    $stmt = db()->prepare("SELECT * FROM cvs WHERE share_token = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

/** Profil riche depuis une ligne CV (rendu public, sans userId) ; amorce si absent. */
function profile_from_cv_row(array $cv): array
{
    if (!empty($cv['profile_json'])) {
        $p = json_decode((string) $cv['profile_json'], true);
        if (is_array($p)) return $p;
    }
    return seed_profile_from_cv($cv);
}

/** URL publique d'un CV à partir de son jeton. */
function cv_public_url(string $token): string
{
    return app_base_url() . '/cv_public.php?token=' . urlencode($token);
}
