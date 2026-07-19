<?php
/**
 * Éditeur de CV WYSIWYG (porté depuis cv_enligne).
 * L'aperçu en direct EST le rendu final : ce qu'on voit est ce qui s'imprime.
 * Le profil riche est stocké en base (colonne cvs.profile_json) via api/cv_profile.php.
 * Accès réservé au propriétaire du CV.
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

// Profil existant, ou amorce depuis les champs texte si l'éditeur n'a jamais servi.
$profile = get_cv_profile($userId, $cvId);
if ($profile === null) {
    $profile = seed_profile_from_cv($cv);
}
$profileJson = json_encode(
    $profile,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
);
$csrf = csrf_token();

function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<title>Éditeur CV — <?= e($cv['full_name']) ?></title>
<style>
    /* =============================================
       cv_enligne — style inspiré du dashboard BK
       Dark theme, accent violet, Inter font, gradients
       ============================================= */
    :root {
      /* Brand */
      --brand: #7c6cf7;
      --brand-light: #a99ffe;
      --brand-glow: #7c6cf730;
      --brand-subtle: #7c6cf710;

      /* Backgrounds */
      --bg-body: #070a12;
      --bg-card: #0e1325;
      --bg-card-hover: #131a33;
      --bg-surface: #0a0f1c;
      --bg-input: #080c16;

      /* Borders */
      --border: #1a2240;
      --border-light: #232e50;

      /* Text */
      --text-primary: #e0e6f0;
      --text-secondary: #6b7794;
      --text-muted: #3e4b68;
      --text-white: #f4f6fb;
      --text-link: var(--brand-light);

      /* Accents */
      --green: #34d399;
      --green-bg: #34d39912;
      --cyan: #22d3ee;
      --cyan-bg: #22d3ee12;
      --amber: #fbbf24;
      --amber-bg: #fbbf2412;
      --rose: #fb7185;
      --rose-bg: #fb718512;
      --red: #ef4444;
      --red-bg: #ef444412;

      /* Spacing */
      --space-xs: 4px; --space-sm: 8px; --space-md: 16px;
      --space-lg: 24px; --space-xl: 32px;

      /* Radius */
      --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px;

      /* Shadows */
      --shadow-md: 0 4px 20px rgba(0,0,0,.3);
      --shadow-lg: 0 8px 40px rgba(0,0,0,.4);

      /* Transition */
      --ease: cubic-bezier(.4, 0, .2, 1);
      --duration: .25s;

      /* Typography */
      --font: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
      --font-mono: 'JetBrains Mono', 'Cascadia Code', 'Fira Code', monospace;

      color-scheme: dark;
    }

    /* ---- Thème clair (override via attribut [data-theme="light"]) ------ */
    html[data-theme="light"] {
      --bg-body: #f5f7fb;
      --bg-card: #ffffff;
      --bg-card-hover: #eef2f7;
      --bg-surface: #fafbfd;
      --bg-input: #ffffff;

      --border: #e2e8f0;
      --border-light: #cbd5e1;

      --text-primary: #1e293b;
      --text-secondary: #64748b;
      --text-muted: #94a3b8;
      --text-white: #0f172a;

      --green-bg:  #dcfce7;
      --cyan-bg:   #cffafe;
      --amber-bg:  #fef3c7;
      --rose-bg:   #ffe4e6;
      --red-bg:    #fee2e2;
      --brand-subtle: #7c6cf710;

      --shadow-md: 0 4px 14px rgba(15, 23, 42, .08);
      --shadow-lg: 0 12px 30px rgba(15, 23, 42, .12);

      color-scheme: light;
    }
    html[data-theme="light"] body {
      background-image:
        radial-gradient(1200px 600px at 80% -10%, #7c6cf710, transparent 60%),
        radial-gradient(800px 500px at -10% 110%, #22d3ee10, transparent 60%);
    }
    /* En mode clair, la barre de bordure du détail est plus douce */
    html[data-theme="light"] tr.detail > td { border-bottom-color: var(--brand); }
    /* Modal overlay un peu moins sombre en clair */
    html[data-theme="light"] .overlay { background: rgba(15, 23, 42, 0.45); }
    /* Toast lisible en clair */
    html[data-theme="light"] .toast { color: var(--text-white); background: var(--bg-card); }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      font-family: var(--font);
      background: var(--bg-body);
      background-image:
        radial-gradient(1200px 600px at 80% -10%, var(--brand-subtle), transparent 60%),
        radial-gradient(800px 500px at -10% 110%, #22d3ee0a, transparent 60%);
      background-attachment: fixed;
      color: var(--text-primary);
      min-height: 100vh;
      line-height: 1.5;
      font-size: 14px;
      -webkit-font-smoothing: antialiased;
      max-width: 1440px; margin: 0 auto;
      padding: var(--space-xl) var(--space-lg);
    }

    /* Scrollbar */
    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--border-light); border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }
    ::selection { background: var(--brand); color: #fff; }

    /* ---- En-tête ------------------------------------------------------- */
    .header { display: flex; align-items: center; justify-content: space-between;
              flex-wrap: wrap; gap: var(--space-md); margin-bottom: var(--space-lg); }
    .header h1 {
      font-size: 28px; margin: 0; font-weight: 800; letter-spacing: -0.5px;
      background: linear-gradient(135deg, var(--brand-light), var(--brand));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .header .actions { display: flex; gap: var(--space-sm); align-items: center; }
    .header-left { display: flex; align-items: center; gap: var(--space-md); }

    /* Bouton Réglages — distinct, rouge, en haut à gauche */
    .btn-settings {
      background: linear-gradient(135deg, var(--red), var(--rose));
      color: #fff;
      border: 1px solid transparent;
      font-weight: 700;
      padding: 9px 18px;
      box-shadow: 0 2px 12px rgba(239, 68, 68, 0.3);
      letter-spacing: 0.2px;
    }
    .btn-settings:hover {
      background: linear-gradient(135deg, #dc2626, var(--red));
      color: #fff;
      border-color: transparent;
      transform: translateY(-1px);
      box-shadow: 0 4px 18px rgba(239, 68, 68, 0.45);
    }
    html[data-theme="light"] .btn-settings {
      box-shadow: 0 2px 10px rgba(239, 68, 68, 0.25);
    }

    /* Bouton Profil — violet, à côté de Réglages */
    .btn-profile {
      background: linear-gradient(135deg, var(--brand), var(--brand-light));
      color: #fff;
      border: 1px solid transparent;
      font-weight: 700;
      padding: 9px 18px;
      box-shadow: 0 2px 12px var(--brand-glow);
      letter-spacing: 0.2px;
    }
    .btn-profile:hover {
      background: linear-gradient(135deg, #6b5cf0, var(--brand));
      color: #fff;
      border-color: transparent;
      transform: translateY(-1px);
      box-shadow: 0 4px 18px var(--brand-glow);
    }

    /* Profil — sections + lignes dynamiques, réordonnables */
    .profile-sections { display: flex; flex-direction: column; gap: var(--space-md); }
    .profile-section {
      background: var(--bg-input);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      padding: var(--space-md);
    }
    .profile-section-head {
      display: flex; align-items: center; gap: var(--space-sm);
      margin-bottom: var(--space-sm);
    }
    .profile-section-head .title {
      flex: 1; font-size: 13px; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.5px;
      color: var(--brand-light);
    }
    /* Titre de section éditable : discret au repos, se révèle au survol/focus. */
    .profile-section-head input.title-input {
      min-width: 0;
      background: transparent;
      border: 1px solid transparent;
      border-radius: var(--radius-sm);
      padding: 6px 8px;
      font-family: inherit;
      transition: border-color var(--duration) var(--ease), background var(--duration) var(--ease);
    }
    .profile-section-head input.title-input:hover { border-color: var(--border-light); }
    .profile-section-head input.title-input:focus {
      outline: none; border-color: var(--brand); background: var(--bg-input);
    }
    .profile-section-head .title-reset { flex: 0 0 auto; width: auto; padding: 4px 8px; }
    .profile-section-rows { display: flex; flex-direction: column; gap: var(--space-sm); }
    .profile-row {
      display: grid; gap: 8px;
      grid-template-columns: 22px 1fr 124px;
      align-items: center;
      padding: 8px;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      transition: opacity var(--duration) var(--ease);
    }
    .profile-row.hidden-item { opacity: 0.45; }
    .profile-row input[type="checkbox"], .profile-section-head input[type="checkbox"] {
      width: 16px; height: 16px; accent-color: var(--brand); cursor: pointer; margin: 0;
    }
    .profile-section.hidden-section { opacity: 0.5; }
    .profile-row .fields {
      display: grid; gap: 8px;
      grid-template-columns: var(--fields, 1fr);
      min-width: 0;
    }
    .profile-row input, .profile-row select {
      padding: 8px 10px; border-radius: var(--radius-sm);
      border: 1px solid var(--border); background: var(--bg-input);
      color: var(--text-primary); font-size: 12px; font-family: inherit;
      outline: none; min-width: 0;
    }
    .profile-row select { padding: 8px 6px; cursor: pointer; font-weight: 600; }
    .profile-row input:focus, .profile-row select:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 2px var(--brand-subtle);
    }
    .row-actions, .section-actions {
      display: flex; gap: 4px; justify-content: flex-end;
    }
    .icon-btn {
      width: 28px; height: 28px; padding: 0;
      display: inline-flex; align-items: center; justify-content: center;
      background: var(--bg-input); color: var(--text-secondary);
      border: 1px solid var(--border-light); border-radius: var(--radius-sm);
      cursor: pointer; font-size: 13px; line-height: 1;
    }
    .icon-btn:hover { background: var(--bg-card-hover); color: var(--brand-light); border-color: var(--brand); }
    .icon-btn:disabled { opacity: 0.3; cursor: not-allowed; }
    .icon-btn.danger { background: var(--red-bg); color: var(--red); border-color: #ef444440; }
    .icon-btn.danger:hover { background: var(--red); color: #fff; }

    /* Poignée de glisser-déposer + repères de réordonnancement libre */
    .drag-handle { cursor: grab; }
    .drag-handle:hover { color: var(--brand); border-color: var(--brand); }
    .drag-handle:active { cursor: grabbing; }
    .profile-row.dragging, .profile-section.dragging {
      opacity: 0.4; outline: 2px dashed var(--brand); outline-offset: 1px;
    }
    .profile-row.drag-over-top,
    .profile-section.drag-over-top    { box-shadow: 0 -3px 0 0 var(--brand); }
    .profile-row.drag-over-bottom,
    .profile-section.drag-over-bottom { box-shadow: 0  3px 0 0 var(--brand); }

    /* Curseur de taille par section (agrandit/réduit le texte de la section dans le CV) */
    .section-size {
      display: flex; align-items: center; gap: 6px;
      font-size: 11px; font-weight: 600; color: var(--text-secondary);
      flex-shrink: 0; cursor: pointer;
    }
    .section-size-icon { font-weight: 800; color: var(--text-muted); font-size: 13px; }
    .section-size-range {
      width: 88px; height: 16px; accent-color: var(--brand);
      cursor: pointer; margin: 0;
    }
    .section-size-val {
      min-width: 40px; text-align: right;
      color: var(--brand-light); font-variant-numeric: tabular-nums;
    }
    .row-add {
      align-self: flex-start;
      background: var(--brand-subtle); color: var(--brand-light);
      border: 1px dashed var(--brand);
      padding: 6px 12px; font-size: 12px; margin-top: 8px;
    }
    .row-add:hover { background: var(--brand-glow); }

    .profile-row-desc {
      grid-column: 1 / -1;
      display: flex; flex-direction: column; gap: 4px;
      padding: 6px 0 2px 30px; margin-top: 4px;
      border-top: 1px dashed var(--border-light);
    }
    .profile-row-desc-item {
      display: grid; grid-template-columns: 22px 1fr 28px;
      gap: 8px; align-items: start;
    }
    .profile-row-desc-item.hidden-item { opacity: 0.45; }
    .profile-row-desc-item textarea {
      resize: none; min-height: 30px; max-height: 240px;
      padding: 6px 10px; border-radius: var(--radius-sm);
      border: 1px solid var(--border); background: var(--bg-input);
      color: var(--text-primary); font-size: 12px;
      font-family: inherit; line-height: 1.4; outline: none;
    }
    .profile-row-desc-item textarea:focus {
      border-color: var(--brand); box-shadow: 0 0 0 2px var(--brand-subtle);
    }
    .profile-row-desc-add {
      align-self: flex-start; margin-top: 2px;
      background: transparent; color: var(--brand-light);
      border: 1px dashed var(--brand-light);
      padding: 4px 10px; font-size: 11px; cursor: pointer;
      border-radius: var(--radius-sm);
    }
    .profile-row-desc-add:hover { background: var(--brand-subtle); }

    /* Profil — sélecteurs de couleurs */
    .color-pickers { display: flex; gap: var(--space-md); align-items: center; flex-wrap: wrap; }
    .color-pickers > label {
      display: flex; align-items: center; gap: 6px;
      font-size: 12px; color: var(--text-secondary); font-weight: 600;
    }
    .color-pickers input[type="color"] {
      width: 42px; height: 32px; padding: 2px; cursor: pointer;
      border: 1px solid var(--border-light); border-radius: var(--radius-sm);
      background: var(--bg-input);
    }
    .color-presets { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
    .color-preset {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 4px 10px 4px 6px; cursor: pointer;
      background: var(--bg-card); border: 1px solid var(--border-light);
      border-radius: 999px; font-size: 11px; color: var(--text-secondary);
      transition: all var(--duration) var(--ease);
    }
    .color-preset:hover { border-color: var(--brand); color: var(--text-primary); }
    .color-preset.active { border-color: var(--brand); color: var(--brand-light); background: var(--brand-subtle); }
    .color-preset .swatches { display: inline-flex; }
    .color-preset .swatch {
      width: 14px; height: 14px; border-radius: 50%;
      border: 2px solid var(--bg-card);
    }
    .color-preset .swatch + .swatch { margin-left: -6px; }

    .color-advanced { margin-top: 12px; border-top: 1px dashed var(--border-light); padding-top: 10px; }
    .color-advanced > summary {
      cursor: pointer; font-size: 12px; font-weight: 700; color: var(--text-secondary);
      padding: 4px 0; user-select: none;
    }
    .color-advanced > summary:hover { color: var(--text-primary); }
    .color-advanced-hint { font-size: 11px; color: var(--text-secondary);
      margin: 6px 0 10px; font-style: italic; }
    .color-extra-grid {
      display: grid; grid-template-columns: repeat(2, minmax(160px, 1fr));
      gap: 8px 14px; margin-top: 4px;
    }
    .color-extra-row {
      display: flex; align-items: center; gap: 8px;
      font-size: 12px; color: var(--text-secondary); font-weight: 600;
      padding: 4px 6px; border-radius: var(--radius-sm);
      transition: background var(--duration) var(--ease);
    }
    .color-extra-row.on { background: var(--brand-subtle); color: var(--text-primary); }
    .color-extra-row input[type="checkbox"] { accent-color: var(--brand); cursor: pointer; margin: 0; }
    .color-extra-row .lbl { flex: 1; cursor: pointer; }
    .color-extra-row input[type="color"] {
      width: 32px; height: 26px; padding: 2px; cursor: pointer;
      border: 1px solid var(--border-light); border-radius: var(--radius-sm);
      background: var(--bg-input);
    }
    .color-extra-row input[type="color"]:disabled { opacity: 0.4; cursor: not-allowed; }

    .template-presets { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
    .template-preset {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 6px 12px 6px 8px; cursor: pointer;
      background: var(--bg-card); border: 1px solid var(--border-light);
      border-radius: var(--radius-sm); font-size: 12px; color: var(--text-secondary);
      transition: all var(--duration) var(--ease);
    }
    .template-preset:hover { border-color: var(--brand); color: var(--text-primary); }
    .template-preset.active { border-color: var(--brand); color: var(--brand-light); background: var(--brand-subtle); }
    .template-preset .swatches { display: inline-flex; }
    .template-preset .swatch {
      width: 14px; height: 14px; border-radius: 4px;
      border: 1px solid var(--border-light);
    }
    .template-preset .swatch + .swatch { margin-left: -6px; }

    /* Profil — photo */
    .photo-row { display: flex; gap: var(--space-md); align-items: center; flex-wrap: wrap; }
    .photo-preview {
      width: 84px; height: 84px; border-radius: 50%;
      background: var(--bg-input); border: 2px dashed var(--border-light);
      display: flex; align-items: center; justify-content: center;
      overflow: hidden; flex-shrink: 0; cursor: pointer;
      transition: border-color var(--duration) var(--ease);
    }
    .photo-preview:hover { border-color: var(--brand); }
    .photo-preview.has-image { border-style: solid; border-color: var(--brand); }
    .photo-preview img { width: 100%; height: 100%; object-fit: cover; }
    .photo-placeholder { font-size: 32px; opacity: 0.4; }
    .photo-actions { display: flex; flex-direction: column; gap: 6px; align-items: flex-start; }
    .photo-include-label {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 12px; color: var(--text-secondary); cursor: pointer;
    }
    .photo-pos-btn.active {
      background: var(--brand); color: #fff; border-color: var(--brand);
    }
    .photo-shape-btn.active {
      background: var(--brand); color: #fff; border-color: var(--brand);
    }
    .photo-include-label input[type="checkbox"] {
      width: 14px; height: 14px; accent-color: var(--brand); cursor: pointer; margin: 0;
    }

    /* Profil — séparation nette : formulaire à gauche, aperçu indépendant à droite */
    #profileOverlay {
      background: transparent;
      backdrop-filter: none; -webkit-backdrop-filter: none;
      padding: 16px;
      align-items: stretch; justify-content: flex-start;
      pointer-events: none;
    }
    #profileOverlay > .modal {
      max-width: 600px; width: 600px; max-height: calc(100vh - 32px);
      pointer-events: auto;
      box-shadow: var(--shadow-lg);
      transition: width .18s ease, max-width .18s ease;
    }
    /* Aperçu masqué : le panneau de réglages occupe toute la largeur de la page. */
    #profileOverlay.preview-off > .modal {
      max-width: none; width: calc(100vw - 32px);
    }

    /* Aperçu : panneau indépendant fixé à droite de l'écran, au-dessus du voile sombre */
    #profilePreviewView {
      position: fixed; top: 16px; right: 16px; bottom: 16px;
      left: calc(600px + 32px); /* à droite du modal + gap */
      display: flex; flex-direction: column;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: var(--space-md);
      box-shadow: var(--shadow-lg);
      z-index: 51; /* au-dessus de l'overlay (50) → pas de flou hérité */
    }
    #profilePreviewView[hidden] { display: none; }
    #profilePreviewView iframe {
      flex: 1; width: 100%; border: 1px solid var(--border);
      border-radius: var(--radius-md); background: #fff; min-height: 0;
    }
    .preview-toolbar {
      display: flex; align-items: center; gap: 8px; margin-bottom: 8px;
      font-size: 12px; color: var(--text-secondary);
    }
    .preview-toolbar .badge {
      padding: 2px 10px; background: var(--brand-subtle); color: var(--brand-light);
      border-radius: 999px; font-weight: 700; font-size: 11px;
      letter-spacing: 0.3px; text-transform: uppercase;
    }
    .preview-hint { white-space: nowrap; }

    /* Voile assombrissant la page derrière le modal (sans bloquer le pane d'aperçu) */
    #profileOverlay::before {
      content: ''; position: fixed; inset: 0;
      background: rgba(0, 0, 0, 0.55);
      backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
      pointer-events: none; z-index: -1;
    }

    .btn-preview-toggle {
      background: var(--brand-subtle); color: var(--brand-light);
      border: 1px solid var(--brand);
    }
    .btn-preview-toggle.active {
      background: linear-gradient(135deg, var(--brand), var(--brand-light));
      color: #fff; border-color: transparent;
    }

    button {
      font-family: inherit; font-size: 13px; cursor: pointer;
      padding: 9px 16px; border-radius: var(--radius-sm);
      border: 1px solid var(--border-light); background: var(--bg-card);
      color: var(--text-primary);
      transition: all var(--duration) var(--ease);
    }
    button:hover { background: var(--bg-card-hover); border-color: var(--brand); color: var(--brand-light); }
    button.primary {
      background: linear-gradient(135deg, var(--brand), #6366f1);
      color: #fff; border-color: transparent;
      box-shadow: 0 2px 12px var(--brand-glow);
    }
    button.primary:hover { box-shadow: 0 4px 20px var(--brand-glow); transform: translateY(-1px); color: #fff; }

    .search { position: relative; flex: 1; min-width: 240px; max-width: 460px; }
    .search input {
      width: 100%; padding: 10px 14px 10px 36px; border-radius: var(--radius-sm);
      border: 1px solid var(--border); background: var(--bg-input);
      color: var(--text-primary); font-size: 13px; outline: none; font-family: inherit;
      transition: all var(--duration) var(--ease);
    }
    .search input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-subtle); }
    .search::before { content: '🔍'; position: absolute; left: 12px; top: 50%;
                      transform: translateY(-50%); font-size: 13px; opacity: 0.4; }

    /* ---- Stats cards (style BK) ---------------------------------------- */
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
             gap: var(--space-md); margin-bottom: var(--space-lg); }
    .stat {
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: var(--radius-md); padding: var(--space-lg);
      position: relative; overflow: hidden;
      transition: all .3s var(--ease);
    }
    .stat::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, var(--brand), var(--rose));
      opacity: 0; transition: opacity .3s var(--ease);
    }
    .stat:hover { border-color: var(--border-light); transform: translateY(-3px); box-shadow: var(--shadow-lg); }
    .stat:hover::before { opacity: 1; }
    .stat .lbl { color: var(--text-secondary); font-size: 11px; font-weight: 700;
                 text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: var(--space-xs); }
    .stat .val {
      font-size: 32px; font-weight: 800; line-height: 1.1; letter-spacing: -1px;
      background: linear-gradient(135deg, var(--brand-light), var(--brand));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .stat .sub { color: var(--text-muted); font-size: 11px; margin-top: var(--space-xs); }

    /* ---- Filtres (chips) ----------------------------------------------- */
    .filters { display: flex; gap: 6px; align-items: center; flex-wrap: wrap;
               margin-bottom: var(--space-md); font-size: 12px; color: var(--text-secondary); }
    .chip {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 5px 12px; border-radius: 999px; cursor: pointer;
      border: 1px solid var(--border-light); background: var(--bg-card);
      color: var(--text-primary); font-size: 12px;
      transition: all var(--duration) var(--ease);
    }
    .chip:hover { border-color: var(--brand); color: var(--brand-light); }
    .chip.active {
      background: linear-gradient(135deg, var(--brand), #6366f1);
      color: #fff; border-color: transparent;
      box-shadow: 0 2px 8px var(--brand-glow);
    }
    .chip .count { opacity: 0.6; font-weight: 700; font-variant-numeric: tabular-nums; }
    .chip.active .count { opacity: 0.9; }

    /* ---- Curseur SMIC -------------------------------------------------- */
    .smic-slider-row {
      display: flex; align-items: center; gap: var(--space-md);
      padding: 10px var(--space-md); margin-bottom: var(--space-md);
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: var(--radius-md); flex-wrap: wrap;
    }
    .smic-slider-lbl { font-size: 12px; color: var(--text-secondary); font-weight: 600; }
    .smic-slider-val {
      font-size: 14px; font-weight: 700; color: var(--brand-light);
      font-variant-numeric: tabular-nums; min-width: 48px;
    }
    .smic-slider-suffix { font-size: 11px; color: var(--text-muted); }
    .smic-slider-count {
      font-size: 11px; color: var(--text-secondary); font-weight: 600;
      background: var(--bg-input); padding: 4px 10px; border-radius: 999px;
      border: 1px solid var(--border); margin-left: auto;
    }
    .smic-slider-reset {
      background: var(--red-bg); color: var(--rose); border: 1px solid #ef444433;
      padding: 4px 10px; font-size: 12px;
    }
    .smic-slider-reset:hover { background: var(--red); color: #fff; border-color: var(--red); }

    /* Slider lui-même — style "radio" rond */
    #smicSlider {
      flex: 1; min-width: 200px; max-width: 400px;
      -webkit-appearance: none; appearance: none;
      height: 6px; border-radius: 999px;
      background: var(--border-light);
      cursor: pointer; outline: none;
      transition: background 0.2s;
    }
    /* Thumb (le bouton circulaire) */
    #smicSlider::-webkit-slider-thumb {
      -webkit-appearance: none; appearance: none;
      width: 18px; height: 18px;
      border-radius: 50%;
      background: var(--brand);
      border: 3px solid var(--bg-body);
      box-shadow: 0 2px 8px var(--brand-glow);
      cursor: grab;
      transition: transform 0.15s, box-shadow 0.15s;
    }
    #smicSlider::-webkit-slider-thumb:hover {
      transform: scale(1.15);
      box-shadow: 0 0 0 6px var(--brand-subtle);
    }
    #smicSlider::-webkit-slider-thumb:active { cursor: grabbing; }
    #smicSlider::-moz-range-thumb {
      width: 18px; height: 18px;
      border-radius: 50%;
      background: var(--brand);
      border: 3px solid var(--bg-body);
      box-shadow: 0 2px 8px var(--brand-glow);
      cursor: grab; transition: transform 0.15s;
    }
    #smicSlider::-moz-range-thumb:hover { transform: scale(1.15); }
    /* Track rempli */
    #smicSlider.active {
      background: linear-gradient(90deg, var(--brand) var(--fill, 0%), var(--border-light) var(--fill, 0%));
    }

    /* ---- Filtre intelligent (smart filter) ----------------------------- */
    .smart-filter {
      display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-sm);
      padding: var(--space-md);
      margin-bottom: var(--space-md);
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: var(--radius-md);
    }
    .smart-filter select, .smart-filter input[type="text"] {
      padding: 8px 12px; border-radius: var(--radius-sm);
      border: 1px solid var(--border); background: var(--bg-input);
      color: var(--text-primary); font-size: 13px; outline: none; font-family: inherit;
      transition: all var(--duration) var(--ease);
    }
    .smart-filter input[type="text"] { flex: 1; min-width: 180px; }
    .smart-filter input[type="text"]:focus,
    .smart-filter select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-subtle); }
    .smart-filter select option { background: var(--bg-card); }
    .smart-filter .lbl { font-size: 12px; color: var(--text-secondary); margin-right: 4px; font-weight: 600; }

    .btn-include { background: var(--green-bg); color: var(--green); border: 1px solid #34d39940; }
    .btn-include:hover { background: var(--green); color: #052e1a; border-color: var(--green); }
    .btn-exclude { background: var(--red-bg); color: var(--rose); border: 1px solid #ef444440; }
    .btn-exclude:hover { background: var(--red); color: #fff; border-color: var(--red); }

    .rules { display: flex; flex-wrap: wrap; gap: 6px; margin-top: var(--space-sm); margin-bottom: var(--space-md); }
    .rule {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 4px 4px 4px 12px; border-radius: 999px;
      font-size: 12px; font-weight: 600;
    }
    .rule.include { background: var(--green-bg); color: var(--green); border: 1px solid #34d39933; }
    .rule.exclude { background: var(--red-bg); color: var(--rose); border: 1px solid #ef444433; }
    .rule .field-tag {
      opacity: 0.7; font-weight: 800; text-transform: uppercase;
      font-size: 9.5px; letter-spacing: 0.06em;
    }
    .rule .x { display: inline-flex; align-items: center; justify-content: center;
               width: 18px; height: 18px; border-radius: 50%; cursor: pointer;
               background: rgba(255,255,255,0.06); transition: background var(--duration) var(--ease); }
    .rule .x:hover { background: rgba(255,255,255,0.18); }
    .rule .empty-msg { font-style: italic; color: var(--text-muted); border: 0; background: transparent; padding-left: 0; }

    /* ---- Compteur de résultats ----------------------------------------- */
    .results-summary {
      display: flex; align-items: center; gap: var(--space-lg);
      margin: var(--space-md) 0 var(--space-md);
      padding: var(--space-lg) var(--space-xl);
      flex-wrap: wrap;
      font-size: 15px; color: var(--text-primary);
      background: linear-gradient(135deg, var(--brand-subtle), #22d3ee10);
      border: 2px solid var(--brand);
      border-radius: var(--radius-lg);
      box-shadow: 0 4px 24px var(--brand-glow);
      position: relative; overflow: hidden;
    }
    .results-summary::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, var(--brand), var(--cyan), var(--brand-light));
    }
    .results-summary .icon { font-size: 36px; line-height: 1; }
    .results-summary .count {
      font-size: 56px; font-weight: 800; line-height: 1;
      font-variant-numeric: tabular-nums; letter-spacing: -2px;
      background: linear-gradient(135deg, var(--brand-light), var(--cyan));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .results-summary .label {
      font-size: 13px; color: var(--text-secondary); font-weight: 700;
      text-transform: uppercase; letter-spacing: 1px; line-height: 1.3;
    }
    .results-summary .total {
      font-size: 13px; color: var(--text-muted); font-weight: 600;
      margin-left: auto;
    }
    .results-summary .filter-pill {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 4px 12px; border-radius: 999px;
      background: var(--brand); color: #fff;
      font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
    }
    html[data-theme="light"] .results-summary {
      background: linear-gradient(135deg, #7c6cf715, #22d3ee15);
    }
    .results-summary .total { color: var(--text-muted); font-weight: 500; }
    .results-summary .filter-pill {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 3px 10px; border-radius: 999px;
      background: var(--brand-subtle); color: var(--brand-light);
      border: 1px solid #7c6cf733;
      font-size: 11px; font-weight: 600;
    }
    .results-summary .label-suffix { color: var(--text-secondary); }

    /* ---- Table --------------------------------------------------------- */
    .table-wrap { background: var(--bg-card); border: 1px solid var(--border);
                  border-radius: var(--radius-md); overflow: hidden; }
    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: left; padding: 12px 16px; vertical-align: top; font-size: 13px; }
    th {
      background: var(--bg-surface); font-weight: 700; color: var(--text-secondary);
      font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px;
      position: sticky; top: 0; z-index: 1; user-select: none; cursor: pointer;
      border-bottom: 1px solid var(--border);
      transition: color var(--duration) var(--ease);
    }
    th:hover { color: var(--text-primary); }
    th .arrow { opacity: 0.3; margin-left: 4px; }
    th.sorted .arrow { opacity: 1; color: var(--brand-light); }
    td { border-bottom: 1px solid rgba(26, 34, 64, .5); color: var(--text-primary); }

    tr.row { cursor: pointer; transition: background var(--duration) var(--ease); }
    tr.row:hover td { background: var(--bg-card-hover); }
    tr.row.open td { background: var(--bg-card-hover); border-bottom-color: transparent; }
    tr.row .toggle { display: inline-block; width: 14px; color: var(--text-muted); transition: transform 0.15s; }
    tr.row.open .toggle { transform: rotate(90deg); color: var(--brand-light); }

    .title-cell { font-weight: 600; max-width: 380px; color: var(--text-white); }
    .company { color: var(--text-secondary); }
    .nowrap { white-space: nowrap; }
    a { color: var(--text-link); text-decoration: none; transition: color .15s var(--ease); }
    a:hover { color: var(--brand-light); text-decoration: underline; }

    /* ---- Badges contrats ----------------------------------------------- */
    .badge {
      display: inline-block; padding: 3px 10px; border-radius: 999px;
      font-size: 11px; font-weight: 600; letter-spacing: 0.3px;
    }
    .badge.cdi    { background: var(--green-bg); color: var(--green); border: 1px solid #34d39933; }
    .badge.cdd    { background: var(--amber-bg); color: var(--amber); border: 1px solid #fbbf2433; }
    .badge.interim, .badge.intérim { background: var(--cyan-bg); color: var(--cyan); border: 1px solid #22d3ee33; }
    .badge.stage  { background: var(--brand-subtle); color: var(--brand-light); border: 1px solid #7c6cf733; }
    .badge.altern { background: var(--rose-bg); color: var(--rose); border: 1px solid #fb718533; }
    .badge.other  { background: var(--bg-input); color: var(--text-secondary); border: 1px solid var(--border); }

    /* ---- Détail (expansion ligne) -------------------------------------- */
    tr.detail { display: none; }
    tr.detail.show { display: table-row; }
    tr.detail > td { background: var(--bg-surface); padding: 0;
                     border-bottom: 2px solid var(--brand); border-bottom-width: 2px; }
    .detail-inner { padding: var(--space-md) var(--space-lg) var(--space-lg); }
    .detail-grid { display: grid; grid-template-columns: 120px 1fr;
                   gap: 6px var(--space-md); font-size: 12px; margin-bottom: var(--space-md); }
    .detail-grid .k { color: var(--text-secondary); font-weight: 700;
                      text-transform: uppercase; letter-spacing: 0.5px; font-size: 10.5px; }
    .detail-grid .v { word-break: break-word; color: var(--text-primary); }
    .detail-grid .v.empty { color: var(--rose); font-style: italic; opacity: 0.7; }

    .tabs { display: flex; gap: 0; border-bottom: 1px solid var(--border);
            margin-top: var(--space-sm); }
    .tabs button {
      background: transparent; border: 0; padding: 9px 16px;
      color: var(--text-secondary); font-size: 12px; font-weight: 600;
      border-radius: 0; border-bottom: 2px solid transparent;
    }
    .tabs button:hover { color: var(--text-primary); background: transparent; border-color: transparent; }
    .tabs button.active { color: var(--brand-light); border-bottom-color: var(--brand); }

    .tab-panel { display: none; padding: var(--space-md) 0; }
    .tab-panel.active { display: block; }
    .tab-panel pre {
      margin: 0; white-space: pre-wrap; font-size: 12px; line-height: 1.6;
      background: var(--bg-input); border: 1px solid var(--border);
      padding: var(--space-md); border-radius: var(--radius-sm);
      max-height: 380px; overflow: auto; color: var(--text-primary);
      font-family: var(--font-mono);
    }
    .tab-panel.html-render { background: var(--bg-input); border: 1px solid var(--border);
                             border-radius: var(--radius-sm); padding: var(--space-md);
                             max-height: 380px; overflow: auto; line-height: 1.6; }
    .tab-panel.html-render p { margin: 0 0 var(--space-sm); }
    .tab-panel.html-render :is(ul,ol) { margin: 4px 0 var(--space-sm) 18px; }

    /* ---- Onglet "Plus d'infos" ---------------------------------------- */
    .info-card { background: var(--bg-input); border: 1px solid var(--border);
                 border-radius: var(--radius-md); padding: var(--space-lg);
                 max-width: 640px; }
    .info-title { font-size: 11px; color: var(--text-secondary); font-weight: 700;
                  text-transform: uppercase; letter-spacing: 0.8px;
                  margin-bottom: var(--space-md);
                  display: flex; align-items: center; gap: var(--space-sm); }
    .info-title::before { content: ''; display: inline-block; width: 4px; height: 14px;
                          background: linear-gradient(180deg, var(--brand), var(--brand-light));
                          border-radius: 2px; }
    .info-grid { display: grid; grid-template-columns: 130px 1fr;
                 gap: var(--space-sm) var(--space-md); align-items: center; }
    .info-grid .k { color: var(--text-secondary); font-size: 12px; font-weight: 600; }
    .info-grid .v { color: var(--text-primary); font-size: 13px; }
    .info-grid .v.empty { color: var(--rose); font-style: italic; }
    .info-grid .v.big { font-size: 17px; }
    .info-grid .v.big .num { font-weight: 700; font-variant-numeric: tabular-nums;
                             color: var(--text-white); }
    .info-grid .v.big .arrow { color: var(--text-muted); margin: 0 4px; }
    .info-grid .v.big .period { color: var(--text-secondary); font-size: 12px;
                                margin-left: var(--space-sm); font-weight: 400; }
    .info-grid .v.big.highlight .num {
      background: linear-gradient(135deg, var(--brand-light), var(--brand));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .info-grid .v .quote { color: var(--text-secondary); font-style: italic; }
    .info-empty { display: flex; align-items: center; gap: var(--space-sm);
                  padding: var(--space-md); background: var(--bg-card);
                  border-radius: var(--radius-sm); color: var(--text-muted);
                  font-style: italic; font-size: 13px; }
    .info-empty .dot { width: 8px; height: 8px; border-radius: 50%;
                       background: var(--text-muted); display: inline-block; }

    /* ---- Encadré "Soit par an" (en VERT — couleur revenu) -------------- */
    .annual-card {
      display: flex; align-items: center; gap: var(--space-md);
      margin-top: var(--space-md); padding: var(--space-md) var(--space-lg);
      background: linear-gradient(135deg, #34d39915, #22d3ee10);
      border: 1px solid #34d39940; border-radius: var(--radius-md);
      position: relative; overflow: hidden;
    }
    .annual-card::before {
      content: ''; position: absolute; top: 0; left: 0; bottom: 0;
      width: 4px; background: linear-gradient(180deg, var(--green), var(--cyan));
    }
    .annual-icon { font-size: 26px; line-height: 1; }
    .annual-body { display: flex; flex-direction: column; gap: 2px; flex: 1; }
    .annual-label {
      font-size: 11px; color: var(--green); font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.8px;
    }
    .annual-value {
      font-size: 24px; font-weight: 800; line-height: 1.1;
      display: flex; align-items: baseline; gap: 8px;
      font-variant-numeric: tabular-nums;
    }
    .annual-value .num {
      background: linear-gradient(135deg, var(--green), var(--cyan));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .annual-value .arrow { color: var(--text-muted); font-size: 18px;
                           -webkit-text-fill-color: var(--text-muted); }
    .annual-value .annual-suffix { color: var(--green); font-size: 16px; font-weight: 600;
                                   margin-left: 4px; }
    .annual-equiv { display: flex; align-items: center; gap: var(--space-sm);
                    margin-top: 6px; font-size: 12px; color: var(--text-secondary);
                    flex-wrap: wrap; }
    .annual-equiv .eq-item { display: inline-flex; align-items: center; gap: 4px; }
    .annual-equiv .eq-icon { font-size: 13px; }
    .annual-equiv .eq-val { color: var(--text-primary); font-weight: 600; font-variant-numeric: tabular-nums; }
    .annual-equiv .eq-period { color: var(--text-muted); }
    .annual-equiv .eq-sep { color: var(--text-muted); }

    /* ---- Sections "Plus d'infos" — détection avantages, expérience… ---- */
    .info-sections { margin-top: var(--space-md); display: grid; gap: var(--space-sm);
                     grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
    .info-row {
      display: flex; align-items: flex-start; gap: var(--space-sm);
      padding: 10px var(--space-md);
      background: var(--bg-input); border: 1px solid var(--border);
      border-radius: var(--radius-sm);
    }
    .info-row.found { border-color: #34d39940; }
    .info-row .ico {
      width: 24px; height: 24px; flex-shrink: 0;
      display: inline-flex; align-items: center; justify-content: center;
      border-radius: 50%; font-size: 12px;
    }
    .info-row.found .ico   { background: var(--green-bg); color: var(--green); }
    .info-row.unknown .ico { background: var(--bg-card); color: var(--text-muted); }
    .info-row .body { flex: 1; min-width: 0; }
    .info-row .ttl { font-size: 11px; color: var(--text-secondary); font-weight: 700;
                     text-transform: uppercase; letter-spacing: 0.5px; }
    .info-row .val { font-size: 13px; color: var(--text-primary); margin-top: 2px;
                     font-weight: 500; word-break: break-word; }
    .info-row.unknown .val { color: var(--text-muted); font-style: italic; font-weight: 400; }
    .tag-list { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 2px; }
    .tag-list .tag {
      display: inline-block; padding: 2px 8px; border-radius: 999px;
      font-size: 11px; font-weight: 500;
      background: var(--green-bg); color: var(--green); border: 1px solid #34d39933;
    }

    /* Ajustement light */
    html[data-theme="light"] .annual-card {
      background: linear-gradient(135deg, #34d39920, #22d3ee15);
      border-color: #34d39955;
    }

    /* Carte NET — ambre/orange pour distinguer du brut (vert) */
    .annual-card.net-card {
      background: linear-gradient(135deg, #fbbf2415, #fb718510);
      border-color: #fbbf2440;
    }
    .annual-card.net-card::before {
      background: linear-gradient(180deg, var(--amber), var(--rose));
    }
    .annual-card.net-card .annual-label { color: var(--amber); }
    .annual-card.net-card .annual-value .num {
      background: linear-gradient(135deg, var(--amber), var(--rose));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .annual-card.net-card .annual-suffix { color: var(--amber); }
    .net-hint { display: block; font-size: 10px; color: var(--text-muted);
                font-weight: 500; text-transform: none; letter-spacing: 0;
                margin-top: 1px; }
    html[data-theme="light"] .annual-card.net-card {
      background: linear-gradient(135deg, #fbbf2425, #fb718520);
      border-color: #fbbf2455;
    }

    /* ---- Carte SMIC ----------------------------------------------------- */
    .annual-card.smic-card {
      flex-direction: row;       /* layout identique aux autres */
      background: linear-gradient(135deg, #7c6cf715, #22d3ee10);
      border-color: #7c6cf740;
    }
    .annual-card.smic-card::before {
      background: linear-gradient(180deg, var(--brand), var(--cyan));
    }
    .annual-card.smic-card .annual-label { color: var(--brand-light); }
    .annual-card.smic-card .annual-value .num {
      background: linear-gradient(135deg, var(--brand-light), var(--cyan));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .annual-card.smic-card .annual-suffix { color: var(--brand-light); }

    /* Tonalités selon proximité au SMIC */
    .annual-card.smic-card.low::before  { background: linear-gradient(180deg, var(--rose), var(--red)); }
    .annual-card.smic-card.low { border-color: #ef444460; }
    .annual-card.smic-card.low .annual-value .num,
    .annual-card.smic-card.low .annual-label { background: none; -webkit-text-fill-color: var(--rose); color: var(--rose); }

    .annual-card.smic-card.med::before  { background: linear-gradient(180deg, var(--amber), var(--rose)); }
    .annual-card.smic-card.top::before  { background: linear-gradient(180deg, var(--green), var(--cyan)); }
    .annual-card.smic-card.top .annual-value .num {
      background: linear-gradient(135deg, var(--green), var(--cyan));
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }

    .smic-bar {
      position: relative; height: 28px;
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: 999px; margin-top: 10px; overflow: hidden;
    }
    .smic-bar-fill, .smic-bar-range {
      position: absolute; top: 0; bottom: 0;
      background: linear-gradient(90deg, var(--brand-light), var(--cyan));
      border-radius: 999px;
      transition: width 0.4s ease;
    }
    .annual-card.smic-card.low .smic-bar-fill,
    .annual-card.smic-card.low .smic-bar-range {
      background: linear-gradient(90deg, var(--rose), var(--red));
    }
    .annual-card.smic-card.med .smic-bar-fill,
    .annual-card.smic-card.med .smic-bar-range {
      background: linear-gradient(90deg, var(--amber), var(--rose));
    }
    .annual-card.smic-card.top .smic-bar-fill,
    .annual-card.smic-card.top .smic-bar-range {
      background: linear-gradient(90deg, var(--green), var(--cyan));
    }
    .smic-marker {
      position: absolute; top: 0; bottom: 0; width: 1px;
      background: rgba(255,255,255,0.35); pointer-events: none;
    }
    .smic-marker span {
      position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%);
      font-size: 9.5px; color: var(--text-muted); white-space: nowrap;
      padding-bottom: 2px; font-weight: 600;
    }
    html[data-theme="light"] .smic-marker { background: rgba(0,0,0,0.18); }

    .smic-remark {
      margin-top: 22px; font-size: 12px; color: var(--text-secondary);
      font-style: italic;
    }
    .annual-card.smic-card.low .smic-remark { color: var(--rose); font-style: normal; font-weight: 600; }

    /* Triplet de références SMIC (horaire / mensuel / annuel) */
    .smic-refs {
      display: grid; grid-template-columns: repeat(3, 1fr);
      gap: var(--space-sm); margin: 8px 0 12px;
      padding: 10px var(--space-md);
      background: var(--bg-input); border: 1px solid var(--border);
      border-radius: var(--radius-sm);
    }
    .smic-ref { display: flex; flex-direction: column; gap: 2px; }
    .smic-ref-lbl { font-size: 10px; color: var(--text-muted); font-weight: 600;
                    text-transform: uppercase; letter-spacing: 0.5px; }
    .smic-ref-val { font-size: 14px; color: var(--text-white); font-weight: 700;
                    font-variant-numeric: tabular-nums; }

    /* Bloc "Cette annonce ≈ X% du SMIC" */
    .smic-compare { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap;
                    margin-top: 6px; }
    .smic-compare-lbl { font-size: 12px; color: var(--text-secondary); font-weight: 500; }
    .smic-range-note { font-size: 11px; color: var(--text-muted); font-style: italic; }

    /* Écart par rapport au SMIC */
    .smic-delta {
      display: flex; align-items: center; gap: var(--space-sm); flex-wrap: wrap;
      margin-top: 12px; padding: 8px 12px;
      background: var(--bg-input); border: 1px solid var(--border);
      border-radius: var(--radius-sm); font-size: 12px;
    }
    .smic-delta-lbl { color: var(--text-secondary); font-weight: 600; font-size: 11px;
                      text-transform: uppercase; letter-spacing: 0.5px; }
    .smic-delta-item { display: inline-flex; align-items: center; gap: 4px; }
    .smic-delta-icon { font-size: 13px; }
    .smic-delta-val { font-weight: 700; font-variant-numeric: tabular-nums; }
    .smic-delta-period { color: var(--text-muted); }
    .smic-delta-sep { color: var(--text-muted); }
    .smic-delta.pos .smic-delta-val { color: var(--green); }
    .smic-delta.neg .smic-delta-val { color: var(--rose); }

    html[data-theme="light"] .annual-card.smic-card {
      background: linear-gradient(135deg, #7c6cf725, #22d3ee15);
      border-color: #7c6cf755;
    }

    /* ---- Similaires ---------------------------------------------------- */
    .sim-list { display: flex; flex-direction: column; gap: var(--space-sm); max-height: 380px; overflow: auto; }
    .sim {
      display: grid; grid-template-columns: 64px 1fr; gap: var(--space-md);
      padding: var(--space-sm) var(--space-md); background: var(--bg-input);
      border: 1px solid var(--border); border-radius: var(--radius-sm);
      cursor: pointer; transition: all var(--duration) var(--ease);
    }
    .sim:hover { border-color: var(--brand); transform: translateY(-2px); box-shadow: var(--shadow-md); }
    .sim-score { display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .sim-score .num { font-size: 18px; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
    .sim-score .bar { width: 52px; height: 4px; background: var(--border); border-radius: 2px;
                      margin-top: 5px; overflow: hidden; }
    .sim-score .bar-fill { height: 100%; }
    .sim-score.high  .num { color: var(--green); }
    .sim-score.high  .bar-fill { background: linear-gradient(90deg, var(--green), var(--cyan)); }
    .sim-score.med   .num { color: var(--amber); }
    .sim-score.med   .bar-fill { background: linear-gradient(90deg, var(--amber), var(--rose)); }
    .sim-score.low   .num { color: var(--text-muted); }
    .sim-score.low   .bar-fill { background: var(--text-muted); }
    .sim-info .title { font-weight: 600; font-size: 13px; color: var(--text-white); }
    .sim-info .sub { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }
    .sim-info .tokens { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 6px; }
    .sim-info .tok { font-size: 10px; padding: 2px 7px; background: var(--brand-subtle);
                     color: var(--brand-light); border-radius: 3px;
                     border: 1px solid #7c6cf733; }

    /* ---- Modal Analyse ------------------------------------------------- */
    .overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 50;
               display: none; align-items: flex-start; justify-content: center;
               padding: 40px 20px; overflow: auto;
               backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); }
    .overlay:not([hidden]) { display: flex; }
    .modal {
      background: var(--bg-card); border: 1px solid var(--border-light);
      border-radius: var(--radius-lg); width: 100%; max-width: 880px;
      box-shadow: var(--shadow-lg); display: flex; flex-direction: column;
      max-height: calc(100vh - 80px);
    }
    .modal-header { display: flex; justify-content: space-between; align-items: flex-start;
                    padding: var(--space-lg) var(--space-lg) var(--space-md);
                    border-bottom: 1px solid var(--border); }
    .modal-header h2 { margin: 0; font-size: 20px; color: var(--text-white); font-weight: 700; }
    .modal-sub { color: var(--text-secondary); font-size: 12px; margin-top: 4px; }
    .close-btn {
      background: transparent; border: 0; font-size: 20px; color: var(--text-secondary);
      padding: 4px 10px; cursor: pointer; border-radius: var(--radius-sm);
    }
    .close-btn:hover { background: var(--bg-input); color: var(--rose); border-color: transparent; }
    .modal-controls {
      display: flex; gap: var(--space-lg); flex-wrap: wrap;
      padding: var(--space-md) var(--space-lg);
      background: var(--bg-surface); border-bottom: 1px solid var(--border);
      font-size: 13px;
    }
    .modal-controls label { display: flex; align-items: center; gap: var(--space-sm); color: var(--text-secondary); }
    .modal-controls strong { color: var(--brand-light); font-variant-numeric: tabular-nums; }
    .modal-controls input[type="range"] { width: 180px; accent-color: var(--brand); }

    /* Settings modal */
    .settings-group { margin-bottom: var(--space-lg); }
    .settings-label { display: block; font-size: 11px; color: var(--text-secondary);
                      font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
                      margin-bottom: 6px; }
    .settings-input-row { display: flex; align-items: center; gap: var(--space-sm); }
    .settings-input-row input {
      flex: 1; padding: 10px 14px; border-radius: var(--radius-sm);
      border: 1px solid var(--border-light); background: var(--bg-input);
      color: var(--text-primary); font-size: 16px; font-family: inherit;
      font-weight: 600; outline: none;
    }
    .settings-input-row input:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 3px var(--brand-subtle);
    }
    .settings-unit { color: var(--text-secondary); font-size: 13px; font-weight: 600;
                     min-width: 70px; }
    .settings-hint { margin-top: 6px; font-size: 11.5px; color: var(--text-muted); }
    .settings-actions {
      display: flex; gap: var(--space-sm); justify-content: flex-end;
      margin-top: var(--space-lg); padding-top: var(--space-md);
      border-top: 1px solid var(--border);
    }
    .settings-actions button.secondary { width: auto; }
    .settings-actions button.primary { padding: 9px 24px; }

    .settings-danger-zone {
      margin-top: var(--space-xl);
      padding: var(--space-md);
      border: 1px solid #ef444440;
      background: var(--red-bg);
      border-radius: var(--radius-sm);
      display: flex; flex-direction: column; align-items: flex-end;
    }
    .danger-label { color: var(--rose); margin-bottom: 6px; align-self: stretch; }
    .danger-desc { font-size: 12px; color: var(--text-secondary); margin: 0 0 12px;
                   align-self: stretch; }
    .btn-danger {
      background: linear-gradient(135deg, var(--red), var(--rose));
      color: #fff; border-color: transparent; font-weight: 700;
      padding: 9px 18px;
    }
    .btn-danger:hover {
      background: linear-gradient(135deg, #dc2626, var(--red));
      color: #fff; border-color: transparent;
    }
    .btn-danger:disabled {
      opacity: 0.4; cursor: not-allowed;
      background: var(--bg-input); color: var(--text-muted);
    }

    /* Bouton "Tout effacer" dans le header — grand, fond sombre/noir, accent rouge */
    #btnWipe {
      background: linear-gradient(135deg, #000 0%, #1a0808 100%);
      color: #fef2f2;
      border: 2px solid var(--red);
      font-weight: 800;
      font-size: 14px;
      padding: 11px 22px;
      letter-spacing: 0.4px;
      box-shadow:
        0 0 0 3px rgba(239, 68, 68, 0.15),
        0 4px 16px rgba(239, 68, 68, 0.2);
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
    }
    #btnWipe:hover {
      background: linear-gradient(135deg, #1a0808 0%, var(--red) 100%);
      color: #fff;
      border-color: var(--red);
      box-shadow:
        0 0 0 4px rgba(239, 68, 68, 0.25),
        0 6px 22px rgba(239, 68, 68, 0.4);
      transform: translateY(-1px);
    }
    /* Bouton "Récupérer les dates" — vert/cyan pour ressortir */
    .btn-refetch {
      background: linear-gradient(135deg, var(--green), var(--cyan));
      color: #052e1a; border: 1px solid transparent;
      font-weight: 800;
      padding: 9px 16px;
      box-shadow: 0 2px 12px rgba(52, 211, 153, 0.3);
    }
    .btn-refetch:hover {
      background: linear-gradient(135deg, #10b981, #06b6d4);
      color: #fff; border-color: transparent;
      transform: translateY(-1px);
      box-shadow: 0 4px 18px rgba(52, 211, 153, 0.45);
    }
    .btn-refetch:disabled {
      opacity: 0.5; cursor: wait; transform: none;
    }

    html[data-theme="light"] #btnWipe {
      background: linear-gradient(135deg, #1a1a1a 0%, #3a0a0a 100%);
      color: #fff;
      border-color: var(--red);
    }

    /* ---- Recherche approfondie (bleu clair) --------------------------- */
    .deep-search {
      margin-top: var(--space-md);
      padding: var(--space-md) var(--space-lg);
      background: linear-gradient(135deg, #22d3ee15, #60a5fa10);
      border: 1px solid #60a5fa40;
      border-radius: var(--radius-md);
      position: relative;
    }
    .deep-search::before {
      content: ''; position: absolute; top: 0; left: 0; bottom: 0;
      width: 4px; background: linear-gradient(180deg, var(--cyan), #60a5fa);
      border-radius: 4px 0 0 4px;
    }
    .deep-search-title {
      font-size: 11px; font-weight: 700; text-transform: uppercase;
      letter-spacing: 0.8px; color: #60a5fa;
      margin-bottom: var(--space-md);
    }
    .deep-search-grid {
      display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: var(--space-sm);
    }
    .ds-link {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      text-decoration: none !important;
      transition: all var(--duration) var(--ease);
    }
    .ds-link:hover {
      border-color: var(--cyan);
      background: var(--bg-card-hover);
      transform: translateY(-1px);
      text-decoration: none !important;
      box-shadow: 0 2px 12px rgba(34, 211, 238, 0.2);
    }
    .ds-icon {
      font-size: 22px; line-height: 1; flex-shrink: 0;
    }
    .ds-body { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
    .ds-name {
      font-weight: 700; font-size: 13px; color: var(--text-white);
    }
    .ds-desc {
      font-size: 11px; color: var(--text-secondary);
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    html[data-theme="light"] .deep-search {
      background: linear-gradient(135deg, #22d3ee20, #60a5fa15);
      border-color: #60a5fa55;
    }
    html[data-theme="light"] .deep-search-title { color: #2563eb; }

    .deep-search-header {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: var(--space-md); flex-wrap: wrap; gap: var(--space-sm);
    }
    .deep-search-header .deep-search-title { margin-bottom: 0; }

    .ds-city-chip {
      display: inline-block; margin-left: 8px;
      padding: 2px 10px; border-radius: 999px; font-size: 10px; font-weight: 700;
      letter-spacing: 0.3px; text-transform: none;
      background: var(--cyan-bg); color: var(--cyan); border: 1px solid #22d3ee44;
      cursor: pointer; transition: all 0.15s ease;
    }
    .ds-city-chip.on:hover { background: var(--cyan); color: #052437; border-color: var(--cyan); }
    .ds-city-chip.off {
      background: var(--bg-card); color: var(--text-muted); border-color: var(--border-light);
      text-decoration: line-through;
    }
    .ds-city-chip.off:hover { background: var(--cyan-bg); color: var(--cyan); border-color: var(--cyan); text-decoration: none; }
    .ds-city-chip.empty {
      background: var(--amber-bg); color: var(--amber); border-color: #fbbf2444;
      cursor: default;
    }
    .ds-fetch-btn {
      background: linear-gradient(135deg, var(--cyan), #60a5fa);
      color: #fff; border: 1px solid transparent; font-weight: 700;
      padding: 7px 14px; font-size: 12px;
      box-shadow: 0 2px 10px rgba(34, 211, 238, 0.3);
    }
    .ds-fetch-btn:hover {
      background: linear-gradient(135deg, #06b6d4, #3b82f6);
      color: #fff; transform: translateY(-1px);
      box-shadow: 0 4px 14px rgba(34, 211, 238, 0.45);
    }
    .ds-fetch-btn:disabled { opacity: 0.6; cursor: wait; transform: none; }

    .ds-actions { display: flex; gap: 6px; align-items: center; }
    .ds-refresh-btn {
      background: var(--bg-card); color: var(--brand-light);
      border: 1px solid var(--brand);
      padding: 7px 11px; font-size: 14px; font-weight: 700;
      transition: all var(--duration) var(--ease);
    }
    .ds-refresh-btn:hover {
      background: var(--brand); color: #fff; border-color: var(--brand);
      transform: rotate(180deg);
    }
    .ds-refresh-btn:disabled { opacity: 0.5; cursor: wait; transform: none; }

    .ds-raw-cache-banner {
      display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
      padding: 8px 12px; margin-bottom: var(--space-sm);
      background: var(--brand-subtle); border: 1px dashed var(--brand);
      border-radius: var(--radius-sm); font-size: 11.5px; color: var(--brand-light);
    }
    .ds-raw-cache-age { color: var(--text-muted); }
    .ds-raw-refresh {
      margin-left: auto;
      background: transparent; color: var(--brand-light);
      border: 1px solid var(--brand);
      padding: 4px 10px; font-size: 11px; font-weight: 600;
    }
    .ds-raw-refresh:hover {
      background: var(--brand); color: #fff; border-color: var(--brand);
    }

    .deep-search-raw { margin-bottom: var(--space-md); display: flex;
                       flex-direction: column; gap: var(--space-sm); }
    .ds-raw-loading {
      padding: 16px; text-align: center; color: var(--text-muted);
      background: var(--bg-card); border: 1px dashed var(--border-light);
      border-radius: var(--radius-sm); font-size: 12px;
    }
    .ds-raw-empty {
      padding: 14px; text-align: center; color: var(--text-secondary);
      background: var(--bg-card); border: 1px dashed var(--border-light);
      border-radius: var(--radius-sm); font-size: 12px; line-height: 1.5;
    }
    .ds-raw-card {
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: var(--radius-sm); padding: 12px 14px;
    }
    .ds-raw-card-head {
      display: flex; justify-content: space-between; align-items: center;
      gap: var(--space-sm); margin-bottom: 8px;
    }
    .ds-raw-source {
      font-size: 10px; font-weight: 800; text-transform: uppercase;
      letter-spacing: 0.8px; color: var(--brand-light);
    }
    .ds-raw-card.wikipedia .ds-raw-source { color: #60a5fa; }
    .ds-raw-card.duckduckgo .ds-raw-source { color: #de5833; }
    .ds-raw-card.wikidata .ds-raw-source { color: #339966; }
    .ds-raw-link { font-size: 11px; color: var(--brand-light); }
    .ds-raw-thumb { max-width: 120px; max-height: 100px; float: right;
                    margin-left: 12px; margin-bottom: 4px; border-radius: 4px; }
    .ds-raw-title { font-size: 14px; font-weight: 700; color: var(--text-white); margin-bottom: 4px; }
    .ds-raw-desc { font-size: 12px; color: var(--text-secondary); font-style: italic; margin-bottom: 6px; }
    .ds-raw-extract { font-size: 12.5px; color: var(--text-primary); line-height: 1.5; }
    .ds-raw-tag {
      display: inline-block; background: var(--brand-subtle); color: var(--brand-light);
      padding: 2px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 600;
      margin-bottom: 6px;
    }
    .ds-raw-warn {
      background: var(--amber-bg); color: var(--amber);
      border: 1px solid #fbbf2440; padding: 6px 10px; border-radius: 4px;
      font-size: 11px; font-weight: 600; margin-top: 6px;
    }
    .ds-raw-related-title { font-size: 11px; font-weight: 700; color: var(--text-secondary);
                            text-transform: uppercase; letter-spacing: 0.5px;
                            margin-top: 10px; margin-bottom: 4px; }
    .ds-raw-related { margin: 0; padding-left: 18px; font-size: 12px; }
    .ds-raw-related li { margin-bottom: 2px; }
    .ds-raw-related .ds-raw-role { color: var(--text-secondary); font-style: italic; }

    /* Fiche officielle gouv.fr */
    .ds-raw-card.gouv .ds-raw-source { color: var(--green); }
    .ds-raw-card.gouv { border-left: 3px solid var(--green); }
    .ds-raw-grid {
      display: grid; grid-template-columns: 110px 1fr;
      gap: 4px var(--space-md); font-size: 12px;
      margin-top: 8px;
    }
    .ds-raw-k { color: var(--text-secondary); font-weight: 700;
                text-transform: uppercase; letter-spacing: 0.4px; font-size: 10.5px; }
    .ds-raw-v { color: var(--text-primary); word-break: break-word; }
    .ds-raw-v code {
      font-family: var(--font-mono); font-size: 11px;
      background: var(--bg-input); padding: 1px 6px; border-radius: 3px;
      border: 1px solid var(--border);
    }
    .ds-raw-tag.ok { background: var(--green-bg); color: var(--green); border: 1px solid #34d39933; }
    .ds-raw-tag.ko { background: var(--red-bg); color: var(--rose); border: 1px solid #ef444433; }

    /* Carte officielle : meilleur match mis en avant */
    /* Carte Site web (en tête de la liste) */
    .ds-raw-card.website-card {
      background: linear-gradient(135deg, #22d3ee15, #34d39912);
      border-color: #22d3ee55;
    }
    .ds-raw-card.website-card .ds-raw-source { color: var(--cyan); font-weight: 800; }
    .ds-raw-card.website-card.empty {
      background: var(--bg-card); border-style: dashed;
    }
    .ds-website-list { display: flex; flex-direction: column; gap: 6px; }
    .ds-website-link {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 14px;
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: var(--radius-sm); text-decoration: none !important;
      transition: all 0.15s ease;
    }
    .ds-website-link:hover {
      border-color: var(--cyan); transform: translateX(2px);
      box-shadow: 0 2px 10px rgba(34, 211, 238, 0.2);
    }
    .ds-website-link.official { border-left: 3px solid var(--green); }
    .ds-website-link.guess { border-left: 3px solid var(--amber); opacity: 0.85; }
    .ds-website-link.guess:hover { opacity: 1; }
    .ds-website-link.guess .ds-website-source {
      background: var(--amber-bg); color: var(--amber);
    }
    .ds-website-host { font-weight: 700; color: var(--text-white); flex: 1;
                       font-family: var(--font-mono); font-size: 13px; }
    .ds-website-source {
      font-size: 10px; padding: 2px 8px; border-radius: 999px;
      background: var(--bg-input); color: var(--text-secondary);
    }
    .ds-website-link.official .ds-website-source {
      background: var(--green-bg); color: var(--green);
    }
    .ds-website-arrow { color: var(--text-muted); font-size: 16px; }
    .ds-website-link:hover .ds-website-arrow { color: var(--cyan); }
    .ds-website-empty {
      padding: 12px; text-align: center; color: var(--text-secondary);
      font-size: 12.5px; line-height: 1.6;
    }
    .ds-website-search {
      display: inline-block; margin-top: 6px;
      padding: 6px 12px; background: var(--bg-card);
      border: 1px solid var(--cyan); border-radius: var(--radius-sm);
      color: var(--cyan); font-weight: 600; text-decoration: none;
    }
    .ds-website-search:hover {
      background: var(--cyan); color: #052437;
    }

    /* Carte Emails */
    .ds-raw-card.emails-card {
      background: linear-gradient(135deg, #fbbf2412, #34d39912);
      border-color: #fbbf2444;
    }
    .ds-raw-card.emails-card .ds-raw-source { color: var(--amber); font-weight: 800; }
    .ds-emails-section { margin-top: 8px; }
    .ds-emails-label { font-size: 11px; font-weight: 700; color: var(--text-secondary);
                       text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
    .ds-emails-list { display: flex; flex-wrap: wrap; gap: 6px; }
    .ds-email {
      display: inline-block; padding: 5px 12px; border-radius: var(--radius-sm);
      font-family: var(--font-mono); font-size: 12px; font-weight: 600;
      text-decoration: none !important;
      transition: all 0.15s ease;
    }
    .ds-email.found {
      background: var(--green-bg); color: var(--green); border: 1px solid #34d39955;
    }
    .ds-email.found:hover {
      background: var(--green); color: #052e1a; border-color: var(--green);
    }
    .ds-email.guess {
      background: var(--bg-card); color: var(--text-secondary); border: 1px dashed var(--amber);
    }
    .ds-email.guess:hover {
      background: var(--amber-bg); color: var(--amber); border-color: var(--amber);
    }

    .ds-raw-card.gouv.best-match {
      border-color: var(--green);
      box-shadow: 0 0 0 1px var(--green), 0 4px 18px rgba(52, 211, 153, 0.15);
    }
    .ds-raw-card.gouv.best-match .ds-raw-source { color: var(--green); font-weight: 800; }

    /* Cartes plateformes : touche de couleur sur le bord gauche */
    .ds-link.ds-platform { border-left: 3px solid var(--cyan); }
    .ds-link.ds-platform.ds-indeed     { border-left-color: #2557a7; }
    .ds-link.ds-platform.ds-linkedin   { border-left-color: #0a66c2; }
    .ds-link.ds-platform.ds-wttj       { border-left-color: #ffcd00; }
    .ds-link.ds-platform.ds-hellowork  { border-left-color: #ff5d5b; }
    .ds-link.ds-platform.ds-apec       { border-left-color: #003e6b; }
    .ds-link.ds-platform.ds-ftravail   { border-left-color: #0064cd; }
    .ds-link.ds-platform.ds-monster    { border-left-color: #6e46ae; }
    .ds-link.ds-platform.ds-glassdoor  { border-left-color: #0caa41; }
    .ds-link.ds-platform.ds-meteojob   { border-left-color: #f59e0b; }
    .ds-link.ds-platform.ds-google     { border-left-color: #4285f4; }

    /* Modal plateforme : iframe inline */
    .modal.platform-modal {
      max-width: min(1280px, 95vw);
      max-height: 92vh; height: 92vh;
      width: 100%;
    }
    .platform-frame-wrap {
      flex: 1; position: relative; overflow: hidden;
      background: var(--bg-input);
      border-radius: 0 0 var(--radius-lg) var(--radius-lg);
    }
    #platformIframe {
      position: absolute; inset: 0;
      width: 100%; height: 100%; border: 0; background: #fff;
    }
    .platform-frame-warn {
      position: absolute; top: 12px; left: 50%; transform: translateX(-50%);
      z-index: 2;
      background: var(--amber-bg); color: var(--amber);
      border: 1px solid #fbbf2455;
      padding: 12px 18px; border-radius: var(--radius-sm);
      font-size: 12px; max-width: 90%; text-align: center;
      box-shadow: var(--shadow-md);
    }
    .ds-link.ds-platform { cursor: pointer; }

    /* Bouton "Voir les autres annonces" */
    .ds-raw-actions { margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--border); }
    .ds-raw-filter-btn {
      width: 100%;
      background: linear-gradient(135deg, var(--brand-light), var(--cyan));
      color: #fff; border: 1px solid transparent;
      padding: 9px 12px; font-size: 12.5px; font-weight: 700;
      border-radius: var(--radius-sm); cursor: pointer;
      transition: all var(--duration) var(--ease);
    }
    .ds-raw-filter-btn:hover {
      background: linear-gradient(135deg, var(--brand), #0891b2);
      transform: translateY(-1px); color: #fff;
      box-shadow: 0 4px 14px rgba(34, 211, 238, 0.3);
    }

    /* Bouton suppression individuelle dans le détail (bas-droite) */
    .detail-actions {
      display: flex; justify-content: flex-end;
      margin-top: var(--space-md);
      padding-top: var(--space-md);
      border-top: 1px solid var(--border);
    }
    .btn-delete-one { font-size: 12px; padding: 8px 16px; }
    .modal-controls select { padding: 5px 10px; border-radius: var(--radius-sm);
                             border: 1px solid var(--border); background: var(--bg-input);
                             color: var(--text-primary); font: inherit; }
    .modal-body { padding: var(--space-md) var(--space-lg) var(--space-lg); overflow: auto; flex: 1; }

    .pair {
      display: grid; grid-template-columns: 76px 1fr;
      padding: var(--space-md); border: 1px solid var(--border); border-radius: var(--radius-sm);
      background: var(--bg-input); margin-bottom: 6px; cursor: pointer; gap: var(--space-md);
      transition: all var(--duration) var(--ease);
    }
    .pair:hover { border-color: var(--brand); transform: translateY(-1px); box-shadow: var(--shadow-md); }
    .pair-score { display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .pair-score .num { font-size: 20px; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
    .pair-score .bar { width: 60px; height: 5px; background: var(--border); border-radius: 3px;
                       margin-top: 6px; overflow: hidden; }
    .pair-score .bar-fill { height: 100%; }
    .pair-score.high .num  { color: var(--green); }
    .pair-score.high .bar-fill { background: linear-gradient(90deg, var(--green), var(--cyan)); }
    .pair-score.med  .num  { color: var(--amber); }
    .pair-score.med  .bar-fill { background: linear-gradient(90deg, var(--amber), var(--rose)); }
    .pair-score.low  .num  { color: var(--text-muted); }
    .pair-score.low  .bar-fill { background: var(--text-muted); }
    .pair-info > div { font-size: 12.5px; line-height: 1.5; }
    .pair-info .a, .pair-info .b { display: flex; align-items: baseline; gap: 6px; }
    .pair-info .arrow { color: var(--text-muted); }
    .pair-info .ttl { font-weight: 600; color: var(--text-white); }
    .pair-info .sub { color: var(--text-secondary); font-size: 11px; }

    .group { border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--bg-input);
             margin-bottom: var(--space-sm); overflow: hidden; }
    .group-head { padding: var(--space-sm) var(--space-md); background: var(--bg-card);
                  border-bottom: 1px solid var(--border);
                  display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--text-secondary); }
    .group-head .ct { font-weight: 700; color: var(--brand-light); }
    .group-list { padding: 6px 0; }
    .group-list .item { padding: 8px var(--space-md); font-size: 12px; cursor: pointer; display: flex;
                        justify-content: space-between; gap: var(--space-md);
                        transition: background var(--duration) var(--ease); }
    .group-list .item:hover { background: var(--bg-card); }
    .group-list .item .ttl { font-weight: 500; color: var(--text-primary); }
    .group-list .item .sub { color: var(--text-secondary); font-size: 11px; }

    .analyze-empty { padding: 40px; text-align: center; color: var(--text-secondary); }
    .analyze-spinner { padding: 60px; text-align: center; color: var(--text-secondary); font-size: 13px; }

    /* ---- États --------------------------------------------------------- */
    .empty-state { padding: 60px var(--space-lg); text-align: center; color: var(--text-secondary); }
    .empty-state h3 { margin: 0 0 6px; color: var(--text-white); font-weight: 600; font-size: 16px; }

    /* ---- Toast --------------------------------------------------------- */
    .toast {
      position: fixed; bottom: 24px; right: 24px; padding: 12px 18px;
      background: var(--bg-card); color: var(--text-white);
      border: 1px solid var(--brand); border-radius: var(--radius-md);
      font-size: 13px; box-shadow: var(--shadow-lg);
      opacity: 0; transform: translateY(8px); transition: all var(--duration) var(--ease);
      pointer-events: none;
    }
    .toast.show { opacity: 1; transform: translateY(0); }

  /* ---- Adaptations page éditeur autonome (hors application cv_enligne) ---- */
  body { padding: 0; }
  /* Lien retour flottant, au-dessus de l'overlay (z 50) et du panneau d'aperçu (z 51). */
  #builderBack {
    position: fixed; top: 14px; left: 16px; z-index: 60;
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 14px; border-radius: 10px;
    background: var(--bg-card); border: 1px solid var(--border-light);
    color: var(--text-primary); text-decoration: none; font-weight: 700; font-size: 13px;
    box-shadow: var(--shadow-md);
  }
  #builderBack:hover { border-color: var(--brand); color: var(--brand-light); }
  /* La croix « fermer » n'a pas de sens sur une page dédiée : on s'appuie sur le lien retour. */
  #profileClose { display: none; }
  /* Laisse la place au lien retour dans l'en-tête du modal. */
  #profileModal .modal-header { padding-left: 120px; }
</style>
    <link id="theme-mario" rel="stylesheet" href="assets/css/mario-theme.css">
    <script src="assets/js/theme-switch.js"></script>
</head>
<body>
<a id="builderBack" href="mes_cv.php?id=<?= (int) $cv['id'] ?>">← Mes CV</a>
  <!-- Modal Profil / CV -->
  <div class="overlay" id="profileOverlay" hidden>
    <div class="modal" id="profileModal" style="max-width: 880px">
      <header class="modal-header">
        <div>
          <h2>Profil & CV</h2>
          <div class="modal-sub">Renseigne tes infos puis génère ton CV en PDF</div>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
          <button id="profilePreviewToggle" class="btn-preview-toggle" title="Afficher / cacher l'aperçu en direct">👁️ Aperçu en direct</button>
          <button class="close-btn" id="profileClose" title="Fermer (Esc)">✕</button>
        </div>
      </header>
      <div class="modal-body">
       <div class="profile-form-pane">
        <div class="settings-group">
          <label class="settings-label">Photo (optionnelle)</label>
          <div class="photo-row">
            <div class="photo-preview" id="profilePhotoPreview" title="Cliquer pour choisir une photo">
              <span class="photo-placeholder" id="profilePhotoPlaceholder">👤</span>
              <img id="profilePhotoImg" alt="" hidden>
            </div>
            <div class="photo-actions">
              <input type="file" id="profilePhotoInput" accept="image/*" hidden>
              <button type="button" id="profilePhotoBtn" class="secondary">📷 Choisir une photo</button>
              <button type="button" id="profilePhotoRemove" class="icon-btn danger"
                      style="width: auto; padding: 4px 10px; font-size: 12px;" hidden>✕ Retirer</button>
              <label class="photo-include-label" id="profilePhotoIncludeLabel" hidden>
                <input type="checkbox" id="profilePhotoInclude" checked>
                Afficher dans le CV
              </label>
              <div style="display: flex; gap: 6px; margin-top: 6px;">
                <button type="button" class="secondary photo-pos-btn" data-pos="left"
                        style="padding: 4px 10px; font-size: 12px;">⬅️ Gauche</button>
                <button type="button" class="secondary photo-pos-btn" data-pos="right"
                        style="padding: 4px 10px; font-size: 12px;">Droite ➡️</button>
              </div>
            </div>
          </div>
        </div>

        <div class="settings-group">
          <label class="settings-label">Modèle de CV</label>
          <div class="template-presets" id="profileTemplates"></div>
        </div>

        <div class="settings-group">
          <label class="settings-label">🎨 Couleurs du modèle (s'appliquent au style sélectionné ci-dessus)</label>
          <div class="color-pickers">
            <label>Principale <input type="color" id="profileColorMain" value="#7c6cf7"></label>
            <label>Secondaire <input type="color" id="profileColorSecondary" value="#a99ffe"></label>
            <button type="button" id="profileColorReset" class="icon-btn" title="Réinitialiser toutes les couleurs"
                    style="width: auto; padding: 4px 10px; font-size: 12px;">↺ Défaut</button>
          </div>
          <div class="color-presets" id="profileColorPresets"></div>

          <details class="color-advanced">
            <summary>+ Couleurs avancées (texte, fond, sidebar, titres, bordures, badges)</summary>
            <p class="color-advanced-hint">
              Coche une case pour forcer la couleur dans tout le CV (s'applique à tous les modèles).
              Décoche pour laisser le modèle décider.
            </p>
            <div class="color-extra-grid" id="profileColorExtras"></div>
          </details>
        </div>

        <div class="settings-group">
          <label for="profileSinglePage"
                 style="display: flex; align-items: center; gap: 12px; cursor: pointer;
                        padding: 14px 16px;
                        background: linear-gradient(135deg, var(--brand) 0%, var(--brand-light) 100%);
                        color: #fff;
                        border-radius: var(--radius-sm);
                        font-size: 14px; font-weight: 600;
                        box-shadow: 0 4px 12px rgba(124, 108, 247, 0.35);">
            <input type="checkbox" id="profileSinglePage"
                   style="width: 20px; height: 20px; accent-color: #fff; cursor: pointer; margin: 0;">
            <span style="flex: 1;">📐 <strong>Tout tenir sur une seule page</strong><br>
              <span style="font-weight: 400; font-size: 12px; opacity: 0.92;">
                Compression automatique du CV pour qu'il rentre sur une page A4
              </span>
            </span>
          </label>
        </div>

        <div class="settings-group">
          <label for="profileFreeLayout"
                 style="display: flex; align-items: center; gap: 12px; cursor: pointer;
                        padding: 14px 16px;
                        background: linear-gradient(135deg, #0ea5e9 0%, #22d3ee 100%);
                        color: #fff;
                        border-radius: var(--radius-sm);
                        font-size: 14px; font-weight: 600;
                        box-shadow: 0 4px 12px rgba(14, 165, 233, 0.35);">
            <input type="checkbox" id="profileFreeLayout"
                   style="width: 20px; height: 20px; accent-color: #fff; cursor: pointer; margin: 0;">
            <span style="flex: 1;">🎨 <strong>Disposition libre (canvas)</strong><br>
              <span style="font-weight: 400; font-size: 12px; opacity: 0.92;">
                Place chaque bloc où tu veux sur la page A4 — glisse ✥ pour déplacer, ⤡ pour la largeur
              </span>
            </span>
          </label>
          <button type="button" id="profileAddTextBlock" class="row-add" style="margin-top: 10px;">
            ➕ Ajouter un bloc de texte libre
          </button>
          <input type="file" id="profileAddImageInput" accept="image/*" hidden>
          <button type="button" id="profileAddImageBlock" class="row-add" style="margin-top: 8px;">
            🖼️ Ajouter une image
          </button>
        </div>

        <div class="settings-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-md);">
          <div>
            <label class="settings-label">Prénom</label>
            <div class="settings-input-row">
              <input type="text" id="profileFirstName" placeholder="Jean">
            </div>
          </div>
          <div>
            <label class="settings-label">Nom</label>
            <div class="settings-input-row">
              <input type="text" id="profileLastName" placeholder="Dupont">
            </div>
          </div>
        </div>

        <div class="settings-group">
          <label class="settings-label">Titre / poste (ligne sous le nom)</label>
          <div class="settings-input-row">
            <input type="text" id="profileHeadline" placeholder="Électricien Tableautier · Câblage industriel"
                   style="width:100%; padding:10px 12px; border-radius: var(--radius-sm); border:1px solid var(--border-light); background:var(--bg-input); color:var(--text-primary); font:inherit;">
          </div>
        </div>

        <div class="settings-group">
          <label class="settings-label">Coordonnées (affichées dans le modèle « Tableautier »)</label>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-sm);">
            <input type="text" id="profileLocation" placeholder="📍 Ville / mobilité"
                   style="padding:10px 12px; border-radius: var(--radius-sm); border:1px solid var(--border-light); background:var(--bg-input); color:var(--text-primary); font:inherit;">
            <input type="text" id="profilePhone" placeholder="📞 Téléphone"
                   style="padding:10px 12px; border-radius: var(--radius-sm); border:1px solid var(--border-light); background:var(--bg-input); color:var(--text-primary); font:inherit;">
            <input type="text" id="profileEmail" placeholder="✉️ E-mail"
                   style="padding:10px 12px; border-radius: var(--radius-sm); border:1px solid var(--border-light); background:var(--bg-input); color:var(--text-primary); font:inherit;">
            <input type="text" id="profilePermis" placeholder="🚗 Permis B"
                   style="padding:10px 12px; border-radius: var(--radius-sm); border:1px solid var(--border-light); background:var(--bg-input); color:var(--text-primary); font:inherit;">
          </div>
        </div>

        <div class="settings-group">
          <label class="settings-label">Profil / accroche (résumé en haut du CV)</label>
          <div class="settings-input-row">
            <textarea id="profileSummary" rows="4"
                      style="width:100%; resize:vertical; padding:10px 12px; border-radius: var(--radius-sm); border:1px solid var(--border-light); background:var(--bg-input); color:var(--text-primary); font:inherit;"
                      placeholder="Électricien industriel fort de plus de 10 ans d'expérience…"></textarea>
          </div>
        </div>

        <div class="settings-group">
          <label class="settings-label">Date de naissance</label>
          <div class="settings-input-row">
            <input type="date" id="profileBirthDate">
            <select id="profileBirthDisplay" title="Choix de l'affichage dans le CV"
                    style="padding: 10px 12px; border-radius: var(--radius-sm);
                           border: 1px solid var(--border-light); background: var(--bg-input);
                           color: var(--text-primary); font-size: 13px; font-family: inherit;
                           font-weight: 600; outline: none; cursor: pointer;">
              <option value="date">Afficher la date</option>
              <option value="age">Afficher l'âge</option>
              <option value="none">Ne pas afficher</option>
            </select>
          </div>
        </div>

        <div class="settings-group" id="profilePhotoSizeGroup" hidden>
          <label class="settings-label">Taille de la photo</label>
          <div class="settings-input-row">
            <input type="range" id="profilePhotoSize" min="60" max="160" step="5" value="100"
                   style="flex: 1; accent-color: var(--brand); cursor: pointer;">
            <span class="settings-unit" id="profilePhotoSizeVal" style="min-width: 60px; text-align: right;">100 px</span>
          </div>
          <div style="display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap;">
            <button type="button" class="secondary photo-shape-btn" data-shape="circle"
                    style="padding: 4px 10px; font-size: 12px;">⚪ Cercle</button>
            <button type="button" class="secondary photo-shape-btn" data-shape="rounded"
                    style="padding: 4px 10px; font-size: 12px;">▢ Arrondi</button>
            <button type="button" class="secondary photo-shape-btn" data-shape="square"
                    style="padding: 4px 10px; font-size: 12px;">◻ Carré</button>
            <button type="button" class="secondary photo-shape-btn" data-shape="portrait"
                    style="padding: 4px 10px; font-size: 12px;">▯ Portrait</button>
            <button type="button" class="secondary photo-shape-btn" data-shape="hexagon"
                    style="padding: 4px 10px; font-size: 12px;">⬢ Hexagone</button>
          </div>
        </div>

        <div class="settings-group">
          <label class="settings-label">Taille du texte</label>
          <div class="settings-input-row">
            <input type="range" id="profileFontScale" min="80" max="130" step="5" value="100"
                   style="flex: 1; accent-color: var(--brand); cursor: pointer;">
            <span class="settings-unit" id="profileFontScaleVal" style="min-width: 60px; text-align: right;">100 %</span>
          </div>
        </div>

        <div class="settings-group">
          <label class="settings-label">Format des dates par défaut (peut être changé par ligne ↓)</label>
          <div class="settings-input-row">
            <select id="profileDateFormat" title="Format appliqué aux lignes qui sont sur 'Par défaut'"
                    style="padding: 10px 12px; border-radius: var(--radius-sm);
                           border: 1px solid var(--border-light); background: var(--bg-input);
                           color: var(--text-primary); font-size: 13px; font-family: inherit;
                           font-weight: 600; outline: none; cursor: pointer; flex: 1;">
              <option value="full">Mois et année (mars 2023)</option>
              <option value="year">Année seule (2023)</option>
            </select>
          </div>
        </div>

        <div class="settings-group">
          <label class="settings-label">Sections du CV (glisse la poignée ⠿ ou utilise ↑ ↓)</label>
          <div class="profile-sections" id="profileSections"></div>
        </div>

        <div class="settings-actions">
          <button id="profileGenerate" class="primary">📄 Générer CV PDF</button>
          <a id="profileViewLink" class="secondary" href="cv_view.php?id=<?= (int) $cv['id'] ?>"
             target="_blank" rel="noopener"
             style="text-decoration:none; display:inline-flex; align-items:center; gap:6px;">👁️ Aperçu (cv_view)</a>
          <button id="profileSave" class="secondary">💾 Enregistrer</button>
          <button id="profileExport" class="secondary" title="Télécharger le profil au format JSON">📤 Exporter JSON</button>
          <button id="profileImport" class="secondary" title="Charger un profil depuis un fichier JSON">📥 Importer JSON</button>
          <input type="file" id="profileImportFile" accept="application/json,.json" hidden>
        </div>
       </div>
      </div>
    </div>
  </div>

  <!-- Aperçu CV : panneau indépendant, hors du modal, posé sur la page -->
  <aside id="profilePreviewView" hidden>
    <div class="preview-toolbar">
      <span class="badge">Aperçu en direct</span>
      <span class="preview-hint">Sur chaque bloc : ✥ déplacer · ↔ largeur · ↕ hauteur · ⤡ les deux</span>
    </div>
    <iframe id="profilePreviewFrame" title="Aperçu du CV"></iframe>
  </aside>

<div id="toast" class="toast"></div>

<script>
  window.__CV_PROFILE__  = <?= $profileJson ?>;
  window.__CV_SAVE_URL__ = "api/cv_profile.php?id=<?= (int) $cv['id'] ?>";
  window.__CV_CSRF__     = <?= json_encode($csrf) ?>;
  // Neutralise la fermeture par Échap (pas de page derrière sur l'éditeur dédié).
  window.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') e.stopImmediatePropagation();
  }, true);
</script>
<script src="assets/js/cv-builder.js?v=<?= @filemtime(__DIR__ . '/assets/js/cv-builder.js') ?>"></script>
</body>
</html>
