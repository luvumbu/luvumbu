<?php
/**
 * Module Candidatures : suivi des CV envoyés aux entreprises,
 * statut de réponse et relances programmées. Utilisé par le tableau de bord.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/cv.php';

/** Statuts de réponse possibles (clé technique => libellé). */
function application_statuses(): array
{
    return [
        'en_attente' => 'En attente',
        'positive'   => 'Réponse positive',
        'negative'   => 'Réponse négative',
    ];
}

/** Crée la table des candidatures si besoin (dépend de users et cvs). */
function ensure_applications_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    ensure_cv_table(); // garantit la table cvs (référencée par clé étrangère)
    db()->exec("CREATE TABLE IF NOT EXISTS applications (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id       INT UNSIGNED NOT NULL,
        cv_id         INT UNSIGNED NULL,
        company       VARCHAR(150) NOT NULL,
        sent_at       DATE         NOT NULL,
        status        VARCHAR(20)  NOT NULL DEFAULT 'en_attente',
        followup      TINYINT(1)   NOT NULL DEFAULT 0,
        followup_date DATE         NULL,
        notes         TEXT         NULL,
        created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_user (user_id),
        KEY idx_cv (cv_id),
        CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_app_cv   FOREIGN KEY (cv_id)   REFERENCES cvs(id)   ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Migration : ajoute followup_date sur une table créée avant cette version.
    $hasCol = db()->query("SHOW COLUMNS FROM applications LIKE 'followup_date'")->fetch();
    if (!$hasCol) {
        db()->exec("ALTER TABLE applications ADD COLUMN followup_date DATE NULL AFTER followup");
    }

    // Migration : informations supplémentaires libres (liste { label, value }) en JSON.
    $hasExtra = db()->query("SHOW COLUMNS FROM applications LIKE 'extra_json'")->fetch();
    if (!$hasExtra) {
        db()->exec("ALTER TABLE applications ADD COLUMN extra_json LONGTEXT NULL AFTER notes");
    }

    $done = true;
}

/** Nettoie une liste d'infos supplémentaires { label, value } et renvoie un JSON (ou null). */
function clean_application_extra(array $items): ?string
{
    $out = [];
    foreach ($items as $it) {
        $label = trim((string) ($it['label'] ?? ''));
        $value = trim((string) ($it['value'] ?? ''));
        if ($label !== '' || $value !== '') {
            $out[] = ['label' => $label, 'value' => $value];
        }
    }
    return $out ? json_encode($out, JSON_UNESCAPED_UNICODE) : null;
}

/** Renvoie les infos supplémentaires d'une candidature sous forme de tableau. */
function application_extra(array $row): array
{
    if (empty($row['extra_json'])) {
        return [];
    }
    $p = json_decode((string) $row['extra_json'], true);
    return is_array($p) ? $p : [];
}

/** Ajoute une candidature ; renvoie son id. $extra = liste de ['label'=>, 'value'=>]. */
function create_application(int $userId, ?int $cvId, string $company, string $sentAt, string $notes = '', array $extra = []): int
{
    ensure_applications_table();
    // Sécurité : on n'associe le CV que s'il appartient à l'utilisateur.
    if ($cvId !== null && !get_cv($userId, $cvId)) {
        $cvId = null;
    }
    $extraJson = clean_application_extra($extra);
    $stmt = db()->prepare(
        "INSERT INTO applications (user_id, cv_id, company, sent_at, notes, extra_json)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $cvId, $company, $sentAt, $notes, $extraJson]);
    return (int) db()->lastInsertId();
}

/** Met à jour les informations supplémentaires d'une candidature existante. */
function update_application_extra(int $userId, int $id, array $extra): void
{
    ensure_applications_table();
    $extraJson = clean_application_extra($extra);
    $stmt = db()->prepare("UPDATE applications SET extra_json = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$extraJson, $id, $userId]);
}

/** Liste les candidatures de l'utilisateur (avec le nom du CV associé). */
function list_applications(int $userId): array
{
    ensure_applications_table();
    $stmt = db()->prepare(
        "SELECT a.*, c.full_name AS cv_name
           FROM applications a
           LEFT JOIN cvs c ON c.id = a.cv_id
          WHERE a.user_id = ?
          ORDER BY a.sent_at DESC, a.id DESC"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Statistiques rapides pour le tableau de bord. */
function application_stats(int $userId): array
{
    $today = date('Y-m-d');
    $stats = ['total' => 0, 'en_attente' => 0, 'positive' => 0, 'negative' => 0, 'followup' => 0, 'due' => 0];
    foreach (list_applications($userId) as $a) {
        $stats['total']++;
        if (isset($stats[$a['status']])) {
            $stats[$a['status']]++;
        }
        if ($a['followup']) {
            $stats['followup']++;
            if (!empty($a['followup_date']) && $a['followup_date'] <= $today) {
                $stats['due']++; // relance à faire (date atteinte ou dépassée)
            }
        }
    }
    return $stats;
}

/** Met à jour le statut de réponse d'une candidature. */
function update_application_status(int $userId, int $id, string $status): void
{
    if (!array_key_exists($status, application_statuses())) {
        return; // statut inconnu : on ignore
    }
    $stmt = db()->prepare("UPDATE applications SET status = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$status, $id, $userId]);
}

/**
 * Programme (ou annule) une relance.
 * - $value = true  : relance programmée à la date $date (par défaut aujourd'hui).
 * - $value = false : relance annulée.
 */
function set_application_followup(int $userId, int $id, bool $value, ?string $date = null): void
{
    if ($value) {
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        $stmt = db()->prepare(
            "UPDATE applications SET followup = 1, followup_date = ? WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$date, $id, $userId]);
    } else {
        $stmt = db()->prepare(
            "UPDATE applications SET followup = 0, followup_date = NULL WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$id, $userId]);
    }
}

/** Supprime une candidature. */
function delete_application(int $userId, int $id): void
{
    $stmt = db()->prepare("DELETE FROM applications WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
}
