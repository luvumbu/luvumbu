# Structure des fichiers

```
bad_place/
│
├─ public/                     ← RACINE WEB (DocumentRoot)
│  ├─ index.html               ← page d'accueil (seule page HTML à la racine)
│  ├─ index.php                ← front controller de l'API (obligatoire à la racine)
│  ├─ .htaccess                ← routage : /api → PHP, reste → pages HTML
│  ├─ pages/                   ← toutes les autres pages HTML
│  │  ├─ connexion.html
│  │  ├─ inscription.html
│  │  ├─ signaler.html         ← formulaire de signalement (réservé aux membres)
│  │  ├─ signalements.html     ← liste filtrable des signalements publiés
│  │  ├─ signalement.html      ← page détail d'un signalement (lieu, cause, tout)
│  │  ├─ carte.html            ← carte interactive Leaflet (points + zones)
│  │  ├─ contestation.html     ← formulaire de droit de réponse
│  │  ├─ mentions-legales.html
│  │  ├─ cgu.html
│  │  ├─ confidentialite.html  ← politique RGPD
│  │  └─ charte-moderation.html
│  └─ assets/
│     ├─ css/style.css         ← design system (responsive, clair/sombre)
│     ├─ js/
│     │  ├─ core.js            ← socle partagé (API, session, en-tête, cookies)
│     │  ├─ app.js             ← page d'accueil
│     │  ├─ signaler.js        ← formulaire + autocomplétion d'adresse
│     │  ├─ signalements.js    ← liste (recherche, filtres, pagination)
│     │  ├─ signalement.js     ← page détail
│     │  └─ carte.js           ← carte Leaflet temps réel (points + zones)
│     ├─ vendor/leaflet/       ← Leaflet hébergé en local (pas de CDN)
│     └─ img/
│
├─ api/
│  └─ routes.php               ← définition de toutes les routes REST
│
├─ src/                        ← code applicatif (namespace App\)
│  ├─ Core/
│  │  ├─ App.php               ← bootstrap + pipeline de middlewares
│  │  ├─ Router.php            ← routeur REST
│  │  ├─ Request.php / Response.php
│  │  ├─ Database.php          ← PDO + transactions
│  │  ├─ Jwt.php               ← jetons access/refresh
│  │  ├─ Crypto.php            ← chiffrement AES-256 (RGPD)
│  │  ├─ Validator.php         ← validation des données
│  │  ├─ Config.php / helpers.php
│  │  ├─ HttpException.php / Middleware.php
│  ├─ Middleware/
│  │  ├─ CorsMiddleware.php
│  │  ├─ RateLimitMiddleware.php
│  │  ├─ AuthenticateMiddleware.php
│  │  └─ RequireRoleMiddleware.php
│  ├─ Controllers/
│  │  ├─ HealthController.php · MetaController.php · CategoryController.php
│  │  ├─ AuthController.php    ← inscription/connexion/session
│  │  ├─ ReportController.php  ← signalements (créer/lister/détail)
│  │  ├─ MapController.php     ← points, zones (vigilance par ville) & heatmap
│  │  ├─ GeoController.php     ← autocomplétion d'adresse
│  │  ├─ MediaController.php   ← service des pièces jointes
│  │  ├─ ContestationController.php ← droit de réponse (LCEN)
│  │  └─ AbuseController.php   ← signalement de contenu illicite
│  └─ Services/
│     ├─ GeocodingService.php  ← Nominatim (adresse → GPS, recherche)
│     ├─ MediaService.php      ← upload + vignettes GD
│     └─ OrganizationService.php ← dédup + indice de vigilance
│
├─ database/
│  ├─ migrate.php              ← runner de migrations & seeds
│  ├─ migrations/*.sql         ← schéma versionné (8 fichiers, 20 tables)
│  └─ seeds/                   ← référentiels (catégories/motifs/types) + admin
│
├─ storage/                    ← hors racine web
│  ├─ media/ · thumbnails/     ← fichiers uploadés
│  ├─ logs/                    ← journaux applicatifs
│  └─ cache/                   ← rate-limiting
│
├─ config/
│  ├─ config.php               ← configuration centrale
│  ├─ .env                     ← secrets (non versionné)
│  └─ .env.example             ← modèle
│
├─ admin/                      ← interface d'administration (à venir)
├─ extension-chrome/           ← source de l'extension (à venir)
├─ doc/                        ← cette documentation
├─ vendor/                     ← dépendances Composer
├─ composer.json / composer.phar
└─ README.md
```

## Règle d'organisation

- **`index`** (`.html` et `.php`) reste à la **racine de `public/`**.
- **Toutes les autres pages HTML** sont dans **`public/pages/`**.
- Les pages dans `pages/` référencent les assets via `../assets/…` ; les liens de navigation injectés par `core.js` sont **absolus** (basés sur la racine détectée), donc valides depuis n'importe quelle page.
