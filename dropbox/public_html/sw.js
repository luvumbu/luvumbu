/* ============================================================================
 * Service worker PhotoSync
 *
 * Volontairement minimal, parce que la galerie est une application *connectée*
 * et *privée* :
 *   - on ne met JAMAIS en cache une page HTML ni une réponse d'api/ : elles
 *     dépendent du compte connecté, un cache ferait réapparaître les fichiers
 *     d'un compte après déconnexion (ou changement d'utilisateur) ;
 *   - on ne met pas non plus les photos en cache : le stockage du téléphone
 *     serait rempli en silence.
 * On se contente donc de la coquille (icônes, manifeste) et d'une page « hors
 * connexion », ce qui suffit à rendre l'app installable et à éviter le dinosaure
 * du navigateur. Le reste passe directement au réseau.
 *
 * Les chemins sont relatifs à l'emplacement de ce fichier (…/dropbox/), donc
 * l'app fonctionne à l'identique en local (/luvumbu/dropbox/) et en ligne.
 * ========================================================================== */

const CACHE = 'photosync-shell-v1';
const SHELL = [
  './offline.html',
  './favicon.svg',
  './assets/icon-192.png',
  './assets/icon-512.png',
  './assets/apple-touch-icon.png',
];

self.addEventListener('install', e => {
  self.skipWaiting();
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).catch(() => {}));
});

self.addEventListener('activate', e => {
  e.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;   // Google, polices… : on ne touche pas.

  // Navigation : toujours le réseau ; hors connexion → page d'attente.
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('./offline.html')));
    return;
  }

  // Coquille statique (icônes) : cache d'abord, rafraîchi en tâche de fond.
  if (url.pathname.includes('/assets/') || url.pathname.endsWith('/favicon.svg')) {
    e.respondWith((async () => {
      const hit = await caches.match(req, { ignoreSearch: true });
      const net = fetch(req).then(res => {
        if (res && res.ok) caches.open(CACHE).then(c => c.put(req, res.clone())).catch(() => {});
        return res;
      }).catch(() => hit);
      return hit || net;
    })());
  }

  // Tout le reste (api/, media.php, ZIP…) : réseau direct, sans interception.
});
