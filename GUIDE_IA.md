# 🤖 GUIDE IA — Tout ce que l'on peut faire sur le projet Luvumbu

> **But de ce document :** permettre à une IA (ou à un humain) de comprendre en une lecture
> l'architecture du site, **comment le gérer à distance**, les procédures types et les pièges.
> Si tu es une IA qui reprend ce projet : **lis ce fichier en entier avant d'agir.**

- **Site en production :** https://luvumbu.com
- **Dépôt local (source de vérité) :** `C:\xampp\htdocs\luvumbu` (XAMPP, Windows)
- **Réalisation phare :** https://bokonzi.com

---

## 1. 🚀 Capacité clé — GÉRER LE SITE EN LIGNE À DISTANCE (API `_gestion`)

Le site en ligne expose une **API de gestion de fichiers** sécurisée. C'est **le** moyen de
lire/modifier/déployer des fichiers sur `luvumbu.com` sans FTP.

- **Endpoint :** `https://luvumbu.com/_gestion/api.php`
- **Interface web :** `https://luvumbu.com/_gestion/` (login + explorateur)
- **Authentification par programme :** en-tête HTTP `X-Api-Key: <clé>`
  - La clé est stockée **sur le serveur** dans `_gestion/apikey.local.php` (jamais sur Git).
  - **Où trouver / gérer la clé :** `luvumbu.com/admin.php` → encart **🔑 Accès API distant** →
    boutons **Voir la clé active / Régénérer / Révoquer**.
  - ⚠️ Ne JAMAIS écrire la clé en clair dans un fichier versionné. La récupérer depuis l'admin au besoin.

### Actions de l'API (JSON)
| Action | Méthode | Paramètres | Effet |
|--------|---------|------------|-------|
| `list`     | GET  | `path` (dossier) | liste un dossier |
| `read`     | GET  | `path` (fichier texte) | renvoie le contenu |
| `download` | GET  | `path` | télécharge (binaire) |
| `save`     | POST | `path`, `content` | **écrit un fichier EXISTANT** |
| `upload`   | POST | `path`, `files[]` (multipart) | envoi de fichiers |
| `mkdir`    | POST | `path`, `name` | nouveau dossier |
| `newfile`  | POST | `path`, `name` | **nouveau fichier vide** |
| `rename`   | POST | `path`, `name` | renommer / déplacer |
| `delete`   | POST | `path` | supprimer (récursif) |

- `path` est **relatif à la racine du site** (`""` = racine, `"cv_luvumbu/includes"` = sous-dossier).
- Auth par clé → **pas de cookie ni CSRF requis** (le CSRF ne concerne que la session navigateur).
- Confinement strict : impossible de sortir de la racine du site (anti path-traversal `realpath`).

### ⚠️ Piège n°1 — créer un fichier neuf
`save` **exige que le fichier existe déjà** (sinon → « Introuvable »). Pour créer un NOUVEAU fichier :
1. `newfile` (crée le fichier vide) 2. puis `save` (écrit le contenu).

### Recette : déployer un fichier local vers le serveur (exemple curl)
```bash
KEY="<clé récupérée dans admin.php>"
B="https://luvumbu.com/_gestion"
# fichier déjà existant en ligne :
curl -s -H "X-Api-Key: $KEY" "$B/api.php?action=save" \
  --data-urlencode "path=inc/carte.php" --data-urlencode "content@inc/carte.php"
# fichier NOUVEAU : d'abord le créer
curl -s -H "X-Api-Key: $KEY" "$B/api.php?action=newfile" -d "path=" -d "name=nouveau.php"
```

### ⚠️ Piège n°2 — `curl.exe` sous Windows/Git Bash
`curl -F "files[]=@/tmp/x"` échoue (`HTTP 000`) car `curl.exe` ne comprend pas les chemins `/tmp`.
Utiliser un chemin **relatif au dossier courant** ou un chemin Windows réel.

### ⚠️ Piège n°3 — lire une réponse JSON avec PHP
Le PHP de Windows ne lit pas `/tmp/...`. Piper directement : `curl ... | php -r '...php://stdin...'`.

