<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/Disciplines.php';

$error = null;
$summary = ['competitions' => 0, 'cities' => 0, 'pending' => 0, 'failed' => 0, 'last_import' => null];

try {
    $pdo = db();
    $summary['competitions'] = (int) $pdo->query('SELECT COUNT(*) FROM competitions')->fetchColumn();
    $summary['cities']       = (int) $pdo->query("SELECT COUNT(*) FROM cities WHERE geocode_status IN ('ok','manual')")->fetchColumn();
    $summary['pending']      = (int) $pdo->query("SELECT COUNT(*) FROM cities WHERE geocode_status = 'pending'")->fetchColumn();
    $summary['failed']       = (int) $pdo->query("SELECT COUNT(*) FROM cities WHERE geocode_status = 'failed'")->fetchColumn();
    $summary['last_import']  = $pdo->query('SELECT finished_at FROM import_runs WHERE status = "ok" ORDER BY id DESC LIMIT 1')->fetchColumn() ?: null;
} catch (Throwable $e) {
    $error = $e->getMessage();

    // Base injoignable ou schéma absent : c'est un défaut de configuration, pas
    // une panne. On envoie sur l'assistant, qui sait poser les questions et
    // écrire config/config.local.php — le fichier que Git ne déploie jamais.
    if (is_file(__DIR__ . '/install.php')) {
        header('Location: install.php');
        exit;
    }
}

// Le menu « Pays » ne liste que ce qui existe réellement en base : proposer un
// pays sans données donnerait un écran vide sans explication.
$labels = app_config()['countries'];
$countries = [];
if ($error === null) {
    foreach ($pdo->query('SELECT DISTINCT country_code FROM competitions ORDER BY country_code')->fetchAll(PDO::FETCH_COLUMN) as $code) {
        $countries[$code] = $labels[$code] ?? $code;
    }
}

// Menu « Épreuve » : le catalogue est dérivé des fiches détaillées. Sur une base
// où elles n'ont pas encore été importées, le menu ne s'affiche pas plutôt que
// de proposer une liste vide.
$disciplines = [];
if ($error === null) {
    try {
        $disciplines = discipline_catalogue($pdo);
    } catch (PDOException $e) {
        // Tables absentes : base installée avant l'ajout du filtre.
        // Correctif : php bin/setup.php && php bin/index-disciplines.php
    }
}

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Carte des compétitions d'athlétisme</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><circle cx='8' cy='8' r='6' fill='none' stroke='%231f6feb' stroke-width='2'/><circle cx='8' cy='8' r='2' fill='%231f6feb'/></svg>">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?php if ($error !== null): ?>
<div class="fatal">
    <h1>Base de données inaccessible</h1>
    <p><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <p><a href="install.php">Configurer la base de données →</a></p>
</div>
<?php else: ?>

<header class="topbar">
    <div class="brand">
        <span class="brand-mark" aria-hidden="true">◎</span>
        <div>
            <h1>Villes de compétition</h1>
            <p class="brand-sub">Calendrier athletisme.app placé sur la carte</p>
        </div>
    </div>

    <div class="counters">
        <div class="counter"><strong id="stat-competitions"><?= $summary['competitions'] ?></strong><span>compétitions</span></div>
        <div class="counter"><strong id="stat-cities"><?= $summary['cities'] ?></strong><span>villes</span></div>
        <?php if ($summary['pending'] || $summary['failed']): ?>
        <div class="counter warn" title="Villes sans coordonnées : lancez php bin/geocode.php">
            <strong><?= $summary['pending'] + $summary['failed'] ?></strong><span>à géocoder</span>
        </div>
        <?php endif; ?>
    </div>
</header>

