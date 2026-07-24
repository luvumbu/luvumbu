<?php
// Reçoit le canvas 2D (PNG base64) généré depuis echantillon.html
// et l'enregistre dans un dossier dédié : card_screen/canvas2d/.

header("Content-Type: application/json");

$data = $_POST["image"] ?? "";
if ($data === "") {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Aucune image reçue."]);
    exit;
}

if (preg_match('/^data:image\/png;base64,/', $data)) {
    $data = preg_replace('/^data:image\/png;base64,/', "", $data);
}
$binary = base64_decode($data, true);
if ($binary === false) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Image invalide."]);
    exit;
}

// Dossier dédié aux canvas 2D (créé au besoin).
$dossier = __DIR__ . DIRECTORY_SEPARATOR . "canvas2d";
if (!is_dir($dossier) && !mkdir($dossier, 0777, true) && !is_dir($dossier)) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Impossible de créer le dossier canvas2d/."]);
    exit;
}

// Nom : canvas2d-z<zoom>-AAAA-MM-JJ-HH-MM-SS.png (le zoom rappelle l'échelle de la source).
$zoom = isset($_POST["zoom"]) && is_numeric($_POST["zoom"]) ? "z" . (int) $_POST["zoom"] . "-" : "";
$filename = "canvas2d-" . $zoom . date("Y-m-d-H-i-s") . ".png";
$path = $dossier . DIRECTORY_SEPARATOR . $filename;

if (file_put_contents($path, $binary) === false) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Écriture impossible."]);
    exit;
}

echo json_encode(["ok" => true, "filename" => $filename]);
