// Service Worker "kill switch" : il s'auto-désinstalle et vide tout le cache.
// À garder déployé pour que les anciennes versions installées chez les users se nettoient.

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
