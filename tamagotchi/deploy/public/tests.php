<?php
/**
 * Rapport de tests WEB — vérifie tous les exercices de chaque classe.
 * Ouvrir : http://localhost/tamagotchi/public/tests.php
 * Pour chaque exercice : on génère N questions et on vérifie qu'il y a
 * EXACTEMENT une bonne réponse (et que la structure est valide).
 */

declare(strict_types=1);
require __DIR__ . '/../src/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\LearningService;
use App\Services\ProgressService;

$SAMPLES = isset($_GET['n']) ? max(5, min(100, (int) $_GET['n'])) : 25;

$learning = new LearningService();

// Libellés des classes
$LABELS = [
    'age3' => '🧸 PS (3-4 ans)', 'age4' => '🎈 MS (4-5 ans)', 'age5' => '✏️ GS (5-6 ans)',
    'age6' => '🎓 CP (6-7)', 'age7' => '🧮 CE1 (7-8)', 'age8' => '📚 CE2 (8-9)',
    'age9' => '🏫 CM1 (9-10)', 'age10' => '🎖️ CM2 (10-11)', 'age11' => '📐 6ème (11-12)',
    'age12' => '📊 5ème (12-13)', 'age13' => '🔬 4ème (13-14)', 'age14' => '🎓 3ème (14-15)',
    'age15' => '🏛️ 2nde (15-16)',
];

$groups = (new ReflectionClass(ProgressService::class))->getConstant('GROUPS');

/** Teste un exercice : renvoie [ok, tested, fails[]]. */
function testTopic(LearningService $svc, string $topic, int $samples): array
{
    $fails = [];
    for ($i = 0; $i < $samples; $i++) {
        $q = $svc->question($topic);

        // Structure valide ?
        foreach (['prompt', 'choices', 'token'] as $f) {
            if (empty($q[$f])) {
                $fails[] = "champ « $f » manquant";
                continue 2;
            }
        }
        if (count($q['choices']) < 2) {
            $fails[] = 'moins de 2 choix';
            continue;
        }
        // Exactement une bonne réponse ?
        $correct = 0;
        $values = [];
        foreach ($q['choices'] as $ch) {
            $values[] = $ch['value'];
            if ($svc->check($q['token'], (string) $ch['value'])['correct']) {
                $correct++;
            }
        }
        if ($correct !== 1) {
            $fails[] = "$correct bonne(s) réponse(s) — « {$q['prompt']} »";
        } elseif (count($values) !== count(array_unique($values))) {
            $fails[] = "choix en double — « {$q['prompt']} »";
        }
    }
    return ['ok' => empty($fails), 'tested' => $samples, 'fails' => $fails];
}

// On exécute tous les tests
$report = [];
$totalTopics = 0; $okTopics = 0; $totalQuestions = 0; $totalFails = 0;
$start = microtime(true);

