# Draw - Editeur de dessin vectoriel

Editeur de dessin vectoriel complet sur canvas HTML5, entierement en JavaScript vanilla (zero dependances).

## Fonctionnalites

### Outils de dessin (16 outils)

| Outil | Touche | Description |
|-------|--------|-------------|
| Selection | V | Deplacer, redimensionner, tourner, deformer |
| Crayon | P | Dessin a main levee |
| Ligne | L | Ligne droite avec snap |
| Rectangle | R | Rectangle avec coins arrondis optionnels |
| Cercle | C | Cercle depuis le centre |
| Ellipse | E | Ellipse avec rayons independants |
| Triangle | T | Triangle isocele |
| Fleche | A | Fleche unidirectionnelle |
| Double fleche | D | Fleche bidirectionnelle |
| Polygone | H | Polygone regulier (3-20 cotes) |
| Etoile | J | Etoile a branches |
| Bezier | B | Courbe de Bezier cubique (4 clics) |
| Texte | X | Texte avec edition inline |
| Gomme | G | Supprimer une forme au clic |
| Image | I | Importer une image (clic ou clic-glisse) |
| Pipette | K | Capturer une couleur du canvas |
| Mesure | M | Mesurer la distance entre 2 points |

### Selection et transformation

- **Carres blancs** (coins) : redimensionnement proportionnel
- **Losanges oranges** (bords) : etirement libre
- **Cercle vert** (dessus) : rotation
- **Shift+Drag** : deformation (skew)
- **Alt+Drag** : rotation libre

### Calques

- Ajout automatique d'un calque par forme
- Renommage par double-clic
- Reordonnement (monter/descendre/premier plan/arriere-plan)
- Copie/suppression
- **Visibilite** (oeil) : masquer sans supprimer
- **Verrouillage** (cadenas) : empecher la modification
- **Groupes** : creer, replier, drag-and-drop entre groupes

### Proprietes des formes

- **Contour** : couleur, epaisseur (1-20px)
- **Remplissage** : couleur avec toggle on/off
- **Opacite** : 5-100%
- **Style de trait** : continu, tirets, pointilles
- **Coins arrondis** : sur les rectangles (0-50px)
- **Ombre portee** : couleur configurable
- **Nombre de cotes** : pour polygones et etoiles (3-20)

### Couleur de fond

- Selecteur de couleur de fond du canvas dans la top-bar
- Sauvegardee avec le projet

### Grille et guides

- **Grille visuelle** : togglable, couvre toute la zone visible
- **Snap aux formes** : guides d'alignement automatiques (bords et centres)
- **Snap a la grille** : accrochage magnetique aux intersections

### Regles (rulers)

- Graduations horizontale et verticale autour du canvas
- Se mettent a jour avec le zoom et le pan
- Masquees en mode presentation

### Zoom et navigation

- **Molette** : zoom vers le curseur (10% a 500%)
- **Espace + drag** ou **clic molette** : deplacer la vue (pan)
- **Ctrl+0** : reset zoom a 100%
- Indicateur de zoom en haut a droite

### Export

- **PNG** : Ctrl+E ou bouton — telecharge le dessin en image
- **SVG** : Ctrl+Shift+E ou bouton — export vectoriel complet

Toutes les formes sont supportees dans l'export SVG : cercle, rectangle, ligne, ellipse, triangle, fleche, double fleche, polygone, etoile, bezier, crayon, texte, image.

### Sauvegarde (systeme de slots)

Systeme de sauvegardes style jeu video :

- **Ctrl+S** ou bouton : ouvre le gestionnaire de sauvegardes
- **Slots illimites** avec nom personnalise
- Chaque slot affiche :
  - Miniature du dessin
  - Nom du projet
  - Date et heure
  - Nombre de formes
  - Dimensions du canvas
- **Actions** : charger, ecraser, renommer, supprimer
- **Auto-save** : sauvegarde automatique toutes les 2 minutes
- **Migration** : l'ancienne sauvegarde unique est importee automatiquement

Les donnees sauvegardees incluent : formes, groupes, taille du canvas, couleur de fond, nom du projet.

### Gestion de projet

- **Bouton Nouveau** (vert) : demande le nom + dimensions, cree un canvas vierge
- **Bouton Taille** (bleu) : redimensionne le canvas sans effacer
- **Presets** : 800x600, 1024x768, HD 720, Full HD, Carre, Portrait
- **Nom du projet** visible dans la top-bar (double-clic pour renommer)
- Dimensions de 100px a 4000px

### Theme clair / sombre

- Bouton soleil dans la top-bar pour basculer
- Theme sombre par defaut (bleu fonce)
- Theme clair complet (gris clair)

### Mode presentation

- Bouton lecture dans la top-bar
- Plein ecran avec fond noir, sans interface
- Echap pour quitter

### Palette de couleurs recentes

- Barre de swatches sous la top-bar
- Se remplit automatiquement en utilisant les couleurs
- Clic gauche : appliquer en contour
- Clic droit : appliquer en remplissage

### Accessibilite

- Attributs `aria-label` sur tous les boutons
- Attributs `role="toolbar"` et `role="separator"`

## Raccourcis clavier

