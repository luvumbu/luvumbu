<?php
namespace App\Services;

/**
 * Calcule l'arbre de progression « à la Duolingo ».
 * Un thème est MAÎTRISÉ après quelques bonnes réponses.
 * Un groupe d'âge se DÉBLOQUE quand le groupe précédent est entièrement maîtrisé.
 */
class ProgressService
{
    /** Bonnes réponses nécessaires pour maîtriser un thème. */
    public const THRESHOLD = 3;

    /** Ordre des groupes d'âge et leurs thèmes. */
    private const GROUPS = [
        ['id' => 'age3', 'topics' => ['problem', 'colors', 'shapes', 'sizes', 'animals', 'intrus', 'count3', 'emotions', 'foods', 'pareil', 'suite', 'assoc', 'compare', 'suitenum', 'body', 'animalsound']],
        ['id' => 'age4', 'topics' => ['problem4', 'colors4', 'shapes4', 'sizes4', 'animals4', 'intrus4', 'count', 'emotions4', 'foods4', 'pareil4', 'suite4', 'assoc4', 'compare4', 'suitenum4', 'body4', 'animalsound4', 'letters', 'letters_sound', 'digits', 'opposites', 'categorie', 'rang', 'nextnum', 'numbig', 'addition', 'subtraction']],
        ['id' => 'age5', 'topics' => ['problem5', 'colors5', 'shapes5', 'sizes5', 'animals5', 'animalsound5', 'foods5', 'body5', 'emotions5', 'opposites5', 'pareil5', 'intrus5', 'suite5', 'assoc5', 'compare5', 'suitenum5', 'categorie5', 'rang5', 'letters5', 'letters_sound5', 'digits5', 'count5', 'nextnum5', 'numbig5', 'addition', 'subtraction', 'addsub']],
        ['id' => 'age6', 'topics' => ['problem6', 'colors6', 'shapes6', 'sizes6', 'animals6', 'animalsound6', 'foods6', 'body6', 'emotions6', 'opposites6', 'pareil6', 'intrus6', 'suite6', 'assoc6', 'compare6', 'suitenum6', 'categorie6', 'rang6', 'letters6', 'letters_sound6', 'digits6', 'count6', 'nextnum6', 'numbig6', 'readword6', 'double6', 'addition', 'subtraction', 'addsub', 'muldiv']],
        ['id' => 'age7', 'topics' => ['problem7', 'sizes7', 'animalsound7', 'opposites7', 'intrus7', 'suite7', 'assoc7', 'compare7', 'suitenum7', 'categorie7', 'rang7', 'letters7', 'letters_sound7', 'readword7', 'spell7', 'digits7', 'count7', 'nextnum7', 'numbig7', 'double7', 'roman7', 'time7', 'money7', 'addition', 'subtraction', 'addsub', 'muldiv']],
        ['id' => 'age8', 'topics' => ['problem8', 'intrus8', 'suite8', 'suitenum8', 'compare8', 'categorie8', 'rang8', 'letters8', 'readword8', 'spell8', 'numbig8', 'nextnum8', 'count8', 'double8', 'roman8', 'measure8', 'time8', 'money8', 'addition', 'subtraction', 'addsub', 'muldiv']],
        ['id' => 'age9', 'topics' => ['problem9', 'intrus9', 'suite9', 'assoc9', 'categorie9', 'rang9', 'suitenum9', 'compare9', 'readword9', 'spell9', 'letters9', 'digits9', 'count9', 'nextnum9', 'numbig9', 'time9', 'double9', 'half9', 'triple9', 'evenodd9', 'complement9', 'roman9', 'measure9', 'money9', 'addition', 'subtraction', 'addsub', 'muldiv']],
        ['id' => 'age10', 'topics' => ['spella', 'readworda', 'numbiga', 'evenodda', 'complementa', 'fraction', 'decimals', 'problema', 'longmult', 'division', 'double9', 'triple9', 'romana', 'measurea', 'moneya', 'addition', 'subtraction', 'addsub', 'muldiv']],
        ['id' => 'age11', 'topics' => ['priorities', 'square', 'perimeter', 'aire', 'relative', 'longmult', 'division', 'fraction', 'decimals', 'problema', 'probpercent', 'probpropor', 'numbiga', 'romana', 'muldiv', 'addition', 'subtraction']],
        ['id' => 'age12', 'topics' => ['problema', 'probpercent', 'probpropor', 'percent', 'airetri', 'priorpar', 'relatadd', 'relatsub', 'proportion', 'volumecube', 'fraction', 'decimals', 'relative', 'priorities', 'aire', 'perimeter', 'longmult', 'division', 'muldiv']],
        ['id' => 'age13', 'topics' => ['problema', 'probpercent', 'probpropor', 'power', 'powerten', 'relatmul', 'expand', 'factorise', 'percent', 'proportion', 'priorpar', 'airetri', 'relatadd', 'relatsub', 'square', 'longmult', 'division', 'muldiv']],
        ['id' => 'age14', 'topics' => ['problema', 'probpercent', 'probpropor', 'sqrt', 'equation', 'equation2', 'function', 'pythagore', 'thales', 'power', 'powerten', 'relatmul', 'expand', 'factorise', 'percent', 'proportion', 'priorpar', 'muldiv', 'longmult']],
        ['id' => 'age15', 'topics' => ['problema', 'probpercent', 'probpropor', 'identremar', 'milieu', 'antecedent', 'evolution', 'moyenne', 'vecteur', 'sqrt', 'equation2', 'function', 'expand', 'factorise', 'power', 'percent', 'pythagore', 'thales']],
    ];

    /**
     * @param array $progress  [topic => bonnes réponses]
     * @return array  état par groupe (déverrouillé, maîtrisé, détail des thèmes)
     */
    public function compute(array $progress): array
    {
        $groups = [];

        foreach (self::GROUPS as $g) {
            $allMastered = true;
            $topics = [];

            foreach ($g['topics'] as $t) {
                $c = $progress[$t] ?? 0;
                $mastered = $c >= self::THRESHOLD;
                if (!$mastered) {
                    $allMastered = false;
                }
                $topics[] = [
                    'topic'    => $t,
                    'correct'  => $c,
                    'needed'   => self::THRESHOLD,
                    'mastered' => $mastered,
                ];
            }

            $groups[] = [
                'id'       => $g['id'],
                'unlocked' => true,            // tout est accessible librement
                'mastered' => $allMastered,    // ✓ affiché quand le groupe est fini
                'topics'   => $topics,
            ];
        }

        // Tout est jouable ; la progression (✓ / jauges) reste juste indicative.
        return ['groups' => $groups, 'all_unlocked' => true];
    }
}
