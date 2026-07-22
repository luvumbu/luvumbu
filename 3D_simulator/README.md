# Tutoriel Three.js — de zéro à un mini-simulateur

Ce dossier contient des pages exécutables **et** ce guide, qui explique comment on écrit le code
plutôt que de se contenter de le montrer.

## Modifier le code en direct

Deux endroits, le même moteur.

**Sur chaque leçon**, le bouton `</> Éditer le code` ouvre un panneau où le code est
**modifiable**. Il se relance 800 ms après la dernière frappe (ou sur `Ctrl+Entrée`, ou en
décochant `auto` puis en cliquant `Exécuter`). `Rétablir` revient au code d'origine.

**`editeur.html`** est le même atelier en plein écran : code à gauche, rendu à droite, console
en bas, et cinq exemples autonomes comme points de départ.

### Emporter un exemple ailleurs

`Copier` et `↓` ne produisent pas le JavaScript seul — il ne tournerait nulle part, car
`import ... from 'three'` a besoin de l'import map. Ils génèrent une **page HTML complète et
autonome** : import map, CSS du HUD, HUD, et votre code. Enregistrez-la en `.html`, ouvrez-la
dans n'importe quel navigateur, elle marche. Rien à installer, Three.js vient du CDN — il faut
donc une connexion internet.

C'est la seule situation où ouvrir un fichier en `file://` fonctionne : le module est *inline*,
pas chargé depuis un fichier voisin. Si un navigateur venait quand même à le bloquer, reposez le
fichier dans `htdocs` et passez par `http://localhost`.

### La rendre autonome pour de bon (sans internet)

Le CDN reste une dépendance. Pour vous en passer, téléchargez Three.js à côté de votre page :

```bash
curl -o three.module.js https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js
```

puis changez l'import map de la page exportée :

```html
<script type="importmap">
{ "imports": { "three": "./three.module.js" } }
</script>
```

Attention : le module est maintenant chargé **depuis un fichier voisin**, ce que `file://`
interdit (CORS). Il faut donc servir le dossier en HTTP — `htdocs` fait très bien l'affaire.
Et si votre code importe un addon (`OrbitControls`, `GLTFLoader`…), récupérez aussi le dossier
`examples/jsm/` et faites-y pointer la clé `"three/addons/"`.

### Comment ça marche

Dans les deux cas, le code tourne dans une **iframe recréée à chaque exécution**. C'est ce qui
rend l'édition en direct praticable : une erreur de syntaxe n'emporte que l'aperçu, et l'ancienne
boucle de rendu comme l'ancien contexte WebGL sont détruits avec l'iframe au lieu de s'empiler.
Sur une leçon, le HUD (les curseurs, les cases à cocher) est recopié dans l'iframe pour que les
réglages de la leçon continuent de fonctionner sur votre version modifiée.

## Par où commencer

Si vous n'avez jamais fait de 3D, **ne commencez pas par les leçons** : elles combinent plusieurs
notions par page. Faites d'abord les étapes de `debutant/`, dans l'ordre. Chacune n'ajoute
qu'une seule idée au code de la précédente.

Les étapes **suffixées d'une lettre** ne font avancer aucune démo : elles s'arrêtent sur une
notion que le fil principal survole. Celles **suffixées d'un point** (`03.1`…) sont des
**simulations** : elles n'apprennent rien de neuf, elles font *servir* l'étape dont elles
portent le numéro. Le chemin court (`00` → `06`) reste valable ; prenez les détours quand
quelque chose vous a laissé un doute.

Une simulation suppose la **boucle de rendu** : avant l'étape 3, l'écran ne se redessine que
sur action, donc rien ne peut tomber ni avancer tout seul. C'est pourquoi la première
simulation porte le numéro 3.1 et pas 0.1.

