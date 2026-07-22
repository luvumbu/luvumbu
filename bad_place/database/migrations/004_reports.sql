-- ============================================================
--  Signalements + tables pivots (motifs, types) + pièces jointes
-- ============================================================

CREATE TABLE reports (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid               CHAR(36) NOT NULL UNIQUE,
    user_id            BIGINT UNSIGNED NULL,
    organization_id    BIGINT UNSIGNED NOT NULL,
    category_id        INT UNSIGNED NOT NULL,

    title              VARCHAR(200) NULL,
    description        TEXT NOT NULL,
    incident_date      DATE NULL,
    incident_time      TIME NULL,

    is_anonymous       TINYINT(1) NOT NULL DEFAULT 0,
    reporter_display   VARCHAR(100) NULL,

    status             ENUM('pending','published','rejected','hidden','removed') NOT NULL DEFAULT 'pending',
    moderation_note    TEXT NULL,

    similar_count      INT UNSIGNED NOT NULL DEFAULT 0,
    not_observed_count INT UNSIGNED NOT NULL DEFAULT 0,
    comments_count     INT UNSIGNED NOT NULL DEFAULT 0,

    ip_hash            CHAR(64) NULL,
    language           VARCHAR(5) NOT NULL DEFAULT 'fr',

    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at       DATETIME NULL,
    deleted_at         DATETIME NULL,

    INDEX idx_report_status  (status),
    INDEX idx_report_org     (organization_id),
    INDEX idx_report_cat     (category_id),
    INDEX idx_report_user    (user_id),
    INDEX idx_report_date    (incident_date),
    INDEX idx_report_created (created_at),
    FULLTEXT KEY ft_report (title, description),
    CONSTRAINT fk_report_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_report_org  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_report_cat  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE report_motifs (
    report_id BIGINT UNSIGNED NOT NULL,
    motif_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (report_id, motif_id),
    CONSTRAINT fk_rm_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_rm_motif  FOREIGN KEY (motif_id) REFERENCES motifs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE report_discrimination_types (
    report_id BIGINT UNSIGNED NOT NULL,
    type_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (report_id, type_id),
    CONSTRAINT fk_rdt_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_rdt_type   FOREIGN KEY (type_id) REFERENCES discrimination_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE report_media (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid          CHAR(36) NOT NULL UNIQUE,
    report_id     BIGINT UNSIGNED NOT NULL,
    type          ENUM('image','video','document') NOT NULL,
    original_name VARCHAR(255) NULL,
    stored_path   VARCHAR(255) NOT NULL,
    thumb_path    VARCHAR(255) NULL,
    mime          VARCHAR(100) NULL,
    size          BIGINT UNSIGNED NULL,
    width         INT NULL,
    height        INT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_media_report (report_id),
    CONSTRAINT fk_media_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
