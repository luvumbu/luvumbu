# 07 — App mobile (PWA)

## Vue d'ensemble

L'app mobile est une **Progressive Web App** entièrement HTML + JavaScript, sans framework, autonome dans `blog/mobile-app/`. Elle communique avec le blog via l'API JSON.

```
blog/mobile-app/
├── index.html              <- shell HTML (toutes les vues)
├── app.js                  <- logique : state, routing, API calls
├── style.css               <- styles
├── manifest.json           <- manifeste PWA (icônes, theme, etc.)
├── sw.js                   <- service worker (kill-switch désactivé)
├── offline.html            <- page affichée si réseau coupé
├── icon-192.png            <- icône standard
├── icon-512.png            <- icône grande
├── widget-template.json    <- gabarit du widget Adaptive Card (Windows 11)
├── widget-data.json        <- données dynamiques du widget
└── generate-icons.ps1      <- script PowerShell pour régénérer les icônes
```

## Architecture interne (app.js)

```
APP_VERSION                  → constante incrémentée à chaque release
API_BASE                     → calculé dynamiquement depuis window.location

state                        → objet en mémoire (token, user, vue courante, historique)
                               + localStorage pour persistance

safeReadUser / safeReadToken → lecture sécurisée de localStorage

api(path, options)           → wrapper fetch() qui ajoute le Bearer token

showView('login' | 'list' | 'detail' | 'new' | ...)
                             → affiche une seule vue, cache les autres
                             → met à jour l'historique

renderList()                 → appel GET /api/articles.php, génère le HTML

renderDetail(id)             → appel GET /api/article.php?id=X

submitArticle()              → POST /api/article.php multipart

forceUpdate()                → désinscrit SW, vide caches, refetch tout, reload

checkForUpdate()             → compare APP_VERSION avec /api/version.php
                               → affiche la bannière "Mise à jour dispo"
```

### API_BASE auto-adaptatif

```javascript
const API_BASE = (() => {
    const here = window.location.href;
    const root = here.replace(/\/mobile-app\/.*$/, '/');
    return root + 'api';
})();
```

| URL d'accès | `API_BASE` devient |
|---|---|
| `https://blog.mariondelval.com/mobile-app/` | `https://blog.mariondelval.com/api` |
| `http://localhost/Blog/blog/mobile-app/` | `http://localhost/Blog/blog/api` |

→ L'app marche partout où elle est servie, sans hardcoder le domaine.

### Système de vues

`index.html` contient toutes les vues dans des `<section id="view-X" hidden>`. Le JS bascule entre elles en jouant sur l'attribut `hidden` :

- `view-login` : écran de connexion
- `view-list` : liste des articles
- `view-detail` : détail d'un article + commentaires
- `view-new` : formulaire de création
- `view-edit` : édition
- `view-profile` : profil utilisateur

Pas de router URL → l'app reste sur `index.html` quelle que soit la vue. Bouton "retour" géré par un `state.history` en mémoire.

## Manifest PWA (`manifest.json`)

```json
{
  "id": "/mobile-app/",
  "name": "Mon Blog",
  "short_name": "Blog",
  "start_url": "./index.html",
  "scope": "./",
  "display": "standalone",
  "theme_color": "#2e7d32",
  "background_color": "#2e7d32",
  "icons": [
    { "src": "icon-192.png?v=16", "sizes": "192x192", "type": "image/png", "purpose": "any" },
    { "src": "icon-512.png?v=16", "sizes": "512x512", "type": "image/png", "purpose": "any" },
    { "src": "icon-192.png?v=16", "sizes": "192x192", "type": "image/png", "purpose": "maskable" },
    { "src": "icon-512.png?v=16", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
  ],
  "shortcuts": [...],
  "share_target": {...},
  "protocol_handlers": [...],
  "widgets": [...]
}
```

Points clés :
- `display: standalone` → l'app s'ouvre sans la barre du navigateur (look natif)
- `?v=16` sur les icônes → cache-busting quand on change l'icône
- `maskable` purpose → permet à Android d'appliquer un masque circulaire / squircle
- `shortcuts` → accès rapide depuis le long-press sur l'icône
- `share_target` → l'app reçoit les partages depuis d'autres apps
- `widgets` → widget Windows 11 (Adaptive Card)

## Service Worker (`sw.js`)

**Volontairement minimal** : c'est un kill-switch désinstallation.

```javascript
self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (e) => {
    e.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.map(k => caches.delete(k)));
        await self.registration.unregister();
        const clients = await self.clients.matchAll();
        clients.forEach(c => c.navigate(c.url));
    })());
});

// Aucune interception fetch : le SW est inerte
```

Pourquoi ? Une version précédente du SW cachait agressivement les fichiers, ce qui provoquait des bugs de désynchronisation (utilisateurs bloqués sur une version cachée). Le SW actuel sert juste à **vider l'ancien cache et se désinstaller** s'il était installé.

L'app fonctionne donc en mode **online-only**, ce qui simplifie tout :
- Pas de logique de queue offline
- Pas de problème de stale cache
- Les mises à jour sont immédiates

## Système de mise à jour

### À 3 niveaux

