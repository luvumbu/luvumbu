# 01 — Architecture

## Vue d'ensemble

Le projet est un **blog PHP server-rendered** + **PWA mobile** + **landing page** statique, déployés sur Hostinger via GitHub Actions, avec un système de synchronisation locale ↔ serveur.

```
                 ┌─────────────────────────────────────┐
                 │           VISITEUR                  │
                 └─────────────────────────────────────┘
                  │                  │                │
   navigateur     │                  │                │
                  ▼                  ▼                ▼
        mariondelval.com   blog.mariondelval.com    PWA installée
        (LANDING HTML)     (BLOG PHP + API)         (mobile-app HTML/JS)
                                  │
                                  ▼
                          ┌──────────────────┐
                          │  Apache + PHP    │
                          │  PDO → MySQL     │
                          └──────────────────┘
                                  │
                                  ▼
                            ┌──────────┐
                            │  MySQL   │  (Hostinger BDD partagée)
                            └──────────┘
```

## Structure du repo Git

```
Blog/                              <- racine repo (= C:\xampp\htdocs\Blog\)
│
├── .git/                          <- métadonnées Git (jamais déployé)
├── .github/
│   └── workflows/
│       └── deploy.yml             <- pipeline FTP Hostinger
│
├── .gitignore                     <- exclusions Git
├── index.html                     <- LANDING (déployée à /public_html/)
├── doc/                           <- cette documentation
│
└── blog/                          <- LE BLOG complet (déployé à /public_html/blog/)
    │
    ├── .gitignore                 <- exclusions spécifiques au blog
    ├── .htaccess                  <- règles Apache prod
    ├── README.md                  <- README court côté code
    ├── install.php                <- installeur premier lancement
    ├── index.php                  <- accueil public du blog
    ├── manifest.json              <- manifest PWA du blog
    ├── sw.js                      <- service worker (kill-switch désactivé)
    ├── icon-192.png, icon-512.png <- icônes PWA principales
    │
    ├── api/                       <- endpoints REST JSON
    │   ├── _bootstrap.php         <- CORS + JSON headers
    │   ├── _auth.php              <- helper Bearer token
    │   ├── login.php              <- POST email/password → token
    │   ├── articles.php           <- GET liste articles
    │   ├── article.php            <- GET détail / POST publish
    │   ├── version.php            <- GET version mobile-app
    │   ├── site_info.php          <- GET settings publics + landing
    │   └── sync_receive.php       <- POST réception sync
    │
    ├── includes/                  <- code commun PHP
    │   ├── bootstrap.php          <- DB, sessions, auth init
    │   ├── db.php                 <- connexion PDO
    │   ├── auth.php               <- login/logout/require_admin
    │   ├── helpers.php            <- e(), base_url(), CSRF, flash
    │   ├── settings.php           <- get_setting/set_setting
    │   ├── upload.php             <- validation + sauvegarde images
    │   ├── sync_keys.php          <- gestion clés sync (fichier JSON)
    │   ├── sync_dump.php          <- dump SQL + zip uploads
    │   ├── header.php             <- partial header HTML public
    │   └── footer.php             <- partial footer
    │
    ├── pages/                     <- pages publiques + admin
    │   ├── login.php / register.php / logout.php
    │   ├── article.php            <- détail public
    │   ├── article_new.php        <- création (auth)
    │   ├── article_edit.php       <- édition (auth)
    │   ├── article_delete.php     <- suppression
    │   ├── comment_delete.php
    │   ├── admin.php              <- dashboard admin
    │   ├── settings.php           <- paramètres généraux
    │   ├── social.php             <- réseaux sociaux
    │   ├── landing_settings.php   <- apparence landing (live preview)
    │   ├── sync_keys.php          <- génération clés (côté serveur)
    │   ├── sync_push.php          <- envoi données (côté local)
    │   └── sync_json.php          <- import/export JSON
    │
    ├── mobile-app/                <- PWA mobile autonome
    │   ├── index.html             <- shell HTML
    │   ├── app.js                 <- logique SPA + API calls
    │   ├── style.css              <- styles mobile
    │   ├── manifest.json          <- manifest PWA mobile
    │   ├── sw.js                  <- service worker (kill-switch)
    │   ├── offline.html
    │   ├── icon-192.png, icon-512.png
    │   ├── widget-template.json
    │   ├── widget-data.json
    │   └── generate-icons.ps1     <- script local pour régénérer
    │
    ├── assets/                    <- ressources statiques du site web
    │   ├── css/styles.css         <- styles publics + admin
    │   └── js/preview.js          <- preview images dans article_new
    │
    ├── config/                    <- (gitignored)
    │   ├── config.php             <- credentials BDD (généré par install.php)
    │   └── sync_keys.json         <- tokens sync (généré à la volée)
    │
    ├── uploads/                   <- (gitignored sauf .gitkeep)
    │   └── ...images des articles...
    │
    └── sql/
        └── schema.sql             <- création initiale des tables
```

