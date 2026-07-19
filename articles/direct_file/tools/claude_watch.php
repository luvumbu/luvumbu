<?php
/**
 * Veille en direct : interroge l'API du site en ligne toutes les N secondes
 * et S'ARRÊTE dès qu'un NOUVEAU message (pas de Claude) arrive, en l'affichant
 * en JSON. Sert à l'assistant pour répondre en quasi temps réel.
 *
 * Usage :
 *   php tools/claude_watch.php <URL_BASE> <CODE> --after=ID [--pass=MDP]
 *                              [--timeout=540] [--interval=3]
 *
 * Sortie JSON :
 *   {"status":"new","messages":[...],"last":ID}   → nouveaux messages détectés
 *   {"status":"timeout","last":ID}                → rien pendant tout le délai
 */

const CLAUDE_PSEUDO = 'Claude 🤖';

$base = rtrim($argv[1] ?? '', '/');
$code = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $argv[2] ?? ''));
if ($base === '' || $code === '') {
    fwrite(STDERR, "Usage: php claude_watch.php <URL_BASE> <CODE> --after=ID [--pass=MDP] [--timeout=540] [--interval=3]\n");
    exit(1);
}

$after = 0; $pass = ''; $timeout = 540; $interval = 3;
for ($i = 3; $i < count($argv); $i++) {
    $a = $argv[$i];
    if (str_starts_with($a, '--after='))    { $after    = (int) substr($a, 8); }
    elseif (str_starts_with($a, '--pass=')) { $pass     = substr($a, 7); }
    elseif (str_starts_with($a, '--timeout=')) { $timeout  = (int) substr($a, 10); }
    elseif (str_starts_with($a, '--interval=')) { $interval = max(2, (int) substr($a, 11)); }
}

$jar = sys_get_temp_dir() . '/claude_cookies_' . md5($base . $code) . '.txt';

function http(string $url, ?array $post, string $jar): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($ch);
    $status = $body === false ? 0 : curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, $body];
}

// Ouvre la session (utile si la conversation est protégée).
http($base . '/index.php', ['action' => 'join', 'code' => $code, 'password' => $pass], $jar);

$deadline = time() + $timeout;
$lastSeen = $after;

while (time() < $deadline) {
    [$st, $body] = http($base . '/api/messages.php?code=' . urlencode($code) . '&after=' . $lastSeen, null, $jar);
    $res = json_decode((string) $body, true);

    if (!empty($res['ok']) && !empty($res['messages'])) {
        $nouveaux = [];
        foreach ($res['messages'] as $m) {
            if ($m['id'] > $lastSeen) { $lastSeen = $m['id']; }
            if ($m['pseudo'] !== CLAUDE_PSEUDO) {   // ignore mes propres messages
                $nouveaux[] = $m;
            }
        }
        if ($nouveaux) {
            echo json_encode(['status' => 'new', 'messages' => $nouveaux, 'last' => $lastSeen], JSON_UNESCAPED_UNICODE);
            exit(0);
        }
    }
    usleep($interval * 1000000);
}

echo json_encode(['status' => 'timeout', 'last' => $lastSeen], JSON_UNESCAPED_UNICODE);
