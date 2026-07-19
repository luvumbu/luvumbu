# 📁 Images du portfolio

Dépose ici tes images, puis renseigne leur chemin dans `config/portfolio.php`.
**Si un champ image est laissé vide → repli automatique** (émoji / dégradé). Rien ne casse.

| Où ça s'affiche | Champ dans `config/portfolio.php` | Fichier conseillé | Format idéal |
|---|---|---|---|
| Photo ronde dans le hero | `identite → avatar` | `images/avatar.jpg` | carré, 400×400 |
| Icône de l'onglet navigateur | `identite → favicon` | `images/favicon.svg` (ou .png) | 64×64 |
| Image de partage (Facebook, X, WhatsApp…) | `identite → og_image` | `images/og.png` | 1200×630 |
| Capture d'écran du projet phare | `projet → image` | `images/bokonzi.png` | 16:9, ~1600px large |
| Visuel d'une zone de la carte | `carte → projets → [ ] → img` | `images/projet-x.png` | carré, ~300×300 |

## Exemple
Dans `config/portfolio.php` :
```php
'identite' => [
  ...
  'avatar'  => 'images/avatar.jpg',
  'favicon' => 'images/favicon.svg',
],
'projet' => [
  ...
  'image' => 'images/bokonzi.png',
],
'carte' => [
  'projets' => [
    ['icon' => '🏟️', 'img' => 'images/bokonzi-map.png', 'nom' => 'BOKONZI', ...],
  ],
],
```

## Astuce
Les chemins sont **relatifs à la racine du portfolio** (`portefolio/`).
Tu peux aussi mettre une URL complète (`https://...`) — ça marche aussi.
