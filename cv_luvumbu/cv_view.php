<?php
/**
 * Aperçu d'un CV — rendu FIDÈLE (identique à l'aperçu de l'éditeur et à l'impression).
 * Le CV est construit par le moteur partagé (assets/js/cv-builder.js → renderCvDocument)
 * à partir du profil riche stocké en base. Impression / export PDF via le navigateur.
 * Accès réservé au propriétaire.
 */

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/guard.php';

ensure_ready();

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/cv.php';
require_login();

$userId = (int) $_SESSION['user_id'];
$cvId   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$cv     = $cvId ? get_cv($userId, $cvId) : null;
if (!$cv) {
    http_response_code(404);
    exit('CV introuvable.');
}

// Profil riche, ou amorce depuis les champs texte si l'éditeur n'a jamais servi.
$profile = get_cv_profile($userId, $cvId);
if ($profile === null) {
    $profile = seed_profile_from_cv($cv);
}
$profileJson = json_encode(
    $profile,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
);

function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <title><?= e($cv['full_name']) ?> — Aperçu CV</title>
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
        .toolbar a, .toolbar button {
            font: inherit; color: #fff; background: transparent;
            border: 1px solid rgba(255,255,255,.4);
            padding: 8px 16px; border-radius: 8px; cursor: pointer; text-decoration: none;
            font-size: 14px;
        }
        .toolbar a:hover, .toolbar button:hover { background: rgba(255,255,255,.12); }
        .toolbar button.primary { background: #d4a23c; border-color: #d4a23c; color: #1d2b4d; font-weight: 700; }
        .toolbar button.primary:hover { filter: brightness(1.07); background: #d4a23c; }
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
    <a href="mes_cv.php?id=<?= (int) $cv['id'] ?>">← Mes CV</a>
    <a href="cv_builder.php?id=<?= (int) $cv['id'] ?>">✏️ Éditer le CV</a>
    <button class="primary" id="btnPrint">🖨 Imprimer / Enregistrer en PDF</button>
</div>

<div class="stage">
    <iframe id="cvFrame" title="Aperçu du CV"></iframe>
</div>

<div id="toast" class="toast"></div>

<script>
    window.__CV_PROFILE__ = <?= $profileJson ?>;
</script>
<script src="assets/js/cv-builder.js?v=<?= @filemtime(__DIR__ . '/assets/js/cv-builder.js') ?>"></script>
<script>
    (function () {
        var frame = document.getElementById('cvFrame');
        // Rendu strictement identique à l'aperçu de l'éditeur.
        frame.srcdoc = window.renderCvDocument(false);
        document.getElementById('btnPrint').addEventListener('click', function () {
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {
                window.print();
            }
        });
    })();
</script>
</body>
</html>