<main class="layout">
    <aside class="panel">
        <form class="filters" id="filters" autocomplete="off">
            <div class="field">
                <span>Mon adresse — Belgique, France ou Luxembourg</span>
                <div class="locate-row">
                    <div class="autocomplete">
                        <input type="search" id="f-address" placeholder="Code postal ou commune…"
                               role="combobox" aria-autocomplete="list" aria-expanded="false"
                               aria-controls="address-suggestions">
                        <ul class="suggestions" id="address-suggestions" role="listbox" hidden></ul>
                    </div>
                    <button type="button" id="f-locate" title="Chercher cette adresse">Situer</button>
                    <button type="button" id="f-gps" title="Utiliser ma position actuelle" aria-label="Utiliser ma position actuelle">◎</button>
                </div>
            </div>

            <p class="locate-result" id="locate-result" hidden></p>

            <div class="field-row" id="distance-controls" hidden>
                <label class="field">
                    <span>Dans un rayon de</span>
                    <select id="f-radius">
                        <option value="">Sans limite</option>
                        <option value="10">10 km</option>
                        <option value="25">25 km</option>
                        <option value="50" selected>50 km</option>
                        <option value="75">75 km</option>
                        <option value="100">100 km</option>
                        <option value="150">150 km</option>
                    </select>
                </label>
                <label class="field">
                    <span>Trier par</span>
                    <select id="f-sort">
                        <option value="distance">Distance</option>
                        <option value="date">Date</option>
                    </select>
                </label>
            </div>

            <label class="field">
                <span>Recherche</span>
                <input type="search" name="q" id="f-q" placeholder="Meeting, club, ville…">
            </label>

            <div class="field-row">
                <label class="field">
                    <span>Du</span>
                    <input type="date" name="from" id="f-from" value="<?= $today ?>">
                </label>
                <label class="field">
                    <span>Au</span>
                    <input type="date" name="to" id="f-to">
                </label>
            </div>

            <div class="field-row">
                <label class="field">
                    <span>Type</span>
                    <select name="env" id="f-env">
                        <option value="all">Tous</option>
                        <option value="out">Outdoor</option>
                        <option value="in">Indoor</option>
                    </select>
                </label>
                <label class="field"<?= count($countries) < 2 ? ' hidden' : '' ?>>
                    <span>Pays</span>
                    <select name="country" id="f-country">
                        <?php if (count($countries) > 1): ?>
                        <option value="">Tous</option>
                        <?php endif; ?>
                        <?php foreach ($countries as $code => $label): ?>
                        <option value="<?= $code ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <?php if ($disciplines !== []): ?>
            <label class="field">
                <span>Épreuve / discipline</span>
                <select name="event" id="f-event">
                    <option value="">Toutes les épreuves</option>
                    <?php foreach ($disciplines as $family => $items): ?>
                    <optgroup label="<?= htmlspecialchars($family, ENT_QUOTES) ?>">
                        <?php foreach ($items as $discipline): ?>
                        <option value="<?= htmlspecialchars($discipline['key'], ENT_QUOTES) ?>"><?= htmlspecialchars($discipline['label'], ENT_QUOTES) ?> (<?= $discipline['count'] ?>)</option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>
            <p class="field-note" id="event-note" hidden>
                Seules les compétitions dont les épreuves sont publiées peuvent être filtrées&nbsp;;
                les autres sont masquées.
            </p>
            <?php endif; ?>

            <label class="check">
                <input type="checkbox" name="past" id="f-past" value="1">
                <span>Inclure les compétitions passées</span>
            </label>
        </form>

        <div class="tabs" role="tablist">
            <button type="button" class="tab is-active" data-view="competitions" role="tab">Compétitions</button>
            <button type="button" class="tab" data-view="cities" role="tab">Villes</button>
        </div>

        <div class="selection" id="selection" hidden>
            <span id="selection-label"></span>
            <button type="button" id="selection-clear" title="Retirer le filtre de ville">×</button>
        </div>

        <div class="results" id="results" aria-live="polite">
            <p class="placeholder">Chargement…</p>
        </div>
    </aside>

    <div class="map-wrap">
        <div id="map"></div>
        <div class="map-note" id="map-note" hidden></div>

        <!-- Fiche détaillée : épreuves, horaire, lieu, lien vers la source -->
        <section class="drawer" id="drawer" hidden aria-label="Détail de la compétition">
            <button type="button" class="drawer-close" id="drawer-close" aria-label="Fermer">×</button>
            <div class="drawer-body" id="drawer-body"></div>
        </section>
    </div>
</main>

<footer class="statusbar">
    <span>
        <?php if ($summary['last_import']): ?>
            Dernier import&nbsp;: <?= htmlspecialchars((string) $summary['last_import'], ENT_QUOTES) ?>
        <?php else: ?>
            Aucun import enregistré
        <?php endif; ?>
    </span>
    <span>Données&nbsp;: athletisme.app · Fonds de carte&nbsp;: OpenStreetMap · Géocodage&nbsp;: Nominatim</span>
</footer>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="assets/app.js"></script>

<?php endif; ?>
</body>
</html>
