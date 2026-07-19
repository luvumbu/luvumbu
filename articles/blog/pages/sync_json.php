<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/sync_dump.php';
require_admin();

$UPLOADS_DIR = __DIR__ . '/../uploads';

// ---- Exports ----
$getAction = $_GET['action'] ?? '';

if ($getAction === 'export') {
    $data = sync_export_json($pdo);
    $filename = 'blog-export-' . date('Y-m-d_His') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($getAction === 'export_images') {
    $tmp = tempnam(sys_get_temp_dir(), 'imgexp_');
    try {
        sync_build_uploads_zip($UPLOADS_DIR, $tmp);
        $filename = 'blog-images-' . date('Y-m-d_His') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
    } finally {
        @unlink($tmp);
    }
    exit;
}

if ($getAction === 'export_full') {
    $tmp = tempnam(sys_get_temp_dir(), 'fullexp_');
    try {
        sync_build_full_export($pdo, $UPLOADS_DIR, $tmp);
        $filename = 'blog-full-' . date('Y-m-d_His') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
    } finally {
        @unlink($tmp);
    }
    exit;
}

// ---- Imports ----
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '')) {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'import') {
        if (($_POST['confirm'] ?? '') !== 'yes') {
            $result = ['ok' => false, 'error' => 'Tu dois confirmer en cochant la case.'];
        } elseif (empty($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
            $result = ['ok' => false, 'error' => 'Aucun fichier JSON envoye.'];
        } else {
            $raw = file_get_contents($_FILES['json_file']['tmp_name']);
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                $result = ['ok' => false, 'error' => 'Fichier JSON invalide.'];
            } else {
                try {
                    $imported = sync_import_json($pdo, $data);
                    $result = [
                        'ok'       => true,
                        'message'  => 'Import JSON termine.',
                        'imported' => $imported,
                    ];
                } catch (Throwable $e) {
                    $result = ['ok' => false, 'error' => $e->getMessage()];
                }
            }
        }
    }

    if ($postAction === 'import_images') {
        if (($_POST['confirm_images'] ?? '') !== 'yes') {
            $result = ['ok' => false, 'error' => 'Tu dois confirmer en cochant la case.'];
        } elseif (empty($_FILES['images_zip']) || $_FILES['images_zip']['error'] !== UPLOAD_ERR_OK) {
            $result = ['ok' => false, 'error' => 'Aucun fichier ZIP envoye.'];
        } else {
            try {
                $count = sync_apply_uploads_zip($_FILES['images_zip']['tmp_name'], $UPLOADS_DIR);
                $result = ['ok' => true, 'message' => "Import images termine : {$count} fichier(s) restaure(s)."];
            } catch (Throwable $e) {
                $result = ['ok' => false, 'error' => $e->getMessage()];
            }
        }
    }

    if ($postAction === 'import_merge') {
        if (($_POST['confirm_merge'] ?? '') !== 'yes') {
            $result = ['ok' => false, 'error' => 'Tu dois confirmer en cochant la case.'];
        } elseif (empty($_FILES['merge_file']) || $_FILES['merge_file']['error'] !== UPLOAD_ERR_OK) {
            $result = ['ok' => false, 'error' => 'Aucun fichier JSON envoye.'];
        } else {
            $raw = file_get_contents($_FILES['merge_file']['tmp_name']);
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                $result = ['ok' => false, 'error' => 'Fichier JSON invalide.'];
            } else {
                try {
                    $merged = sync_merge_json($pdo, $data);
                    $totalAdded   = array_sum(array_column($merged, 'added'));
                    $totalSkipped = array_sum(array_column($merged, 'skipped'));
                    $result = [
                        'ok'      => true,
                        'message' => "Fusion terminee : {$totalAdded} ligne(s) ajoutee(s), {$totalSkipped} doublon(s) ignore(s).",
                        'merged'  => $merged,
                    ];
                } catch (Throwable $e) {
                    $result = ['ok' => false, 'error' => $e->getMessage()];
                }
            }
        }
    }

    if ($postAction === 'import_full') {
        if (($_POST['confirm_full'] ?? '') !== 'yes') {
            $result = ['ok' => false, 'error' => 'Tu dois confirmer en cochant la case.'];
        } elseif (empty($_FILES['full_zip']) || $_FILES['full_zip']['error'] !== UPLOAD_ERR_OK) {
            $result = ['ok' => false, 'error' => 'Aucun fichier ZIP envoye.'];
        } else {
            try {
                $imported = sync_apply_full_import($pdo, $_FILES['full_zip']['tmp_name'], $UPLOADS_DIR);
                $nbImages = $imported['_uploads_files'] ?? 0;
                unset($imported['_uploads_files']);
                $result = [
                    'ok'       => true,
                    'message'  => "Import complet termine : donnees + {$nbImages} fichier(s) image(s).",
                    'imported' => $imported,
                ];
            } catch (Throwable $e) {
                $result = ['ok' => false, 'error' => $e->getMessage()];
            }
        }
    }
}

