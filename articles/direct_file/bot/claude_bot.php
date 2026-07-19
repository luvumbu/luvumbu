<?php
/**
 * Bot Claude pour direct_file.
 *
 * Lit les nouveaux messages des discussions surveillées et y répond
 * automatiquement via l'API Anthropic, sous une identité réservée.
 *
 * Usage :
 *   php claude_bot.php            → une passe (idéal pour un cron toutes les minutes)
 *   php claude_bot.php --loop     → tourne en continu (sonde toutes les N secondes)
 *   php claude_bot.php --loop=5   → idem, intervalle de 5 secondes
 *
 * Identité : le bot écrit ses messages avec ip = 'BOT' (jamais en collision
 * avec un humain), donc il ne se répond jamais à lui-même.
 *
 * Aucune dépendance : PDO + cURL (standard sur tout hébergeur PHP).
 */

date_default_timezone_set('Europe/Paris');
error_reporting(E_ALL);
ini_set('display_errors', '1');

// ----- Chargement de la config du bot -----
$cfgPath = __DIR__ . '/bot_config.php';
if (!is_file($cfgPath)) {
    fwrite(STDERR, "Config absente : copiez bot_config.sample.php en bot_config.php puis renseignez la clé API.\n");
    exit(1);
}
$BOT = require $cfgPath;
if (empty($BOT['api_key']) || strpos($BOT['api_key'], 'sk-ant-') !== 0) {
    fwrite(STDERR, "Clé API manquante ou invalide dans bot_config.php (doit commencer par sk-ant-).\n");
    exit(1);
}

// ----- Connexion BDD (réutilise les identifiants du site) -----
$dbCfg = @include __DIR__ . '/../config/database.php';
if (!is_array($dbCfg)) {
    $dbCfg = ['host' => '127.0.0.1', 'name' => 'direct_file', 'user' => 'root', 'pass' => '', 'charset' => 'utf8mb4'];
}
$dsn = "mysql:host={$dbCfg['host']};dbname={$dbCfg['name']};charset={$dbCfg['charset']}";
try {
    $pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Connexion BDD impossible : " . $e->getMessage() . "\n");
    exit(1);
}

