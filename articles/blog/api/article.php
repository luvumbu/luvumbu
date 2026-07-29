<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/upload.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_error('id manquant', 400);

    $stmt = $pdo->prepare("
        SELECT a.id, a.titre, a.image, a.contenu, a.sources, a.parent_id,
               a.visible, " . publish_at_select($pdo, 'a') . ", a.created_at, a.updated_at,
               u.id AS author_id, u.nom, u.prenom
        FROM articles a
        JOIN users u ON u.id = a.user_id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    if (!$article) json_error('Article introuvable', 404);
    // Un article masqué ou programmé plus tard reste accessible à son auteur ou à
    // un admin (token Bearer optionnel).
    $viewer = api_current_user();
    $isPriv = $viewer && ((int)$article['author_id'] === (int)$viewer['id'] || !empty($viewer['is_admin']));
    $isScheduled = article_is_scheduled($article);
    if (((int)$article['visible'] === 0 || $isScheduled) && !$isPriv) json_error('Article introuvable', 404);
    $article['visible']      = (int)$article['visible'];
    $article['scheduled']    = $isScheduled;
    $article['published_at'] = article_public_date($article);

    // Compteur de vues : 1 par IP unique.
    record_article_view($pdo, $id);
    $vStmt = $pdo->prepare('SELECT COUNT(*) FROM article_views WHERE article_id = ?');
    $vStmt->execute([$id]);
    $article['views'] = (int)$vStmt->fetchColumn();

    $article['image_url'] = $article['image']
        ? (preg_match('#^https?://#i', $article['image']) ? $article['image'] : base_url($article['image']))
        : null;
    unset($article['image']);

    // Sous-articles (masqués / programmés réservés à l'auteur et à l'admin)
    $childVis = article_visibility_clause($pdo, 'a', (int)($viewer['id'] ?? 0), !empty($viewer['is_admin']));
    $childStmt = $pdo->prepare("
        SELECT a.id, a.titre, a.created_at, u.prenom, u.nom,
               (SELECT COUNT(*) FROM comments c WHERE c.article_id = a.id) AS nb_comments
        FROM articles a
        JOIN users u ON u.id = a.user_id
        WHERE a.parent_id = ?{$childVis}
        ORDER BY a.created_at ASC
    ");
    $childStmt->execute([$id]);
    $article['children'] = array_map(function ($c) {
        $c['nb_comments'] = (int)$c['nb_comments'];
        return $c;
    }, $childStmt->fetchAll());

    // Commentaires
    $cStmt = $pdo->prepare("
        SELECT c.id, c.contenu, c.created_at, u.prenom, u.nom
        FROM comments c
        JOIN users u ON u.id = c.user_id
        WHERE c.article_id = ?
        ORDER BY c.created_at ASC
    ");
    $cStmt->execute([$id]);
    $article['comments'] = $cStmt->fetchAll();

    // Galerie
    $gStmt = $pdo->prepare("
        SELECT id, path, caption, position
        FROM article_images
        WHERE article_id = ?
        ORDER BY position ASC, id ASC
    ");
    $gStmt->execute([$id]);
    $article['gallery'] = array_map(function ($g) {
        return [
            'id'       => (int)$g['id'],
            'url'      => preg_match('#^https?://#i', $g['path']) ? $g['path'] : base_url($g['path']),
            'caption'  => $g['caption'],
            'position' => (int)$g['position'],
        ];
    }, $gStmt->fetchAll());

    json_response(['article' => $article]);
}

if ($method === 'POST') {
    $user = api_require_user();

    // Accepte multipart (avec image) OU JSON (sans image)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isMultipart = stripos($contentType, 'multipart/form-data') !== false;

    if ($isMultipart) {
        $titre    = trim($_POST['titre']   ?? '');
        $contenu  = trim($_POST['contenu'] ?? '');
        $sources  = trim($_POST['sources'] ?? '');
        $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
        $overrideMethod = strtoupper(trim($_POST['_method'] ?? ''));
        $editId   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        // null = champ absent (création -> visible par défaut ; édition -> on garde l'existant)
        $visible  = array_key_exists('visible', $_POST) ? (int)((bool)$_POST['visible']) : null;
        $schedGiven = array_key_exists('publish_at', $_POST);
        $schedRaw   = $schedGiven ? $_POST['publish_at'] : null;
    } else {
        $body = read_json_body();
        $titre    = trim($body['titre']   ?? '');
        $contenu  = trim($body['contenu'] ?? '');
        $sources  = trim($body['sources'] ?? '');
        $parentId = isset($body['parent_id']) ? (int)$body['parent_id'] : 0;
        $overrideMethod = strtoupper(trim($body['_method'] ?? ''));
        $editId   = isset($body['id']) ? (int)$body['id'] : 0;
        $visible  = array_key_exists('visible', $body) ? (int)((bool)$body['visible']) : null;
        $schedGiven = array_key_exists('publish_at', $body);
        $schedRaw   = $schedGiven ? $body['publish_at'] : null;
    }

    // Programmation : "publish_at" absent = inchangé ; vide/null = publication
    // immédiate ; date ("2026-08-01T09:00" ou "2026-08-01 09:00:00") = programmée.
    $publishAt = null;
    if ($schedGiven) {
        $publishAt = parse_publish_at($schedRaw);
        if ($publishAt === false) json_error('publish_at invalide (format attendu : 2026-08-01 09:00)', 422);
    }
    $canSchedule = $schedGiven && has_publish_at($pdo);

    // Suppression : POST avec _method=DELETE (et un id)
    if ($overrideMethod === 'DELETE') {
        delete_article($pdo, $user, $editId);
        return;
    }

    // Édition : POST avec _method=PUT (et un id)
    if ($overrideMethod === 'PUT' || $editId > 0) {
        edit_article($pdo, $user, $editId, $titre, $contenu, $sources, $isMultipart, $visible, $canSchedule, $publishAt);
        return;
    }

    if ($titre === '' || mb_strlen($titre) > 190) json_error('Titre obligatoire (max 190)', 422);
    if ($contenu === '') json_error('Contenu obligatoire', 422);

    if ($parentId > 0) {
        $p = $pdo->prepare('SELECT id, user_id FROM articles WHERE id = ?');
        $p->execute([$parentId]);
        $parent = $p->fetch();
        if (!$parent) json_error('Article parent introuvable', 404);
        if ((int)$parent['user_id'] !== (int)$user['id'] && empty($user['is_admin'])) {
            json_error('Seul l\'auteur du parent peut ajouter un sous-article', 403);
        }
    }

    // Upload de l'image de couverture si fournie (multipart only)
    $coverPath = null;
    if ($isMultipart && isset($_FILES['image'])) {
        try {
            $coverPath = handle_image_upload($_FILES['image']);
        } catch (Exception $e) {
            json_error('Image : ' . $e->getMessage(), 422);
        }
    }

    // Upload de la galerie (plusieurs photos) si fournie
    $galleryUploads = [];
    if ($isMultipart && isset($_FILES['gallery'])) {
        $captions  = $_POST['captions']  ?? [];
        $positions = $_POST['positions'] ?? [];
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
                // On nettoie ce qui a déjà été uploadé puis on remonte l'erreur
                if ($coverPath) delete_uploaded_image($coverPath);
                foreach ($galleryUploads as $g) delete_uploaded_image($g['path']);
                json_error("Photo galerie #" . ($i + 1) . " : " . $e->getMessage(), 422);
            }
        }
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO articles (user_id, parent_id, titre, image, contenu, sources, visible) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $user['id'],
            $parentId > 0 ? $parentId : null,
            $titre,
            $coverPath,
            $contenu,
            $sources !== '' ? $sources : null,
            $visible === null ? 1 : $visible,
        ]);
        $newId = (int)$pdo->lastInsertId();

        if ($canSchedule) {
            $pdo->prepare('UPDATE articles SET publish_at = ? WHERE id = ?')->execute([$publishAt, $newId]);
        }

        if (!empty($galleryUploads)) {
            $ins = $pdo->prepare('INSERT INTO article_images (article_id, path, caption, position) VALUES (?, ?, ?, ?)');
            foreach ($galleryUploads as $g) {
                $ins->execute([$newId, $g['path'], $g['caption'] !== '' ? $g['caption'] : null, $g['position']]);
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($coverPath) delete_uploaded_image($coverPath);
        foreach ($galleryUploads as $g) delete_uploaded_image($g['path']);
        json_error('Erreur d\'enregistrement : ' . $e->getMessage(), 500);
    }

    $galleryOut = array_map(function ($g) {
        return [
            'url'      => absolute_url($g['path']),
            'caption'  => $g['caption'] !== '' ? $g['caption'] : null,
            'position' => $g['position'],
        ];
    }, $galleryUploads);

    $scheduled = $canSchedule && $publishAt && strtotime($publishAt) > time();
    json_response([
        'id' => $newId,
        'image_url'  => $coverPath ? absolute_url($coverPath) : null,
        'gallery'    => $galleryOut,
        'publish_at' => $canSchedule ? $publishAt : null,
        'scheduled'  => $scheduled,
        'message'    => $scheduled ? 'Article programmé pour le ' . format_publish_at($publishAt) : 'Article publié',
    ], 201);
}

json_error('Méthode non autorisée', 405);

/**
 * Édition d'un article existant.
 * Accepte les mêmes champs que la création + :
 *   - id, remove_image (1 pour retirer la couverture actuelle)
 *   - existing[<imageId>][caption|position|delete] : MAJ ou suppression d'une photo galerie existante
 *   - gallery[], captions[], positions[] : nouvelles photos à ajouter
 *   - publish_at : date de publication programmée (vide = publier maintenant,
 *     champ absent = programmation inchangée)
 */
function edit_article(PDO $pdo, array $user, int $id, string $titre, string $contenu, string $sources, bool $isMultipart, ?int $visible = null, bool $setPublishAt = false, ?string $publishAt = null) {
    if ($id <= 0) json_error('id manquant pour l\'édition', 400);
    if ($titre === '' || mb_strlen($titre) > 190) json_error('Titre obligatoire (max 190)', 422);
    if ($contenu === '') json_error('Contenu obligatoire', 422);

    $stmt = $pdo->prepare('SELECT id, user_id, image, visible FROM articles WHERE id = ?');
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    if (!$article) json_error('Article introuvable', 404);
    if ((int)$article['user_id'] !== (int)$user['id'] && empty($user['is_admin'])) {
        json_error('Tu ne peux pas modifier cet article', 403);
    }

    $removeImage = !empty($_POST['remove_image']);
    $newCoverPath = null;
    if ($isMultipart && isset($_FILES['image']) && !empty($_FILES['image']['name'])) {
        try {
            $newCoverPath = handle_image_upload($_FILES['image']);
        } catch (Exception $e) {
            json_error('Couverture : ' . $e->getMessage(), 422);
        }
    }

    // Nouvelles photos galerie
    $newGalleryUploads = [];
    if ($isMultipart && isset($_FILES['gallery'])) {
        $captions  = $_POST['captions']  ?? [];
        $positions = $_POST['positions'] ?? [];
        $files = normalize_files_array($_FILES['gallery']);
        foreach ($files as $i => $file) {
            try {
                $path = handle_image_upload($file);
                if ($path) {
                    $newGalleryUploads[] = [
                        'path'     => $path,
                        'caption'  => isset($captions[$i])  ? trim((string)$captions[$i]) : '',
                        'position' => isset($positions[$i]) ? (int)$positions[$i] : 100 + $i,
                    ];
                }
            } catch (Exception $e) {
                if ($newCoverPath) delete_uploaded_image($newCoverPath);
                foreach ($newGalleryUploads as $g) delete_uploaded_image($g['path']);
                json_error("Nouvelle photo #" . ($i + 1) . " : " . $e->getMessage(), 422);
            }
        }
    }

    // Détermine la couverture finale
    $finalCover = $article['image'];
    if ($newCoverPath) {
        $finalCover = $newCoverPath;
    } elseif ($removeImage) {
        $finalCover = null;
    }

    // Visibilité : si non fournie, on conserve la valeur actuelle.
    $finalVisible = $visible === null ? (int)$article['visible'] : $visible;

    try {
        $pdo->beginTransaction();

        $upd = $pdo->prepare('UPDATE articles SET titre = ?, image = ?, contenu = ?, sources = ?, visible = ?, updated_at = NOW() WHERE id = ?');
        $upd->execute([$titre, $finalCover, $contenu, $sources !== '' ? $sources : null, $finalVisible, $id]);

        if ($setPublishAt) {
            $pdo->prepare('UPDATE articles SET publish_at = ? WHERE id = ?')->execute([$publishAt, $id]);
        }

        // Photos existantes : MAJ ou suppression
        $existing = $_POST['existing'] ?? [];
        $deletedPaths = [];
        if (is_array($existing) && !empty($existing)) {
            $deleteIds = [];
            $updateStmt = $pdo->prepare('UPDATE article_images SET caption = ?, position = ? WHERE id = ? AND article_id = ?');
            foreach ($existing as $imgId => $data) {
                $imgId = (int)$imgId;
                if (!empty($data['delete'])) {
                    $deleteIds[] = $imgId;
                } else {
                    $cap = isset($data['caption']) ? trim((string)$data['caption']) : '';
                    $pos = isset($data['position']) ? (int)$data['position'] : 0;
                    $updateStmt->execute([$cap !== '' ? $cap : null, $pos, $imgId, $id]);
                }
            }
            if (!empty($deleteIds)) {
                $in = implode(',', array_fill(0, count($deleteIds), '?'));
                $sel = $pdo->prepare("SELECT path FROM article_images WHERE article_id = ? AND id IN ($in)");
                $sel->execute(array_merge([$id], $deleteIds));
                $deletedPaths = array_column($sel->fetchAll(), 'path');
                $del = $pdo->prepare("DELETE FROM article_images WHERE article_id = ? AND id IN ($in)");
                $del->execute(array_merge([$id], $deleteIds));
            }
        }

        // Nouvelles photos
        if (!empty($newGalleryUploads)) {
            $ins = $pdo->prepare('INSERT INTO article_images (article_id, path, caption, position) VALUES (?, ?, ?, ?)');
            foreach ($newGalleryUploads as $g) {
                $ins->execute([$id, $g['path'], $g['caption'] !== '' ? $g['caption'] : null, $g['position']]);
            }
        }

        $pdo->commit();

        // Cleanup fichiers physiques uniquement après commit OK
        if ($newCoverPath && $article['image']) delete_uploaded_image($article['image']);
        if ($removeImage && $article['image'] && !$newCoverPath) delete_uploaded_image($article['image']);
        foreach ($deletedPaths as $p) delete_uploaded_image($p);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($newCoverPath) delete_uploaded_image($newCoverPath);
        foreach ($newGalleryUploads as $g) delete_uploaded_image($g['path']);
        json_error('Erreur d\'enregistrement : ' . $e->getMessage(), 500);
    }

    $scheduled = $setPublishAt && $publishAt && strtotime($publishAt) > time();
    json_response([
        'id'        => $id,
        'image_url' => $finalCover ? (preg_match('#^https?://#i', $finalCover) ? $finalCover : absolute_url($finalCover)) : null,
        'scheduled' => $scheduled,
        'message'   => $scheduled ? 'Article modifié — publication programmée le ' . format_publish_at($publishAt) : 'Article modifié',
    ]);
}

/**
 * Suppression d'un article (et de toute sa descendance via cascade SQL).
 * Réservé à l'auteur ou à un admin. Nettoie aussi les fichiers images sur le disque.
 */
function delete_article(PDO $pdo, array $user, int $id) {
    if ($id <= 0) json_error('id manquant pour la suppression', 400);

    $stmt = $pdo->prepare('SELECT user_id FROM articles WHERE id = ?');
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    if (!$article) json_error('Article introuvable', 404);
    if ((int)$article['user_id'] !== (int)$user['id'] && empty($user['is_admin'])) {
        json_error('Tu ne peux pas supprimer cet article', 403);
    }

    // Récupère l'article + tous ses descendants pour nettoyer leurs fichiers.
    $allIds = collect_descendant_ids($pdo, $id);
    $in = implode(',', array_fill(0, count($allIds), '?'));

    $covers = $pdo->prepare("SELECT image FROM articles WHERE id IN ($in) AND image IS NOT NULL");
    $covers->execute($allIds);
    $coverPaths = $covers->fetchAll(PDO::FETCH_COLUMN);

    $gal = $pdo->prepare("SELECT path FROM article_images WHERE article_id IN ($in)");
    $gal->execute($allIds);
    $galleryPaths = $gal->fetchAll(PDO::FETCH_COLUMN);

    // DELETE de la racine : la cascade SQL supprime enfants, images, commentaires.
    $pdo->prepare('DELETE FROM articles WHERE id = ?')->execute([$id]);

    foreach ($coverPaths as $p)   delete_uploaded_image($p);
    foreach ($galleryPaths as $p) delete_uploaded_image($p);

    json_response(['id' => $id, 'message' => 'Article supprimé']);
}

// Renvoie l'id de l'article + tous ses descendants (sous-articles, en profondeur).
function collect_descendant_ids(PDO $pdo, int $rootId): array {
    $ids   = [$rootId];
    $queue = [$rootId];
    $stmt  = $pdo->prepare('SELECT id FROM articles WHERE parent_id = ?');
    while ($queue) {
        $cur = array_shift($queue);
        $stmt->execute([$cur]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $childId) {
            $childId = (int)$childId;
            $ids[]   = $childId;
            $queue[] = $childId;
        }
    }
    return $ids;
}
