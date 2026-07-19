<?php
namespace App\Services;

/**
 * Génère les exercices éducatifs et vérifie les réponses.
 *
 * Anti-triche : la bonne réponse n'est JAMAIS envoyée au navigateur.
 * On renvoie un "token" = réponse signée (HMAC). À la correction, on recalcule
 * la signature : impossible de la falsifier sans la clé secrète du serveur.
 */
class LearningService
{
    private string $secret;

    public function __construct()
    {
        $cfg = require __DIR__ . '/../../config/config.php';
        $this->secret = $cfg['learning']['secret'];
    }

    /**
     * Construit une question pour le niveau demandé.
     * Retour : prompt, éléments visuels, choix, et token (réponse signée).
     */
    /**
     * Pools d'exercices « au hasard » par tranche d'âge (cumulatifs).
     * Plus l'enfant grandit, plus le tirage couvre de thèmes.
     */
    private const POOLS = [
        // 3-4 ans : éveil complet (couleurs, formes, tailles, animaux, intrus,
        // compter jusqu'à 3, émotions, aliments, trouver le même).
        // 3-4 ans (petite section) — versions simples
        'age3' => ['problem', 'colors', 'shapes', 'sizes', 'animals', 'intrus', 'count3', 'emotions', 'foods', 'pareil', 'suite', 'assoc', 'compare', 'suitenum', 'body', 'animalsound'],
        // 4-5 ans (moyenne section) — mêmes thèmes (plus durs) + lettres, chiffres, contraires, calcul…
        'age4' => ['problem4', 'colors4', 'shapes4', 'sizes4', 'animals4', 'intrus4', 'count', 'emotions4', 'foods4', 'pareil4', 'suite4', 'assoc4', 'compare4', 'suitenum4', 'body4', 'animalsound4', 'letters', 'letters_sound', 'digits', 'opposites', 'categorie', 'rang', 'nextnum', 'numbig', 'addition', 'subtraction'],
        // 5-6 ans (grande section, niveau 3 = suffixe « 5 »)
        'age5' => ['problem5', 'colors5', 'shapes5', 'sizes5', 'animals5', 'animalsound5', 'foods5', 'body5', 'emotions5', 'opposites5', 'pareil5', 'intrus5', 'suite5', 'assoc5', 'compare5', 'suitenum5', 'categorie5', 'rang5', 'letters5', 'letters_sound5', 'digits5', 'count5', 'nextnum5', 'numbig5', 'addition', 'subtraction', 'addsub'],
        // 6-7 ans (CP, niveau 4 = suffixe « 6 »)
        'age6' => ['problem6', 'colors6', 'shapes6', 'sizes6', 'animals6', 'animalsound6', 'foods6', 'body6', 'emotions6', 'opposites6', 'pareil6', 'intrus6', 'suite6', 'assoc6', 'compare6', 'suitenum6', 'categorie6', 'rang6', 'letters6', 'letters_sound6', 'digits6', 'count6', 'nextnum6', 'numbig6', 'readword6', 'double6', 'addition', 'subtraction', 'addsub', 'muldiv'],
        // 7-8 ans (CE1, niveau 5 = suffixe « 7 »)
        'age7' => ['problem7', 'sizes7', 'animalsound7', 'opposites7', 'intrus7', 'suite7', 'assoc7', 'compare7', 'suitenum7', 'categorie7', 'rang7', 'letters7', 'letters_sound7', 'readword7', 'spell7', 'digits7', 'count7', 'nextnum7', 'numbig7', 'double7', 'roman7', 'time7', 'money7', 'addition', 'subtraction', 'addsub', 'muldiv'],
        // 8-9 ans (CE2, niveau 6 = suffixe « 8 ») — programme « à l'ancienne »
        'age8' => ['problem8', 'intrus8', 'suite8', 'suitenum8', 'compare8', 'categorie8', 'rang8', 'letters8', 'readword8', 'spell8', 'numbig8', 'nextnum8', 'count8', 'double8', 'roman8', 'measure8', 'time8', 'money8', 'addition', 'subtraction', 'addsub', 'muldiv'],
        // 9-10 ans (CM1, niveau 7 = suffixe « 9 »)
        'age9' => ['problem9', 'intrus9', 'suite9', 'assoc9', 'categorie9', 'rang9', 'suitenum9', 'compare9', 'readword9', 'spell9', 'letters9', 'digits9', 'count9', 'nextnum9', 'numbig9', 'time9', 'double9', 'half9', 'triple9', 'evenodd9', 'complement9', 'roman9', 'measure9', 'money9', 'addition', 'subtraction', 'addsub', 'muldiv'],
        // 10-11 ans (CM2, niveau 8 = suffixe « a ») — niveau certificat d'études
        'age10' => ['spella', 'readworda', 'numbiga', 'evenodda', 'complementa', 'fraction', 'decimals', 'problema', 'longmult', 'division', 'double9', 'triple9', 'romana', 'measurea', 'moneya', 'addition', 'subtraction', 'addsub', 'muldiv'],
        // 11-12 ans (6ème, collège)
        'age11' => ['priorities', 'square', 'perimeter', 'aire', 'relative', 'diveucl', 'multiple', 'roundten', 'comparedec', 'longmult', 'division', 'fraction', 'decimals', 'problema', 'probpercent', 'probpropor', 'numbiga', 'romana', 'muldiv', 'addition', 'subtraction'],
        // 12-13 ans (5ème)
        'age12' => ['problema', 'probpercent', 'probpropor', 'percent', 'airetri', 'priorpar', 'relatadd', 'relatsub', 'proportion', 'volumecube', 'fraction', 'decimals', 'relative', 'priorities', 'aire', 'perimeter', 'longmult', 'division', 'muldiv'],
        // 13-14 ans (4ème)
        'age13' => ['problema', 'probpercent', 'probpropor', 'power', 'powerten', 'relatmul', 'expand', 'factorise', 'percent', 'proportion', 'priorpar', 'airetri', 'relatadd', 'relatsub', 'square', 'longmult', 'division', 'muldiv'],
        // 14-15 ans (3ème)
        'age14' => ['problema', 'probpercent', 'probpropor', 'sqrt', 'equation', 'equation2', 'function', 'pythagore', 'thales', 'power', 'powerten', 'relatmul', 'expand', 'factorise', 'percent', 'proportion', 'priorpar', 'muldiv', 'longmult'],
        // 15-16 ans (Seconde, lycée)
        'age15' => ['problema', 'probpercent', 'probpropor', 'identremar', 'milieu', 'antecedent', 'evolution', 'moyenne', 'vecteur', 'sqrt', 'equation2', 'function', 'expand', 'factorise', 'power', 'percent', 'pythagore', 'thales'],
        'all'  => ['colors', 'shapes', 'sizes', 'animals', 'intrus', 'count3', 'emotions', 'foods', 'pareil', 'suite', 'assoc', 'compare', 'suitenum', 'body', 'animalsound',
                   'colors4', 'shapes4', 'sizes4', 'animals4', 'intrus4', 'count', 'emotions4', 'foods4', 'pareil4', 'suite4', 'assoc4', 'compare4', 'suitenum4', 'body4', 'animalsound4', 'letters', 'letters_sound', 'digits', 'opposites', 'categorie', 'rang', 'nextnum', 'numbig', 'addition', 'subtraction', 'addsub', 'muldiv'],
    ];

    /**
     * @param string $topic     thème ou pool (« age4 », « all »…)
     * @param array  $progress  [topic => bonnes réponses] pour adapter la difficulté du calcul
     */
    public function question(string $topic, array $progress = []): array
    {
        // Un thème « pool » (âge / au hasard) tire un exercice concret au sort.
        if (isset(self::POOLS[$topic])) {
            $pool  = self::POOLS[$topic];
            $topic = $pool[random_int(0, count($pool) - 1)];
        } elseif ($topic === 'eveil') {
            $pool  = self::POOLS['age3'];
            $topic = $pool[random_int(0, count($pool) - 1)];
        }

        // Nombre de réussites déjà obtenues sur CE thème → calcul progressif.
        $done = (int) ($progress[$topic] ?? 0);
        $q = $this->generate($topic, $done);

        // On sépare la réponse (secrète) du reste (public).
        $answer = (string) $q['answer'];
        unset($q['answer']);
        $q['topic'] = $topic;
        $q['token'] = $this->sign($answer);
        return $q;
    }

