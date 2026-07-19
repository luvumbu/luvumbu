<?php
// Renvoie le compte associe a la cle API fournie.
// Lecture seule : sert a retrouver l'email de connexion et a verifier les droits.
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Méthode non autorisée', 405);
}

$user = api_require_user();

json_response([
    'user' => [
        'id'       => (int)$user['id'],
        'prenom'   => $user['prenom'],
        'nom'      => $user['nom'],
        'email'    => $user['email'],
        'is_admin' => (int)$user['is_admin'],
    ],
    'login_url' => base_url('pages/login.php'),
]);
