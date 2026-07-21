<?php
/* ═══════════════════════════════════════════════════════════════════════
   ÉLAN — API : charge / sauvegarde le tableau d'objectifs.
   Données par utilisateur (clé = e-mail SSO), en JSON dans objectifs/data/.
   ═══════════════════════════════════════════════════════════════════════ */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/* Auth SSO (si déployé) : sinon accès « public » (tolérant en dev). */
$user = null;
$ssoClient = __DIR__ . '/../sso/client.php';
if (is_file($ssoClient)) {
    require_once $ssoClient;
    $user = luvumbu_user();
    if (!$user) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'non authentifié']); exit; }
}

$key  = $user ? 'u_' . sha1(strtolower((string)($user['email'] ?? 'x'))) : 'public';
$dir  = __DIR__ . '/data';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$file = $dir . '/' . $key . '.json';

$action = $_GET['action'] ?? '';

if ($action === 'load') {
    $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
    echo json_encode(['ok' => true, 'data' => (is_array($data) ? $data : null)],
                     JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'save') {
    $raw = file_get_contents('php://input');
    if (strlen($raw) > 1024 * 1024) { echo json_encode(['ok' => false, 'error' => 'trop volumineux']); exit; }
    $d = json_decode($raw, true);
    if (!is_array($d) || !isset($d['columns']) || !is_array($d['columns'])) {
        echo json_encode(['ok' => false, 'error' => 'format invalide']); exit;
    }
    // ré-encode proprement (normalise, évite d'écrire du contenu arbitraire non-JSON)
    $clean = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $fp = @fopen($file, 'c+');
    if ($fp) { flock($fp, LOCK_EX); ftruncate($fp, 0); rewind($fp); fwrite($fp, $clean); flock($fp, LOCK_UN); fclose($fp);
        echo json_encode(['ok' => true]); }
    else echo json_encode(['ok' => false, 'error' => 'écriture impossible']);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'action inconnue']);
