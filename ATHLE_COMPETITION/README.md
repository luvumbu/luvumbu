# ATHLE_COMPETITION — carte des villes de compétition

Application PHP/MySQL qui récupère le calendrier des compétitions d'athlétisme
d'[athletisme.app](https://www.athletisme.app/wedstrijden/), géocode chaque ville
et les affiche sur une carte interactive avec recherche par adresse et fiches
détaillées.

**État actuel de la base** (dernier rafraîchissement) :

| | |
|---|---|
| Compétitions | 165 |
| Villes géocodées | 69 / 69 |
| Compétitions avec leurs épreuves | 164 (3 263 épreuves) |
| Disciplines distinctes | 70 |
| Avec horaire extrait | 88 |
| Avec lien d'inscription | 162 |
| Avec lien vers l'horaire complet | 165 |
| Codes postaux (BE + FR + LU) | 44 543 |

## Ce que ça fait

1. **Scraper du calendrier** (Node + Playwright) — pilote un vrai Chrome pour
   franchir le challenge Cloudflare, puis interroge l'endpoint interne du site
   (`feeder.php?page=search&do=events&…`) et écrit `data/competitions.json`.
2. **Import** (PHP) — normalise les noms de villes, crée la table des villes et
   rattache chaque compétition à sa ville par clé étrangère.
3. **Scraper des fiches** (Node + Playwright) — ouvre chaque fiche pour en
   extraire les épreuves par catégorie, l'horaire, l'adresse exacte du stade,
   la période d'inscription et les liens directs (inscription, horaire,
   inscrits).
4. **Géocodage** (PHP + Nominatim/OpenStreetMap) — attribue une latitude et une
   longitude à chaque ville, une seule fois, avec mise en cache en base.
5. **Interface** (Leaflet) — un marqueur par ville dimensionné selon le nombre de
   compétitions, liste latérale synchronisée, filtres date / indoor-outdoor /
   **épreuve** / recherche libre.
6. **Recherche par adresse** — autocomplétion instantanée sur les codes postaux
   **belges, français et luxembourgeois** (répertoire local, aucun appel réseau),
   ou géolocalisation du navigateur : les compétitions sont triées par distance
   et filtrables par rayon. L'adresse est mémorisée dans le navigateur.
   Habiter en France et courir en Belgique fonctionne : la recherche d'adresse
   est indépendante du pays des compétitions.
7. **Fiches détaillées** — un clic sur une compétition ouvre un panneau latéral :
   épreuves par catégorie, premières lignes de l'horaire, adresse du stade
   cliquable vers Google Maps, période d'inscription, contact du club.

## Choisir son épreuve

Le menu **Épreuve / discipline** ne garde que les compétitions qui proposent
l'épreuve choisie — « Saut à la perche », « 800 mètres », « Javelot ». Il se
combine avec tous les autres filtres : *perche, à moins de 60 km de Liège, en
septembre* est une question qui a une réponse en trois clics. Le nombre entre
parenthèses indique combien de compétitions à venir la proposent.

Les épreuves sont regroupées par famille — sprint, demi-fond et fond, haies,
steeple, relais, sauts, lancers, marche, para-athlétisme.

Une fois l'épreuve choisie, elle ressort **en bleu, texte blanc**, à deux
endroits de la fiche : sur la pastille de chaque catégorie d'âge qui l'accueille
— avec son **heure de passage** quand elle est connue —, et sur les lignes
correspondantes de l'horaire. On voit donc d'un coup d'œil pour quelles
catégories l'épreuve est ouverte, et à quelle heure il faut être là.

### Comment l'heure est retrouvée

L'horaire source est une simple liste `heure / texte`, sans identifiant
d'épreuve : `18:00h — Saut à la perche AC-M/V - Polshoog begin 3.21 groep 1`.
Chaque ligne est rattachée à son épreuve en cherchant, parmi les intitulés de la
compétition, celui qui **préfixe** le texte, le plus long l'emportant — sans
quoi « 80 mètres » gagnerait sur « 80 mètres haies ». L'heure retenue est la
première du jour pour cette épreuve.

Un détail qui compte&nbsp;: la source écrit tantôt `09:00h`, tantôt `9:00h`.
Comparer les chaînes brutes classerait `11:00h` avant `9:00h` — la comparaison
se fait donc sur une heure normalisée, et c'est la forme d'origine qui est
affichée.

### Comment la liste est construite