| Touche | Action |
|--------|--------|
| V | Selection |
| P | Crayon |
| L | Ligne |
| R | Rectangle |
| C | Cercle |
| E | Ellipse |
| T | Triangle |
| A | Fleche |
| D | Double fleche |
| H | Polygone |
| J | Etoile |
| B | Bezier |
| X | Texte |
| G | Gomme |
| I | Image |
| K | Pipette |
| M | Mesure |
| Ctrl+Z | Annuler |
| Ctrl+Y | Refaire |
| Ctrl+C | Copier |
| Ctrl+V | Coller |
| Ctrl+D | Dupliquer (decale de 20px) |
| Ctrl+A | Selectionner |
| Ctrl+S | Sauvegarder |
| Ctrl+E | Exporter PNG |
| Ctrl+Shift+E | Exporter SVG |
| Ctrl+0 | Reset zoom |
| +/- | Taille du trait |
| Molette | Zoom |
| Espace+Drag | Deplacer la vue (pan) |
| Suppr / Backspace | Supprimer la forme |
| Esc | Annuler l'action en cours |
| Shift+Drag | Deformer (skew) |
| Alt+Drag | Rotation libre |
| Double-clic | Editer texte / Renommer calque |

## Prerequis

- XAMPP (ou tout serveur web capable de servir du PHP)
- Navigateur moderne (Chrome, Firefox, Edge)

## Installation

1. Cloner ou copier le dossier dans `htdocs`
2. Acceder via `http://localhost/draw/`

## Architecture

```
draw/
  index.php              Point d'entree (HTML + init JS)
  README.md              Ce fichier
  css/
    style.css            Feuille de style principale
  class/
    Shape.js             Classe de base (style, transform, serialisation)
    Circle.js            Cercle
    Rect.js              Rectangle (avec coins arrondis)
    LineShape.js         Ligne (+ utilitaire _distToSegment)
    EllipseShape.js      Ellipse
    Triangle.js          Triangle
    Arrow.js             Fleche unidirectionnelle
    DoubleArrow.js       Fleche bidirectionnelle
    PolygonShape.js      Polygone regulier / etoile
    BezierShape.js       Courbe de Bezier cubique
    Pencil.js            Trait libre
    TextShape.js         Texte (edition inline)
    ImageShape.js        Image (base64, redraw auto)
    ShapeFactory.js      Deserialisation (Shape.deserialize)
    History.js           Pile undo/redo (50 etats max)
    LayerManager.js      Calques, groupes, sidebar, visibilite, verrouillage
    CanvasApp.js         Controleur principal (outils, zoom, export, sauvegardes)
```

### Ordre de chargement des scripts

1. `Shape.js` — classe de base
2. `Circle.js`, `Rect.js`, `LineShape.js` — formes de base
3. `EllipseShape.js`, `Triangle.js` — formes geometriques
4. `Arrow.js`, `Pencil.js` — dependent de `LineShape._distToSegment`
5. `TextShape.js`, `ImageShape.js` — formes speciales
6. `BezierShape.js`, `PolygonShape.js`, `DoubleArrow.js` — formes avancees
7. `ShapeFactory.js` — factory (a besoin de toutes les formes)
8. `History.js` — standalone
9. `LayerManager.js` — gestion des calques
10. `CanvasApp.js` — controleur principal

### Hierarchie des classes

```
Shape (base)
  ├── Circle
  ├── Rect (+ borderRadius)
  ├── LineShape (+ _distToSegment partage)
  ├── EllipseShape
  ├── Triangle
  ├── Arrow
  ├── DoubleArrow
  ├── PolygonShape (polygone + etoile)
  ├── BezierShape
  ├── Pencil
  ├── TextShape
  └── ImageShape

LayerManager    — calques, groupes, visibilite, verrouillage, z-order
History         — pile undo/redo
CanvasApp       — controleur, outils, zoom, export, sauvegardes, themes
```

## Proprietes des formes (Shape)

Chaque forme herite de `Shape` et dispose des proprietes suivantes :

| Propriete | Type | Description |
|-----------|------|-------------|
| strokeColor | string | Couleur du contour |
| fillColor | string | Couleur de remplissage |
| useFill | boolean | Remplissage actif |
| lineWidth | number | Epaisseur du trait (1-20) |
| opacity | number | Opacite (0-1) |
| dashStyle | string | Style de trait (solid/dashed/dotted) |
| rotation | number | Rotation en radians |
| skewX / skewY | number | Deformation |
| locked | boolean | Verrouille |
| visible | boolean | Visible |
| shadowColor | string | Couleur de l'ombre (vide = pas d'ombre) |
| shadowBlur | number | Flou de l'ombre |
| groupId | number | ID du groupe parent |

## Stockage

- **Sauvegardes** : `localStorage` cle `draw_saves` (tableau JSON de slots)
- **Format** : objet avec layers, groups, canvasWidth, canvasHeight, bgColor, projectName
- **Retrocompatible** avec l'ancien format (cle `draw_save`, tableau simple)
- **Miniatures** : generees en 160px de large, stockees en base64

## Technique

- **Zero dependances** : pas de npm, pas de framework, pas de build
- **100% client-side** : aucun appel serveur (PHP sert juste le HTML)
- **ES6+** : classes, arrow functions, template literals, destructuring
- **Canvas 2D** : rendu via l'API Canvas standard
- **Transformations** : rotation et skew via matrice inverse pour le hit-testing
- **Zoom** : via `setTransform` sur le contexte canvas
