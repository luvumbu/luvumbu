<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

// Champs personnalisables de la landing page, regroupes par sections.
$sections = [
    'Textes' => [
        'landing_eyebrow'     => ['label' => 'Badge en haut (ex: "En ligne"). Vide = masque.', 'type' => 'text',  'max' => 60, 'default' => ''],
        'landing_title'       => ['label' => 'Titre principal (laisse vide pour utiliser le nom du site)', 'type' => 'text', 'max' => 100, 'default' => ''],
        'landing_subtitle'    => ['label' => 'Slogan / sous-titre (laisse vide pour utiliser le slogan du blog)', 'type' => 'text', 'max' => 200, 'default' => ''],
        'landing_cta_text'    => ['label' => 'Texte du bouton principal', 'type' => 'text', 'max' => 60, 'default' => 'Découvrir le blog'],
        'landing_cta_url'     => ['label' => 'Lien du bouton principal (chemin relatif ou URL complète)', 'type' => 'text', 'max' => 200, 'default' => 'blog/'],
        'landing_footer_text' => ['label' => 'Texte du lien en bas (vide = affiche le domaine actuel)', 'type' => 'text', 'max' => 100, 'default' => ''],
        'landing_footer_url'  => ['label' => 'URL du lien en bas', 'type' => 'text', 'max' => 200, 'default' => 'blog/'],
    ],
    'Comportement' => [
        'landing_show_pulse'  => ['label' => 'Afficher le point qui pulse à côté du badge', 'type' => 'checkbox', 'default' => '1'],
    ],
    'Typographie' => [
        'landing_font' => [
            'label'   => 'Police du grand titre',
            'type'    => 'select',
            'default' => 'default',
            'options' => [
                'default' => 'Élégante — Playfair Display (défaut)',
                'gothic'  => 'Gothique — blackletter',
                'neon'    => 'Futuriste — Orbitron',
                'serif2'  => 'Dramatique — DM Serif Display',
                'modern'  => 'Moderne — Inter (sans serif)',
            ],
        ],
    ],
    'Couleurs principales' => [
        'landing_bg_color'     => ['label' => 'Couleur de fond',                     'type' => 'color', 'default' => '#0f172a'],
        'landing_text_color'   => ['label' => 'Couleur du texte principal',          'type' => 'color', 'default' => '#f1f5f9'],
        'landing_muted_color'  => ['label' => 'Couleur du texte secondaire',         'type' => 'color', 'default' => '#94a3b8'],
        'landing_accent_color' => ['label' => 'Couleur du bouton (clair)',           'type' => 'color', 'default' => '#16a34a'],
        'landing_accent_dark'  => ['label' => 'Couleur du bouton (foncé, dégradé)',  'type' => 'color', 'default' => '#166534'],
    ],
    'Couleurs d\'ambiance' => [
        'landing_blob_1' => ['label' => 'Blob 1 (haut-gauche)',  'type' => 'color', 'default' => '#6366f1'],
        'landing_blob_2' => ['label' => 'Blob 2 (bas-droit)',    'type' => 'color', 'default' => '#ec4899'],
        'landing_blob_3' => ['label' => 'Blob 3 (centre)',       'type' => 'color', 'default' => '#22c55e'],
    ],
];

// On aplatit pour faciliter le traitement
$fields = [];
foreach ($sections as $section => $items) {
    foreach ($items as $key => $def) $fields[$key] = $def;
}

$errors  = [];
$current = get_all_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $errors[] = 'Jeton invalide, recharge la page.';
    }
    foreach ($fields as $key => $def) {
        if ($def['type'] === 'checkbox') {
            $val = isset($_POST[$key]) ? '1' : '0';
        } else {
            $val = trim((string)($_POST[$key] ?? ''));
        }
        if ($def['type'] === 'color' && $val !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $val)) {
            $errors[] = $def['label'] . ' : code couleur invalide (#rrggbb attendu).';
        }
        if ($def['type'] === 'select' && $val !== '' && !isset($def['options'][$val])) {
            // Valeur hors liste : on retombe sur le défaut sans bloquer.
            $val = $def['default'];
        }
        if (isset($def['max']) && mb_strlen($val) > $def['max']) {
            $errors[] = $def['label'] . ' : trop long.';
        }
        $current[$key] = $val;
    }
    if (empty($errors)) {
        foreach ($fields as $key => $_) {
            set_setting($key, $current[$key]);
        }
        flash_set('success', 'Apparence de la page d\'accueil enregistrée.');
        redirect(base_url('pages/landing_settings.php'));
    }
}

