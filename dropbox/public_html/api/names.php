<?php
// === Liste des noms de fichiers déjà présents POUR CE COMPTE ===
//   GET names.php  (X-Auth-Token: <jeton du compte>)
//   Réponse : { ok, names: ["IMG_001.jpg", ...] }
// Sert à l'app pour ignorer rapidement (par nom) les photos déjà envoyées,
// sans recalculer d'empreinte fichier par fichier.

require __DIR__ . '/../lib/bootstrap.php';
Api::header();

$uid = Auth::requireToken();

$st = Db::pdo()->prepare(
    'SELECT original_name FROM ' . TBL_PHOTOS . ' WHERE user_id = ? AND deleted_at IS NULL'
);
$st->execute([$uid]);

$names = $st->fetchAll(PDO::FETCH_COLUMN);
Api::json(['ok' => true, 'names' => $names]);