| Étape | Ce qu'elle ajoute |
|---|---|
| `debutant/00-canvas-vide.html` | scène, caméra, renderer |
| `debutant/00b-couleur-du-fond.html` | l'hexadécimal, `THREE.Color`, `alpha`, `outputColorSpace` |
| `debutant/01-premier-cube.html` | geometry + material = mesh, `scene.add()` |
| `debutant/01b-couleur-du-cube.html` | `material.color`, `wireframe`, `opacity`, `side` |
| `debutant/01c-repere-3d.html` | X/Y/Z, le « Y en haut », les unités, les radians |
| `debutant/02-bouger.html` | `position`, `rotation`, `scale` |
| `debutant/02b-la-camera.html` | FOV, `near`/`far`, z-fighting, `updateProjectionMatrix()` |
| `debutant/03-animation.html` | la boucle `requestAnimationFrame` |
| `debutant/03.1-chute-et-rebond.html` | **simulation** — gravité, rebond, amortissement |
| `debutant/03.2-pendule.html` | **simulation** — oscillation, et les limites de 2π√(L/g) |
| `debutant/03.3-ressort.html` | **simulation** — amortissement, et le piège du lissage |
| `debutant/03.4-ecrasement.html` | **simulation** — squash & stretch, volume, fragmentation |
| `debutant/04-lumiere.html` | `AmbientLight`, `DirectionalLight` |
| `debutant/04.1-jour-nuit.html` | **simulation** — cycle solaire, 3 éclairages en viewports |
| `debutant/04b-autour-du-cube.html` | le sol, la grille, `scene.fog`, `scene.environment` |
| `debutant/05-plusieurs-objets.html` | d'autres géométries, le repère des axes |
| `debutant/05.1-systeme-solaire.html` | **simulation** — hiérarchie parent/enfant, orbites |
| `debutant/05.3-mille-objets.html` | **simulation** — appels de dessin, `InstancedMesh` |
| `debutant/05b-toutes-les-formes.html` | les 19 géométries, le compromis segments / triangles |
| `debutant/06-souris.html` | `OrbitControls`, redimensionnement |
| `debutant/06.1-tir-au-but.html` | **simulation** — balistique, clic contre glisser |

### Chaque astre, seul

Les simulations `03.1`, `03.2` et `06.1` **comparent** Lune, Terre et Jupiter côte à côte —
c'est la comparaison qui instruit. Le dossier **`astres/`** fait l'inverse : un seul astre à
l'écran, ses chiffres, et ce qu'on sait de lui. 3 astres × 3 simulations = 9 pages, générées :

```bash
node scripts/creer-pages-astres.mjs
```

### Les 19 formes, une par une

`debutant/05b` montre que les géométries existent. Le dossier **`formes/`** apprend à s'en
servir : **une page par géométrie**, seule à l'écran, avec tous ses paramètres en curseurs,
son appel de constructeur qui s'écrit en direct, son compte de triangles et ses pièges.

Ces 19 pages sont **générées** — la plomberie y est identique, seuls changent les paramètres
et les pièges :

```bash
node scripts/creer-pages-formes.mjs
```

Pour corriger une page, modifiez le tableau `FORMES` du script et relancez-le : les 19 restent
cohérentes. Même parti pris que `scripts/creer-robot.py` pour le modèle 3D — le résultat est
reproductible et se versionne.

À la fin de l'étape 6, vous aurez sous les yeux le squelette complet d'une application Three.js.
Les huit leçons de `lecons/` ne font que le remplir, et la suite de ce guide en détaille chaque
partie. Sur toutes les pages, le bouton **`</> Voir le code`** affiche le source commenté.

## Lancer le projet

Three.js s'utilise avec des modules ES. Ouvrir un fichier avec un double-clic (`file://`) déclenche
une erreur CORS : le navigateur refuse de charger un module depuis le disque.
Comme le projet est dans `htdocs`, démarrez Apache dans XAMPP puis allez sur :

```
http://localhost/3D_simulator/
```

---

## Étape 0 — Installer Three.js sans rien installer

La méthode classique passe par npm et un bundler. Pour apprendre, l'**import map** suffit :
c'est une balise `<script>` qui indique au navigateur où trouver le module nommé `three`.

```html
<script type="importmap">
{
  "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
  }
}
</script>

<script type="module">
  import * as THREE from 'three';
  import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
</script>
```

Deux règles à retenir :

- `type="module"` est obligatoire, sinon `import` est une erreur de syntaxe.
- L'import map doit apparaître **avant** le premier `<script type="module">`.

