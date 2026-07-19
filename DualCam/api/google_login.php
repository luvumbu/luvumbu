<?php
// === Connexion via Google (depuis l'app) ===
//   POST google_login.php   champ : id_token (jeton d'identité Google)
//   réponse : { ok, token, username }
// Le serveur vérifie le jeton auprès de Google, retrouve ou crée le compte lié,
// et renvoie le jeton interne de l'app (utilisé ensuite par tous les autres appels).

require __DIR__ . '/../lib/bootstrap.php';
Api::header();

$idToken = $_POST['id_token'] ?? ($_POST['credential'] ?? '');
if (!is_string($idToken) || $idToken === '') {
    Api::fail('Jeton Google manquant', 400);
}

try {
    $res = Auth::loginWithGoogle($idToken);
} catch (Throwable $e) {
    Api::fail($e->getMessage(), 500);
}

if (empty($res['ok'])) {
    Api::fail($res['error'], $res['code'] ?? 401);
}

Api::json(['ok' => true, 'token' => $res['token'], 'username' => $res['username']]);
