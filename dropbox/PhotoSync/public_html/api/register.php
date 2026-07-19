<?php
// === Création de compte (depuis l'app) ===
// Protégé par le MOT DE PASSE DE LA BASE DE DONNÉES (DB_PASS) : seul celui qui connaît
// le mot de passe du serveur peut créer un compte.
//   POST register.php   (X-Auth-Token: <mot de passe BDD>)
//   champs : username, password
//   réponse : { ok, token, username }

require __DIR__ . '/../lib/bootstrap.php';
Api::header();

// Porte d'entrée : code d'inscription = mot de passe de la base.
$invite = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? ($_POST['invite'] ?? '');
if (DB_PASS === '' || !is_string($invite) || !hash_equals(DB_PASS, $invite)) {
    Api::fail("Code d'inscription invalide (mot de passe du serveur)", 401);
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$res = Auth::createAccount($username, is_string($password) ? $password : '');
if (!$res['ok']) {
    Api::fail($res['error'], $res['code']);
}

Api::json(['ok' => true, 'token' => $res['token'], 'username' => $res['username']]);
