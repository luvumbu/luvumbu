<?php
// Envoi d'UN article (déjà créé en local) vers le serveur distant, en un clic.
// Réservé à l'auteur de l'article ou à un admin. Mode Fusion (non destructif).
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

function jr(array $arr, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jr(['ok' => false, 'error' => 'Méthode non autorisée'], 405);
if (!csrf_check($_POST['csrf'] ?? ''))     jr(['ok' => false, 'error' => 'Jeton invalide, recharge la page.'], 403);

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jr(['ok' => false, 'error' => 'Identifiant manquant.'], 400);

$stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
$stmt->execute([$id]);
$article = $stmt->fetch();
if (!$article) jr(['ok' => false, 'error' => 'Article introuvable.'], 404);

$user = current_user();
if ((int)$article['user_id'] !== (int)$user['id'] && !is_admin()) {
    jr(['ok' => false, 'error' => 'Tu ne peux envoyer que tes propres articles.'], 403);
}

// Clé : on prend celle saisie dans l'input de la page article si fournie (et on la
// mémorise), sinon on retombe sur la clé enregistrée dans les réglages.
$remoteUrl = trim((string)get_setting('sync_remote_url', 'https://blog.mariondelval.com/api/sync_receive.php'));
$token     = trim((string)($_POST['token'] ?? ''));
if ($token !== '') {
    set_setting('sync_remote_key', $token); // mémorisé pour les prochains envois
} else {
    $token = trim((string)get_setting('sync_remote_key', ''));
}
if ($token === '') {
    jr(['ok' => false, 'error' => "Aucune clé : colle une clé d'autorisation (générée sur le serveur) dans le champ à côté du bouton."], 400);
}

// On rassemble l'article + sa chaîne de parents (pour respecter la hiérarchie),
// les auteurs concernés et les images. (Pas les commentaires : on pousse l'article.)
$chain = [];
$seen  = [];
$cur   = $article;
while ($cur && empty($seen[$cur['id']])) {
    $seen[$cur['id']] = true;
    $chain[] = $cur;
    if (!empty($cur['parent_id'])) {
        $p = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
        $p->execute([(int)$cur['parent_id']]);
        $cur = $p->fetch() ?: null;
    } else {
        $cur = null;
    }
}
$chain      = array_reverse($chain); // racine d'abord, article cible en dernier
$articleIds = array_map(fn($a) => (int)$a['id'], $chain);
$userIds    = array_values(array_unique(array_map(fn($a) => (int)$a['user_id'], $chain)));

$inU  = implode(',', array_fill(0, count($userIds), '?'));
$usersRows = $pdo->prepare("SELECT * FROM users WHERE id IN ($inU)");
$usersRows->execute($userIds);
$usersRows = $usersRows->fetchAll();

$inA = implode(',', array_fill(0, count($articleIds), '?'));
$imgRows = $pdo->prepare("SELECT * FROM article_images WHERE article_id IN ($inA)");
$imgRows->execute($articleIds);
$imgRows = $imgRows->fetchAll();

$data = [
    '_meta'          => ['exported_at' => date('c'), 'version' => 1, 'single_article' => $id],
    'users'          => $usersRows,
    'articles'       => $chain,
    'article_images' => $imgRows,
];

// Fichiers images de l'article (couverture + galerie).
$relPaths = [];
foreach ($chain as $a) {
    if (!empty($a['image']) && strpos($a['image'], 'uploads/') === 0) $relPaths[] = $a['image'];
}
foreach ($imgRows as $g) {
    if (!empty($g['path']) && strpos($g['path'], 'uploads/') === 0) $relPaths[] = $g['path'];
}
$relPaths = array_values(array_unique($relPaths));

@set_time_limit(0);
$zipFile = tempnam(sys_get_temp_dir(), 'artpush');
try {
    if (!class_exists('ZipArchive')) jr(['ok' => false, 'error' => 'Extension ZipArchive requise.'], 500);
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        jr(['ok' => false, 'error' => 'Impossible de créer le payload.'], 500);
    }
    $zip->addFromString('data.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $nbImg = 0;
    foreach ($relPaths as $rel) {
        $full = __DIR__ . '/../' . $rel;
        if (is_file($full)) { $zip->addFile($full, $rel); $nbImg++; }
    }
    $zip->close();

    // Petit helper : POST le payload au serveur en mode upsert (dry-run ou réel).
    $send = function (bool $dry) use ($remoteUrl, $token, $zipFile, $nbImg) {
        $ch = curl_init($remoteUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT        => 180,
            // Cible = ton propre serveur ; on ne bloque pas sur le SSL local (XAMPP sans bundle CA).
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_POSTFIELDS     => [
                'token'           => $token,
                'mode'            => 'upsert', // ajoute OU met à jour par ID
                'include_db'      => '1',
                'include_uploads' => $nbImg > 0 ? '1' : '0',
                'dry_run'         => $dry ? '1' : '0',
                'payload'         => new CURLFile($zipFile, 'application/zip', 'payload.zip'),
            ],
        ]);
        $r = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return [$r, $code, $err];
    };

    // 1) Dry-run de sécurité : on vérifie que le serveur connaît bien le mode "upsert".
    //    (Un vieux serveur traiterait "upsert" comme "miroir" = il EFFACERAIT toute la base.)
    [$r, $code, $err] = $send(true);
    if ($err) jr(['ok' => false, 'error' => "Erreur réseau : $err"], 502);
    $resp = json_decode((string)$r, true);
    if (!($code === 200 && is_array($resp) && !empty($resp['ok']))) {
        $msg = is_array($resp) && isset($resp['error']) ? $resp['error'] : ('Réponse inattendue (HTTP ' . $code . ')');
        jr(['ok' => false, 'error' => $msg], 502);
    }
    if (($resp['mode'] ?? '') !== 'upsert') {
        jr(['ok' => false, 'error' =>
            "Le serveur distant n'est pas à jour : il ne connaît pas le mode « mise à jour ». "
            . "Déploie d'abord api/sync_receive.php + includes/sync_dump.php sur la prod. "
            . "(Envoi bloqué par sécurité : sinon toute la base serait écrasée.)"], 409);
    }

    // 2) Envoi réel (upsert) : ajoute ou met à jour l'article par ID.
    [$r, $code, $err] = $send(false);
    if ($err) jr(['ok' => false, 'error' => "Erreur réseau : $err"], 502);
    $resp = json_decode((string)$r, true);
    if ($code === 200 && is_array($resp) && !empty($resp['ok'])) {
        $art     = $resp['summary']['db']['articles'] ?? [];
        $added   = (int)($art['added']   ?? 0);
        $updated = (int)($art['updated'] ?? 0);
        $verb = $updated > 0 ? 'mis à jour' : ($added > 0 ? 'ajouté' : 'déjà à jour');
        jr([
            'ok'      => true,
            'message' => "Article {$verb} sur le serveur ({$added} ajouté, {$updated} mis à jour, images : {$nbImg}).",
            'summary' => $resp['summary'] ?? null,
        ]);
    }
    $err2 = is_array($resp) && isset($resp['error']) ? $resp['error'] : ('Réponse inattendue (HTTP ' . $code . ')');
    jr(['ok' => false, 'error' => $err2], 502);
} finally {
    @unlink($zipFile);
}
