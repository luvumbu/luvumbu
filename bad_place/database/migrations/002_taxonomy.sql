-- ============================================================
--  Référentiels : groupes de catégories, catégories,
--  motifs de discrimination, types de discrimination
--  (gérables par l'administration, sans redéploiement)
-- ============================================================

CREATE TABLE category_groups (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(100) NOT NULL,
    slug      VARCHAR(120) NOT NULL UNIQUE,
    icon      VARCHAR(60) NULL,
    position  INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id  INT UNSIGNED NOT NULL,
    name      VARCHAR(120) NOT NULL,
    slug      VARCHAR(140) NOT NULL,
    position  INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_category (group_id, slug),
    CONSTRAINT fk_category_group FOREIGN KEY (group_id) REFERENCES category_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE motifs (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(120) NOT NULL,
    slug      VARCHAR(140) NOT NULL UNIQUE,
    position  INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE discrimination_types (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(120) NOT NULL,
    slug      VARCHAR(140) NOT NULL UNIQUE,
    position  INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
