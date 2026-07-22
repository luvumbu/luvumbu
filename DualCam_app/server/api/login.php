<?php
// === Connexion (depuis l'app) ===
//   POST login.php  champs : username, password
//   réponse : { ok, token, username }

require __DIR__ . '/../lib/bootstrap.php';
Api::header();
Auth::ensureSchema();

$username = trim($_POST['username'] ?? '');
$user = Auth::verifyCredentials($username, $_POST['password'] ?? '');
if (!$user) Api::fail('Identifiant ou mot de passe incorrect', 401);

Api::json(['ok' => true, 'token' => $user['api_token'], 'username' => $username]);
