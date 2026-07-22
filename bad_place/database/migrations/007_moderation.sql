-- ============================================================
--  Modération : historique des actions (audit) + droit de réponse
-- ============================================================

CREATE TABLE moderation_actions (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id    BIGINT UNSIGNED NULL,
    action      VARCHAR(80) NOT NULL,          -- ex: report.approve, comment.hide, user.ban
    target_type VARCHAR(40) NOT NULL,          -- ex: report, comment, user, organization
    target_id   BIGINT UNSIGNED NOT NULL,
    reason      TEXT NULL,
    meta        JSON NULL,
    ip_hash     CHAR(64) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_modact_actor  (actor_id),
    INDEX idx_modact_target (target_type, target_id),
    INDEX idx_modact_action (action),
    CONSTRAINT fk_modact_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contestations (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid           CHAR(36) NOT NULL UNIQUE,
    organization_id BIGINT UNSIGNED NULL,
    report_id      BIGINT UNSIGNED NULL,
    claimant_name  VARCHAR(150) NOT NULL,
    claimant_email VARCHAR(512) NOT NULL,       -- chiffré (AES) via Crypto
    claimant_role  VARCHAR(120) NULL,           -- ex: responsable, service juridique
    message        TEXT NOT NULL,
    evidence_path  VARCHAR(255) NULL,
    status         ENUM('pending','accepted','rejected','published') NOT NULL DEFAULT 'pending',
    response_text  TEXT NULL,
    handled_by     BIGINT UNSIGNED NULL,
    handled_at     DATETIME NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contest_org    (organization_id),
    INDEX idx_contest_report (report_id),
    INDEX idx_contest_status (status),
    CONSTRAINT fk_contest_org    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    CONSTRAINT fk_contest_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE SET NULL,
    CONSTRAINT fk_contest_handler FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