---

## 2. 🐢 Piège MAJEUR — le CACHE (LiteSpeed / Hostinger)

Le serveur envoie `Cache-Control: max-age=604800` (**7 jours**) sur les fichiers statiques (`.css`, `.js`).
**Conséquence :** après avoir modifié `css/*.css` ou `js/*.js`, le navigateur ressert l'ANCIENNE version.

**Règle absolue :** après TOUTE modif d'un `.css` ou `.js`, **incrémenter le `?v=N`** dans `index.php` :
```html
<link rel="stylesheet" href="css/carte.css?v=11">   <!-- passer à ?v=12 -->
<script src="js/carte.js?v=15"></script>            <!-- passer à ?v=16 -->
```
Puis redéployer `index.php`. Sans ça, « rien ne change » à l'écran même si le serveur a le bon fichier.

**Pour vérifier/tester sans cache :** ouvrir une URL fraîche (`https://luvumbu.com/?x=123`) ou
récupérer le fichier avec la MÊME URL que le navigateur (`.../carte.js?v=15`) pour confirmer le contenu.

---

## 3. 🗂️ Structure du dépôt

```
luvumbu/  (racine = luvumbu.com)
├── index.php              Portfolio (page d'accueil) — inclut inc/carte.php
├── admin.php              Espace admin (apparence, projets, mondes, clé API, gestionnaire)
├── contact.php
├── _gestion/             ⚙️ API de gestion de fichiers à distance (voir §1)
│   ├── api.php · index.php · lib.php · .htaccess · README.txt
│   └── apikey.local.php   (secret, gitignoré) · password.local.php (optionnel)
├── config/
│   ├── portfolio.php      SOURCE DE VÉRITÉ du contenu (gros tableau retourné)
│   ├── appearance.json    Surcharges d'apparence écrites par admin.php
│   ├── projets.json       Point d'entrée par dossier { "dossier": "sous-chemin" }
│   └── projets_meta.json  Habillage par projet { "dossier": {icon,nom,img,desc,ordre} }
├── inc/carte.php          Génère la carte des projets (scan + tri + data-attributs)
├── js/carte.js            Rendu carte : mondes, apparence, nœuds
├── css/carte.css          Styles carte (dont thèmes/apparences)
├── images/projets/        Images uploadées par projet
└── [dossiers-projets]     anniversaire, bokonzi, cv_luvumbu, dropbox, DualCam,
                           ELECTRONIQUE, rpn, tamagotchi, ztransfert, articles,
                           Cours_complet_canvas, softwaire_programes, puissance4
```

### ⚠️ Différences LOCAL vs EN LIGNE
- En ligne, le dossier `ztransfert` s'appelle **`zt`** (clé dupliquée dans `projets_meta.json`).
- `puissance4` **n'est pas déployé** en ligne (existe seulement en local).
- Le CODE en ligne a parfois été **plus ancien** que le local. Toujours vérifier que le fichier
  déployé correspond au local avant de conclure qu'un réglage « ne marche pas ».

---

## 4. 🎮 La carte des projets (portfolio) — comment ça marche

`index.php` → inclut `inc/carte.php` → lit `config/portfolio.php` (`$CFG['carte']`) + surcharges.
La carte affiche 1 projet = 1 nœud, façon jeu rétro. Réglages via `admin.php`.

### Sources de données (fusion)
1. `config/portfolio.php` → `carte.meta[dossier]` = habillage par défaut + `carte.source='scan'`, `carte.exclude`.
2. `config/projets_meta.json` → surcharge par projet : `icon, nom, img, desc, ordre` (écrit par admin).
3. `config/projets.json` → point d'entrée (URL) par dossier.
4. `config/appearance.json` → réglages globaux (apparence, mondes) écrits par admin.

