# 3D_simulator — notes pour Claude

Tutoriel Three.js **en français**, servi par XAMPP depuis `htdocs`. Pas de build, pas de npm,
pas de dépôt Git. Three.js arrive du CDN jsDelivr, **épinglé en 0.160.0**, via une import map.

Ouvrir une page en `file://` échoue (CORS sur les modules ES). Toujours passer par
`http://localhost/3D_simulator/`.

## Structure

| Chemin | Rôle |
|---|---|
| `index.html` | sommaire, une carte `<a>` par page |
| `debutant/` | niveau 1 — une seule idée nouvelle par étape |
| `formes/` | **généré** — un sous-chapitre par géométrie (19 pages) |
| `astres/` | **généré** — une simulation seule par astre (3 astres × 3 sims = 9 pages) |
| `matieres/` | **généré** — un écrasement seul par matière (3 pages) |
| `lecons/` | niveau 2 — plusieurs notions par page |
| `editeur.html` | bac à sable plein écran — 5 exemples FINIS à démonter |
| `atelier.html` | **exercices** — un script qu'on écrit et qui pilote la scène en direct |
| `js/` | l'infrastructure partagée (voir plus bas) |
| `css/style.css` | **toute** la CSS du projet, un seul fichier |
| `models/` | `.glb` générés par les scripts Python |
| `scripts/` | générateurs — Blender (`.py`) et les pages de `formes/` (`.mjs`) |
| `README.md` | le cours écrit, en 10 étapes — le pendant texte des pages |

## Anatomie d'une page (débutant ou leçon)

Toutes suivent le même moule, et l'ordre des balises est contraint :

```html
<link rel="stylesheet" href="../css/style.css">
<script type="importmap">…</script>   <!-- AVANT tout script type="module" -->
…
<div class="hud">…</div>              <!-- titre, explication, réglages, liens ←/→ -->
<script type="module">…</script>      <!-- LA démo. Doit être le 1er module inline -->
<script type="module" src="../js/code-panel.js"></script>
```

Trois règles à ne pas casser :

1. **Le `<script type="module">` inline de la démo doit être le premier du fichier.**
   `js/source.js` l'extrait avec la regex `/<script type="module">([\s\S]*?)<\/script>/`.
   Elle attrape le premier `>` collé à `"module"` — les scripts avec `src` y échappent grâce
   à leur attribut. Corollaire : ce code ne doit jamais contenir la chaîne `</script>`.
2. **Le HUD porte la classe `.hud`** — `code-panel.js` le recopie dans l'iframe, sinon les
   `getElementById` de la démo n'y trouvent rien et les curseurs deviennent inertes.
3. **Tout ce dont la démo a besoin doit vivre dans son module**, y compris les retouches de
   la page (`document.body.style.…`). Un `<style>` ajouté au fichier ne suit ni dans
   l'iframe ni dans la page exportée.

## L'infrastructure `js/`

- `source.js` — extrait et désindente le code d'une page. Aucune recopie manuelle nulle part :
  le code affiché **est** le code exécuté.
- `code-panel.js` — le bouton `</> Éditer le code` de chaque leçon. Se charge tout seul,
  sans configuration.
- `bac-a-sable.js` — exécute le code dans une **iframe recréée à chaque lancement**. C'est ce
  qui rend l'édition en direct viable : une erreur n'emporte que l'aperçu, et l'ancienne boucle
  de rendu comme l'ancien contexte WebGL meurent avec l'iframe au lieu de s'empiler.
- `page-autonome.js` — `Copier`/`↓` produisent une **page HTML complète** (import map + CSS du
  HUD + HUD + code), pas le JS seul, qui ne tournerait nulle part.
  ⚠️ `CSS_HUD` y duplique une version réduite de `css/style.css` : **une règle `.hud` ajoutée
  à `css/style.css` doit y être répercutée**, sinon l'export diverge de la leçon.
- `editeur-widget.js` + `highlight.js` — `<textarea>` transparent superposé au pixel près à un
  `<pre>` coloré. Toute divergence de police/interligne entre les deux décale le curseur.
- `exemples.js` — les exemples de départ du bac à sable (`editeur.html`).
- `atelier-exercices.js` — les exercices de `atelier.html`. Chacun a une `consigne`, une
  liste d'`essais`, et parfois une `solution`.
  ⚠️ **`atelier.html` n'utilise PAS `bac-a-sable.js`**, et c'est le cœur du sujet.
  `editeur.html` REMPLACE la page (le code de l'utilisateur *est* la page, dans une iframe
  jetable) ; `atelier.html` fait tourner **une seule scène, dans la page**, et le script
  vient AGIR dessus via `new Function` — d'où l'accès direct à nos objets, impossible à
  travers une iframe. Un exercice n'est donc **pas** un module : pas d'`import`, tout
  arrive en argument (`THREE`, `scene`, `camera`, `renderer`, `ajouter`, `chaqueImage`,
  `log`). Le script est rejoué sur la même scène à chaque lancement : `nettoyer()` retire
  ce que le précédent avait ajouté.

## Conventions d'écriture

- **Tout en français**, y compris les identifiants du code (`forme`, `apparence`, `soleil`,
  `dessiner`, `animer`). Le vouvoiement.
- Chaque page débutant reprend le code de la précédente sous un commentaire
  `// --- Le socle de l'étape N, inchangé ---`, puis isole l'ajout sous
  `// --- Ce qui est nouveau ---`. Le HUD se termine par `<b>Nouveau ici :</b> …`.
