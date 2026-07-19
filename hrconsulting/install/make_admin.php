<?php
/* =========================================================
   CRÉATION D'UN COMPTE ADMIN (à utiliser une seule fois)
   Ouvre : https://TON-SITE/hrconsulting/install/make_admin.php?key=TA_SETUP_KEY
   Crée (ou met à jour) un compte administrateur avec l'identifiant
   et le mot de passe définis ci-dessous.
   ⚠️ À SUPPRIMER après usage.
   ========================================================= */

require_once __DIR__ . '/../config/bdd.php';
header('Content-Type: text/plain; charset=utf-8');

if (SETUP_KEY === '' || !hash_equals(SETUP_KEY, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit("Accès refusé. Ajoute ?key=TA_SETUP_KEY à l'URL.");
}
if (!isset($bdd) || !($bdd instanceof PDO)) {
    http_response_code(500);
    exit("Pas de connexion à la base.");
}

// Identifiants de connexion au SITE (ce que tu tapes dans le formulaire)
$login = 'u489596434_hr';
$pass  = 'v3p9r3e@59A';

$hash = password_hash($pass, PASSWORD_DEFAULT);

$q = $bdd->prepare('SELECT user_id FROM users WHERE user_email = ?');
$q->execute([$login]);
if ($q->fetch()) {
    $bdd->prepare('UPDATE users SET user_password = ?, user_is_admin = 1, user_jesuis = ? WHERE user_email = ?')
        ->execute([$hash, 'recruteur', $login]);
    $action = 'mis à jour';
} else {
    $bdd->prepare('INSERT INTO users(user_name, user_email, user_password, user_jesuis, user_is_admin) VALUES(?,?,?,?,1)')
        ->execute(['Admin', $login, $hash, 'recruteur']);
    $action = 'créé';
}

echo "✅ Compte admin $action.\n\n";
echo "POUR TE CONNECTER SUR LE SITE (bouton Admin) :\n";
echo "  Case « Adresse email »  : $login\n";
echo "  Case « Mot de passe »   : $pass\n\n";
echo "⚠️ SUPPRIME ce fichier (install/make_admin.php) après usage.";
