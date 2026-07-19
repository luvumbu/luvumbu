<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/theme_defs.php';
require_admin();

// Thèmes disponibles : doivent rester alignés avec assets/css/themes.css et header.php.
$themes = [
    'defaut'   => ['name' => '📄 Défaut',     'desc' => 'Le style clair original (rouge & gris).',      'bg' => '#fafafa', 'text' => '#2a2a2a', 'dots' => ['#fafafa', '#963637', '#3a3a3a']],
    'gothique' => ['name' => '🦇 Gothique',    'desc' => 'Noir profond, cramoisi, titre blackletter.',   'bg' => '#0d0b10', 'text' => '#e7e1d8', 'dots' => ['#0d0b10', '#b91c4b', '#581c87']],
    'nuit'     => ['name' => '🌙 Nuit',        'desc' => 'Sombre élégant, accents bleu ciel.',           'bg' => '#0f172a', 'text' => '#e2e8f0', 'dots' => ['#0f172a', '#38bdf8', '#1e293b']],
    'sepia'    => ['name' => '📜 Sépia',       'desc' => 'Ambiance parchemin chaude et vintage.',        'bg' => '#f3e9d6', 'text' => '#3b2f23', 'dots' => ['#f3e9d6', '#9a5b2d', '#4a3526']],
    'ocean'    => ['name' => '🌊 Océan',       'desc' => 'Bleus profonds et turquoise.',                 'bg' => '#04293a', 'text' => '#e0f2fe', 'dots' => ['#04293a', '#2dd4bf', '#064663']],
    'foret'    => ['name' => '🌲 Forêt',       'desc' => 'Verts sombres et émeraude.',                   'bg' => '#0c1f17', 'text' => '#ecfdf5', 'dots' => ['#0c1f17', '#34d399', '#163728']],
    'papier'   => ['name' => '📃 Papier',       'desc' => 'Blanc pur, encre noire, titre serif. Idéal pour lire un cours.', 'bg' => '#ffffff', 'text' => '#1a1a1a', 'dots' => ['#ffffff', '#1d4ed8', '#1a1a1a']],
    'ardoise'  => ['name' => '🪨 Ardoise',      'desc' => 'Gris anthracite adouci, accents ambre.',       'bg' => '#1f2226', 'text' => '#eceff3', 'dots' => ['#1f2226', '#f59e0b', '#333840']],
    'lavande'  => ['name' => '💜 Lavande',      'desc' => 'Clair et doux, violet profond.',              'bg' => '#f6f3fd', 'text' => '#2e2545', 'dots' => ['#f6f3fd', '#7c3aed', '#3b2d63']],
    'rubis'    => ['name' => '🍷 Rubis',        'desc' => 'Bordeaux sombre et rouge vif.',               'bg' => '#1c0d12', 'text' => '#f7e9ee', 'dots' => ['#1c0d12', '#e11d48', '#351a24']],
    'solaire'  => ['name' => '☀️ Solaire',      'desc' => 'Crème chaud et ambre, lumineux.',             'bg' => '#fffaf0', 'text' => '#3b2f14', 'dots' => ['#fffaf0', '#d97706', '#4a3a15']],
    'neon'     => ['name' => '⚡ Néon',         'desc' => 'Nuit électrique, cyan et magenta.',           'bg' => '#08060f', 'text' => '#eae6ff', 'dots' => ['#08060f', '#22d3ee', '#d946ef']],
    'perso'    => ['name' => '🎨 Personnalisé', 'desc' => 'Choisis toi-même toutes les couleurs.',        'bg' => '#fafafa', 'text' => '#2a2a2a', 'dots' => ['#fafafa', '#963637', '#3a3a3a']],
];

