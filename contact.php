<?php
/* ═══════════════════════════════════════════════════════════
   FORMULAIRE DE CONTACT — reçoit le message, l'enregistre
   (journal protégé) et l'envoie par email via bkMail (BOKONZI).
   Répond en JSON. Autonome.
   ═══════════════════════════════════════════════════════════ */
header('Content-Type: application/json; charset=utf-8');

$CFG = require __DIR__ . '/config/portfolio.php';
$to  = $CFG['identite']['email'] ?? '';

function out($ok, $msg) { echo json_encode(['ok' => $ok, 'msg' => $msg], JSON_UNESCAPED_UNICODE); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, 'Méthode non autorisée.');

/* Entrée : JSON ou form-data */
$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) $in = $_POST;

$nom     = trim((string)($in['nom'] ?? ''));
$email   = trim((string)($in['email'] ?? ''));
$message = trim((string)($in['message'] ?? ''));
$hp      = trim((string)($in['website'] ?? ''));   // honeypot

/* Anti-spam : si le honeypot est rempli → on fait semblant d'accepter (bot) */
if ($hp !== '') out(true, 'Message envoyé.');

/* Validation */
if ($nom === '' || mb_strlen($nom) > 100)                 out(false, 'Merci d\'indiquer votre nom.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL))           out(false, 'Adresse email invalide.');
if (mb_strlen($message) < 5)                              out(false, 'Votre message est trop court.');
if (mb_strlen($message) > 5000)                           out(false, 'Message trop long (5000 caractères max).');

$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

/* Rate limit simple : 5 messages / heure / IP */
$logFile = __DIR__ . '/config/.contact_log.php';
if (is_file($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES) ?: [];
    $recent = 0; $since = time() - 3600;
    foreach ($lines as $ln) {
        if ($ln === '' || $ln[0] === '<') continue;         // saute le garde <?php die();
        $e = json_decode($ln, true);
        if (is_array($e) && ($e['ip'] ?? '') === $ip && strtotime($e['date'] ?? '') > $since) $recent++;
    }
    if ($recent >= 5) out(false, 'Trop de messages envoyés. Réessayez dans une heure.');
}

/* Enregistrement (journal protégé par un garde PHP die) */
if (!is_file($logFile)) @file_put_contents($logFile, "<?php die(); ?>\n");
$entry = ['date' => date('c'), 'ip' => $ip, 'nom' => $nom, 'email' => $email, 'message' => $message];
@file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

/* Envoi email via bkMail (BOKONZI) — fonctionne en prod (SMTP) ; en local ça peut échouer */
$sent = false;
$mailer = dirname(__DIR__) . '/core/mailer.php';
if ($to !== '' && is_file($mailer)) {
    try {
        require_once $mailer;
        if (function_exists('bkMail')) {
            $subject = 'Portfolio — nouveau message de ' . $nom;
            $body = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#222">'
                  . '<h2 style="color:#6c5ce7">📬 Nouveau message (portfolio)</h2>'
                  . '<p><b>Nom :</b> ' . htmlspecialchars($nom) . '</p>'
                  . '<p><b>Email :</b> <a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a></p>'
                  . '<p><b>Message :</b></p><p style="background:#f4f2ff;padding:14px;border-radius:8px;white-space:pre-wrap">'
                  . htmlspecialchars($message) . '</p>'
                  . '<hr><p style="color:#888;font-size:12px">Envoyé depuis le formulaire de contact du portfolio.</p></div>';
            $sent = (bool) bkMail($to, $subject, $body, $email);   // Reply-To = expéditeur
        }
    } catch (\Throwable $e) { $sent = false; }
}

/* On confirme toujours : le message est sauvegardé (l'email peut échouer en local) */
out(true, 'Merci ' . $nom . ' ! Votre message a bien été envoyé. Je vous réponds vite.');
