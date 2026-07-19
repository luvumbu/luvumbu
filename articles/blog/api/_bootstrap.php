<?php
// Bootstrap commun à toutes les routes API.
// - Active CORS (pour app mobile / dev local)
// - Force Content-Type JSON
// - Charge la DB et les helpers du blog
// - Définit json_response() et read_json_body()

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../includes/helpers.php';

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    http_response_code(503);
    echo json_encode(['error' => 'Blog non installé']);
    exit;
}
require_once $configFile;
require_once __DIR__ . '/../includes/db.php';

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400): void {
    json_response(['error' => $message], $status);
}

function read_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

set_exception_handler(function ($e) {
    json_error('Erreur serveur : ' . $e->getMessage(), 500);
});