foreach ($groups as $g) {
    $rows = [];
    foreach ($g['topics'] as $topic) {
        $r = testTopic($learning, $topic, $SAMPLES);
        $rows[] = ['topic' => $topic] + $r;
        $totalTopics++;
        $totalQuestions += $r['tested'];
        if ($r['ok']) { $okTopics++; } else { $totalFails += count($r['fails']); }
    }
    $report[$g['id']] = $rows;
}
$elapsed = round((microtime(true) - $start) * 1000);
$allGreen = ($okTopics === $totalTopics);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🧪 Rapport de tests</title>
<style>
  :root { --green:#58cc02; --green-d:#3f9000; --red:#e63946; --ink:#2b2b2b; }
  * { box-sizing:border-box; margin:0; padding:0; font-family:'Segoe UI',system-ui,sans-serif; }
  body { background:#f4f6f8; color:var(--ink); padding:1rem; }
  .wrap { max-width:900px; margin:0 auto; }
  h1 { font-size:1.5rem; margin-bottom:.3rem; }
  .sub { color:#777; margin-bottom:1rem; font-size:.9rem; }
  .summary { border-radius:16px; padding:1.1rem 1.3rem; margin-bottom:1.2rem; color:#fff; font-weight:700; }
  .summary.ok { background:var(--green); }
  .summary.ko { background:var(--red); }
  .summary .big { font-size:1.6rem; }
  .summary .stats { margin-top:.5rem; font-weight:500; font-size:.95rem; }
  .toolbar { margin-bottom:1rem; display:flex; gap:.5rem; flex-wrap:wrap; }
  .btn { background:#fff; border:2px solid #ddd; border-radius:10px; padding:.5rem .9rem; cursor:pointer; text-decoration:none; color:var(--ink); font-weight:700; font-size:.9rem; }
  .class { background:#fff; border-radius:14px; margin-bottom:1rem; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.06); }
  .class h2 { font-size:1.05rem; padding:.7rem 1rem; background:#fafafa; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; }
  .class h2 .badge { font-size:.8rem; padding:.15rem .5rem; border-radius:8px; color:#fff; }
  .badge.ok { background:var(--green); } .badge.ko { background:var(--red); }
  table { width:100%; border-collapse:collapse; }
  td, th { padding:.45rem .8rem; text-align:left; font-size:.9rem; border-bottom:1px solid #f0f0f0; }
  th { color:#888; font-size:.75rem; text-transform:uppercase; }
  tr.fail td { background:#fff3f3; }
  .status-ok { color:var(--green-d); font-weight:800; }
  .status-ko { color:var(--red); font-weight:800; }
  .failmsg { color:var(--red); font-size:.8rem; }
  code { background:#eef; padding:.05rem .3rem; border-radius:5px; }
  @media (prefers-color-scheme: dark) {
    body { background:#1e2126; color:#e8e8e8; }
    .class, .summary { box-shadow:none; }
    .class { background:#2a2e35; }
    .class h2 { background:#242830; border-color:#333; }
    td,th { border-color:#333; }
    tr.fail td { background:#3a2626; }
    .btn { background:#2a2e35; color:#e8e8e8; border-color:#444; }
  }
</style>
</head>
<body>
<div class="wrap">
  <h1>🧪 Rapport de tests — toutes les classes</h1>
  <p class="sub">Chaque exercice est généré <?= $SAMPLES ?> fois. On vérifie qu'il y a exactement UNE bonne réponse et une structure valide. Terminé en <?= $elapsed ?> ms.</p>

  <div class="summary <?= $allGreen ? 'ok' : 'ko' ?>">
    <div class="big"><?= $allGreen ? '✅ TOUT EST BON' : '❌ DES ERREURS DÉTECTÉES' ?></div>
    <div class="stats">
      <?= $okTopics ?>/<?= $totalTopics ?> exercices OK ·
      <?= number_format($totalQuestions, 0, ',', ' ') ?> questions vérifiées<?php if (!$allGreen): ?> ·
      <?= $totalFails ?> échec(s)<?php endif; ?>
    </div>
  </div>

  <div class="toolbar">
    <a class="btn" href="?n=<?= $SAMPLES ?>">🔄 Relancer</a>
    <a class="btn" href="?n=50">Test approfondi (×50)</a>
    <a class="btn" href="index.html">← Retour au jeu</a>
  </div>

<?php foreach ($report as $ageId => $rows):
    $classOk = array_reduce($rows, fn ($c, $r) => $c && $r['ok'], true);
    $label = $LABELS[$ageId] ?? $ageId; ?>
  <div class="class">
    <h2><?= htmlspecialchars($label) ?>
      <span class="badge <?= $classOk ? 'ok' : 'ko' ?>"><?= $classOk ? 'OK' : 'ERREUR' ?></span>
    </h2>
    <table>
      <tr><th>Exercice</th><th>Testé</th><th>Résultat</th></tr>
      <?php foreach ($rows as $r): ?>
      <tr class="<?= $r['ok'] ? '' : 'fail' ?>">
        <td><code><?= htmlspecialchars($r['topic']) ?></code></td>
        <td><?= $r['tested'] ?>×</td>
        <td>
          <?php if ($r['ok']): ?>
            <span class="status-ok">✅ OK</span>
          <?php else: ?>
            <span class="status-ko">❌ <?= count($r['fails']) ?> échec(s)</span>
            <div class="failmsg"><?= htmlspecialchars(implode(' | ', array_slice($r['fails'], 0, 3))) ?></div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
<?php endforeach; ?>
</div>
</body>
</html>
