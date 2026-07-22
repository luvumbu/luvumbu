<?php
// === Vérifie quelles empreintes existent déjà POUR CE COMPTE ===
//   POST check.php  (X-Auth-Token: <jeton du compte>)
//   Corps JSON : {"hashes":["abc...","def..."]}
//   Réponse    : {"ok":true,"exists":["abc..."]}

require __DIR__ . '/../lib/bootstrap.php';
Api::header();

$uid = Auth::requireToken();

$json = Request::json();
$hashes = (is_array($json) && isset($json['hashes']) && is_array($json['hashes'])) ? $json['hashes'] : [];
if (!$hashes && isset($_POST['hashes']) && is_array($_POST['hashes'])) $hashes = $_POST['hashes'];

$hashes = array_values(array_unique(array_filter($hashes, function ($h) {
    return is_string($h) && preg_match('/^[0-9a-f]{64}$/i', $h);
})));

if (!$hashes) Api::json(['ok' => true, 'exists' => []]);

$params = array_merge([$uid], $hashes);
$placeholders = implode(',', array_fill(0, count($hashes), '?'));
$stmt = Db::pdo()->prepare("SELECT sha256 FROM " . TBL_PHOTOS . " WHERE user_id = ? AND sha256 IN ($placeholders)");
$stmt->execute($params);

$exists = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'sha256');
Api::json(['ok' => true, 'exists' => $exists]);
