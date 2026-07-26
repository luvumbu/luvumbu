<?php
/**
 * Catalogue des disciplines (épreuves).
 *
 * Les fiches détaillées listent les épreuves sous deux formes : un code court
 * (« 100m », « TS », « Cogner ») et un intitulé long qui embarque souvent les
 * spécifications de matériel — « Lancer du poids<br>Cadets Hommes: 4kg<br> ».
 * Sur 3 263 épreuves cela donne 537 intitulés distincts pour seulement
 * 73 codes courts : c'est donc le code court, normalisé, qui sert de clé.
 *
 * L'index est reconstruit à chaque import de fiches (bin/import-details.php),
 * ou à la demande (bin/index-disciplines.php).
 */

declare(strict_types=1);

/**
 * Familles d'épreuves, dans l'ordre d'affichage du menu.
 *
 * @return array<string,string> clé => libellé
 */
function discipline_families(): array
{
    return [
        'sprint'   => 'Sprint',
        'demifond' => 'Demi-fond et fond',
        'haies'    => 'Haies',
        'steeple'  => 'Steeple',
        'relais'   => 'Relais',
        'saut'     => 'Sauts',
        'lancer'   => 'Lancers',
        'marche'   => 'Marche',
        'para'     => 'Para-athlétisme',
        'autre'    => 'Autres',
    ];
}

/**
 * Réduit un code court à une clé stable : minuscules, sans accent ni séparateur.
 *
 * « 150 m » et « 150m » désignent la même épreuve, « 4 x 80 mix » et
 * « 4x80mix » aussi. Renvoie '' si le code est inexploitable.
 */
function discipline_key(string $short): string
{
    $key = mb_strtolower(trim($short));
    $key = strtr($key, [
        'à'=>'a','â'=>'a','ä'=>'a','ç'=>'c','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ÿ'=>'y',
    ]);
    $key = preg_replace('/[^a-z0-9]+/', '', $key) ?? '';

    // Doublons de vocabulaire de la source : « Cogner » est une traduction
    // malheureuse de « Kogel » (le poids) ; « Ver ZA » et « Ver stand »
    // désignent tous deux la longueur sans élan.
    $aliases = ['cogner' => 'poids', 'verza' => 'verstand'];

    return $aliases[$key] ?? $key;
}

/**
 * Nettoie un intitulé long : retire les spécifications de matériel, les
 * compteurs de places et le balisage.
 *
 * « Lancer du poids<br>Cadets Hommes: 4kg<br><br> 1/20 places occupées »
 *   → « Lancer du poids »
 */
function clean_event_label(string $label): string
{
    $label = preg_replace('/<br\s*\/?>.*$/is', '', $label) ?? $label;
    $label = strip_tags($label);
    $label = html_entity_decode($label, ENT_QUOTES, 'UTF-8');
    $label = preg_replace('/\s*\d+\s*\/\s*\d+\s*places occupées\s*$/iu', '', $label) ?? $label;
    $label = preg_replace('/\s*inscription complète\s*$/iu', '', $label) ?? $label;

    return trim(preg_replace('/\s+/u', ' ', $label) ?? $label);
}

/**
 * Rattache une clé d'épreuve à sa famille.
 *
 * L'ordre des tests compte : « 4x400mix » est un relais avant d'être un 400 m,
 * « 3000m SC » un steeple avant d'être du fond.
 */
function discipline_family(string $key): string
{
    static $lancers = ['poids', 'disque', 'javelot', 'marteau', 'lourds', 'vortex', 'balle', 'hockey'];
    static $sauts   = ['longueur', 'hauteur', 'perche', 'ts', 'verstand'];
    static $relais  = ['teamrelais', 'suedois', 'olyrelais'];
    static $fond    = ['1em', '2em', 'mara'];

    if (preg_match('/^\d+x/', $key) || in_array($key, $relais, true)) {
        return 'relais';
    }
    // Suffixes du para-athlétisme : WC = fauteuil (« roues »), FR = frame running.
    if (preg_match('/^\d.*(wc|fr)$/', $key)) {
        return 'para';
    }
    if (str_ends_with($key, 'sc')) {
        return 'steeple';
    }
    if (str_ends_with($key, 'sw')) {
        return 'marche';
    }
    if (preg_match('/^\d+mh$/', $key)) {
        return 'haies';
    }
    if (in_array($key, $lancers, true)) {
        return 'lancer';
    }
    if (in_array($key, $sauts, true)) {
        return 'saut';
    }
    if (preg_match('/^(\d+)m$/', $key, $m)) {
        return (int) $m[1] <= 400 ? 'sprint' : 'demifond';
    }
    if (in_array($key, $fond, true)) {
        return 'demifond';
    }

    return 'autre';
}

/**
 * Ordre à l'intérieur d'une famille : la plus grande distance citée par la clé,
 * pour que 60 m précède 100 m et que 4x100 m précède 4x400 m. Les épreuves sans
 * distance (sauts, lancers) retombent à 0 et se classent alors par intitulé.
 */
function discipline_sort_order(string $key): int
{
    // Distances que la clé ne porte pas en chiffres.
    static $known = ['1em' => 1609, '2em' => 3219, 'mara' => 42195];
    if (isset($known[$key])) {
        return $known[$key];
    }

    preg_match_all('/\d+/', $key, $matches);
    return $matches[0] === [] ? 0 : max(array_map('intval', $matches[0]));
}