## Mapping local ↔ serveur

Le local **mirroir parfaitement** la structure du serveur, ce qui simplifie tout :

| Local | Serveur Hostinger | URL publique |
|---|---|---|
| `Blog/index.html` | `/public_html/index.html` | https://mariondelval.com/ |
| `Blog/blog/index.php` | `/public_html/blog/index.php` | https://blog.mariondelval.com/ |
| `Blog/blog/api/login.php` | `/public_html/blog/api/login.php` | https://blog.mariondelval.com/api/login.php |
| `Blog/blog/mobile-app/index.html` | `/public_html/blog/mobile-app/index.html` | https://blog.mariondelval.com/mobile-app/ |

Le sous-domaine `blog.mariondelval.com` est configuré dans hPanel pour pointer vers `/public_html/blog/`. Donc le contenu est accessible de **deux manières équivalentes** :

- via `mariondelval.com/blog/...` (chemin sur le domaine principal)
- via `blog.mariondelval.com/...` (raccourci via le sous-domaine)

## Flux de données principaux

### 1. Visite d'un article public

```
visiteur → blog.mariondelval.com/pages/article.php?id=42
       │
       ▼
   Apache → blog/pages/article.php
       │
       ▼
   require_once includes/bootstrap.php
       │
       ├── ouvre la session
       ├── construit $pdo (PDO MySQL)
       └── charge les helpers
       │
       ▼
   SELECT FROM articles WHERE id=42 + sous-articles + commentaires
       │
       ▼
   include includes/header.php  (rend <head>, menu, etc.)
   echo HTML de l'article
   include includes/footer.php
       │
       ▼
   réponse HTML au navigateur
```

### 2. Publication d'un article depuis la PWA mobile

```
PWA → POST blog.mariondelval.com/api/article.php (multipart)
   │   Authorization: Bearer <token>
   │   Body: title, content, image, ...
   │
   ▼
api/_bootstrap.php  (CORS headers, charge config, PDO)
api/_auth.php       (valide le token contre api_tokens table)
api/article.php
   │
   ├── valide les champs
   ├── upload de l'image → includes/upload.php → uploads/...
   ├── INSERT INTO articles ...
   └── INSERT INTO article_images ...
   │
   ▼
JSON { "ok": true, "id": 123 }
   │
   ▼
PWA met à jour l'UI
```

### 3. Synchronisation local → serveur

```
LOCAL ADMIN                              SERVEUR ADMIN
   │                                          │
   │  visite sync_keys.php côté serveur   ◀───┤  génère clé
   │                                          │  → sync_keys.json
   │                                          │
   │  ◀── copie/colle la clé ─────────────────┤
   │                                          │
   │  visite sync_push.php côté local         │
   │  → génère dump SQL                       │
   │  → zip de uploads/                       │
   │  → cURL multipart POST                   │
   │           │                              │
   │           └──────────────────────────────▶  api/sync_receive.php
   │                                          │     │
   │                                          │     ├── consomme la clé
   │                                          │     ├── importe SQL
   │                                          │     └── extrait zip
   │                                          │     │
   │  ◀── JSON {ok:true, uploads:42} ─────────┤◀────┘
```

## Choix d'architecture

- **Pas de framework** : pour rester proche du code, facile à déployer en FTP shared hosting, minimum de complexité
- **PHP server-rendered** pour les pages publiques → SEO-friendly, pas besoin de SPA pour un blog
- **API JSON séparée** pour alimenter la PWA mobile → mêmes données, deux frontends
- **Service Worker volontairement minimal** : `sw.js` est un kill-switch désinstallation, pour éviter les bugs de cache complexes qu'on a eu avant. Update-check via `version.php` et clear caches manuel
- **Sous-domaine pour le blog** : libère le domaine principal pour une landing élégante, permet d'évoluer indépendamment
- **Sync à clés à usage unique** : pas de secret partagé permanent. Chaque sync nécessite une action manuelle côté serveur (admin génère la clé). Plus sûr qu'un token statique en `config.php`

## Évolutions possibles

- Cache HTTP côté Apache (mod_expires) sur `/blog/assets/*` et `/blog/icon-*.png`
- File d'attente pour les uploads d'images (resize à la volée vs au moment de l'upload)
- Webhooks (push event à chaque article publié)
- Migration BDD versionnée (table `migrations` + scripts dans `sql/`)