// ----- Table d'état (mémorise le dernier message traité par discussion) -----
$pdo->exec('
    CREATE TABLE IF NOT EXISTS bot_state (
        conversation_id INT PRIMARY KEY,
        last_id         INT NOT NULL DEFAULT 0,
        updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
');

function log_line(string $msg): void {
    echo '[' . date('H:i:s') . '] ' . $msg . "\n";
}

/**
 * Renvoie les conversations à surveiller (selon la config).
 */
function target_conversations(PDO $pdo, array $BOT): array {
    if (!empty($BOT['codes'])) {
        $in = implode(',', array_fill(0, count($BOT['codes']), '?'));
        $q  = $pdo->prepare("SELECT id, code FROM conversations WHERE code IN ($in)");
        $q->execute($BOT['codes']);
    } else {
        $q = $pdo->query("SELECT id, code FROM conversations WHERE is_open = 1");
    }
    return $q->fetchAll();
}

/**
 * Appelle l'API Anthropic et renvoie le texte de la réponse (ou null).
 */
function ask_claude(array $BOT, array $transcript): ?string {
    $userBlock = "Voici les derniers messages de la discussion :\n\n"
               . implode("\n", $transcript)
               . "\n\nRéponds au(x) dernier(s) message(s).";

    $payload = [
        'model'      => $BOT['model'],
        'max_tokens' => $BOT['max_tokens'] ?? 400,
        'system'     => $BOT['system'],
        'messages'   => [
            ['role' => 'user', 'content' => $userBlock],
        ],
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . $BOT['api_key'],
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        log_line("Erreur cURL API : $err");
        return null;
    }
    $data = json_decode($resp, true);
    if ($http !== 200) {
        $m = $data['error']['message'] ?? $resp;
        log_line("API HTTP $http : $m");
        return null;
    }
    $text = '';
    foreach ($data['content'] ?? [] as $blk) {
        if (($blk['type'] ?? '') === 'text') {
            $text .= $blk['text'];
        }
    }
    $text = trim($text);
    return $text === '' ? null : $text;
}

/**
 * Traite une discussion : répond aux messages non encore vus.
 */
function process_conversation(PDO $pdo, array $BOT, array $conv): void {
    $convId = (int) $conv['id'];

    // Dernier id traité.
    $st = $pdo->prepare('SELECT last_id FROM bot_state WHERE conversation_id = ?');
    $st->execute([$convId]);
    $lastId = (int) ($st->fetchColumn() ?: 0);

    // Première fois : on se cale sur le dernier message existant sans répondre
    // (pour ne pas spammer tout l'historique au démarrage).
    if ($lastId === 0) {
        $max = (int) $pdo->query("SELECT COALESCE(MAX(id),0) FROM messages WHERE conversation_id = $convId")->fetchColumn();
        $pdo->prepare('INSERT INTO bot_state (conversation_id, last_id) VALUES (?, ?)
                       ON DUPLICATE KEY UPDATE last_id = VALUES(last_id)')->execute([$convId, $max]);
        log_line("[{$conv['code']}] Initialisé au message #$max (pas de réponse rétroactive).");
        return;
    }

    // Nouveaux messages, en ignorant ceux du bot.
    $q = $pdo->prepare(
        'SELECT id, pseudo, content, ip FROM messages
          WHERE conversation_id = ? AND id > ? AND ip <> ?
       ORDER BY id ASC'
    );
    $q->execute([$convId, $lastId, $BOT['bot_ip']]);
    $newMsgs = $q->fetchAll();

    // Avance toujours l'état (même messages du bot) pour ne pas boucler.
    $newMax = (int) $pdo->query("SELECT COALESCE(MAX(id),$lastId) FROM messages WHERE conversation_id = $convId")->fetchColumn();

    if (!$newMsgs) {
        if ($newMax > $lastId) {
            $pdo->prepare('UPDATE bot_state SET last_id = ? WHERE conversation_id = ?')->execute([$newMax, $convId]);
        }
        return;
    }

    log_line("[{$conv['code']}] " . count($newMsgs) . " nouveau(x) message(s) → réponse…");

    // Construit l'historique de contexte (N derniers messages).
    $hist = (int) ($BOT['history'] ?? 20);
    $h = $pdo->prepare(
        'SELECT pseudo, content, ip FROM messages
          WHERE conversation_id = ? ORDER BY id DESC LIMIT ?'
    );
    $h->bindValue(1, $convId, PDO::PARAM_INT);
    $h->bindValue(2, $hist, PDO::PARAM_INT);
    $h->execute();
    $rows = array_reverse($h->fetchAll());

    $transcript = [];
    foreach ($rows as $r) {
        $who = ($r['ip'] === $BOT['bot_ip']) ? $BOT['pseudo'] . ' (toi)' : $r['pseudo'];
        $transcript[] = $who . ' : ' . $r['content'];
    }

    $reply = ask_claude($BOT, $transcript);
    if ($reply === null) {
        log_line("[{$conv['code']}] Pas de réponse générée (voir erreur ci-dessus). État NON avancé pour réessayer.");
        return;
    }
    $reply = mb_substr($reply, 0, 2000);

    $ins = $pdo->prepare(
        'INSERT INTO messages (conversation_id, ip, pseudo, content) VALUES (?, ?, ?, ?)'
    );
    $ins->execute([$convId, $BOT['bot_ip'], $BOT['pseudo'], $reply]);

    // État = id du message du bot qu'on vient d'écrire (le plus grand).
    $botMsgId = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE bot_state SET last_id = ? WHERE conversation_id = ?')->execute([$botMsgId, $convId]);
    log_line("[{$conv['code']}] Répondu (msg #$botMsgId).");
}

// ----- Boucle principale -----
$loop = false;
$interval = 4;
foreach ($argv as $a) {
    if ($a === '--loop') { $loop = true; }
    elseif (strpos($a, '--loop=') === 0) { $loop = true; $interval = max(1, (int) substr($a, 7)); }
}

do {
    try {
        foreach (target_conversations($pdo, $BOT) as $conv) {
            process_conversation($pdo, $BOT, $conv);
        }
    } catch (Throwable $e) {
        log_line('Erreur : ' . $e->getMessage());
    }
    if ($loop) { sleep($interval); }
} while ($loop);