// Champs de couleur personnalisables, regroupés pour l'UI.
$customGroups = [
    'Fond & texte' => [
        'bg'        => 'Fond de page',
        'surface'   => 'Cartes / encadrés',
        'surface-2' => 'Sous-cartes',
        'text'      => 'Texte principal',
        'text-soft' => 'Texte secondaire',
        'muted'     => 'Texte discret (dates)',
        'border'    => 'Bordures',
    ],
    'Couleur d\'accent' => [
        'accent'          => 'Accent (liens, boutons)',
        'accent-dark'     => 'Accent au survol',
        'accent-contrast' => 'Texte sur les boutons',
    ],
    'En-tête & navigation' => [
        'bar-bg'      => 'Barre du haut — fond',
        'bar-text'    => 'Barre du haut — texte',
        'title-color' => 'Titre du site',
        'nav-bg'      => 'Menu — fond',
        'nav-text'    => 'Menu — texte',
        'nav-hover'   => 'Menu — survol',
    ],
];
$fontOptions = [
    'default' => 'Anton (défaut)',
    'gothic'  => 'Gothique — blackletter',
    'serif'   => 'Playfair Display — serif',
    'modern'  => 'Helvetica / sans serif',
];

$defaults = blog_theme_custom_defaults();
$fontStacks = blog_theme_font_stacks();

$errors  = [];
$current = get_setting('blog_theme', 'defaut');
if (!isset($themes[$current])) $current = 'defaut';

// Valeurs personnalisées courantes
$customValues = [];
foreach ($defaults as $var => $def) {
    $customValues[$var] = blog_theme_safe_hex(get_setting(blog_theme_setting_key($var), $def), $def);
}
$customFontVal = (string)get_setting('blog_custom_font', 'default');
if (!isset($fontStacks[$customFontVal])) $customFontVal = 'default';

// La carte "Personnalisé" reflète les vraies couleurs choisies.
$themes['perso']['bg']   = $customValues['bg'];
$themes['perso']['text'] = $customValues['text'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $errors[] = 'Jeton invalide, recharge la page.';
    }
    $choice = trim((string)($_POST['blog_theme'] ?? 'defaut'));
    if (!isset($themes[$choice])) {
        $errors[] = 'Thème inconnu.';
    }
    if (empty($errors)) {
        if ($choice === 'perso') {
            foreach ($defaults as $var => $def) {
                $key = blog_theme_setting_key($var);
                $val = blog_theme_safe_hex($_POST[$key] ?? '', $def);
                set_setting($key, $val);
                $customValues[$var] = $val;
            }
            $f = (string)($_POST['blog_custom_font'] ?? 'default');
            if (!isset($fontStacks[$f])) $f = 'default';
            set_setting('blog_custom_font', $f);
            $customFontVal = $f;
        }
        set_setting('blog_theme', $choice);
        $current = $choice;
        flash_set('success', 'Thème du blog enregistré : ' . $themes[$choice]['name'] . '.');
        redirect(base_url('pages/blog_theme.php'));
    }
}

