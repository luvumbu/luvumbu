<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/upload.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
    flash_set('error', 'Requête invalide.');
    redirect(base_url('index.php'));
}

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT user_id, image FROM articles WHERE id = ?');
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    flash_set('error', 'Article introuvable.');
    redirect(base_url('index.php'));
}

$user = current_user();
if ((int)$article['user_id'] !== (int)$user['id'] && !is_admin()) {
    flash_set('error', 'Tu ne peux pas supprimer cet article.');
    redirect(base_url('pages/article.php?id=' . $id));
}

$imgs = $pdo->prepare('SELECT path FROM article_images WHERE article_id = ?');
$imgs->execute([$id]);
$galleryPaths = $imgs->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare('DELETE FROM articles WHERE id = ?');
$stmt->execute([$id]);

delete_uploaded_image($article['image']);
foreach ($galleryPaths as $p) {
    delete_uploaded_image($p);
}

flash_set('success', 'Article supprimé.');
redirect(base_url('index.php'));