    /** Fabrique l'exercice concret correspondant à un thème. */
    private function generate(string $topic, int $done = 0): array
    {
        // Suffixe d'âge/classe → niveau : 4→2 (MS) … 8→6 (CE2), 9→7 (CM1), a→8 (CM2).
        $level = 1;
        $last  = substr($topic, -1);
        if (in_array($last, ['4', '5', '6', '7', '8', '9'], true)) {
            $level = (int) $last - 2;
            $topic = substr($topic, 0, -1);
        } elseif ($last === 'a') {
            $level = 8;                       // CM2
            $topic = substr($topic, 0, -1);
        }

        return match ($topic) {
            'colors'   => $this->colors($level),
            'shapes'   => $this->shapes($level),
            'sizes'    => $this->sizes($level),
            'animals'  => $this->animals($level),
            'intrus'   => $this->intruder($level),
            'count3'   => $this->countSmall(),
            'emotions' => $this->emotions($level),
            'foods'    => $this->foods($level),
            'pareil'   => $this->matching($level),
            'suite'    => $this->pattern($level),
            'assoc'    => $this->association($level),
            'compare'  => $this->compare($level),
            'suitenum' => $this->numberGap($level),
            'body'         => $this->body($level),      // 🧍 parties du corps
            'animalsound'  => $this->animalSound($level), // 🔊 cris des animaux
            'nextnum'      => $this->nextNum($level),    // 🔢 avant / après
            'numbig'       => $this->numBig($level),     // 🔢 le plus grand chiffre
            'letters'      => $this->letters($level),    // 🔤 reconnaître les lettres
            'letters_sound'=> $this->lettersSound($level), // 🔊 lettre au son (non affichée)
            'readword'     => $this->readWord(),         // 📖 lire un mot
            'double'       => $this->doubles($level),    // ✖️2 les doubles
            'half'         => $this->half($level),       // ✂️ la moitié
            'triple'       => $this->triple($level),     // ✖️3 le triple
            'evenodd'      => $this->evenOdd($level),    // 🔢 pair ou impair
            'complement'   => $this->complement($level), // 🎯 compléter à 10/100
            'fraction'     => $this->fraction(),         // ½ fractions d'un nombre (visuel)
            'decimals'     => $this->decimals(),         // 🔟 nombres décimaux (visuel)
            'problem'      => $this->problem($level),     // 📝 problèmes (adaptés à l'âge)
            'probpercent'  => $this->probPercent(),      // 📝 problème de pourcentage (collège)
            'probpropor'   => $this->probPropor(),       // 📝 problème de proportionnalité (collège)
            'longmult'     => $this->longMult(),         // ✖️ multiplications à 2 chiffres
            'division'     => $this->division(),         // ➗ divisions
            'priorities'   => $this->priorities(),       // ✖️➕ priorités opératoires (6e)
            'square'       => $this->squares(),          // ² les carrés / puissances (6e)
            'perimeter'    => $this->perimeter(),        // 📐 périmètre (6e)
            'aire'         => $this->area(),             // 🟦 aire (6e)
            'relative'     => $this->relative(),         // 🌡️ nombres relatifs (6e)
            'percent'      => $this->percent(),          // % pourcentages (5e)
            'airetri'      => $this->triangleArea(),     // 🔺 aire du triangle (5e)
            'priorpar'     => $this->priorPar(),         // () priorités avec parenthèses (5e)
            'relatadd'     => $this->relatAdd(),         // ± addition de relatifs (5e)
            'power'        => $this->power(),            // ^ puissances (4e)
            'relatmul'     => $this->relatMul(),         // ± multiplication de relatifs (4e)
            'expand'       => $this->expand(),           // x calcul littéral (4e)
            'sqrt'         => $this->sqrtEx(),           // √ racines carrées (3e)
            'equation'     => $this->equationEx(),       // = équations (3e)
            'pythagore'    => $this->pythagore(),        // △ théorème de Pythagore (3e)
            'thales'       => $this->thales(),           // △ théorème de Thalès (3e)
            'diveucl'      => $this->divEuclid(),        // reste de division (6e)
            'multiple'     => $this->multiple(),         // multiples (6e)
            'roundten'     => $this->roundTen(),         // arrondir (6e)
            'comparedec'   => $this->compareDec(),       // comparer décimaux (6e)
            'proportion'   => $this->proportion(),       // proportionnalité (5e)
            'relatsub'     => $this->relatSub(),         // soustraire des relatifs (5e)
            'volumecube'   => $this->volumeCube(),       // volume du cube (5e)
            'powerten'     => $this->powerTen(),         // puissances de 10 (4e)
            'factorise'    => $this->factorise(),        // factoriser (4e)
            'equation2'    => $this->equation2(),        // équation à 2 étapes (3e)
            'function'     => $this->functionEx(),       // image d'une fonction (3e)
            'identremar'   => $this->identRemar(),       // (a+b)² identités remarquables (2nde)
            'milieu'       => $this->milieu(),           // milieu d'un segment (2nde)
            'antecedent'   => $this->antecedent(),       // antécédent d'une fonction (2nde)
            'evolution'    => $this->evolution(),        // pourcentage d'évolution (2nde)
            'moyenne'      => $this->moyenne(),          // moyenne (2nde)
            'vecteur'      => $this->distanceEx(),       // distance sur une droite (2nde)
            'roman'        => $this->romanNumerals($level), // Ⅻ chiffres romains
            'measure'      => $this->measures(),         // 📏 système métrique
            'time'         => $this->tellTime(),         // 🕐 lire l'heure
            'spell'        => $this->spelling(),         // ✍️ orthographe
            'money'        => $this->money($level),      // 🪙 la monnaie
            'digits'       => $this->digits($level),     // 🔢 reconnaître les chiffres
            'opposites'    => $this->opposites(),       // ↔️ les contraires
            'categorie'    => $this->categorie(),       // 🗂️ trouver le bon groupe
            'rang'         => $this->rang(),            // 🥇 premier / dernier
            'count'      => $this->counting($level),
            'addition'   => $this->addition($done),     // ➕ progressive
            'subtraction'=> $this->subtraction($done),  // ➖ progressive
            'addsub'     => $this->addSub($done),        // mélange
            'muldiv'     => $this->mulDiv($done),        // progressive
            default      => $this->colors($level),
        };
    }

    /**
     * Vérifie une réponse. Retourne [correct(bool), correctAnswer(string)].
     */
    public function check(string $token, string $answer): array
    {
        $expected = $this->unsign($token);
        if ($expected === null) {
            return ['correct' => false, 'correctAnswer' => null, 'valid' => false];
        }
        $correct = $this->normalize($answer) === $this->normalize($expected);
        return ['correct' => $correct, 'correctAnswer' => $expected, 'valid' => true];
    }

    // ---------------------------------------------------------------
    //  Générateurs de questions par niveau
    // ---------------------------------------------------------------

    /** Reconnaître une lettre (alphabet complet à partir de la grande section). */
    private function letters(int $level = 1): array
    {
        $pool = $level >= 3 ? range('A', 'Z') : ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        shuffle($pool);
        $pick    = $pool[0];
        $choices = array_slice($pool, 0, 4);
        shuffle($choices);

        return [
            'type'    => 'letter',
            'prompt'  => "Trouve la lettre $pick !",
            'visual'  => '',
            'choices' => array_map(fn ($l) => ['label' => $l, 'value' => $l], $choices),
            'answer'  => $pick,
        ];
    }

    /** Chiffres romains (école d'autrefois). */
    private function romanNumerals(int $level = 1): array
    {
        $table = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII',
            8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII', 15 => 'XV',
            20 => 'XX', 40 => 'XL', 50 => 'L', 90 => 'XC', 100 => 'C', 500 => 'D', 1000 => 'M',
        ];
        $keys = array_keys($table);
        // Petits nombres d'abord ; L/C au CM1 ; D/M seulement au CM2.
        $cap  = $level >= 8 ? 1000 : ($level >= 7 ? 100 : 12);
        $pool = array_filter($keys, fn ($k) => $k <= $cap);
        $pool = array_values($pool);
        $n = $pool[random_int(0, count($pool) - 1)];

        // On montre le chiffre romain, l'enfant donne le nombre.
        $distract = [];
        while (count($distract) < 3) {
            $c = $keys[random_int(0, count($keys) - 1)];
            if ($c !== $n && !in_array($c, $distract, true)) {
                $distract[] = $c;
            }
        }
        $choices = array_merge([$n], $distract);
        shuffle($choices);

