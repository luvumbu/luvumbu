-- ============================================================
--  Entités signalées : lieux, entreprises, marques, services.
--  Les signalements s'y rattachent -> permet la carte agrégée,
--  les niveaux d'activité, le droit de réponse, les notifs par marque.
-- ============================================================

CREATE TABLE organizations (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid          CHAR(36) NOT NULL UNIQUE,
    name          VARCHAR(255) NOT NULL,
    slug          VARCHAR(280) NOT NULL,
    type          ENUM('place','company','brand','online_service','other') NOT NULL DEFAULT 'place',
    brand_name    VARCHAR(255) NULL,
    category_id   INT UNSIGNED NULL,

    address       VARCHAR(255) NULL,
    city          VARCHAR(120) NULL,
    postal_code   VARCHAR(20) NULL,
    department    VARCHAR(120) NULL,
    region        VARCHAR(120) NULL,
    country       VARCHAR(100) NOT NULL DEFAULT 'France',
    country_code  CHAR(2) NOT NULL DEFAULT 'FR',
    latitude      DECIMAL(10,7) NULL,
    longitude     DECIMAL(10,7) NULL,
    website       VARCHAR(255) NULL,

    reports_count INT UNSIGNED NOT NULL DEFAULT 0,
    activity_level ENUM('low','medium','high') NOT NULL DEFAULT 'low',
    status        ENUM('active','merged','hidden') NOT NULL DEFAULT 'active',
    merged_into   BIGINT UNSIGNED NULL,

    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_org_city      (city),
    INDEX idx_org_postal    (postal_code),
    INDEX idx_org_region    (region),
    INDEX idx_org_dept      (department),
    INDEX idx_org_geo       (latitude, longitude),
    INDEX idx_org_category  (category_id),
    INDEX idx_org_brand     (brand_name),
    INDEX idx_org_status    (status),
    FULLTEXT KEY ft_org_name (name, brand_name, address),
    CONSTRAINT fk_org_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
