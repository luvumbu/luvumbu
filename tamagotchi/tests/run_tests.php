<?php
/**
 * Suite de tests du module d'apprentissage.
 * Vérifie CHAQUE exercice (tous niveaux) : structure valide + exactement UNE bonne réponse.
 *
 * Lancer :  php tests/run_tests.php
 */

require __DIR__ . '/../src/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\LearningService;
use App\Services\ProgressService;

$learning = new LearningService();

// --- Récupère tous les thèmes et pools via réflexion ---
$poolsConst = (new ReflectionClass(LearningService::class))->getConstant('POOLS');
$groups     = (new ReflectionClass(ProgressService::class))->getConstant('GROUPS');

$poolKeys = array_keys($poolsConst);                 // age3, age4, ..., all
$topics   = [];
foreach ($poolsConst as $list) {
    foreach ($list as $t) {
        $topics[$t] = true;
    }
}
$topics = array_keys($topics);                        // tous les thèmes concrets

$REPEATS = 30;                                        // chaque exercice testé 30 fois
$fails   = [];
$ok      = 0;

/** Vérifie une question : structure + une seule bonne réponse. */
function checkQuestion(LearningService $svc, array $q, string $label, array &$fails): bool
{
    foreach (['prompt', 'choices', 'token', 'type'] as $field) {
        if (!isset($q[$field]) || $q[$field] === '' || $q[$field] === []) {
            $fails[] = "$label : champ « $field » manquant";
            return false;
        }
    }
    if (count($q['choices']) < 2) {
        $fails[] = "$label : moins de 2 choix";
        return false;
    }
    $correct = 0;
    $values  = [];
    foreach ($q['choices'] as $ch) {
        if (!isset($ch['label']) || !isset($ch['value']) || $ch['label'] === '') {
            $fails[] = "$label : choix mal formé";
            return false;
        }
        $values[] = $ch['value'];
        if ($svc->check($q['token'], (string) $ch['value'])['correct']) {
            $correct++;
        }
    }
    if ($correct !== 1) {
        $fails[] = "$label : $correct bonne(s) réponse(s) au lieu d'1  (prompt: {$q['prompt']})";
        return false;
    }
    // Les valeurs de choix doivent être uniques (sinon ambiguïté)
    if (count($values) !== count(array_unique($values))) {
        $fails[] = "$label : choix en double";
        return false;
    }
    return true;
}

echo "=== 1) Chaque thème concret ({$REPEATS}× chacun) ===\n";
foreach ($topics as $t) {
    $localFail = 0;
    for ($i = 0; $i < $REPEATS; $i++) {
        $q = $learning->question($t);
        if (!checkQuestion($learning, $q, "thème=$t", $fails)) {
            $localFail++;
            if ($localFail > 2) break;               // évite le spam
        } else {
            $ok++;
        }
    }
}
printf("  %d thèmes testés\n", count($topics));

echo "=== 2) Chaque pool (âge / au hasard) ===\n";
foreach (array_merge($poolKeys, ['eveil']) as $pool) {
    for ($i = 0; $i < $REPEATS; $i++) {
        $q = $learning->question($pool);
        if (checkQuestion($learning, $q, "pool=$pool", $fails)) {
            $ok++;
        }
    }
}
printf("  %d pools testés\n", count($poolKeys) + 1);

echo "=== 3) Cohérence progression ⊆ pools ===\n";
$missing = [];
foreach ($groups as $g) {
    foreach ($g['topics'] as $t) {
        if (!in_array($t, $topics, true)) {
            $missing[] = "{$g['id']} → thème « $t » absent des pools";
        }
    }
}
if ($missing) {
    $fails = array_merge($fails, $missing);
} else {
    echo "  OK : tous les thèmes de progression existent dans les pools\n";
}

echo "=== 4) Anti-triche : token falsifié rejeté ===\n";
$bad = $learning->check('Nw==.deadbeef', '7');
if ($bad['valid'] !== false) {
    $fails[] = "Sécurité : un token falsifié a été accepté !";
} else {
    echo "  OK : token falsifié bien rejeté\n";
}

// --- Bilan ---
echo "\n========================================\n";
if (empty($fails)) {
    echo "✅ TOUS LES TESTS PASSENT ($ok questions vérifiées)\n";
    exit(0);
}
echo "❌ " . count($fails) . " ÉCHEC(S) :\n";
foreach (array_slice($fails, 0, 40) as $f) {
    echo "  - $f\n";
}
exit(1);
