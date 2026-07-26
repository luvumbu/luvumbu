-- Schéma de la base ATHLE_COMPETITION
-- Compatible MariaDB 10.4+ / MySQL 5.7+
-- Exécution : php bin/setup.php   (ou import du fichier via phpMyAdmin)

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Villes : une ligne par ville distincte, avec ses coordonnées géocodées.
-- C'est la table qui « lie » les compétitions à un point sur la carte.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cities (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name             VARCHAR(160) NOT NULL COMMENT 'Nom tel qu''affiché',
    name_normalized  VARCHAR(160) NOT NULL COMMENT 'Minuscules sans accents, sert au rapprochement',
    country_code     CHAR(2)      NOT NULL DEFAULT 'BE',
    postal_code      VARCHAR(16)  DEFAULT NULL,
    region           VARCHAR(120) DEFAULT NULL COMMENT 'Province / région renvoyée par le géocodeur',
    latitude         DECIMAL(10,7) DEFAULT NULL,
    longitude        DECIMAL(10,7) DEFAULT NULL,
    geocode_status   ENUM('pending','ok','failed','manual') NOT NULL DEFAULT 'pending',
    geocode_provider VARCHAR(40)  DEFAULT NULL,
    geocode_query    VARCHAR(255) DEFAULT NULL COMMENT 'Requête effectivement envoyée au géocodeur',
    geocode_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    geocoded_at      DATETIME     DEFAULT NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_city (name_normalized, country_code),
    KEY idx_geocode_status (geocode_status),
    KEY idx_coords (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Compétitions : rattachées à une ville par clé étrangère.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS competitions (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source        VARCHAR(40)  NOT NULL DEFAULT 'athletisme.app',
    external_id   VARCHAR(64)  DEFAULT NULL COMMENT 'Identifiant chez la source, si disponible',
    fingerprint   CHAR(40)     NOT NULL COMMENT 'sha1(source|date|titre|ville) : dédoublonnage',
    title         VARCHAR(255) NOT NULL,
    city_id       INT UNSIGNED DEFAULT NULL,
    city_raw      VARCHAR(255) DEFAULT NULL COMMENT 'Texte de lieu brut avant normalisation',
    venue         VARCHAR(255) DEFAULT NULL COMMENT 'Stade / complexe si distinct de la ville',
    country_code  CHAR(2)      NOT NULL DEFAULT 'BE',
    start_date    DATE         DEFAULT NULL,
    end_date      DATE         DEFAULT NULL,
    environment   ENUM('in','out','unknown') NOT NULL DEFAULT 'unknown' COMMENT 'in = indoor, out = outdoor',
    categories    VARCHAR(255) DEFAULT NULL,
    events        TEXT         DEFAULT NULL,
    organizer     VARCHAR(255) DEFAULT NULL,
    url           VARCHAR(512) DEFAULT NULL,
    raw           LONGTEXT     DEFAULT NULL COMMENT 'Enregistrement source complet (JSON)',
    first_seen_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_fingerprint (fingerprint),
    KEY idx_city (city_id),
    KEY idx_start_date (start_date),
    KEY idx_environment (environment),
    CONSTRAINT fk_competition_city FOREIGN KEY (city_id)
        REFERENCES cities (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Catalogue des disciplines (épreuves), dérivé de `competitions.events`.
-- Reconstruit par bin/import-details.php et bin/index-disciplines.php : ne rien
-- y saisir à la main, tout y est écrasé à chaque passage.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS disciplines (
    discipline_key VARCHAR(40)  NOT NULL COMMENT 'Code court normalisé : 100m, poids, 4x100m…',
    label          VARCHAR(120) NOT NULL COMMENT 'Intitulé le plus fréquent dans les fiches',
    family         VARCHAR(24)  NOT NULL COMMENT 'sprint, demifond, haies, steeple, relais, saut, lancer, marche, para, autre',
    family_rank    TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre d''affichage de la famille',
    sort_order     SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Distance citée, pour classer 60m avant 100m',
    PRIMARY KEY (discipline_key),
    KEY idx_discipline_family (family_rank, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Quelles disciplines chaque compétition propose : c'est la table qui permet
-- de filtrer le calendrier sur « perche » ou « 800 m ».
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS competition_disciplines (
    competition_id INT UNSIGNED NOT NULL,
    discipline_key VARCHAR(40)  NOT NULL,
    PRIMARY KEY (competition_id, discipline_key),
    KEY idx_link_discipline (discipline_key),
    CONSTRAINT fk_link_competition FOREIGN KEY (competition_id)
        REFERENCES competitions (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_link_discipline FOREIGN KEY (discipline_key)
        REFERENCES disciplines (discipline_key) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Journal des imports : permet de savoir d'où viennent les données.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS import_runs (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source                 VARCHAR(40)  NOT NULL,
    source_file            VARCHAR(255) DEFAULT NULL,
    started_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at            DATETIME     DEFAULT NULL,
    rows_read              INT UNSIGNED NOT NULL DEFAULT 0,
    cities_created         INT UNSIGNED NOT NULL DEFAULT 0,
    competitions_created   INT UNSIGNED NOT NULL DEFAULT 0,
    competitions_updated   INT UNSIGNED NOT NULL DEFAULT 0,
    rows_skipped           INT UNSIGNED NOT NULL DEFAULT 0,
    status                 ENUM('running','ok','error') NOT NULL DEFAULT 'running',
    message                TEXT         DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Alias de villes : corrige les variantes d'écriture d'une même ville
-- (« Gand » / « Gent », « Bruxelles » / « Brussel », fautes de frappe…).
-- L'import consulte cette table avant de créer une nouvelle ville.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS city_aliases (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    alias_normalized VARCHAR(160) NOT NULL,
    country_code    CHAR(2)      NOT NULL DEFAULT 'BE',
    city_id         INT UNSIGNED NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_alias (alias_normalized, country_code),
    KEY idx_alias_city (city_id),
    CONSTRAINT fk_alias_city FOREIGN KEY (city_id)
        REFERENCES cities (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Répertoire local de localités (codes postaux GeoNames).
-- Sert à l'autocomplétion et à la résolution d'adresse SANS appel réseau :
-- Nominatim interdit l'autocomplétion au clavier et limite à 1 requête/seconde.
-- Alimenté par : php bin/import-places.php
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS places (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    country_code    CHAR(2)      NOT NULL,
    postal_code     VARCHAR(16)  NOT NULL,
    name            VARCHAR(160) NOT NULL,
    name_normalized VARCHAR(160) NOT NULL,
    region          VARCHAR(120) DEFAULT NULL COMMENT 'Région / communauté',
    province        VARCHAR(120) DEFAULT NULL,
    latitude        DECIMAL(10,7) NOT NULL,
    longitude       DECIMAL(10,7) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_place (country_code, postal_code, name_normalized),
    KEY idx_place_name (country_code, name_normalized),
    KEY idx_place_postal (country_code, postal_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Vue pratique : compétitions avec les coordonnées de leur ville.
-- ---------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_competitions_geo AS
SELECT
    c.id,
    c.title,
    c.start_date,
    c.end_date,
    c.environment,
    c.categories,
    c.organizer,
    c.url,
    c.venue,
    c.city_raw,
    ci.id          AS city_id,
    ci.name        AS city_name,
    ci.country_code,
    ci.region,
    ci.latitude,
    ci.longitude,
    ci.geocode_status
FROM competitions c
LEFT JOIN cities ci ON ci.id = c.city_id;
