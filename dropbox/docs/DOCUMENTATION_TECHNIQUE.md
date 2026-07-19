# PhotoSync — Documentation technique

> Référence interne couvrant **toute la chaîne technique** : architecture, base de données,
> API, **transfert/renvoi des fichiers** (Android ↔ serveur), sécurité, application Android,
> et **installation/déploiement**.
> Pour le mode d'emploi destiné aux utilisateurs, voir **`DOCUMENTATION_UTILISATION.md`**.

---

## Table des matières
1. [Architecture générale](#1-architecture-générale)
2. [Arborescence du projet](#2-arborescence-du-projet)
3. [Modèle de données (MySQL)](#3-modèle-de-données-mysql)
4. [API serveur (endpoints)](#4-api-serveur-endpoints)
5. [Renvoi des fichiers — flux d'upload de bout en bout](#5-renvoi-des-fichiers--flux-dupload-de-bout-en-bout)
6. [Stockage des fichiers sur le serveur](#6-stockage-des-fichiers-sur-le-serveur)
7. [Service des images & vignettes](#7-service-des-images--vignettes)
8. [Corbeille & cycle de vie d'une photo](#8-corbeille--cycle-de-vie-dune-photo)
9. [Authentification & comptes](#9-authentification--comptes)
10. [Sécurité](#10-sécurité)
11. [Application Android (interne)](#11-application-android-interne)
12. [Installation & déploiement du serveur](#12-installation--déploiement-du-serveur)
13. [Compilation de l'APK](#13-compilation-de-lapk)
14. [Configuration de référence](#14-configuration-de-référence)
15. [Dépannage technique](#15-dépannage-technique)

---

## 1. Architecture générale

PhotoSync est composé de deux moitiés qui communiquent en **HTTP/JSON + multipart** :

```
┌─────────────────────────┐         HTTPS          ┌──────────────────────────────┐
│   App Android (Kotlin)  │  ───────────────────►  │   Serveur PHP (mutualisé)    │
│                         │   multipart / JSON      │                              │
│  WorkManager (15 min)   │  ◄───────────────────   │  api/*.php  → JSON            │
│  OkHttp / Room / Coil   │      jeton X-Auth-Token  │  web/*.php  → pages HTML     │
└─────────────────────────┘                         │  uploads/   → fichiers       │
            ▲                                        │  MySQL      → métadonnées    │
            │                                        └──────────────────────────────┘
   Navigateur (iPhone/PC) ── session web (cookie) ──────────────┘
```

- **Serveur** : PHP 7.4+ avec PDO MySQL, sans framework. Endpoints regroupés par rôle
  (`api/` pour les appels machine, `web/` pour les pages navigateur, `lib/` pour le code commun).
- **Client Android** : Kotlin, `minSdk 26` / `targetSdk 35`. Sauvegarde en arrière-plan via
  **WorkManager**, réseau via **OkHttp**, base locale via **Room**, affichage via **Coil**.
- **Multi-comptes** : chaque requête de données est filtrée par `user_id`. Aucun mélange possible.
- **Authentification machine** : un **jeton** de 64 caractères hexadécimaux par compte,
  transmis dans l'en-tête `X-Auth-Token` (ou `?token=` pour les URL d'images).
- **Authentification web** : session PHP classique (cookie) après login.

---

## 2. Arborescence du projet

```
public_html/                 ← À DÉPOSER À LA RACINE du domaine
├── index.php                accueil → redirige vers web/gallery.php
├── install.php              assistant : configure la BDD, crée les tables (à supprimer après)
├── diag.php                 diagnostic serveur
├── favicon.svg / .user.ini  favicon + relèvement des limites PHP de l'hébergeur
├── lib/                     CODE COMMUN — jamais appelé directement par URL
│   ├── config.php               réglages app + gestion d'erreurs JSON ; lit db.config.php
│   ├── db.config.php            GÉNÉRÉ par install.php : identifiants MySQL (non versionné)
│   ├── bootstrap.php            charge config + définit TBL_USERS/TBL_PHOTOS + les 4 classes
│   ├── Db.php                   connexion PDO partagée (singleton)
│   ├── Api.php                  réponses JSON : Api::json(), Api::fail(), Api::header()
│   ├── Auth.php                 comptes : schéma, jetons, identification, session web
│   └── Photos.php               corbeille, vignettes, dates FR, classification par type,
│                                filtres/tri SQL, tailles lisibles, icônes SVG par catégorie
├── api/                     ENDPOINTS appelés par l'app Android (et le JS de la page web)
│   ├── register.php             création de compte (gardée par le code d'inscription)
│   ├── login.php                connexion → renvoie le jeton du compte
│   ├── setup.php                vérification de config au 1er lancement de l'app
│   ├── names.php                noms de fichiers déjà présents pour le compte (dédup rapide)
│   ├── check.php                quelles empreintes SHA-256 existent déjà (filet de sécurité)
│   ├── upload.php               RÉCEPTION d'une photo (multipart)
│   ├── feed.php                 liste JSON paginée des photos du compte
│   ├── media.php                sert une image / vignette au propriétaire
│   └── delete.php               met des photos à la corbeille
├── web/                     PAGES NAVIGATEUR
│   ├── gallery.php              galerie : login, corbeille, multi-sélection, nb/page,
│   │                            filtres Type/Origine, tri, vignettes par type
│   ├── view.php                 lecteur en ligne (vidéo/audio/image) — streaming via media.php
│   ├── upload_web.php           envoi manuel (iPhone / navigateur)
│   ├── register.php             inscription web
│   ├── admin.php                panneau admin (accès par le mot de passe BDD)
│   ├── maintenance.php          réconciliation base ↔ disque
│   └── list.php                 ancienne URL → redirige vers gallery.php
├── sql/db.sql               schéma SQL de référence
└── uploads/                 STOCKAGE des fichiers (accès HTTP direct interdit)
    └── .htaccess                Require all denied / php_flag engine off

android/                     PROJET ANDROID (Kotlin / Gradle)
└── app/src/main/java/com/example/photosync/
    ├── MainActivity.kt          écran principal, interrupteurs, déclenchement synchro
    ├── AuthActivity.kt          écran connexion / inscription
    ├── GalleryActivity.kt       galerie en ligne (Coil)
    ├── FullImageActivity.kt     affichage plein écran
    ├── CleanupActivity.kt       réconciliation « photos supprimées du téléphone »
    ├── SettingsStore.kt         persistance (SharedPreferences) + construction de l'URL
    ├── MediaScanner.kt          lecture MediaStore (images + vidéos + audio optionnel)
    ├── PhotoAdapter.kt          grille galerie : miniature (photos) ou icône+nom (autres types)
    ├── ApiClient.kt             couche réseau OkHttp (upload, feed, login, etc.)
    ├── UploadWorker.kt          WorkManager : scan + envoi en parallèle + notifications
    ├── AppDb.kt                 Room : table locale des photos déjà envoyées
    └── SyncApp.kt               Application (singleton Room)
```

---

## 3. Modèle de données (MySQL)

Les tables sont **préfixées** (`photosync_` par défaut, configurable). Elles peuvent donc
cohabiter avec d'autres applications dans une base partagée. Définition des noms dans
`lib/bootstrap.php` : `TBL_USERS = DB_PREFIX.'users'`, `TBL_PHOTOS = DB_PREFIX.'photos'`.

### `photosync_users` (créée par `Auth::ensureSchema()`)
| Colonne | Type | Rôle |
|---|---|---|
| `id` | INT UNSIGNED, PK, AUTO_INCREMENT | identifiant interne du compte |
| `username` | VARCHAR(64), UNIQUE | identifiant de connexion |
| `pass_hash` | VARCHAR(255) | hash `password_hash()` (bcrypt par défaut) |
| `api_token` | CHAR(64), UNIQUE | jeton machine (32 octets aléatoires en hex) |
| `created_at` | DATETIME, défaut CURRENT_TIMESTAMP | date de création |

### `photosync_photos` (créée par `install.php`, complétée par `ensureSchema()`)
| Colonne | Type | Rôle |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, AUTO_INCREMENT | identifiant de la photo |
| `user_id` | INT UNSIGNED, NULL, INDEX | propriétaire (NULL = orpheline, pré-comptes) |
| `sha256` | CHAR(64) | empreinte du contenu (déduplication) |
| `original_name` | VARCHAR(255) | nom de fichier d'origine |
| `stored_path` | VARCHAR(512) | chemin relatif sous `uploads/` |
| `size_bytes` | BIGINT UNSIGNED | taille |
| `taken_at` | DATETIME, NULL, INDEX | date de prise de vue (sinon NULL) |
| `uploaded_at` | DATETIME, défaut CURRENT_TIMESTAMP | date de réception |
| `deleted_at` | DATETIME, NULL, INDEX | si renseignée → en corbeille |

**Index clé** : `UNIQUE (user_id, sha256)` (`uniq_user_sha`). La déduplication est donc
**par compte** : deux comptes peuvent posséder la même photo, mais un compte ne peut pas
l'avoir deux fois. (L'ancien index global `sha256` est supprimé par `ensureSchema()` lors
de la migration.)

> `ensureSchema()` est **idempotent** : il ajoute colonnes/index manquants à chaque appel,
> ce qui permet la migration en douceur d'une base existante.

> **Catégorie de fichier (non stockée)** : le type (`photo` / `video` / `audio` /
> `document` / `other`) n'est **pas** une colonne. Il est **déduit à la lecture** par
> `Photos::categoryOf()` à partir de l'extension de `original_name` (et du chemin
> `…/videos/…` pour les vidéos, du MIME réel en dernier recours). Le **filtrage** et le
> **tri** par type se font donc en SQL sur l'extension (`SUBSTRING_INDEX`) — voir §7.1.
> `size_bytes`, déjà présent, sert au tri « Taille » et à l'affichage de la taille lisible.

---

## 4. API serveur (endpoints)

Tous les endpoints `api/*` incluent `lib/bootstrap.php`, appellent `Api::header()`
(CORS + `Content-Type: application/json`) et répondent en JSON via `Api::json()` /
`Api::fail($msg, $httpCode)`. L'authentification machine se fait via
`Auth::userIdFromToken()` (en-tête `X-Auth-Token`, ou `?token=`, ou `token` POST).

| Endpoint | Méthode | Auth | Entrée | Sortie |
|---|---|---|---|---|
| `register.php` | POST | code d'inscription (`X-Auth-Token` = mot de passe BDD) | `username`, `password` | `{ok, token, username}` |
| `login.php` | POST | identifiants | `username`, `password` | `{ok, token, username}` |
| `setup.php` | POST | jeton | — | `{ok, user_id, space, schema}` |
| `names.php` | GET | jeton | — | `{ok, names:[…]}` |
| `check.php` | POST | jeton | `{hashes:[sha256…]}` | `{ok, exists:[sha256…]}` |
| `upload.php` | POST | jeton **ou** session web | multipart `photo` + `taken_at` | `{ok, duplicate, path}` |
| `feed.php` | GET | jeton | `?page=N[&type=…][&sort=…]` | `{ok, total, page, pages, perPage, photos:[…]}` |
| `media.php` | GET | jeton/session/admin | `?id=N[&thumb=1]` | flux binaire image, **ou icône SVG** (non-image) |
| `delete.php` | POST | jeton/session | `{ids:[…]}` | met à la corbeille |

**`feed.php` — filtrage & tri** :
- `type` ∈ `all` (défaut) `photo` `video` `audio` `document` `other` → ajoute la condition
  SQL `Photos::categoryCondition($type)` (le **comptage** et la **pagination** restent donc justes).
- `sort` ∈ `date_desc` (défaut) `date_asc` `name_asc` `name_desc` `size_desc` `size_asc` `type`
  → `Photos::sortClause($sort)` (clause `ORDER BY` en **liste blanche**, pas d'injection).
- Chaque élément renvoyé contient désormais : `id, name, date, video(bool), category, size, source`.

**Codes d'erreur usuels** : `400` requête invalide · `401` jeton/compte invalide ·
`404` introuvable · `405` méthode non autorisée · `413` fichier trop volumineux ·
`500` erreur serveur (renvoyée en JSON lisible grâce au `set_exception_handler` de `config.php`).

### Détail `setup.php` (vérification au 1er lancement)
Appelé **une fois par compte** par l'app. Il : (1) confirme le jeton, (2) garantit le schéma
par compte (`ensureSchema()`), (3) crée et teste en écriture l'espace `uploads/<user_id>/`
(écrit puis supprime un fichier `.write_test`). Permet à l'app de signaler tôt un problème de
droits ou de configuration, sans toucher aux autres membres.

---

## 5. Renvoi des fichiers — flux d'upload de bout en bout

C'est le cœur du système. Voici le parcours complet d'une photo, du téléphone au disque serveur.

### 5.1 Côté Android — `UploadWorker.doWork()`

```
1. isLoggedIn ?  ──non──► échec « Compte non connecté »
2. (1ʳᵉ fois pour ce compte) verifySetup() :
      - permission photos accordée ? sinon échec explicite
      - api/setup.php OK ? (compte + schéma + espace inscriptible)
      - mémorise setupVerifiedToken = token (ne se refait plus)
3. SCAN local : MediaScanner.queryImages() → images + vidéos (MediaStore),
   triées du plus ancien au plus récent.
4. FILTRE local : retire les ids déjà présents dans Room (uploadedDao).
5. PHASE_VERIFY : api/names.php → ensemble des NOMS déjà sur le serveur (1 requête).
      - chaque photo locale dont le nom (minuscule) est déjà présent → marquée
        « envoyée » dans Room et comptée en "skipped" (pas de réenvoi).
6. PHASE_UPLOAD : envoi EN PARALLÈLE (Semaphore = 4 simultanés) des restantes,
   limité éventuellement par maxPerSync.
      pour chaque photo :
        api.upload(photo)  →  POST multipart vers api/upload.php
        succès → dao.markUploaded(id) immédiat (reprise possible) + progression
        échec  → compteur d'échecs ; si 0 réussie et ≥3 échecs → abandon (serveur KO)
7. BILAN : notification « ✅ X sauvegardée(s) ». Si que des échecs → Result.retry().
```

Points importants :
- **Deux niveaux d'anti-doublon** : (a) base **Room** locale (id MediaStore) pour ne pas
  rescanner ; (b) **noms serveur** via `names.php` pour ignorer ce qui est déjà en ligne.
  Le **SHA-256** (`check.php`) reste un filet de sécurité côté serveur (voir 5.3).
- **Parallélisme** : `PARALLEL = 4` envois simultanés (compromis vitesse / charge réseau).
- **Reprise** : chaque succès est noté tout de suite ; une interruption ne refait pas le travail déjà fait.
- **Limite par passage** : `maxPerSync` (0 = illimité) évite de saturer une synchro.

### 5.2 Côté Android — `ApiClient.upload()` (la requête réseau)

```kotlin
// content:// n'est pas un fichier : copie d'abord dans le cache local
val tmp = File.createTempFile("up_", ".bin", context.cacheDir)
contentResolver.openInputStream(photo.uri).copyTo(tmp)

val body = MultipartBody.Builder().setType(FORM)
    .addFormDataPart("taken_at", photo.dateTakenMs.toString())   // ms depuis epoch
    .addFormDataPart("photo", photo.name, tmp.asRequestBody(mime))
    .build()

POST {base}/upload.php
   header X-Auth-Token: <jeton du compte>
   → 2xx : succès
   → 401/404/413 : messages dédiés ; autre : « HTTP code — message serveur »
finally { tmp.delete() }   // le fichier temporaire est toujours nettoyé
```

Timeouts OkHttp adaptés aux gros médias : **connect 30 s**, **write 20 min**, **read 5 min**.
Le `mime` réel est lu via `contentResolver.getType()` (images **et** vidéos).

### 5.3 Côté serveur — `api/upload.php` (la réception)

```
1. POST obligatoire (sinon 405).
2. uid = Auth::currentUserId()  (jeton OU session web) ; sinon 401.
3. $_FILES['photo'] présent et UPLOAD_ERR_OK ? sinon 400.
4. Contrôles : taille > 0 (sinon 400) ; MAX_BYTES (0 = illimité, sinon 413) ;
   is_uploaded_file() (sinon 400, anti-injection).
5. sha = hash_file('sha256', tmp).
6. DÉDUPLICATION : SELECT stored_path WHERE user_id = uid AND sha256 = sha.
      trouvé → réponse {ok:true, duplicate:true, path} SANS réécrire le fichier.
7. RANGEMENT : sous-dossier uploads/<uid>/<AAAA>/<MM>/ (d'après taken_at, sinon date du jour).
      nom final = <12 premiers caractères du sha>_<nom assaini>.
      (assainissement : [^A-Za-z0-9._-] → "_")
8. move_uploaded_file(tmp, dest) ; sinon 500.
9. INSERT (user_id, sha256, original_name, stored_path, size_bytes, taken_at).
10. réponse 201 {ok:true, duplicate:false, path:<chemin relatif>}.
```

Ainsi, même si l'app envoyait par erreur un doublon, le serveur le **détecte par contenu**
(SHA-256) et ne stocke pas deux fois — c'est le filet de sécurité ultime mentionné en 5.1.

### 5.4 Schéma de séquence (résumé)

```
App                         Serveur
 │  (1er lancement) POST setup.php ─────────────►  vérifie compte+schéma+espace
 │  ◄──────────────────────── {ok:true}
 │  GET names.php ─────────────────────────────►  SELECT original_name WHERE user_id
 │  ◄──────────── {names:[…]}   (ignore par nom)
 │  POST upload.php (×N en //) ────────────────►  sha256 + dédup + range + INSERT
 │  ◄──────────── {ok, duplicate, path}
 │  notification « ✅ X sauvegardée(s) »
```

---

## 6. Stockage des fichiers sur le serveur

- **Racine** : `UPLOAD_DIR = lib/../uploads` (donc `public_html/uploads/`).
- **Par membre & par date** : `uploads/<user_id>/<année>/<mois>/<sha12>_<nom>`.
  Exemple : `uploads/3/2026/06/a1b2c3d4e5f6_IMG_2026.jpg`.
- **Corbeille** : `uploads/.corbeille/<même chemin relatif>` (les fichiers supprimés y sont
  **déplacés**, pas effacés tout de suite).
- **Vignettes** : `uploads/.thumbs/<id>.jpg` (cache régénéré si l'original est plus récent).
- Le chemin relatif stocké en base (`stored_path`) est commun aux dossiers principal et corbeille
  (seul le préfixe de base change selon `deleted_at`).

> Le dossier `uploads/` doit être **inscriptible** par PHP. `setup.php` et `upload.php`
> créent les sous-dossiers à la volée (`mkdir 0775` récursif).

---

## 7. Service des images & vignettes

`api/media.php?id=N[&thumb=1]` — sert le binaire, **uniquement au propriétaire** (ou à
l'admin via session `admin_ok`). Logique :

1. Auth : session web (`uid`) **ou** jeton (`?token=`/`X-Auth-Token`) ; admin = voit tout.
2. `SELECT user_id, stored_path, deleted_at` filtré par `id` (+ `user_id` si non-admin).
3. Choix du dossier : `UPLOAD_DIR` si actif, `trashDir()` si en corbeille.
4. **Anti-traversal** : `realpath()` du fichier doit commencer par `realpath(UPLOAD_DIR)`.
5. **Auto-nettoyage** : fichier disparu → `Photos::deleteForever()` puis 404 (plus jamais d'image cassée).
6. **Vignette** (`thumb=1`) :
   - image → cache JPEG via GD (`makeThumb`, 500 px max sur le grand côté, qualité 82) —
     **format réduit** qui évite de transférer l'original pour l'aperçu ;
   - non-image (vidéo / audio / document / autre) → **icône SVG** légère adaptée au type
     via `Photos::iconSvg(Photos::categoryOf(...))` (note de musique, document, ▶ vidéo…).
7. En-têtes : `Content-Type` réel, `Cache-Control: private, max-age=86400`, `Content-Length`,
   puis `readfile()`.

Les URL utilisées par l'app sont construites dans `ApiClient` :
`…/api/media.php?id=N&token=<jeton>` (et `&thumb=1` pour la vignette).

### 7.1 Catégories, filtres et tri (code partagé `lib/Photos.php`)

Le même code sert l'app **et** le web :

| Méthode | Rôle |
|---|---|
| `categoryExtensions()` | listes d'extensions connues par catégorie (source unique de vérité) |
| `categoryOf($name,$storedPath,$mime)` | déduit la catégorie d'un fichier (dossier `videos/` > extension > MIME) |
| `categoryCondition($cat)` | fragment SQL **sans paramètre** (valeurs constantes inlinées) pour filtrer une catégorie ; compatible avec les paramètres nommés `:uid/:lim/:off` |
| `sortClause($sort)` | clause `ORDER BY` en liste blanche (date/nom/taille/type) |
| `humanSize($bytes)` | octets → « 1,4 Mo », « 820 Ko » |
| `categoryLabel($cat)` | libellé court avec emoji (🖼️ Photo, 🎬 Vidéo…) |
| `iconSvg($cat)` | icône SVG de vignette pour les fichiers non-image |

> Le filtrage par type est fait **en base** (et non après lecture) pour que `COUNT(*)`,
> la pagination et le tri restent cohérents. Les extensions étant des **constantes du code**
> (pas une entrée utilisateur), elles sont inlinées sans risque d'injection.

### 7.2 Lecture en ligne — `web/view.php`

`view.php?id=N` lit le fichier **dans le navigateur** (au lieu de le télécharger) :
- auth par **session web** (sinon redirection vers `gallery.php`) ; l'admin voit tout ;
- récupère `original_name` + `stored_path`, déduit la catégorie, puis rend :
  - `video` → `<video controls autoplay>` (streaming `media.php` avec **HTTP Range** → avance fluide) ;
  - `audio` → `<audio controls>` ; `photo` → `<img>` ; **document/autre** → lien de téléchargement.
- En-tête commun : bouton **Retour** + **Télécharger**. La galerie web pointe vers `view.php`
  pour les médias lisibles, et directement vers `media.php` (attribut `download`) pour les documents.

---

## 8. Corbeille & cycle de vie d'une photo

Géré par `lib/Photos.php`, toujours **scopé au compte** (`$uid`) :

- `trash($id,$uid)` : déplace le fichier vers `.corbeille/`, supprime la vignette,
  met `deleted_at = NOW()`.
- `restore($id,$uid)` : déplace en sens inverse, remet `deleted_at = NULL`.
- `deleteForever($id,$uid)` : supprime fichier (principal **et** corbeille) + vignette + ligne BD.
- `purgeOldTrash($uid)` : supprime définitivement ce qui est en corbeille depuis plus de
  **`TRASH_DAYS = 30`** jours.
- `fileExists($row)` : permet l'auto-nettoyage des entrées dont le fichier a disparu
  (appelé par `feed.php` et `media.php`).

`frDate()` formate les dates SQL en français court (« 7 juin 2026 · 14:05 »).

---

## 9. Authentification & comptes

Classe `lib/Auth.php`.

- **Création** (`createAccount`) : valide l'identifiant (`^[A-Za-z0-9_.-]{3,64}$`) et le mot
  de passe (≥ 4 car.), refuse les doublons (409), insère avec `password_hash()` et un
  `api_token` aléatoire (`bin2hex(random_bytes(32))`). **Le 1ᵉʳ compte** créé adopte les
  photos orphelines (`user_id IS NULL`).
- **Jeton machine** (`userIdFromToken`) : lit `X-Auth-Token` / `?token=` / POST `token`
  (≥ 32 car.), retrouve le compte par `api_token`.
- **Compte courant** (`currentUserId`) : session web (`$_SESSION['uid']`) en priorité,
  sinon jeton app — c'est ce qui permet à `upload.php`/`media.php` de servir aussi le navigateur.
- **Vérif identifiants** (`verifyCredentials`) : `password_verify()`.
- **Session web** (`webSession`) : démarre la session, gère logout, traite le POST de login
  avec `session_regenerate_id(true)` (anti fixation), renvoie `{uid, uname, error}`.

> **Code d'inscription** : la création de compte (app **et** web) est gardée par le
> **mot de passe de la base de données** (`DB_PASS`), transmis par l'app dans `X-Auth-Token`
> à `register.php`. L'admin (`web/admin.php`) utilise le même secret. Plus de code séparé.

---

## 10. Sécurité

- **Fichiers privés** : `uploads/.htaccess` (`Require all denied`, `php_flag engine off`,
  `Options -Indexes`) interdit l'accès HTTP direct. Tout passe par `media.php`, qui vérifie le compte.
- **Anti-traversal** : `media.php` valide via `realpath()` que le fichier reste sous `uploads/`.
- **Cloisonnement par compte** : toutes les requêtes filtrent par `user_id` ; index unique
  `(user_id, sha256)`. Aucun mélange entre comptes.
- **Mots de passe** : `password_hash()` / `password_verify()` (bcrypt), comparaison en temps constant.
- **Upload** : `is_uploaded_file()` + `move_uploaded_file()`, nom de fichier assaini,
  taille bornée par `MAX_BYTES` (configurable).
- **Sessions web** : régénération d'ID à la connexion.
- **Erreurs** : `set_exception_handler` renvoie un JSON `{ok:false, error:…}` (pas de page 500 muette).
- **À faire après installation** : **supprimer `install.php`** (non protégé par mot de passe).

> Pistes connues non encore implémentées : anti-bruteforce/limite de débit sur `login.php`,
> rotation de jeton / mot de passe oublié, HTTPS forcé.

---

## 11. Application Android (interne)

### Pile technique (`app/build.gradle.kts`)
`applicationId com.example.photosync` · `minSdk 26` · `targetSdk/compileSdk 35` · Java 17.
Dépendances : WorkManager `2.9.1`, OkHttp `4.12.0`, Coil `2.7.0`, Room `2.6.1`
(via KSP), coroutines `1.8.1`, Material `1.12.0`, RecyclerView `1.3.2`.

### Permissions (`AndroidManifest.xml`)
`INTERNET`, `ACCESS_NETWORK_STATE`, `READ_MEDIA_IMAGES` + `READ_MEDIA_VIDEO` +
**`READ_MEDIA_AUDIO`** (Android 13+), `READ_EXTERNAL_STORAGE` (`maxSdkVersion=32`),
`POST_NOTIFICATIONS`. Les permissions média **réellement demandées** dépendent des types
cochés par l'utilisateur (voir « Types de fichiers à synchroniser » ci-dessous).

### Composants
- **`MediaScanner`** : lit `MediaStore.Images`, `MediaStore.Video` **et** (optionnel)
  `MediaStore.Audio` selon les types activés —
  `queryImages(context, photos=true, videos=true, audio=false)`. Colonnes
  (`_ID`, `DISPLAY_NAME`, `DATE_TAKEN`, `DATE_ADDED`, `SIZE`) ; `DATE_TAKEN` est **facultative**
  (absente pour l'audio → repli sur `DATE_ADDED`). Trie par date croissante, expose des
  `LocalPhoto(id, uri, name, dateTakenMs, size)`.
  > ⚠️ Limite Android : les **documents** (PDF, zip…) ne sont pas accessibles via MediaStore
  > sans accès étendu ; la synchro depuis le téléphone couvre **photos / vidéos / audio**.
  > Les documents s'ajoutent depuis le **web** (`upload_web.php`, bouton « Ordinateur »).
- **`ApiClient`** : toute la couche réseau OkHttp — `upload`, `fetchNames`, `checkExisting`,
  `fetchPhotos`/`fetchAllPhotos` (feed paginé), `deletePhotos`, `verifySetup`, `login`,
  `register`, `sha256` (même algo que le serveur), URLs `thumbUrl`/`fullUrl`.
- **`UploadWorker`** (`CoroutineWorker`) : la logique de synchro (voir §5.1). Planification :
  - `schedulePeriodic` : `PeriodicWorkRequest` **15 min**, contrainte réseau
    (`UNMETERED` si Wi-Fi only, sinon `CONNECTED`), backoff exponentiel, `enqueueUniquePeriodicWork(UPDATE)`.
  - `runNow` : `OneTimeWorkRequest` unique (`REPLACE`).
  - `cancelPeriodic` / `cancelAll`.
  - Progression publiée via `setProgress` (`KEY_PHASE`, `KEY_DONE`, `KEY_TOTAL`, …) pour l'UI temps réel.
- **`AppDb` (Room)** : table locale des `UploadedPhoto(id, uploadedAt)` → savoir ce qui est
  déjà parti sans réinterroger le serveur. **Vidée à la déconnexion** (`logout`).
- **`SettingsStore`** (SharedPreferences `photosync`) : `serverUrl`, `domain`, `subPath`,
  `token`, `username`, `wifiOnly`, `autoSync`, `watchGallery`, `maxPerSync`, `setupVerifiedToken`,
  et les **types à synchroniser** `uploadPhotos` (défaut on), `uploadVideos` (défaut on),
  `uploadAudio` (défaut off). `UploadWorker` ne scanne que les types activés et `MainActivity`
  ne demande que leurs permissions ; si aucun type n'est coché, la synchro ne fait rien.
  - `buildServerUrl(domain, sub)` : normalise l'URL — tolère un schéma collé, des `/` en trop,
    et récupère un chemin mis dans le domaine comme sous-dossier.
    `("luvumbu.com","")` → `https://luvumbu.com` ; `("luvumbu.com","apk")` → `https://luvumbu.com/apk`.
    L'app appelle ensuite les endpoints sous `{serverUrl}/api/…`.

### Galerie de l'app, catégories & tri
- **`GalleryActivity`** : grille `GridLayoutManager(3)`. Une barre d'**onglets de catégories**
  (`ChipGroup` à sélection unique : Tout / Photos / Vidéos / Musique / Documents / Autres) et un
  **bouton de tri** (`PopupMenu` : date, nom, taille, type). Tout changement relance
  `fetchPhotos(page, type, sort)` depuis la page 1 (le filtrage/tri est fait **côté serveur**).
- **`ApiClient.fetchPhotos(page, type, sort)`** ajoute `&type=&sort=` à `feed.php` et parse
  les nouveaux champs ; `ServerPhoto(id, name, date, category, video, size)`.
- **`PhotoAdapter`** : pour une **photo**, charge la **miniature réduite** (Coil) + un petit
  badge « appareil photo » ; pour les **autres types**, affiche une **icône vectorielle locale**
  (`ic_cat_video/audio/document/file`) + le nom, **sans télécharger d'aperçu lourd**.
- **Ouverture** : une photo s'ouvre dans `FullImageActivity` ; les autres types via
  `ACTION_VIEW` (lecteur/visionneuse externe) — équivalent mobile de `view.php`.

### Thème & design (épuré)
- Thème **Material 3 sombre** (`Theme.Material3.Dark.NoActionBar`) avec une **palette unique**
  (`colors.xml` : fond bleu nuit, un seul accent orange, bordures discrètes) et un style de
  **carte sobre** `Card.Epure` (coins 18 dp, sans ombre).
- **Accueil** organisé en **sections titrées dans des cartes** (Synchronisation, Types de
  fichiers, Options, Activité) ; hiérarchie de boutons (plein / tonal / contour / texte).
- **Vignettes** de galerie en **`MaterialCardView`** arrondies (16 dp), badges discrets.

---

## 12. Installation & déploiement du serveur

### Prérequis hébergement
- PHP **7.4+** avec extensions **`pdo_mysql`** et **`gd`** (vignettes).
- Une base **MySQL** + un utilisateur.
- Accès FTP / Gestionnaire de fichiers.

### Étapes
1. **Base de données** : dans le panneau de l'hébergeur (ex. hPanel Hostinger →
   *Bases de données MySQL*), créer la base et l'utilisateur. Noter **nom de base / utilisateur /
   mot de passe**. *(L'assistant peut aussi créer la base lui-même si l'hébergeur l'autorise.)*
2. **Envoyer les fichiers** : déposer le **contenu** de `public_html/` (donc `index.php`,
   `install.php`, `lib/`, `api/`, `web/`, `sql/`, `uploads/`) **directement à la racine**
   `public_html/` de l'hébergement → on doit obtenir `public_html/api/`, `public_html/lib/`, etc.
   ⚠️ Ne pas envoyer le *dossier* `public_html` lui-même, mais ce qu'il contient.
3. **Assistant** : ouvrir `https://luvumbu.com/install.php`.
   - Au 1ᵉʳ accès, le serveur détecte qu'il n'est pas configuré → **formulaire** :
     hôte / nom de base / utilisateur / mot de passe / préfixe (défaut `photosync_`).
   - *Enregistrer et vérifier* → l'assistant **écrit `lib/db.config.php`**, **crée les tables**,
     crée `uploads/` + son `.htaccess`, et affiche un **rapport** (vert si tout est OK).
   - Si la base n'existe pas, l'assistant **tente de la créer** (`CREATE DATABASE IF NOT EXISTS`).
4. **Sécuriser** : **supprimer `install.php`** du serveur. La config reste dans `db.config.php`.
5. **Régler `config.php`** si besoin : `GALLERY_PASSWORD`, `MAX_BYTES` (0 = illimité).
   Les identifiants MySQL n'y sont **pas** : ils vivent dans `db.config.php` (généré).

### Ce que `install.php` vérifie (rapport)
Version PHP ≥ 7.4 · extension `pdo_mysql` · connexion MySQL · tables `…users`/`…photos` créées ·
dossier `uploads/` présent et inscriptible · `uploads/.htaccess` en place ·
limites `upload_max_filesize` / `post_max_size` (informatif, relevées par `.user.ini`).

### Test en local (XAMPP)
Hôte `localhost`, utilisateur `root`, mot de passe vide. Ouvrir
`http://localhost/LUVUMBU/public_html/install.php` (adapter selon l'emplacement).

---

## 13. Compilation de l'APK

Environnement de dev (machine de référence) :
- JDK : `C:\Program Files\Android\Android Studio\jbr` (Java 21).
- SDK : `%LOCALAPPDATA%\Android\Sdk` (plateforme `android-35`, build-tools `35.0.0`).
- Gradle **8.14.3** (compatible AGP 8.7.2) ; Kotlin 2.0.20 ; compile/target SDK 35 ; minSdk 26.

> Le dépôt `android/` n'embarque pas le wrapper `gradlew`. Sur la machine de référence, le
> binaire Gradle d'Android Studio est utilisé directement, p. ex. :
> `…\.gradle\wrapper\dists\gradle-8.14.3-all\<hash>\gradle-8.14.3\bin\gradle.bat`
> avec `JAVA_HOME` pointant sur le JBR d'Android Studio.

```bash
# depuis android/, avec JAVA_HOME et ANDROID_HOME définis
gradle assembleDebug --no-daemon
# sortie : android/app/build/outputs/apk/debug/app-debug.apk → copié en PhotoSync.apk
```

C'est un **APK de debug** (signé clé debug, installable directement). Pour le Play Store, il
faudrait un APK **release** signé avec une clé de production.

**Alternative cloud** : `.github/workflows/android.yml` compile l'APK sur **GitHub Actions**
(artifact téléchargeable) sans rien installer en local.

---

## 14. Configuration de référence

### `lib/config.php` (réglages applicatifs)
| Constante | Défaut | Rôle |
|---|---|---|
| `GALLERY_PASSWORD` | `luvumbu2026` | mot de passe legacy de la galerie web — **à changer** |
| `UPLOAD_DIR` | `lib/../uploads` | dossier de stockage |
| `MAX_BYTES` | `0` | taille max par fichier (0 = illimité) |
| `DB_HOST/NAME/USER/PASS` | depuis `db.config.php` | identifiants MySQL |
| `DB_PREFIX` | `photosync_` | préfixe des tables |

### `lib/db.config.php` (généré par `install.php`, non versionné)
Tableau PHP `['host','name','user','pass','prefix']`. À **régénérer** en relançant `install.php`.

### Côté Android (`SettingsStore`)
`DEFAULT_DOMAIN = "luvumbu.com"` · `DEFAULT_SUBPATH = "dropbox"` · `DEFAULT_URL = "https://luvumbu.com/dropbox"`.

> **Cohérence des secrets** : le **code d'inscription** saisi dans l'app doit être égal au
> **mot de passe de la base de données** (`DB_PASS`), puisque `register.php` valide l'inscription
> contre ce secret.

---

## 15. Dépannage technique

| Symptôme | Cause probable | Piste |
|---|---|---|
| `install.php` : « connexion à la base impossible » | identifiants MySQL erronés / `db.config.php` absent | reconfigurer via le formulaire (`?reconfig=1`) |
| `500` JSON « Serveur: … » | exception PHP (BDD, droits) | lire le message (renvoyé par `set_exception_handler`) ; vérifier `pdo_mysql` |
| Upload `401` côté app | jeton invalide / compte changé | se reconnecter ; vérifier `X-Auth-Token` |
| Upload `413` | fichier > `MAX_BYTES`, ou limites PHP de l'hébergeur | `MAX_BYTES = 0` + vérifier `.user.ini` (`upload_max_filesize`, `post_max_size`) |
| « Espace du membre non inscriptible » (`setup.php`) | droits de `uploads/` | `chmod 775` sur `uploads/` |
| Images cassées dans la galerie | fichier disparu du disque | auto-nettoyé par `feed.php`/`media.php` (entrée supprimée) ; voir `web/maintenance.php` |
| Vignettes absentes | extension **GD** manquante | activer `gd` côté serveur |
| Synchro auto qui ne part pas | restriction batterie / réseau | batterie « sans restriction » ; décocher Wi-Fi only ou être en Wi-Fi |
| Doublons | — | impossibles : dédup par `(user_id, sha256)` côté serveur |
| `web/maintenance.php` | entrées sans fichier / fichiers sans entrée | réconcilier base ↔ disque |
| `diag.php` | besoin d'un état serveur | page de diagnostic dédiée |
```
