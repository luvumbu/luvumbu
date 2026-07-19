<?php
/**
 * Outil pour que l'assistant (Claude) lise/participe à une conversation
 * hébergée EN LIGNE, via l'API HTTP du site (et non la base locale).
 *
 * Usage :
 *   php tools/claude_remote.php <URL_BASE> <CODE> [--pass=MDP] [--after=ID]
 *   php tools/claude_remote.php <URL_BASE> <CODE> "mon message" [--pass=MDP]
 *
 * Exemple :
 *   php tools/claude_remote.php https://mon-site.com/direct_file AB123
 *   php tools/claude_remote.php https://mon-site.com/direct_file AB123 "Bonjour !" --pass=secret
 */

const CLAUDE_PSEUDO = 'Claude 🤖';

$base = rtrim($argv[1] ?? '', '/');
$code = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $argv[2] ?? ''));
if ($base === '' || $code === '') {
    fwrite(STDERR, "Usage: php claude_remote.php <URL_BASE> <CODE> [\"message\"] [--pass=MDP] [--after=ID]\n");
    exit(1);
}

$message = null;
$pass    = '';
$after   = 0;
for ($i = 3; $i < count($argv); $i++) {
    $a = $argv[$i];
    if (str_starts_with($a, '--pass='))       { $pass  = substr($a, 7); }
    elseif (str_starts_with($a, '--after='))  { $after = (int) substr($a, 8); }
    else                                      { $message = $a; }
}

// Fichier cookie temporaire pour conserver la session (accès aux conv. protégées).
$jar = sys_get_temp_dir() . '/claude_cookies_' . md5($base . $code) . '.txt';

/** Petit helper cURL renvoyant [code_http, corps]. */
function http(string $url, ?array $post, string $jar): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false, // tolère les certificats auto-signés
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($ch);
    if ($body === false) {
        fwrite(STDERR, "Erreur réseau : " . curl_error($ch) . "\n");
        exit(1);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, $body];
}

// 1) Rejoindre la conversation (ouvre la session ; obligatoire si protégée).
http($base . '/index.php', ['action' => 'join', 'code' => $code, 'password' => $pass], $jar);

// 2) Définir le pseudo de l'assistant.
http($base . '/api/pseudo.php', ['code' => $code, 'pseudo' => CLAUDE_PSEUDO], $jar);

// 3) Envoyer un message si demandé.
if ($message !== null && $message !== '') {
    [$st, $body] = http($base . '/api/messages.php', ['code' => $code, 'content' => $message], $jar);
    $res = json_decode($body, true);
    if (!empty($res['ok'])) {
        echo "✅ Message envoyé dans [$code] : $message\n";
    } else {
        echo "❌ Échec envoi : " . ($res['error'] ?? "HTTP $st") . "\n";
    }
}

// 4) Lire le fil.
[$st, $body] = http($base . '/api/messages.php?code=' . urlencode($code) . '&after=' . $after, null, $jar);
$res = json_decode($body, true);

echo "\n=== Conversation $code ($base) ===\n";
if (empty($res['ok'])) {
    echo "❌ Lecture impossible : " . ($res['error'] ?? "HTTP $st") . "\n";
    exit(1);
}
if (empty($res['messages'])) {
    echo "(aucun message" . ($after ? " après l'id $after" : "") . ")\n";
}
foreach ($res['messages'] as $m) {
    echo "[#{$m['id']} {$m['time']}] {$m['pseudo']} : {$m['content']}\n";
}