Épinglez toujours une version (`@0.160.0`). Three.js casse régulièrement son API entre versions ;
sans numéro, votre page marche aujourd'hui et plante dans six mois.

> Pour un vrai projet : `npm install three` + Vite. Tout le code de ce tutoriel fonctionne à
> l'identique, seule la ligne d'import map disparaît.

---

## Étape 1 — Le trio scène / caméra / renderer

Toute application Three.js répond à la même question : **quoi** afficher (la scène), **depuis où**
(la caméra), et **où dessiner le résultat** (le renderer).

### La scène

C'est un graphe d'objets, un simple arbre. Tout ce que vous voulez voir doit y être ajouté.

```js
const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0e1116);
```

### La caméra

`PerspectiveCamera` imite l'œil humain : les objets lointains rétrécissent.

```js
const camera = new THREE.PerspectiveCamera(
  75,                          // FOV : champ de vision vertical, en degrés
  innerWidth / innerHeight,    // ratio d'aspect = largeur / hauteur du canvas
  0.1,                         // near : rien de plus proche n'est dessiné
  1000                         // far  : rien de plus lointain n'est dessiné
);
camera.position.z = 3;
```

**Le piège du débutant** : une caméra fraîchement créée est en `(0, 0, 0)`, et un cube créé
au même endroit l'est aussi. La caméra se retrouve *à l'intérieur* du cube, et comme les faces
arrière ne sont pas dessinées par défaut, l'écran reste noir. D'où le `camera.position.z = 3`.

Le couple `near`/`far` n'est pas anodin : un rapport `far/near` énorme (par exemple `0.001` et
`100000`) épuise la précision du z-buffer et produit du *z-fighting*, ces surfaces qui clignotent
quand elles se chevauchent. Gardez `near` aussi grand que possible.

### Le renderer

Il traduit la scène en appels WebGL et remplit un `<canvas>`.

```js
const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setSize(innerWidth, innerHeight);
renderer.setPixelRatio(Math.min(devicePixelRatio, 2));  // sur un écran 4K, 3 ou 4 ferait ramer
document.body.appendChild(renderer.domElement);
```

---

## Étape 2 — Afficher un objet : Mesh = Geometry + Material

Un objet visible est toujours la combinaison de deux choses.

- La **géométrie** est la forme : une liste de sommets et de triangles.
- Le **matériau** est l'apparence : couleur, brillance, réaction à la lumière.

```js
const geometry = new THREE.BoxGeometry(1, 1, 1);
const material = new THREE.MeshNormalMaterial();
const cube = new THREE.Mesh(geometry, material);
scene.add(cube);   // sans cette ligne, l'objet existe mais n'est pas affiché
```

**Partagez** géométries et matériaux. 500 cubes identiques doivent réutiliser la même
`BoxGeometry` et le même matériau : c'est un seul envoi vers la carte graphique au lieu de 500.
En revanche, si vous voulez changer la couleur d'un cube au clic, il lui faut son propre matériau,
sinon les 500 changent en même temps (leçon 05).

### Quel matériau choisir ?

| Matériau | Réagit à la lumière | Usage |
|---|---|---|
| `MeshBasicMaterial` | non | aplats de couleur, wireframe, debug |
| `MeshNormalMaterial` | non | debug de l'orientation des faces |
| `MeshLambertMaterial` | oui, diffus seulement | surfaces mates, très rapide |
| `MeshStandardMaterial` | oui, PBR complet | le choix par défaut aujourd'hui |

Si un objet apparaît **entièrement noir**, la cause est presque toujours l'une des deux :
son matériau réagit à la lumière et il n'y a aucune lumière dans la scène, ou bien c'est un
matériau métallique sans environnement à réfléchir (voir étape 6).

---

## Étape 3 — La boucle de rendu

Rien ne bouge tant que vous ne redessinez pas. `requestAnimationFrame` demande au navigateur
de rappeler votre fonction juste avant le prochain rafraîchissement de l'écran.

```js
function animate() {
  requestAnimationFrame(animate);   // réserve la frame suivante
  cube.rotation.y += 0.01;
  renderer.render(scene, camera);   // le dessin effectif
}
animate();
```

