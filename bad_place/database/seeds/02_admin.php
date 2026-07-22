<?php

/**
 * Crée un compte administrateur initial si aucun n'existe.
 * Identifiants par défaut (à changer immédiatement en production) :
 *   email    : admin@badplace.local
 *   password : Admin1234!
 */

use App\Core\Database;

$email = 'admin@badplace.local';
$existing = Database::selectOne('SELECT id FROM users WHERE email = ?', [$email]);

if ($existing) {
    echo "    → Administrateur déjà présent ($email).\n";
    return;
}

$hash = password_hash('Admin1234!', PASSWORD_DEFAULT);

Database::execute(
    'INSERT INTO users (uuid, email, email_verified_at, password_hash, display_name, role, status)
     VALUES (?, ?, NOW(), ?, ?, ?, ?)',
    [str_uuid(), $email, $hash, 'Administrateur', 'admin', 'active']
);

echo "    → Administrateur créé : $email / Admin1234!  (⚠ à modifier)\n";
