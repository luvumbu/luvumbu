<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/upload.php';
require_login();

$errors = [];
$titre = $contenu = $sources = '';
$visible = 1;

// Sous-article : on récupère le parent depuis GET ou POST (hidden)
$parentId = (int)($_POST['parent_id'] ?? $_GET['parent'] ?? 0);
$parent = null;
if ($parentId > 0) {
    $stmt = $pdo->prepare('SELECT id, user_id, titre FROM articles WHERE id = ?');
    $stmt->execute([$parentId]);
    $parent = $stmt->fetch();
    if (!$parent) {
        flash_set('error', 'Article parent introuvable.');
        redirect(base_url('index.php'));
    }
    $currentUser = current_user();
    if ((int)$parent['user_id'] !== (int)$currentUser['id'] && !is_admin()) {
        flash_set('error', 'Seul l\'auteur du parent peut ajouter un sous-article.');
        redirect(base_url('pages/article.php?id=' . (int)$parent['id']));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $errors[] = 'Jeton invalide, recharge la page.';
    }
    $titre   = trim($_POST['titre'] ?? '');
    $contenu = trim($_POST['contenu'] ?? '');
    $sources = trim($_POST['sources'] ?? '');
    $visible = !empty($_POST['visible']) ? 1 : 0;
    $captions  = $_POST['captions']  ?? [];
    $positions = $_POST['positions'] ?? [];
    $layoutOrdered = order_layout_from_positions($_POST['layout_pos'] ?? []);
    $layoutString  = implode(',', $layoutOrdered);

    if ($titre === '' || mb_strlen($titre) > 190) $errors[] = 'Titre obligatoire (max 190 caractères).';
    if ($contenu === '') $errors[] = 'Contenu obligatoire.';

    $coverPath = null;
    if (empty($errors)) {
        try {
            $coverPath = handle_image_upload($_FILES['image'] ?? []);
        } catch (Exception $e) {
            $errors[] = 'Couverture : ' . $e->getMessage();
        }
    }

    $galleryUploads = [];
    if (empty($errors) && isset($_FILES['gallery'])) {
        $galleryFiles = normalize_files_array($_FILES['gallery']);
        foreach ($galleryFiles as $i => $file) {
            try {
                $path = handle_image_upload($file);
                if ($path) {
                    $galleryUploads[] = [
                        'path'     => $path,
                        'caption'  => isset($captions[$i])  ? trim((string)$captions[$i]) : '',
                        'position' => isset($positions[$i]) ? (int)$positions[$i] : $i,
                    ];
                }
            } catch (Exception $e) {
                $errors[] = "Photo galerie #" . ($i + 1) . " : " . $e->getMessage();
            }
        }
    }

    if (empty($errors)) {
        $user = current_user();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO articles (user_id, parent_id, titre, image, contenu, sources, layout, visible) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$user['id'], $parent ? (int)$parent['id'] : null, $titre, $coverPath, $contenu, $sources ?: null, $layoutString, $visible]);
            $articleId = (int)$pdo->lastInsertId();

            if (!empty($galleryUploads)) {
                $ins = $pdo->prepare('INSERT INTO article_images (article_id, path, caption, position) VALUES (?, ?, ?, ?)');
                foreach ($galleryUploads as $g) {
                    $ins->execute([$articleId, $g['path'], $g['caption'] ?: null, $g['position']]);
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            foreach ($galleryUploads as $g) delete_uploaded_image($g['path']);
            if ($coverPath) delete_uploaded_image($coverPath);
            $errors[] = 'Erreur enregistrement : ' . $e->getMessage();
        }

        if (empty($errors)) {
            flash_set('success', 'Article publié.');
            redirect(base_url('pages/article.php?id=' . $articleId));
        }
    }
}

$pageTitle = $parent ? 'Nouveau sous-article' : 'Nouvel article';
include __DIR__ . '/../includes/header.php';
?>
<div class="editor-layout">
    <div class="editor-form auth-card auth-card-wide">
        <h1><?= $parent ? 'Écrire un sous-article' : 'Écrire un article' ?></h1>
        <?php if ($parent): ?>
            <p class="meta">Sous-article de <a href="<?= e(base_url('pages/article.php?id=' . (int)$parent['id'])) ?>"><?= e($parent['titre']) ?></a></p>
        <?php endif; ?>
        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post" class="form" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <?php if ($parent): ?>
                <input type="hidden" name="parent_id" value="<?= (int)$parent['id'] ?>">
            <?php endif; ?>

            <label>Titre
                <input type="text" name="titre" value="<?= e($titre) ?>" required maxlength="190">
            </label>

            <label>Image de couverture (optionnel — affichée en haut de l'article)
                <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
            </label>
            <div id="image-preview-wrap" class="image-preview-wrap" hidden>
                <p class="muted" style="margin:0 0 6px;">Aperçu couverture :</p>
                <img id="image-preview" alt="">
            </div>

            <label>Contenu
                <textarea name="contenu" rows="12" required><?= e($contenu) ?></textarea>
            </label>

            <fieldset class="gallery-fieldset">
                <legend>Galerie (plusieurs photos)</legend>
                <input type="file" id="gallery-input" name="gallery[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                <p class="muted">Sélectionne plusieurs fichiers à la fois. Ajoute une légende et un ordre d'affichage à chacun.</p>
                <div id="gallery-list" class="gallery-list"></div>
            </fieldset>

            <label>Sources / références (optionnel — une URL par ligne)
                <textarea name="sources" rows="4" placeholder="https://exemple.com/article-source-1&#10;https://autre-source.com"><?= e($sources) ?></textarea>
            </label>

            <?php $currentLayout = default_layout(); include __DIR__ . '/../includes/layout_editor.php'; ?>

            <label style="flex-direction:row;align-items:center;gap:8px;font-weight:400;">
                <input type="checkbox" name="visible" value="1" <?= $visible ? 'checked' : '' ?> style="width:auto;">
                Article visible publiquement <span class="muted">(décoche pour le garder en brouillon / masqué)</span>
            </label>

            <button type="submit" class="btn-primary">Publier</button>
        </form>
    </div>

    <aside class="editor-preview">
        <p class="muted" style="text-transform:uppercase;letter-spacing:1px;font-size:12px;margin:0 0 10px;">Aperçu en direct</p>
        <article class="article-full preview-card" id="pv-article">
            <div data-block="title"><h1 id="pv-titre">Titre de l'article</h1></div>
            <div data-block="cover" id="pv-cover-wrap" hidden>
                <img id="pv-cover-img" class="article-img" alt="">
            </div>
            <div data-block="content">
                <div class="article-body" id="pv-contenu"><em class="muted">Commence à écrire pour voir l'aperçu…</em></div>
            </div>
            <div data-block="gallery" id="pv-gallery" class="gallery" hidden></div>
            <div data-block="sources" id="pv-sources" class="sources" hidden></div>
        </article>
    </aside>
</div>

<script src="<?= e(base_url('assets/js/preview.js')) ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
