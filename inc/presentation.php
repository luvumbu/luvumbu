<?php
/* ═══════════════════════════════════════════════════════════
   PRÉSENTATION — rendu des sections « classiques » du portfolio
   à partir de $CFG. Fichier séparé de la carte (inc/carte.php).
   ═══════════════════════════════════════════════════════════ */
if (!isset($CFG)) { return; }
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
$H = $CFG['hero']; $A = $CFG['about']; $P = $CFG['projet'];
?>

<!-- ░░ HERO ░░ -->
<section class="hero" id="hero">
  <div class="hero-inner reveal">
    <?php if (!empty($CFG['identite']['avatar'])): ?>
    <img class="hero-avatar" src="<?= e($CFG['identite']['avatar']) ?>" alt="<?= e($CFG['identite']['nom']) ?>">
    <?php endif; ?>
    <p class="hero-eyebrow"><span class="dot"></span> <?= e($CFG['identite']['dispo']) ?></p>
    <h1 class="hero-title"><?= $H['titre'] /* html volontaire */ ?></h1>
    <p class="hero-lead"><?= $H['lead'] ?></p>
    <div class="hero-cta">
      <a href="<?= e($H['cta1'][1]) ?>" class="btn btn-primary"><?= e($H['cta1'][0]) ?></a>
      <a href="<?= e($H['cta2'][1]) ?>" class="btn btn-ghost"><?= e($H['cta2'][0]) ?></a>
    </div>
    <div class="hero-stats">
      <?php foreach ($H['stats'] as $s): ?>
      <div class="hstat"><b data-count="<?= (int)$s['n'] ?>" data-suffix="<?= e($s['suffix']) ?>">0</b><span><?= e($s['label']) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
  <a href="#about" class="scroll-hint" aria-label="Défiler"><span></span></a>
</section>

<!-- ░░ PROFIL ░░ -->
<section class="section" id="about">
  <div class="section-head reveal">
    <span class="tag">01 — Profil</span>
    <h2>L'artisan derrière le code</h2>
  </div>
  <div class="about-grid">
    <div class="about-text reveal">
      <?php foreach ($A['paragraphes'] as $para): ?><p><?= $para ?></p><?php endforeach; ?>
      <ul class="about-facts">
        <?php foreach ($A['facts'] as $f): ?>
        <li><span><?= e($f[0]) ?></span> <?= e($f[1]) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="about-card reveal">
      <div class="term">
        <div class="term-bar"><i></i><i></i><i></i><span>luvumbu@dev — bash</span></div>
        <pre class="term-body"><code><?php foreach ($A['terminal'] as $ln):
          if ($ln[0] === '~$') echo '<span class="c-p">~$</span> '.e($ln[1])."\n";
          elseif ($ln[0] === 'ok') echo '<span class="c-g">'.e($ln[1])."</span>\n";
          elseif ($ln[0] === 'out') {
            $t = $ln[1]; $pos = strpos($t, '#');
            if ($pos !== false) echo '<span class="c-o">'.e(substr($t,0,$pos)).'</span><span class="c-d">'.e(substr($t,$pos))."</span>\n";
            else echo '<span class="c-o">'.e($t)."</span>\n";
          } else echo "\n";
        endforeach; ?></code></pre>
      </div>
    </div>
  </div>
</section>

<!-- ░░ STACK ░░ -->
<section class="section" id="stack">
  <div class="section-head reveal">
    <span class="tag">02 — Stack</span>
    <h2>Les outils que je maîtrise</h2>
    <p class="section-sub">Pas de magie, pas de framework surdimensionné. Des fondations solides, comprises en profondeur.</p>
  </div>
  <div class="stack-grid">
    <?php foreach ($CFG['stack'] as $st): ?>
    <div class="stack-card reveal"><b><?= e($st[0]) ?></b><span><?= e($st[1]) ?></span></div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ░░ PROJET PHARE ░░ -->
<section class="section section-feature" id="bokonzi">
  <div class="section-head reveal">
    <span class="tag">03 — Projet phare</span>
    <h2><span class="grad"><?= e($P['nom']) ?></span></h2>
    <p class="section-sub"><?= e($P['sous']) ?></p>
  </div>
  <?php if (!empty($P['image'])): ?>
  <a class="feature-shot reveal" href="<?= e($P['lien'][1]) ?>" target="_blank" rel="noopener" title="<?= e($P['nom']) ?>">
    <img src="<?= e($P['image']) ?>" alt="Capture d'écran — <?= e($P['nom']) ?>" loading="lazy">
  </a>
  <?php endif; ?>
  <div class="feature-wrap reveal">
    <div class="feature-side">
      <p class="feature-lead"><?= $P['lead'] ?></p>
      <div class="feature-metrics">
        <?php foreach ($P['metrics'] as $m): ?>
        <div><b><?= e($m[0]) ?></b><span><?= e($m[1]) ?></span></div>
        <?php endforeach; ?>
      </div>
      <div class="feature-tags">
        <?php foreach ($P['tags'] as $t): ?><span><?= e($t) ?></span><?php endforeach; ?>
      </div>
      <a href="<?= e($P['lien'][1]) ?>" target="_blank" rel="noopener" class="btn btn-primary"><?= e($P['lien'][0]) ?></a>
    </div>
    <div class="feature-list">
      <?php foreach ($P['features'] as $ft): ?>
      <article class="feat reveal"><h3><?= e($ft[0]) ?></h3><p><?= e($ft[1]) ?></p></article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ░░ SAVOIR-FAIRE ░░ -->
<section class="section" id="skills">
  <div class="section-head reveal">
    <span class="tag">04 — Prestations</span>
    <h2>Ce que je peux construire pour vous</h2>
  </div>
  <div class="skills-grid">
    <?php foreach ($CFG['skills'] as $sk): ?>
    <div class="skill reveal">
      <span class="skill-ico"><?= e($sk[0]) ?></span>
      <h3><?= e($sk[1]) ?></h3>
      <p><?= e($sk[2]) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ░░ CONTACT ░░ -->
<section class="section section-contact" id="contact">
  <div class="contact-card reveal">
    <span class="tag">05 — Contact</span>
    <h2><?= e($CFG['contact']['titre']) ?></h2>
    <p><?= e($CFG['contact']['texte']) ?></p>

    <form class="contact-form" id="contactForm" novalidate>
      <div class="cf-row">
        <input type="text" name="nom" placeholder="Votre nom" required autocomplete="name">
        <input type="email" name="email" placeholder="Votre email" required autocomplete="email">
      </div>
      <textarea name="message" placeholder="Votre projet, votre besoin…" rows="5" required></textarea>
      <!-- anti-spam (honeypot, laissé vide) -->
      <input type="text" name="website" class="cf-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="cf-bottom">
        <button type="submit" class="btn btn-primary" id="cfSubmit">Envoyer le message ✉️</button>
        <?php foreach ($CFG['contact']['actions'] as $ac): if (strpos($ac[1], 'http') === 0): ?>
        <a href="<?= e($ac[1]) ?>" target="_blank" rel="noopener" class="btn btn-ghost"><?= e($ac[0]) ?></a>
        <?php endif; endforeach; ?>
      </div>
      <div class="cf-status" id="cfStatus" role="status"></div>
    </form>
  </div>
</section>
