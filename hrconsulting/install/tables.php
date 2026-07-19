<?php
/* =========================================================
   CRÉATION AUTOMATIQUE DES TABLES (à utiliser une seule fois)
   Ouvre :  https://TON-SITE/hrconsulting/install/tables.php?key=TA_SETUP_KEY
   Crée les tables users / mission / candidature dans la base connectée.
   ⚠️ À SUPPRIMER après usage.
   ========================================================= */

require_once __DIR__ . '/../config/bdd.php';

header('Content-Type: text/plain; charset=utf-8');

// Sécurité : clé requise (SETUP_KEY de config/bdd.php)
if (SETUP_KEY === '' || !hash_equals(SETUP_KEY, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit("Accès refusé. Ajoute ?key=TA_SETUP_KEY à l'URL (voir SETUP_KEY dans config/bdd.php).");
}

if (!isset($bdd) || !($bdd instanceof PDO)) {
    http_response_code(500);
    exit("Pas de connexion à la base. Configure d'abord la connexion via install/.");
}

// SQL des tables (intégré ici pour ne dépendre d'aucun autre fichier)
$SQL = <<<SQL
DROP TABLE IF EXISTS `candidature`;
DROP TABLE IF EXISTS `mission`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_name` VARCHAR(50) NOT NULL,
  `user_email` VARCHAR(100) NOT NULL UNIQUE,
  `user_password` VARCHAR(255) NOT NULL,
  `user_jesuis` ENUM('freelance','recruteur') NOT NULL DEFAULT 'freelance',
  `user_telephone` VARCHAR(20) DEFAULT NULL,
  `user_ville` VARCHAR(50) DEFAULT NULL,
  `user_update` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `mission` (
  `mission_id` INT(11) NOT NULL AUTO_INCREMENT,
  `mission_id_user` INT(11) NOT NULL,
  `mission_titre_mission` VARCHAR(150) NOT NULL,
  `mission_description` TEXT NOT NULL,
  `mission_technologie` VARCHAR(255) DEFAULT NULL,
  `mission_profil` VARCHAR(100) DEFAULT NULL,
  `mission_niveau_etudes` VARCHAR(100) DEFAULT NULL,
  `mission_ville` VARCHAR(100) NOT NULL,
  `mission_type_contrat` VARCHAR(50) DEFAULT NULL,
  `mission_salaire` VARCHAR(50) DEFAULT NULL,
  `mission_date_up` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`mission_id`),
  KEY `idx_user` (`mission_id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `candidature` (
  `candidature_id` INT(11) NOT NULL AUTO_INCREMENT,
  `candidature_id_mission` INT(11) NOT NULL,
  `candidature_id_user` INT(11) NOT NULL,
  `candidature_message` TEXT NOT NULL,
  `candidature_statut` ENUM('en_attente','acceptee','refusee') NOT NULL DEFAULT 'en_attente',
  `candidature_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`candidature_id`),
  UNIQUE KEY `unique_candidature` (`candidature_id_mission`, `candidature_id_user`),
  KEY `idx_mission` (`candidature_id_mission`),
  KEY `idx_user` (`candidature_id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SQL;

// Découpe en instructions individuelles (en retirant les lignes de commentaire)
$lignes = preg_split('/\r?\n/', $SQL);
$propre = [];
foreach ($lignes as $l) {
    if (preg_match('/^\s*--/', $l)) continue;
    $propre[] = $l;
}
$instructions = array_filter(array_map('trim', explode(';', implode("\n", $propre))));

try {
    $n = 0;
    foreach ($instructions as $sql) {
        if ($sql === '') continue;
        $bdd->exec($sql);
        $n++;
    }

    $tables = [];
    foreach ($bdd->query('SHOW TABLES') as $r) {
        $tables[] = array_values($r)[0];
    }

    echo "✅ Tables créées avec succès ($n instructions exécutées).\n\n";
    echo "Tables présentes : " . implode(', ', $tables) . "\n\n";
    echo "Étapes suivantes :\n";
    echo "  1. Va sur le site et INSCRIS-toi (choisis 'recruteur').\n";
    echo "  2. Promeus ton compte en admin : install/../admin/promote_admin.php?key=" . SETUP_KEY . "&email=TON-EMAIL\n";
    echo "  3. Déconnecte-toi / reconnecte-toi.\n\n";
    echo "⚠️ SUPPRIME ce fichier (install/tables.php) maintenant, par sécurité.";
} catch (Exception $e) {
    http_response_code(500);
    echo "❌ Erreur lors de la création des tables :\n" . $e->getMessage();
}