### Le problème du delta time

`+= 0.01` par frame veut dire « 0,6 rad/s sur un écran 60 Hz » — et le double sur un écran 120 Hz.
Votre animation change de vitesse selon le matériel. La correction consiste à raisonner par
seconde et à multiplier par le temps réellement écoulé :

```js
const clock = new THREE.Clock();

function animate() {
  requestAnimationFrame(animate);
  const dt = Math.min(clock.getDelta(), 1 / 30);   // secondes depuis la frame précédente
  cube.rotation.y += 0.6 * dt;                     // 0,6 rad/s, sur n'importe quel écran
  renderer.render(scene, camera);
}
```

Le `Math.min` n'est pas décoratif. Quand l'utilisateur change d'onglet, `requestAnimationFrame`
se met en pause ; au retour, `getDelta()` peut valoir 30 secondes. Dans une simulation physique,
un objet avancerait de 30 × sa vitesse en un pas et traverserait le sol. On plafonne donc `dt`.

### Redimensionnement

Sans ce bloc, l'image se déforme dès que la fenêtre change de taille.

```js
addEventListener('resize', () => {
  camera.aspect = innerWidth / innerHeight;
  camera.updateProjectionMatrix();   // obligatoire : recalcule la matrice de projection
  renderer.setSize(innerWidth, innerHeight);
});
```

---

## Étape 4 — Lumières et ombres

Quatre lumières couvrent l'essentiel des besoins :

```js
scene.add(new THREE.AmbientLight(0xffffff, 0.3));            // uniforme, aucune ombre, aucun relief

const soleil = new THREE.DirectionalLight(0xffffff, 2);      // rayons parallèles, comme le soleil
soleil.position.set(5, 8, 4);                                // seule la DIRECTION compte, pas la distance

const ampoule = new THREE.PointLight(0xffffff, 30, 20);      // (couleur, intensité, portée)
const projecteur = new THREE.SpotLight(0xffffff, 60, 20, Math.PI / 7);   // + angle du cône
```

