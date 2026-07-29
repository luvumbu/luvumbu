<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect(base_url('index.php'));
}

$stmt = $pdo->prepare("
    SELECT a.*, u.nom, u.prenom
    FROM articles a
    JOIN users u ON u.id = a.user_id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    http_response_code(404);
    $pageTitle = 'Article introuvable';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="auth-card"><h1>Article introuvable</h1><p><a href="' . e(base_url('index.php')) . '">Retour à l\'accueil</a></p></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$user = current_user();
$errors = [];

$canEdit = $user && ((int)$article['user_id'] === (int)$user['id'] || is_admin());

// Article programmé : sa date de publication n'est pas encore atteinte.
$isScheduled = article_is_scheduled($article);

// Article masqué ou pas encore publié : invisible au public, accessible
// uniquement à l'auteur / admin.
if (((int)($article['visible'] ?? 1) === 0 || $isScheduled) && !$canEdit) {
    http_response_code(404);
    $pageTitle = 'Article introuvable';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="auth-card"><h1>Article introuvable</h1><p><a href="' . e(base_url('index.php')) . '">Retour à l\'accueil</a></p></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// Bascule visible / masqué en un clic (auteur ou admin).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_visible'])) {
    if ($canEdit && csrf_check($_POST['csrf'] ?? '')) {
        $newVisible = (int)$article['visible'] === 1 ? 0 : 1;
        $pdo->prepare('UPDATE articles SET visible = ? WHERE id = ?')->execute([$newVisible, $id]);
        flash_set('success', $newVisible ? 'Article rendu visible.' : 'Article masqué (brouillon).');
    }
    redirect(base_url('pages/article.php?id=' . $id));
}

// Annule la programmation et publie tout de suite (auteur ou admin).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_now'])) {
    if ($canEdit && csrf_check($_POST['csrf'] ?? '') && has_publish_at($pdo)) {
        $pdo->prepare('UPDATE articles SET publish_at = NULL, visible = 1 WHERE id = ?')->execute([$id]);
        flash_set('success', 'Article publié immédiatement.');
    }
    redirect(base_url('pages/article.php?id=' . $id));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    if (!$user) {
        flash_set('error', 'Connecte-toi pour commenter.');
        redirect(base_url('pages/login.php'));
    }
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $errors[] = 'Jeton invalide, recharge la page.';
    }
    $contenu = trim($_POST['comment'] ?? '');
    if ($contenu === '') $errors[] = 'Le commentaire est vide.';
    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO comments (article_id, user_id, contenu) VALUES (?, ?, ?)');
        $stmt->execute([$id, $user['id'], $contenu]);
        flash_set('success', 'Commentaire publié.');
        redirect(base_url('pages/article.php?id=' . $id . '#commentaires'));
    }
}

