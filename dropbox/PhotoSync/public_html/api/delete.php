<?php
// === Mise à la corbeille de photos (depuis l'app) ===
//   POST delete.php   (X-Auth-Token: <jeton du compte>)
//   Corps : JSON {"ids":[1,2,3]}  OU  formulaire ids[]=1&ids[]=2
//   Réponse : { ok, trashed }
// Les photos sont mises à la CORBEILLE (récupérables 30 j), pas supprimées définitivement.

require __DIR__ . '/../lib/bootstrap.php';
Api::header();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Api::fail('Méthode non autorisée', 405);

$uid = Auth::userIdFromToken();
if ($uid === null) Api::fail('Jeton de compte invalide', 401);

// Récupère les ids depuis le JSON ou le formulaire.
$ids = [];
$raw = file_get_contents('php://input');
$json = $raw !== '' ? json_decode($raw, true) : null;
if (is_array($json) && isset($json['ids']) && is_array($json['ids'])) {
    foreach ($json['ids'] as $v) { $i = (int) $v; if ($i > 0) $ids[] = $i; }
} elseif (isset($_POST['ids']) && is_array($_POST['ids'])) {
    foreach ($_POST['ids'] as $v) { $i = (int) $v; if ($i > 0) $ids[] = $i; }
}

if (!$ids) Api::fail('Aucune photo indiquée', 400);

$n = 0;
foreach (array_unique($ids) as $id) {
    Photos::trash($id, $uid); // scopé au compte : ne touche jamais aux photos d'un autre
    $n++;
}

Api::json(['ok' => true, 'trashed' => $n]);
