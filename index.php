<?php
/* ═══════════════════════════════════════════════════════════
   PORTFOLIO — orchestrateur.
   1) Charge la config paramétrable (config/portfolio.php)
   2) Rend la PRÉSENTATION (inc/presentation.php)
   3) Rend la CARTE des projets À LA FIN (inc/carte.php)
   ═══════════════════════════════════════════════════════════ */
$CFG = require __DIR__ . '/config/portfolio.php';
/* Apparence personnalisée via l'espace admin (config/appearance.json) → surcharge le thème */
$__appFile = __DIR__ . '/config/appearance.json';
if (is_file($__appFile)) {
    $__app = json_decode(file_get_contents($__appFile), true);
    if (is_array($__app)) {
        if (!empty($__app['accent']))     $CFG['theme']['accent']     = $__app['accent'];
        if (!empty($__app['accent_dim'])) $CFG['theme']['accent_dim'] = $__app['accent_dim'];
        if (isset($__app['dark']))        $CFG['theme']['defaut_sombre'] = (bool)$__app['dark'];
        if (isset($__app['particules']))  $CFG['theme']['particules']    = (bool)$__app['particules'];
        if (!empty($__app['carte_mode']))  $CFG['carte']['default_mode']  = $__app['carte_mode'];
        if (!empty($__app['carte_biome'])) $CFG['carte']['default_biome'] = $__app['carte_biome'];
    }
}
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$ID = $CFG['identite'];
$sombre = !empty($CFG['theme']['defaut_sombre']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($ID['nom']) ?> — <?= e($ID['titre']) ?></title>
<meta name="description" content="<?= e($ID['seo_desc']) ?>">
<meta name="author" content="<?= e($ID['nom']) ?>">
<meta property="og:title" content="<?= e($ID['nom']) ?> — <?= e($ID['titre']) ?>">
<meta property="og:description" content="<?= e($ID['seo_desc']) ?>">
<meta property="og:type" content="website">
<?php if (!empty($ID['og_image'])): ?>
<meta property="og:image" content="<?= e($ID['og_image']) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?= e($ID['og_image']) ?>">
<?php endif; ?>
<?php if (!empty($ID['favicon'])): ?>
<link rel="icon" href="<?= e($ID['favicon']) ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=JetBrains+Mono:wght@400;600&family=Press+Start+2P&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=5">
<link rel="stylesheet" href="css/carte.css?v=6">
<style>
  :root{ --accent:<?= e($CFG['theme']['accent']) ?>; --accent-dim:<?= e($CFG['theme']['accent_dim']) ?>; }
</style>
</head>
<body>

<!-- ░░ Fond animé ░░ -->
<div class="bg-grid" aria-hidden="true"></div>
<div class="bg-glow glow-1" aria-hidden="true"></div>
<div class="bg-glow glow-2" aria-hidden="true"></div>
<?php if (!empty($CFG['theme']['particules'])): ?>
<canvas id="particles" aria-hidden="true"></canvas>
<?php endif; ?>

<!-- ░░ Barre de progression ░░ -->
<div class="scroll-progress" id="scrollProgress"></div>

<!-- ░░ Navigation ░░ -->
<header class="nav" id="nav">
  <a href="#hero" class="nav-logo"><?= e($ID['initiale']) ?><span>.</span></a>
  <nav class="nav-links">
    <a href="#about">Profil</a>
    <a href="#stack">Stack</a>
    <a href="#bokonzi"><?= e($CFG['projet']['nom']) ?></a>
    <a href="#skills">Savoir-faire</a>
    <a href="#carte">Projets</a>
    <a href="#contact">Contact</a>
    <a class="js-admin-open" style="cursor:pointer">🔒 Admin</a>
  </nav>
  <button class="theme-toggle" id="themeToggle" aria-label="Basculer le thème" title="Thème clair / sombre">
    <span class="toggle-icon"><?= $sombre ? '☾' : '☀' ?></span>
  </button>
</header>

<main>
  <?php include __DIR__ . '/inc/presentation.php'; ?>
  <?php include __DIR__ . '/inc/carte.php'; ?>
</main>

<footer class="footer">
  <p>© <span id="year"><?= date('Y') ?></span> <?= e($ID['nom']) ?> — <?= e($ID['titre']) ?>. Développement web sur-mesure.</p>
</footer>

<!-- Modale Espace admin (ouverte DANS index.php, ne quitte pas la page) -->
<div class="admin-modal" id="pfAdminModal" aria-hidden="true">
  <div class="admin-modal-inner">
    <div class="admin-modal-bar">
      <span>🔒 Espace admin — apparence</span>
      <div class="am-actions">
        <a class="am-btn" href="admin.php" target="_blank" rel="noopener" title="Ouvrir dans un onglet">⤢ Onglet</a>
        <button type="button" class="am-btn am-close" id="pfAdminClose" title="Fermer (Échap)">✕ Fermer</button>
      </div>
    </div>
    <iframe id="pfAdminFrame" title="Espace admin" data-src="admin.php" style="flex:1;width:100%;border:0;background:#080a10"></iframe>
  </div>
</div>
<script>
(function(){
  var opens=document.querySelectorAll('.js-admin-open'),m=document.getElementById('pfAdminModal'),
      c=document.getElementById('pfAdminClose'),f=document.getElementById('pfAdminFrame');
  if(!opens.length||!m)return;
  function openM(){ if(f&&!f.getAttribute('src'))f.setAttribute('src',f.getAttribute('data-src')); m.classList.add('open'); m.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; }
  function closeM(){ m.classList.remove('open'); m.setAttribute('aria-hidden','true'); document.body.style.overflow=''; }
  opens.forEach(function(o){ o.addEventListener('click',openM); });
  if(c)c.addEventListener('click',closeM);
  m.addEventListener('click',function(e){ if(e.target===m) closeM(); });
  document.addEventListener('keydown',function(e){ if(e.key==='Escape') closeM(); });
  /* rouvrir la modale après un enregistrement admin (le portfolio vient de se recharger) */
  try{ if(sessionStorage.getItem('pf_admin_reopen')){ sessionStorage.removeItem('pf_admin_reopen'); openM(); } }catch(e){}
})();
</script>

<script>window.PF_DARK_DEFAULT = <?= $sombre ? 'true' : 'false' ?>;</script>
<script src="js/main.js?v=5"></script>
<script src="js/carte.js?v=9"></script>
</body>
</html>