$stmt = $pdo->prepare("
    SELECT c.id, c.contenu, c.created_at, u.nom, u.prenom
    FROM comments c
    JOIN users u ON u.id = c.user_id
    WHERE c.article_id = ?
    ORDER BY c.created_at ASC
");
$stmt->execute([$id]);
$comments = $stmt->fetchAll();

$galleryStmt = $pdo->prepare('SELECT path, caption FROM article_images WHERE article_id = ? ORDER BY position ASC, id ASC');
$galleryStmt->execute([$id]);
$gallery = $galleryStmt->fetchAll();

// Parent (pour fil d'ariane)
$parent = null;
if (!empty($article['parent_id'])) {
    $pStmt = $pdo->prepare('SELECT id, titre FROM articles WHERE id = ?');
    $pStmt->execute([(int)$article['parent_id']]);
    $parent = $pStmt->fetch() ?: null;
}

// Sous-articles. Les masqués et les programmés ne sont visibles qu'à l'admin ou à leur auteur.
$uid = (int)($user['id'] ?? 0);
$childVis = article_visibility_clause($pdo, 'a', $uid, is_admin());
$childStmt = $pdo->prepare("
    SELECT a.id, a.titre, a.image, a.contenu, a.created_at, a.visible, " . publish_at_select($pdo, 'a') . ",
           u.nom, u.prenom,
           (SELECT COUNT(*) FROM comments c WHERE c.article_id = a.id) AS nb_comments,
           (SELECT COUNT(*) FROM articles s WHERE s.parent_id = a.id) AS nb_children
    FROM articles a
    JOIN users u ON u.id = a.user_id
    WHERE a.parent_id = ?{$childVis}
    ORDER BY a.created_at ASC
");
$childStmt->execute([$id]);
$children = $childStmt->fetchAll();

// Compteur de vues : 1 par IP unique. On n'enregistre que sur une vraie
// consultation (GET), pas sur les POST (commentaire, bascule de visibilité).
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    record_article_view($pdo, $id);
}
$views = count_article_views($pdo, $id);

$pageTitle = $article['titre'];
include __DIR__ . '/../includes/header.php';
?>
<?php
$sourcesList = [];
if (!empty($article['sources'])) {
    foreach (preg_split('/\r?\n/', $article['sources']) as $line) {
        $line = trim($line);
        if ($line !== '' && filter_var($line, FILTER_VALIDATE_URL)) {
            $sourcesList[] = $line;
        }
    }
}
$imgSrc = '';
if (!empty($article['image'])) {
    $imgSrc = preg_match('#^https?://#i', $article['image'])
        ? $article['image']
        : base_url($article['image']);
}
$canAddChild = $canEdit;
$layout = parse_layout($article['layout'] ?? null);
?>
<?php if ($parent): ?>
    <p class="breadcrumb">← <a href="<?= e(base_url('pages/article.php?id=' . (int)$parent['id'])) ?>"><?= e($parent['titre']) ?></a></p>
<?php endif; ?>
<article class="article-full">
<?php foreach ($layout as $block): ?>
    <?php if ($block === 'title'): ?>
        <h1><?= e($article['titre']) ?></h1>
        <p class="meta">
            <?= $isScheduled ? 'Écrit par' : 'Publié par' ?> <span class="publie"><?= e($article['prenom'] . ' ' . $article['nom']) ?></span>
            · <?= e(article_public_date($article)) ?>
            <?php if (!empty($article['updated_at'])): ?>
                · <em>modifié le <?= e($article['updated_at']) ?></em>
            <?php endif; ?>
            · 👁️ <?= (int)$views ?> vue<?= $views > 1 ? 's' : '' ?>
            <?php if ((int)$article['visible'] === 0): ?>
                · <span class="pill pill-warn">🔒 masqué</span>
            <?php endif; ?>
            <?php if ($isScheduled): ?>
                · <span class="pill pill-warn">⏳ publication programmée le <?= e(format_publish_at($article['publish_at'])) ?></span>
            <?php endif; ?>
        </p>
    <?php elseif ($block === 'cover' && $imgSrc): ?>
        <figure class="img-zoomable">
            <img src="<?= e($imgSrc) ?>" class="article-img" alt="" data-full="<?= e($imgSrc) ?>">
            <button type="button" class="btn-zoom" data-full="<?= e($imgSrc) ?>">🔍 Voir en entier</button>
        </figure>
    <?php elseif ($block === 'content'): ?>
        <div class="article-body"><?= nl2br(e($article['contenu'])) ?></div>
    <?php elseif ($block === 'gallery' && !empty($gallery)): ?>
        <div class="gallery">
            <?php foreach ($gallery as $g): ?>
                <?php $src = preg_match('#^https?://#i', $g['path']) ? $g['path'] : base_url($g['path']); ?>
                <figure class="gallery-fig img-zoomable">
                    <img src="<?= e($src) ?>" alt="" data-full="<?= e($src) ?>">
                    <button type="button" class="btn-zoom" data-full="<?= e($src) ?>" aria-label="Voir en entier">🔍</button>
                    <?php if (!empty($g['caption'])): ?>
                        <figcaption><?= e($g['caption']) ?></figcaption>
                    <?php endif; ?>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php elseif ($block === 'sources' && !empty($sourcesList)): ?>
        <div class="sources">
            <h3>Sources</h3>
            <ul>
                <?php foreach ($sourcesList as $url): ?>
                    <li><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($url) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php
// Quiz rattachés à cet article. L'auteur/admin voit aussi les brouillons ; le public
// ne voit que les quiz publiés (active = 1).
$aqStmt = $pdo->prepare('
    SELECT q.id, q.title, q.description, q.active,
           (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS nb_questions
    FROM article_quizzes aq
    JOIN quizzes q ON q.id = aq.quiz_id
    WHERE aq.article_id = ? ' . ($canEdit ? '' : 'AND q.active = 1') . '
    ORDER BY aq.position ASC, q.id ASC
');
$aqStmt->execute([(int)$article['id']]);
$articleQuizzes = $aqStmt->fetchAll();
?>
<?php if (!empty($articleQuizzes)): ?>
    <section class="article-quiz" id="quiz">
        <h2 class="aq-title">📝 Teste tes connaissances</h2>
        <p class="aq-sub">Tu as terminé la lecture ? Réponds <?= count($articleQuizzes) > 1 ? 'aux questionnaires' : 'au questionnaire' ?> ci-dessous.</p>
        <div class="aq-list">
            <?php foreach ($articleQuizzes as $qz): ?>
                <a class="aq-item" href="<?= e(base_url('pages/quiz.php?id=' . (int)$qz['id'])) ?>">
                    <span class="aq-ico">❓</span>
                    <span class="aq-body">
                        <span class="aq-name"><?= e($qz['title']) ?><?php if ((int)$qz['active'] !== 1): ?> <em style="color:#e63946;font-style:normal;">(brouillon)</em><?php endif; ?></span>
                        <span class="aq-meta"><?= (int)$qz['nb_questions'] ?> question<?= (int)$qz['nb_questions'] > 1 ? 's' : '' ?></span>
                    </span>
                    <span class="aq-cta">Commencer →</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <style>
        .article-quiz{margin:28px 0;padding:22px 24px;border-radius:16px;border:1px solid #f4c14b;background:rgba(244,193,75,.08)}
        .aq-title{font-size:19px;color:#c98a00;margin:0 0 4px}
        .aq-sub{font-size:14px;color:#666;margin:0 0 16px}
        .aq-list{display:flex;flex-direction:column;gap:12px}
        .aq-item{display:flex;align-items:center;gap:14px;text-decoration:none;color:inherit;padding:14px 16px;border-radius:12px;border:1px solid rgba(0,0,0,.1);background:#fff;transition:.15s}
        .aq-item:hover{border-color:#f4c14b;transform:translateY(-2px)}
        .aq-ico{width:44px;height:44px;flex:0 0 44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;background:rgba(244,193,75,.18)}
        .aq-body{flex:1;display:flex;flex-direction:column}
        .aq-name{font-weight:700}
        .aq-meta{font-size:13px;color:#777}
        .aq-cta{font-weight:700;color:#c98a00}
    </style>
<?php endif; ?>

    <?php if ($canEdit): ?>
        <div class="article-actions">
            <a class="btn-secondary" href="<?= e(base_url('pages/article_edit.php?id=' . $article['id'])) ?>">Modifier</a>
            <span class="push-group" style="display:inline-flex; gap:6px; align-items:center; flex-wrap:wrap;">
                <input type="text" id="push-key" value="<?= e(get_setting('sync_remote_key', '')) ?>"
                       placeholder="Clé d'envoi (serveur)" autocomplete="off" spellcheck="false"
                       style="padding:8px 10px; border:1px solid #ccc; border-radius:6px; font-size:13px; min-width:220px;">
                <button type="button" class="btn-secondary" id="btn-push-article" data-id="<?= (int)$article['id'] ?>">📤 Envoyer vers le serveur</button>
            </span>
            <a class="btn-primary" href="<?= e(base_url('pages/article_new.php?parent=' . (int)$article['id'])) ?>">+ Sous-article</a>
            <?php if ($isScheduled): ?>
                <form method="post" class="inline-form"
                      onsubmit="return confirm('Publier cet article maintenant (annule la programmation) ?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="publish_now" value="1">
                    <button type="submit" class="btn-secondary">🚀 Publier maintenant</button>
                </form>
            <?php endif; ?>
            <form method="post" class="inline-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="toggle_visible" value="1">
                <button type="submit" class="btn-secondary">
                    <?= (int)$article['visible'] === 1 ? '🔒 Masquer' : '👁️ Rendre visible' ?>
                </button>
            </form>
            <form method="post" action="<?= e(base_url('pages/article_delete.php')) ?>" class="inline-form"
                  onsubmit="return confirm('Supprimer définitivement cet article ?<?= !empty($children) ? ' Tous ses sous-articles seront aussi supprimés.' : '' ?>');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                <button type="submit" class="btn-danger">Supprimer l'article</button>
            </form>
        </div>
    <?php endif; ?>
</article>

<?php if (!empty($children) || $canAddChild): ?>
<section class="children">
    <h2>Sous-articles<?= !empty($children) ? ' (' . count($children) . ')' : '' ?></h2>
    <?php if (empty($children)): ?>
        <p class="muted">Aucun sous-article pour l'instant.</p>
    <?php else: foreach ($children as $c): ?>
        <article class="article-card child-card">
            <h3><a href="<?= e(base_url('pages/article.php?id=' . (int)$c['id'])) ?>"><?= e($c['titre']) ?></a><?php if ((int)$c['visible'] === 0): ?> <span class="pill pill-warn">🔒 masqué</span><?php endif; ?><?php if (article_is_scheduled($c)): ?> <span class="pill pill-warn">⏳ <?= e(format_publish_at($c['publish_at'])) ?></span><?php endif; ?></h3>
            <p class="meta">
                Par <span class="publie"><?= e($c['prenom'] . ' ' . $c['nom']) ?></span>
                · <?= e(article_public_date($c)) ?>
                · <?= (int)$c['nb_comments'] ?> commentaire<?= $c['nb_comments'] > 1 ? 's' : '' ?>
                <?php if ((int)$c['nb_children'] > 0): ?>
                    · <?= (int)$c['nb_children'] ?> sous-article<?= $c['nb_children'] > 1 ? 's' : '' ?>
                <?php endif; ?>
            </p>
            <?php if (!empty($c['image'])): ?>
                <?php $cSrc = preg_match('#^https?://#i', $c['image']) ? $c['image'] : base_url($c['image']); ?>
                <img src="<?= e($cSrc) ?>" class="article-img" alt="">
            <?php endif; ?>
            <p class="article-excerpt"><?= e(mb_substr(strip_tags($c['contenu']), 0, 200)) ?><?= mb_strlen(strip_tags($c['contenu'])) > 200 ? '…' : '' ?></p>
            <a class="read-more" href="<?= e(base_url('pages/article.php?id=' . (int)$c['id'])) ?>">Lire la suite →</a>
        </article>
    <?php endforeach; endif; ?>
</section>
<?php endif; ?>

<section id="commentaires" class="comments">
    <h2><?= count($comments) ?> commentaire<?= count($comments) > 1 ? 's' : '' ?></h2>

    <?php foreach ($comments as $c): ?>
        <div class="comment">
            <p class="comment-author"><?= e($c['prenom'] . ' ' . $c['nom']) ?> <span class="muted">· <?= e($c['created_at']) ?></span></p>
            <p class="comment-body"><?= nl2br(e($c['contenu'])) ?></p>
            <?php if (is_admin()): ?>
                <form method="post" action="<?= e(base_url('pages/comment_delete.php')) ?>" class="inline-form"
                      onsubmit="return confirm('Supprimer ce commentaire ?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="btn-danger">Supprimer</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if ($user): ?>
        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post" class="form comment-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label>Laisser un commentaire
                <textarea name="comment" rows="4" required></textarea>
            </label>
            <button type="submit" class="btn-primary">Commenter</button>
        </form>
    <?php else: ?>
        <p class="comment-locked">
            <a href="<?= e(base_url('pages/login.php')) ?>">Connecte-toi</a>
            ou <a href="<?= e(base_url('pages/register.php')) ?>">inscris-toi</a>
            pour laisser un commentaire.
        </p>
    <?php endif; ?>
</section>

<script>
(function () {
    const btn = document.getElementById('btn-push-article');
    if (!btn) return;
    btn.addEventListener('click', async () => {
        if (!confirm('Envoyer cet article (et ses images) vers le serveur en ligne ?')) return;
        const old = btn.textContent;
        btn.disabled = true;
        btn.textContent = '⏳ Envoi…';
        try {
            const keyInput = document.getElementById('push-key');
            const fd = new FormData();
            fd.append('csrf', '<?= e(csrf_token()) ?>');
            fd.append('id', btn.dataset.id);
            if (keyInput) fd.append('token', keyInput.value.trim());
            const res = await fetch('<?= e(base_url('pages/article_push.php')) ?>', { method: 'POST', body: fd });
            const d = await res.json().catch(() => ({}));
            if (d.ok) {
                btn.textContent = '✅ Envoyé';
                alert('✅ ' + (d.message || 'Article envoyé sur le serveur.'));
            } else {
                btn.textContent = old;
                alert('❌ ' + (d.error || ('Échec (HTTP ' + res.status + ')')));
            }
        } catch (e) {
            btn.textContent = old;
            alert('❌ Erreur réseau : ' + e.message);
        } finally {
            btn.disabled = false;
            setTimeout(() => { btn.textContent = old; }, 2500);
        }
    });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
