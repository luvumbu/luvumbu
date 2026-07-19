-- ============================================================
--  Migration 001 — Fondations éducatives
--  Ajoute Santé, Connaissance, Points (monnaie) et Niveau aux créatures.
-- ============================================================
USE tamagotchi;

ALTER TABLE pets
    ADD COLUMN health    TINYINT UNSIGNED NOT NULL DEFAULT 100 AFTER energy,
    ADD COLUMN knowledge INT UNSIGNED     NOT NULL DEFAULT 0   AFTER health,
    ADD COLUMN points    INT UNSIGNED     NOT NULL DEFAULT 0   AFTER knowledge,
    ADD COLUMN level     INT UNSIGNED     NOT NULL DEFAULT 1   AFTER points;
