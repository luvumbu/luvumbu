<?php

/**
 * Seeder des référentiels (issus du cahier des charges) :
 * groupes de catégories, catégories, motifs, types de discrimination.
 * Idempotent : réexécutable sans créer de doublons.
 *
 * @var \PDO $pdo  (fourni par le contexte d'exécution via Database)
 */

use App\Core\Database;

/** Structure : groupe (icône) => [sous-catégories]. */
$taxonomy = [
    'Commerces' => ['icon' => 'shopping-bag', 'items' => [
        'Magasin de vêtements', 'Supermarché', 'Boutique', 'Bijouterie', 'Pharmacie',
        'Magasin de sport', 'Centre commercial', "Magasin d'électronique", 'Librairie', 'Autre',
    ]],
    'Restaurants' => ['icon' => 'utensils', 'items' => [
        'Restaurant', 'Fast-food', 'Café', 'Bar', 'Salon de thé',
        'Boulangerie', 'Pâtisserie', 'Glacier',
    ]],
    'Hébergement' => ['icon' => 'bed', 'items' => [
        'Hôtel', 'Airbnb', 'Camping', 'Auberge', 'Résidence de tourisme',
    ]],
    'Emploi' => ['icon' => 'briefcase', 'items' => [
        'Entreprise', 'Cabinet de recrutement', "Agence d'intérim", 'Centre de formation', 'Organisme de formation',
    ]],
    'Santé' => ['icon' => 'heart-pulse', 'items' => [
        'Hôpital', 'Clinique', 'Cabinet médical', 'Dentiste', 'Pharmacie', 'Laboratoire',
    ]],
    'Éducation' => ['icon' => 'graduation-cap', 'items' => [
        'École', 'Collège', 'Lycée', 'Université', 'Crèche', 'Centre de formation',
    ]],
    'Services publics' => ['icon' => 'landmark', 'items' => [
        'Préfecture', 'Mairie', 'France Travail', 'CAF', 'CPAM',
        'Commissariat', 'Gendarmerie', 'Tribunal', 'Centre des impôts',
    ]],
    'Transport' => ['icon' => 'train', 'items' => [
        'Gare', 'Métro', 'Bus', 'Taxi', 'VTC', 'Aéroport', 'Compagnie aérienne', 'SNCF',
    ]],
    'Banque / Assurance' => ['icon' => 'wallet', 'items' => [
        'Banque', 'Assurance', 'Organisme de crédit',
    ]],
    'Immobilier' => ['icon' => 'building', 'items' => [
        'Agence immobilière', 'Bailleur social', 'Syndic', 'Résidence étudiante',
    ]],
    'Loisirs' => ['icon' => 'ticket', 'items' => [
        'Salle de sport', 'Piscine', 'Stade', 'Cinéma', 'Théâtre',
        'Parc', 'Musée', 'Salle de concert', 'Discothèque',
    ]],
    'Marques' => ['icon' => 'tag', 'items' => [
        'Marque de vêtements', 'Marque de chaussures', 'Marque automobile', 'Marque de cosmétiques',
        'Marque alimentaire', 'Marque de luxe', 'Marque de téléphonie', 'Autre',
    ]],
    'Services numériques' => ['icon' => 'globe', 'items' => [
        'Site internet', 'Application mobile', 'Réseau social', 'Marketplace',
        'Banque en ligne', 'Plateforme de réservation',
    ]],
    'Clubs et associations' => ['icon' => 'users', 'items' => [
        'Club sportif', 'Association', 'Fédération',
    ]],
    'Événements' => ['icon' => 'calendar', 'items' => [
        'Festival', 'Concert', 'Salon', 'Conférence', 'Manifestation sportive',
    ]],
    'Autres' => ['icon' => 'ellipsis', 'items' => [
        'ONG', 'Fondation', 'Organisation religieuse', 'Syndicat',
        'Parti politique', 'Entreprise privée', 'Autre',
    ]],
];

$groupPos = 0;
foreach ($taxonomy as $groupName => $group) {
    $groupPos++;
    $slug = str_slug($groupName);

    Database::execute(
        'INSERT INTO category_groups (name, slug, icon, position)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), icon = VALUES(icon), position = VALUES(position)',
        [$groupName, $slug, $group['icon'], $groupPos]
    );

    $groupId = (int) Database::selectOne('SELECT id FROM category_groups WHERE slug = ?', [$slug])['id'];

    $pos = 0;
    foreach ($group['items'] as $item) {
        $pos++;
        Database::execute(
            'INSERT INTO categories (group_id, name, slug, position)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), position = VALUES(position)',
            [$groupId, $item, str_slug($item), $pos]
        );
    }
}

// --- Motifs de discrimination ---
$motifs = [
    'Origine', 'Couleur de peau', 'Nationalité', 'Sexe', 'Genre', 'Orientation sexuelle',
    'Religion', 'Handicap', 'Âge', 'Apparence physique', 'Situation familiale',
    'Situation sociale', 'Langue', 'Grossesse', 'État de santé', 'Autre',
];
$pos = 0;
foreach ($motifs as $motif) {
    $pos++;
    Database::execute(
        'INSERT INTO motifs (name, slug, position) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), position = VALUES(position)',
        [$motif, str_slug($motif), $pos]
    );
}

// --- Types de discrimination ---
$types = [
    "Refus d'accès", 'Refus de vente', 'Refus de service', 'Contrôle abusif',
    "Refus d'embauche", 'Refus de logement', 'Harcèlement', 'Insultes', 'Menaces',
    'Violence verbale', 'Violence physique', 'Traitement inégal', 'Autre',
];
$pos = 0;
foreach ($types as $type) {
    $pos++;
    Database::execute(
        'INSERT INTO discrimination_types (name, slug, position) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), position = VALUES(position)',
        [$type, str_slug($type), $pos]
    );
}

$gc = Database::selectOne('SELECT COUNT(*) c FROM category_groups')['c'];
$cc = Database::selectOne('SELECT COUNT(*) c FROM categories')['c'];
$mc = Database::selectOne('SELECT COUNT(*) c FROM motifs')['c'];
$tc = Database::selectOne('SELECT COUNT(*) c FROM discrimination_types')['c'];
echo "    → $gc groupes, $cc catégories, $mc motifs, $tc types de discrimination.\n";