- Les commentaires expliquent **pourquoi**, et surtout **ce qui casse si on l'oublie**. C'est
  la signature du projet : chaque notion arrive avec son piège (caméra dans le cube, `dt` non
  plafonné, `enableDamping` sans `update()`, les trois activations d'ombre…).
- Les liens `← Précédent` / `Suivant →` en bas de chaque HUD forment la chaîne de navigation.
  **Insérer une étape oblige à retoucher les deux pages voisines**, `index.html` et le tableau
  du README.
- Palette (reprise de `css/style.css`) : fond `0x0e1116`, accent `0x58a6ff`, vert `0x7ee787`,
  orange `0xf78166`.
- Les étapes ajoutées après coup sont **suffixées** (`00b`, `01b`, `01c`, `04b`, `05b`) pour ne
  pas renuméroter les URLs existantes. Ce sont des « détours » : elles ne font avancer aucune
  démo, elles s'arrêtent sur une notion que le fil principal survole.
- Les pages **`N.1`**, **`N.2`** (`03.1`, `03.2`…) sont des **simulations** : elles appliquent
  l'étape `N` sans rien enseigner de neuf. Carte orange (`.card-simu`) au sommaire.
  **Une simulation exige la boucle de rendu** : rien n'est possible avant l'étape 3, d'où
  l'absence de `00.1`/`01.1`/`02.1`.
- **Les lumières ne se filtrent PAS par objet.** `light.layers` est testé contre la **caméra**
  (`object.layers.test(camera.layers)`, source de Three.js r160) : une lumière éclaire tout ou
  rien. Pour montrer plusieurs éclairages côte à côte (`04.1`), il faut rendre la scène
  plusieurs fois avec `setViewport` + `setScissor` en basculant `light.visible` entre les
  passes — et sans `setScissorTest(true)`, chaque passe efface tout le canvas.
- **Une simulation compare, elle ne bascule pas.** Lune / Terre / Jupiter tournent *côte à
  côte en même temps*, jamais via des boutons qui changent `g` : on n'apprend rien en
  mémorisant l'écran précédent. Même palette partout — Lune `0x8b949e`, Terre `0x58a6ff`,
  Jupiter `0xf78166` — et vraies valeurs (1.62 / 9.81 / 24.79 m/s²).
- Les caméras des simulations sont **décentrées** (`lookAt` en x ≈ 0.2, recul z ≈ 12) : le HUD
  mange le tiers gauche, et viser le milieu des trois colonnes cacherait la Lune derrière lui.
- **Tout chiffre affiché par une simulation se mesure avant d'être écrit.** `03.1` colle à
  `√(2h/g)` et `03.2` à la théorie exacte (intégrale elliptique) à 0,3 % près — ce qui a exigé
  8 **sous-pas** d'intégration par frame dans `03.2` : à 1/60 s, Euler « coupait le virage »
  au point bas et la période sortait 15 % trop courte.
- **`formes/`, `astres/` et `matieres/` ne s'éditent pas à la main.** Elles sortent de
  `scripts/creer-pages-formes.mjs`, `creer-pages-astres.mjs` et `creer-pages-matieres.mjs` ;
  toute correction se fait dans le tableau du script, puis on relance. Éditer un `.html`
  généré serait écrasé à la génération suivante.
- **Le contact ne se résout PAS en repositionnant l'objet.** Un `position.y = sol` téléporte :
  à 10 m/s et 1/60 s, l'objet s'enfonce de 17 cm avant qu'on le détecte, et le remonter d'un
  coup se VOIT. Modèle correct : le sol est un ressort (pénalité), l'objet s'y enfonce, et la
  déformation *est* l'enfoncement. Avec 12 sous-pas par image.
- **Un impact dure 60 ms** — quatre images. Sa durée `π/ω` et sa profondeur `v/ω` sont liées
  par le même `ω` : on ne peut pas avoir profond ET lent. La seule sortie honnête est un
  curseur de **ralenti** sur `dt`, jamais un truquage de la physique.
- **Groupé ET séparé, les deux.** `debutant/03.1`, `03.2`, `06.1` comparent les trois astres
  côte à côte ; `astres/` les isole un par un, avec les faits propres à chacun. Aucune ne
  remplace l'autre — c'est une demande explicite de l'utilisateur.
- Le sommaire est **rangé par thèmes** (`<h3 class="groupe">`), qui regroupent des étapes
  consécutives sans toucher à l'ordre : la progression reste linéaire, chaque étape s'appuyant
  sur la précédente.
- Les curseurs d'**angle** sont en **degrés**, convertis par `THREE.MathUtils.degToRad()`. Ce
  n'est pas cosmétique : un `<input type="range">` ne peut atteindre que des valeurs alignées
  sur son `step`, donc `max="6.28" step="0.05"` plafonne à 6.25 — un tour complet deviendrait
  inatteignable et une sphère ne pourrait jamais se refermer.

## Pièges Three.js déjà documentés

Le README les couvre en détail — inutile de les réexpliquer, y renvoyer. Résumé : écran noir
(rien d'ajouté / caméra dans l'objet), objet noir (aucune lumière / métal sans
`scene.environment`), CORS en `file://`, ombres (trois activations + cadrage du
`shadow.camera`), delta time non plafonné, `dispose()` du GPU.
