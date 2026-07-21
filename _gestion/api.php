<?php
/* ═══════════════════════════════════════════════════════════════════════
   GESTIONNAIRE DE FICHIERS — API JSON.
   Toutes les réponses sont en JSON. Auth obligatoire ; CSRF sur écritures.
   Actions : list, tree, read, save, upload, mkdir, newfile, rename, delete,
             download.
   ═══════════════════════════════════════════════════════════════════════ */

require __DIR__ . '/lib.php';
fs_boot();

/* Authentification obligatoire : session (navigateur) OU clé d'API (programme). */
$viaKey = fs_apikey_ok();
if (!$viaKey && !fs_authed()) fs_json(['ok' => false, 'error' => 'Non authentifié.'], 401);

$action = (string)($_REQUEST['action'] ?? '');

/* Actions modifiantes : jeton CSRF requis EN SESSION uniquement.
   L'auth par clé n'utilise pas de cookie ambiant → pas de risque CSRF. */
$mutating = ['save','upload','mkdir','newfile','rename','delete'];
if (!$viaKey && in_array($action, $mutating, true)) {
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!fs_csrf_ok(is_string($token) ? $token : '')) {
        fs_json(['ok' => false, 'error' => 'Jeton CSRF invalide.'], 403);
    }
}

try {
    switch ($action) {

    /* ─── Lister un dossier ─────────────────────────────────────────────── */
    case 'list': {
        $rel = (string)($_GET['path'] ?? '');
        $dir = fs_resolve($rel, true);
        if (!is_dir($dir)) fs_json(['ok' => false, 'error' => 'Pas un dossier.'], 400);

        $items = [];
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $abs   = $dir . '/' . $name;
            $isDir = is_dir($abs);
            $items[] = [
                'name'  => $name,
                'path'  => fs_relpath($abs),
                'dir'   => $isDir,
                'size'  => $isDir ? 0 : (int)@filesize($abs),
                'hsize' => $isDir ? '' : fs_human((int)@filesize($abs)),
                'mtime' => (int)@filemtime($abs),
                'text'  => !$isDir && fs_is_text($name),
                'self'  => fs_is_self($abs),
                'w'     => is_writable($abs),
            ];
        }
        // dossiers d'abord, puis alpha
        usort($items, function ($a, $b) {
            if ($a['dir'] !== $b['dir']) return $a['dir'] ? -1 : 1;
            return strcasecmp($a['name'], $b['name']);
        });
        fs_json(['ok' => true, 'path' => fs_relpath($dir), 'items' => $items]);
    }

    /* ─── Lire un fichier texte ─────────────────────────────────────────── */
    case 'read': {
        $file = fs_resolve((string)($_GET['path'] ?? ''), true);
        if (!is_file($file)) fs_json(['ok' => false, 'error' => 'Pas un fichier.'], 400);
        if (!fs_is_text(basename($file)))
            fs_json(['ok' => false, 'error' => 'Type non éditable en texte.'], 415);
        if ((int)filesize($file) > FS_MAX_EDIT)
            fs_json(['ok' => false, 'error' => 'Fichier trop volumineux pour l\'éditeur.'], 413);
        fs_json(['ok' => true, 'path' => fs_relpath($file),
                 'content' => file_get_contents($file)]);
    }

    /* ─── Enregistrer un fichier texte ──────────────────────────────────── */
    case 'save': {
        $file = fs_resolve((string)($_POST['path'] ?? ''), true);
        if (!is_file($file)) fs_json(['ok' => false, 'error' => 'Pas un fichier.'], 400);
        if (!fs_is_text(basename($file)))
            fs_json(['ok' => false, 'error' => 'Type non éditable.'], 415);
        $content = (string)($_POST['content'] ?? '');
        if (@file_put_contents($file, $content) === false)
            fs_json(['ok' => false, 'error' => 'Écriture impossible (droits ?).'], 500);
        fs_json(['ok' => true, 'path' => fs_relpath($file)]);
    }

    /* ─── Téléverser des fichiers ───────────────────────────────────────── */
    case 'upload': {
        $dir = fs_resolve((string)($_POST['path'] ?? ''), true);
        if (!is_dir($dir)) fs_json(['ok' => false, 'error' => 'Destination invalide.'], 400);
        if (empty($_FILES['files'])) fs_json(['ok' => false, 'error' => 'Aucun fichier.'], 400);

        $f = $_FILES['files'];
        $names = (array)$f['name'];
        $done = []; $errs = [];
        foreach ($names as $i => $orig) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errs[] = "$orig : erreur d'envoi"; continue;
            }
            if (!is_uploaded_file($f['tmp_name'][$i])) { $errs[] = "$orig : rejeté"; continue; }
            if ((int)$f['size'][$i] > FS_MAX_UPLOAD) { $errs[] = "$orig : trop volumineux"; continue; }
            // nom de fichier assaini (on garde l'extension, on nettoie le reste)
            $base = basename(str_replace('\\', '/', (string)$orig));
            $safe = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $base);
            $safe = ltrim($safe, '.') ?: 'fichier';
            $dest = $dir . '/' . $safe;
            // vérif de confinement de la destination
            fs_resolve(fs_relpath($dir) . '/' . $safe, false);
            if (move_uploaded_file($f['tmp_name'][$i], $dest)) $done[] = $safe;
            else $errs[] = "$safe : déplacement impossible";
        }
        fs_json(['ok' => empty($errs) || !empty($done), 'saved' => $done, 'errors' => $errs]);
    }

    /* ─── Créer un dossier ──────────────────────────────────────────────── */
    case 'mkdir': {
        $parent = fs_resolve((string)($_POST['path'] ?? ''), true);
        $name   = trim((string)($_POST['name'] ?? ''));
        $name   = basename(str_replace('\\', '/', $name));
        if ($name === '' || $name === '.' || $name === '..')
            fs_json(['ok' => false, 'error' => 'Nom invalide.'], 400);
        $target = fs_resolve(fs_relpath($parent) . '/' . $name, false);
        if (file_exists($target)) fs_json(['ok' => false, 'error' => 'Existe déjà.'], 409);
        if (!@mkdir($target, 0755))
            fs_json(['ok' => false, 'error' => 'Création impossible (droits ?).'], 500);
        fs_json(['ok' => true, 'path' => fs_relpath($target)]);
    }

    /* ─── Créer un fichier vide ─────────────────────────────────────────── */
    case 'newfile': {
        $parent = fs_resolve((string)($_POST['path'] ?? ''), true);
        $name   = trim((string)($_POST['name'] ?? ''));
        $name   = basename(str_replace('\\', '/', $name));
        if ($name === '' || $name === '.' || $name === '..')
            fs_json(['ok' => false, 'error' => 'Nom invalide.'], 400);
        $target = fs_resolve(fs_relpath($parent) . '/' . $name, false);
        if (file_exists($target)) fs_json(['ok' => false, 'error' => 'Existe déjà.'], 409);
        if (@file_put_contents($target, '') === false)
            fs_json(['ok' => false, 'error' => 'Création impossible (droits ?).'], 500);
        fs_json(['ok' => true, 'path' => fs_relpath($target)]);
    }

    /* ─── Renommer / déplacer ───────────────────────────────────────────── */
    case 'rename': {
        $src = fs_resolve((string)($_POST['path'] ?? ''), true);
        if (fs_is_self($src)) fs_json(['ok' => false, 'error' => 'Dossier de gestion protégé.'], 403);
        $newName = trim((string)($_POST['name'] ?? ''));
        $newName = basename(str_replace('\\', '/', $newName));
        if ($newName === '' || $newName === '.' || $newName === '..')
            fs_json(['ok' => false, 'error' => 'Nom invalide.'], 400);
        $dest = fs_resolve(fs_relpath(dirname($src)) . '/' . $newName, false);
        if (file_exists($dest)) fs_json(['ok' => false, 'error' => 'Cible déjà existante.'], 409);
        if (!@rename($src, $dest))
            fs_json(['ok' => false, 'error' => 'Renommage impossible.'], 500);
        fs_json(['ok' => true, 'path' => fs_relpath($dest)]);
    }

    /* ─── Supprimer (fichier ou dossier récursif) ───────────────────────── */
    case 'delete': {
        $target = fs_resolve((string)($_POST['path'] ?? ''), true);
        if ($target === realpath(FS_ROOT))
            fs_json(['ok' => false, 'error' => 'Racine non supprimable.'], 403);
        if (fs_is_self($target))
            fs_json(['ok' => false, 'error' => 'Dossier de gestion protégé.'], 403);
        if (!fs_rrmdir($target))
            fs_json(['ok' => false, 'error' => 'Suppression impossible (droits ?).'], 500);
        fs_json(['ok' => true]);
    }

    /* ─── Télécharger un fichier ────────────────────────────────────────── */
    case 'download': {
        $file = fs_resolve((string)($_GET['path'] ?? ''), true);
        if (!is_file($file)) { http_response_code(404); exit('Introuvable'); }
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($file));
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    default:
        fs_json(['ok' => false, 'error' => 'Action inconnue.'], 400);
    }
} catch (\Throwable $e) {
    fs_json(['ok' => false, 'error' => $e->getMessage()], 400);
}
