<?php
/**
 * Vue PUBLIQUE d'un CV — accessible sans connexion via un jeton de partage.
 *   cv_public.php?token=<jeton>
 * Le CV est rendu par le moteur partagé (assets/js/cv-builder.js → renderCvDocument).
 * Seuls les CV explicitement partagés (share_token) et non supprimés sont visibles.
 */

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/guard.php';

ensure_ready();

require __DIR__ . '/includes/cv.php';

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$cv    = $token !== '' ? get_cv_by_share_token($token) : null;

if (!$cv) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>CV introuvable</title>'
       . '<div style="font-family:system-ui;max-width:520px;margin:80px auto;text-align:center;color:#334">'
       . '<h1 style="font-size:1.4rem">Lien indisponible</h1>'
       . '<p>Ce CV n\'existe pas, ou son partage a été désactivé par son propriétaire.</p></div>';
    exit;
}

$profile = profile_from_cv_row($cv);
$profileJson = json_encode(
    $profile,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
);

function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES); }
$jsVer = @filemtime(__DIR__ . '/assets/js/cv-builder.js');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <title><?= e($cv['full_name']) ?> — CV</title>
    <meta name="robots" content="noindex">
    <style>
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0; display: flex; flex-direction: column;
            background: #e9edf3;
            font-family: "Segoe UI", system-ui, Arial, sans-serif;
        }
        .toolbar {
            display: flex; gap: 12px; align-items: center; justify-content: center;
            flex-wrap: wrap; padding: 12px 16px; background: #1d2b4d; flex: 0 0 auto;
        }
        .toolbar .name { color: #fff; font-weight: 700; margin-right: auto; font-size: 14px; }
        .toolbar button {
            font: inherit; color: #1d2b4d; background: #d4a23c; border: none;
            padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 14px;
        }
        .toolbar button:hover { filter: brightness(1.07); }
        .toolbar .admin-link {
            color: #cdd6ea; text-decoration: none; font-size: 13px; font-weight: 600;
            padding: 8px 12px; border: 1px solid #3a4a72; border-radius: 8px;
        }
        .toolbar .admin-link:hover { color: #fff; border-color: #d4a23c; }
        .stage { flex: 1 1 auto; overflow: auto; display: flex; justify-content: center; padding: 22px; }
        #cvFrame {
            width: 210mm; min-height: 297mm; border: none; background: #fff;
            box-shadow: 0 10px 40px rgba(0,0,0,.18);
        }
        @media (max-width: 800px) { #cvFrame { width: 100%; } }
    </style>
    <link id="theme-mario" rel="stylesheet" href="assets/css/mario-theme.css">
    <script src="assets/js/theme-switch.js"></script>
</head>
<body>
<div class="toolbar">
    <span class="name"><?= e($cv['full_name']) ?></span>
    <button id="btnPrint">🖨 Imprimer / Enregistrer en PDF</button>
    <a class="admin-link" href="admin.php">🔒 Espace admin</a>
</div>

<div class="stage">
    <iframe id="cvFrame" title="CV"></iframe>
</div>

<script>
    window.__CV_PROFILE__ = <?= $profileJson ?>;
</script>
<script src="assets/js/cv-builder.js?v=<?= $jsVer ?>"></script>
<script>
    (function () {
        var frame = document.getElementById('cvFrame');
        frame.srcdoc = window.renderCvDocument(false);
        document.getElementById('btnPrint').addEventListener('click', function () {
            try { frame.contentWindow.focus(); frame.contentWindow.print(); }
            catch (e) { window.print(); }
        });
    })();
</script>
</body>
</html>
