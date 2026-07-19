// Kill-switch : ce service worker ne met PLUS rien en cache.
// Il se désinstalle et vide les caches existants, pour éliminer tout contenu
// périmé (un ancien SW mettait en cache des chemins absolus erronés -> erreurs
// "addAll Request failed" + pages/CSS périmés).
self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (e) => {
    e.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.map(k => caches.delete(k)));
        try { await self.registration.unregister(); } catch (_) {}
    })());
});

// Aucun handler 'fetch' : toutes les requêtes passent normalement par le réseau.