```
┌────────────────────────────────────────────────────┐
│  Niveau 1 : api/version.php                        │
│  → renvoie la version courante de l'app           │
│  → bumper à chaque release de mobile-app/         │
└────────────────────────────────────────────────────┘
                       ▲
                       │ comparaison
                       │
┌────────────────────────────────────────────────────┐
│  Niveau 2 : APP_VERSION dans app.js               │
│  → const APP_VERSION = 'v16';                     │
│  → cette valeur arrive dans le navigateur         │
└────────────────────────────────────────────────────┘
                       ▲
                       │ détecte différence
                       │
┌────────────────────────────────────────────────────┐
│  Niveau 3 : bouton "Mise à jour disponible"        │
│  → affiche une bannière en haut                   │
│  → utilisateur clique → forceUpdate()             │
└────────────────────────────────────────────────────┘
```

### `checkForUpdate()`

```javascript
async function checkForUpdate() {
    const res = await fetch(API_BASE + '/version.php?_=' + Date.now(), {
        cache: 'no-store',
    });
    const data = await res.json();
    if (data.version && data.version !== APP_VERSION) {
        document.getElementById('update-banner').hidden = false;
    }
}
```

Appelée au démarrage. Si la version du serveur diffère, bannière jaune en haut "🚀 Nouvelle version disponible !".

### `forceUpdate()`

```javascript
async function forceUpdate() {
    // 1. Désinscrit tous les service workers
    const regs = await navigator.serviceWorker.getRegistrations();
    await Promise.all(regs.map(r => r.unregister()));

    // 2. Vide tous les caches HTTP
    const keys = await caches.keys();
    await Promise.all(keys.map(k => caches.delete(k)));

    // 3. Refetch les fichiers critiques sans cache
    const ts = Date.now();
    const base = window.location.href.replace(/\/index\.html.*$/, '/');
    await Promise.all([
        fetch(base + 'index.html?_=' + ts,    { cache: 'reload' }),
        fetch(base + 'app.js?_=' + ts,        { cache: 'reload' }),
        fetch(base + 'style.css?_=' + ts,     { cache: 'reload' }),
        fetch(base + 'manifest.json?_=' + ts, { cache: 'reload' }),
        fetch(base + 'icon-192.png?_=' + ts,  { cache: 'reload' }),
        fetch(base + 'icon-512.png?_=' + ts,  { cache: 'reload' }),
    ]);

    // 4. Reload la page avec un timestamp pour éviter le cache HTTP
    window.location.replace(window.location.href.split('?')[0] + '?_=' + Date.now());
}
```

### Procédure de release mobile-app

À chaque modification de `mobile-app/`, bump 3 endroits :

1. `mobile-app/app.js` : `const APP_VERSION = 'v17';`
2. `api/version.php` : `const MOBILE_APP_VERSION = 'v17';`
3. `mobile-app/manifest.json` : remplacer `?v=16` par `?v=17` (toutes occurrences)

Optionnel : `mobile-app/index.html` favicon `href="icon-192.png?v=17"`.

Puis `git push` → le déploiement se fait → la bannière apparaît chez les utilisateurs lors de leur prochaine ouverture.

## Icônes

### Génération

`mobile-app/generate-icons.ps1` (PowerShell, Windows) génère `icon-192.png` et `icon-512.png` programmatiquement avec `System.Drawing` :

```powershell
.\mobile-app\generate-icons.ps1
```

Le script dessine actuellement un **arbre stylisé** (3 cercles foliaires + tronc + fruits) sur fond vert dégradé.

Pour modifier le design, ouvre le `.ps1` et adapte la fonction `New-TreeIcon`.

⚠️ Le script est **exclu** du déploiement FTP (uniquement local). Le résultat (les PNG) est commité et déployé.

### Cache OS

Quand une PWA est installée sur Android/iOS, le système **met l'icône en cache**. Même si tu changes le manifest, l'icône installée ne se met pas à jour. Pour la rafraîchir :

- **Désinstaller / réinstaller** la PWA
- Ou attendre que Chrome détecte le changement de manifest (~24h)

## Login / Auth API

```javascript
const res = await fetch(API_BASE + '/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
});
const data = await res.json();
state.token = data.token;
state.user  = data.user;
localStorage.setItem('token', data.token);
localStorage.setItem('user',  JSON.stringify(data.user));
```

Le token reste dans `localStorage` et est utilisé pour toutes les requêtes suivantes :

```javascript
async function api(path, options = {}) {
    const headers = Object.assign({}, options.headers || {});
    if (state.token) headers['Authorization'] = 'Bearer ' + state.token;
    const res = await fetch(API_BASE + path, { ...options, headers });
    if (res.status === 401) {
        logoutSilently();
        showView('login');
        throw { status: 401 };
    }
    return res.json();
}
```

Si le token expire ou est révoqué, le serveur renvoie 401 → l'app déconnecte silencieusement et bascule sur l'écran de login.

## Installation sur les différents OS

| OS | Procédure |
|---|---|
| **Android (Chrome)** | Visite `.../mobile-app/` → menu ⋮ → "Installer l'application" |
| **iOS (Safari)** | Visite la page → bouton Partager → "Sur l'écran d'accueil" |
| **Windows 11 (Edge/Chrome)** | Visite la page → icône d'installation dans la barre d'URL |
| **macOS (Chrome/Safari)** | Idem Windows |

Sur Android, après installation, l'app apparaît dans le tiroir d'apps comme une vraie app, avec son icône et son label.

## Limites connues

- **iOS** : pas de notifications push avant iOS 16.4. Pas de `vibration`, `getBattery`.
- **Pas de mode offline** (par choix). L'app affiche `offline.html` si pas de réseau.
- **Pas d'éditeur WYSIWYG** : le contenu d'article est entré en texte brut (avec markdown si l'utilisateur veut).
- **Pas de Push notifications** non plus côté serveur — l'archi pour ça reste à construire (VAPID + table `push_subscriptions`).