$pageTitle = 'Thème du blog';
include __DIR__ . '/../includes/header.php';
?>
<!-- Polices des thèmes, pour l'aperçu en direct -->
<link href="https://fonts.googleapis.com/css2?family=UnifrakturCook:wght@700&family=Playfair+Display:wght@600;800&display=swap" rel="stylesheet">
<style>
.theme-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
    margin: 20px 0 28px;
}
.theme-opt { position: relative; }
.theme-opt input.theme-radio { position: absolute; opacity: 0; pointer-events: none; }
.theme-opt label {
    display: block;
    border: 2px solid var(--border, #e5e7eb);
    border-radius: 14px;
    padding: 16px;
    cursor: pointer;
    transition: border-color .15s, transform .15s, box-shadow .15s;
    background: var(--surface, #fff);
}
.theme-opt label:hover { transform: translateY(-2px); box-shadow: 0 10px 24px -14px rgba(0,0,0,.5); }
.theme-opt input.theme-radio:checked + label {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,.25);
}
.theme-opt .swatch { display: flex; height: 56px; border-radius: 8px; overflow: hidden; margin-bottom: 12px; }
.theme-opt .swatch span { flex: 1; }
.theme-opt .t-name { font-weight: 700; font-size: 16px; margin: 0 0 4px; }
.theme-opt .t-desc { font-size: 13px; opacity: .75; margin: 0; }
.theme-opt input.theme-radio:checked + label .t-name::after { content: " ✓"; color: #6366f1; }

/* Panneau de personnalisation */
.custom-panel {
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 24px;
    background: var(--surface, #fff);
}
.custom-panel[hidden] { display: none; }
.custom-group { margin-bottom: 18px; }
.custom-group h4 { margin: 0 0 10px; font-size: 14px; text-transform: uppercase; letter-spacing: .5px; opacity: .8; }
.color-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
.color-row { display: flex; align-items: center; gap: 10px; font-size: 14px; }
.color-row input[type="color"] { width: 44px; height: 32px; padding: 2px; border: 1px solid var(--border, #ccc); border-radius: 6px; cursor: pointer; flex-shrink: 0; }
.color-row .c-label { flex: 1; }
.font-row { margin-top: 6px; display: flex; flex-direction: column; gap: 6px; max-width: 360px; }
.font-row select { padding: 8px 10px; border: 1px solid var(--border, #ccc); border-radius: 6px; }
</style>

<div class="auth-card auth-card-wide">
    <h1>🎭 Thème du blog</h1>
    <p class="muted">
        Choisis l'ambiance visuelle de <strong>tout le blog</strong> (en-tête, articles, sidebar, formulaires).
        L'aperçu s'applique <strong>immédiatement à cette page</strong> ; clique sur <em>Enregistrer</em> pour le rendre public.
    </p>
    <p class="muted">Différent de l'<a href="<?= e(base_url('pages/landing_settings.php')) ?>">Apparence de la page d'accueil</a> (l'écran d'entrée avant le blog).</p>

    <?php foreach ($errors as $err): ?>
        <p class="flash flash-error"><?= e($err) ?></p>
    <?php endforeach; ?>
    <?php if ($flashMsg = flash_get('success')): ?>
        <p class="flash flash-success"><?= e($flashMsg) ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="theme-grid">
            <?php foreach ($themes as $key => $t): ?>
                <div class="theme-opt">
                    <input type="radio" class="theme-radio" name="blog_theme" id="theme-<?= e($key) ?>" value="<?= e($key) ?>"
                           <?= $current === $key ? 'checked' : '' ?>>
                    <label for="theme-<?= e($key) ?>" data-card="<?= e($key) ?>"
                           style="background:<?= e($t['bg']) ?>; color:<?= e($t['text']) ?>;">
                        <span class="swatch">
                            <?php if ($key === 'perso'):
                                $persoDots = ['bg' => $customValues['bg'], 'accent' => $customValues['accent'], 'bar-bg' => $customValues['bar-bg']];
                                foreach ($persoDots as $var => $c): ?>
                                    <span data-swatch="<?= e($var) ?>" style="background:<?= e($c) ?>"></span>
                            <?php endforeach; else:
                                foreach ($t['dots'] as $c): ?>
                                    <span style="background:<?= e($c) ?>"></span>
                            <?php endforeach; endif; ?>
                        </span>
                        <p class="t-name"><?= e($t['name']) ?></p>
                        <p class="t-desc"><?= e($t['desc']) ?></p>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="custom-panel" id="custom-panel" <?= $current === 'perso' ? '' : 'hidden' ?>>
            <?php foreach ($customGroups as $groupTitle => $vars): ?>
                <div class="custom-group">
                    <h4><?= e($groupTitle) ?></h4>
                    <div class="color-grid">
                        <?php foreach ($vars as $var => $label): ?>
                            <label class="color-row">
                                <input type="color" name="<?= e(blog_theme_setting_key($var)) ?>"
                                       value="<?= e($customValues[$var]) ?>" data-var="<?= e($var) ?>">
                                <span class="c-label"><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="custom-group">
                <h4>Police du titre</h4>
                <div class="font-row">
                    <select name="blog_custom_font" id="custom-font">
                        <?php foreach ($fontOptions as $fkey => $flabel): ?>
                            <option value="<?= e($fkey) ?>" <?= $customFontVal === $fkey ? 'selected' : '' ?>><?= e($flabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button type="submit" class="btn-primary">💾 Enregistrer le thème</button>
            <a href="<?= e(base_url('pages/admin.php')) ?>" class="btn-secondary">Retour</a>
            <a href="<?= e(base_url('index.php')) ?>" class="btn-secondary" target="_blank">Voir le blog ↗</a>
        </div>
    </form>
</div>

<script>
(function () {
    const root = document.documentElement;
    const panel = document.getElementById('custom-panel');
    const colorInputs = Array.from(document.querySelectorAll('#custom-panel input[type="color"]'));
    const fontSelect = document.getElementById('custom-font');

    // Pile CSS par police (aligné avec theme_defs.php).
    const FONT_STACKS = <?= json_encode(array_map(function ($f) { return $f[0]; }, $fontStacks), JSON_UNESCAPED_UNICODE) ?>;

    // Rectangles d'aperçu de la carte "Personnalisé" → reflètent les vraies couleurs.
    const swatchEls = {};
    document.querySelectorAll('[data-swatch]').forEach(s => { swatchEls[s.dataset.swatch] = s; });
    const colorFor = (varName) => {
        const inp = colorInputs.find(i => i.dataset.var === varName);
        return inp ? inp.value : null;
    };
    const refreshPersoSwatch = () => {
        Object.keys(swatchEls).forEach(varName => {
            const c = colorFor(varName);
            if (c) swatchEls[varName].style.background = c;
        });
    };

    // Fond/texte de la carte "Personnalisé" → reflètent les vraies couleurs.
    const persoCard = document.querySelector('[data-card="perso"]');
    const refreshPersoCard = () => {
        if (!persoCard) return;
        const bg = colorFor('bg'), text = colorFor('text');
        if (bg) persoCard.style.background = bg;
        if (text) persoCard.style.color = text;
    };

    const clearCustomVars = () => {
        colorInputs.forEach(i => root.style.removeProperty('--' + i.dataset.var));
        root.style.removeProperty('--title-font');
    };

    const applyCustomVars = () => {
        colorInputs.forEach(i => root.style.setProperty('--' + i.dataset.var, i.value));
        const stack = FONT_STACKS[fontSelect.value] || FONT_STACKS.default;
        root.style.setProperty('--title-font', stack);
    };

    const selectTheme = (key) => {
        clearCustomVars();
        if (key === 'defaut') {
            root.removeAttribute('data-theme');
        } else {
            root.setAttribute('data-theme', key);
        }
        if (key === 'perso') {
            panel.hidden = false;
            applyCustomVars();
        } else {
            panel.hidden = true;
        }
    };

    document.querySelectorAll('input[name="blog_theme"]').forEach(radio => {
        radio.addEventListener('change', () => selectTheme(radio.value));
    });
    colorInputs.forEach(i => i.addEventListener('input', () => {
        if (!panel.hidden) root.style.setProperty('--' + i.dataset.var, i.value);
        if (swatchEls[i.dataset.var]) swatchEls[i.dataset.var].style.background = i.value;
        if (i.dataset.var === 'bg' || i.dataset.var === 'text') refreshPersoCard();
    }));
    fontSelect.addEventListener('change', () => {
        if (!panel.hidden) root.style.setProperty('--title-font', FONT_STACKS[fontSelect.value] || FONT_STACKS.default);
    });

    // Aperçu initial si "perso" déjà sélectionné
    refreshPersoSwatch();
    refreshPersoCard();
    if (panel && !panel.hidden) applyCustomVars();
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
