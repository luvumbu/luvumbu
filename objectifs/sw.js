/* Service worker Élan — réseau d'abord, repli cache (app connectée au serveur). */
const CACHE = 'elan-v1';
const SHELL = ['/objectifs/', '/objectifs/icon-192.png', '/objectifs/icon-512.png', '/objectifs/manifest.webmanifest'];

self.addEventListener('install', e => {
  self.skipWaiting();
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL).catch(() => {})));
});
self.addEventListener('activate', e => {
  e.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)));
    await self.clients.claim();
  })());
});
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  e.respondWith((async () => {
    try {
      const res = await fetch(e.request);
      if (res && res.ok && e.request.url.startsWith(self.location.origin)) {
        const clone = res.clone();
        caches.open(CACHE).then(c => c.put(e.request, clone)).catch(() => {});
      }
      return res;
    } catch (err) {
      const cached = await caches.match(e.request);
      return cached || caches.match('/objectifs/');
    }
  })());
});
