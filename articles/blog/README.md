# Mon Blog

Blog PHP avec API JSON, app mobile PWA, landing page publique, déploiement automatisé et synchronisation local → serveur.

## Architecture du repo

```
Blog/                              <- racine du repo Git
├── index.html                     <- LANDING PAGE (mariondelval.com)
├── .github/workflows/deploy.yml   <- GitHub Actions → FTP Hostinger
└── blog/                          <- LE BLOG complet (blog.mariondelval.com)
    ├── index.php                  <- accueil articles
    ├── pages/                     <- pages publiques + admin (PHP)
    ├── api/                       <- API REST JSON
    ├── includes/                  <- bootstrap, DB, auth, helpers, sync
    ├── assets/                    <- CSS + JS du site
    ├── mobile-app/                <- PWA mobile (HTML + JS)
    ├── uploads/                   <- images articles (non commit)
    ├── config/                    <- config.php + sync_keys.json (non commit)
    ├── sql/schema.sql             <- schéma BDD
    └── install.php                <- installeur premier lancement
```

### Architecture en production (Hostinger)

```
/public_html/
├── index.html              -> mariondelval.com (landing)
└── blog/                   -> blog.mariondelval.com (sous-domaine)
    └── (tout le contenu de blog/)
```

Le sous-domaine `blog.mariondelval.com` doit pointer vers `/public_html/blog/` dans hPanel.

## Installation locale

1. Lancer XAMPP (Apache + MySQL)
2. Cloner le repo dans `htdocs/Blog/`
3. Ouvrir `http://localhost/Blog/blog/install.php`
4. Remplir le formulaire (DB locale + admin)

Accès local :
- Landing : `http://localhost/Blog/`
- Blog : `http://localhost/Blog/blog/`
- Mobile-app : `http://localhost/Blog/blog/mobile-app/`

## Déploiement automatique (GitHub Actions → FTP Hostinger)

Chaque `git push` sur `master` déclenche un déploiement en 2 étapes :

1. `./blog/` → `/public_html/blog/` (le blog sur le sous-domaine)
2. `./index.html` → `/public_html/index.html` (la landing sur le domaine principal)

### Secrets GitHub requis

Dans **Settings → Secrets and variables → Actions** :

| Secret | Valeur exemple |
|---|---|
| `FTP_HOST` | `ftp.tondomaine.com` ou IP |
| `FTP_USER` | `u489596434.tondomaine` |
| `FTP_PASSWORD` | mot de passe FTP |
| `FTP_REMOTE_DIR` | (optionnel) défaut `/public_html/` |
| `FTP_PROTOCOL` | (optionnel) `ftps` ou `ftp` |
| `FTP_PORT` | (optionnel) défaut `21` |

Le workflow exclut automatiquement : `config/config.php`, `config/sync_keys.json`, `uploads/`, `APK/`, `*.keystore`, `_diagnostic.php`.

## Synchronisation local → serveur

Système d'envoi sécurisé de la BDD locale + images vers le serveur distant.

### Vue d'ensemble

1. **Côté serveur** : génère une clé d'autorisation à usage unique (5min - 24h)
2. **Côté local** : colle la clé, envoie le tout via HTTPS
3. **Côté serveur** : valide la clé (consommée), importe SQL + extrait les uploads

### Étapes

**Sur le serveur** (`blog.mariondelval.com/pages/admin.php`) :
- `🔑 Clés sync (serveur)` → choisis une durée → `Générer une nouvelle clé`
- Bouton `📋 Copier` à côté de la clé générée

**Sur le local** (`localhost/Blog/blog/pages/admin.php`) :
- `📤 Envoyer vers serveur` → colle la clé → coche la confirmation → `Envoyer maintenant`
- Overlay avec spinner pendant le transfert
- Un message ✅ ou ❌ s'affiche au retour

### Sécurité

