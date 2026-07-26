<?php
/**
 * Amorçage commun : configuration, connexion PDO, fonctions utilitaires.
 * Tout point d'entrée (CLI ou web) inclut ce fichier.
 */

declare(strict_types=1);

mb_internal_encoding('UTF-8');

/**
 * @return array<string,mixed>
 */
function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }
    return $config;
}

/**
 * Connexion PDO partagée.
 *
 * @param bool $withDatabase false = se connecte au serveur sans sélectionner de base
 *                           (nécessaire pour CREATE DATABASE au premier lancement)
 */
function db(bool $withDatabase = true): PDO
{
    static $handles = [];
    $key = $withDatabase ? 'db' : 'server';

    if (!isset($handles[$key])) {
        $cfg = app_config()['db'];
        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $cfg['host'], $cfg['port'], $cfg['charset']);
        if ($withDatabase) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'],
                $cfg['port'],
                $cfg['name'],
                $cfg['charset']
            );
        }

        $handles[$key] = new PDO($dsn, $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $handles[$key];
}

/**
 * Normalise un nom de ville pour le rapprochement :
 * minuscules, accents retirés, ponctuation et bruit supprimés.
 *
 * « Ottignies-Louvain-la-Neuve » → « ottignies louvain la neuve »
 * « BRUXELLES (BE) »             → « bruxelles »
 */
function normalize_city_name(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }

    // Retire un code postal en tête ou en fin : « 1000 Bruxelles », « Bruxelles 1000 »
    $name = preg_replace('/^\s*\d{4,5}\s+/u', '', $name) ?? $name;
    $name = preg_replace('/\s+\d{4,5}\s*$/u', '', $name) ?? $name;

    // Retire un suffixe pays entre parenthèses : « Gand (BE) »
    $name = preg_replace('/\s*\((?:[A-Za-z]{2,3}|[^)]{0,20})\)\s*$/u', '', $name) ?? $name;

    $lower = mb_strtolower($name);

    // Translittération des accents (indépendante de l'extension intl)
    $map = [
        'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','ā'=>'a',
        'ç'=>'c','ć'=>'c','č'=>'c',
        'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ē'=>'e','ę'=>'e',
        'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ī'=>'i',
        'ñ'=>'n','ń'=>'n',
        'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o','ō'=>'o',
        'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ū'=>'u',
        'ý'=>'y','ÿ'=>'y',
        'ž'=>'z','ź'=>'z','ż'=>'z',
        'š'=>'s','ś'=>'s',
        'ß'=>'ss','æ'=>'ae','œ'=>'oe',
    ];
    $lower = strtr($lower, $map);

    // Tout ce qui n'est pas lettre/chiffre devient une espace
    $lower = preg_replace('/[^a-z0-9]+/u', ' ', $lower) ?? $lower;

    return trim(preg_replace('/\s+/', ' ', $lower) ?? $lower);
}

/**
 * Sépare un libellé de lieu en [ville, salle/stade éventuel].
 * « Gent - Topsporthal Vlaanderen » → ['Gent', 'Topsporthal Vlaanderen']
 *
 * @return array{0:string,1:?string}
 */
function split_venue(string $location): array
{
    $location = trim($location);
    if ($location === '') {
        return ['', null];
    }

    foreach ([' - ', ' – ', ' — ', ', ', ' / '] as $separator) {
        $pos = mb_strpos($location, $separator);
        if ($pos !== false) {
            $city  = trim(mb_substr($location, 0, $pos));
            $venue = trim(mb_substr($location, $pos + mb_strlen($separator)));
            if ($city !== '') {
                return [$city, $venue !== '' ? $venue : null];
            }
        }
    }

    return [$location, null];
}

/**
 * Convertit les formats de date rencontrés en Y-m-d, ou null si illisible.
 */
function parse_date(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    // Timestamp Unix (secondes ou millisecondes)
    if (preg_match('/^-?\d{9,13}$/', $value)) {
        $ts = (int) $value;
        if (abs($ts) > 100000000000) {
            $ts = intdiv($ts, 1000);
        }
        return date('Y-m-d', $ts);
    }

    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Écrit une ligne sur la sortie standard (scripts CLI).
 */
function cli_log(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}
