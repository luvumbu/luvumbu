<?php
// Reçoit une image (PNG en base64) et l'enregistre automatiquement
// dans le dossier du projet, avec un nom horodaté. Aucune saisie requise.

header("Content-Type: application/json");

$data = $_POST["image"] ?? "";

if ($data === "") {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Aucune image reçue."]);
    exit;
}

// Retire le préfixe "data:image/png;base64,"
if (preg_match('/^data:image\/png;base64,/', $data)) {
    $data = preg_replace('/^data:image\/png;base64,/', "", $data);
}

$binary = base64_decode($data, true);

if ($binary === false) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Image invalide."]);
    exit;
}

// Le zoom de la carte, inscrit dans le nom : c'est lui qui donne l'échelle de l'image.
// (int) sécurise l'entrée ; sans zoom valide, on n'ajoute rien plutôt qu'un "z0" trompeur.
$zoom = isset($_POST["zoom"]) && is_numeric($_POST["zoom"]) ? (int) $_POST["zoom"] : null;
$prefixeZoom = $zoom !== null ? "z{$zoom}-" : "";

// Dossier dédié aux captures (créé au besoin) — plus rien dans la racine du projet.
$dossier = __DIR__ . DIRECTORY_SEPARATOR . "captures";
if (!is_dir($dossier) && !mkdir($dossier, 0777, true) && !is_dir($dossier)) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Impossible de créer le dossier captures/."]);
    exit;
}

// Nom horodaté : card-maps-z12-AAAA-MM-JJ-HH-MM-SS.png
$filename = "card-maps-" . $prefixeZoom . date("Y-m-d-H-i-s") . ".png";
$path = $dossier . DIRECTORY_SEPARATOR . $filename;

if (file_put_contents($path, $binary) === false) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Écriture impossible."]);
    exit;
}

// Métadonnées (zoom, cadre géo, lieux relevés) : enregistrées dans un .json de même nom.
// On ne garde que du JSON valide, et l'échec ici n'annule pas l'image déjà écrite.
$nbElements = 0;
$meta = $_POST["meta"] ?? "";
if ($meta !== "") {
    $decode = json_decode($meta, true);
    if (is_array($decode)) {
        $base = preg_replace('/\.png$/', '', $filename);
        $jsonPath = $dossier . DIRECTORY_SEPARATOR . $base . ".json";
        file_put_contents($jsonPath, json_encode($decode, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $nbElements = isset($decode["elements"]) ? count($decode["elements"]) : 0;
    }
}

echo json_encode(["ok" => true, "filename" => $filename, "elements" => $nbElements]);
