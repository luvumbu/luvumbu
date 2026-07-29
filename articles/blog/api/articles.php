<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Méthode non autorisée', 405);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

// Visibilité : public => articles visibles ET déjà publiés (programmation échue) ;
// admin => tous ; auteur => les publics + les siens.
$viewer = api_current_user();
$visClause = article_visibility_clause($pdo, 'a', (int)($viewer['id'] ?? 0), !empty($viewer['is_admin']));
$visParams = [];

$stmt = $pdo->prepare("
    SELECT a.id, a.titre, a.image, a.contenu, a.visible, " . publish_at_select($pdo, 'a') . ", a.created_at, a.updated_at,
           u.id AS author_id, u.nom, u.prenom,
           (SELECT COUNT(*) FROM comments c WHERE c.article_id = a.id) AS nb_comments,
           (SELECT COUNT(*) FROM articles s WHERE s.parent_id = a.id) AS nb_children,
           (SELECT COUNT(*) FROM article_views v WHERE v.article_id = a.id) AS nb_views
    FROM articles a
    JOIN users u ON u.id = a.user_id
    WHERE a.parent_id IS NULL{$visClause}
    ORDER BY " . article_date_order($pdo, 'a') . " DESC
    LIMIT ? OFFSET ?
");
$i = 1;
foreach ($visParams as $p) { $stmt->bindValue($i++, $p, PDO::PARAM_INT); }
$stmt->bindValue($i++, $limit, PDO::PARAM_INT);
$stmt->bindValue($i++, $offset, PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM articles a WHERE a.parent_id IS NULL{$visClause}");
$countStmt->execute($visParams);
$total = (int)$countStmt->fetchColumn();

foreach ($articles as &$a) {
    $a['excerpt'] = mb_substr(strip_tags($a['contenu']), 0, 280);
    $a['image_url'] = $a['image']
        ? (preg_match('#^https?://#i', $a['image']) ? $a['image'] : absolute_url($a['image']))
        : null;
    $a['nb_comments'] = (int)$a['nb_comments'];
    $a['nb_children'] = (int)$a['nb_children'];
    $a['nb_views']    = (int)$a['nb_views'];
    $a['visible']     = (int)$a['visible'];
    $a['scheduled']   = article_is_scheduled($a);
    $a['published_at'] = article_public_date($a);
    unset($a['image']);
}

json_response([
    'articles' => $articles,
    'pagination' => [
        'page'  => $page,
        'limit' => $limit,
        'total' => $total,
        'has_more' => ($offset + count($articles)) < $total,
    ],
]);
