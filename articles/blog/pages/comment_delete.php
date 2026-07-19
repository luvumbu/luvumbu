<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? '')) {
    flash_set('error', 'Requête invalide.');
    redirect(base_url('index.php'));
}

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT article_id FROM comments WHERE id = ?');
$stmt->execute([$id]);
$comment = $stmt->fetch();

if (!$comment) {
    flash_set('error', 'Commentaire introuvable.');
    redirect(base_url('index.php'));
}

$stmt = $pdo->prepare('DELETE FROM comments WHERE id = ?');
$stmt->execute([$id]);

flash_set('success', 'Commentaire supprimé.');
redirect(base_url('pages/article.php?id=' . (int)$comment['article_id'] . '#commentaires'));
