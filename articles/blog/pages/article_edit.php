<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/upload.php';
require_login();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) redirect(base_url('index.php'));

$stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    flash_set('error', 'Article introuvable.');
    redirect(base_url('index.php'));
}

$user = current_user();
if ((int)$article['user_id'] !== (int)$user['id'] && !is_admin()) {
    flash_set('error', 'Tu ne peux pas modifier cet article.');
    redirect(base_url('pages/article.php?id=' . $id));
}

$errors = [];
$titre   = $article['titre'];
$contenu = $article['contenu'];
$sources = $article['sources'] ?? '';
$visible = (int)($article['visible'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $errors[] = 'Jeton invalide, recharge la page.';
    }
    $titre   = trim($_POST['titre'] ?? '');
    $contenu = trim($_POST['contenu'] ?? '');
    $sources = trim($_POST['sources'] ?? '');
    $visible = !empty($_POST['visible']) ? 1 : 0;
    $removeImage = !empty($_POST['remove_image']);
    $layoutOrdered = order_layout_from_positions($_POST['layout_pos'] ?? []);
    $layoutString  = implode(',', $layoutOrdered);

    if ($titre === '' || mb_strlen($titre) > 190) $errors[] = 'Titre obligatoire (max 190 caractères).';
    if ($contenu === '') $errors[] = 'Contenu obligatoire.';

    $newCoverPath = null;
    $hasNewCover = !empty($_FILES['image']['name']);

    if (empty($errors) && $hasNewCover) {
        try {
            $newCoverPath = handle_image_upload($_FILES['image']);
        } catch (Exception $e) {
            $errors[] = 'Couverture : ' . $e->getMessage();
        }
    }

    // Nouvelles photos de galerie
    $newGalleryUploads = [];
    $newCaptions  = $_POST['new_captions']  ?? [];
    $newPositions = $_POST['new_positions'] ?? [];
    if (empty($errors) && isset($_FILES['gallery'])) {
        $files = normalize_files_array($_FILES['gallery']);
        foreach ($files as $i => $file) {
            try {
                $path = handle_image_upload($file);
                if ($path) {
                    $newGalleryUploads[] = [
                        'path'     => $path,
                        'caption'  => isset($newCaptions[$i])  ? trim((string)$newCaptions[$i]) : '',
                        'position' => isset($newPositions[$i]) ? (int)$newPositions[$i] : 100 + $i,
                    ];
                }
            } catch (Exception $e) {
                $errors[] = "Nouvelle photo #" . ($i + 1) . " : " . $e->getMessage();
            }
        }
    }

    if (empty($errors)) {
        $finalCover = $article['image'];
        if ($newCoverPath) {
            delete_uploaded_image($article['image']);
            $finalCover = $newCoverPath;
        } elseif ($removeImage) {
            delete_uploaded_image($article['image']);
            $finalCover = null;
        }

        $pdo->beginTransaction();
        try {
            // MAJ article
            $stmt = $pdo->prepare('UPDATE articles SET titre = ?, image = ?, contenu = ?, sources = ?, layout = ?, visible = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$titre, $finalCover, $contenu, $sources ?: null, $layoutString, $visible, $id]);

            // MAJ ou suppression des photos existantes
            $existing = $_POST['existing'] ?? [];
            if (is_array($existing)) {
                $deleteIds = [];
                $updateStmt = $pdo->prepare('UPDATE article_images SET caption = ?, position = ? WHERE id = ? AND article_id = ?');
                foreach ($existing as $imgId => $data) {
                    $imgId = (int)$imgId;
                    if (!empty($data['delete'])) {
                        $deleteIds[] = $imgId;
                    } else {
                        $cap = isset($data['caption']) ? trim((string)$data['caption']) : '';
                        $pos = isset($data['position']) ? (int)$data['position'] : 0;
                        $updateStmt->execute([$cap ?: null, $pos, $imgId, $id]);
                    }
                }
                if (!empty($deleteIds)) {
                    $in = implode(',', array_fill(0, count($deleteIds), '?'));
                    $sel = $pdo->prepare("SELECT id, path FROM article_images WHERE article_id = ? AND id IN ($in)");
                    $sel->execute(array_merge([$id], $deleteIds));
                    $toDelete = $sel->fetchAll();
                    foreach ($toDelete as $row) {
                        delete_uploaded_image($row['path']);
                    }
                    $del = $pdo->prepare("DELETE FROM article_images WHERE article_id = ? AND id IN ($in)");
                    $del->execute(array_merge([$id], $deleteIds));
                }
            }

            // Ajout des nouvelles
            if (!empty($newGalleryUploads)) {
                $ins = $pdo->prepare('INSERT INTO article_images (article_id, path, caption, position) VALUES (?, ?, ?, ?)');
                foreach ($newGalleryUploads as $g) {
                    $ins->execute([$id, $g['path'], $g['caption'] ?: null, $g['position']]);
                }
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            foreach ($newGalleryUploads as $g) delete_uploaded_image($g['path']);
            if ($newCoverPath) delete_uploaded_image($newCoverPath);
            $errors[] = 'Erreur enregistrement : ' . $e->getMessage();
        }

        if (empty($errors)) {
            flash_set('success', 'Article modifié.');
            redirect(base_url('pages/article.php?id=' . $id));
        }
    }
}

// Récupération des photos existantes
$gallery = $pdo->prepare('SELECT id, path, caption, position FROM article_images WHERE article_id = ? ORDER BY position ASC, id ASC');
$gallery->execute([$id]);
$gallery = $gallery->fetchAll();

$currentImageSrc = '';
if (!empty($article['image'])) {
    $currentImageSrc = preg_match('#^https?://#i', $article['image'])
        ? $article['image']
        : base_url($article['image']);
}

$pageTitle = 'Modifier l\'article';
include __DIR__ . '/../includes/header.php';
?>
<div class="editor-layout">
    <div class="editor-form auth-card auth-card-wide">
        <h1>Modifier l'article</h1>
        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post" class="form" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$id ?>">

            <label>Titre
                <input type="text" name="titre" value="<?= e($titre) ?>" required maxlength="190">
            </label>

            <?php if ($currentImageSrc): ?>
                <div>
                    <p class="muted" style="margin:0 0 8px;">Couverture actuelle :</p>
                    <img src="<?= e($currentImageSrc) ?>" alt="" style="max-width:240px;border-radius:4px;">
                    <label style="flex-direction:row;align-items:center;gap:8px;font-weight:400;margin-top:8px;">
                        <input type="checkbox" name="remove_image" value="1" style="width:auto;">
                        Supprimer la couverture actuelle
                    </label>
                </div>
            <?php endif; ?>

            <label>Remplacer la couverture (optionnel)
                <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
            </label>
            <div id="image-preview-wrap" class="image-preview-wrap" hidden>
                <p class="muted" style="margin:0 0 6px;">Aperçu nouvelle couverture :</p>
                <img id="image-preview" alt="">
            </div>

            <label>Contenu
                <textarea name="contenu" rows="12" required><?= e($contenu) ?></textarea>
            </label>

            <fieldset class="gallery-fieldset">
                <legend>Galerie</legend>

                <?php if (!empty($gallery)): ?>
                    <p class="muted">Photos existantes — édite la légende ou l'ordre, ou coche pour supprimer.</p>
                    <div class="gallery-list">
                        <?php foreach ($gallery as $g): ?>
                            <?php $src = preg_match('#^https?://#i', $g['path']) ? $g['path'] : base_url($g['path']); ?>
                            <div class="gallery-item">
                                <img src="<?= e($src) ?>" alt="">
                                <div class="gallery-meta">
                                    <input type="text" name="existing[<?= (int)$g['id'] ?>][caption]" value="<?= e($g['caption']) ?>" placeholder="Légende (optionnel)">
                                    <label class="row-label">Ordre
                                        <input type="number" name="existing[<?= (int)$g['id'] ?>][position]" value="<?= (int)$g['position'] ?>" min="0">
                                    </label>
                                    <label class="row-label">
                                        <input type="checkbox" name="existing[<?= (int)$g['id'] ?>][delete]" value="1">
                                        Supprimer
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <p class="muted" style="margin-top:14px;">Ajouter de nouvelles photos :</p>
                <input type="file" id="gallery-input" name="gallery[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                <div id="gallery-list" class="gallery-list"></div>
            </fieldset>

            <label>Sources / références (une URL par ligne)
                <textarea name="sources" rows="4" placeholder="https://..."><?= e($sources) ?></textarea>
            </label>

            <?php $currentLayout = parse_layout($article['layout'] ?? null); include __DIR__ . '/../includes/layout_editor.php'; ?>

            <label style="flex-direction:row;align-items:center;gap:8px;font-weight:400;">
                <input type="checkbox" name="visible" value="1" <?= $visible ? 'checked' : '' ?> style="width:auto;">
                Article visible publiquement <span class="muted">(décoche pour le masquer / repasser en brouillon)</span>
            </label>

            <button type="submit" class="btn-primary">Enregistrer</button>
        </form>
    </div>

    <aside class="editor-preview">
        <p class="muted" style="text-transform:uppercase;letter-spacing:1px;font-size:12px;margin:0 0 10px;">Aperçu en direct</p>
        <article class="article-full preview-card" id="pv-article">
            <div data-block="title"><h1 id="pv-titre"><?= e($titre) ?></h1></div>
            <div data-block="cover" id="pv-cover-wrap" <?= $currentImageSrc ? '' : 'hidden' ?>>
                <img id="pv-cover-img" class="article-img" src="<?= e($currentImageSrc) ?>" alt="">
            </div>
            <div data-block="content">
                <div class="article-body" id="pv-contenu"><?= nl2br(e($contenu)) ?></div>
            </div>
            <div data-block="gallery" id="pv-gallery" class="gallery" <?= empty($gallery) ? 'hidden' : '' ?>>
                <?php foreach ($gallery as $g): ?>
                    <?php $src = preg_match('#^https?://#i', $g['path']) ? $g['path'] : base_url($g['path']); ?>
                    <figure class="gallery-fig">
                        <img src="<?= e($src) ?>" alt="">
                        <?php if (!empty($g['caption'])): ?><figcaption><?= e($g['caption']) ?></figcaption><?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
            <div data-block="sources" id="pv-sources" class="sources" hidden></div>
        </article>
    </aside>
</div>

<script src="<?= e(base_url('assets/js/preview.js')) ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