/**
 * Reconstruit `disciplines` et `competition_disciplines` à partir de la colonne
 * `competitions.events`, qui reste la source de vérité.
 *
 * @return array{disciplines:int,links:int}
 */
function rebuild_discipline_index(PDO $pdo): array
{
    // Artefacts de découpage de la fiche source : ce ne sont pas des épreuves.
    $ignored = ['heures', ''];

    // Intitulés imposés là où la source est muette ou néerlandophone.
    $overrides = [
        'teamrelais' => "Relais d'équipe",
        'verstand'   => 'Longueur sans élan',
    ];

    $links  = [];  // [competition_id => [clé => true]]
    $votes  = [];  // [clé => [intitulé nettoyé => occurrences]]

    $rows = $pdo->query('SELECT id, events FROM competitions WHERE events IS NOT NULL');

    foreach ($rows as $row) {
        $blocks = json_decode((string) $row['events'], true);
        if (!is_array($blocks)) {
            continue;
        }

        foreach ($blocks as $block) {
            foreach ($block['groups'] ?? [] as $group) {
                foreach ($group['events'] ?? [] as $event) {
                    $key = discipline_key((string) ($event['short'] ?? ''));
                    if (in_array($key, $ignored, true)) {
                        continue;
                    }

                    $links[(int) $row['id']][$key] = true;

                    // L'intitulé retenu est le plus fréquent : « Lancer du
                    // poids » l'emporte ainsi sur le laconique « Poids ».
                    $label = clean_event_label((string) ($event['label'] ?? ''));
                    if ($label !== '') {
                        $votes[$key][$label] = ($votes[$key][$label] ?? 0) + 1;
                    }
                }
            }
        }
    }

    $families = array_keys(discipline_families());

    $catalogue = [];
    foreach ($votes as $key => $labels) {
        arsort($labels);
        $family = discipline_family($key);
        $catalogue[$key] = [
            'key'         => $key,
            'label'       => $overrides[$key] ?? (string) array_key_first($labels),
            'family'      => $family,
            'family_rank' => (int) array_search($family, $families, true),
            'sort_order'  => discipline_sort_order($key),
        ];
    }

    // Une épreuve sans aucun intitulé exploitable garde son code court.
    foreach ($links as $keys) {
        foreach (array_keys($keys) as $key) {
            if (!isset($catalogue[$key])) {
                $family = discipline_family($key);
                $catalogue[$key] = [
                    'key'         => $key,
                    'label'       => $key,
                    'family'      => $family,
                    'family_rank' => (int) array_search($family, $families, true),
                    'sort_order'  => discipline_sort_order($key),
                ];
            }
        }
    }

    $pdo->beginTransaction();
    try {
        // Données entièrement dérivées : on repart de zéro à chaque passage.
        // DELETE et non TRUNCATE, à cause des clés étrangères.
        $pdo->exec('DELETE FROM competition_disciplines');
        $pdo->exec('DELETE FROM disciplines');

        $insertDiscipline = $pdo->prepare(
            'INSERT INTO disciplines (discipline_key, label, family, family_rank, sort_order)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($catalogue as $discipline) {
            $insertDiscipline->execute([
                $discipline['key'],
                mb_substr($discipline['label'], 0, 120),
                $discipline['family'],
                $discipline['family_rank'],
                $discipline['sort_order'],
            ]);
        }

        $insertLink = $pdo->prepare(
            'INSERT INTO competition_disciplines (competition_id, discipline_key) VALUES (?, ?)'
        );
        $linkCount = 0;
        foreach ($links as $competitionId => $keys) {
            foreach (array_keys($keys) as $key) {
                $insertLink->execute([$competitionId, $key]);
                $linkCount++;
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['disciplines' => count($catalogue), 'links' => $linkCount];
}

/**
 * Catalogue prêt à afficher, groupé par famille et compté sur les compétitions
 * réellement proposées.
 *
 * @param bool $upcomingOnly ne compter que ce qui n'est pas terminé
 * @return array<string,list<array{key:string,label:string,count:int}>>
 */
function discipline_catalogue(PDO $pdo, bool $upcomingOnly = true): array
{
    $where = $upcomingOnly ? 'WHERE COALESCE(c.end_date, c.start_date) >= CURDATE()' : '';

    $sql = "
        SELECT d.discipline_key, d.label, d.family, COUNT(*) AS competitions
        FROM competition_disciplines cd
        JOIN disciplines  d ON d.discipline_key = cd.discipline_key
        JOIN competitions c ON c.id = cd.competition_id
        {$where}
        GROUP BY d.discipline_key, d.label, d.family, d.family_rank, d.sort_order
        ORDER BY d.family_rank, d.sort_order, d.label
    ";

    $labels  = discipline_families();
    $grouped = [];

    foreach ($pdo->query($sql) as $row) {
        $family = $labels[$row['family']] ?? $row['family'];
        $grouped[$family][] = [
            'key'   => $row['discipline_key'],
            'label' => $row['label'],
            'count' => (int) $row['competitions'],
        ];
    }

    return $grouped;
}
