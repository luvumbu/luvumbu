<?php
// Projets : l'arrangement complet d'une carte (zones retouchées, objets posés, hauteurs,
// réglages) rangé dans projets/<nom>.json. L'image, elle, reste dans captures/ — un projet
// ne fait que la désigner : dupliquer 7 Mo de PNG à chaque enregistrement serait absurde.
//
// Une seule entrée pour les quatre actions (liste, lecture, écriture, suppression) : c'est
// le même dossier, les mêmes vérifications de nom, autant ne pas les écrire quatre fois.

header("Content-Type: application/json");

$dossier = __DIR__ . DIRECTORY_SEPARATOR . "projets";
if (!is_dir($dossier) && !mkdir($dossier, 0777, true) && !is_dir($dossier)) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Impossible de créer projets/."]);
    exit;
}

$action = $_REQUEST["action"] ?? "liste";

// Un nom de projet vient du navigateur : on n'en garde que des caractères sûrs et on borne
// la longueur. Pas de séparateur, pas de point : impossible de sortir du dossier.
function nomSur($brut) {
    $n = trim((string) $brut);
    $n = preg_replace('/[^\p{L}\p{N} _\-]+/u', '', $n);
    $n = preg_replace('/\s+/', ' ', $n);
    return mb_substr(trim($n), 0, 60);
}

if ($action === "liste") {
    $out = [];
    foreach (glob($dossier . DIRECTORY_SEPARATOR . "*.json") ?: [] as $f) {
        $j = json_decode(file_get_contents($f), true);
        $out[] = [
            "nom" => basename($f, ".json"),
            "capture" => is_array($j) && isset($j["capture"]) ? $j["capture"] : null,
            "modifie" => date("c", filemtime($f)),
            "poids" => filesize($f),
        ];
    }
    usort($out, fn($a, $b) => strcmp($b["modifie"], $a["modifie"]));
    echo json_encode(["ok" => true, "projets" => $out]);
    exit;
}

$nom = nomSur($_REQUEST["nom"] ?? "");
if ($nom === "") {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Nom de projet vide ou refusé."]);
    exit;
}
$chemin = $dossier . DIRECTORY_SEPARATOR . $nom . ".json";

if ($action === "lire") {
    if (!is_file($chemin)) {
        http_response_code(404);
        echo json_encode(["ok" => false, "error" => "Projet introuvable."]);
        exit;
    }
    echo json_encode(["ok" => true, "nom" => $nom, "projet" => json_decode(file_get_contents($chemin), true)]);
    exit;
}

if ($action === "supprimer") {
    if (!is_file($chemin) || !@unlink($chemin)) {
        http_response_code(404);
        echo json_encode(["ok" => false, "error" => "Suppression impossible."]);
        exit;
    }
    echo json_encode(["ok" => true, "nom" => $nom]);
    exit;
}

if ($action === "enregistrer") {
    $data = json_decode($_POST["data"] ?? "", true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["ok" => false, "error" => "Contenu de projet invalide."]);
        exit;
    }
    $ok = file_put_contents($chemin, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($ok === false) {
        http_response_code(500);
        echo json_encode(["ok" => false, "error" => "Écriture impossible."]);
        exit;
    }
    echo json_encode(["ok" => true, "nom" => $nom, "poids" => $ok]);
    exit;
}

http_response_code(400);
echo json_encode(["ok" => false, "error" => "Action inconnue."]);
