<?php
/* ═══════════════════════════════════════════════════════════
   CARTE DES PROJETS — affichée À LA FIN de la présentation.
   Même logique que LUVUMBU LAND : 1 projet = 1 nœud sur une
   carte en serpentin. Données depuis $CFG['carte'] (paramétrable).
   Fichier séparé de la présentation (inc/presentation.php).
   ═══════════════════════════════════════════════════════════ */
if (!isset($CFG)) { return; }
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
$C = $CFG['carte'];
$root = dirname(__DIR__);                 // racine du portfolio (portefolio/)
$projets = [];
$vues        = $C['vues'] ?? [];
$apparences  = $C['apparences'] ?? [];
$luvumbuUrl  = $C['luvumbu_url'] ?? 'luvumbu/';
$adminUrl    = $C['admin_url'] ?? 'luvumbu/?admin=1';
$defaultMode  = $C['default_mode']  ?? 'default';   // mode carte par défaut (réglé dans l'admin)
$defaultBiome = $C['default_biome'] ?? '';          // univers par défaut

/* LUVUMBU LAND (vues jeu + admin) est optionnel : présent seulement si le dossier existe */
$hasLuvumbu = is_dir($root . '/luvumbu');

/* ─── MODE SCAN : détecte les vrais dossiers-projets à la racine (auto-suffisant) ─── */
if (($C['source'] ?? 'manuel') === 'scan') {
    $scanRel = trim($C['scan_dir'] ?? '.', '/');
    $isRoot  = ($scanRel === '.' || $scanRel === '');
    $scanAbs = $isRoot ? $root : $root . '/' . $scanRel;
    $exclude = $C['exclude'] ?? ['luvumbu', 'config', 'css', 'js', 'inc', 'images', 'vendor', 'node_modules'];
    $meta    = $C['meta'] ?? [];

    $folders = [];
    if (is_dir($scanAbs)) {
        foreach (scandir($scanAbs) as $e) {
            if ($e === '.' || $e === '..' || $e[0] === '.') continue;   // ignore cachés
            if (in_array($e, $exclude, true)) continue;                 // ignore l'infra
            if (!is_dir($scanAbs . '/' . $e)) continue;                 // dossiers uniquement
            $folders[] = $e;
        }
        sort($folders, SORT_NATURAL | SORT_FLAG_CASE);
    }

    foreach ($folders as $f) {
        $m = $meta[$f] ?? [];
        $target    = isset($m['target']) ? trim($m['target'], '/') : '';
        $urlPrefix = $isRoot ? '' : $scanRel . '/';
        $autoUrl   = $urlPrefix . rawurlencode($f) . ($target !== '' ? '/' . $target : '') . '/';
        $projets[] = [
            'icon'   => $m['icon'] ?? '🕹️',
            'img'    => $m['img']  ?? '',
            'nom'    => $m['nom']  ?? $f,
            'folder' => $f,                            // nom réel du dossier
            'etat'   => $m['etat'] ?? 'ouvert',
            'url'    => $m['url']  ?? $autoUrl,
            'desc'   => $m['desc'] ?? 'Projet détecté automatiquement.',
        ];
    }
}

/* ─── Repli : liste manuelle si le scan n'a rien donné ─── */
if (!$projets) { $projets = array_values($C['projets'] ?? []); }
?>