        return [
            'type'    => 'roman',
            'prompt'  => "Que vaut le chiffre romain {$table[$n]} ?",
            'visual'  => $table[$n],
            'choices' => array_map(fn ($v) => ['label' => (string) $v, 'value' => (string) $v], $choices),
            'answer'  => $n,
        ];
    }

    /** Système métrique / mesures (leçon d'autrefois). */
    private function measures(): array
    {
        $items = [
            ['1 mètre',      'centimètres', 100],
            ['1 kilomètre',  'mètres',      1000],
            ['1 heure',      'minutes',     60],
            ['1 minute',     'secondes',    60],
            ['1 kilogramme', 'grammes',     1000],
            ['1 euro',       'centimes',    100],
            ['1 semaine',    'jours',       7],
            ['1 douzaine',   '',            12],
        ];
        $it = $items[random_int(0, count($items) - 1)];
        $answer = $it[2];
        $prompt = $it[1] === ''
            ? "Combien y a-t-il dans {$it[0]} ?"
            : "Combien y a-t-il de {$it[1]} dans {$it[0]} ?";

        $options = array_values(array_unique(array_column($items, 2)));
        shuffle($options);
        $choices = array_slice(array_filter($options, fn ($v) => $v !== $answer), 0, 3);
        $choices[] = $answer;
        shuffle($choices);

        return [
            'type'    => 'measure',
            'prompt'  => $prompt,
            'visual'  => '📏',
            'choices' => array_map(fn ($v) => ['label' => (string) $v, 'value' => (string) $v], $choices),
            'answer'  => $answer,
        ];
    }

    /** Lire l'heure sur une horloge. */
    private function tellTime(): array
    {
        $clocks = ['🕐' => 1, '🕑' => 2, '🕒' => 3, '🕓' => 4, '🕔' => 5, '🕕' => 6,
                   '🕖' => 7, '🕗' => 8, '🕘' => 9, '🕙' => 10, '🕚' => 11, '🕛' => 12];
        $faces = array_keys($clocks);
        $face  = $faces[random_int(0, count($faces) - 1)];
        $answer = $clocks[$face];

        $choices = [$answer];
        while (count($choices) < 4) {
            $c = random_int(1, 12);
            if (!in_array($c, $choices, true)) {
                $choices[] = $c;
            }
        }
        shuffle($choices);

        return [
            'type'    => 'time',
            'prompt'  => 'Quelle heure est-il ?',
            'visual'  => $face,
            'choices' => array_map(fn ($v) => ['label' => $v . ' h', 'value' => (string) $v], $choices),
            'answer'  => $answer,
        ];
    }

    /** Orthographe : quel mot est bien écrit ? (la dictée d'autrefois). */
    private function spelling(): array
    {
        // [correct, mauvaises orthographes...]
        $words = [
            ['maison', 'mèzon', 'maizon'],
            ['bateau', 'batau', 'batteau'],
            ['oiseau', 'oizeau', 'oisot'],
            ['éléphant', 'élefant', 'éléfant'],
            ['fille', 'fiye', 'fiie'],
            ['chapeau', 'chapo', 'chappeau'],
            ['gâteau', 'gato', 'gatteau'],
            ['jaune', 'jonne', 'jaunne'],
        ];
        $w = $words[random_int(0, count($words) - 1)];
        $correct = $w[0];
        $choices = $w;
        shuffle($choices);

        return [
            'type'    => 'spell',
            'prompt'  => 'Quel mot est bien écrit ?',
            'visual'  => '✍️',
            'choices' => array_map(fn ($s) => ['label' => $s, 'value' => $s], $choices),
            'answer'  => $correct,
        ];
    }

    /** La monnaie : combien font ces pièces ? */
    private function money(int $level = 1): array
    {
        $coins = $level >= 6 ? [1, 2, 5, 10] : [1, 2, 5];
        $val   = $coins[random_int(0, count($coins) - 1)];
        $count = random_int(2, $level >= 6 ? 6 : 4);
        $answer = $val * $count;

        $pieces = str_repeat('🪙', $count);
        return [
            'type'    => 'money',
            'prompt'  => "$count pièces de $val, ça fait combien ?",
            'visual'  => $pieces,
            'choices' => $this->numberChoices($answer, 1, $answer + 8, 4),
            'answer'  => $answer,
        ];
    }

    /** Lire un mot et trouver l'image correspondante (CE1). */
    private function readWord(): array
    {
        $pairs = [
            ['chat', '🐱'], ['chien', '🐶'], ['pomme', '🍎'], ['soleil', '☀️'],
            ['maison', '🏠'], ['fleur', '🌸'], ['voiture', '🚗'], ['poisson', '🐟'],
            ['lune', '🌙'], ['gâteau', '🍰'], ['ballon', '🎈'], ['étoile', '⭐'],
        ];
        $pair = $pairs[random_int(0, count($pairs) - 1)];
        [$word, $answer] = $pair;

        $bag = [];
        foreach ($pairs as $p) {
            if ($p[1] !== $answer) {
                $bag[] = $p[1];
            }
        }
        shuffle($bag);
        $distractors = array_slice($bag, 0, 3);

        $choices = array_merge([$answer], $distractors);
        shuffle($choices);

        return [
            'type'    => 'readword',
            'prompt'  => "Lis le mot : « $word »",
            'visual'  => '',
            'choices' => array_map(fn ($e) => ['label' => $e, 'value' => $e], $choices),
            'answer'  => $answer,
        ];
    }

    /** Les doubles : le double d'un nombre (CE1). */
    private function doubles(int $level = 1): array
    {
        $max = $level >= 5 ? 20 : 10;
        $n   = random_int(2, $max);
        $res = $n * 2;

        return [
            'type'    => 'math',
            'prompt'  => "Le double de $n = ?",
            'visual'  => '',
            'choices' => $this->numberChoices($res, 2, $res + 6, 4),
            'answer'  => $res,
        ];
    }

    /** Fractions d'un nombre, avec REPRÉSENTATION VISUELLE (le nombre partagé en parts égales). */
    private function fraction(): array
    {
        $names = [2 => 'la moitié', 3 => 'le tiers', 4 => 'le quart', 5 => 'le cinquième'];
        $d = array_rand($names);
        $res = random_int(2, 4);
        $n = $res * $d;                                 // divisible → résultat entier

        // Visuel : le tout partagé en $d groupes égaux, chacun encadré en pointillés.
        $group  = str_repeat('🟦', $res);
        $groups = array_fill(0, $d, $group);

        return [
            'type'    => 'math',
            'prompt'  => "{$names[$d]} de $n = ? (regarde les $d groupes)",
            'visual'  => implode('   ', $groups),        // repli texte
            'groups'  => $groups,                        // cadres en pointillés côté front
            'choices' => $this->numberChoices($res, 1, $res + 8, 4),
            'answer'  => $res,
        ];
    }

    /** Nombres décimaux : une barre de 10 dixièmes, combien de parts coloriées ? */
    private function decimals(): array
    {
        $k = random_int(1, 9);
        $answer = '0,' . $k;                            // ex : 0,3

        $all = [];
        for ($i = 1; $i <= 9; $i++) {
            $all[] = '0,' . $i;
        }
        shuffle($all);
        $choices = array_slice(array_filter($all, fn ($v) => $v !== $answer), 0, 3);
        $choices[] = $answer;
        shuffle($choices);

        return [
            'type'    => 'decimal',
            'prompt'  => 'Quelle est la partie coloriée en vert ? (en dixièmes)',
            'visual'  => str_repeat('🟩', $k) . str_repeat('⬜', 10 - $k),  // repli texte
            'bar'     => ['filled' => $k, 'total' => 10],                    // barre dessinée côté front
            'choices' => array_map(fn ($v) => ['label' => $v, 'value' => $v], $choices),
            'answer'  => $answer,
        ];
    }

    /**
     * Problèmes ADAPTÉS À L'ÂGE :
     * petits (+/−) chez les jeunes, puis ×/÷ et de plus grands nombres.
     */
    private function problem(int $level = 1): array
    {
        $names   = ['Paul', 'Léa', 'Tom', 'Emma', 'Lucas', 'Zoé', 'Nina', 'Hugo'];
        $objects = ['billes', 'bonbons', 'images', 'gâteaux', 'pommes', 'autocollants', 'cartes'];
        $name = $names[random_int(0, count($names) - 1)];
        $obj  = $objects[random_int(0, count($objects) - 1)];

        // Opérations et tailles selon le niveau.
        if ($level <= 2) {          // maternelle / début primaire
            $ops = ['+', '-'];  $max = 5;
        } elseif ($level <= 4) {    // CP / CE1
            $ops = ['+', '-', 'x']; $max = 12;
        } elseif ($level <= 6) {    // CE2 / CM1
            $ops = ['+', '-', 'x', '/']; $max = 30;
        } else {                    // CM2 et +
            $ops = ['+', '-', 'x', '/']; $max = 60;
        }
        $op = $ops[random_int(0, count($ops) - 1)];

        switch ($op) {
            case '+':
                $a = random_int(1, $max); $b = random_int(1, $max);
                $res = $a + $b;
                $prompt = "$name a $a $obj. On lui en donne $b. Combien en a-t-il en tout ?";
                break;
            case '-':
                $a = random_int(2, $max); $b = random_int(1, $a);
                $res = $a - $b;
                $prompt = "$name a $a $obj. Il en donne $b. Combien lui en reste-t-il ?";
                break;
            case 'x':
                $a = random_int(2, $level <= 4 ? 5 : 9);
                $b = random_int(2, $level <= 4 ? 5 : 9);
                $res = $a * $b;
                $prompt = "$name a $a paquets de $b $obj. Combien de $obj en tout ?";
                break;
            default: // '/'
                $b = random_int(2, 6);
                $res = random_int(2, $level <= 6 ? 6 : 10);
                $a = $b * $res;
                $prompt = "$name partage $a $obj entre $b amis. Combien chacun en a-t-il ?";
                break;
        }

        return [
            'type'    => 'math',
            'prompt'  => $prompt,
            'visual'  => '📝',
            'choices' => $this->numberChoices($res, 0, $res + 8, 4),
            'answer'  => $res,
        ];
    }

    /** Problème de pourcentage (collège). */
    private function probPercent(): array
    {
        $pcts = [10, 20, 25, 50];
        $p = $pcts[random_int(0, count($pcts) - 1)];
        $res = random_int(2, 15);
        $n = (int) ($res * 100 / $p);

        $ctx = [
            "Dans une classe de $n élèves, $p % font du sport. Combien d'élèves ?",
            "Un manteau coûte $n €. Il y a $p % de réduction. Combien économise-t-on ?",
            "Sur $n bonbons, $p % sont au chocolat. Combien de bonbons au chocolat ?",
        ];
        return [
            'type'    => 'math',
            'prompt'  => $ctx[random_int(0, count($ctx) - 1)],
            'visual'  => '📝',
            'choices' => $this->numberChoices($res, max(0, $res - 8), $res + 8, 4),
            'answer'  => $res,
        ];
    }

    /** Problème de proportionnalité (collège). */
    private function probPropor(): array
    {
        $unit = random_int(2, 9);       // prix (ou quantité) pour 1
        $a = random_int(2, 6);          // quantité connue
        do {
            $b = random_int(2, 9);      // quantité cherchée (différente)
        } while ($b === $a);
        // a objets coûtent (unit*a) ; on cherche le prix de b objets = unit*b
        $totalA = $unit * $a;
        $res = $unit * $b;

        return [
            'type'    => 'math',
            'prompt'  => "$a croissants coûtent $totalA €. Combien coûtent $b croissants ?",
            'visual'  => '📝',
            'choices' => $this->numberChoices($res, max(0, $res - 12), $res + 12, 4),
            'answer'  => $res,
        ];
    }

    /** Multiplications à deux chiffres. */
    private function longMult(): array
    {
        $a = random_int(11, 25);
        $b = random_int(2, 9);
        $res = $a * $b;

        return [
            'type'    => 'math',
            'prompt'  => "$a × $b = ?",
            'visual'  => '',
            'choices' => $this->numberChoices($res, $res - 15, $res + 15, 4),
            'answer'  => $res,
        ];
    }

    /** Divisions exactes. */
    private function division(): array
    {
        $b = random_int(2, 9);
        $res = random_int(2, 10);
        $a = $b * $res;

        return [
            'type'    => 'math',
            'prompt'  => "$a ÷ $b = ?",
            'visual'  => '',
            'choices' => $this->numberChoices($res, 1, $res + 8, 4),
            'answer'  => $res,
        ];
    }

    // ---------------------------------------------------------------
    //  Collège — 6ème
    // ---------------------------------------------------------------

    /** Priorités opératoires : × et ÷ avant + et −. */
    private function priorities(): array
    {
        $a = random_int(1, 9);
        $b = random_int(2, 9);
        $c = random_int(2, 9);
        if (random_int(0, 1) === 0) {
            $res = $a + $b * $c;
            $prompt = "$a + $b × $c = ?";
        } else {
            $res = $b * $c + $a;
            $prompt = "$b × $c + $a = ?";
        }

        // On glisse l'erreur classique (calcul de gauche à droite) comme piège.
        $trap = ($a + $b) * $c;
        $vals = [$res];
        if ($trap !== $res) {
            $vals[] = $trap;
        }
        $guard = 0;
        while (count($vals) < 4 && $guard++ < 60) {
            $cand = $res + random_int(-9, 9);
            if ($cand >= 0 && !in_array($cand, $vals, true)) {
                $vals[] = $cand;
            }
        }
        shuffle($vals);

        return [
            'type'    => 'math',
            'prompt'  => $prompt,
            'visual'  => '',
            'choices' => array_map(fn ($v) => ['label' => (string) $v, 'value' => (string) $v], $vals),
            'answer'  => $res,
        ];
    }

    /** Les carrés (puissances). */
    private function squares(): array
    {
        $n = random_int(2, 12);
        $res = $n * $n;

        return [
            'type'    => 'math',
            'prompt'  => "{$n}² = ?  (c'est $n × $n)",
            'visual'  => '',
            'choices' => $this->numberChoices($res, max(0, $res - 15), $res + 15, 4),
            'answer'  => $res,
        ];
    }

    /** Périmètre d'un carré ou d'un rectangle. */
    private function perimeter(): array
    {
        if (random_int(0, 1) === 0) {
            $c = random_int(2, 12);
            $res = 4 * $c;
            $prompt = "Le périmètre d'un carré de côté $c = ?";
        } else {
            $l = random_int(3, 12);
            $w = random_int(2, $l);
            $res = 2 * ($l + $w);
            $prompt = "Le périmètre d'un rectangle de $l sur $w = ?";
        }

        return [
            'type'    => 'math',
            'prompt'  => $prompt,
            'visual'  => '📐',
            'choices' => $this->numberChoices($res, max(0, $res - 12), $res + 12, 4),
            'answer'  => $res,
        ];
    }

    /** Aire d'un carré ou d'un rectangle. */
    private function area(): array
    {
        if (random_int(0, 1) === 0) {
            $c = random_int(2, 10);
            $res = $c * $c;
            $prompt = "L'aire d'un carré de côté $c = ?";
        } else {
            $l = random_int(3, 10);
            $w = random_int(2, $l);
            $res = $l * $w;
            $prompt = "L'aire d'un rectangle de $l sur $w = ?";
        }

        return [
            'type'    => 'math',
            'prompt'  => $prompt,
            'visual'  => '🟦',
            'choices' => $this->numberChoices($res, max(0, $res - 15), $res + 15, 4),
            'answer'  => $res,
        ];
    }

    // ---------------------------------------------------------------
    //  Collège — 5ème / 4ème / 3ème
    // ---------------------------------------------------------------

    /** Pourcentages simples (5e). */
    private function percent(): array
    {
        $pcts = [10, 20, 25, 50, 100];
        $p    = $pcts[random_int(0, count($pcts) - 1)];
        $res  = random_int(1, 12);
        $n    = (int) ($res * 100 / $p);            // garantit un résultat entier

        return [
            'type'    => 'math',
            'prompt'  => "$p% de $n = ?",
            'visual'  => '',
            'choices' => $this->numberChoices($res, max(0, $res - 8), $res + 8, 4),
            'answer'  => $res,
        ];
    }

    /** Aire d'un triangle : (base × hauteur) ÷ 2 (5e). */
    private function triangleArea(): array
    {
        do {
            $b = random_int(2, 12);
            $h = random_int(2, 12);
        } while (($b * $h) % 2 !== 0);              // produit pair → aire entière
        $res = $b * $h / 2;

        return [
            'type'    => 'math',
            'prompt'  => "Aire d'un triangle : base $b, hauteur $h. Aire = ?",
            'visual'  => '🔺',
            'choices' => $this->numberChoices((int) $res, 1, (int) $res + 12, 4),
            'answer'  => (int) $res,
        ];
    }

    /** Priorités avec parenthèses (5e). */
    private function priorPar(): array
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $c = random_int(2, 6);
        $res = ($a + $b) * $c;

        return [
            'type'    => 'math',
            'prompt'  => "($a + $b) × $c = ?",
            'visual'  => '',
            'choices' => $this->numberChoices($res, max(0, $res - 12), $res + 12, 4),
            'answer'  => $res,
        ];
    }

    /** Addition de nombres relatifs (5e). */
    private function relatAdd(): array
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        switch (random_int(0, 2)) {
            case 0:  $res = -$a - $b; $prompt = "(−$a) + (−$b) = ?"; break;
            case 1:  $res = $b - $a;  $prompt = "(−$a) + $b = ?";    break;
            default: $res = $a - $b;  $prompt = "$a + (−$b) = ?";    break;
        }

        return [
            'type'    => 'math',
            'prompt'  => $prompt,
            'visual'  => '🌡️',
            'choices' => $this->numberChoices($res, $res - 4, $res + 4, 4),
            'answer'  => $res,
        ];
    }

    /** Puissances (4e). */
    private function power(): array
    {
        $sup  = ['0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴', '5' => '⁵'];
        $bases = [2, 3, 4, 5, 10];
        $base  = $bases[random_int(0, count($bases) - 1)];
        $exp   = $base === 10 ? random_int(2, 3) : random_int(2, ($base === 2 ? 5 : 3));
        $res   = (int) ($base ** $exp);

        $e = strtr((string) $exp, $sup);
        return [
            'type'    => 'math',
            'prompt'  => "$base$e = ?  (c'est $base multiplié $exp fois par lui-même)",
            'visual'  => '',
            'choices' => $this->numberChoices($res, max(0, $res - 20), $res + 20, 4),
            'answer'  => $res,
        ];
    }

    /** Multiplication de nombres relatifs (4e). */
    private function relatMul(): array
    {
        $a = random_int(2, 9);
        $b = random_int(2, 9);
        switch (random_int(0, 2)) {
            case 0:  $res = -($a * $b); $prompt = "(−$a) × $b = ?";    break;
            case 1:  $res = $a * $b;    $prompt = "(−$a) × (−$b) = ?"; break;
            default: $res = -($a * $b); $prompt = "$a × (−$b) = ?";    break;
        }

        return [
            'type'    => 'math',
            'prompt'  => $prompt,
            'visual'  => '',
            'choices' => $this->numberChoices($res, $res - 10, $res + 10, 4),
            'answer'  => $res,
        ];
    }

    /** Calcul littéral : développer a(x + b) (4e). */
    private function expand(): array
    {
        $a = random_int(2, 6);
        $b = random_int(2, 9);
        $res = $a * $b;
        $ax  = $a;

        return [
            'type'    => 'math',
            'prompt'  => "Développe : $a(x + $b) = {$ax}x + ?",
            'visual'  => '',
            'choices' => $this->numberChoices($res, max(0, $res - 10), $res + 10, 4),
            'answer'  => $res,
        ];
    }

    /** Racines carrées (3e). */
    private function sqrtEx(): array
    {
        $n  = random_int(2, 12);
        $sq = $n * $n;

        return [
            'type'    => 'math',
            'prompt'  => "√$sq = ?  (quel nombre au carré donne $sq ?)",
            'visual'  => '',
            'choices' => $this->numberChoices($n, 1, $n + 8, 4),
            'answer'  => $n,
        ];
    }

    /** Équations du premier degré (3e). */
    private function equationEx(): array
    {
        if (random_int(0, 1) === 0) {
            $x = random_int(1, 12);
            $a = random_int(1, 10);
            $b = $x + $a;
            $prompt = "x + $a = $b. Combien vaut x ?";
        } else {
            $a = random_int(2, 6);
            $x = random_int(2, 10);
            $b = $a * $x;
            $prompt = "$a × x = $b. Combien vaut x ?";
        }

        return [
            'type'    => 'math',
            'prompt'  => $prompt,
            'visual'  => '',
            'choices' => $this->numberChoices($x, 1, $x + 8, 4),
            'answer'  => $x,
        ];
    }

    /** Théorème de Thalès : longueur proportionnelle (3e). */
    private function thales(): array
    {
        $ab = random_int(2, 6);
        $k  = random_int(2, 4);
        $ac = $ab * $k;
        $ad = random_int(2, 6);
        $ae = $ad * $k;                             // AB/AC = AD/AE  →  AE = AD × (AC/AB)

        return [
            'type'    => 'math',
            'prompt'  => "Théorème de Thalès : AB/AC = AD/AE. Si AB=$ab, AC=$ac et AD=$ad, alors AE = ?",
            'visual'  => '📐',
            'choices' => $this->numberChoices($ae, max(1, $ae - 8), $ae + 8, 4),
            'answer'  => $ae,
        ];
    }

    /** Théorème de Pythagore : trouver l'hypoténuse (3e). */
    private function pythagore(): array
    {
        $triples = [[3, 4, 5], [6, 8, 10], [5, 12, 13], [8, 15, 17], [9, 12, 15], [7, 24, 25]];
        [$a, $b, $c] = $triples[random_int(0, count($triples) - 1)];

        return [
            'type'    => 'math',
            'prompt'  => "Triangle rectangle : les 2 côtés sont $a et $b. L'hypoténuse = ?",
            'visual'  => '📐',
            'choices' => $this->numberChoices($c, max(1, $c - 8), $c + 8, 4),
            'answer'  => $c,
        ];
    }

    /** Division euclidienne : trouver le reste (6e). */
    private function divEuclid(): array
    {
        $b = random_int(2, 9);
        $q = random_int(1, 9);
        $r = random_int(0, $b - 1);
        $a = $b * $q + $r;

        return [
            'type'    => 'math',
            'prompt'  => "Dans la division $a ÷ $b, quel est le RESTE ?",
            'visual'  => '',
            'choices' => $this->numberChoices($r, 0, max($b - 1, 4), 4),
            'answer'  => $r,
        ];
    }

    /** Les multiples (6e). */
    private function multiple(): array
    {
        $n = random_int(2, 9);
        $k = random_int(2, 9);
        $res = $n * $k;

        return [
            'type'    => 'math',
            'prompt'  => "Le {$k}ᵉ multiple de $n = ?  (c'est $n × $k)",
            'visual'  => '',
            'choices' => $this->numberChoices($res, max(0, $res - 12), $res + 12, 4),
            'answer'  => $res,
        ];
    }

    /** Arrondir à la dizaine la plus proche (6e). */
    private function roundTen(): array
    {
        $n = random_int(11, 98);
        $res = (int) (round($n / 10) * 10);

        $vals = [$res];
        foreach ([$res - 10, $res + 10, $res + 20] as $v) {
            if ($v >= 0 && !in_array($v, $vals, true)) {
                $vals[] = $v;
            }
        }
        $vals = array_slice($vals, 0, 4);
        shuffle($vals);

        return [
            'type'    => 'math',
            'prompt'  => "Arrondis $n à la dizaine la plus proche.",
            'visual'  => '',
            'choices' => array_map(fn ($v) => ['label' => (string) $v, 'value' => (string) $v], $vals),
            'answer'  => $res,
        ];
    }

    /** Comparer des nombres décimaux (6e). */
    private function compareDec(): array
    {
        $pool = [['0,5', 0.5], ['0,25', 0.25], ['0,75', 0.75], ['0,1', 0.1], ['0,9', 0.9],
                 ['0,45', 0.45], ['0,6', 0.6], ['0,08', 0.08], ['0,3', 0.3], ['0,7', 0.7]];
        shuffle($pool);
        $set = array_slice($pool, 0, 3);
        $most = random_int(0, 1) === 0;

        usort($set, fn ($x, $y) => $y[1] <=> $x[1]);
        $answer = $most ? $set[0][0] : $set[count($set) - 1][0];
        shuffle($set);

        return [
            'type'    => 'numbig',
            'prompt'  => $most ? 'Quel est le plus GRAND nombre décimal ?' : 'Quel est le plus PETIT nombre décimal ?',
            'visual'  => '',
            'choices' => array_map(fn ($x) => ['label' => $x[0], 'value' => $x[0]], $set),
            'answer'  => $answer,
        ];
    }

    /** Proportionnalité (5e). */
    private function proportion(): array
    {
        $p = random_int(2, 9);
        $n = random_int(2, 9);
        $res = $p * $n;

        return [
            'type'    => 'math',
            'prompt'  => "1 objet coûte $p €. Combien coûtent $n objets ?",
            'visual'  => '',
            'choices' => $this->numberChoices($res, max(0, $res - 12), $res + 12, 4),
            'answer'  => $res,
        ];
    }

    /** Soustraire des nombres relatifs (5e). */
    private function relatSub(): array
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        switch (random_int(0, 2)) {
            case 0:  $res = $a + $b;  $prompt = "$a − (−$b) = ?";    break;   // moins un négatif = plus
            case 1:  $res = -$a - $b; $prompt = "(−$a) − $b = ?";    break;
            default: $res = $b - $a;  $prompt = "(−$a) − (−$b) = ?"; break;
        }

        return [
            'type'    => 'math',
            'prompt'  => $prompt,
            'visual'  => '🌡️',
            'choices' => $this->numberChoices($res, $res - 4, $res + 4, 4),
            'answer'  => $res,
        ];
    }

    /** Volume d'un cube (5e). */
    private function volumeCube(): array
    {
        $c = random_int(2, 6);
        $res = $c ** 3;

        return [
            'type'    => 'math',
            'prompt'  => "Volume d'un cube d'arête $c = ?  (c'est $c × $c × $c)",
            'visual'  => '🧊',
            'choices' => $this->numberChoices($res, max(1, $res - 20), $res + 20, 4),
            'answer'  => $res,
        ];
    }

    /** Puissances de 10 (4e). */
    private function powerTen(): array
    {
        $sup = ['2' => '²', '3' => '³', '4' => '⁴', '5' => '⁵'];
        $e = random_int(2, 5);
        $res = 10 ** $e;

        $vals = [$res];
        foreach ([10 ** ($e - 1), 10 ** ($e + 1), $res * 5] as $v) {
            if (!in_array($v, $vals, true)) {
                $vals[] = $v;
            }
        }
        $vals = array_slice($vals, 0, 4);
        shuffle($vals);

        return [
            'type'    => 'math',
            'prompt'  => "10{$sup[(string) $e]} = ?  (1 suivi de $e zéros)",
            'visual'  => '',
            'choices' => array_map(fn ($v) => ['label' => (string) $v, 'value' => (string) $v], $vals),
            'answer'  => $res,
        ];
    }

    /** Factoriser : ax + ab = a(x + b) (4e). */
    private function factorise(): array
    {
        $a = random_int(2, 6);
        $b = random_int(2, 9);
        $ax = $a;
        $ab = $a * $b;

        return [
            'type'    => 'math',
            'prompt'  => "Factorise : {$ax}x + $ab = $a(x + ?)",
            'visual'  => '',
            'choices' => $this->numberChoices($b, 1, $b + 8, 4),
            'answer'  => $b,
        ];
    }

    /** Équation à deux étapes : ax + b = c (3e). */
    private function equation2(): array
    {
        $a = random_int(2, 5);
        $x = random_int(1, 9);
        $b = random_int(1, 9);
        $c = $a * $x + $b;

        return [
            'type'    => 'math',
            'prompt'  => "{$a}x + $b = $c. Combien vaut x ?",
            'visual'  => '',
            'choices' => $this->numberChoices($x, 1, $x + 8, 4),
            'answer'  => $x,
        ];
    }

    /** Image d'une fonction affine (3e). */
    private function functionEx(): array
    {
        $a = random_int(2, 5);
        $b = random_int(0, 9);
        $x = random_int(1, 9);
        $res = $a * $x + $b;

        return [
            'type'    => 'math',
            'prompt'  => "f(x) = {$a}x + $b. Combien vaut f($x) ?",
            'visual'  => '',
            'choices' => $this->numberChoices($res, max(0, $res - 12), $res + 12, 4),
            'answer'  => $res,
        ];
    }

    // ---------------------------------------------------------------
    //  Lycée — Seconde
    // ---------------------------------------------------------------

    /** Identités remarquables : (x + a)² (2nde). */
    private function identRemar(): array
    {
        $a = random_int(1, 9);
        $res = 2 * $a;                              // le double produit
        $carre = $a * $a;

        return [
            'type'    => 'math',
            'prompt'  => "(x + $a)² = x² + ?x + $carre.  Quel est le coefficient du milieu ?",
            'visual'  => '',
            'choices' => $this->numberChoices($res, max(0, $res - 8), $res + 8, 4),
            'answer'  => $res,
        ];
    }

    /** Milieu d'un segment sur une droite (2nde). */
    private function milieu(): array
    {
        do {
            $xa = random_int(0, 18);
            $xb = random_int(0, 18);
        } while (($xa + $xb) % 2 !== 0 || $xa === $xb);
        $res = (int) (($xa + $xb) / 2);

        return [
            'type'    => 'math',
            'prompt'  => "Le milieu du segment [AB], avec A($xa) et B($xb) sur une droite, est en ?",
            'visual'  => '',
            'choices' => $this->numberChoices($res, max(0, $res - 6), $res + 6, 4),
            'answer'  => $res,
        ];
    }

    /** Antécédent d'une fonction affine (2nde). */
    private function antecedent(): array
    {
        $a = random_int(2, 5);
        $x = random_int(1, 9);
        $b = random_int(0, 9);
        $img = $a * $x + $b;

        return [
            'type'    => 'math',
            'prompt'  => "f(x) = {$a}x + $b. Quel nombre x donne f(x) = $img ?",
            'visual'  => '',
            'choices' => $this->numberChoices($x, 1, $x + 8, 4),
            'answer'  => $x,
        ];
    }

    /** Pourcentage d'évolution (2nde). */
    private function evolution(): array
    {
        $pcts = [10, 20, 25, 50, 100];
        $q = $pcts[random_int(0, count($pcts) - 1)];
        $p = (random_int(1, 8)) * 20;               // prix multiple de 20 → résultat entier
        $up = random_int(0, 1) === 0;
        $res = $up ? (int) ($p + $p * $q / 100) : (int) ($p - $p * $q / 100);

        return [
            'type'    => 'math',
            'prompt'  => $up
                ? "Un article coûte $p €. Son prix AUGMENTE de $q %. Nouveau prix ?"
                : "Un article coûte $p €. Son prix BAISSE de $q %. Nouveau prix ?",
            'visual'  => '',
            'choices' => $this->numberChoices($res, max(0, $res - 20), $res + 20, 4),
            'answer'  => $res,
        ];
    }

    /** Moyenne de trois nombres (2nde). */
    private function moyenne(): array
    {
        do {
            $a = random_int(2, 18);
            $b = random_int(2, 18);
            $c = random_int(2, 18);
        } while (($a + $b + $c) % 3 !== 0);
        $res = (int) (($a + $b + $c) / 3);

        return [
            'type'    => 'math',
            'prompt'  => "Quelle est la moyenne de $a, $b et $c ?",
            'visual'  => '📊',
            'choices' => $this->numberChoices($res, max(0, $res - 8), $res + 8, 4),
            'answer'  => $res,
        ];
    }

    /** Distance entre deux points sur une droite (2nde). */
    private function distanceEx(): array
    {
        $xa = random_int(0, 18);
        do {
            $xb = random_int(0, 18);
        } while ($xb === $xa);
        $res = abs($xb - $xa);

        return [
            'type'    => 'math',
            'prompt'  => "Sur une droite, A est en $xa et B en $xb. Quelle est la distance AB ?",
            'visual'  => '',
            'choices' => $this->numberChoices($res, max(0, $res - 6), $res + 6, 4),
            'answer'  => $res,
        ];
    }

    /** Nombres relatifs (introduction, avec la température). */
    private function relative(): array
    {
        $a = random_int(1, 9);       // on part de −a degrés
        $b = random_int(1, 12);      // on monte de b degrés
        $res = $b - $a;              // peut être négatif

        return [
            'type'    => 'math',
            'prompt'  => "Il fait −$a degrés. La température monte de $b degrés. Combien fait-il ?",
            'visual'  => '🌡️',
            'choices' => $this->numberChoices($res, $res - 3, $res + 3, 4),
            'answer'  => $res,
        ];
    }

    /** La moitié d'un nombre. */
    private function half(int $level = 1): array
    {
        $res = random_int(2, $level >= 7 ? 20 : 10);
        $n   = $res * 2;                                 // nombre pair → moitié entière

        return [
            'type'    => 'math',
            'prompt'  => "La moitié de $n = ?",
            'visual'  => '',
            'choices' => $this->numberChoices($res, 1, $res + 6, 4),
            'answer'  => $res,
        ];
    }

    /** Le triple d'un nombre. */
    private function triple(int $level = 1): array
    {
        $n   = random_int(2, $level >= 7 ? 12 : 6);
        $res = $n * 3;

        return [
            'type'    => 'math',
            'prompt'  => "Le triple de $n = ?",
            'visual'  => '',
            'choices' => $this->numberChoices($res, 2, $res + 8, 4),
            'answer'  => $res,
        ];
    }

    /** Pair ou impair. */
    private function evenOdd(int $level = 1): array
    {
        $n = random_int(1, $level >= 7 ? 50 : 20);
        $answer = $n % 2 === 0 ? 'pair' : 'impair';

        $choices = [['label' => 'pair', 'value' => 'pair'], ['label' => 'impair', 'value' => 'impair']];
        shuffle($choices);

        return [
            'type'    => 'evenodd',
            'prompt'  => "Le nombre $n est…",
            'visual'  => (string) $n,
            'choices' => $choices,
            'answer'  => $answer,
        ];
    }

    /** Compléter à 10 ou à 100. */
    private function complement(int $level = 1): array
    {
        $target = ($level >= 7 && random_int(0, 1) === 0) ? 100 : 10;
        $a = random_int(1, $target - 1);
        $answer = $target - $a;

        return [
            'type'    => 'math',
            'prompt'  => "Combien faut-il ajouter à $a pour aller à $target ?",
            'visual'  => '',
            'choices' => $this->numberChoices($answer, 0, $target, 4),
            'answer'  => $answer,
        ];
    }

    /** Reconnaître une partie du corps. */
    private function body(int $level = 1): array
    {
        // [emoji, nom, genre]
        $pool = [
            ['✋', 'main', 'f'], ['🦶', 'pied', 'm'], ['👃', 'nez', 'm'], ['👄', 'bouche', 'f'],
            ['👁️', 'œil', 'm'], ['👂', 'oreille', 'f'], ['🦵', 'jambe', 'f'], ['🦷', 'dent', 'f'],
            ['👅', 'langue', 'f'], ['🖐️', 'doigt', 'm'],
        ];
        return $this->pickOne($pool, 'body', $level >= 2 ? 4 : 3);
    }

    /** Cri d'animal → trouver l'animal (« Qui fait Meuh ? »). */
    private function animalSound(int $level = 1): array
    {
        $pairs = [
            ['Meuh', '🐮'], ['Miaou', '🐱'], ['Ouaf ouaf', '🐶'], ['Coin coin', '🦆'],
            ['Cocorico', '🐓'], ['Bêê', '🐑'], ['Groin', '🐷'], ['Hi-han', '🫏'],
        ];
        if ($level >= 2) {
            $pairs = array_merge($pairs, [['Croâ', '🐸'], ['Hou hou', '🦉'], ['Toc toc', '🐦']]);
        }
        $pair = $pairs[random_int(0, count($pairs) - 1)];
        [$sound, $answer] = $pair;

        $bag = [];
        foreach ($pairs as $p) {
            if ($p[1] !== $answer) {
                $bag[] = $p[1];
            }
        }
        shuffle($bag);
        $distractors = array_slice(array_values(array_unique($bag)), 0, $level >= 2 ? 3 : 2);

        $choices = array_merge([$answer], $distractors);
        shuffle($choices);

        return [
            'type'    => 'animalsound',
            'prompt'  => "Qui fait « $sound » ?",
            'visual'  => '',
            'choices' => array_map(fn ($e) => ['label' => $e, 'value' => $e], $choices),
            'answer'  => $answer,
        ];
    }

    /** Quel nombre vient avant / après (nombres plus grands avec l'âge). */
    private function nextNum(int $level = 1): array
    {
        $max = $level >= 5 ? 50 : ($level >= 4 ? 30 : ($level >= 3 ? 20 : 9));
        if (random_int(0, 1) === 0) {
            $base = random_int(1, $max - 1);
            $answer = $base + 1;
            $prompt = "Quel nombre vient APRÈS $base ?";
        } else {
            $base = random_int(2, $max);
            $answer = $base - 1;
            $prompt = "Quel nombre vient AVANT $base ?";
        }

        return [
            'type'    => 'nextnum',
            'prompt'  => $prompt,
            'visual'  => '',
            'choices' => $this->numberChoices($answer, 0, $max, $level >= 3 ? 4 : 3),
            'answer'  => $answer,
        ];
    }

    /** Comparer des nombres écrits : le plus grand / le plus petit (plus grands avec l'âge). */
    private function numBig(int $level = 1): array
    {
        $max = $level >= 5 ? 50 : ($level >= 4 ? 30 : ($level >= 3 ? 20 : 9));
        $howMany = $level >= 3 ? 4 : 3;
        $nums = range(1, $max);
        shuffle($nums);
        $nums = array_slice($nums, 0, $howMany);

        $most   = random_int(0, 1) === 0;
        $answer = $most ? max($nums) : min($nums);

        shuffle($nums);
        return [
            'type'    => 'numbig',
            'prompt'  => $most ? 'Quel est le plus GRAND nombre ?' : 'Quel est le plus PETIT nombre ?',
            'visual'  => '',
            'choices' => array_map(fn ($n) => ['label' => (string) $n, 'value' => (string) $n], $nums),
            'answer'  => $answer,
        ];
    }

    /** Lettre à L'OREILLE : la lettre est dite par la voix, pas affichée. */
    private function lettersSound(int $level = 1): array
    {
        $pool = $level >= 3 ? range('A', 'Z') : ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        shuffle($pool);
        $pick    = $pool[0];
        $choices = array_slice($pool, 0, 4);
        shuffle($choices);

        return [
            'type'    => 'letter_sound',
            'prompt'  => 'Écoute bien : quelle lettre ?',
            'say'     => $pick,                        // dit par la voix, JAMAIS affiché
            'visual'  => '👂',
            'choices' => array_map(fn ($l) => ['label' => $l, 'value' => $l], $choices),
            'answer'  => $pick,
        ];
    }

    /** LOGIQUE — catégoriser : trouver un objet d'une famille demandée. */
    private function categorie(): array
    {
        // [article, nom, membres]
        $fams = [
            ['un', 'fruit',    ['🍎', '🍌', '🍓', '🍊', '🍇']],
            ['un', 'animal',   ['🐶', '🐱', '🐰', '🐮', '🐷']],
            ['un', 'véhicule', ['🚗', '🚕', '🚌', '🚓', '🚑']],
            ['une', 'fleur',   ['🌸', '🌻', '🌷', '🌹']],
        ];
        shuffle($fams);
        $target = $fams[0];
        $answer = $target[2][array_rand($target[2])];

        // Distracteurs : des membres d'AUTRES familles
        $bag = [];
        for ($i = 1; $i < count($fams); $i++) {
            foreach ($fams[$i][2] as $e) {
                $bag[] = $e;
            }
        }
        shuffle($bag);
        $distractors = array_slice($bag, 0, 2);

        $choices = array_merge([$answer], $distractors);
        shuffle($choices);

        return [
            'type'    => 'categorie',
            'prompt'  => "Trouve {$target[0]} {$target[1]} !",
            'visual'  => '',
            'choices' => array_map(fn ($e) => ['label' => $e, 'value' => $e], $choices),
            'answer'  => $answer,
        ];
    }

    /** LOGIQUE — premier / dernier : repérer la position dans une rangée. */
    private function rang(): array
    {
        $pool = ['🐶', '🐱', '🐰', '🐸', '🐦', '🐠', '🦋', '🐝'];
        shuffle($pool);
        $row = array_slice($pool, 0, 4);

        $first  = random_int(0, 1) === 0;
        $answer = $first ? $row[0] : $row[count($row) - 1];

        $choices = $row;
        shuffle($choices);

        return [
            'type'    => 'rang',
            'prompt'  => $first ? 'Qui est le PREMIER ?' : 'Qui est le DERNIER ?',
            'visual'  => implode(' ', $row),          // rangée dans l'ordre (à lire de gauche à droite)
            'choices' => array_map(fn ($e) => ['label' => $e, 'value' => $e], $choices),
            'answer'  => $answer,
        ];
    }

    /** Reconnaître un chiffre écrit (nombres plus grands avec l'âge). */
    private function digits(int $level = 1): array
    {
        $max  = $level >= 5 ? 50 : ($level >= 4 ? 20 : ($level >= 3 ? 15 : 9));
        $pool = range(1, $max);
        shuffle($pool);
        $pick    = $pool[0];
        $choices = array_slice($pool, 0, 4);
        shuffle($choices);

        return [
            'type'    => 'digit',
            'prompt'  => $max > 9 ? "Trouve le nombre $pick !" : "Trouve le chiffre $pick !",
            'visual'  => '',
            'choices' => array_map(fn ($n) => ['label' => (string) $n, 'value' => (string) $n], $choices),
            'answer'  => $pick,
        ];
    }

    /** Trouver le contraire (jour/nuit, chaud/froid…). */
    private function opposites(): array
    {
        $pairs = [
            ['☀️', '🌙'], ['🔥', '❄️'], ['😊', '😢'], ['🐘', '🐭'],
            ['⬆️', '⬇️'], ['🚀', '🐌'], ['👍', '👎'], ['🌞', '🌧️'],
        ];
        $pair = $pairs[random_int(0, count($pairs) - 1)];
        if (random_int(0, 1) === 1) {
            $pair = [$pair[1], $pair[0]];             // on montre parfois l'autre côté
        }
        [$shown, $answer] = $pair;

        // Distracteurs : des emojis d'autres paires
        $bag = [];
        foreach ($pairs as $p) {
            foreach ($p as $e) {
                if ($e !== $shown && $e !== $answer) {
                    $bag[] = $e;
                }
            }
        }
        shuffle($bag);
        $distractors = array_slice(array_values(array_unique($bag)), 0, 2);

        $choices = array_merge([$answer], $distractors);
        shuffle($choices);

        return [
            'type'    => 'opposite',
            'prompt'  => 'Trouve le contraire !',
            'visual'  => $shown,
            'choices' => array_map(fn ($e) => ['label' => $e, 'value' => $e], $choices),
            'answer'  => $answer,
        ];
    }

    /** Compter jusqu'à 3 (très petits). */
    private function countSmall(): array
    {
        $items = ['🍎', '🐶', '⭐', '🎈', '🌸', '🚗'];
        $item  = $items[random_int(0, count($items) - 1)];
        $n     = random_int(1, 3);

        return [
            'type'    => 'count3',
            'prompt'  => 'Combien y en a-t-il ?',
            'visual'  => str_repeat($item, $n),
            'choices' => $this->numberChoices($n, 1, 3),
            'answer'  => $n,
        ];
    }

    /** Reconnaître une émotion sur un visage. */
    private function emotions(int $level = 1): array
    {
        // [emoji, nom] — tous s'accordent avec « le visage » (masculin)
        $pool = [
            ['😊', 'content'], ['😢', 'triste'], ['😠', 'fâché'],
            ['😮', 'surpris'], ['😴', 'fatigué'], ['😨', 'effrayé'],
        ];
        if ($level >= 2) {   // moyenne section : émotions plus fines
            $pool = array_merge($pool, [
                ['😍', 'amoureux'], ['🤢', 'dégoûté'], ['😎', 'fier'], ['😳', 'gêné'],
            ]);
        }
        $n = $level >= 2 ? 4 : 3;
        shuffle($pool);
        $pick    = $pool[0];
        $choices = array_slice($pool, 0, $n);
        shuffle($choices);

        return [
            'type'    => 'emotion',
            'prompt'  => "Trouve le visage {$pick[1]} !",
            'visual'  => '',
            'choices' => array_map(fn ($e) => ['label' => $e[0], 'value' => $e[1]], $choices),
            'answer'  => $pick[1],
        ];
    }

    /** Reconnaître un aliment. */
    private function foods(int $level = 1): array
    {
        // [emoji, nom, genre]
        $pool = [
            ['🍎', 'pomme', 'f'], ['🍌', 'banane', 'f'], ['🍓', 'fraise', 'f'],
            ['🥕', 'carotte', 'f'], ['🍞', 'pain', 'm'], ['🧀', 'fromage', 'm'],
            ['🍰', 'gâteau', 'm'], ['🍫', 'chocolat', 'm'],
        ];
        if ($level >= 2) {   // moyenne section : aliments en plus
            $pool = array_merge($pool, [
                ['🍇', 'raisin', 'm'], ['🍊', 'orange', 'f'], ['🥦', 'brocoli', 'm'],
                ['🍕', 'pizza', 'f'], ['🥚', 'œuf', 'm'], ['🍅', 'tomate', 'f'],
            ]);
        }
        return $this->pickOne($pool, 'food', $level >= 2 ? 4 : 3);
    }

    /** Trouver le même dessin (association simple). */
    private function matching(int $level = 1): array
    {
        $pool = ['🐶', '🐱', '🍎', '⭐', '🚗', '🌸', '🐟', '🎈', '🌈', '🍦'];
        $nd   = $level >= 2 ? 3 : 2;   // nombre de distracteurs
        shuffle($pool);
        $target      = $pool[0];
        $distractors = array_slice($pool, 1, $nd);

        $choices = array_merge([$target], $distractors);
        shuffle($choices);

        return [
            'type'    => 'pareil',
            'prompt'  => 'Trouve le même !',
            'visual'  => $target,
            'choices' => array_map(fn ($e) => ['label' => $e, 'value' => $e], $choices),
            'answer'  => $target,
        ];
    }

    /**
     * LOGIQUE — suite logique : un motif AB se répète, quel est le suivant ?
     * ex : 🔴🔵🔴🔵🔴 ❓  → 🔵
     */
    private function pattern(int $level = 1): array
    {
        // Motif : 2 éléments (AB) en PS, 3 éléments (ABC) en moyenne section.
        $symbols = ['🔴', '🔵', '🟡', '🟢', '⭐', '❤️', '🔺', '🐶', '🐱', '🍎'];
        shuffle($symbols);
        $motif = array_slice($symbols, 0, $level >= 2 ? 3 : 2);

        $reps = random_int(2, 3);
        $full = [];
        for ($r = 0; $r < $reps; $r++) {
            foreach ($motif as $m) {
                $full[] = $m;
            }
        }
        // On coupe la suite à un endroit et la bonne réponse est l'élément suivant.
        $cut  = count($full) - random_int(1, count($motif));
        $shown = array_slice($full, 0, $cut);
        $next  = $motif[$cut % count($motif)];

        // Choix : les éléments du motif + un intrus
        $distractor = array_slice($symbols, -1)[0];
        $choices = array_values(array_unique(array_merge($motif, [$distractor])));
        shuffle($choices);

        return [
            'type'    => 'suite',
            'prompt'  => 'Qu\'est-ce qui vient après ?',
            'visual'  => implode('', $shown) . ' ❓',
            'choices' => array_map(fn ($e) => ['label' => $e, 'value' => $e], $choices),
            'answer'  => $next,
        ];
    }

    /**
     * LOGIQUE — comparer des quantités : où y en a-t-il le plus / le moins ?
     * ex : 🍎🍎  vs  🍎🍎🍎🍎  → le plus = le 2e groupe
     */
    private function compare(int $level = 1): array
    {
        $emojis = ['🍎', '🐶', '⭐', '🎈', '🍬', '🐟', '🌸'];
        $emoji  = $emojis[random_int(0, count($emojis) - 1)];
        $most   = random_int(0, 1) === 0;         // le plus (sinon le moins)

        // Quantités DIFFÉRENTES → une seule bonne réponse.
        // Plus l'âge monte, plus les quantités sont grandes.
        $max     = [1 => 6, 2 => 9, 3 => 12, 4 => 15, 5 => 18][$level] ?? 9;
        $groupsN = $level >= 2 ? 4 : 3;
        $counts = range(1, $max);
        shuffle($counts);
        $counts = array_slice($counts, 0, $groupsN);

        $groups  = array_map(fn ($n) => str_repeat($emoji, $n), $counts);
        $target  = $most ? max($counts) : min($counts);
        $answer  = str_repeat($emoji, $target);

        shuffle($groups);

        return [
            'type'    => 'compare',
            'prompt'  => $most ? 'Où y en a-t-il le plus ?' : 'Où y en a-t-il le moins ?',
            'visual'  => '',
            'choices' => array_map(fn ($g) => ['label' => $g, 'value' => $g], $groups),
            'answer'  => $answer,
        ];
    }

    /**
     * LOGIQUE — suite de nombres : quel nombre manque ?
     * ex : 1  2  ❓  4  → 3
     */
    private function numberGap(int $level = 1): array
    {
        // Nombres de départ plus grands avec l'âge.
        $startMax = [1 => 2, 2 => 6, 3 => 12, 4 => 20, 5 => 30][$level] ?? 6;
        $start  = random_int(1, $startMax);
        $seq    = [$start, $start + 1, $start + 2, $start + 3];   // 4 nombres qui se suivent
        $hole   = random_int(1, 3);                               // on cache un du milieu/fin
        $answer = $seq[$hole];

        $shown = array_map(fn ($v, $i) => $i === $hole ? '❓' : (string) $v, $seq, array_keys($seq));
        $max   = $start + 5;
        $n     = $level >= 2 ? 4 : 3;

        return [
            'type'    => 'suitenum',
            'prompt'  => 'Qu\'est-ce qui manque ?',
            'visual'  => implode('  ', $shown),
            'choices' => $this->numberChoices($answer, 1, $max, $n),
            'answer'  => $answer,
        ];
    }

    /**
     * LOGIQUE — association : quel objet va avec l'image montrée ?
     * ex : 🐶 → 🦴, ☔ → 🌧️
     */
    private function association(int $level = 1): array
    {
        $pairs = [
            ['🐶', '🦴'], ['🐝', '🌸'], ['☔', '🌧️'], ['🔑', '🔒'],
            ['👶', '🍼'], ['✏️', '📄'], ['🐱', '🐟'], ['🐦', '🥚'],
        ];
        $pair = $pairs[random_int(0, count($pairs) - 1)];
        [$shown, $answer] = $pair;

        // Distracteurs : des éléments d'AUTRES paires (3 en moyenne section)
        $bag = [];
        foreach ($pairs as $p) {
            if ($p[0] !== $shown) {
                $bag[] = $p[1];
            }
        }
        shuffle($bag);
        $distractors = array_slice(array_values(array_unique($bag)), 0, $level >= 2 ? 3 : 2);

        $choices = array_merge([$answer], $distractors);
        shuffle($choices);

        return [
            'type'    => 'assoc',
            'prompt'  => 'Qu\'est-ce qui va avec ?',
            'visual'  => $shown,
            'choices' => array_map(fn ($e) => ['label' => $e, 'value' => $e], $choices),
            'answer'  => $answer,
        ];
    }

    /**
     * Couleurs (sans lecture) : on montre un rond coloré, l'enfant retrouve
     * la MÊME couleur parmi des carrés.
     */
    private function colors(int $level = 1): array
    {
        // [nom, rond, carré] de la même couleur
        $pool = [
            ['rouge',  '🔴', '🟥'], ['bleu',   '🔵', '🟦'],
            ['vert',   '🟢', '🟩'], ['jaune',  '🟡', '🟨'],
            ['violet', '🟣', '🟪'], ['orange', '🟠', '🟧'],
        ];
        if ($level >= 2) {   // moyenne section : plus de couleurs
            $pool = array_merge($pool, [
                ['marron', '🟤', '🟫'], ['noir', '⚫', '⬛'], ['blanc', '⚪', '⬜'],
            ]);
        }
        $n = $level >= 2 ? 4 : 3;
        shuffle($pool);
        $pick    = $pool[0];
        $choices = array_slice($pool, 0, $n);
        shuffle($choices);

        return [
            'type'    => 'color',
            'prompt'  => 'Trouve la même couleur !',
            'visual'  => $pick[1],                                   // le rond coloré à retrouver
            'choices' => array_map(fn ($c) => ['label' => $c[2], 'value' => $c[0]], $choices),
            'answer'  => $pick[0],
        ];
    }

    /** Reconnaître une forme. */
    private function shapes(int $level = 1): array
    {
        // [emoji, nom, genre]
        $pool = [
            ['⚪', 'rond', 'm'], ['🔺', 'triangle', 'm'], ['🟩', 'carré', 'm'],
            ['⭐', 'étoile', 'f'], ['❤️', 'cœur', 'm'],
        ];
        if ($level >= 2) {   // moyenne section : formes en plus
            $pool = array_merge($pool, [
                ['🔷', 'losange', 'm'], ['🟦', 'rectangle', 'm'], ['🌙', 'croissant', 'm'],
            ]);
        }
        return $this->pickOne($pool, 'shape', $level >= 2 ? 4 : 3);
    }

    /** Reconnaître un animal. */
    private function animals(int $level = 1): array
    {
        // [emoji, nom, genre]
        $pool = [
            ['🐱', 'chat', 'm'], ['🐶', 'chien', 'm'], ['🐮', 'vache', 'f'], ['🐷', 'cochon', 'm'],
            ['🐔', 'poule', 'f'], ['🐴', 'cheval', 'm'], ['🐰', 'lapin', 'm'], ['🐟', 'poisson', 'm'],
        ];
        if ($level >= 2) {   // moyenne section : animaux moins courants
            $pool = array_merge($pool, [
                ['🐘', 'éléphant', 'm'], ['🦁', 'lion', 'm'], ['🐻', 'ours', 'm'],
                ['🐸', 'grenouille', 'f'], ['🦓', 'zèbre', 'm'], ['🐧', 'pingouin', 'm'],
            ]);
        }
        return $this->pickOne($pool, 'animal', $level >= 2 ? 4 : 3);
    }

    /** Comparer des tailles : le plus grand / le plus petit. */
    private function sizes(int $level = 1): array
    {
        $pool = [
            ['🐜', 1], ['🐭', 2], ['🐰', 3], ['🐶', 4],
            ['🐴', 5], ['🐘', 6], ['🐋', 7],
        ];
        $n = $level >= 2 ? 4 : 3;   // plus d'éléments à comparer
        shuffle($pool);
        $set = array_slice($pool, 0, $n);

        $biggest = random_int(0, 1) === 0;
        usort($set, fn ($a, $b) => $b[1] <=> $a[1]);         // du plus grand au plus petit
        $answer = $biggest ? $set[0][0] : $set[count($set) - 1][0];

        shuffle($set);
        return [
            'type'    => 'size',
            'prompt'  => $biggest ? 'Lequel est le plus GRAND ?' : 'Lequel est le plus PETIT ?',
            'visual'  => '',
            'choices' => array_map(fn ($x) => ['label' => $x[0], 'value' => $x[0]], $set),
            'answer'  => $answer,
        ];
    }

    /** Trouver l'intrus : N objets d'une famille + 1 différent. */
    private function intruder(int $level = 1): array
    {
        $families = [
            ['🍎', '🍌', '🍓', '🍊', '🍇'],   // fruits
            ['🐶', '🐱', '🐰', '🐮', '🐷'],   // animaux
            ['🚗', '🚕', '🚌', '🚓', '🚑'],   // véhicules
            ['🌸', '🌻', '🌷', '🌹', '🌼'],   // fleurs
        ];
        shuffle($families);
        $famA = $families[0];
        $famB = $families[1];

        shuffle($famA);
        $sameCount = $level >= 2 ? 4 : 3;       // moyenne section : 4 pareils + 1 intrus
        $sameOnes = array_slice($famA, 0, $sameCount);
        $intruder = $famB[array_rand($famB)];   // 1 intrus

        $choices = array_merge($sameOnes, [$intruder]);
        shuffle($choices);

        return [
            'type'    => 'intrus',
            'prompt'  => "Trouve l'intrus !",
            'visual'  => '',
            'choices' => array_map(fn ($e) => ['label' => $e, 'value' => $e], $choices),
            'answer'  => $intruder,
        ];
    }

    /**
     * Fabrique une question « Où est … ? » à partir d'une liste [emoji, nom, genre].
     * Le genre ('m'/'f') sert à mettre le bon article (le / la / l').
     */
    private function pickOne(array $pool, string $type, int $n = 3): array
    {
        shuffle($pool);
        $pick    = $pool[0];
        $choices = array_slice($pool, 0, $n);
        shuffle($choices);

        return [
            'type'    => $type,
            'prompt'  => 'Où est ' . $this->withArticle($pick[1], $pick[2]) . ' ?',
            'visual'  => '',
            'choices' => array_map(fn ($x) => ['label' => $x[0], 'value' => $x[1]], $choices),
            'answer'  => $pick[1],
        ];
    }

    /** Ajoute l'article correct : le chat, la vache, l'étoile (élision devant voyelle). */
    private function withArticle(string $name, string $gender): string
    {
        $first  = mb_strtolower(mb_substr($name, 0, 1));
        $vowels = ['a','e','i','o','u','y','à','â','ä','é','è','ê','ë','î','ï','ô','ö','û','ü','œ','h'];

        if (in_array($first, $vowels, true)) {
            return "l'" . $name;                 // l'étoile, l'ours...
        }
        return ($gender === 'f' ? 'la ' : 'le ') . $name;
    }

    /** Niveau 2 : compter des objets. */
    private function counting(int $level = 1): array
    {
        $fruits = ['🍎', '🍌', '🍓', '🍊', '🍇'];
        $fruit  = $fruits[random_int(0, count($fruits) - 1)];
        $max    = [1 => 5, 2 => 6, 3 => 10, 4 => 12, 5 => 15][$level] ?? 6;   // plus grand avec l'âge
        $n      = random_int(1, $max);

        return [
            'type'    => 'count',
            'prompt'  => 'Combien y en a-t-il ?',
            'visual'  => str_repeat($fruit, $n),
            'choices' => $this->numberChoices($n, 1, $max + 1),
            'answer'  => $n,
        ];
    }

    /** Addition seule, PROGRESSIVE (nombres qui grandissent avec les réussites). */
    private function addition(int $done = 0): array
    {
        $max = 3 + min(intdiv($done, 4), 7);      // 3 → 10 selon la progression
        $a = random_int(1, $max);
        $b = random_int(1, $max);
        return $this->mathPayload($a, $b, '+', $a + $b, ($max) * 2);
    }

    /** Soustraction seule, PROGRESSIVE. */
    private function subtraction(int $done = 0): array
    {
        $max = 3 + min(intdiv($done, 4), 7);
        $a = random_int(2, $max + 2);
        $b = random_int(1, $a);                   // jamais de résultat négatif
        return $this->mathPayload($a, $b, '−', $a - $b, $max + 2);
    }

    /** Addition OU soustraction mélangées (grande section). */
    private function addSub(int $done = 0): array
    {
        return random_int(0, 1) === 0 ? $this->addition($done) : $this->subtraction($done);
    }

    /** Prépare une question de calcul (avec a, b, op pour l'animation côté front). */
    private function mathPayload(int $a, int $b, string $op, int $res, int $choiceMax): array
    {
        return [
            'type'    => 'math',
            'prompt'  => "$a $op $b = ?",
            'visual'  => '',
            'a'       => $a,
            'b'       => $b,
            'op'      => $op,                     // '+' ou '−' → utilisé pour l'animation
            'choices' => $this->numberChoices($res, 0, max($choiceMax, 4)),
            'answer'  => $res,
        ];
    }

    /**
     * Multiplication / division PROGRESSIVE.
     * On démarre avec les petites tables (×2, ×3) puis on monte peu à peu.
     */
    private function mulDiv(int $done = 0): array
    {
        $tier = min(intdiv($done, 4), 7);   // palier tous les 4 succès
        $max  = min(2 + $tier, 9);          // tables de 2 jusqu'à 9

        if (random_int(0, 1) === 0) {
            $a = random_int(2, $max);
            $b = random_int(2, $max);
            $res = $a * $b;
            $prompt = "$a × $b = ?";
        } else {
            $b = random_int(2, $max);
            $res = random_int(2, $max);
            $a = $b * $res;                 // division toujours entière
            $prompt = "$a ÷ $b = ?";
        }

        return [
            'type'    => 'math',
            'prompt'  => $prompt,
            'visual'  => '',
            'choices' => $this->numberChoices($res, 1, $max * $max),
            'answer'  => $res,
        ];
    }

    /** Fabrique N choix numériques dont la bonne réponse, mélangés. */
    private function numberChoices(int $correct, int $min, int $max, int $n = 3): array
    {
        $values = [$correct];
        $guard = 0;
        while (count($values) < $n && $guard++ < 80) {
            $cand = random_int($min, $max);
            if (!in_array($cand, $values, true)) {
                $values[] = $cand;
            }
        }
        shuffle($values);
        return array_map(fn ($v) => ['label' => (string) $v, 'value' => (string) $v], $values);
    }

    // ---------------------------------------------------------------
    //  Signature (anti-triche)
    // ---------------------------------------------------------------

    private function sign(string $value): string
    {
        $payload = base64_encode($value);
        $sig     = hash_hmac('sha256', $payload, $this->secret);
        return $payload . '.' . $sig;
    }

    private function unsign(string $token): ?string
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$payload, $sig] = $parts;
        $expectedSig = hash_hmac('sha256', $payload, $this->secret);
        if (!hash_equals($expectedSig, $sig)) {
            return null;                 // token falsifié
        }
        $decoded = base64_decode($payload, true);
        return $decoded === false ? null : $decoded;   // NB : "0" est valide (ne pas utiliser ?:)
    }

    private function normalize(string $s): string
    {
        return trim(mb_strtolower($s));
    }
}
