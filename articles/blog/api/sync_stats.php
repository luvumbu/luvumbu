<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/sync_keys.php';
require_once __DIR__ . '/../includes/sync_dump.php';

// Endpoint read-only : renvoie les stats du serveur si la cle de sync fournie
// est valide (sans la consommer). Sert au panel sync_push local pour afficher
// la diff local <-> distant avant d'envoyer.

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    json_error('Methode non autorisee', 405);
}

$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
if (!sync_key_check($token)) {
    json_error('Cle invalide, expiree ou deja utilisee', 403);
}

$stats = [];
foreach (SYNC_TABLES as $t) {
    try {
        $stats[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    } catch (Throwable $e) {
        $stats[$t] = null;
    }
}

$uploadsDir   = __DIR__ . '/../uploads';
$uploadsCount = 0;
$uploadsBytes = 0;
if (is_dir($uploadsDir)) {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iter as $f) {
        if ($f->isFile() && $f->getFilename() !== '.gitkeep') {
            $uploadsCount++;
            $uploadsBytes += $f->getSize();
        }
    }
}

json_response([
    'ok'          => true,
    'tables'      => $stats,
    'uploads'     => ['count' => $uploadsCount, 'bytes' => $uploadsBytes],
    'generated_at'=> date('c'),
]);
