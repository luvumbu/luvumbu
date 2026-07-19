-- Table d'index des photos reçues.
-- À importer une fois dans phpMyAdmin (ou via la ligne de commande mysql).

CREATE TABLE IF NOT EXISTS photos (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sha256        CHAR(64)        NOT NULL UNIQUE,        -- empreinte du fichier (dédoublonnage)
    original_name VARCHAR(255)    NOT NULL,               -- nom d'origine côté téléphone
    stored_path   VARCHAR(512)    NOT NULL,               -- chemin relatif dans /uploads
    size_bytes    BIGINT UNSIGNED NOT NULL,
    taken_at      DATETIME        NULL,                   -- date de prise de vue si connue
    uploaded_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at    DATETIME        NULL DEFAULT NULL,      -- non NULL = en corbeille
    INDEX idx_taken (taken_at),
    INDEX idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
