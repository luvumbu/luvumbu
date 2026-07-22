-- ============================================================
--  RGPD : registre des consentements + demandes d'export/suppression
-- ============================================================

CREATE TABLE consents (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      BIGINT UNSIGNED NULL,
    consent_type VARCHAR(60) NOT NULL,      -- ex: terms, privacy, cookies_analytics
    granted      TINYINT(1) NOT NULL DEFAULT 1,
    version      VARCHAR(20) NULL,          -- version du document accepté
    ip_hash      CHAR(64) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_consent_user (user_id),
    INDEX idx_consent_type (consent_type),
    CONSTRAINT fk_consent_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rgpd_requests (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid         CHAR(36) NOT NULL UNIQUE,
    user_id      BIGINT UNSIGNED NULL,
    email_hash   CHAR(64) NULL,
    type         ENUM('export','delete') NOT NULL,
    status       ENUM('pending','processing','done','rejected') NOT NULL DEFAULT 'pending',
    result_path  VARCHAR(255) NULL,         -- fichier d'export généré
    processed_by BIGINT UNSIGNED NULL,
    processed_at DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rgpd_status (status),
    CONSTRAINT fk_rgpd_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_rgpd_handler FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
