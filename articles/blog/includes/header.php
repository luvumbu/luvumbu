<?php
global $pdo;
$user = current_user();
$socials = $pdo->query('SELECT platform, url, icon FROM social_links ORDER BY platform')->fetchAll();

// Thème global du blog (couche de surcharge themes.css). 'defaut' = aucun attribut.
require_once __DIR__ . '/theme_defs.php';
$blogThemes = ['defaut', 'gothique', 'nuit', 'sepia', 'ocean', 'foret',
               'papier', 'ardoise', 'lavande', 'rubis', 'solaire', 'neon', 'perso'];
$blogTheme  = trim((string)get_setting('blog_theme', 'defaut'));
if (!in_array($blogTheme, $blogThemes, true)) $blogTheme = 'defaut';
// Polices web supplémentaires requises par certains thèmes.
$themeFonts = [
    'gothique' => 'UnifrakturCook:wght@700',
    'sepia'    => 'Playfair+Display:wght@600;800',
    'papier'   => 'Playfair+Display:wght@600;800',
];

// Thème personnalisé : on construit le bloc de variables CSS + la police à charger.
$customCss  = '';
$customFont = null; // famille Google Fonts à charger, le cas échéant
if ($blogTheme === 'perso') {
    $decls = [];
    foreach (blog_theme_custom_defaults() as $var => $default) {
        $val = blog_theme_safe_hex(get_setting(blog_theme_setting_key($var), $default), $default);
        $decls[] = '--' . $var . ':' . $val;
    }
    $fontStacks = blog_theme_font_stacks();
    $fontKey    = (string)get_setting('blog_custom_font', 'default');
    if (!isset($fontStacks[$fontKey])) $fontKey = 'default';
    [$stack, $googleFamily] = $fontStacks[$fontKey];
    $decls[]    = '--title-font:' . $stack;
    $customFont = $googleFamily;
    $customCss  = 'html[data-theme="perso"]{' . implode(';', $decls) . '}';
}
?>
<!DOCTYPE html>
<html lang="fr"<?= $blogTheme !== 'defaut' ? ' data-theme="' . e($blogTheme) . '"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#963637">
    <meta name="description" content="<?= e(get_setting('about_text')) ?>">

    <!-- PWA -->
    <link rel="manifest" href="<?= e(base_url('manifest.json')) ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="<?= e(get_setting('site_name')) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= e(get_setting('site_name')) ?>">
    <link rel="apple-touch-icon" href="<?= e(base_url('icon-192.png?v=2')) ?>">
    <link rel="apple-touch-icon" sizes="192x192" href="<?= e(base_url('icon-192.png?v=2')) ?>">
    <link rel="apple-touch-icon" sizes="512x512" href="<?= e(base_url('icon-512.png?v=2')) ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= e(base_url('icon-192.png?v=2')) ?>">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= e(base_url('icon-512.png?v=2')) ?>">
    <meta name="msapplication-TileColor" content="#963637">
    <meta name="msapplication-TileImage" content="<?= e(base_url('icon-192.png?v=2')) ?>">

    <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?><?= e(get_setting('site_name')) ?></title>
    <?php $cssVer = @filemtime(__DIR__ . '/../assets/css/styles.css') ?: time(); ?>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/styles.css') . '?v=' . $cssVer) ?>">
    <?php $themeVer = @filemtime(__DIR__ . '/../assets/css/themes.css') ?: time(); ?>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/themes.css') . '?v=' . $themeVer) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css?family=Anton" rel="stylesheet">
    <?php if (isset($themeFonts[$blogTheme])): ?>
    <link href="https://fonts.googleapis.com/css2?family=<?= e($themeFonts[$blogTheme]) ?>&display=swap" rel="stylesheet">
    <?php endif; ?>
    <?php if ($customFont): ?>
    <link href="https://fonts.googleapis.com/css2?family=<?= e($customFont) ?>&display=swap" rel="stylesheet">
    <?php endif; ?>
    <?php if ($customCss !== ''): ?>
    <style><?= $customCss ?></style>
    <?php endif; ?>
    <script>
        // Service worker neutralisé : on désinscrit tout SW et on vide les caches.
        // (Un ancien SW de cache servait des pages/CSS périmés et provoquait des
        //  erreurs "addAll Request failed".)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations()
                .then(rs => rs.forEach(r => r.unregister())).catch(() => {});
            if (window.caches) {
                caches.keys().then(ks => ks.forEach(k => caches.delete(k))).catch(() => {});
            }
        }
    </script>
