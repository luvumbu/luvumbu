<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect(base_url('index.php')); }

$stmt = $pdo->prepare('SELECT * FROM quizzes WHERE id = ?');
$stmt->execute([$id]);
$quiz = $stmt->fetch();
if (!$quiz) { http_response_code(404); $quiz = null; }

// Brouillon : visible seulement par l'auteur ou un admin.
$viewer  = current_user();
$canSee  = $quiz && ((int)$quiz['active'] === 1
    || ($viewer && ((int)$viewer['id'] === (int)$quiz['author_id'] || is_admin())));
if ($quiz && !$canSee) { http_response_code(404); $quiz = null; }

$questions = [];
if ($quiz) {
    $qs = $pdo->prepare('SELECT id, body, explanation, type FROM quiz_questions WHERE quiz_id = ? ORDER BY position ASC, id ASC');
    $qs->execute([$id]);
    foreach ($qs->fetchAll() as $q) {
        $os = $pdo->prepare('SELECT label, is_correct FROM quiz_options WHERE question_id = ? ORDER BY position ASC, id ASC');
        $os->execute([$q['id']]);
        $q['options'] = $os->fetchAll();
        $questions[] = $q;
    }
}

// Article auquel ce questionnaire est rattaché : sert de renvoi « relire le chapitre »
// dans chaque correction (l'explication renvoie vers la source interne qui la confirme).
$srcArticle = null;
if ($quiz) {
    $sa = $pdo->prepare('SELECT a.id, a.titre FROM article_quizzes aq
                         JOIN articles a ON a.id = aq.article_id
                         WHERE aq.quiz_id = ? AND a.visible = 1
                         ORDER BY aq.position ASC, a.id ASC LIMIT 1');
    $sa->execute([$id]);
    $srcArticle = $sa->fetch() ?: null;
}

// Rend une explication : échappée, liens cliquables, retours à la ligne conservés.
function qz_explanation_html($text) {
    $html = e($text);
    $html = preg_replace(
        '~(https?://[^\s<]+[^\s<.,;:!?)\]])~',
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
        $html
    );
    return nl2br($html);
}

// Réglages du site : fixés par l'administrateur (pages/settings.php), non modifiables par le visiteur.
$qzMode    = quiz_mode_default();    // one | all
$qzEffect  = quiz_effect_default();  // none | fade | slide | up | zoom | flip
$qzReveal  = quiz_reveal_default();  // live | end
$isLogged  = is_logged_in();
$loginUrl  = base_url('pages/login.php?next=quiz&qid=' . $id);
$regUrl    = base_url('pages/register.php?next=quiz&qid=' . $id);

$pageTitle = $quiz ? $quiz['title'] : 'Questionnaire';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-card auth-card-wide">
<?php if (!$quiz): ?>
    <h1>Questionnaire introuvable</h1>
    <p class="muted">Ce questionnaire n'existe pas ou n'est pas encore publié.</p>
    <p><a class="btn-primary" href="<?= e(base_url('index.php')) ?>">← Retour</a></p>
<?php else: ?>
    <h1>📝 <?= e($quiz['title']) ?></h1>
    <?php if (!empty($quiz['description'])): ?>
        <p class="muted"><?= e($quiz['description']) ?></p>
    <?php endif; ?>

    <?php if (!$isLogged): ?>
        <div class="qz-note">
            Tu peux commencer tout de suite, sans compte.
            <strong>La connexion ne sera demandée qu'à la fin</strong>, pour afficher ton résultat.
        </div>
    <?php endif; ?>

    <div class="qz-progress"><i id="qzbar"></i></div>

    <div id="qz-quiz"
         data-mode="<?= e($qzMode) ?>"
         data-effect="<?= e($qzEffect) ?>"
         data-reveal="<?= e($qzReveal) ?>"
         data-logged="<?= $isLogged ? '1' : '0' ?>"
         data-quiz="<?= (int)$id ?>">
        <?php foreach ($questions as $i => $q):
            $correctIndex = -1;
            foreach ($q['options'] as $k => $o) { if ((int)$o['is_correct'] === 1) { $correctIndex = $k; break; } }
        ?>
            <div class="qz-q" data-answer="<?= (int)$correctIndex ?>">
                <div class="qz-qh">Question <?= $i + 1 ?> / <?= count($questions) ?></div>
                <div class="qz-qtext"><?= e($q['body']) ?></div>
                <?php foreach ($q['options'] as $o): ?>
                    <button class="qz-opt" type="button"><?= e($o['label']) ?></button>
                <?php endforeach; ?>
                <?php if (!empty($q['explanation']) || $srcArticle): ?>
                    <div class="qz-expl">
                        <?php if (!empty($q['explanation'])): ?>
                            <?= qz_explanation_html($q['explanation']) ?>
                        <?php endif; ?>
                        <?php if ($srcArticle): ?>
                            <a class="qz-relire" href="<?= e(base_url('pages/article.php?id=' . (int)$srcArticle['id'])) ?>">
                                📖 Relire le chapitre : <?= e($srcArticle['titre']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="qz-nav">
        <button class="btn-primary" type="button" id="qz-next" hidden>Suivante →</button>
    </div>

    <!-- Résultat : visible uniquement une fois connecté. -->
    <div class="qz-final" id="qz-final" hidden>
        <div class="qz-score"><span id="qz-num">0</span> / <?= count($questions) ?></div>
        <div id="qz-badge" class="qz-badge">Commence le quiz…</div>
        <button class="btn-primary" type="button" id="qz-restart">↻ Recommencer</button>
    </div>

    <!-- Porte de connexion : test terminé, mais visiteur non connecté. -->
    <div class="qz-gate" id="qz-gate" hidden>
        <div class="qz-gate-ico">🔒</div>
        <h2>Test terminé !</h2>
        <p class="muted">Connecte-toi pour découvrir ton score<?= $qzReveal === 'end' ? ' et la correction' : '' ?>. Tes réponses sont conservées.</p>
        <p>
            <a class="btn-primary" href="<?= e($loginUrl) ?>" style="display:inline-block;text-decoration:none;">Se connecter</a>
            <a href="<?= e($regUrl) ?>" style="margin-left:12px;">Créer un compte</a>
        </p>
    </div>
<?php endif; ?>
</div>

<style>
/* Les couleurs suivent le thème du blog (variables de themes.css), avec repli sur le style clair. */
.qz-note{margin:14px 0 0;padding:10px 14px;border-radius:10px;background:rgba(244,193,75,.12);border:1px solid var(--accent, #f4c14b);font-size:14px;color:var(--text, inherit)}
.qz-progress{height:6px;background:var(--surface-2, rgba(0,0,0,.1));border:1px solid var(--border, transparent);border-radius:99px;margin:18px 0 24px;overflow:hidden}
.qz-progress>i{display:block;height:100%;width:0;background:var(--accent, #c98a00);transition:width .4s}
#qz-quiz{perspective:1000px}
.qz-q{background:var(--surface-2, rgba(0,0,0,.02));border:1px solid var(--border, rgba(0,0,0,.08));border-radius:14px;padding:18px 20px;margin-bottom:16px;color:var(--text, inherit)}
.qz-q[hidden]{display:none}
.qz-qh{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted, #999);font-weight:700;margin-bottom:6px}
.qz-qtext{font-weight:600;margin-bottom:12px;font-size:16px}
.qz-opt{display:block;width:100%;text-align:left;padding:11px 14px;margin:7px 0;border-radius:9px;border:1px solid var(--border, rgba(0,0,0,.12));background:var(--surface, #fff);color:var(--text, inherit);cursor:pointer;font-size:15px;font-family:inherit;transition:.15s}
.qz-opt:hover:not(:disabled){border-color:var(--accent, #f4c14b)}
.qz-opt:disabled{cursor:default}
/* Réponse enregistrée, correction plus tard : teinte d'accent du thème. */
.qz-opt.chosen{background:color-mix(in srgb, var(--accent, #c98a00) 15%, var(--surface, #fff));border-color:var(--accent, #c98a00);font-weight:600}
/* Juste / faux : couleurs sémantiques, identiques dans tous les thèmes (lisibles sur fond clair comme sombre). */
.qz-opt.correct{background:rgba(22,163,74,.18);border-color:#16a34a;color:var(--text, #14532d)}
.qz-opt.wrong{background:rgba(220,38,38,.18);border-color:#dc2626;color:var(--text, #7f1d1d)}
.qz-expl{margin-top:10px;padding:12px 14px;border-radius:9px;background:var(--surface, #fff);border-left:3px solid #16a34a;font-size:14px;display:none;color:var(--text-soft, inherit);line-height:1.6}
.qz-expl.show{display:block}
.qz-expl a{color:var(--accent, #c98a00);word-break:break-word}
.qz-relire{display:inline-block;margin-top:10px;padding:6px 12px;border-radius:8px;border:1px solid var(--accent, #c98a00);font-weight:600;text-decoration:none;font-size:13px}
.qz-relire:hover{background:var(--accent, #c98a00);color:var(--accent-contrast, #fff)}
.qz-nav{display:flex;justify-content:flex-end;margin-bottom:16px}
.qz-nav button[hidden]{display:none}
.qz-final,.qz-gate{text-align:center;padding:22px;border-radius:16px;background:var(--surface-2, rgba(244,193,75,.1));border:1px solid var(--border, transparent);margin-top:8px;color:var(--text, inherit)}
.qz-final[hidden],.qz-gate[hidden]{display:none}
.qz-score{font-size:40px;font-weight:800;color:var(--accent, #c98a00)}
.qz-badge{display:inline-block;padding:5px 14px;border-radius:99px;font-weight:700;color:#fff;background:#888;margin:6px 0 14px}
.qz-gate-ico{font-size:34px}
.qz-gate h2{margin:4px 0 6px}

/* --- Effets de transition entre deux questions (choisis par l'admin) --- */
.qz-in-fade  {animation:qzInFade  .34s ease both}
.qz-out-fade {animation:qzOutFade .18s ease both}
.qz-in-slide {animation:qzInSlide .34s cubic-bezier(.22,.8,.3,1) both}
.qz-out-slide{animation:qzOutSlide .18s ease-in both}
.qz-in-up    {animation:qzInUp    .34s cubic-bezier(.22,.8,.3,1) both}
.qz-out-up   {animation:qzOutUp   .18s ease-in both}
.qz-in-zoom  {animation:qzInZoom  .34s cubic-bezier(.22,.8,.3,1) both}
.qz-out-zoom {animation:qzOutZoom .18s ease-in both}
.qz-in-flip  {animation:qzInFlip  .45s cubic-bezier(.22,.8,.3,1) both}
.qz-out-flip {animation:qzOutFlip .22s ease-in both}
@keyframes qzInFade  {from{opacity:0}                            to{opacity:1}}
@keyframes qzOutFade {from{opacity:1}                            to{opacity:0}}
@keyframes qzInSlide {from{opacity:0;transform:translateX(45px)} to{opacity:1;transform:none}}
@keyframes qzOutSlide{from{opacity:1;transform:none}             to{opacity:0;transform:translateX(-45px)}}
@keyframes qzInUp    {from{opacity:0;transform:translateY(32px)} to{opacity:1;transform:none}}
@keyframes qzOutUp   {from{opacity:1;transform:none}             to{opacity:0;transform:translateY(-24px)}}
@keyframes qzInZoom  {from{opacity:0;transform:scale(.9)}        to{opacity:1;transform:none}}
@keyframes qzOutZoom {from{opacity:1;transform:none}             to{opacity:0;transform:scale(1.06)}}
@keyframes qzInFlip  {from{opacity:0;transform:rotateY(75deg)}   to{opacity:1;transform:none}}
@keyframes qzOutFlip {from{opacity:1;transform:none}             to{opacity:0;transform:rotateY(-75deg)}}
@media (prefers-reduced-motion: reduce){
  .qz-q[class*="qz-in-"], .qz-q[class*="qz-out-"]{animation:none !important}
}
</style>
<script>
(function(){
  var box = document.getElementById('qz-quiz');
  if (!box) return;
  var qs = Array.from(box.querySelectorAll('.qz-q'));
  var total = qs.length;
  if (!total) return;

  var mode   = box.dataset.mode;    // 'one' | 'all'
  var effect = box.dataset.effect;  // 'none' | 'fade' | 'slide' | 'up' | 'zoom' | 'flip'
  var reveal = box.dataset.reveal;  // 'live' | 'end'
  var logged = box.dataset.logged === '1';
  var STORE  = 'qz_answers_' + box.dataset.quiz;
  var OUT_MS = 200; // >= durée des animations .qz-out-*

  var num     = document.getElementById('qz-num');
  var badge   = document.getElementById('qz-badge');
  var bar     = document.getElementById('qzbar');
  var final_  = document.getElementById('qz-final');
  var gate    = document.getElementById('qz-gate');
  var nextBtn = document.getElementById('qz-next');
  var restart = document.getElementById('qz-restart');

  var answers = new Array(total).fill(-1); // index choisi par question, -1 = sans réponse
  var answered = 0, correct = 0;
  var current = 0, busy = false;

  function store(){
    try {
      if (answered === total) localStorage.setItem(STORE, JSON.stringify(answers));
    } catch (err) {}
  }
  function clearStore(){ try { localStorage.removeItem(STORE); } catch (err) {} }
  function loadStore(){
    try {
      var raw = JSON.parse(localStorage.getItem(STORE) || 'null');
      if (Array.isArray(raw) && raw.length === total && raw.every(function(v){ return v >= 0; })) return raw;
    } catch (err) {}
    return null;
  }

  // --- Effets ---
  function animateIn(q){
    if (effect === 'none') return;
    var cls = 'qz-in-' + effect;
    q.classList.remove(cls);
    void q.offsetWidth; // relance l'animation
    q.classList.add(cls);
    q.addEventListener('animationend', function h(){
      q.classList.remove(cls);
      q.removeEventListener('animationend', h);
    });
  }
  function animateOut(q, done){
    if (effect === 'none') { done(); return; }
    var cls = 'qz-out-' + effect;
    q.classList.add(cls);
    setTimeout(function(){ q.classList.remove(cls); done(); }, OUT_MS);
  }

  // --- Correction d'une question ---
  function markChosen(q, i){
    var opts = q.querySelectorAll('.qz-opt');
    if (opts[i]) opts[i].classList.add('chosen');
  }
  function correction(q, i){
    var opts = q.querySelectorAll('.qz-opt');
    var ans  = parseInt(q.dataset.answer, 10);
    var expl = q.querySelector('.qz-expl');
    if (opts[i]) opts[i].classList.remove('chosen');
    if (i === ans) { if (opts[i]) opts[i].classList.add('correct'); }
    else {
      if (opts[i])   opts[i].classList.add('wrong');
      if (opts[ans]) opts[ans].classList.add('correct');
    }
    if (expl) expl.classList.add('show');
  }
  function isCorrect(q, i){ return i === parseInt(q.dataset.answer, 10); }

  function badgeText(){
    var t, c;
    if (correct === total){ t = '🏆 Parfait !'; c = '#16a34a'; }
    else if (correct >= total*0.75){ t = '👍 Très bien'; c = '#c98a00'; }
    else if (correct >= total*0.5){ t = '🙂 À consolider'; c = '#d97706'; }
    else { t = '📖 À revoir'; c = '#dc2626'; }
    badge.textContent = t; badge.style.background = c;
  }

  // Fin du test : score si connecté, sinon invitation à se connecter.
  function finish(){
    nextBtn.hidden = true;
    if (reveal === 'end') {
      qs.forEach(function(q, k){ correction(q, answers[k]); });
      if (mode === 'one') qs.forEach(function(q){ q.hidden = false; }); // correction complète
    }
    if (!logged) {
      store();
      gate.hidden = false;
      gate.scrollIntoView({behavior: 'smooth', block: 'nearest'});
      return;
    }
    clearStore();
    num.textContent = correct;
    badgeText();
    final_.hidden = false;
    final_.scrollIntoView({behavior: 'smooth', block: 'nearest'});
  }

  function updateNextBtn(){
    if (mode !== 'one' || busy || answered >= total) { nextBtn.hidden = true; return; }
    nextBtn.hidden = !(answers[current] >= 0) || current >= total - 1;
  }

  function goTo(i){
    if (busy || i < 0 || i >= total || i === current) return;
    busy = true;
    nextBtn.hidden = true;
    var from = qs[current], to = qs[i];
    animateOut(from, function(){
      from.hidden = true;
      current = i;
      to.hidden = false;
      animateIn(to);
      busy = false;
      updateNextBtn();
      to.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    });
  }

  function answer(qi, i){
    if (answers[qi] >= 0 || busy) return;
    answers[qi] = i;
    answered++;
    var q = qs[qi];
    q.querySelectorAll('.qz-opt').forEach(function(o){ o.disabled = true; });
    if (isCorrect(q, i)) correct++;

    if (reveal === 'live') correction(q, i);   // correction immédiate
    else                   markChosen(q, i);   // réponse notée, correction à la fin

    bar.style.width = (answered/total*100) + '%';

    if (answered === total) { finish(); return; }

    if (mode === 'all') {
      var nx = -1;
      for (var k = qi + 1; k < total; k++) { if (answers[k] < 0) { nx = k; break; } }
      if (nx !== -1) {
        setTimeout(function(){
          animateIn(qs[nx]);
          qs[nx].scrollIntoView({behavior: 'smooth', block: 'nearest'});
        }, reveal === 'live' ? 450 : 150);
      }
    } else {
      updateNextBtn();
    }
  }

  qs.forEach(function(q, qi){
    q.querySelectorAll('.qz-opt').forEach(function(opt, i){
      opt.addEventListener('click', function(){ answer(qi, i); });
    });
  });

  nextBtn.addEventListener('click', function(){ goTo(current + 1); });
  restart.addEventListener('click', function(){ clearStore(); location.reload(); });

  // Retour après connexion : on rejoue les réponses conservées et on affiche le résultat.
  var saved = logged ? loadStore() : null;
  if (saved) {
    saved.forEach(function(i, k){
      answers[k] = i; answered++;
      qs[k].querySelectorAll('.qz-opt').forEach(function(o){ o.disabled = true; });
      if (isCorrect(qs[k], i)) correct++;
      correction(qs[k], i);
    });
    qs.forEach(function(q){ q.hidden = false; });
    bar.style.width = '100%';
    clearStore();
    num.textContent = correct;
    badgeText();
    final_.hidden = false;
    return;
  }

  // Affichage initial.
  if (mode === 'all') {
    qs.forEach(function(q){ q.hidden = false; });
  } else {
    qs.forEach(function(q, i){ q.hidden = (i !== 0); });
  }
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