// ---- Statistiques ----
$stats = [
    'articles' => (int)$pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn(),
    'users'    => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'comments' => (int)$pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn(),
    'images'   => (int)$pdo->query('SELECT COUNT(*) FROM article_images')->fetchColumn(),
];

$uploadsCount = 0;
$uploadsBytes = 0;
if (is_dir($UPLOADS_DIR)) {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($UPLOADS_DIR, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iter as $f) {
        if ($f->isFile() && $f->getFilename() !== '.gitkeep') {
            $uploadsCount++;
            $uploadsBytes += $f->getSize();
        }
    }
}
$uploadsMb = $uploadsBytes > 0 ? number_format($uploadsBytes / 1048576, 1) : '0';

$pageTitle = 'Import / Export JSON';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-card auth-card-wide">
    <h1>📦 Import / Export</h1>
    <p class="muted">
        Sauvegarde toutes les données (articles, commentaires, comptes, settings) et/ou les images,
        ou restaure une sauvegarde précédente.
    </p>

    <?php if ($result): ?>
        <p class="flash <?= $result['ok'] ? 'flash-success' : 'flash-error' ?>">
            <?= e($result['ok'] ? $result['message'] : $result['error']) ?>
            <?php if (!empty($result['imported'])): ?>
                <br><small><?php
                    $parts = [];
                    foreach ($result['imported'] as $t => $n) {
                        if ($t === '_partial_errors') continue;
                        $parts[] = "$t : $n";
                    }
                    echo e(implode(' • ', $parts));
                ?></small>
            <?php endif; ?>
            <?php if (!empty($result['merged'])): ?>
                <br><small><?php
                    $parts = [];
                    foreach ($result['merged'] as $t => $c) {
                        $parts[] = "$t : +{$c['added']}/={$c['skipped']}";
                    }
                    echo e(implode(' • ', $parts));
                ?></small>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <div class="section-block">
        <div class="section-head"><span class="ico">📥</span><h3>Exporter</h3></div>
        <p>Choisis ce que tu veux télécharger :</p>

        <div class="mini-stats">
            <div class="mini-stat"><span class="v"><?= $stats['articles'] ?></span><span class="k">Articles</span></div>
            <div class="mini-stat"><span class="v"><?= $stats['users'] ?></span><span class="k">Utilisateurs</span></div>
            <div class="mini-stat"><span class="v"><?= $stats['comments'] ?></span><span class="k">Commentaires</span></div>
            <div class="mini-stat"><span class="v"><?= $stats['images'] ?></span><span class="k">Images BDD</span></div>
            <div class="mini-stat"><span class="v"><?= $uploadsCount ?></span><span class="k">Fichiers uploads</span></div>
            <div class="mini-stat"><span class="v"><?= $uploadsMb ?> Mo</span><span class="k">Taille uploads</span></div>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:14px;">
            <a class="btn-primary" href="<?= e(base_url('pages/sync_json.php?action=export')) ?>">
                📄 JSON (données seules)
            </a>
            <a class="btn-secondary" href="<?= e(base_url('pages/sync_json.php?action=export_images')) ?>">
                🖼️ Images (ZIP)
            </a>
            <a class="btn-primary" href="<?= e(base_url('pages/sync_json.php?action=export_full')) ?>">
                📦 Export complet (JSON + images)
            </a>
        </div>

        <p class="muted" style="font-size:13px; margin-top:12px;">
            ⚠️ Le JSON contient les <strong>hashes de mots de passe</strong>. Ne le partage pas publiquement.
            L'export complet (ZIP) contient le JSON + le dossier <code>uploads/</code> dans un seul fichier.
        </p>
    </div>

    <div class="section-block">
        <div class="section-head"><span class="ico">📤</span><h3>Importer les données (JSON)</h3></div>
        <p>Restaure une sauvegarde JSON. <span class="pill pill-warn">remplace les données actuelles</span></p>

        <form method="post" enctype="multipart/form-data" class="form" id="json-import-form">
            <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="import">

            <label class="dropzone" id="dz-json">
                <input type="file" name="json_file" id="json-file" accept="application/json,.json" required>
                <div class="dz-icon">📁</div>
                <div class="dz-main">Glisse-dépose ton fichier .json ici</div>
                <div class="dz-hint">ou clique pour en sélectionner un</div>
                <div class="dz-file" id="dz-json-file"></div>
            </label>

            <label class="checkbox-label" style="margin-top:14px;">
                <input type="checkbox" name="confirm" value="yes" required>
                Je comprends que toutes les données actuelles vont être remplacées.
            </label>

            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:14px;">
                <button type="submit" class="btn-primary">📤 Importer le JSON</button>
                <a href="<?= e(base_url('pages/admin.php')) ?>" class="btn-secondary">Annuler</a>
            </div>
        </form>
    </div>

    <div class="section-block">
        <div class="section-head"><span class="ico">➕</span><h3>Fusionner les données (JSON)</h3></div>
        <p>Ajoute uniquement les lignes du fichier qui <strong>n'existent pas déjà</strong> (vérification par ID). Les doublons sont ignorés, rien n'est écrasé. <span class="pill pill-ok">non destructif</span></p>

        <form method="post" enctype="multipart/form-data" class="form" id="merge-import-form">
            <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="import_merge">

            <label class="dropzone" id="dz-merge">
                <input type="file" name="merge_file" id="merge-file" accept="application/json,.json" required>
                <div class="dz-icon">➕</div>
                <div class="dz-main">Glisse-dépose ton fichier .json ici</div>
                <div class="dz-hint">les lignes en doublon (même ID) seront ignorées</div>
                <div class="dz-file" id="dz-merge-file"></div>
            </label>

            <label class="checkbox-label" style="margin-top:14px;">
                <input type="checkbox" name="confirm_merge" value="yes" required>
                Je veux ajouter les lignes manquantes sans rien écraser.
            </label>

            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:14px;">
                <button type="submit" class="btn-primary">➕ Fusionner</button>
                <a href="<?= e(base_url('pages/admin.php')) ?>" class="btn-secondary">Annuler</a>
            </div>
        </form>
    </div>

    <div class="section-block">
        <div class="section-head"><span class="ico">🖼️</span><h3>Importer les images (ZIP)</h3></div>
        <p>Restaure le contenu du dossier <code>uploads/</code> depuis un ZIP. <span class="pill pill-warn">remplace toutes les images actuelles</span></p>

        <form method="post" enctype="multipart/form-data" class="form" id="images-import-form">
            <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="import_images">

            <label class="dropzone" id="dz-zip">
                <input type="file" name="images_zip" id="images-zip" accept="application/zip,.zip" required>
                <div class="dz-icon">🗜️</div>
                <div class="dz-main">Glisse-dépose ton fichier .zip ici</div>
                <div class="dz-hint">ou clique pour en sélectionner un</div>
                <div class="dz-file" id="dz-zip-file"></div>
            </label>

            <label class="checkbox-label" style="margin-top:14px;">
                <input type="checkbox" name="confirm_images" value="yes" required>
                Je comprends que toutes les images actuelles dans <code>uploads/</code> vont être supprimées.
            </label>

            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:14px;">
                <button type="submit" class="btn-primary">📤 Importer les images</button>
                <a href="<?= e(base_url('pages/admin.php')) ?>" class="btn-secondary">Annuler</a>
            </div>
        </form>
    </div>

    <div class="section-block">
        <div class="section-head"><span class="ico">📦</span><h3>Importer tout (JSON + images depuis ZIP)</h3></div>
        <p>Restaure les données <strong>et</strong> les images en une fois, depuis un ZIP créé par "Export complet". <span class="pill pill-warn">remplace données + images</span></p>

        <form method="post" enctype="multipart/form-data" class="form" id="full-import-form">
            <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="import_full">

            <label class="dropzone" id="dz-full">
                <input type="file" name="full_zip" id="full-zip" accept="application/zip,.zip" required>
                <div class="dz-icon">📦</div>
                <div class="dz-main">Glisse-dépose ton fichier .zip ici</div>
                <div class="dz-hint">le ZIP doit contenir <code>data.json</code> + dossier <code>uploads/</code></div>
                <div class="dz-file" id="dz-full-file"></div>
            </label>

            <label class="checkbox-label" style="margin-top:14px;">
                <input type="checkbox" name="confirm_full" value="yes" required>
                Je comprends que toutes les données et les images actuelles vont être remplacées.
            </label>

            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:14px;">
                <button type="submit" class="btn-primary">📥 Importer tout</button>
                <a href="<?= e(base_url('pages/admin.php')) ?>" class="btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    function bindDropzone(dzId, inputId, fileLblId, formId, confirmMsg) {
        const dz  = document.getElementById(dzId);
        const inp = document.getElementById(inputId);
        const lbl = document.getElementById(fileLblId);
        if (!dz || !inp || !lbl) return;

        const reset = () => { inp.value = ''; lbl.textContent = ''; };

        const onFile = (file) => {
            if (!file) { lbl.textContent = ''; return; }
            const kb = (file.size / 1024).toFixed(1);
            lbl.textContent = '✓ ' + file.name + ' (' + kb + ' Ko)';

            const form = formId && document.getElementById(formId);
            if (!form || !confirmMsg) return;

            const msg = confirmMsg.replace('{file}', file.name);
            if (confirm(msg)) {
                form.querySelectorAll('input[type=checkbox][required]').forEach(c => c.checked = true);
                form.submit();
            } else {
                reset();
            }
        };

        inp.addEventListener('change', () => onFile(inp.files[0]));

        ['dragenter', 'dragover'].forEach(ev => {
            dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('dragover'); });
        });
        ['dragleave', 'drop'].forEach(ev => {
            dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('dragover'); });
        });
        dz.addEventListener('drop', e => {
            const f = e.dataTransfer.files && e.dataTransfer.files[0];
            if (!f) return;
            const dt = new DataTransfer();
            dt.items.add(f);
            inp.files = dt.files;
            onFile(f);
        });
    }

    bindDropzone(
        'dz-json', 'json-file', 'dz-json-file',
        'json-import-form',
        'Importer "{file}" ?\n\nToutes les données actuelles (articles, commentaires, utilisateurs, paramètres) seront remplacées.'
    );
    bindDropzone(
        'dz-merge', 'merge-file', 'dz-merge-file',
        'merge-import-form',
        'Fusionner "{file}" ?\n\nSeules les lignes qui n\'existent pas encore (par ID) seront ajoutées. Rien ne sera écrasé.'
    );
    bindDropzone(
        'dz-zip', 'images-zip', 'dz-zip-file',
        'images-import-form',
        'Importer "{file}" ?\n\nToutes les images actuelles dans uploads/ seront supprimées et remplacées par celles du ZIP.'
    );
    bindDropzone(
        'dz-full', 'full-zip', 'dz-full-file',
        'full-import-form',
        'Importer "{file}" ?\n\nLes données ET les images actuelles seront entièrement remplacées par le contenu du ZIP.'
    );
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
