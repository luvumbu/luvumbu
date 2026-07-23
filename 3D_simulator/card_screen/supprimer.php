<?php
// Supprime une capture (le PNG et son .json de même nom).
//
// Deux verrous, parce qu'un nom de fichier vient toujours du navigateur et ne se croit pas :
//   1. le nom doit correspondre EXACTEMENT au motif des captures (pas de "..", pas de "/") ;
//   2. le chemin réel obtenu doit rester DANS captures/ — realpath() tranche les liens
//      symboliques et les remontées de dossier qui auraient survécu au premier verrou.

header("Content-Type: application/json");

$nom = $_POST["fichier"] ?? "";

if (!preg_match('/^card-maps-[A-Za-z0-9\-]+\.png$/', $nom)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Nom de fichier refusé."]);
    exit;
}

$dossier = realpath(__DIR__ . DIRECTORY_SEPARATOR . "captures");
$cible = realpath($dossier . DIRECTORY_SEPARATOR . $nom);

if ($dossier === false || $cible === false || strpos($cible, $dossier . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    echo json_encode(["ok" => false, "error" => "Capture introuvable."]);
    exit;
}

$supprimes = [];
if (@unlink($cible)) $supprimes[] = basename($cible);

// Le .json de métadonnées porte le même nom : il part avec l'image, sinon il reste orphelin.
$json = preg_replace('/\.png$/', '.json', $cible);
if (is_file($json) && @unlink($json)) $supprimes[] = basename($json);

if (!$supprimes) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Suppression impossible (droits ?)."]);
    exit;
}

echo json_encode(["ok" => true, "supprimes" => $supprimes]);
