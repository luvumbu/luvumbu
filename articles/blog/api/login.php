<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Méthode non autorisée', 405);
}

$body = read_json_body();
$email = trim($body['email'] ?? '');
$password = (string)($body['password'] ?? '');

if ($email === '' || $password === '') {
    json_error('Email et mot de passe requis', 400);
}

$stmt = $pdo->prepare('SELECT id, nom, prenom, email, password_hash, is_admin FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_error('Identifiants invalides', 401);
}

$token = api_generate_token((int)$user['id']);

unset($user['password_hash']);
json_response([
    'token' => $token,
    'user'  => $user,
    'expires_in_days' => 30,
]);
