-- ============================================================
--  Données de départ — à importer APRÈS schema.sql
-- ============================================================
USE tamagotchi;

-- Joueur de démo (le temps que l'authentification soit en place)
INSERT INTO users (id, username, email, password_hash)
VALUES (1, 'demo', 'demo@tamagotchi.local', '')
ON DUPLICATE KEY UPDATE username = username;

-- Espèces (arbre d'évolution simple)
INSERT INTO species (id, name, sprite_base, evolves_to) VALUES
    (1, 'Blob',    'assets/img/blob.png',    NULL),
    (2, 'Dragon',  'assets/img/dragon.png',  NULL)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Items de boutique
INSERT INTO items (id, name, type, effect, price) VALUES
    (1, 'Pomme',    'food',     'hunger:-30',    5),
    (2, 'Gâteau',   'food',     'hunger:-50',   12),
    (3, 'Balle',    'toy',      'happiness:+20', 8),
    (4, 'Potion',   'medicine', 'energy:+40',   15)
ON DUPLICATE KEY UPDATE name = VALUES(name);
