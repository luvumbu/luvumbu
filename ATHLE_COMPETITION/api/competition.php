<?php
/**
 * Fiche complète d'une compétition : épreuves par catégorie, horaire, lieu,
 * inscriptions. Alimente le panneau latéral de l'interface.
 *
 * GET api/competition.php?id=42
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/Disciplines.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Identifiant manquant.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = db()->prepare(
        'SELECT c.*, ci.name AS city_name, ci.region, ci.latitude, ci.longitude
         FROM competitions c
         LEFT JOIN cities ci ON ci.id = c.city_id
         WHERE c.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if ($row === false) {
        http_response_code(404);
        echo json_encode(['error' => 'Compétition inconnue.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = json_decode((string) ($row['raw'] ?? ''), true);
    $blocks = json_decode((string) ($row['events'] ?? ''), true);
    $schedule = json_decode((string) ($row['schedule'] ?? ''), true);

    // Compte les épreuves et attache à chacune sa clé de discipline : le
    // panneau latéral s'en sert pour surligner celle qui est filtrée.
    $distinct = [];
    $labelToKey = [];  // intitulé nettoyé (minuscules) => clé, pour l'horaire
    $count = 0;
    if (is_array($blocks)) {
        foreach ($blocks as $b => $block) {
            foreach ($block['groups'] ?? [] as $g => $group) {
                foreach ($group['events'] ?? [] as $e => $event) {
                    $count++;
                    $key = discipline_key((string) ($event['short'] ?? ''));
                    $blocks[$b]['groups'][$g]['events'][$e]['key'] = $key;
                    if ($key !== '') {
                        $distinct[$key] = true;
                        $label = clean_event_label((string) ($event['label'] ?? ''));
                        if ($label !== '') {
                            $labelToKey[mb_strtolower($label)] = $key;
                        }
                    }
                }
            }
        }
    }

    // Rattache chaque ligne d'horaire à son épreuve. Les lignes sont de la forme
    // « 80 mètres haies Minimes Hommes » : l'intitulé est en tête, suivi de la
    // catégorie et du groupe. On retient la correspondance la plus longue, sans
    // quoi « 80 mètres » l'emporterait sur « 80 mètres haies ».
    $times = [];
    $earliest = [];  // même clé, heure normalisée, pour comparer
    if (is_array($schedule)) {
        foreach ($schedule as $i => $line) {
            $text = mb_strtolower((string) ($line['event'] ?? ''));
            $match = null;
            $matchLength = 0;

            foreach ($labelToKey as $label => $key) {
                $length = mb_strlen($label);
                if ($length > $matchLength && str_starts_with($text, $label)) {
                    $match = $key;
                    $matchLength = $length;
                }
            }

            $schedule[$i]['key'] = $match;

            // Première heure de passage de l'épreuve. La source écrit tantôt
            // « 09:00h » tantôt « 9:00h » : comparer les chaînes brutes
            // classerait 11:00 avant 9:00. On compare donc sur une heure
            // normalisée, et on affiche la forme d'origine.
            $time = trim((string) ($line['time'] ?? ''));
            if ($match !== null && preg_match('/^(\d{1,2}):(\d{2})/', $time, $hm)) {
                $sortable = sprintf('%02d:%02d', (int) $hm[1], (int) $hm[2]);
                if (!isset($earliest[$match]) || $sortable < $earliest[$match]) {
                    $earliest[$match] = $sortable;
                    $times[$match] = $time;
                }
            }
        }
    }

    echo json_encode([
        'id'                => (int) $row['id'],
        'title'             => $row['title'],
        'start_date'        => $row['start_date'],
        'end_date'          => $row['end_date'],
        'start_time'        => $row['start_time'] ? substr((string) $row['start_time'], 0, 5) : null,
        'end_time'          => $row['end_time'] ? substr((string) $row['end_time'], 0, 5) : null,
        'environment'       => $row['environment'],
        'conditions'        => $row['conditions'],
        'organizer'         => $row['organizer'],
        'contact_email'     => $row['contact_email'],
        'city_name'         => $row['city_name'],
        'region'            => $row['region'],
        'latitude'          => $row['latitude'] !== null ? (float) $row['latitude'] : null,
        'longitude'         => $row['longitude'] !== null ? (float) $row['longitude'] : null,
        'venue'             => $row['venue'],
        'venue_address'     => $row['venue_address'],
        'maps_url'          => $row['maps_url'],
        'registration_from' => $row['registration_from'],
        'registration_to'   => $row['registration_to'],
        'registration_url'  => $row['registration_url'],
        'entrants_url'      => $row['entrants_url'],
        'schedule_url'      => $row['schedule_url'],
        // Le lien n'existe sur le site que tant que les inscriptions sont
        // ouvertes ; on recoupe malgré tout avec la date, la base étant un
        // instantané qui peut dater de plusieurs jours.
        'registration_open' => $row['registration_url'] !== null
            && ($row['registration_to'] === null || $row['registration_to'] >= date('Y-m-d')),
        'categories'        => $row['categories'],
        'blocks'            => is_array($blocks) ? $blocks : [],
        'schedule'          => is_array($schedule) ? $schedule : [],
        // Heure de première apparition, par discipline — vide si la compétition
        // n'a pas publié d'horaire.
        'event_times'       => $times,
        'event_count'       => $count,
        'distinct_events'   => count($distinct),
        'participants'      => is_array($raw) && isset($raw['participants']) ? (int) $raw['participants'] : null,
        'status'            => is_array($raw) ? ($raw['status'] ?? null) : null,
        'url'               => $row['url'],
        'has_details'       => $row['details_fetched_at'] !== null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lecture impossible.', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
