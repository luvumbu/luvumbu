<?php
// Reinitialise le mot de passe du compte associe a la cle API fournie.
// Protege par un token API valide (donc par un acces admin deja etabli).
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Méthode non autorisée', 405);
}

$user = api_require_user();

$body     = read_json_body();
$password = (string)($body['new_password'] ?? $_POST['new_password'] ?? '');

if (strlen($password) < 8) {
    json_error('Mot de passe trop court (8 caractères minimum)', 422);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$stmt->execute([$hash, (int)$user['id']]);

json_response([
    'ok'    => true,
    'email' => $user['email'],
    'message' => 'Mot de passe mis à jour. Connecte-toi avec cet email et le nouveau mot de passe.',
]);
