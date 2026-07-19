<?php
/* =========================================================
   DIAGNOSTIC TEMPORAIRE — À SUPPRIMER APRÈS USAGE
   Ouvre :  https://TON-SITE/diag.php?key=diag-2026
   Affiche la VRAIE erreur de connexion à la base de données.
   ========================================================= */

if (($_GET['key'] ?? '') !== 'diag-2026') {
    http_response_code(403);
    exit('Cle de diagnostic manquante ou invalide.');
}

define('BDD_DIAG', true);   // demande à config/bdd.php de renvoyer l'erreur au lieu de la masquer

$ok = false;
$err = '';
try {
    require __DIR__ . '/config/bdd.php';   // définit $bd_host/$bd_name/$bd_user, ou lève l'erreur
    $ok = true;
} catch (Throwable $e) {
    $err = $e->getMessage();
}

header('Content-Type: text/plain; charset=utf-8');

echo "================ DIAGNOSTIC HR CONSULTING ================\n\n";
echo "PHP version         : " . PHP_VERSION . "\n";
echo "Extension pdo_mysql : " . (extension_loaded('pdo_mysql') ? 'OUI' : '!!! NON (à activer) !!!') . "\n";
echo "HTTP_HOST vu par PHP : " . ($_SERVER['HTTP_HOST'] ?? '(vide)') . "\n";
echo "Environnement détecté: " . (defined('EST_LOCAL') ? (EST_LOCAL ? 'LOCAL (root/hrconsulting)' : 'PRODUCTION (identifiants Hostinger)') : '?') . "\n";
if (isset($bd_name)) {
    echo "Base ciblée         : \"$bd_name\"  sur  \"$bd_host\"  (utilisateur \"$bd_user\")\n";
}
echo "\n---------------------------------------------------------\n";

if ($ok) {
    echo "CONNEXION BASE DE DONNÉES :  ✅  RÉUSSIE\n\n";

    // Lister les tables présentes
    $tables = [];
    foreach ($bdd->query('SHOW TABLES') as $row) {
        $tables[] = array_values($row)[0];
    }
    echo "Tables présentes (" . count($tables) . ") : " . ($tables ? implode(', ', $tables) : 'AUCUNE') . "\n\n";

    $attendues  = ['users', 'mission', 'candidature'];
    $manquantes = array_diff($attendues, $tables);

    if ($manquantes) {
        echo ">>> ❌  TABLES MANQUANTES : " . implode(', ', $manquantes) . "\n\n";
        echo "C'EST LA CAUSE DU PROBLÈME (erreur 500 sur les pages qui lisent la base).\n";
        echo "SOLUTION : importe le fichier sql/install.sql dans la base\n";
        echo "\"$bd_name\" via phpMyAdmin (Hostinger > Bases de données > phpMyAdmin).\n";
    } else {
        echo ">>> ✅  Les 3 tables attendues sont présentes.\n";
        try {
            $bdd->query('SELECT m.*, u.user_name FROM mission m LEFT JOIN users u ON u.user_id = m.mission_id_user ORDER BY m.mission_date_up DESC LIMIT 1')->fetchAll();
            echo ">>> ✅  La requête d'accueil fonctionne : le site devrait s'afficher.\n";
        } catch (Throwable $e) {
            echo ">>> ❌  Erreur sur la requête d'accueil :\n    " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "CONNEXION BASE DE DONNÉES :  ❌  ÉCHEC\n\n";
    echo "Message exact de MySQL :\n  $err\n\n";
    echo "--- Ce que ça veut dire ---\n";
    if (stripos($err, 'Access denied') !== false) {
        echo "→ Le NOM D'UTILISATEUR ou le MOT DE PASSE MySQL est faux.\n";
    } elseif (stripos($err, 'Unknown database') !== false) {
        echo "→ Le NOM DE LA BASE est faux (cette base n'existe pas sur le serveur).\n";
    } elseif (stripos($err, 'refused') !== false || stripos($err, '2002') !== false || stripos($err, 'No such') !== false) {
        echo "→ L'HÔTE est faux (sur Hostinger c'est en général \"localhost\").\n";
    } else {
        echo "→ Voir le message ci-dessus.\n";
    }
    echo "\n--- Comment corriger ---\n";
    echo "Ouvre config/bdd.php, branche 'else' (= production), et mets les 3\n";
    echo "valeurs EXACTES de hPanel Hostinger > Bases de données MySQL :\n";
    echo "  \$bd_name     = \"...\";  // nom de la base\n";
    echo "  \$bd_user     = \"...\";  // nom d'utilisateur\n";
    echo "  \$bd_password = \"...\";  // mot de passe\n";
    echo "Puis ré-uploade config/bdd.php sur le serveur.\n";
}
echo "\n=========================================================\n";
echo "⚠ SUPPRIME ce fichier (diag.php) une fois le problème réglé.\n";