Les fiches sources donnent deux intitulés par épreuve : un code court (`100m`,
`TS`, `Cogner`) et un intitulé long qui embarque les spécifications de matériel
(`Lancer du poids<br>Cadets Hommes: 4kg<br>`). Sur 3 263 épreuves, cela fait
537 intitulés longs distincts pour seulement 73 codes courts — c'est donc le
**code court, normalisé**, qui sert de clé (`src/Disciplines.php`), et
l'intitulé long, nettoyé de ses spécifications, qui fournit le libellé affiché
(le plus fréquent l'emporte : « Lancer du poids » plutôt que « Poids »).

Trois regroupements sont faits à la main, parce que la source nomme deux fois la
même chose : `Cogner` → poids (mauvaise traduction du néerlandais *kogel*),
`Ver ZA` et `Ver stand` → longueur sans élan. L'entrée `Heures` est écartée :
c'est un artefact de découpage de la page source, pas une épreuve.

L'index est reconstruit à chaque `import-details.php`, ou seul&nbsp;:

```bash
php bin/index-disciplines.php           # reconstruit
php bin/index-disciplines.php --list    # et affiche le catalogue obtenu
```

⚠️ Une compétition dont les épreuves n'ont pas été récupérées **disparaît** dès
qu'une épreuve est choisie : rien ne permet d'affirmer qu'elle la propose. C'est
signalé sous le menu.

## Les liens vers le site source

Chaque fiche se termine par des liens directs, dans cet ordre :

| Lien | Style | Destination | Condition d'affichage |
|---|---|---|---|
| **S'inscrire à cette compétition** | bleu plein | `/wedstrijd/inschrijven/{id}/` | inscriptions ouvertes |
| Horaire complet | bleu contour | `/wedstrijd/chronoloog/{id}/` | lien présent sur la fiche |
| Voir les inscrits | bleu contour | `/wedstrijd/atleten/{id}/` | lien présent sur la fiche |
| En savoir plus | bleu contour | URL du calendrier | si différente des précédentes |

Le lien « horaire complet » apparaît aussi à droite du titre de la section
**Horaire** : la fiche source ne montre que les premières épreuves, le lien mène
à la liste minute par minute.

Deux règles qui évitent les liens trompeurs :

- **Inscriptions.** Le site ne publie le lien d'inscription que tant qu'elles
  sont ouvertes ; sa présence est donc le signal, recoupé avec la date de
  clôture. Quand c'est fermé, le bouton disparaît au profit de « Inscriptions
  clôturées le … » en orange.
- **Dédoublonnage.** Pour certaines compétitions, l'URL du calendrier *est déjà*
  celle de l'horaire ou des inscrits. Les boutons qui pointeraient au même
  endroit sont masqués.

## Trouver les compétitions près de chez soi

1. Tapez votre adresse dans **Mon adresse** — « 4020 Liège » ou « 59200
   Tourcoing » suffisent, une adresse de rue complète marche aussi. Des
   suggestions apparaissent dès 2 caractères, avec le pays (`BE` / `FR` / `LU`) :
   flèches ↑↓ puis Entrée, ou clic. Le bouton **◎** utilise la position GPS du
   navigateur.
2. Choisissez un **rayon** (10 à 150 km, ou sans limite) et le **tri**
   (distance ou date).
3. La carte affiche votre point de départ (⌂) et le cercle du rayon ; chaque
   compétition porte sa distance.

Les distances sont calculées par formule de haversine directement en SQL, sur
les coordonnées des villes. Ce sont des distances **à vol d'oiseau**, pas des
temps de trajet routier.

## Prérequis

- XAMPP avec **Apache** et **MySQL** démarrés (testé avec PHP 8.2 / MariaDB 10.4)
- **Node.js** 18+
- **Google Chrome** installé (Playwright l'utilise via `channel: 'chrome'`)
- Extensions PHP : `pdo_mysql`, `curl`, `mbstring`, `json`, `zip`

## Installation

```bash
# 1. Base de données — idempotent, relançable.
#    Ajoute aussi les colonnes manquantes sur une base déjà installée.
C:\xampp\php\php.exe bin/setup.php

# 2. Répertoire des codes postaux (autocomplétion d'adresse)
C:\xampp\php\php.exe bin/import-places.php BE FR LU

# 3. Dépendances du scraper
cd scraper
npm install
```

## Utilisation

Tout en une commande :

```bat
update.cmd
```

`update.cmd --rapide` saute les fiches détaillées, de loin l'étape la plus
longue (une requête par compétition, avec temporisation).

Ou étape par étape :

```bash
cd scraper && node scrape.js          # calendrier  → data/competitions.json
cd .. && php bin/import.php           #             → MySQL
cd scraper && node details.js         # épreuves    → data/details.json
cd .. && php bin/import-details.php   #             → MySQL
php bin/geocode.php                   # villes      → coordonnées
```

Puis ouvrez **http://localhost/ATHLE_COMPETITION/**

### Options — `scrape.js` (calendrier)

| Option | Effet |
|---|---|
| *(aucune)* | 12 mois à partir d'aujourd'hui |
| `--from=2026-01-01 --to=2026-12-31` | fenêtre de dates explicite |
| `--months=24` | fenêtre à partir d'aujourd'hui |
| `--country=BE` | code pays (voir « Limites connues » : seule la Belgique a des données) |
| `--headless` | sans fenêtre — **uniquement après un premier run réussi** |
| `--debug` | conserve le HTML brut du feeder dans `data/raw/` |

### Options — `details.js` (fiches)

| Option | Effet |
|---|---|
| *(aucune)* | toutes les compétitions de `data/competitions.json` |
| `--limit=5` | les 5 premières, pour mise au point |
| `--only=44492` | une compétition précise |
| `--ids=44516,44603` | une liste précise (voir ci-dessous) |
| `--delay=800` | pause entre deux fiches, en millisecondes |
| `--debug` | conserve le HTML de la première fiche |

Le site renvoie **HTTP 429** si on enchaîne trop vite. `details.js` attend de
plus en plus longtemps et réessaie jusqu'à 4 fois. S'il reste des trous :

```bash
# liste les compétitions sans épreuves
php -r "require 'src/bootstrap.php'; echo implode(',', db()->query(
  'SELECT external_id FROM competitions WHERE events IS NULL')->fetchAll(PDO::FETCH_COLUMN));"

# puis relance uniquement celles-là
cd scraper && node details.js --ids=44516,44603
```

### Options — `geocode.php`

```bash
php bin/geocode.php --limit=20        # traite 20 villes puis s'arrête
php bin/geocode.php --retry-failed    # retente les villes en échec
php bin/geocode.php --city="Oordegem" --lat=50.9333 --lon=3.9167   # correction manuelle
```

## Le challenge Cloudflare

Le site renvoie une page « Checking your browser » à toute requête HTTP directe :
`curl`, `file_get_contents()` et un `fetch` côté serveur ne récupèrent **jamais**
les données. C'est pourquoi les scrapers passent par Chrome.

Le profil de navigateur est conservé dans `scraper/.browser-profile`, donc le
cookie `cf_clearance` survit d'une exécution à l'autre. Concrètement :

- **premier lancement** : laissez la fenêtre visible, cochez la case si elle
  apparaît ;
- **lancements suivants** : `node scrape.js --headless` fonctionne tant que le
  cookie est valide. S'il expire, relancez une fois sans `--headless`.

Supprimez `scraper/.browser-profile` pour repartir d'une session vierge.

## Structure

```
ATHLE_COMPETITION/
├── index.php               interface carte
├── update.cmd              calendrier + import + fiches + géocodage
├── api/
│   ├── competitions.php    liste filtrée + distances, consommée par la carte
│   ├── competition.php     fiche complète : épreuves, horaire, liens
│   ├── suggest.php         autocomplétion d'adresse (répertoire local)
│   └── locate.php          adresse libre → coordonnées (cascade + cache)
├── assets/
│   ├── app.js              carte, liste, filtres, panneau de détail
│   └── style.css
├── bin/
│   ├── setup.php           base + schéma + colonnes manquantes
│   ├── import-places.php   codes postaux GeoNames → table `places`
│   ├── import.php          calendrier JSON → MySQL
│   ├── import-details.php  épreuves/horaires/liens JSON → MySQL
│   ├── index-disciplines.php  épreuves → catalogue des disciplines
│   └── geocode.php         villes → coordonnées
├── config/
│   └── config.php          identifiants BD, réglages géocodeur
├── data/
│   ├── competitions.json   dernier scraping du calendrier
│   ├── details.json        dernier scraping des fiches
│   ├── address-cache.json  adresses déjà géocodées
│   └── raw/                dumps de diagnostic
├── scraper/
│   ├── scrape.js           calendrier (Playwright + parseur du feeder)
│   └── details.js          fiches détaillées (épreuves, horaires, liens)
├── sql/
│   └── schema.sql
└── src/
    ├── bootstrap.php       config, PDO, normalisation, dates
    ├── Disciplines.php     clés d'épreuves, familles, index
    └── Geocoder.php        client Nominatim
```

## API interne

Toutes les réponses sont en JSON, sans authentification (usage local).

| Endpoint | Paramètres | Renvoie |
|---|---|---|
| `api/competitions.php` | `from` `to` `env` `event` `country` `city` `q` `past` `lat` `lon` `radius` `sort` | compétitions filtrées, villes agrégées, compteurs |
| `api/competition.php` | `id` | fiche complète : épreuves, horaire, liens, `registration_open`, `event_times` |
| `api/suggest.php` | `q` `country` *(optionnel)* | jusqu'à 8 localités, tous pays par défaut |
| `api/locate.php` | `q` `country` *(optionnel)* | `lat`, `lon`, `label`, `precision`, `source` |

`api/locate.php` applique une **cascade** : pour une saisie « commune » il
interroge le répertoire local d'abord (instantané, sans quota) ; pour une adresse
de rue il tente Nominatim, puis Nominatim sans le numéro de police, puis retombe
sur le code postal, puis sur le nom de commune. Il ne répond « introuvable »
qu'après avoir tout épuisé.

## Modèle de données

- **`cities`** — une ligne par ville, `UNIQUE(name_normalized, country_code)`.
  Porte les coordonnées et le statut de géocodage (`pending`/`ok`/`failed`/`manual`).
- **`competitions`** — `city_id` en clé étrangère vers `cities`. Dédoublonnage par
  `fingerprint` (sha1 de l'identifiant source, ou date+titre+ville à défaut) :
  relancer un import met à jour au lieu de dupliquer.

  Colonnes issues des fiches détaillées : `start_time`, `end_time`,
  `venue_address`, `maps_url`, `contact_email`, `conditions`,
  `registration_from`, `registration_to`, `registration_url`, `entrants_url`,
  `schedule_url`, `categories`, `events` (JSON), `schedule` (JSON),
  `details_fetched_at`.
- **`places`** — codes postaux BE + FR + LU (GeoNames, CC BY 4.0). Sert à
  l'autocomplétion et à la résolution d'adresse **sans appel réseau** :
  Nominatim interdit l'autocomplétion au clavier et limite à 1 requête/seconde.
  Les codes CEDEX français sont écartés à l'import (boîtes postales
  d'entreprise, pas des lieux habités). Ajoutez un pays :
  `php bin/import-places.php NL`.
- **`disciplines`** / **`competition_disciplines`** — catalogue des épreuves et
  rattachement de chaque compétition. **Entièrement dérivées** de
  `competitions.events` : elles sont vidées puis reconstruites à chaque
  indexation, ne rien y saisir à la main.
- **`city_aliases`** — variantes d'écriture d'une même ville (« Gand »/« Gent »).
  Ajoutez-y une ligne pour fusionner deux entrées créées en double.
- **`import_runs`** — journal des imports.
- **`v_competitions_geo`** — vue joignant compétitions et coordonnées.

### Règle de rafraîchissement

`import-details.php` écrit en `COALESCE(?, colonne)` : **un rafraîchissement ne
peut qu'enrichir, jamais effacer**. Le site tronque parfois ses pages sous charge
ou répond 429 en cours de route ; sans cette règle, une fiche muette effacerait
des épreuves déjà acquises (c'est arrivé sur 8 compétitions avant le correctif).

Une seule exception volontaire : `registration_url` est écrasé sans filet. Quand
le site retire ce lien, c'est que les inscriptions sont closes — l'information
doit disparaître.

## Personnalisation

Créez `config/config.local.php` pour surcharger sans toucher au dépôt :

```php
<?php
return [
    'db' => ['user' => 'athle', 'password' => 'secret'],
    'geocoder' => ['user_agent' => 'MonApp/1.0 (moi@exemple.be)'],
];
```

⚠️ **Nominatim** exige un `User-Agent` identifiable avec un contact réel et
limite à 1 requête/seconde — les deux sont respectés par `src/Geocoder.php`.
Modifiez l'adresse e-mail dans la configuration avant tout usage régulier.

## Limites connues

- **athletisme.app ne publie que le calendrier belge.** `node scrape.js
  --country=FR` renvoie zéro résultat : le site est celui des fédérations
  belges. Le menu « Pays » de l'interface ne liste donc que les pays réellement
  présents en base, et disparaît quand il n'y en a qu'un. Cela n'empêche pas
  d'habiter en France : seule la source des compétitions est belge. Pour des
  compétitions françaises, il faudrait une seconde source (FFA).
- Quand plusieurs localités partagent un code postal (7700 = Mouscron et
  Luingne), la résolution automatique en choisit une arbitrairement — GeoNames
  n'indique pas laquelle est la principale. Passez par la liste de suggestions
  pour trancher ; l'écart est de quelques kilomètres.
- Les distances sont à vol d'oiseau. Pour du temps de trajet routier il faudrait
  un service d'itinéraires (OSRM, API Google Maps).
- Une compétition mise en avant en tête de calendrier n'a pas de date dans le
  listing ; elle est importée avec `start_date = NULL` et n'apparaît que si vous
  cochez « Inclure les compétitions passées ».
- Les fusions de communes belges (« Beveren-Kruibeke-Zwijndrecht ») sont
  géocodées sur la première commune du groupe si le nom composé est absent
  d'OpenStreetMap.

## Sources

Données : athletisme.app · Fonds de carte : OpenStreetMap · Géocodage : Nominatim
· Codes postaux : GeoNames (CC BY 4.0)
