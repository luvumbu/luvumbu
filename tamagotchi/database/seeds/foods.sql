-- Rechargement des aliments — À IMPORTER en UTF-8 :
--   mysql -u root --default-character-set=utf8mb4 tamagotchi < foods.sql
SET NAMES utf8mb4;
USE tamagotchi;

DELETE FROM feed_log;
DELETE FROM inventory;
DELETE FROM items;

INSERT INTO items (id, name, emoji, type, category, effect, price, d_hunger, d_energy, d_health, d_happy) VALUES
    (1, 'Pomme',   '🍎', 'food', 'fruit',    'faim/énergie', 5,  -15, 10, 3, 2),
    (2, 'Banane',  '🍌', 'food', 'fruit',    'faim/énergie', 10, -25, 20, 3, 3),
    (3, 'Carotte', '🥕', 'food', 'legume',   'santé',        8,  -20, 12, 6, 1),
    (4, 'Poisson', '🐟', 'food', 'proteine', 'santé/force',  15, -30, 18, 8, 3),
    (5, 'Gâteau',  '🍰', 'food', 'sucre',    'gourmandise',  30, -30, 30, -5, 10),
    (6, 'Bonbon',  '🍬', 'food', 'sucre',    'gourmandise',  12, -10, 15, -6, 8);
