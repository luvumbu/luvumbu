<?php
// === Manifeste de l'application installable (PWA) ===
//
// Servi par PHP plutôt que par un fichier .webmanifest statique : Apache ne
// connaît pas cette extension par défaut et la renverrait avec un mauvais type
// MIME (Chrome refuse alors l'installation). Passer par PHP évite d'avoir à
// toucher au .htaccess ou à la configuration de l'hébergement.
//
// Les chemins sont relatifs à CE fichier (racine de l'app), donc identiques en
// local (/luvumbu/dropbox/) et en ligne (luvumbu.com/dropbox/).

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
?>
{
  "id": "./",
  "name": "PhotoSync — mes photos",
  "short_name": "PhotoSync",
  "description": "Ma galerie personnelle : photos, vidéos et documents sauvegardés sur mon serveur, consultables et partageables depuis n'importe quel appareil.",
  "start_url": "./web/gallery.php?src=pwa",
  "scope": "./",
  "display": "standalone",
  "display_override": ["standalone", "minimal-ui", "browser"],
  "background_color": "#0b1220",
  "theme_color": "#0b1220",
  "lang": "fr",
  "dir": "ltr",
  "categories": ["photo", "productivity", "utilities"],
  "prefer_related_applications": false,
  "icons": [
    { "src": "assets/icon-192.png?v=1", "sizes": "192x192", "type": "image/png", "purpose": "any" },
    { "src": "assets/icon-512.png?v=1", "sizes": "512x512", "type": "image/png", "purpose": "any" },
    { "src": "assets/icon-192-maskable.png?v=1", "sizes": "192x192", "type": "image/png", "purpose": "maskable" },
    { "src": "assets/icon-512-maskable.png?v=1", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
  ],
  "shortcuts": [
    {
      "name": "Envoyer des photos",
      "short_name": "Envoyer",
      "description": "Ajouter des fichiers depuis cet appareil",
      "url": "web/upload_web.php",
      "icons": [{ "src": "assets/icon-192.png?v=1", "sizes": "192x192", "type": "image/png" }]
    },
    {
      "name": "Mes albums",
      "short_name": "Albums",
      "description": "Les albums partageables",
      "url": "web/albums.php",
      "icons": [{ "src": "assets/icon-192.png?v=1", "sizes": "192x192", "type": "image/png" }]
    },
    {
      "name": "Corbeille",
      "short_name": "Corbeille",
      "description": "Fichiers supprimés (30 jours)",
      "url": "web/gallery.php?view=corbeille",
      "icons": [{ "src": "assets/icon-192.png?v=1", "sizes": "192x192", "type": "image/png" }]
    }
  ]
}