<!-- ░░ CARTE DES PROJETS ░░ -->
<section class="section section-carte" id="carte">
  <div class="section-head reveal" style="text-align:center;max-width:760px;margin:0 auto 46px">
    <span class="tag">06 — Projets</span>
    <h2><span class="grad"><?= e($C['titre']) ?></span></h2>
    <p class="section-sub"><?= e($C['sous']) ?></p>
  </div>

  <?php if ($vues && $hasLuvumbu): /* vues jeu + admin : seulement si LUVUMBU LAND est présent — AVANT le rendu */ ?>
  <!-- Autres vues : mêmes projets, mises en scène différentes (LUVUMBU LAND) -->
  <div class="carte-views reveal" data-luvumbu="<?= e($luvumbuUrl) ?>">
    <div class="cv-head">
      <div class="cv-head-txt">
        <h3>🎮 Voir autrement</h3>
        <p>Les mêmes projets, mis en scène façon jeu rétro. Choisis ta vue.</p>
      </div>
      <button type="button" class="cv-admin" id="adminOpen" title="Connexion admin (MySQL) — paramétrer les vues, projets et descriptions">🔒 Connexion admin</button>
    </div>
    <div class="cv-sub">🎬 Mode</div>
    <div class="cv-grid">
      <button type="button" class="cv-card<?= $defaultMode === 'default' ? ' active' : '' ?>" data-mode="default">
        <span class="cv-ico">🗺️</span>
        <b>Carte</b>
        <small>Vue intégrée (par défaut)</small>
      </button>
      <?php foreach ($vues as $v): ?>
      <button type="button" class="cv-card<?= $defaultMode === $v['mode'] ? ' active' : '' ?>" data-mode="<?= e($v['mode']) ?>">
        <span class="cv-ico"><?= e($v['icon']) ?></span>
        <b><?= e($v['nom']) ?></b>
        <small><?= e($v['desc']) ?></small>
      </button>
      <?php endforeach; ?>
    </div>

    <?php if ($apparences): ?>
    <div class="cv-sub">🎨 Apparence / univers <span class="cv-sub-hint">(s'applique aux vues jeu)</span></div>
    <div class="cv-biomes">
      <?php foreach ($apparences as $a): ?>
      <button type="button" class="cv-biome<?= $defaultBiome === $a['biome'] ? ' active' : '' ?>" data-biome="<?= e($a['biome']) ?>">
        <span class="cvb-ico"><?= e($a['icon']) ?></span><?= e($a['nom']) ?>
      </button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- SEULE cette boîte change quand on choisit une autre vue -->
  <div class="carte-frame reveal" id="carteFrame" data-luvumbu="<?= e($luvumbuUrl) ?>" data-default-mode="<?= e($defaultMode) ?>" data-default-biome="<?= e($defaultBiome) ?>">
    <div id="carteDefault">
      <div class="carte-hud">
        <span>★ WORLD 1</span>
        <span class="carte-count"><?= count($projets) ?> ZONES</span>
      </div>

      <div class="carte-map" id="carteMap"
           data-projets='<?= e(json_encode($projets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
        <!-- chemin + nœuds générés en JS (js/carte.js) -->
      </div>

      <div class="carte-legend">
        <span><i class="lg lg-open"></i> Ouvert</span>
        <span><i class="lg lg-lock"></i> À venir / privé</span>
        <span class="carte-tip">💡 Clique sur une zone pour entrer</span>
      </div>
    </div>
    <!-- l'iframe (autres modes) est injectée ici par js/carte.js -->
  </div>

  <?php if ($projets): ?>
  <!-- Descriptions des zones — À L'EXTÉRIEUR de la carte, dans la typographie du portfolio -->
  <div class="carte-desc reveal">
    <?php foreach ($projets as $i => $p): $locked = (($p['etat'] ?? 'ouvert') === 'verrou'); ?>
    <article class="cd-card" id="cd-<?= (int)$i ?>">
      <div class="cd-ico">
        <?php if (!empty($p['img'])): ?><img src="<?= e($p['img']) ?>" alt=""><?php else: ?><?= e($p['icon'] ?? '★') ?><?php endif; ?>
      </div>
      <div class="cd-body">
        <?php if (!empty($p['folder'])): ?><div class="cd-folder">📁 <?= e($p['folder']) ?></div><?php endif; ?>
        <h4><span class="cd-num"><?= (int)$i + 1 ?></span> <?= e($p['nom'] ?? 'Zone') ?></h4>
        <p><?= e($p['desc'] ?? '') ?></p>
      </div>
      <?php if (!$locked && !empty($p['url'])): ?>
      <a class="cd-go" href="<?= e($p['url']) ?>"<?= strpos($p['url'], 'http') === 0 ? ' target="_blank" rel="noopener"' : '' ?>>Entrer ▶</a>
      <?php else: ?>
      <span class="cd-lock">🔒 Privé</span>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php if ($hasLuvumbu): ?>
<!-- Modale Espace admin : ouvre LUVUMBU LAND (connexion + paramétrage) SANS quitter le portfolio -->
<div class="admin-modal" id="adminModal" aria-hidden="true">
  <div class="admin-modal-inner">
    <div class="admin-modal-bar">
      <span>🔒 Espace admin — connexion &amp; paramétrage</span>
      <div class="am-actions">
        <a class="am-btn" href="<?= e($adminUrl) ?>" target="_blank" rel="noopener" title="Ouvrir dans un nouvel onglet">⤢ Onglet</a>
        <button type="button" class="am-btn am-close" id="adminClose" title="Fermer (Échap)">✕ Fermer</button>
      </div>
    </div>
    <iframe id="adminFrame" title="Espace admin LUVUMBU LAND" data-src="<?= e($adminUrl) ?>"></iframe>
  </div>
</div>
<?php endif; ?>

