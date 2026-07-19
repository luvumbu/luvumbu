<?php
/**
 * Outil console pour que l'assistant (Claude) lise et participe à une conversation.
 *
 * Usage :
 *   php tools/claude_chat.php AB123                 → affiche les messages
 *   php tools/claude_chat.php AB123 "mon message"   → envoie un message en tant que "Claude 🤖"
 *   php tools/claude_chat.php AB123 --after=12       → affiche seulement après l'id 12
 */
require __DIR__ . '/../app/bootstrap.php';

const CLAUDE_IP     = 'assistant-claude';   // "IP" virtuelle réservée à l'assistant
const CLAUDE_PSEUDO = 'Claude 🤖';

$code = isset($argv[1]) ? normalize_code($argv[1]) : '';
if ($code === '') {
    fwrite(STDERR, "Usage: php claude_chat.php <CODE> [\"message\"] [--after=ID]\n");
    exit(1);
}

// Récupère la conversation.
$stmt = db()->prepare('SELECT * FROM conversations WHERE code = ?');
$stmt->execute([$code]);
$conv = $stmt->fetch();
if (!$conv) {
    fwrite(STDERR, "Conversation introuvable pour le code : $code\n");
    exit(1);
}
$convId = (int) $conv['id'];

// Sépare les arguments : message éventuel + option --after.
$message = null;
$after   = 0;
for ($i = 2; $i < count($argv); $i++) {
    if (str_starts_with($argv[$i], '--after=')) {
        $after = (int) substr($argv[$i], 8);
    } else {
        $message = $argv[$i];
    }
}

// --- Mode envoi ---
if ($message !== null && $message !== '') {
    // Enregistre le pseudo de l'assistant (une fois).
    $up = db()->prepare(
        'INSERT INTO participants (conversation_id, ip, pseudo) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE pseudo = VALUES(pseudo)'
    );
    $up->execute([$convId, CLAUDE_IP, CLAUDE_PSEUDO]);

    $ins = db()->prepare(
        'INSERT INTO messages (conversation_id, ip, pseudo, content) VALUES (?, ?, ?, ?)'
    );
    $ins->execute([$convId, CLAUDE_IP, CLAUDE_PSEUDO, mb_substr($message, 0, 2000)]);

    echo "✅ Message envoyé dans [{$conv['code']}] : $message\n";
}

// --- Mode lecture (toujours : on affiche le fil après envoi) ---
$q = db()->prepare(
    'SELECT id, pseudo, content, created_at FROM messages
      WHERE conversation_id = ? AND id > ? ORDER BY id ASC LIMIT 200'
);
$q->execute([$convId, $after]);
$rows = $q->fetchAll();

$titre = $conv['title'] !== '' ? $conv['title'] : 'Discussion ' . $conv['code'];
echo "\n=== {$titre}  (code {$conv['code']}) ===\n";
if (!$rows) {
    echo "(aucun message" . ($after ? " après l'id $after" : "") . ")\n";
}
foreach ($rows as $m) {
    $h = date('H:i', strtotime($m['created_at']));
    echo "[#{$m['id']} {$h}] {$m['pseudo']} : {$m['content']}\n";
}
