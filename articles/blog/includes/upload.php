<?php
// Helper d'upload d'image. Renvoie le chemin relatif (ex: "uploads/abc.jpg")
// ou jette une Exception en cas d'erreur.

function handle_image_upload(array $file) {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Erreur d'upload (code {$file['error']}).");
    }

    $maxBytes = 14 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Image trop lourde (max 14 Mo).');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Format non supporté. Utilise JPG, PNG, GIF ou WebP.');
    }

    $ext = $allowed[$mime];
    $name = bin2hex(random_bytes(12)) . '.' . $ext;

    $uploadsDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0775, true);
    }

    $dest = $uploadsDir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException("Impossible d'enregistrer le fichier (permissions ?).");
    }

    return 'uploads/' . $name;
}

// Transforme $_FILES['gallery'] (multi-upload) en liste de fichiers individuels.
function normalize_files_array(array $files) {
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [];
    }
    $count = count($files['name']);
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $out[] = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
    }
    return $out;
}

function delete_uploaded_image($relativePath) {
    if (!$relativePath) return;
    if (strpos($relativePath, 'uploads/') !== 0) return; // sécurité : ne supprime que dans /uploads
    $full = __DIR__ . '/../' . $relativePath;
    if (is_file($full)) {
        @unlink($full);
    }
}