</head>
<body>
<header>
    <div class="menu1">
        <div class="tr"><?= e(get_setting('header_baseline')) ?></div>
        <div class="socials">
            <?php if (empty($socials)): ?>
                <span class="socials-empty"></span>
            <?php else: foreach ($socials as $s): ?>
                <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" title="<?= e($s['platform']) ?>">
                    <i class="fa <?= e($s['icon']) ?>"></i>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <div class="bokonzi"><a href="<?= e(base_url('index.php')) ?>"><?= e(get_setting('site_name')) ?></a> <a>.</a>
        <p><?= e(get_setting('tagline')) ?></p>
    </div>
    <div class="menu20">
        <nav class="menu2">
            <a class="cursor" href="<?= e(base_url('index.php')) ?>">Accueil</a>
            <?php if ($user): ?>
                <a class="cursor" href="<?= e(base_url('pages/article_new.php')) ?>">Écrire un article</a>
                <?php if (is_admin()): ?>
                    <a class="cursor" href="<?= e(base_url('pages/admin.php')) ?>">Admin</a>
                    <a class="cursor" href="<?= e(base_url('pages/api_tokens.php')) ?>">Clés API</a>
                    <a class="cursor" href="<?= e(base_url('pages/settings.php')) ?>">Paramètres</a>
                    <a class="cursor" href="<?= e(base_url('pages/social.php')) ?>">Réseaux sociaux</a>
                <?php endif; ?>
                <a class="cursor cursor-user">Bonjour, <?= e($user['prenom']) ?><?= is_admin() ? ' (admin)' : '' ?></a>
                <a class="cursor" href="<?= e(base_url('pages/logout.php')) ?>">Se déconnecter</a>
            <?php else: ?>
                <a class="cursor" href="<?= e(base_url('pages/login.php')) ?>">Se connecter</a>
                <a class="cursor" href="<?= e(base_url('pages/register.php')) ?>">S'inscrire</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
<?php $successMsg = flash_get('success'); $errorMsg = flash_get('error'); ?>
<?php if ($successMsg): ?>
    <div class="flash flash-success"><?= e($successMsg) ?></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="flash flash-error"><?= e($errorMsg) ?></div>
<?php endif; ?>
<div class="toast-stack" id="toast-stack"></div>
<?php if ($successMsg || $errorMsg): ?>
<script>
(function() {
    const stack = document.getElementById('toast-stack');
    function showToast(msg, type) {
        const t = document.createElement('div');
        t.className = 'toast' + (type === 'error' ? ' error' : '');
        t.innerHTML = '<span class="toast-icon">' + (type === 'error' ? '⚠️' : '✅') + '</span><span>' + msg + '</span>';
        stack.appendChild(t);
        if ('vibrate' in navigator) {
            navigator.vibrate(type === 'error' ? [100, 50, 100] : 80);
        }
        setTimeout(() => {
            t.style.transition = 'opacity .4s, transform .4s';
            t.style.opacity = '0';
            t.style.transform = 'translateY(20px)';
            setTimeout(() => t.remove(), 400);
        }, 4000);
    }
    <?php if ($successMsg): ?>showToast(<?= json_encode($successMsg) ?>, 'success');<?php endif; ?>
    <?php if ($errorMsg): ?>showToast(<?= json_encode($errorMsg) ?>, 'error');<?php endif; ?>
})();
</script>
<?php endif; ?>
