<?php
// Liste les captures enregistrées par l'app (card-maps-*.png), la plus récente d'abord.
// Sert à peupler le menu déroulant de la vue 3D (3d.html) sans saisie manuelle.

header("Content-Type: application/json");

// Les captures vivent dans le sous-dossier dédié captures/.
$dossier = "captures";
$files = glob(__DIR__ . DIRECTORY_SEPARATOR . $dossier . DIRECTORY_SEPARATOR . "card-maps-*.png") ?: [];

// Tri par date de modification décroissante : la dernière capture arrive en tête.
usort($files, function ($a, $b) {
    return filemtime($b) <=> filemtime($a);
});

$list = array_map(function ($path) use ($dossier) {
    return [
        "filename" => basename($path),           // pour lire le zoom / l'affichage
        "url" => $dossier . "/" . basename($path), // chemin à charger par les pages
        "modified" => date("c", filemtime($path)),
    ];
}, $files);

echo json_encode(["ok" => true, "images" => $list]);