Une scène crédible mélange une ambiante faible (pour que les zones d'ombre ne soient pas noires)
et une directionnelle forte (pour le modelé et les ombres portées).

### Les ombres demandent trois activations

C'est la source d'erreur numéro un : oublier l'une des trois.

```js
renderer.shadowMap.enabled = true;   // 1. sur le renderer
lumiere.castShadow = true;           // 2. sur la lumière qui projette
objet.castShadow = true;             // 3a. sur l'objet qui projette son ombre
sol.receiveShadow = true;            // 3b. sur l'objet qui reçoit l'ombre
```

Un objet peut faire les deux. Le sol, lui, ne fait généralement que recevoir.

### Cadrer la caméra d'ombre

Une `DirectionalLight` rend la scène une seconde fois depuis son point de vue, avec une caméra
**orthographique** dont le volume par défaut est petit (±5 unités). Tout ce qui déborde n'a pas
d'ombre. Si vos ombres sont absentes ou en escalier, agrandissez ce volume :

```js
soleil.shadow.camera.top = soleil.shadow.camera.right = 15;
soleil.shadow.camera.bottom = soleil.shadow.camera.left = -15;
soleil.shadow.mapSize.set(1024, 1024);   // résolution ; 2048 est plus net mais plus lourd
```

Et n'agrandissez pas au-delà du nécessaire : la même résolution étalée sur une zone deux fois
plus grande, ce sont des ombres deux fois plus pixellisées.

Pour voir où se trouvent réellement vos lumières, ajoutez un helper :
`scene.add(new THREE.DirectionalLightHelper(soleil, 1))`.

---

## Étape 5 — Textures

Une texture est une image plaquée sur la surface. `TextureLoader` en charge une depuis un fichier,
mais on peut aussi la fabriquer dans un `<canvas>` — c'est ce que fait la leçon 04, pour éviter
tout téléchargement.

```js
const map = new THREE.TextureLoader().load('bois.jpg');
map.colorSpace = THREE.SRGBColorSpace;       // indispensable pour une texture de COULEUR
map.wrapS = map.wrapT = THREE.RepeatWrapping;
map.repeat.set(3, 3);                        // carrelage 3×3 au lieu d'un étirement

const material = new THREE.MeshStandardMaterial({
  map,                 // la couleur de base (albédo)
  normalMap,           // simule des bosses sans ajouter un seul triangle
  roughness: 0.4,      // 0 = miroir, 1 = totalement mat
  metalness: 0.6,      // 0 = plastique/bois, 1 = métal. Les valeurs intermédiaires sont rares
});
```

**La règle du colorSpace** décide de la justesse de vos couleurs :

- une texture qui décrit une **couleur** (`map`, `emissiveMap`) → `THREE.SRGBColorSpace` ;
- une texture qui décrit une **donnée** (`normalMap`, `roughnessMap`, `metalnessMap`,
  `aoMap`, `displacementMap`) → `THREE.NoColorSpace`.

Appliquer une correction gamma à une normal map fausse les directions et donne un éclairage
subtilement bancal, difficile à diagnostiquer.

En PBR, `metalness` est presque toujours **0 ou 1**. Un objet est un métal ou ne l'est pas ;
0,5 ne décrit aucun matériau réel. Ce sont `roughness` et les textures qui créent la variété.

---

## Étape 6 — Interaction : du clic 2D à l'objet 3D

L'utilisateur clique un pixel ; vous voulez savoir quel objet se trouve derrière. C'est le rôle
du **raycaster** : il lance un rayon depuis la caméra à travers ce pixel et liste les objets
traversés, triés du plus proche au plus lointain.

```js
const raycaster = new THREE.Raycaster();
const pointeur = new THREE.Vector2();

addEventListener('pointerdown', (event) => {
  // Conversion pixels (0 → largeur) vers coordonnées normalisées (-1 → +1).
  pointeur.x = (event.clientX / innerWidth) * 2 - 1;
  pointeur.y = -(event.clientY / innerHeight) * 2 + 1;   // l'axe Y est inversé en 3D

  raycaster.setFromCamera(pointeur, camera);
  const touches = raycaster.intersectObjects(cubes);     // tableau, vide si rien n'est touché

  if (touches.length > 0) {
    touches[0].object.material.color.set(0xff0000);      // [0] = le plus proche de la caméra
    console.log('point exact touché :', touches[0].point);
  }
});
```

Deux détails qui font la différence :

- `intersectObjects(liste)` ne teste que la liste fournie. Passer `scene.children` teste tout,
  y compris le sol et les helpers — rarement ce que l'on veut.
- Le second argument `recursive` vaut `true` par défaut : un modèle glTF chargé (un `Group`)
  verra bien ses `Mesh` enfants testés.

### OrbitControls

Pour naviguer autour de la scène, inutile de coder la caméra à la main :

```js
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;         // inertie : le mouvement s'arrête en douceur
controls.maxPolarAngle = Math.PI / 2.1; // interdit de passer sous le sol
```

`enableDamping` **oblige** à appeler `controls.update()` à chaque frame de la boucle de rendu.
Sans cet appel, l'inertie ne s'applique jamais et la caméra semble figée après le relâchement.

Enfin, si vous posez un objet au clic *et* que vous utilisez OrbitControls, distinguez le clic
du glisser : mesurez le déplacement de la souris entre `pointerdown` et `pointerup`, et n'agissez
que s'il est faible. Sinon chaque rotation de caméra déclenche une action non voulue (leçon 08).

---

## Étape 7 — Charger un modèle glTF

`.gltf` / `.glb` est le format standard : géométrie, matériaux PBR, squelette et animations en un
seul fichier. Le chargement est **asynchrone**, avec trois callbacks.

```js
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

new GLTFLoader().load(
  'models/robot.glb',

  (gltf) => {                       // succès
    scene.add(gltf.scene);
  },
  (evt) => {                        // progression
    console.log(Math.round((evt.loaded / evt.total) * 100), '%');
  },
  (err) => {                        // erreur : 404, fichier corrompu, CORS…
    console.error(err);
  }
);
```

Ne supposez jamais qu'un modèle arrive centré ou à la bonne échelle. Le recentrage automatique :

```js
const boite = new THREE.Box3().setFromObject(modele);
const centre = boite.getCenter(new THREE.Vector3());
const taille = boite.getSize(new THREE.Vector3()).length();

modele.position.sub(centre);        // ramène le centre de l'objet sur l'origine
modele.scale.setScalar(3 / taille); // normalise la diagonale à 3 unités
```

Un modèle est un **arbre**, pas un mesh unique. Pour agir sur toutes ses parties :

```js
modele.traverse((noeud) => {
  if (noeud.isMesh) {
    noeud.castShadow = true;
    noeud.receiveShadow = true;
  }
});
```

Enfin, un matériau métallique n'a rien à réfléchir dans une scène vide : il paraît noir, même
bien éclairé. Donnez à la scène un environnement.

```js
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

const pmrem = new THREE.PMREMGenerator(renderer);
scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
```

Pour les animations contenues dans le fichier :

```js
const mixer = new THREE.AnimationMixer(gltf.scene);
gltf.animations.forEach((clip) => mixer.clipAction(clip).play());
// puis, dans la boucle : mixer.update(dt);
```

---

## Étape 8 — Un peu de physique, sans moteur

Pour une chute, un rebond ou une collision de sphères, quinze lignes suffisent. Le schéma est
toujours le même : **intégrer**, puis **corriger les pénétrations**.

```js
// 1. Intégration : la gravité modifie la vitesse, la vitesse modifie la position.
bille.vitesse.y -= gravite * dt;
bille.mesh.position.addScaledVector(bille.vitesse, dt);

// 2. Collision avec le sol : on repositionne, puis on inverse et amortit la vitesse.
if (bille.mesh.position.y - bille.rayon < 0) {
  bille.mesh.position.y = bille.rayon;                       // corriger la pénétration
  bille.vitesse.y = Math.abs(bille.vitesse.y) * elasticite;  // rebondir en perdant de l'énergie
}
```

Corriger la position *avant* d'inverser la vitesse est essentiel : sinon l'objet peut rester
coincé sous le sol et osciller indéfiniment. Et n'écrivez pas `vitesse.y = -vitesse.y` — au
contact, la vitesse est déjà négative dans certains cas limites, et le signe s'inverse une frame
sur deux. `Math.abs(...)` garantit que la bille repart toujours vers le haut.

Deux réflexes de performance dans une boucle 60 fps :

```js
// À éviter : une allocation par frame et par objet, le ramasse-miettes finit par saccader.
const delta = new THREE.Vector3().subVectors(b.position, a.position);

// À préférer : un vecteur réutilisé, créé une fois hors de la boucle.
const _delta = new THREE.Vector3();
_delta.subVectors(b.position, a.position);
```

La leçon 07 pousse jusqu'au choc élastique entre deux sphères. Au-delà de quelques dizaines
d'objets, ou dès qu'il faut des formes arbitraires et des contraintes, passez à un vrai moteur :
**Rapier** (rapide, WASM) ou **Cannon-es**.

---

## Étape 9 — Faire venir un modèle depuis Blender

Oui, un objet modélisé dans Blender s'affiche dans Three.js. Le pont entre les deux, c'est glTF.
Ouvrez `lecons/09-blender.html` et **déposez votre fichier sur la page** : rien n'est envoyé à un
serveur, le navigateur lit le fichier en mémoire.

### Un modèle fabriqué par script

`models/robot.glb` n'a pas été modélisé à la souris : il est produit par
`scripts/creer-robot.py`, un script Python que Blender exécute sans même ouvrir sa fenêtre.

```bash
"C:\Program Files\Blender Foundation\Blender 5.1\blender.exe" --background --python scripts/creer-robot.py
```

Changez une couleur ou une proportion dans le script, relancez la commande, rechargez la page :
le modèle est régénéré. C'est l'intérêt du script sur le clic — le résultat est reproductible,
et il se versionne dans Git, contrairement à un `.blend` binaire.

Le script montre au passage les trois choses qui comptent pour un export propre : n'utiliser que
le nœud *Principled BSDF* pour les matériaux, **appliquer les modificateurs** (ici un Bevel),
et poser des images clés pour produire un clip d'animation (`Flottement`).

### L'export, dans Blender

`Fichier → Exporter → glTF 2.0 (.glb/.gltf)`, puis :

| Réglage | Valeur | Pourquoi |
|---|---|---|
| Format | **glTF Binary (.glb)** | un seul fichier : maillage, matériaux et textures dedans |
| Include → Selected Objects | coché si besoin | sinon toute la scène part, caméras et lampes comprises |
| Transform → +Y Up | **coché** | Blender travaille en Z-up, Three.js en Y-up |
| Data → Apply Modifiers | coché | sinon vos Subdivision/Mirror n'existent pas dans l'export |
| Animation | coché | seulement si vous avez des actions à emporter |

Le format `.gltf` « séparé » produit un `.gltf` + un `.bin` + un dossier de textures. Il est
parfait dans un projet servi par HTTP, mais **indéposable** sur une page : le loader chercherait
les fichiers voisins qu'il n'a pas. En glissé-déposé, utilisez `.glb`.

### Dans le code

```js
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

new GLTFLoader().load('models/robot.glb', (gltf) => {
  scene.add(gltf.scene);

  // Les « actions » de Blender arrivent ici, sous forme de clips.
  const mixer = new THREE.AnimationMixer(gltf.scene);
  gltf.animations.forEach((clip) => mixer.clipAction(clip).play());
  // ... et dans la boucle de rendu : mixer.update(dt);
});
```

Trois pièges, dans l'ordre où ils vous tomberont dessus :

1. **Le modèle est invisible.** Il est probablement énorme ou minuscule : glTF ne garantit
   aucune échelle. Mesurez-le avec `new THREE.Box3().setFromObject(modele)` et recadrez la
   caméra — c'est ce que fait la leçon 09 automatiquement.
2. **Le modèle est noir.** Ses matériaux sont métalliques et n'ont rien à réfléchir.
   Donnez un environnement à la scène (`scene.environment`, voir l'étape 7).
3. **Les matériaux ne ressemblent pas à Blender.** Seul le nœud *Principled BSDF* s'exporte.
   Un shader bricolé avec des nœuds de bruit ou de dégradé ne survit pas à l'export : il faut
   le « cuire » en texture (*bake*) avant de sortir le fichier.

Pour l'usage courant, posez vos `.glb` dans un dossier `models/` du projet et chargez-les par
leur chemin, comme dans la leçon 06.

---

## Erreurs fréquentes, et leur cause

| Symptôme | Cause probable |
|---|---|
| Écran noir, aucune erreur | Rien n'a été `scene.add()`, ou la caméra est dans l'objet |
| Objet noir mais présent | Aucune lumière, ou matériau métallique sans `scene.environment` |
| `Cannot use import outside a module` | Il manque `type="module"` sur la balise `<script>` |
| Erreur CORS au chargement | Page ouverte en `file://` — passez par `http://localhost` |
| Aucune ombre | L'une des trois activations manque, ou le `shadow.camera` est trop petit |
| Ombres pixellisées | `shadow.camera` trop large pour la `mapSize` choisie |
| Animation deux fois trop rapide | Vitesse fixe par frame au lieu d'un delta time |
| La caméra se fige au relâchement | `enableDamping` sans `controls.update()` dans la boucle |
| Les FPS chutent avec le temps | Objets recréés sans `dispose()`, ou allocations dans la boucle |

### Libérer la mémoire GPU

JavaScript ramasse ses miettes, WebGL non. Quand vous supprimez définitivement un objet :

```js
scene.remove(mesh);
mesh.geometry.dispose();
mesh.material.dispose();
// et pour chaque texture du matériau :
mesh.material.map?.dispose();
```

À ne faire que si la géométrie et le matériau ne sont **pas partagés** avec d'autres objets
encore en scène — c'est pourquoi la leçon 08 s'abstient de les libérer.

---

## Pour aller plus loin

- Documentation officielle : <https://threejs.org/docs/>
- Exemples annotés : <https://threejs.org/examples/>
- Post-processing (bloom, contour, profondeur de champ) : `three/addons/postprocessing/`
- Instanciation (`InstancedMesh`) : afficher 100 000 objets identiques en un seul appel de dessin
- React Three Fiber, si votre projet est déjà en React
