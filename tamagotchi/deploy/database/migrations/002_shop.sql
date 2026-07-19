-- ============================================================
--  Migration 002 — Boutique & nutrition
--  Aliments structurés (effets détaillés) + journal alimentaire pour l'équilibre.
-- ============================================================
-- Colonnes détaillées sur les items (au lieu d'une chaîne "effect" figée)
ALTER TABLE items
    ADD COLUMN emoji     VARCHAR(8)  NOT NULL DEFAULT '🍽️' AFTER name,
    ADD COLUMN category  VARCHAR(20) NOT NULL DEFAULT 'autre' AFTER type,
    ADD COLUMN d_hunger  INT NOT NULL DEFAULT 0 AFTER category,  -- effet sur la faim (négatif = rassasie)
    ADD COLUMN d_energy  INT NOT NULL DEFAULT 0 AFTER d_hunger,
    ADD COLUMN d_health  INT NOT NULL DEFAULT 0 AFTER d_energy,
    ADD COLUMN d_happy   INT NOT NULL DEFAULT 0 AFTER d_health;

-- Journal des repas : sert à détecter les déséquilibres (trop de sucre...)
CREATE TABLE IF NOT EXISTS feed_log (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pet_id     INT UNSIGNED NOT NULL,
    category   VARCHAR(20)  NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- On repart d'un catalogue d'aliments propre
DELETE FROM inventory;
DELETE FROM items;

INSERT INTO items (id, name, emoji, type, category, effect, price, d_hunger, d_energy, d_health, d_happy) VALUES
    (1, 'Pomme',   '🍎', 'food', 'fruit',    'faim/énergie', 5,  -15, 10, 3, 2),
    (2, 'Banane',  '🍌', 'food', 'fruit',    'faim/énergie', 10, -25, 20, 3, 3),
    (3, 'Carotte', '🥕', 'food', 'legume',   'santé',        8,  -20, 12, 6, 1),
    (4, 'Poisson', '🐟', 'food', 'proteine', 'santé/force',  15, -30, 18, 8, 3),
    (5, 'Gâteau',  '🍰', 'food', 'sucre',    'gourmandise',  30, -30, 30, -5, 10),
    (6, 'Bonbon',  '🍬', 'food', 'sucre',    'gourmandise',  12, -10, 15, -6, 8);