- Clé aléatoire 32 octets hex, **usage unique**, expiration courte
- Stockée dans `config/sync_keys.json` (gitignore + FTP-exclude)
- Stockée **hors BDD** pour qu'une sync ne puisse pas effacer son propre auth
- Admin requis sur les 2 instances, CSRF sur tous les formulaires

## Import / Export

Sauvegarde / restauration manuelle via fichier JSON et/ou ZIP d'images.

- `📦 Import / Export` dans l'admin
- **Export** — 3 boutons :
  - `📄 JSON (données seules)` : articles, users, comments, settings, social_links (chemins d'images uniquement, pas les fichiers)
  - `🖼️ Images (ZIP)` : tout le contenu de `uploads/` zippé
  - `📦 Export complet (JSON + images)` : un seul ZIP contenant `data.json` + `uploads/` — la sauvegarde la plus complète
- **Import** — 2 zones séparées :
  - **JSON** : drag-and-drop d'un `.json` → remplace toutes les données BDD
  - **Images ZIP** : drag-and-drop d'un `.zip` → remplace tout `uploads/` (purge avant extraction)

⚠️ Les fichiers d'export contiennent des hashes de mots de passe : **ne jamais commit** (les patterns `blog-export-*.json`, `blog-images-*.zip` et `blog-full-*.zip` sont à exclure).

## Page d'accueil personnalisable

`mariondelval.com/index.html` est une landing page dynamique pilotée par les paramètres du blog.

### Admin → `🎨 Apparence accueil`

| Section | Paramètres |
|---|---|
| **Textes** | Badge en haut, Titre, Slogan, Texte bouton, URL bouton, Texte du lien en bas, URL du lien en bas |
| **Comportement** | Afficher/cacher le pulse vert |
| **Couleurs principales** | Fond, Texte principal, Texte secondaire, Bouton clair, Bouton foncé |
| **Couleurs d'ambiance** | 3 blobs (haut-gauche, bas-droit, centre) |

- **Aperçu en temps réel** : mini-landing à droite du formulaire qui se met à jour à chaque frappe / changement de couleur
- **5 palettes préréglées** : Sombre (défaut), Clair, Sunset, Forêt, Minimal
- Bouton **↺ Tout réinitialiser** pour revenir aux valeurs par défaut

Si un paramètre texte est vide, le bloc est masqué ou utilise le fallback (`site_name` pour le titre, `tagline` pour le slogan).

### Endpoint public

`GET blog/api/site_info.php` (CORS ouvert, cache 5 min) renvoie :

```json
{
  "site_name": "...",
  "tagline":   "...",
  "about_text":"...",
  "landing": {
    "eyebrow":      "...",
    "title":        "...",
    "subtitle":     "...",
    "cta_text":     "...",
    "cta_url":      "blog/",
    "footer_text":  "...",
    "footer_url":   "blog/",
    "show_pulse":   true,
    "bg_color":     "#0f172a",
    "text_color":   "#f1f5f9",
    "muted_color":  "#94a3b8",
    "accent_color": "#16a34a",
    "accent_dark":  "#166534",
    "blob_1": "#6366f1",
    "blob_2": "#ec4899",
    "blob_3": "#22c55e"
  }
}
```

## App mobile (PWA)

### Installation

- **Android** : Chrome → `/mobile-app/` → Menu ⋮ → `Installer l'application`
- **iOS** : Safari → `/mobile-app/` → Partager → `Sur l'écran d'accueil`

### Mises à jour

Le système de mise à jour fonctionne en 3 niveaux :

1. `api/version.php` expose la version courante (`MOBILE_APP_VERSION`)
2. `mobile-app/app.js` compare avec sa propre `APP_VERSION` et affiche la bannière "🚀 Nouvelle version dispo"
3. Le bouton **Mettre à jour** appelle `forceUpdate()` qui désinscrit le SW, vide tous les caches, refetch `index.html / app.js / style.css / manifest.json / icon-192.png / icon-512.png` et reload

**Pour bump la version** à chaque release : change `APP_VERSION` dans `app.js` et `MOBILE_APP_VERSION` dans `version.php` (et idéalement le `?v=` dans `manifest.json` + `index.html` pour invalider les caches d'icônes).

⚠️ Limitation OS : l'icône d'une PWA déjà installée n'est pas rafraîchie par Chrome. Désinstaller / réinstaller la PWA pour voir la nouvelle icône.

### Générer un APK distribuable (optionnel)

- https://www.pwabuilder.com
- Entrer `https://blog.mariondelval.com/mobile-app/`
- `Package for Stores` → Android → Generate
- Garder précieusement le `signing.keystore` (jamais commité)

## API JSON

| Endpoint | Méthode | Auth | Description |
|---|---|---|---|
| `/api/login.php` | POST | — | Email/password → token |
| `/api/articles.php` | GET | — | Liste articles racines |
| `/api/article.php?id=X` | GET | — | Détail article + enfants + commentaires |
| `/api/article.php` | POST | Token | Publier (JSON ou multipart) |
| `/api/version.php` | GET | — | Version courante de l'app mobile |
| `/api/site_info.php` | GET | — | Settings publics + config landing |
| `/api/sync_receive.php` | POST | Clé sync | Recevoir un dump + uploads zip |

Authentification : `Authorization: Bearer <token>` (30 jours).

## Sous-articles

Chaque article peut avoir des sous-articles (arborescence illimitée). Seul l'auteur du parent (ou un admin) peut ajouter un sous-article. La suppression du parent supprime toute la descendance (cascade SQL).

## Panel admin

Accessible via `pages/admin.php` (compte admin requis). Dashboard avec grille de cartes colorées :

| Carte | Page | Rôle |
|---|---|---|
| 🎨 Apparence accueil | `landing_settings.php` | Personnaliser la landing |
| ⚙️ Paramètres du site | `settings.php` | Nom, slogan, baseline, à propos |
| 🔗 Réseaux sociaux | `social.php` | Ajouter / éditer liens sociaux |
| 📤 Envoyer vers serveur | `sync_push.php` | Synchroniser le local vers la prod |
| 🔑 Clés sync | `sync_keys.php` | Générer une clé d'autorisation |
| 📦 Import / Export JSON | `sync_json.php` | Sauvegarde/restauration manuelle |

## Sécurité

- `config/config.php` (credentials BDD) jamais commit, généré par `install.php`
- `config/sync_keys.json` (tokens sync) jamais commit, généré à la volée
- `signing.keystore` (clé APK) jamais commit — sans lui pas de mise à jour APK
- Uploads validés : MIME, taille max 12 Mo, formats JPG/PNG/GIF/WebP
- CSRF sur tous les formulaires
- Tokens API séparés des sessions web (table `api_tokens`)
- Synchronisation par clés à usage unique, expirations courtes, stockage hors BDD
- HTTPS forcé en prod (`.htaccess`)

## Fonctionnalités natives PWA

| Fonctionnalité | API web | Android | iOS |
|---|---|---|---|
| Appareil photo | `getUserMedia` / `<input capture>` | OK | OK |
| Microphone | `getUserMedia` | OK | OK |
| GPS | `navigator.geolocation` | OK | OK |
| Vibration | `navigator.vibrate` | OK | Non |
| Push notifications | `ServiceWorker + Push` | OK | iOS 16.4+ |
| Menu Partager natif | `navigator.share` | OK | OK |
| Accéléromètre / boussole | `DeviceOrientation` | OK | OK |
| Batterie | `navigator.getBattery` | OK | Non |
| Bluetooth | `Web Bluetooth` | OK | Non |
| NFC | `Web NFC` | OK | Non |
| Stockage local | `localStorage / IndexedDB` | OK | OK |
| Fichiers | `<input file>` | OK | OK |

Non disponible (réservé natif) : SMS, appels auto, services background persistants, achat in-app store.
