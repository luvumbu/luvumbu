-- ============================================================
--  Commentaires, votes, signalements de contenu abusif
-- ============================================================

CREATE TABLE comments (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid         CHAR(36) NOT NULL UNIQUE,
    report_id    BIGINT UNSIGNED NOT NULL,
    user_id      BIGINT UNSIGNED NULL,
    parent_id    BIGINT UNSIGNED NULL,
    body         TEXT NOT NULL,
    is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
    author_display VARCHAR(100) NULL,
    status       ENUM('published','pending','hidden','removed') NOT NULL DEFAULT 'published',
    ip_hash      CHAR(64) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at   DATETIME NULL,
    INDEX idx_comment_report (report_id),
    INDEX idx_comment_parent (parent_id),
    INDEX idx_comment_status (status),
    CONSTRAINT fk_comment_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_comment_user   FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_comment_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE votes (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id  BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NULL,
    ip_hash    CHAR(64) NOT NULL,
    vote_type  ENUM('similar','not_observed') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vote_user (report_id, user_id),
    UNIQUE KEY uq_vote_ip   (report_id, ip_hash),
    INDEX idx_vote_report (report_id),
    CONSTRAINT fk_vote_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_vote_user   FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE abuse_reports (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reportable_type ENUM('report','comment') NOT NULL,
    reportable_id   BIGINT UNSIGNED NOT NULL,
    reporter_user_id BIGINT UNSIGNED NULL,
    reason          ENUM('defamation','false_info','hate','spam','privacy','other') NOT NULL,
    details         TEXT NULL,
    status          ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
    ip_hash         CHAR(64) NULL,
    handled_by      BIGINT UNSIGNED NULL,
    resolved_at     DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_abuse_target (reportable_type, reportable_id),
    INDEX idx_abuse_status (status),
    CONSTRAINT fk_abuse_reporter FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_abuse_handler  FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
