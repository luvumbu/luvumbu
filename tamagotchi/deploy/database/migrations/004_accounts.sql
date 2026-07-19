-- ============================================================
--  Migration 004 — Comptes parents (Google) + profils enfants
--  Un PARENT (users) se connecte avec Google et gère plusieurs
--  ENFANTS (children). Chaque créature (pets) appartient à un enfant.
-- ============================================================

-- Le parent peut venir de Google : on stocke son identifiant Google (sub).
ALTER TABLE users
    ADD COLUMN google_sub VARCHAR(64)  NULL UNIQUE AFTER email,
    ADD COLUMN avatar_url  VARCHAR(255) NULL         AFTER google_sub;

-- Le mot de passe n'est plus obligatoire (connexion via Google).
ALTER TABLE users MODIFY password_hash VARCHAR(255) NULL;

-- Profils enfants rattachés à un parent.
CREATE TABLE IF NOT EXISTS children (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,               -- le parent
    name       VARCHAR(50)  NOT NULL,
    avatar     VARCHAR(16)  NOT NULL DEFAULT '🐣',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Chaque créature appartient à un enfant.
ALTER TABLE pets ADD COLUMN child_id INT UNSIGNED NULL AFTER user_id;
