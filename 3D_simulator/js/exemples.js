// Points de départ du bac à sable. Chacun est autonome : aucun élément HTML externe,
// donc il tourne tel quel dans l'iframe. Ce sont des bases à casser et à recomposer.

export const EXEMPLES = [
  {
    id: 'cube',
    titre: 'Le cube qui tourne',
    code: `import * as THREE from 'three';

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0e1116);

const camera = new THREE.PerspectiveCamera(75, innerWidth / innerHeight, 0.1, 100);
camera.position.z = 3;

const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setSize(innerWidth, innerHeight);
document.body.appendChild(renderer.domElement);

const cube = new THREE.Mesh(
  new THREE.BoxGeometry(1, 1, 1),
  new THREE.MeshNormalMaterial()
);
scene.add(cube);

// Essayez : remplacez BoxGeometry par TorusKnotGeometry(0.6, 0.25, 128, 32)
// ou changez la vitesse de rotation ci-dessous.
function animer() {
  requestAnimationFrame(animer);
  cube.rotation.x += 0.01;
  cube.rotation.y += 0.01;
  renderer.render(scene, camera);
}
animer();`,
  },

  {
    id: 'lumiere',
    titre: 'Lumières et matériau',
    code: `import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0e1116);

const camera = new THREE.PerspectiveCamera(50, innerWidth / innerHeight, 0.1, 100);
camera.position.set(3, 3, 5);

const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setSize(innerWidth, innerHeight);
document.body.appendChild(renderer.domElement);

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;

scene.add(new THREE.AmbientLight(0xffffff, 0.4));
const soleil = new THREE.DirectionalLight(0xffffff, 3);
soleil.position.set(3, 5, 4);
scene.add(soleil);

// Essayez : roughness 0 (miroir) puis 1 (mat), metalness 0 puis 1.
const sphere = new THREE.Mesh(
  new THREE.SphereGeometry(1, 64, 32),
  new THREE.MeshStandardMaterial({ color: 0x58a6ff, roughness: 0.3, metalness: 0.6 })
);
scene.add(sphere);
scene.add(new THREE.GridHelper(10, 10, 0x3d444d, 0x232a33));

function animer() {
  requestAnimationFrame(animer);
  controls.update();
  renderer.render(scene, camera);
}
animer();`,
  },

  {
    id: 'foule',
    titre: 'Une grille d’objets',
    code: `import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0e1116);

const camera = new THREE.PerspectiveCamera(50, innerWidth / innerHeight, 0.1, 200);
camera.position.set(0, 12, 18);

const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setSize(innerWidth, innerHeight);
document.body.appendChild(renderer.domElement);

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;

scene.add(new THREE.AmbientLight(0xffffff, 0.5));
const soleil = new THREE.DirectionalLight(0xffffff, 2.5);
soleil.position.set(5, 10, 5);
scene.add(soleil);

// Géométrie et matériau créés UNE fois, partagés par les 400 cubes.
const forme = new THREE.BoxGeometry(0.6, 0.6, 0.6);
const cubes = [];

const COTE = 20;
for (let x = 0; x < COTE; x++) {
  for (let z = 0; z < COTE; z++) {
    const cube = new THREE.Mesh(forme, new THREE.MeshStandardMaterial({
      color: new THREE.Color().setHSL((x + z) / (COTE * 2), 0.6, 0.6),
    }));
    cube.position.set(x - COTE / 2, 0, z - COTE / 2);
    scene.add(cube);
    cubes.push(cube);
  }
}

// Une vague : la hauteur dépend de la distance au centre et du temps.
const horloge = new THREE.Clock();
function animer() {
  requestAnimationFrame(animer);
  const t = horloge.getElapsedTime();

  for (const cube of cubes) {
    const d = Math.hypot(cube.position.x, cube.position.z);
    cube.position.y = Math.sin(d * 0.6 - t * 2) * 1.2;
  }

  controls.update();
  renderer.render(scene, camera);
}
animer();`,
  },

  {
    id: 'clic',
    titre: 'Cliquer un objet (raycaster)',
    code: `import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0e1116);

const camera = new THREE.PerspectiveCamera(50, innerWidth / innerHeight, 0.1, 100);
camera.position.set(0, 4, 8);

const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setSize(innerWidth, innerHeight);
document.body.appendChild(renderer.domElement);

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;

scene.add(new THREE.AmbientLight(0xffffff, 0.5));
const soleil = new THREE.DirectionalLight(0xffffff, 2.5);
soleil.position.set(5, 8, 5);
scene.add(soleil);

// Chaque cube a SON matériau : sinon changer une couleur les changerait tous.
const forme = new THREE.BoxGeometry(1, 1, 1);
const cubes = [];
for (let i = -2; i <= 2; i++) {
  const cube = new THREE.Mesh(forme, new THREE.MeshStandardMaterial({ color: 0x58a6ff }));
  cube.position.x = i * 1.5;
  scene.add(cube);
  cubes.push(cube);
}

const raycaster = new THREE.Raycaster();
const pointeur = new THREE.Vector2();

addEventListener('pointerdown', (e) => {
  // Pixels (0 → largeur) vers coordonnées normalisées (-1 → +1). L'axe Y est inversé.
  pointeur.x = (e.clientX / innerWidth) * 2 - 1;
  pointeur.y = -(e.clientY / innerHeight) * 2 + 1;

  raycaster.setFromCamera(pointeur, camera);
  const touches = raycaster.intersectObjects(cubes);

  if (touches.length) {
    touches[0].object.material.color.setHSL(Math.random(), 0.7, 0.6);
    console.log('Cube touché en', touches[0].point.x.toFixed(2));
  }
});

function animer() {
  requestAnimationFrame(animer);
  controls.update();
  renderer.render(scene, camera);
}
animer();`,
  },

  {
    id: 'rebond',
    titre: 'Une balle qui rebondit',
    code: `import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0e1116);

const camera = new THREE.PerspectiveCamera(50, innerWidth / innerHeight, 0.1, 100);
camera.position.set(0, 4, 10);

const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setSize(innerWidth, innerHeight);
renderer.shadowMap.enabled = true;
document.body.appendChild(renderer.domElement);

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;

scene.add(new THREE.AmbientLight(0xffffff, 0.4));
const soleil = new THREE.DirectionalLight(0xffffff, 3);
soleil.position.set(4, 10, 4);
soleil.castShadow = true;
scene.add(soleil);

const sol = new THREE.Mesh(
  new THREE.PlaneGeometry(20, 20),
  new THREE.MeshStandardMaterial({ color: 0x2a313c })
);
sol.rotation.x = -Math.PI / 2;
sol.receiveShadow = true;
scene.add(sol);

const RAYON = 0.6;
const balle = new THREE.Mesh(
  new THREE.SphereGeometry(RAYON, 32, 16),
  new THREE.MeshStandardMaterial({ color: 0xf78166, roughness: 0.4 })
);
balle.position.y = 6;
balle.castShadow = true;
scene.add(balle);

// Essayez : gravite = 4 (la Lune), elasticite = 1 (rebond éternel) ou 0.3 (balle molle).
let gravite = 15;
let elasticite = 0.8;
let vitesseY = 0;

const horloge = new THREE.Clock();
function animer() {
  requestAnimationFrame(animer);

  // On plafonne dt : un onglet en arrière-plan renverrait un delta énorme,
  // et la balle traverserait le sol en un seul pas.
  const dt = Math.min(horloge.getDelta(), 1 / 30);

  vitesseY -= gravite * dt;
  balle.position.y += vitesseY * dt;

  if (balle.position.y < RAYON) {
    balle.position.y = RAYON;                    // corriger AVANT d'inverser la vitesse
    vitesseY = Math.abs(vitesseY) * elasticite;  // Math.abs : repart toujours vers le haut
  }

  controls.update();
  renderer.render(scene, camera);
}
animer();`,
  },
];
