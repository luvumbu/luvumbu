<?php
/**
 * Alimente la table `places` avec le répertoire des codes postaux GeoNames.
 *
 * Pourquoi : Nominatim n'autorise pas l'autocomplétion au clavier et limite à
 * 1 requête/seconde. Avec ce répertoire en local, la saisie d'adresse propose
 * des communes instantanément et résout « 4020 » ou « Liège » sans réseau.
 *
 * Données : https://download.geonames.org/export/zip/ (licence CC BY 4.0)
 *
 * Usage :
 *   php bin/import-places.php              importe BE
 *   php bin/import-places.php BE FR NL     importe plusieurs pays
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande uniquement.\n");
}

$countries = array_slice($argv, 1);
if ($countries === []) {
    $countries = ['BE'];
}

$pdo = db();

try {
    $pdo->query('SELECT 1 FROM places LIMIT 1');
} catch (PDOException $e) {
    cli_log('[ERREUR] Table `places` absente. Lancez d\'abord : php bin/setup.php');
    exit(1);
}

$insert = $pdo->prepare(
    'INSERT INTO places (country_code, postal_code, name, name_normalized, region, province, latitude, longitude)
     VALUES (?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE
        name = VALUES(name), region = VALUES(region), province = VALUES(province),
        latitude = VALUES(latitude), longitude = VALUES(longitude)'
);

foreach ($countries as $country) {
    $country = strtoupper(substr(trim($country), 0, 2));
    if (!preg_match('/^[A-Z]{2}$/', $country)) {
        cli_log("[IGNORÉ] Code pays invalide : {$country}");
        continue;
    }

    $url = "https://download.geonames.org/export/zip/{$country}.zip";
    cli_log("Téléchargement de {$url}…");

    $zipPath = tempnam(sys_get_temp_dir(), 'geonames_') ?: null;
    if ($zipPath === null) {
        cli_log('[ERREUR] Impossible de créer un fichier temporaire.');
        exit(1);
    }

    $handle = fopen($zipPath, 'wb');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $handle,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_USERAGENT      => (string) app_config()['geocoder']['user_agent'],
    ]);
    $ok = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    fclose($handle);

    if (!$ok || $status !== 200) {
        cli_log("[ERREUR] Téléchargement échoué (HTTP {$status}) {$curlError}");
        @unlink($zipPath);
        continue;
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        cli_log('[ERREUR] Archive illisible.');
        @unlink($zipPath);
        continue;
    }

    $content = $zip->getFromName("{$country}.txt");
    $zip->close();
    @unlink($zipPath);

    if ($content === false) {
        cli_log("[ERREUR] {$country}.txt absent de l'archive.");
        continue;
    }

    // Format GeoNames (tabulations) :
    // 0 pays | 1 code postal | 2 localité | 3 région | 4 code | 5 province
    // | 6 code | 7 arrondissement | 8 code | 9 latitude | 10 longitude | 11 précision
    $imported = 0;
    $skipped  = 0;

    $pdo->beginTransaction();
    foreach (explode("\n", $content) as $line) {
        $line = rtrim($line, "\r");
        if ($line === '') {
            continue;
        }

        $f = explode("\t", $line);
        if (count($f) < 11 || $f[1] === '' || $f[2] === '' || $f[9] === '' || $f[10] === '') {
            $skipped++;
            continue;
        }

        // Les codes CEDEX français ne désignent pas un lieu habité mais une
        // boîte postale d'entreprise : ils polluent l'autocomplétion.
        if (stripos($f[1], 'CEDEX') !== false) {
            $skipped++;
            continue;
        }

        $insert->execute([
            $country,
            trim($f[1]),
            trim($f[2]),
            normalize_city_name($f[2]),
            trim($f[3]) !== '' ? trim($f[3]) : null,
            trim($f[5]) !== '' ? trim($f[5]) : null,
            (float) $f[9],
            (float) $f[10],
        ]);
        $imported++;
    }
    $pdo->commit();

    $total = (int) $pdo->query("SELECT COUNT(*) FROM places WHERE country_code = '{$country}'")->fetchColumn();
    cli_log("[OK] {$country} : {$imported} localité(s) traitée(s), {$skipped} ligne(s) ignorée(s) — {$total} en base.");
}

cli_log('');
cli_log('Répertoire prêt : la saisie d\'adresse propose désormais les communes hors ligne.');
