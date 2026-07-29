<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isset($_GET['installed'])) {
    flash_set('success', 'Installation terminée — crée ton compte pour publier.');
}

// Compte une vue de la page d'accueil (1 par IP unique, séparée des articles).
record_home_view($pdo);

// Les articles masqués et ceux dont la publication est programmée plus tard ne
// s'affichent qu'à l'admin ou à leur auteur.
$me  = current_user();
$uid = (int)($me['id'] ?? 0);
$visClause = article_visibility_clause($pdo, 'a', $uid, is_admin());

$articles = $pdo->query("
    SELECT a.id, a.titre, a.image, a.contenu, a.created_at, a.visible, " . publish_at_select($pdo, 'a') . ",
           u.nom, u.prenom,
           (SELECT COUNT(*) FROM comments c WHERE c.article_id = a.id) AS nb_comments,
           (SELECT COUNT(*) FROM articles s WHERE s.parent_id = a.id) AS nb_children,
           (SELECT COUNT(*) FROM article_views v WHERE v.article_id = a.id) AS nb_views
    FROM articles a
    JOIN users u ON u.id = a.user_id
    WHERE a.parent_id IS NULL{$visClause}
    ORDER BY " . article_date_order($pdo, 'a') . " DESC
")->fetchAll();

$pageTitle = 'Accueil';
include __DIR__ . '/includes/header.php';
?>
<div class="layout">
    <section class="feed">
        <?php if (empty($articles)): ?>
            <div class="empty-state">
                <h2>Aucun article pour l'instant</h2>
                <?php if (is_logged_in()): ?>
                    <p><a class="btn-primary" href="<?= e(base_url('pages/article_new.php')) ?>">Publier le premier article</a></p>
                <?php else: ?>
                    <p><a class="btn-primary" href="<?= e(base_url('pages/register.php')) ?>">Inscris-toi pour commencer</a></p>
                <?php endif; ?>
            </div>
        <?php else: foreach ($articles as $a): ?>
            <article class="article-card">
                <h2><a href="<?= e(base_url('pages/article.php?id=' . $a['id'])) ?>"><?= e($a['titre']) ?></a><?php if ((int)$a['visible'] === 0): ?> <span class="pill pill-warn">🔒 masqué</span><?php endif; ?><?php if (article_is_scheduled($a)): ?> <span class="pill pill-warn">⏳ programmé le <?= e(format_publish_at($a['publish_at'])) ?></span><?php endif; ?></h2>
                <p class="meta"><?= article_is_scheduled($a) ? 'Écrit par' : 'Publié par' ?> <span class="publie"><?= e($a['prenom'] . ' ' . $a['nom']) ?></span> · <?= e(article_public_date($a)) ?> · 👁️ <?= (int)$a['nb_views'] ?> vue<?= $a['nb_views'] > 1 ? 's' : '' ?> · <?= (int)$a['nb_comments'] ?> commentaire<?= $a['nb_comments'] > 1 ? 's' : '' ?><?php if ((int)$a['nb_children'] > 0): ?> · <?= (int)$a['nb_children'] ?> sous-article<?= $a['nb_children'] > 1 ? 's' : '' ?><?php endif; ?></p>
                <?php if (!empty($a['image'])): ?>
                    <?php $src = preg_match('#^https?://#i', $a['image']) ? $a['image'] : base_url($a['image']); ?>
                    <figure class="img-zoomable">
                        <img src="<?= e($src) ?>" class="article-img" alt="" data-full="<?= e($src) ?>">
                        <button type="button" class="btn-zoom" data-full="<?= e($src) ?>">🔍 Voir en entier</button>
                    </figure>
                <?php endif; ?>
                <p class="article-excerpt"><?= e(mb_substr(strip_tags($a['contenu']), 0, 280)) ?><?= mb_strlen(strip_tags($a['contenu'])) > 280 ? '…' : '' ?></p>
                <a class="read-more" href="<?= e(base_url('pages/article.php?id=' . $a['id'])) ?>">Lire la suite →</a>
            </article>
        <?php endforeach; endif; ?>
    </section>
    <aside class="sidebar">
        <div class="widget">
            <h3>À propos</h3>
            <p><?= e(get_setting('about_text')) ?></p>
        </div>
        <?php if (!is_logged_in()): ?>
            <div class="widget">
                <h3>Rejoindre</h3>
                <p><a class="btn-primary" href="<?= e(base_url('pages/register.php')) ?>">S'inscrire</a></p>
            </div>
        <?php endif; ?>
    </aside>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