// Resoudre les valeurs courantes
$values = [];
foreach ($fields as $key => $def) {
    $val = $current[$key] ?? null;
    if ($val === null || ($val === '' && in_array($def['type'], ['color', 'select'], true))) {
        $val = $def['default'];
    }
    $values[$key] = $val;
}

// Pour pre-remplir l'apercu avec les fallbacks
$siteName    = trim((string)get_setting('site_name', 'Mon Blog'));
$siteTagline = trim((string)get_setting('tagline', ''));
$titlePreview    = $values['landing_title']    !== '' ? $values['landing_title']    : $siteName;
$subtitlePreview = $values['landing_subtitle'] !== '' ? $values['landing_subtitle'] : $siteTagline;

$pageTitle = 'Apparence — Page d\'accueil';
include __DIR__ . '/../includes/header.php';
?>
<!-- Polices utilisées par les thèmes (pour l'aperçu temps réel) -->
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;800&family=UnifrakturCook:wght@700&family=Orbitron:wght@600;800&family=DM+Serif+Display&display=swap" rel="stylesheet">
<style>
.landing-edit {
    display: grid;
    grid-template-columns: minmax(340px, 1fr) minmax(360px, 1.2fr);
    gap: 28px;
    margin-top: 8px;
}
@media (max-width: 1024px) {
    .landing-edit { grid-template-columns: 1fr; }
}
.landing-edit .form { margin-top: 0; }

/* .section-block et .section-block .section-head sont definis dans styles.css */

.preview-pane { position: sticky; top: 16px; align-self: start; }
.preview-label {
    font-size: 12px; letter-spacing: 2px; text-transform: uppercase;
    color: #6b7280; margin-bottom: 8px;
}

/* ===== Cartes de thèmes complets ===== */
.theme-row {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 10px;
}
.theme-card {
    display: flex; flex-direction: column; align-items: flex-start; gap: 10px;
    padding: 12px 14px;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 12px;
    background: var(--card-bg, #fff);
    cursor: pointer;
    font-size: 14px; font-weight: 600;
    color: inherit;
    transition: transform .12s ease, border-color .12s ease, box-shadow .12s ease;
}
.theme-card:hover { transform: translateY(-2px); border-color: #6366f1; box-shadow: 0 8px 20px -12px rgba(99,102,241,0.6); }
.theme-card .theme-dots { display: inline-flex; gap: 5px; }
.theme-card .theme-dots span { width: 18px; height: 18px; border-radius: 50%; box-shadow: inset 0 0 0 1px rgba(0,0,0,0.12); }
.theme-card.theme-reset { justify-content: center; align-items: center; flex-direction: row; border-style: dashed; border-color: #dc2626; color: #dc2626; }

.lp-preview {
    --bg-1:        #0f172a;
    --text:        #f1f5f9;
    --text-muted:  #94a3b8;
    --accent:      #16a34a;
    --accent-dark: #166534;
    --blob-1: #6366f1;
    --blob-2: #ec4899;
    --blob-3: #22c55e;
    --title-font: 'Playfair Display', serif;

    position: relative;
    width: 100%;
    aspect-ratio: 16 / 11;
    border-radius: 18px;
    overflow: hidden;
    background: var(--bg-1);
    color: var(--text);
    font-family: 'Inter', system-ui, sans-serif;
    box-shadow: 0 16px 40px -16px rgba(0,0,0,0.3);
    isolation: isolate;
}
.lp-preview .lp-blob { position: absolute; border-radius: 50%; filter: blur(40px); pointer-events: none; }
.lp-preview .lp-blob.one   { top: -30%; left: -20%; width: 60%; height: 100%; background: radial-gradient(circle, var(--blob-1) 0%, transparent 70%); opacity: 0.55; }
.lp-preview .lp-blob.two   { bottom: -30%; right: -20%; width: 70%; height: 110%; background: radial-gradient(circle, var(--blob-2) 0%, transparent 70%); opacity: 0.45; }
.lp-preview .lp-blob.three { top: 30%; left: 55%; width: 40%; height: 60%; background: radial-gradient(circle, var(--blob-3) 0%, transparent 70%); opacity: 0.25; }
.lp-preview .lp-stage {
    position: absolute; inset: 0; z-index: 2;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    text-align: center; padding: 24px;
}
.lp-preview .lp-eyebrow {
    display: inline-flex; align-items: center; gap: 7px;
    font-size: 10px; letter-spacing: 3px; text-transform: uppercase;
    color: var(--text-muted);
    border: 1px solid rgba(255,255,255,0.10);
    background: rgba(255,255,255,0.04);
    padding: 5px 12px; border-radius: 999px; margin-bottom: 14px;
}
.lp-preview .lp-eyebrow[hidden] { display: none; }
.lp-preview .lp-eyebrow::before {
    content: ""; width: 6px; height: 6px;
    background: var(--accent); border-radius: 50%;
    box-shadow: 0 0 8px var(--accent);
    animation: lpPulse 2s ease-in-out infinite;
}
.lp-preview .lp-eyebrow.no-pulse::before { display: none; }
@keyframes lpPulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }
.lp-preview .lp-title {
    font-family: var(--title-font, 'Playfair Display', serif);
    font-weight: 800;
    font-size: clamp(28px, 4vw, 46px);
    line-height: 1.0;
    letter-spacing: -0.02em;
    background: linear-gradient(135deg, var(--text) 0%, color-mix(in srgb, var(--text) 60%, var(--text-muted)) 60%, var(--text-muted) 100%);
    -webkit-background-clip: text; background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
}
.lp-preview .lp-title[hidden] { display: none; }
.lp-preview .lp-tagline {
    font-size: 13px; color: var(--text-muted);
    font-weight: 300; margin-bottom: 18px;
    max-width: 85%; line-height: 1.5;
}
.lp-preview .lp-tagline[hidden] { display: none; }
.lp-preview .lp-cta {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 18px;
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
    color: #fff; font-size: 13px; font-weight: 500;
    border-radius: 10px;
    box-shadow: 0 8px 20px -6px color-mix(in srgb, var(--accent) 60%, transparent);
    margin-bottom: 16px;
}
.lp-preview .lp-cta::after { content: "→"; font-size: 14px; }
.lp-preview .lp-footer {
    font-size: 11px;
    color: var(--text-muted);
    border-bottom: 1px dashed color-mix(in srgb, var(--text-muted) 30%, transparent);
    padding-bottom: 2px;
}
.lp-preview .lp-footer[hidden] { display: none; }
</style>

<div class="auth-card auth-card-wide">
    <h1>🎨 Apparence — Page d'accueil</h1>
    <p class="muted">
        Personnalise tous les éléments visibles de la page d'accueil publique.
        L'aperçu de droite se met à jour <strong>en temps réel</strong>, rien n'est sauvegardé tant que tu n'as pas cliqué sur <em>Enregistrer</em>.
    </p>

    <?php foreach ($errors as $err): ?>
        <p class="flash flash-error"><?= e($err) ?></p>
    <?php endforeach; ?>

    <?php if ($flashMsg = flash_get('success')): ?>
        <p class="flash flash-success"><?= e($flashMsg) ?></p>
    <?php endif; ?>

    <div class="landing-edit">
        <form method="post" class="form" id="landing-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <div class="section-block">
                <div class="section-head">
                    <span class="ico">🎭</span>
                    <h3>Thèmes complets</h3>
                </div>
                <p class="muted" style="margin:-4px 0 14px;">Un clic applique une ambiance entière (couleurs + police). Tu peux ensuite ajuster chaque détail ci-dessous.</p>
                <div class="theme-row">
                    <button type="button" class="theme-card" data-theme="sombre">
                        <span class="theme-dots"><span style="background:#0f172a"></span><span style="background:#16a34a"></span><span style="background:#6366f1"></span></span>
                        <span class="theme-name">🌙 Sombre</span>
                    </button>
                    <button type="button" class="theme-card" data-theme="clair">
                        <span class="theme-dots"><span style="background:#f8fafc"></span><span style="background:#0f766e"></span><span style="background:#ec4899"></span></span>
                        <span class="theme-name">☀️ Clair</span>
                    </button>
                    <button type="button" class="theme-card" data-theme="gothique">
                        <span class="theme-dots"><span style="background:#0a0a0f"></span><span style="background:#9f1239"></span><span style="background:#581c87"></span></span>
                        <span class="theme-name">🦇 Gothique</span>
                    </button>
                    <button type="button" class="theme-card" data-theme="neon">
                        <span class="theme-dots"><span style="background:#070718"></span><span style="background:#06b6d4"></span><span style="background:#d946ef"></span></span>
                        <span class="theme-name">⚡ Néon</span>
                    </button>
                    <button type="button" class="theme-card" data-theme="sunset">
                        <span class="theme-dots"><span style="background:#1f1147"></span><span style="background:#f97316"></span><span style="background:#e11d48"></span></span>
                        <span class="theme-name">🌅 Sunset</span>
                    </button>
                    <button type="button" class="theme-card" data-theme="foret">
                        <span class="theme-dots"><span style="background:#0c2820"></span><span style="background:#15803d"></span><span style="background:#84cc16"></span></span>
                        <span class="theme-name">🌲 Forêt</span>
                    </button>
                    <button type="button" class="theme-card theme-reset" id="reset-defaults">
                        ↺ Réinitialiser
                    </button>
                </div>
            </div>

            <?php
            $sectionIcons = [
                'Textes' => '📝',
                'Comportement' => '⚙️',
                'Typographie' => '🔤',
                'Couleurs principales' => '🎨',
                'Couleurs d\'ambiance' => '✨',
            ];
            ?>
            <?php foreach ($sections as $sectionTitle => $sectionFields): ?>
                <div class="section-block">
                    <div class="section-head">
                        <span class="ico"><?= e($sectionIcons[$sectionTitle] ?? '•') ?></span>
                        <h3><?= e($sectionTitle) ?></h3>
                    </div>
                    <?php foreach ($sectionFields as $key => $def): ?>
                        <?php if ($def['type'] === 'checkbox'): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="<?= e($key) ?>" value="1" <?= $values[$key] === '1' ? 'checked' : '' ?> data-field="<?= e($key) ?>">
                                <?= e($def['label']) ?>
                            </label>
                        <?php elseif ($def['type'] === 'select'): ?>
                            <label>
                                <span class="label-text"><?= e($def['label']) ?></span>
                                <select name="<?= e($key) ?>" data-field="<?= e($key) ?>">
                                    <?php foreach ($def['options'] as $optVal => $optLabel): ?>
                                        <option value="<?= e($optVal) ?>" <?= $values[$key] === $optVal ? 'selected' : '' ?>><?= e($optLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php elseif ($def['type'] === 'color'): ?>
                            <label>
                                <span class="label-text"><?= e($def['label']) ?></span>
                                <span style="display:inline-flex; gap:10px; align-items:center;">
                                    <input type="color" name="<?= e($key) ?>" value="<?= e($values[$key]) ?>"
                                           data-field="<?= e($key) ?>"
                                           style="width:54px; height:36px; padding:2px; cursor:pointer;">
                                    <code style="font-size:13px;" data-color-display="<?= e($key) ?>"><?= e($values[$key]) ?></code>
                                </span>
                            </label>
                        <?php else: ?>
                            <label>
                                <span class="label-text"><?= e($def['label']) ?></span>
                                <input type="text" name="<?= e($key) ?>" value="<?= e($values[$key]) ?>"
                                       data-field="<?= e($key) ?>"
                                       maxlength="<?= (int)($def['max'] ?? 200) ?>">
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <button type="submit" class="btn-primary">💾 Enregistrer</button>
                <a href="<?= e(base_url('pages/admin.php')) ?>" class="btn-secondary">Retour</a>
            </div>
        </form>

        <div class="preview-pane">
            <p class="preview-label">Aperçu en temps réel</p>
            <div class="lp-preview" id="lp-preview">
                <div class="lp-blob one"></div>
                <div class="lp-blob two"></div>
                <div class="lp-blob three"></div>
                <div class="lp-stage">
                    <span class="lp-eyebrow" id="prev-eyebrow"></span>
                    <div class="lp-title"   id="prev-title"><?= e($titlePreview) ?></div>
                    <div class="lp-tagline" id="prev-tagline"><?= e($subtitlePreview) ?></div>
                    <span class="lp-cta"    id="prev-cta"><?= e($values['landing_cta_text']) ?></span>
                    <span class="lp-footer" id="prev-footer"></span>
                </div>
            </div>
            <p style="margin-top:10px; color:#6b7280; font-size:12px;">
                Si le titre ou le slogan sont vides ci-dessus, l'aperçu utilise le
                <a href="<?= e(base_url('pages/settings.php')) ?>">nom du site et slogan globaux</a>.
            </p>
        </div>
    </div>
</div>

<script>
(function () {
    const preview = document.getElementById('lp-preview');
    if (!preview) return;
    const style = preview.style;

    // Fallbacks injectes par PHP (nom du site / slogan globaux)
    const fallbackTitle    = <?= json_encode($siteName, JSON_UNESCAPED_UNICODE) ?>;
    const fallbackSubtitle = <?= json_encode($siteTagline, JSON_UNESCAPED_UNICODE) ?>;

    const el = {
        eyebrow: document.getElementById('prev-eyebrow'),
        title:   document.getElementById('prev-title'),
        tagline: document.getElementById('prev-tagline'),
        cta:     document.getElementById('prev-cta'),
        footer:  document.getElementById('prev-footer'),
    };
    const getVal = key => {
        const node = document.querySelector('[data-field="' + key + '"]');
        if (!node) return '';
        return node.type === 'checkbox' ? (node.checked ? '1' : '0') : node.value;
    };

    const setOrHide = (node, value) => {
        const v = (value ?? '').toString().trim();
        if (v === '') { node.hidden = true; }
        else { node.hidden = false; node.textContent = v; }
    };

    const apply = () => {
        // Textes
        setOrHide(el.eyebrow, getVal('landing_eyebrow'));
        const title    = getVal('landing_title').trim()    || fallbackTitle;
        const subtitle = getVal('landing_subtitle').trim() || fallbackSubtitle;
        setOrHide(el.title,   title);
        setOrHide(el.tagline, subtitle);
        el.cta.textContent = getVal('landing_cta_text').trim() || 'Découvrir le blog';

        // Footer : si vide, on affiche le host courant ("localhost", "mariondelval.com"...)
        const footer = getVal('landing_footer_text').trim() || location.host;
        setOrHide(el.footer, footer);

        // Pulse
        el.eyebrow.classList.toggle('no-pulse', getVal('landing_show_pulse') !== '1');

        // Couleurs
        style.setProperty('--bg-1',        getVal('landing_bg_color'));
        style.setProperty('--text',        getVal('landing_text_color'));
        style.setProperty('--text-muted',  getVal('landing_muted_color'));
        style.setProperty('--accent',      getVal('landing_accent_color'));
        style.setProperty('--accent-dark', getVal('landing_accent_dark'));
        style.setProperty('--blob-1',      getVal('landing_blob_1'));
        style.setProperty('--blob-2',      getVal('landing_blob_2'));
        style.setProperty('--blob-3',      getVal('landing_blob_3'));

        // Police du titre
        const fontKey = getVal('landing_font') || 'default';
        const font = FONTS[fontKey] || FONTS.default;
        style.setProperty('--title-font', font.stack);
    };

    document.querySelectorAll('[data-field]').forEach(input => {
        const evName = input.type === 'checkbox' ? 'change' : 'input';
        input.addEventListener(evName, () => {
            if (input.type === 'color') {
                const display = document.querySelector('[data-color-display="' + input.dataset.field + '"]');
                if (display) display.textContent = input.value;
            }
            apply();
        });
    });

    // ===== Polices disponibles (doit rester aligné avec index.html et site_info.php) =====
    const FONTS = {
        default: { stack: "'Playfair Display', serif" },
        gothic:  { stack: "'UnifrakturCook', 'Playfair Display', serif" },
        neon:    { stack: "'Orbitron', system-ui, sans-serif" },
        serif2:  { stack: "'DM Serif Display', 'Playfair Display', serif" },
        modern:  { stack: "'Inter', system-ui, sans-serif" },
    };

    // ===== Thèmes complets : couleurs + police =====
    const THEMES = {
        sombre: {
            font: 'default',
            landing_bg_color: '#0f172a', landing_text_color: '#f1f5f9', landing_muted_color: '#94a3b8',
            landing_accent_color: '#16a34a', landing_accent_dark: '#166534',
            landing_blob_1: '#6366f1', landing_blob_2: '#ec4899', landing_blob_3: '#22c55e',
        },
        clair: {
            font: 'default',
            landing_bg_color: '#f8fafc', landing_text_color: '#0f172a', landing_muted_color: '#64748b',
            landing_accent_color: '#0f766e', landing_accent_dark: '#115e59',
            landing_blob_1: '#ec4899', landing_blob_2: '#22c55e', landing_blob_3: '#fbbf24',
        },
        gothique: {
            font: 'gothic',
            landing_bg_color: '#0a0a0f', landing_text_color: '#e7e1d8', landing_muted_color: '#8a7f72',
            landing_accent_color: '#9f1239', landing_accent_dark: '#4c0519',
            landing_blob_1: '#7f1d1d', landing_blob_2: '#581c87', landing_blob_3: '#1c1917',
        },
        neon: {
            font: 'neon',
            landing_bg_color: '#070718', landing_text_color: '#e0f7ff', landing_muted_color: '#7dd3fc',
            landing_accent_color: '#06b6d4', landing_accent_dark: '#0e7490',
            landing_blob_1: '#d946ef', landing_blob_2: '#06b6d4', landing_blob_3: '#3b82f6',
        },
        sunset: {
            font: 'serif2',
            landing_bg_color: '#1f1147', landing_text_color: '#fdf2f8', landing_muted_color: '#fbcfe8',
            landing_accent_color: '#f97316', landing_accent_dark: '#c2410c',
            landing_blob_1: '#e11d48', landing_blob_2: '#f97316', landing_blob_3: '#a855f7',
        },
        foret: {
            font: 'default',
            landing_bg_color: '#0c2820', landing_text_color: '#ecfdf5', landing_muted_color: '#a7f3d0',
            landing_accent_color: '#15803d', landing_accent_dark: '#14532d',
            landing_blob_1: '#84cc16', landing_blob_2: '#10b981', landing_blob_3: '#fbbf24',
        },
    };

    const setField = (key, value) => {
        const input = document.querySelector('[data-field="' + key + '"]');
        if (!input) return;
        if (input.type === 'checkbox') input.checked = (value === '1' || value === true);
        else input.value = value;
        if (input.type === 'color') {
            const display = document.querySelector('[data-color-display="' + key + '"]');
            if (display) display.textContent = value;
        }
    };

    document.querySelectorAll('[data-theme]').forEach(btn => {
        btn.addEventListener('click', () => {
            const theme = THEMES[btn.dataset.theme];
            if (!theme) return;
            for (const k in theme) {
                if (k === 'font') setField('landing_font', theme.font);
                else setField(k, theme[k]);
            }
            apply();
        });
    });

    const resetBtn = document.getElementById('reset-defaults');
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            if (!confirm('Réinitialiser tous les paramètres aux valeurs par défaut ?')) return;
            const defaults = {
                landing_eyebrow: '',
                landing_title: '',
                landing_subtitle: '',
                landing_cta_text: 'Découvrir le blog',
                landing_cta_url: 'blog/',
                landing_footer_text: '',
                landing_footer_url: 'blog/',
                landing_show_pulse: '1',
                landing_font: 'default',
                landing_bg_color: THEMES.sombre.landing_bg_color,
                landing_text_color: THEMES.sombre.landing_text_color,
                landing_muted_color: THEMES.sombre.landing_muted_color,
                landing_accent_color: THEMES.sombre.landing_accent_color,
                landing_accent_dark: THEMES.sombre.landing_accent_dark,
                landing_blob_1: THEMES.sombre.landing_blob_1,
                landing_blob_2: THEMES.sombre.landing_blob_2,
                landing_blob_3: THEMES.sombre.landing_blob_3,
            };
            for (const k in defaults) setField(k, defaults[k]);
            apply();
        });
    }

    apply();
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
