/* Service worker — Anniversaires (PWA)
 * Rend l'app installable et utilisable hors-ligne pour l'interface.
 * Les appels à api.php passent TOUJOURS par le réseau (données à jour + session).
 */
const CACHE = "anniv-v1";

// Ressources de l'"app shell" mises en cache dès l'installation.
const SHELL = [
  "./",
  "./index.html",
  "./icon-192.png",
  "./icon-512.png",
  "./manifest.webmanifest",
];

self.addEventListener("install", (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener("activate", (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (e) => {
  const req = e.request;
  const url = new URL(req.url);

  // 1) API et requêtes non-GET : réseau uniquement (jamais de cache).
  if (req.method !== "GET" || url.pathname.endsWith("/api.php")) {
    return; // laisse le navigateur faire la requête réseau normale
  }

  // 2) Autre origine (polices Google, endpoints Google) : réseau simple.
  if (url.origin !== self.location.origin) {
    return;
  }

  // 3) Navigation (ouverture d'une page/URL propre) : réseau, repli sur index.html en cache.
  if (req.mode === "navigate") {
    e.respondWith(
      fetch(req).catch(() => caches.match("./index.html"))
    );
    return;
  }

  // 4) Ressources statiques : cache d'abord, sinon réseau (et on met en cache au passage).
  e.respondWith(
    caches.match(req).then((hit) =>
      hit ||
      fetch(req).then((res) => {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
        return res;
      })
    )
  );
});
