<?php
session_start();

function decode_chunk($data) {
    $data = explode(';base64,', $data);

    if (!is_array($data) || !isset($data[1])) {
        return false;
    }

    $data = base64_decode($data[1]);
    if (!$data) {
        return false;
    }

    return $data;
}

// $file_path: fichier cible: garde le même nom de fichier, dans le dossier uploads
$test = $_POST['file'];

$total = "";
for ($i = strlen($test) - 1; $i > 0; $i--) {
    $total = $total . $test[$i];
    if ($test[$i] == ".") {
        break;
    }
}

$total = strrev($total);

$_SESSION["name"] = time();

$file_path = '../src/img/' . $_SESSION["information_user_id_sha1"] . '/' . $_SESSION["name"] . $total;

$_SESSION["file_path"] = $file_path;
$_SESSION["total"] = $total;

$file_data = decode_chunk($_POST['file_data']);

if (false === $file_data) {
    echo "error";
}

// on ajoute le segment de données qu'on vient de recevoir au fichier qu'on est en train de ré-assembler:
file_put_contents($file_path, $file_data, FILE_APPEND);

// Redimensionner l'image
resizeImage($file_path, 500);

// nécessaire pour que JavaScript considère que la requête s'est bien passée:
echo json_encode([]);

function resizeImage($file_path, $max_width) {
    list($width, $height, $type) = getimagesize($file_path);

    if ($width <= $max_width) {
        return; // Si l'image est déjà plus petite que la largeur maximale, on ne fait rien.
    }

    $ratio = $height / $width;
    $new_width = $max_width;
    $new_height = $max_width * $ratio;

    switch ($type) {
        case IMAGETYPE_JPEG:
            $src = imagecreatefromjpeg($file_path);
            break;
        case IMAGETYPE_PNG:
            $src = imagecreatefrompng($file_path);
            break;
        case IMAGETYPE_GIF:
            $src = imagecreatefromgif($file_path);
            break;
        default:
            return; // Type d'image non supporté
    }

    $dst = imagecreatetruecolor($new_width, $new_height);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($dst, $file_path);
            break;
        case IMAGETYPE_PNG:
            imagepng($dst, $file_path);
            break;
        case IMAGETYPE_GIF:
            imagegif($dst, $file_path);
            break;
    }

    imagedestroy($src);
    imagedestroy($dst);
}
?>