Fusion dans `inc/carte.php` : `array_merge(meta_portfolio, array_filter(override_json))`
(les valeurs vides de l'override sont ignorées).

### Fonctionnalités de la carte (toutes réglables dans admin.php)
| Fonction | Réglage admin | Stocké dans | Lu par |
|----------|---------------|-------------|--------|
| **Mondes** (WORLD 1/2/3, façon Mario) | 🗺️ Mondes : zones/monde + noms | `appearance.json` `world_size`, `world_names` | `carte.js` (`data-world-*`) |
| **Apparence** (Défaut/Plaine/Désert/…) | 🎨 Apparence de la carte | `appearance.json` `carte_apparence` | `carte.js` (`data-appearance` → classe `.mario.wt-N`) |
| **Position** (n° de chaque projet) | 🔢 Position (select par projet) | `projets_meta.json` `ordre` | `inc/carte.php` (usort) |
| **Habillage** (icône/nom/image/desc) | éditeur par projet | `projets_meta.json` | `inc/carte.php` |
| **Point d'entrée** (page au clic) | 🎯 Page d'entrée | `projets.json` | `inc/carte.php` |

- **Apparence = décision ADMIN**, pas visiteur. `''`/`default` = look neutre d'origine ;
  `auto` = un biome par monde ; `0..5` = biome fixe (couleurs douces). Le style ne s'applique
  que si `.carte-map` porte la classe `.mario` (posée par `carte.js`).
- **Position :** « Auto » = ordre alphabétique ; un n° met le projet à cette place (les numérotés d'abord).

---

## 5. 🔐 admin.php — accès et fonctions

- **URL :** `luvumbu.com/admin.php`
- **Connexion (3 méthodes) :** identifiants MySQL réels · identifiants BOKONZI (`../core/credentials.php`)
  · mot de passe de secours `config/portfolio.php` → `admin.password` (actuellement `admin2026`, À CHANGER).
- **Sections :** Apparence du portfolio · 🗂️ Gérer tous les fichiers (ouvre `_gestion/`) ·
  🔑 Accès API distant (clé) · 🎨 Apparence de la carte · 🗺️ Mondes · éditeur par projet (icône,
  nom, image, description, **position**, point d'entrée).
- Le bouton « 🗂️ Gérer les fichiers » et `_gestion/` **partagent la session** (`pf_admin` ou `fs_admin`).

---

## 6. 🛡️ Sécurité — à retenir

- L'API `_gestion` = **accès en écriture à TOUT le site** (et lecture des secrets, ex. mots de passe
  DB dans les `config.php` des projets). La **clé est une clé maîtresse.**
- Contrôle par l'admin : **Régénérer** la clé coupe tout accès existant ; **Révoquer** la supprime.
- Protections en place : auth obligatoire (401 sinon), confinement `realpath`, CSRF (session),
  anti-bruteforce (verrou IP), cookies durcis, uploads assainis, HTTPS exigé pour la clé.
- Avant prod : mot de passe haché `_gestion/password.local.php`, changer `admin2026`, HTTPS.
- Ne jamais committer : `apikey.local.php`, `password.local.php`, `.lockout.json` (déjà dans `.gitignore`).

---

## 7. 🍳 Recettes rapides (procédures types)

**Modifier un fichier en ligne :** éditer en local → `save` via l'API (ou `newfile`+`save` si neuf) →
si c'est un `.css`/`.js`, **bumper `?v=` dans index.php** et redéployer index.php.

**Changer l'apparence de la carte :** admin.php → 🎨 Apparence → choisir → Enregistrer.
(ou directement : `appearance.json` `carte_apparence` = `''|auto|0..5`).

**Réordonner les projets :** admin.php → projet → 🔢 Position → n° → Enregistrer.
(ou `projets_meta.json` `ordre`).

**Ajouter un projet :** créer le dossier à la racine (avec un `index.*`) → il apparaît
automatiquement sur la carte (scan). Habillage/desc/position via admin.

**Vérifier un déploiement :** `read` le fichier en ligne via l'API et comparer au local ;
pour l'affichage, ouvrir `luvumbu.com/?x=<aléatoire>` (contourne le cache).

**Déployer proprement des assets :** toujours (1) pousser le fichier, (2) bumper `?v=`,
(3) pousser `index.php`, (4) vérifier via URL fraîche.

---

## 8. 📇 Les projets (résumé)

Descriptions détaillées : voir `DESCRIPTIONS_PROJETS.md`. En bref :
BOKONZI (data athlétisme, écosystème web+mobile+API) · CV Luvumbu (éditeur de CV) ·
PhotoSync/dropbox (sauvegarde photo Android+serveur) · DualCam (photo, backend indépendant) ·
Anniversaire (comptes à rebours, PWA) · RPN (plateforme communautaire MVC) ·
Tamagotchi (jeu, API en couches) · ztransfert/zt (envoi de gros fichiers) ·
Articles (blog Marion Delval) · Cours HTML5 Canvas · Arduino Nano (électronique) ·
Mes programmes (portail) · Puissance 4 (jeu, scores sauvegardés) ·
Portfolio « Luvumbu Land » (la page d'accueil ludique + admin + gestionnaire).

---

## 10. 🔑 Luvumbu ID — connexion unique (SSO) — `sso/`

Service d'**identité partagée** entre toutes les apps (1ʳᵉ brique du « Hub Luvumbu »).
Un JWT signé HS256 (secret partagé) porte l'identité ; l'identité de départ vient de **Google**.

- **Hub :** `https://luvumbu.com/sso/` · **Démo :** `sso/demo.php` (et `demo2.php`).
- **Fichiers :** `lib.php` (JWT sign/verify, vérif Google, cookie `LUVID`), `index.php` (hub Google→JWT),
  `client.php` (à inclure : `luvumbu_require_login()`, `luvumbu_user()`, `luvumbu_logout()`), `logout.php`.
- **Secret :** `sso/secret.local.php` (gitignoré) = `['secret'=>…, 'google_client_id'=>'878381681024-…', 'cookie_domain'=>'']`.
  Le `secret` doit être IDENTIQUE dans toutes les apps.
- **Intégrer une app (2 lignes) :** `require '…/sso/client.php'; $user = luvumbu_require_login();`.
  Hors luvumbu.com : copier `sso/` + le même secret, `define('LUVUMBU_HUB','https://luvumbu.com/sso/')` avant l'include.
- **Flux :** app → redirige au hub (`?app=&return=`) → Google → JWT + cookie → retour `?sso=<jwt>` → l'app vérifie.
  `return` validé contre une liste blanche d'hôtes (anti open-redirect).
- **Sécurité :** JWT signé (falsification impossible), exp 7 j, cookie HttpOnly/Lax/Secure, HTTPS exigé.
- Voir `sso/README.md`. Le clic Google réel ne se teste qu'en navigateur.

## 11. 🔥 Élan — organiseur d'objectifs (sport & pro) — `objectifs/`

App Kanban/Trello **protégée par le SSO** (1ʳᵉ app branchée dessus). `https://luvumbu.com/objectifs/`.

- **`index.php`** : UI (colonnes, cartes déplaçables en drag&drop, catégories 🏃 Sport / 💼 Pro,
  priorité, échéance, filtres). Login via `../sso/client.php`. `« Quitter »` = `luvumbu_logout(true, …)`.
- **`api.php`** : `action=load`/`save` (POST JSON). Données **par utilisateur** :
  `objectifs/data/u_<sha1(email lowercased)>.json`. 401 si non authentifié.
- **`.htaccess`** bloque les `.json`. **`three.min.js`** hébergé localement (pas de CDN).

### Actions prédéfinies → muscles (gamification)
- `ACTIONS` (JS) = 14 exercices → contributions par muscle. Chaque carte a `actions[]` (cases à cocher ;
  cocher **auto-remplit** le champ Détails via `syncActionsToText()`, sans effacer les notes perso).
- **Valider une séance** = glisser la carte vers une colonne « Terminé/✅ » → `completeSession()` accumule
  les muscles dans `DATA.stats.muscles` (× multiplicateur de **fréquence**, plafond 100), log date.
- **Fréquence** (`frequency()`) : séances/28 j → /semaine + multiplicateur (≥4→1.3, ≥2→1.1, ≥1→1.0, sinon 0.8).
  **Décroissance** (`applyDecay()`) : inactif >7 j → −1.5/muscle/jour.
- **13 muscles** (`MUSCLES`) : pecs, épaules, trapèzes, biceps, triceps, avant-bras, dos, abdos, fessiers,
  cuisses/quads, ischios, mollets, cardio.

### Avatar 3D (Three.js r128, local)
- Bouton « 🏋️ Avatar » → modale : corps 3D (`buildModel()` en primitives : sphères/cylindres/box)
  + barres 0-100 %/muscle + stats. **Rotation au drag** + rotation auto (`animate()`).
- **Muscles = matériau peau PARTAGÉ** (`AV.skinMat`) : ils **grossissent** avec le niveau (scale, pas de rouge),
  style « CJ (San Andreas) qui se muscle ». `updateAvatar3D()` ne change QUE le volume.
- **Teint** réglable (`stats.skin`, sélecteur `SKINS[]` + `setSkin()`), défaut brun foncé `#3d2413`.
- **Rendu fidèle à la palette :** `toneMapping = NoToneMapping` + lumière plafonnée ~1.0 (sinon les couleurs
  clippent vers le blanc et le foncé s'éclaircit — piège important).
- ⚠️ `CapsuleGeometry` **n'existe pas** en r128 → utiliser `CylinderGeometry`/sphères.

## 12. 🎮 Carte des projets — mondes, apparences, positions, pages détaillées

- **Mondes** (façon Mario) : `carte.js` découpe les projets en mondes ; réglable admin (`world_size`, `world_names`
  dans `appearance.json`). Piège cache : après modif `carte.css`/`carte.js`, **bumper `?v=` dans `index.php`**.
- **Apparence** (décision ADMIN, `appearance.json` `carte_apparence` : `''`=neutre, `auto`, `0..5`=biome doux).
  Style thématique appliqué seulement si `.carte-map` a la classe `.mario`.
- **Position** de chaque projet : select dans l'admin → `projets_meta.json` `ordre` (0=auto) → tri dans `inc/carte.php`.
- **Pages détaillées** : `projet.php?p=<dossier>` (hero + `details` en markdown léger + section Liens :
  `url_app` + `liens[{label,url}]`). Champs gérés dans l'admin (éditeur par projet) ; lien « 📖 En savoir plus » sur les fiches.

## 13. 🗃️ Note bases de données (Hostinger)

Convention Hostinger : **utilisateur MySQL == nom de la base**. Ex. `ztransfert` (dossier `zt` en ligne) est passé
de la base `u489596434_marion` à **`u489596434_zt`** (même mot de passe) dans `zt/admin.php`, `zt/config.php`,
`zt/all_doc.php`. La base doit exister côté Hostinger (l'API ne peut pas créer de base) ; les tables peuvent se créer
via le code de l'app. Les mots de passe DB sont en clair dans les `config.php` des projets → l'API `_gestion` les expose.

---

## 9. 🧠 Fichiers de référence à connaître

| Fichier | Rôle |
|---------|------|
| `GUIDE_IA.md` | **CE fichier** — vue d'ensemble + procédures |
| `DESCRIPTIONS_PROJETS.md` | descriptions détaillées de chaque projet |
| `CARNET_DE_COMPETENCES.txt` | compétences démontrées (orienté recruteur) |
| `_gestion/README.txt` | mode d'emploi + sécurité du gestionnaire de fichiers |
| `sso/README.md` | intégration de la connexion unique (SSO) |
| `config/portfolio.php` | source de vérité du contenu du portfolio |
| `config/appearance.json` | réglages carte (apparence, mondes) écrits par admin.php |
| `config/projets_meta.json` | habillage/desc/ordre/liens par projet |

---

_Dernière logique en place : gestion distante par API + clé (contrôlée depuis admin.php) ·
carte à mondes + apparences choisies par l'admin + projets ordonnables (select) + pages détaillées (`projet.php`) ·
SSO « Luvumbu ID » (`sso/`) et 1ʳᵉ app branchée dessus, **Élan** (`objectifs/`) avec avatar 3D musculaire (Three.js).
En cas de doute : lire le fichier réel (local ET en ligne via l'API) avant de conclure._
