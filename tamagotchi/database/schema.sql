-- ============================================================
--  Tamagotchi — schéma de base de données
--  À importer dans phpMyAdmin ou : mysql -u root tamagotchi < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS tamagotchi
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tamagotchi;

-- ---------- Joueurs ----------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- Catalogue d'espèces ----------
CREATE TABLE IF NOT EXISTS species (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(50) NOT NULL,
    sprite_base  VARCHAR(100) NOT NULL,          -- chemin vers l'image
    evolves_to   INT UNSIGNED NULL,              -- espèce suivante (arbre d'évolution)
    FOREIGN KEY (evolves_to) REFERENCES species(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Créatures ----------
CREATE TABLE IF NOT EXISTS pets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    species_id  INT UNSIGNED NOT NULL,
    name        VARCHAR(50) NOT NULL,
    hunger      TINYINT UNSIGNED NOT NULL DEFAULT 0,     -- 0 = rassasié
    happiness   TINYINT UNSIGNED NOT NULL DEFAULT 100,
    energy      TINYINT UNSIGNED NOT NULL DEFAULT 100,
    stage       ENUM('egg','baby','child','teen','adult') NOT NULL DEFAULT 'egg',
    age_hours   INT UNSIGNED NOT NULL DEFAULT 0,
    is_sleeping TINYINT(1) NOT NULL DEFAULT 0,
    is_alive    TINYINT(1) NOT NULL DEFAULT 1,
    last_update DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  -- pour calculer le temps écoulé
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (species_id) REFERENCES species(id)
) ENGINE=InnoDB;

-- ---------- Items (boutique) ----------
CREATE TABLE IF NOT EXISTS items (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name    VARCHAR(50) NOT NULL,
    type    ENUM('food','toy','medicine') NOT NULL,
    effect  VARCHAR(50) NOT NULL,          -- ex: "hunger:-40"
    price   INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ---------- Inventaire ----------
CREATE TABLE IF NOT EXISTS inventory (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id  INT UNSIGNED NOT NULL,
    item_id  INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Journal des actions (debug / stats) ----------
CREATE TABLE IF NOT EXISTS actions_log (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pet_id     INT UNSIGNED NOT NULL,
    action     VARCHAR(30) NOT NULL,       -- feed, play, sleep...
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
) ENGINE=InnoDB;
