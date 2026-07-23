// ============================================================
//  ÉCHANTILLON — image → relief 3D. Chef d'orchestre de la page.
//
//  Ce fichier tient la SCÈNE et l'ÉTAT (l'image classée, la grille, le pliage, le
//  marcheur) ; les briques sans état vivent à côté :
//    couleurs.js — TSL, familles, rangement d'un pixel
//    formes.js   — enveloppe des cases (carré, arrondi, lisse, boule)
//    pliage.js   — la courbure du globe (plier / orienter)
//
//  Ordre des sections, tel qu'on le lit :
//    1. scène, caméra, lumières        5. cadrage et pliage animé
//    2. état de l'image et de la grille 6. chargement d'image
//    3. construction de la grille       7. menu (familles, palette, commandes)
//    4. étiquettes de noms              8. édition · chutes · soleil · 1ʳᵉ personne · boucle
// ============================================================
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { PointerLockControls } from 'three/addons/controls/PointerLockControls.js';
import { FAM, FINES, rgb2hsl, familleIndex } from './couleurs.js';
import { ELARGIR, geometrieCases } from './formes.js';
import { rayonPlein, plier, orienterPlie, vHaut } from './pliage.js';

// ============================================================
//  Scène / caméra / renderer
// ============================================================
const scene = new THREE.Scene();
(function fond() {
  const c = document.createElement('canvas'); c.width = 4; c.height = 256;
  const ctx = c.getContext('2d');
  const g = ctx.createLinearGradient(0, 0, 0, 256);
  g.addColorStop(0, '#1c2836'); g.addColorStop(0.55, '#111722'); g.addColorStop(1, '#0a0d12');
  ctx.fillStyle = g; ctx.fillRect(0, 0, 4, 256);
  const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace;
  scene.background = t;
})();

const camera = new THREE.PerspectiveCamera(45, innerWidth / innerHeight, 0.1, 500);

const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setSize(innerWidth, innerHeight);
// NETTETÉ — super-échantillonnage. On calcule l'image plus grande que l'écran, la carte
// graphique la réduit ensuite : les bords crénelés des cases se lissent. Le coût monte au
// CARRÉ (×1,5 = 2,25 fois plus de pixels), d'où le réglage — et le plafond à 3, sans quoi
// un écran déjà 2× demanderait 36 fois les pixels d'un écran normal.
let nettete = 1;
function majNettete() {
  renderer.setPixelRatio(Math.min(Math.min(devicePixelRatio, 2) * nettete, 3));
  renderer.setSize(innerWidth, innerHeight);   // refait le tampon à la nouvelle densité
  majEtats();
}
renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
renderer.shadowMap.enabled = true;
renderer.shadowMap.type = THREE.PCFSoftShadowMap;
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = 1.05;
document.body.appendChild(renderer.domElement);

scene.add(new THREE.HemisphereLight(0xdfe9ff, 0x2a2f38, 0.6));
const soleil = new THREE.DirectionalLight(0xfff2dc, 2.3);
soleil.position.set(-8, 18, 10);
soleil.castShadow = true;
soleil.shadow.mapSize.set(2048, 2048);
soleil.shadow.radius = 3.5;
soleil.shadow.bias = -0.0003;
const dd = 32;
soleil.shadow.camera.left = -dd; soleil.shadow.camera.right = dd;
soleil.shadow.camera.top = dd;   soleil.shadow.camera.bottom = -dd;
soleil.shadow.camera.near = 1; soleil.shadow.camera.far = 160;
scene.add(soleil);
const appoint = new THREE.DirectionalLight(0x9fb6d8, 0.6);
appoint.position.set(12, 8, -10);
scene.add(appoint);

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.dampingFactor = 0.08;
controls.maxPolarAngle = Math.PI / 2 - 0.02;
controls.enableZoom = false;           // zoom maison, progressif (voir plus bas)
controls.autoRotateSpeed = 1.5;        // vitesse de la rotation auto (réglable dans le menu)
let distCible = 40, distMin = 2, distMax = 400;

const socleOmbre = new THREE.Mesh(
  new THREE.PlaneGeometry(200, 200),
  new THREE.ShadowMaterial({ opacity: 0.25 })
);
socleOmbre.rotation.x = -Math.PI / 2;
socleOmbre.position.y = -0.001;
socleOmbre.receiveShadow = true;
scene.add(socleOmbre);

// Étiquettes de noms en 3D (lues dans le .json de la capture)
const etiquettes = new THREE.Group();
etiquettes.visible = false;
scene.add(etiquettes);
let elemNoms = [], bornesNoms = null, montrerNoms = false;

// ============================================================
//  Données de l'image : on classe chaque pixel UNE fois au chargement.
// ============================================================
const LARGEUR = 30;
let finesse = 220, relief = 1, forme = 'cube', zoomCourant = null;
let aspect = 1, px = null, fam = null, NW = 0, NH = 0;
let grille = null;
// Palette détectée de l'image (couleurs "autres" que les 9 familles), manipulable comme elles.
let PAL = [], palIdx = null, paletteMode = false;
let dernierGrid = null;   // { C, R, cellules } — sert au canvas 2D et à l'édition
const overrides = new Map();  // "i,j" → { h?, r?,g?,b?, del? } : modifications manuelles
const chutes = [];            // couleurs détachées en cours d'animation
let typeAnim = 'effondrement'; // type d'animation déclenché par le bouton ⬇ (menu Familles)
// Animation de montée progressive (case par case)
let animActif = false, animT = 0, animVitesse = 0.7, animCells = [];

const couleur = new THREE.Color();
const dummy = new THREE.Object3D();

// ============================================================
//  3. LA GRILLE — pose des cases (à plat ou pliée) et construction
//     Le pliage lui-même est dans pliage.js ; ici on ne fait que l'appliquer.
// ============================================================
let globeT = 0, globeCible = 0;     // état courant / état visé du pliage
let globeAnim = null;               // pliage en cours : { depart, cible, duree, prog }
let etatPlat = null;                // caméra + limites mémorisées AVANT le pliage

// Pose d'une case dans `dummy`, à plat ou pliée.
//   i,j : la case · h : sa hauteur · C,R : la grille · cell : le pas à plat
function poser(i, j, h, C, R, cell) {
  const x = (i + 0.5 - C / 2) * cell;
  const z = (j + 0.5 - R / 2) * cell;
  const sw = cell * (ELARGIR[forme] || 0.99);
  if (globeT <= 0.0001) {
    dummy.position.set(x, h / 2, z);
    dummy.quaternion.identity();
    dummy.scale.set(sw, h, sw);
    dummy.updateMatrix();
    return;
  }
  const g = plier(x, z, h / 2, rayonPlein(C, R, cell), globeT, dummy.position);
  orienterPlie(g, dummy.quaternion);
  // Seule « déformation » : deux colonnes voisines se rapprochent en s'éloignant de la
  // ligne centrale (cos b) — c'est la géométrie de la sphère elle-même, comme les
  // méridiens qui se resserrent vers les pôles. Ni la hauteur ni la profondeur ne bougent.
  dummy.scale.set(Math.max(sw * 0.05, sw * g.cb), h, sw);
  dummy.updateMatrix();
}

// Repose TOUTES les cases au `globeT` courant (appelée à chaque image du pliage).
// Les couleurs ne bougent pas : on ne réécrit que les matrices.
function reposerGrille() {
  if (!grille || !dernierGrid) return;
  const { C, R, cellules } = dernierGrid;
  const cell = LARGEUR / C, hMax = cell * 6;
  for (let k = 0; k < cellules.length; k++) {
    const c = cellules[k];
    poser(c.i, c.j, Math.max(cell * 0.12, c.h * hMax * relief), C, R, cell);
    grille.setMatrixAt(k, dummy.matrix);
  }
  grille.instanceMatrix.needsUpdate = true;
  if (selCell) majSelection();
  poserEtiquettes();
  poserObjets();
  poserZoneVisuel();
}

function construire() {
  if (!px) return;
  animActif = false;                    // une reconstruction stoppe l'animation de montée
  if (grille) { grille.dispose(); scene.remove(grille); }

  const C = finesse;
  const R = Math.max(1, Math.round(C / aspect));
  const cell = LARGEUR / C;
  const hMax = cell * 6;

  // Groupes actifs : soit les 9 FAMILLES (par teinte), soit la PALETTE détectée de l'image.
  const paletteOn = paletteMode && PAL.length > 0 && palIdx;
  const groupes = paletteOn ? PAL : FAM;
  const idxArr = paletteOn ? palIdx : fam;
  const NG = groupes.length;

  // Accumulateurs par groupe (réutilisés à chaque case pour éviter la casse mémoire).
  const cnt = new Int32Array(NG);
  const sr = new Float64Array(NG);
  const sg = new Float64Array(NG);
  const sb = new Float64Array(NG);

  const cellules = [];
  for (let j = 0; j < R; j++) {
    const y0 = Math.floor(j * NH / R), y1 = Math.max(y0 + 1, Math.floor((j + 1) * NH / R));
    for (let i = 0; i < C; i++) {
      const x0 = Math.floor(i * NW / C), x1 = Math.max(x0 + 1, Math.floor((i + 1) * NW / C));
      cnt.fill(0); sr.fill(0); sg.fill(0); sb.fill(0);
      let total = 0;
      for (let y = y0; y < y1; y++) {
        let o = (y * NW + x0) * 4;
        for (let x = x0; x < x1; x++, o += 4) {
          const f = idxArr[y * NW + x];
          cnt[f]++; sr[f] += px[o]; sg[f] += px[o + 1]; sb[f] += px[o + 2]; total++;
        }
      }

      let best = -1, bestN = -1, ci = -1;
      if (paletteOn) {
        // Mode palette : la couleur détectée dominante parmi les visibles…
        for (let f = 0; f < NG; f++) if (groupes[f].vis && cnt[f] > bestN) { best = f; bestN = cnt[f]; }
        // …mais une couleur globalement RARE (trait fin : route, frontière) présente dans la
        // case l'emporte, même minoritaire — sinon elle serait noyée sous l'eau/la terre.
        let rf = -1, rn = 0;
        for (let f = 0; f < NG; f++) if (groupes[f].vis && groupes[f].frac < 0.04 && cnt[f] > rn) { rf = f; rn = cnt[f]; }
        if (rf >= 0 && rn >= Math.max(2, total * 0.10)) best = rf;
        ci = best;
      } else {
        // Mode familles : dominante (0..7) + priorité aux traits fins + frontière grise (8).
        for (let f = 0; f < 8; f++) if (FAM[f].vis && cnt[f] > bestN) { best = f; bestN = cnt[f]; }
        let tf = -1, tn = 0;
        for (const f of FINES) if (FAM[f].vis && cnt[f] > tn) { tf = f; tn = cnt[f]; }
        if (tf >= 0 && tn >= Math.max(2, total * 0.10)) {
          best = tf;
        } else if (FAM[8].vis) {
          const fondN = cnt[7];
          if (fondN >= Math.max(1, total * 0.03) && fondN < total * 0.55 && cnt[1] >= fondN) best = 8;
        }
        ci = best === 8 ? 7 : best;
      }
      if (best < 0 || ci < 0 || cnt[ci] === 0) continue;   // aucune famille visible → trou

      let rr = sr[ci] / cnt[ci], gg = sg[ci] / cnt[ci], bb = sb[ci] / cnt[ci], hh = groupes[best].h;
      // Modifications manuelles (mode édition) : elles écrasent la valeur auto.
      const ov = overrides.get(i + ',' + j);
      if (ov) {
        if (ov.del) continue;                        // cube supprimé à la main
        if (ov.h != null) hh = ov.h;
        if (ov.r != null) { rr = ov.r; gg = ov.g; bb = ov.b; }
      }
      cellules.push({ i, j, r: rr, g: gg, b: bb, h: hh, f: best });
    }
  }

  // Hauteur réelle du plus haut cube : le cadrage du globe en a besoin (des pics de 3
  // unités sur un rayon de 5, ça déborde de l'écran si on ne les compte pas).
  let hPlusHaut = 0;
  for (const c of cellules) if (c.h > hPlusHaut) hPlusHaut = c.h;
  dernierGrid = { C, R, cellules, hMaxReel: Math.max(cell * 0.12, hPlusHaut * hMax * relief) };

  const mat = new THREE.MeshStandardMaterial({ roughness: 0.92, metalness: 0 });
  grille = new THREE.InstancedMesh(geometrieCases(forme, cellules.length), mat, cellules.length);
  grille.castShadow = true; grille.receiveShadow = true;
  grille.frustumCulled = false;   // sinon toute la grille est éliminée au zoom (boîte d'1 seul cube)

  for (let k = 0; k < cellules.length; k++) {
    const c = cellules[k];
    const h = Math.max(cell * 0.12, c.h * hMax * relief);
    poser(c.i, c.j, h, C, R, cell);       // à plat, sur le globe, ou entre les deux
    grille.setMatrixAt(k, dummy.matrix);
    couleur.setRGB(c.r / 255, c.g / 255, c.b / 255).convertSRGBToLinear();
    grille.setColorAt(k, couleur);
  }
  grille.instanceMatrix.needsUpdate = true;
  // `instanceColor` n'existe qu'à partir du premier `setColorAt` : si TOUTES les familles
  // sont masquées, la grille est vide et l'attribut est nul. Le cas arrive pour de vrai —
  // masquer la dernière couleur visible, ou faire tomber la seule couleur d'une palette.
  if (grille.instanceColor) grille.instanceColor.needsUpdate = true;
  scene.add(grille);

  if (selCell) majSelection();       // garde la surbrillance calée après reconstruction
  if (montrerNoms) construireEtiquettes();
  poserObjets();                     // les objets se recalent sur la nouvelle grille
  majZoneVisuel();                   // idem pour le marqueur de zone
}

// --- Étiquettes de noms en 3D ---
function mercY(lat) { return Math.log(Math.tan(Math.PI / 4 + lat * Math.PI / 360)); }
function faireEtiquette(texte) {
  const mesure = document.createElement('canvas').getContext('2d');
  mesure.font = 'bold 40px system-ui';
  const w = Math.ceil(mesure.measureText(texte).width) + 36;
  const cv = document.createElement('canvas'); cv.width = w; cv.height = 60;
  const ctx = cv.getContext('2d');
  ctx.fillStyle = 'rgba(13,17,23,0.82)'; ctx.fillRect(0, 0, w, 60);
  ctx.font = 'bold 40px system-ui'; ctx.fillStyle = '#fff';
  ctx.textBaseline = 'middle'; ctx.fillText(texte, 18, 32);
  const tex = new THREE.CanvasTexture(cv); tex.colorSpace = THREE.SRGBColorSpace;
  const spr = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex, depthTest: false, transparent: true }));
  spr.renderOrder = 998;
  const s = 0.03; spr.scale.set(w * s, 60 * s, 1);
  return spr;
}
function construireEtiquettes() {
  while (etiquettes.children.length) {   // vide + libère l'ancien lot
    const c = etiquettes.children[0]; etiquettes.remove(c);
    if (c.material) { if (c.material.map) c.material.map.dispose(); c.material.dispose(); }
  }
  if (!bornesNoms || !dernierGrid || !elemNoms.length) return;
  const { C, R, cellules } = dernierGrid;
  const cell = LARGEUR / C, depth = cell * R, hMax = cell * 6;
  const { sud, ouest, nord, est } = bornesNoms;
  const yN = mercY(nord), yS = mercY(sud);
  for (const e of elemNoms) {
    if (e.lon < ouest || e.lon > est || e.lat < sud || e.lat > nord) continue;
    const u = (e.lon - ouest) / (est - ouest);            // gauche→droite
    const v = (yN - mercY(e.lat)) / (yN - yS);            // haut(nord)→bas (Mercator)
    const i = Math.min(C - 1, Math.max(0, Math.floor(u * C)));
    const j = Math.min(R - 1, Math.max(0, Math.floor(v * R)));
    const c = cellules.find((o) => o.i === i && o.j === j);
    const h = c ? Math.max(cell * 0.12, c.h * hMax * relief) : 0.5;
    const spr = faireEtiquette(e.nom);
    spr.userData = { u, v, h, depth };     // repères conservés : le globe s'en sert aussi
    etiquettes.add(spr);
  }
  poserEtiquettes();
}
// Place les étiquettes selon `globeT` : au-dessus de la dalle, ou au-dessus du globe.
// Même pliage que les cases — elles restent posées sur leur lieu.
function poserEtiquettes() {
  if (!etiquettes.children.length) return;
  const C = dernierGrid ? dernierGrid.C : 1, Rr = dernierGrid ? dernierGrid.R : 1;
  const cell = LARGEUR / C, R1 = rayonPlein(C, Rr, cell);
  for (const spr of etiquettes.children) {
    const d = spr.userData; if (!d) continue;
    const xp = (d.u - 0.5) * LARGEUR, zp = (d.v - 0.5) * d.depth;
    if (globeT <= 0.0001) { spr.position.set(xp, d.h + 1.3, zp); continue; }
    plier(xp, zp, d.h + 1.3, R1, globeT, spr.position);
  }
}

// Y a-t-il du globe à l'écran (même 1 %) ?
const surGlobe = () => globeT > 0.0001 || globeCible > 0.0001;

// Encombrement de la carte pliée au pourcentage courant : largeur, profondeur et point
// le plus bas. Analytique — mesurer la boîte des 50 000 cubes à chaque image coûterait cher.
function mesuresGlobe() {
  const C = dernierGrid ? dernierGrid.C : 1, Rr = dernierGrid ? dernierGrid.R : 1;
  const cell = LARGEUR / C;
  const Rt = rayonPlein(C, Rr, cell) / Math.max(globeT, 1e-4);
  const aMax = (C * cell / 2) / Rt, bMax = (Rr * cell / 2) / Rt;   // demi-angles couverts
  const dx = 2 * Rt * (aMax >= Math.PI / 2 ? 1 : Math.sin(aMax));
  const dz = 2 * Rt * (bMax >= Math.PI / 2 ? 1 : Math.sin(bMax));
  // le plus bas : le coin, ou le bord si le pliage a déjà dépassé l'équateur
  const bas = Rt * (Math.min(Math.cos(aMax) * Math.cos(bMax), Math.cos(aMax), Math.cos(bMax)) - 1);
  const hh = dernierGrid ? dernierGrid.hMaxReel || 0 : 0;    // le relief dépasse de la sphère
  return { etendue: Math.max(dx, dz, -bas) + 2 * hh, centreY: bas / 2 };
}

// Cadrage suivi : la distance VISÉE et le point regardé suivent le pourcentage. On ne
// téléporte pas la caméra — la boucle de rendu rejoint `distCible` en glissant, comme au
// zoom molette ; le point regardé, lui, entraîne la caméra avec lui (pas de dérive).
function cadrerSelonGlobe() {
  if (!etatPlat || modeFP) return;   // en 1ʳᵉ personne, la caméra est portée par le marcheur
  const m = mesuresGlobe();
  const fov = camera.fov * Math.PI / 180;
  const dG = Math.max(
    (m.etendue * 0.5) / Math.tan(fov / 2) * 1.2,
    (m.etendue * 0.5) / (Math.tan(fov / 2) * camera.aspect) * 1.15
  );
  distCible = dG;                    // à 0 %, `dG` retombe sur le cadrage de la dalle
  distMin = m.etendue * 0.1;
  distMax = Math.max(etatPlat.distMax, dG * 4);
  camera.near = Math.max(0.02, distCible * 0.02);
  camera.far = distCible * 12;
  camera.updateProjectionMatrix();
  const dy = (etatPlat.cible.y + m.centreY) - controls.target.y;
  controls.target.y += dy; camera.position.y += dy;
}

// On QUITTE la dalle (dès 1 %) : on mémorise tout ce que le globe va changer.
function entrerEtatGlobe() {
  if (etatPlat) return;
  viderChutes();                     // les chutes sont calculées à plat : on les retire
  etatPlat = {
    cible: controls.target.clone(), distCible, distMin, distMax,
    near: camera.near, far: camera.far, polar: controls.maxPolarAngle,
  };
  controls.maxPolarAngle = Math.PI;  // on peut passer sous l'équateur et voir le pôle sud
}
// On REVIENT à 0 % : la caméra retrouve le cadrage qu'elle avait avant le pliage.
function sortirEtatGlobe() {
  if (!etatPlat) return;
  controls.maxPolarAngle = etatPlat.polar;   // OrbitControls relèvera la caméra au besoin
  if (!modeFP) {          // en 1ʳᵉ personne la caméra appartient au marcheur : on n'y touche pas
    // La caméra suit le point regardé (comme pendant le pliage) : sans ça, un retour direct
    // depuis 80 % ferait remonter la cible seule et la vue basculerait à ras du sol.
    camera.position.y += etatPlat.cible.y - controls.target.y;
    camera.near = etatPlat.near; camera.far = etatPlat.far;
    camera.updateProjectionMatrix();
  }
  controls.target.copy(etatPlat.cible);
  distMin = etatPlat.distMin; distMax = etatPlat.distMax; distCible = etatPlat.distCible;
  etatPlat = null;
}

// Applique l'état courant : repose les cases, suit le cadrage, met le menu à jour.
function appliquerGlobe() {
  socleOmbre.visible = globeT < 0.02;   // le sol d'ombre traverserait la sphère
  if (globeT <= 0.0001 && globeCible <= 0.0001) sortirEtatGlobe();
  else cadrerSelonGlobe();
  reposerGrille();
  majGlobeUI();
}
const nSec = (d) => d.toFixed(1).replace('.', ',') + ' s';
function majGlobeUI() {
  majEtats();
  const pct = Math.round(globeT * 100);
  const s = document.getElementById('globePct');
  if (s && document.activeElement !== s) s.value = pct;   // pas pendant qu'on le tient
  const v = document.getElementById('globePctV'); if (v) v.textContent = pct + ' %';
  const b = document.getElementById('btnGlobe');
  const actif = globeCible > 0.0001;
  b.classList.toggle('actif', actif);
  b.textContent = actif ? '🗺️ À plat' : '🌍 Globe';
  b.title = actif ? 'Revenir à la dalle, telle qu’elle était' : 'Enrouler la carte sur une sphère';
  // Le bouton d'animation annonce ce qu'il va faire, réglages compris — et propose le
  // retour quand on est déjà arrivé, pour ne jamais rester un bouton mort.
  const go = document.getElementById('btnGlobeGo');
  if (go) {
    const cible = parseInt(document.getElementById('globeVers').value, 10) / 100;
    const duree = parseFloat(document.getElementById('globeDuree').value);
    go.textContent = Math.abs(cible - globeT) < 0.005
      ? `▶ Revenir à plat en ${nSec(duree)}`
      : `▶ Plier à ${Math.round(cible * 100)} % en ${nSec(duree)}`;
  }
}

// Réglage direct (curseur) : le pliage suit le doigt, sans animation.
function reglerGlobe(t) {
  t = Math.min(1, Math.max(0, t));
  globeAnim = null;                     // la main reprend sur l'animation en cours
  if (t > 0.0001) entrerEtatGlobe();
  globeT = globeCible = t;
  appliquerGlobe();
}
// Pliage progressif vers `cible` en `duree` secondes (boucle de rendu).
function animerGlobe(cible, duree) {
  cible = Math.min(1, Math.max(0, cible));
  if (cible > 0.0001) entrerEtatGlobe();
  globeAnim = { depart: globeT, cible, duree: Math.max(0.05, duree), prog: 0 };
  globeCible = cible;
  majGlobeUI();
}
// Bouton flottant : 0 % ⇄ 100 %, à la durée réglée dans le menu.
function basculerGlobe() {
  animerGlobe(globeCible > 0.0001 ? 0 : 1, parseFloat($('globeDuree').value));
}

function recadrer() {
  const R = Math.max(1, Math.round(finesse / aspect));
  const cell = LARGEUR / finesse;
  const etendue = Math.max(LARGEUR, cell * R);
  const fov = camera.fov * Math.PI / 180;
  let dist = (etendue * 0.5) / Math.tan(fov / 2) * 1.15;
  dist = Math.max(dist, (LARGEUR * 0.5) / (Math.tan(fov / 2) * camera.aspect) * 1.1);
  controls.target.set(0, 0, 0);
  camera.near = Math.max(0.05, dist * 0.02);
  camera.far = dist * 12;
  camera.updateProjectionMatrix();
  // Sur le globe (même à 30 %), le cadrage de la dalle devient la RÉFÉRENCE mémorisée :
  // on recadre alors selon le pourcentage courant, sinon on cadre la dalle directement.
  if (surGlobe() && etatPlat) {
    etatPlat.distCible = dist; etatPlat.distMin = etendue * 0.12; etatPlat.distMax = dist * 3;
    etatPlat.near = Math.max(0.05, dist * 0.02); etatPlat.far = dist * 12;
    cadrerSelonGlobe();
  } else {
    camera.position.set(0, dist * 0.72, dist * 0.72);
    distMin = etendue * 0.12; distMax = dist * 3; distCible = dist;
  }
  controls.update();
}

renderer.domElement.addEventListener('wheel', (e) => {
  e.preventDefault();
  distCible = Math.min(distMax, Math.max(distMin, distCible * Math.exp(e.deltaY * 0.0012)));
}, { passive: false });

// ============================================================
//  Chargement d'image : dessin natif + classification une fois pour toutes.
// ============================================================
function lireZoom(nom) { const m = /-z(\d+)-/.exec(nom || ''); return m ? parseInt(m[1], 10) : null; }
function afficherZoom() {
  document.getElementById('zoom').innerHTML =
    `Zoom détecté : <b>${zoomCourant !== null ? 'z' + zoomCourant : 'inconnu'}</b>`;
}

// ---- L'état des sections repliables, affiché dans leur titre ----------------
// Le menu est replié la plupart du temps : sans ce résumé, il faudrait ouvrir une
// section pour savoir ce qu'elle contient comme réglage.
const NOM_FORME = { cube: 'carrés', arrondi: 'arrondis', lisse: 'très lisses', boule: 'boules' };
// Dernière répartition appliquée : elle n'est déductible d'aucune valeur (deux répartitions
// peuvent donner des hauteurs proches), on la retient donc pour l'afficher.
let repartitionCourante = "d'origine";
let projetCourant = null;                   // nom du projet ouvert/enregistré, pour l'afficher
function majEtats() {
  const e = (id, texte) => { const n = document.getElementById(id); if (n) n.textContent = texte; };
  e('etatRelief', `${finesse} · ${NOM_FORME[forme] || forme}`);
  e('etatHauteurs', `×${String(relief).replace('.', ',')} · ${repartitionCourante}`);
  e('etatProjet', projetCourant || 'non enregistré');
  e('etatGlobe', Math.round(globeT * 100) + ' %');
  e('etatVue', `netteté ${String(nettete).replace('.', ',')}×`);
  e('etatSoleil', solPilote ? `${boussole(solAz)} · ${solEl}°${solAuto ? ' · tourne' : ''}` : 'éclairage par défaut');
}

function chargerImage(url, nom, apres) {
  const im = new Image();
  im.crossOrigin = 'anonymous';
  im.onload = () => {
    aspect = im.naturalWidth / im.naturalHeight || 1;
    // Plafond de largeur pour la classification. Il était à 1400 : une capture « détail ×4 »
    // (3600 px) y était réduite de 61 %, et ses traits fins — les rues qu'on est justement
    // allé chercher — se moyennaient jusqu'à disparaître. À 3000, une capture ×2 passe
    // intacte et une ×4 garde l'essentiel. Coût : 3000×1700 pixels = 20 Mo lus une fois.
    NW = Math.min(im.naturalWidth || 512, 3000);
    NH = Math.max(1, Math.round(NW / aspect));
    const cv = document.createElement('canvas'); cv.width = NW; cv.height = NH;
    const cx = cv.getContext('2d'); cx.drawImage(im, 0, 0, NW, NH);
    px = cx.getImageData(0, 0, NW, NH).data;
    fam = new Uint8Array(NW * NH);
    for (let p = 0; p < NW * NH; p++) {
      const o = p * 4; fam[p] = familleIndex(px[o], px[o + 1], px[o + 2]);
    }
    // Nouvelle image → tout repart à zéro, objets posés compris : ils appartenaient à la
    // carte précédente. Leur place est relative (u, v), donc rien ne les empêcherait de
    // « survivre » au changement — ils se retrouveraient plantés sur une autre ville.
    overrides.clear(); deselect(); deselectZone(false); viderChutes(); viderObjets();
    PAL = []; palIdx = null; paletteMode = false; palCandidats = [];   // palette propre à chaque image
    const pm = document.getElementById('palMode'); if (pm) pm.checked = false;
    const pl = document.getElementById('palette'); if (pl) pl.innerHTML = '';
    // Noms de lieux : on lit le .json de même nom dans captures/ (s'il existe).
    elemNoms = []; bornesNoms = null;
    fetch('captures/' + (nom || '').replace(/\.png$/, '.json'))
      .then((r) => (r.ok ? r.json() : null))
      .then((meta) => {
        if (meta) { elemNoms = meta.elements || []; bornesNoms = meta.bornes || null; }
        majEchelle();                   // la latitude vient de ces bornes : sans elles, pas d'échelle
        if (montrerNoms) construireEtiquettes();
      })
      .catch(() => {});
    zoomCourant = lireZoom(nom); afficherZoom();
    const ec = document.getElementById('etatCapture');
    if (ec) ec.textContent = (nom || 'image').replace(/^card-maps-/, '').replace(/\.png$/, '');
    construire(); recadrer(); majEtats(); majEchelle();
    if (apres) apres();               // un projet en cours de chargement reprend la main ici
  };
  im.src = url;
}

// ============================================================
//  Menu des familles (généré depuis FAM) + commandes générales
// ============================================================
const $ = (id) => document.getElementById(id);

const conteneur = $('familles');
FAM.forEach((f, idx) => {
  const row = document.createElement('div');
  row.className = 'fam-row';
  row.innerHTML =
    `<span class="fam-sw" style="background:${f.sw}"></span>` +
    `<input type="checkbox" ${f.vis ? 'checked' : ''} data-vis="${idx}">` +
    `<label title="${f.nom}">${f.nom}</label>` +
    `<input type="range" min="0" max="4" step="0.05" value="${f.h}" data-h="${idx}">` +
    `<span class="val" data-hv="${idx}">${f.h.toFixed(2)}</span>` +
    `<button class="fam-drop" data-drop="${idx}" title="Faire tomber / remettre cette couleur">⬇</button>`;
  conteneur.appendChild(row);
});
conteneur.addEventListener('input', (e) => {
  const t = e.target;
  if (t.dataset.h !== undefined) {
    memoriserGeste("hauteur d'une famille");
    const i = +t.dataset.h; FAM[i].h = parseFloat(t.value);
    conteneur.querySelector(`[data-hv="${i}"]`).textContent = FAM[i].h.toFixed(2);
    construire();
  }
});
conteneur.addEventListener('change', (e) => {
  const t = e.target;
  if (t.dataset.vis !== undefined) { memoriser("visibilité d'une famille"); FAM[+t.dataset.vis].vis = t.checked; construire(); }
});
conteneur.addEventListener('click', (e) => {
  if (e.target.dataset.drop !== undefined) fallColor(+e.target.dataset.drop);
});

// ---- Palette : détecte les vraies couleurs dominantes de l'image (au-delà des 9 familles) ----
// (la fonction hx() — RVB → hex — est définie plus bas dans le bloc d'édition, même portée)
let palCandidats = [], palTol = 40, palTotal = 1;
function detecterCouleurs() {
  if (!px) return;
  // Quantification FINE (paliers de 16 = 16×16×16 boîtes) : sépare les nuances proches
  // d'une carte (terre 245 vs frontière 223…) que des paliers grossiers fusionneraient.
  const boites = new Map();
  for (let p = 0; p < NW * NH; p++) {
    const o = p * 4, r = px[o], g = px[o + 1], b = px[o + 2];
    const key = ((r >> 4) << 8) | ((g >> 4) << 4) | (b >> 4);
    let e = boites.get(key);
    if (!e) { e = { r: 0, g: 0, b: 0, n: 0 }; boites.set(key, e); }
    e.r += r; e.g += g; e.b += b; e.n++;
  }
  // On garde TOUTES les boîtes (triées par fréquence pour l'ordre de fusion), pas seulement
  // les plus fréquentes : sinon les couleurs rares mais distinctes (routes, frontières) sont perdues.
  palCandidats = [...boites.values()]
    .map((e) => ({ r: e.r / e.n, g: e.g / e.n, b: e.b / e.n, n: e.n }))
    .sort((a, b) => b.n - a.n);
  palTotal = palCandidats.reduce((s, c) => s + c.n, 0) || 1;
  regrouperPalette(palTol);
}
// Fusionne les couleurs proches (distance RVB < tol), du plus fréquent au moins fréquent.
// Garde jusqu'à 24 groupes — assez pour conserver les couleurs distinctes rares.
function regrouperPalette(tol) {
  const cl = [];
  for (const cand of palCandidats) {
    let fusion = null;
    for (const g of cl) {
      const dr = cand.r - g.r, dg = cand.g - g.g, db = cand.b - g.b;
      if (dr * dr + dg * dg + db * db < tol * tol) { fusion = g; break; }
    }
    if (fusion) {                       // fusion pondérée par les effectifs
      const n = fusion.n + cand.n;
      fusion.r = (fusion.r * fusion.n + cand.r * cand.n) / n;
      fusion.g = (fusion.g * fusion.n + cand.g * cand.n) / n;
      fusion.b = (fusion.b * fusion.n + cand.b * cand.n) / n;
      fusion.n = n;
    } else {
      cl.push({ r: cand.r, g: cand.g, b: cand.b, n: cand.n });
    }
  }
  PAL = cl.sort((a, b) => b.n - a.n).slice(0, 24).map((c) => ({
    r: Math.round(c.r), g: Math.round(c.g), b: Math.round(c.b),
    h: 0.5, vis: true, frac: c.n / palTotal,   // frac = part globale (les rares = traits fins)
  }));
  recalculerPalIdx();
  afficherPalette();
}
// Pour chaque pixel, la couleur de palette la plus proche (une seule fois).
function recalculerPalIdx() {
  palIdx = new Uint8Array(NW * NH);
  for (let p = 0; p < NW * NH; p++) {
    const o = p * 4, r = px[o], g = px[o + 1], b = px[o + 2];
    let bi = 0, bd = 1e9;
    for (let k = 0; k < PAL.length; k++) {
      const dr = r - PAL[k].r, dg = g - PAL[k].g, db = b - PAL[k].b, d = dr * dr + dg * dg + db * db;
      if (d < bd) { bd = d; bi = k; }
    }
    palIdx[p] = bi;
  }
}
function afficherPalette() {
  const c = $('palette'); c.innerHTML = '';
  PAL.forEach((p, idx) => {
    const sw = '#' + hx(p.r) + hx(p.g) + hx(p.b);
    const row = document.createElement('div');
    row.className = 'fam-row';
    row.innerHTML =
      `<span class="fam-sw" style="background:${sw}"></span>` +
      `<input type="checkbox" checked data-pvis="${idx}">` +
      `<label title="${sw}">${sw}</label>` +
      `<input type="range" min="0" max="4" step="0.05" value="${p.h}" data-ph="${idx}">` +
      `<span class="val" data-phv="${idx}">${p.h.toFixed(2)}</span>` +
      `<button class="fam-drop" data-pdrop="${idx}" title="Faire tomber / remettre cette couleur">⬇</button>`;
    c.appendChild(row);
  });
}
// Relie (fusionne) les couleurs de la palette trop proches, en gardant la plus fréquente
// comme référence (sa hauteur, sa visibilité). Distance = curseur « Regrouper les proches ».
function relierProches() {
  if (!PAL.length) detecterCouleurs();
  if (!PAL.length) return;                     // pas d'image chargée
  const seuil = Math.max(6, palTol);           // en deçà, deux couleurs comptent pour « les mêmes »
  const avant = PAL.length;
  const groupes = [];
  for (const c of [...PAL].sort((a, b) => b.frac - a.frac)) {   // du plus fréquent au moins
    let cible = null;
    for (const g of groupes) {
      const dr = c.r - g.r, dg = c.g - g.g, db = c.b - g.b;
      if (dr * dr + dg * dg + db * db < seuil * seuil) { cible = g; break; }
    }
    if (cible) {                               // fusion pondérée par la fréquence
      const w = cible.frac + c.frac || 1;
      cible.r = Math.round((cible.r * cible.frac + c.r * c.frac) / w);
      cible.g = Math.round((cible.g * cible.frac + c.g * c.frac) / w);
      cible.b = Math.round((cible.b * cible.frac + c.b * c.frac) / w);
      cible.frac = w; cible.vis = cible.vis || c.vis;   // garde la hauteur de la dominante (déjà en place)
    } else {
      groupes.push({ ...c });                  // nouvelle référence : conserve sa hauteur
    }
  }
  PAL = groupes;
  paletteMode = true; $('palMode').checked = true;
  recalculerPalIdx(); afficherPalette(); construire();
  if (avant !== PAL.length) console.log('Couleurs reliées : ' + avant + ' → ' + PAL.length);
}
$('relier').addEventListener('click', relierProches);

$('detecter').addEventListener('click', () => {
  detecterCouleurs();
  $('palMode').checked = true; paletteMode = true;   // détecter → on bascule sur la palette
  construire();
});
$('palMode').addEventListener('change', (e) => {
  if (e.target.checked && PAL.length === 0) detecterCouleurs();
  paletteMode = e.target.checked; construire();
});
$('palTol').addEventListener('input', (e) => {
  palTol = parseFloat(e.target.value); $('palTolV').textContent = e.target.value;
  if (palCandidats.length) { regrouperPalette(palTol); if (paletteMode) construire(); }
});
$('palette').addEventListener('input', (e) => {
  const t = e.target;
  if (t.dataset.ph !== undefined) {
    const i = +t.dataset.ph; PAL[i].h = parseFloat(t.value);
    $('palette').querySelector(`[data-phv="${i}"]`).textContent = PAL[i].h.toFixed(2);
    construire();
  }
});
$('palette').addEventListener('change', (e) => {
  const t = e.target;
  if (t.dataset.pvis !== undefined) { PAL[+t.dataset.pvis].vis = t.checked; construire(); }
});
$('palette').addEventListener('click', (e) => {
  if (e.target.dataset.pdrop !== undefined) fallColor(+e.target.dataset.pdrop);
});

const selCapture = $('capture');
// La liste des captures est relue à chaque fois qu'elle change (chargement, suppression).
// `charger` : faut-il ouvrir la première de la liste ? Non après une suppression si une
// autre capture est déjà à l'écran — on ne rejette pas le travail en cours.
function listerCaptures(charger = true) {
  return fetch('list.php')
    .then((r) => r.json())
    .then((data) => {
      selCapture.innerHTML = '';
      if (!data.ok || !data.images.length) {
        selCapture.innerHTML = '<option value="">(aucune capture)</option>';
        return 0;
      }
      for (const im of data.images) {
        const o = document.createElement('option');
        o.value = im.url;                 // chemin captures/xxx.png
        o.dataset.nom = im.filename;      // nom (pour lire le zoom)
        o.textContent = im.filename.replace('card-maps-', '');
        selCapture.appendChild(o);
      }
      if (charger) chargerImage(data.images[0].url, data.images[0].filename);
      return data.images.length;
    })
    .catch(() => { selCapture.innerHTML = '<option value="">(liste indisponible)</option>'; return 0; });
}
listerCaptures();

// Supprimer la capture choisie — image ET métadonnées, côté serveur. Irréversible : on
// demande confirmation, en nommant le fichier pour qu'on sache ce qu'on efface.
$('capSuppr').addEventListener('click', () => {
  const opt = selCapture.selectedOptions[0];
  const nom = opt && opt.dataset.nom;
  if (!nom) return;
  if (!confirm(`Supprimer définitivement « ${nom} » du serveur ?
(l'image et son fichier de lieux)`)) return;
  const body = new URLSearchParams(); body.set('fichier', nom);
  fetch('supprimer.php', { method: 'POST', body })
    .then((r) => r.json())
    .then((res) => {
      if (!res.ok) { toast('Suppression refusée : ' + res.error, 3500); return; }
      toast(`🗑 ${nom} supprimée`);
      // On ne recharge une autre image que s'il n'y a plus rien à l'écran.
      return listerCaptures(!px);
    })
    .catch(() => toast('Serveur injoignable', 3000));
});

selCapture.addEventListener('change', () => {
  const opt = selCapture.selectedOptions[0];
  if (selCapture.value) chargerImage(selCapture.value + '?t=' + selCapture.selectedIndex, opt.dataset.nom);
});
$('finesse').addEventListener('input', (e) => {
  finesse = parseInt(e.target.value, 10); $('finesse-val').textContent = finesse;
  overrides.clear(); deselect(); deselectZone(false); viderChutes();   // modifs, zone et chutes liées à la grille courante
  construire(); recadrer(); majEtats(); majEchelle();
});
$('relief').addEventListener('input', (e) => {
  relief = parseFloat(e.target.value); $('relief-val').textContent = relief; construire(); majEtats();
});
$('forme').addEventListener('change', (e) => { forme = e.target.value; construire(); majEtats(); });
$('montrerNoms').addEventListener('change', (e) => {
  montrerNoms = e.target.checked; etiquettes.visible = montrerNoms;
  if (montrerNoms) construireEtiquettes();
});
$('nettete').addEventListener('change', (e) => { nettete = parseFloat(e.target.value); majNettete(); });
$('autorot').addEventListener('change', (e) => { controls.autoRotate = e.target.checked; });
$('rotvit').addEventListener('input', (e) => {
  controls.autoRotateSpeed = parseFloat(e.target.value); $('rotvitV').textContent = e.target.value;
});
// Montée progressive : au clic, tout pousse depuis le sol, case par case (vague diagonale).
function demarrerMontee() {
  if (!dernierGrid) return;
  const { C, R, cellules } = dernierGrid;
  const cell = LARGEUR / C, hMax = cell * 6;
  animCells = cellules.map((c) => ({
    i: c.i, j: c.j, C, R, cell,            // la pose (plat ou globe) est calculée par poser()
    hT: Math.max(cell * 0.12, c.h * hMax * relief),
    phase: (c.i / C + c.j / R) / 2,        // 0 (coin) → 1 (coin opposé) : la vague
  }));
  animT = 0; animActif = true;
}
$('btnMontee').addEventListener('click', demarrerMontee);
$('montVit').addEventListener('input', (e) => {
  animVitesse = parseFloat(e.target.value); $('montVitV').textContent = e.target.value;
});

// --- Calcul des hauteurs À LA DEMANDE, couleur par couleur (mode palette) ---
// Table de référence par famille (0..7) : eau bas → frontière/rouge haut.
const H_AUTO = [0.05, 0.50, 0.30, 0.15, 0.12, 1.20, 1.00, 0.10];
function hauteurSelonCritere(r, g, b, crit) {
  if (crit === 'nature') return H_AUTO[familleIndex(r, g, b)] ?? 0.5;
  const lum = (r + g + b) / 3 / 255;
  if (crit === 'lumD') return 0.05 + (1 - lum) * 1.3;   // sombre = haut
  return 0.05 + lum * 1.3;                               // clair = haut
}
// Les hauteurs de départ, pour pouvoir toujours y revenir. C'est ce qui rend les autres
// modes sans risque : aucun n'est définitif.
const H_DEFAUT = FAM.map((f) => f.h);
const teinteHex = (h) => [parseInt(h.slice(1, 3), 16), parseInt(h.slice(3, 5), 16), parseInt(h.slice(5, 7), 16)];

// Le groupe sur lequel on agit : les FAMILLES, ou la PALETTE si elle est active. L'ancienne
// version basculait d'autorité en mode palette — demander « inverser » ne doit pas changer
// la façon dont l'image est classée.
const groupeHauteurs = () => (paletteMode && PAL.length ? PAL : FAM);

// Après un calcul, les curseurs du menu affichent encore les anciennes valeurs : on les
// recale, sinon le menu ment sur ce qu'on voit à l'écran.
function rafraichirHauteursUI() {
  if (groupeHauteurs() !== FAM) { afficherPalette(); return; }
  FAM.forEach((f, i) => {
    const s = conteneur.querySelector(`[data-h="${i}"]`); if (s) s.value = f.h;
    const v = conteneur.querySelector(`[data-hv="${i}"]`); if (v) v.textContent = f.h.toFixed(2);
  });
}

function calculerHauteurs() {
  memoriser('répartition des hauteurs');
  const crit = $('calcCrit').value;
  const g = groupeHauteurs();
  const borne = (h) => Math.min(4, Math.max(0.01, Math.round(h * 100) / 100));
  const rvb = (x) => (x.sw ? teinteHex(x.sw) : [x.r, x.g, x.b]);   // famille (pastille) ou couleur détectée

  if (crit === 'inverse') {
    // Symétrie autour du milieu de la plage EXISTANTE : le plus bas devient le plus haut,
    // et les écarts sont conservés. Un simple `max - h` écraserait tout vers zéro dès que
    // les hauteurs ne partent pas de zéro.
    const hs = g.map((x) => x.h), lo = Math.min(...hs), hi = Math.max(...hs);
    g.forEach((x) => { x.h = borne(lo + hi - x.h); });
  } else if (crit === 'plat') {
    g.forEach((x) => { x.h = 0.3; });
  } else if (crit === 'hasard') {
    g.forEach((x) => { x.h = borne(0.05 + Math.random() * 1.4); });
  } else if (crit === 'escalier') {
    g.forEach((x, i) => { x.h = borne(0.08 + (i / Math.max(1, g.length - 1)) * 1.4); });
  } else if (crit === 'nature') {
    if (g === FAM) FAM.forEach((f, i) => { f.h = H_DEFAUT[i]; });
    else PAL.forEach((p) => { p.h = borne(hauteurSelonCritere(p.r, p.g, p.b, 'nature')); });
  } else {
    g.forEach((x) => { const [r, v, b] = rvb(x); x.h = borne(hauteurSelonCritere(r, v, b, crit)); });
  }

  repartitionCourante = $('calcCrit').selectedOptions[0].textContent.split(' (')[0];
  rafraichirHauteursUI();
  construire();
  majEtats();
  toast(`Hauteurs : ${$('calcCrit').selectedOptions[0].textContent} · ${g === FAM ? 'familles' : 'palette'}`);
}
$('calcH').addEventListener('click', calculerHauteurs);
$('remettre').addEventListener('click', remettreEnPlace);
$('typeAnim').addEventListener('change', (e) => { typeAnim = e.target.value; });
$('reset').addEventListener('click', recadrer);

// --- Génération du canvas 2D (vue du dessus, mêmes cases classées que la 3D) ---
function genererCanvas2D() {
  if (!dernierGrid) return;
  const { C, R, cellules } = dernierGrid;
  const cellPx = Math.max(1, Math.floor(1000 / Math.max(C, R)));   // netteté raisonnable
  const cv = document.createElement('canvas');
  cv.width = C * cellPx; cv.height = R * cellPx;
  const ctx = cv.getContext('2d');
  ctx.fillStyle = '#0a0d12';                    // fond pour les cases masquées (trous)
  ctx.fillRect(0, 0, cv.width, cv.height);
  for (const c of cellules) {
    ctx.fillStyle = `rgb(${Math.round(c.r)},${Math.round(c.g)},${Math.round(c.b)})`;
    ctx.fillRect(c.i * cellPx, c.j * cellPx, cellPx, cellPx);
  }
  const corps = $('apercu2d-body');
  corps.innerHTML = '';
  corps.appendChild(cv);
  const dataUrl = cv.toDataURL('image/png');
  $('dl2d').href = dataUrl;
  $('apercu2d').hidden = false;

  // Enregistrement automatique dans le dossier dédié canvas2d/.
  $('etat2d').textContent = 'Enregistrement…';
  const body = new URLSearchParams();
  body.set('image', dataUrl);
  if (zoomCourant !== null) body.set('zoom', zoomCourant);
  fetch('save-canvas.php', { method: 'POST', body })
    .then((r) => r.json())
    .then((res) => { $('etat2d').textContent = res.ok ? `Enregistré : ${res.filename}` : `Erreur : ${res.error}`; })
    .catch(() => { $('etat2d').textContent = 'Enregistrement impossible (serveur ?).'; });
}
$('btn2d').addEventListener('click', genererCanvas2D);     // bouton flottant sur le rendu 3D
$('close2d').addEventListener('click', () => { $('apercu2d').hidden = true; });

// ============================================================
//  Mode ÉDITION : cliquer un cube pour changer sa hauteur / couleur, ou le supprimer.
// ============================================================
let editMode = false, selCell = null, downEX = 0, downEY = 0;
const surbrillance = new THREE.LineSegments(
  new THREE.EdgesGeometry(new THREE.BoxGeometry(1, 1, 1)),
  new THREE.LineBasicMaterial({ color: 0x58a6ff })
);
surbrillance.visible = false; surbrillance.renderOrder = 999; scene.add(surbrillance);
const rayEdit = new THREE.Raycaster();
const sou = new THREE.Vector2();
const hx = (v) => ('0' + Math.round(v).toString(16)).slice(-2);
const ovKey = () => selCell.i + ',' + selCell.j;
const cellCourante = () =>
  dernierGrid && selCell ? dernierGrid.cellules.find((o) => o.i === selCell.i && o.j === selCell.j) : null;

// Recale la surbrillance et le panneau sur la case sélectionnée (appelée depuis construire).
function majSelection() {
  const c = cellCourante();
  if (!c) { surbrillance.visible = false; return; }
  const { C, R } = dernierGrid, cell = LARGEUR / C;
  const h = Math.max(cell * 0.12, c.h * (cell * 6) * relief);
  poser(c.i, c.j, h, C, R, cell);          // même pose que le cube, globe compris
  surbrillance.position.copy(dummy.position);
  surbrillance.quaternion.copy(dummy.quaternion);
  surbrillance.scale.set(dummy.scale.x + 0.03, dummy.scale.y + 0.03, dummy.scale.z + 0.03);
  surbrillance.visible = true;
  $('editH').value = c.h; $('editHV').textContent = (+c.h).toFixed(2);
  $('editC').value = '#' + hx(c.r) + hx(c.g) + hx(c.b);
  $('selInfo').textContent = `(${c.i}, ${c.j})`;
}
function deselect() {
  selCell = null; surbrillance.visible = false;
  const p = document.getElementById('panelEdit'); if (p) p.hidden = true;
}

renderer.domElement.addEventListener('pointerdown', (e) => { downEX = e.clientX; downEY = e.clientY; });
renderer.domElement.addEventListener('pointerup', (e) => {
  if (modeFP || !editMode || !grille) return;
  if (Math.hypot(e.clientX - downEX, e.clientY - downEY) > 5) return;   // c'était une rotation
  sou.x = (e.clientX / innerWidth) * 2 - 1; sou.y = -(e.clientY / innerHeight) * 2 + 1;
  rayEdit.setFromCamera(sou, camera);
  const hit = rayEdit.intersectObject(grille)[0];
  if (hit && hit.instanceId != null && dernierGrid && dernierGrid.cellules[hit.instanceId]) {
    const c = dernierGrid.cellules[hit.instanceId];
    selCell = { i: c.i, j: c.j }; majSelection(); $('panelEdit').hidden = false;
  } else {
    selCell = null; surbrillance.visible = false; $('panelEdit').hidden = true;
  }
});

$('btnEdit').addEventListener('click', () => {
  editMode = !editMode;
  $('btnEdit').classList.toggle('actif', editMode);
  if (editMode) modeExclusif('cube');   // un seul mode lit le clic dans la scène
  if (!editMode) { selCell = null; surbrillance.visible = false; $('panelEdit').hidden = true; }
});
$('editH').addEventListener('input', (e) => {
  if (!selCell) return;
  memoriserGeste("hauteur d'un cube");
  const ov = overrides.get(ovKey()) || {}; ov.h = parseFloat(e.target.value); overrides.set(ovKey(), ov);
  construire();
});
$('editC').addEventListener('input', (e) => {
  if (!selCell) return;
  memoriserGeste("couleur d'un cube");
  const s = e.target.value;
  const ov = overrides.get(ovKey()) || {};
  ov.r = parseInt(s.substr(1, 2), 16); ov.g = parseInt(s.substr(3, 2), 16); ov.b = parseInt(s.substr(5, 2), 16);
  overrides.set(ovKey(), ov); construire();
});
$('editDel').addEventListener('click', () => {
  if (!selCell) return;
  memoriser('cube supprimé');
  const ov = overrides.get(ovKey()) || {}; ov.del = true; overrides.set(ovKey(), ov);
  selCell = null; surbrillance.visible = false; $('panelEdit').hidden = true; construire();
});
$('editClose').addEventListener('click', () => {
  selCell = null; surbrillance.visible = false; $('panelEdit').hidden = true;
});

// ============================================================
//  ZONES — sélectionner une étendue de couleur, puis la manipuler d'un bloc.
//
//  La sélection part d'une case cliquée et s'étend aux cases qui lui ressemblent :
//  soit **de proche en proche** (la tache que l'œil voit : un parc, un lac), soit
//  **partout sur la carte** (toute la végétation d'un coup). Le critère est la famille
//  ou la couleur à une tolérance près.
//
//  Les retouches passent par `overrides` — la même table que l'édition d'un cube. Rien
//  de nouveau à mémoriser : elles s'appliquent par-dessus l'auto, survivent aux
//  reconstructions, et le pliage du globe les emporte sans rien savoir d'elles.
// ============================================================
const TEINTE_SEL = new THREE.Color(0xf78166);     // orange de la palette du projet
const zoneSel = new Set();                        // clés "i,j" de la zone sélectionnée
let modeZone = false, zoneCrit = 'famille', zoneTol = 30, zonePortee = 'contigue';

// --- Marqueur de la zone -----------------------------------------------------
// Il est POSÉ AU-DESSUS des cases, il ne teinte pas leur couleur. La première version
// mélangeait de l'orange à la couleur de chaque case sélectionnée : on ne voyait plus le
// rendu réel, et la zone « changeait de couleur » à la désélection — impossible de juger
// une couleur qu'on est en train de choisir. Ici les cases gardent leur couleur exacte,
// et l'interrupteur « surbrillance » permet en plus de retirer le marqueur sans perdre
// la sélection.
const geoMarque = new THREE.BoxGeometry(1, 1, 1);
const vMarque = new THREE.Vector3();
let zoneMesh = null, zoneCases = [], zoneVoir = true;

function majZoneVisuel() {
  if (zoneMesh) { zoneMesh.dispose(); scene.remove(zoneMesh); zoneMesh.material.dispose(); zoneMesh = null; }
  // On ne marque QUE le contour : une case du bord est une case qui a un voisin hors zone.
  // Poser le marqueur sur toutes les cases masquerait le dessus de la zone — donc les
  // couleurs qu'on est justement en train de régler. Là, l'intérieur reste intact et
  // seul le pourtour est souligné : on voit à la fois l'étendue et le rendu réel.
  zoneCases = zoneVoir && zoneSel.size
    ? casesZone().filter((c) => (
        !zoneSel.has((c.i + 1) + ',' + c.j) || !zoneSel.has((c.i - 1) + ',' + c.j) ||
        !zoneSel.has(c.i + ',' + (c.j + 1)) || !zoneSel.has(c.i + ',' + (c.j - 1))
      ))
    : [];
  if (!zoneCases.length) return;
  zoneMesh = new THREE.InstancedMesh(
    geoMarque,
    new THREE.MeshBasicMaterial({ color: TEINTE_SEL, transparent: true, opacity: 0.55, depthWrite: false }),
    zoneCases.length
  );
  zoneMesh.frustumCulled = false;      // même raison que la grille : la boîte d'un seul cube
  zoneMesh.renderOrder = 997;
  scene.add(zoneMesh);
  poserZoneVisuel();
}

function poserZoneVisuel() {
  if (!zoneMesh || !dernierGrid) return;
  const { C, R } = dernierGrid, cell = LARGEUR / C, hMax = cell * 6, ep = cell * 0.16;
  for (let k = 0; k < zoneCases.length; k++) {
    const c = zoneCases[k];
    const h = Math.max(cell * 0.12, c.h * hMax * relief);
    poser(c.i, c.j, h, C, R, cell);                             // la pose du cube lui-même
    vMarque.set(0, 1, 0).applyQuaternion(dummy.quaternion);     // son « haut », globe compris
    dummy.position.addScaledVector(vMarque, h / 2 + ep * 0.6);  // on flotte juste au-dessus
    dummy.scale.set(dummy.scale.x * 1.03, ep, dummy.scale.z * 1.03);
    dummy.updateMatrix();
    zoneMesh.setMatrixAt(k, dummy.matrix);
  }
  zoneMesh.instanceMatrix.needsUpdate = true;
}

const cleC = (c) => c.i + ',' + c.j;
// Deux cases se ressemblent-elles ? Par famille, ou par distance de couleur.
function memeZone(a, b) {
  if (zoneCrit === 'famille') return a.f === b.f;
  return Math.abs(a.r - b.r) + Math.abs(a.g - b.g) + Math.abs(a.b - b.b) <= zoneTol * 3;
}

function selectionnerZone(depart) {
  zoneSel.clear();
  if (!dernierGrid) return;
  if (zonePortee === 'globale') {
    for (const c of dernierGrid.cellules) if (memeZone(depart, c)) zoneSel.add(cleC(c));
  } else {
    // Propagation de proche en proche (4 voisins), sur une pile : une carte à 250 000
    // cases ferait exploser la pile d'appels en récursif.
    const pile = [depart];
    zoneSel.add(cleC(depart));
    while (pile.length) {
      const c = pile.pop();
      for (const [di, dj] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
        const v = caseA(c.i + di, c.j + dj);
        if (!v || zoneSel.has(cleC(v)) || !memeZone(depart, v)) continue;
        zoneSel.add(cleC(v)); pile.push(v);
      }
    }
  }
  majZoneUI(depart);
  construire();                                   // la zone se colore en orange
}

function casesZone() {
  return dernierGrid ? dernierGrid.cellules.filter((c) => zoneSel.has(cleC(c))) : [];
}
// Applique une retouche à toute la zone : `f(ov, c)` remplit l'override de la case.
function appliquerZone(f) {
  for (const c of casesZone()) {
    const k = cleC(c), ov = overrides.get(k) || {};
    f(ov, c);
    overrides.set(k, ov);
  }
  construire();
}

function majZoneUI(depart) {
  const n = zoneSel.size;
  const e = $('etatZone'); if (e) e.textContent = n ? `${n} case${n > 1 ? 's' : ''}` : 'aucune';
  $('btnZone').classList.toggle('actif', modeZone);
  $('btnZoneBar').classList.toggle('actif', modeZone);
  $('btnZone').textContent = modeZone ? '✓ Mode zone actif — cliquer la carte' : '🎯 Sélectionner une zone';
  const p = $('panelZone');
  p.hidden = n === 0;
  if (!n) return;
  const cs = casesZone();
  const grp = paletteMode && PAL.length ? PAL : FAM;      // le sens de `f` dépend du mode
  const nomGrp = depart && grp[depart.f] ? grp[depart.f].nom || 'couleur détectée' : '';
  // Une zone vidée n'a plus de cases dans la grille — mais elle garde ses clés, donc son
  // panneau, donc « Rétablir ». On le dit, plutôt que d'afficher une moyenne de rien.
  $('zoneInfo').textContent = cs.length
    ? `— ${n} cases${nomGrp ? ' · ' + nomGrp : ''}`
    : `— ${n} cases vidées`;
  if (cs.length) {
    const moy = cs.reduce((s, c) => s + c.h, 0) / cs.length;
    $('zoneH').value = moy; $('zoneHV').textContent = moy.toFixed(2);
  }
  if (depart) $('zoneCoul').value = '#' + hx(depart.r) + hx(depart.g) + hx(depart.b);
}
// `refaire` : la reconstruction est parfois faite juste après par l'appelant (changement
// d'image ou d'échantillonnage) — inutile de la lancer deux fois.
function deselectZone(refaire = true) {
  zoneSel.clear(); $('panelZone').hidden = true; majZoneUI();
  if (refaire) construire();
}

// La liste « Fondre en » reprend les familles : associer une zone à une autre couleur,
// c'est lui donner la couleur ET la hauteur de la famille choisie.
(function remplirFamillesZone() {
  const s = $('zoneFam');
  FAM.forEach((f, i) => { const o = document.createElement('option'); o.value = i; o.textContent = f.nom; s.appendChild(o); });
})();

// Trois modes lisent le clic dans la scène (cube, zone, objet) : un seul à la fois, sinon
// un même clic voudrait dire trois choses. Une seule fonction les arbitre — les trois
// boutons l'appellent, aucun ne connaît les autres.
function modeExclusif(garde) {
  if (garde !== 'cube' && editMode) { editMode = false; $('btnEdit').classList.remove('actif'); deselect(); }
  if (garde !== 'mesure' && modeMesure) {
    modeMesure = false; mesureDepart = null; ligneMesure.visible = false;
    $('btnMesure').classList.remove('actif'); $('btnMesure').textContent = '📏 Mesurer une distance';
  }
  if (garde !== 'zone' && modeZone) { modeZone = false; majZoneUI(); }
  if (garde !== 'objet' && modeObjet) { modeObjet = false; majObjetsUI(); }
}

$('btnZone').addEventListener('click', () => {
  modeZone = !modeZone;
  if (modeZone) modeExclusif('zone');
  majZoneUI();
});
// La barre du haut et le menu commandent la MÊME bascule : le bouton de la barre relaie,
// pour qu'il n'existe qu'un seul endroit où le mode change d'état.
$('btnZoneBar').addEventListener('click', () => { $('blocZone').open = true; $('btnZone').click(); });
$('zoneCrit').addEventListener('change', (e) => { zoneCrit = e.target.value; });
$('zonePortee').addEventListener('change', (e) => { zonePortee = e.target.value; });
$('zoneTol').addEventListener('input', (e) => {
  zoneTol = parseInt(e.target.value, 10); $('zoneTolV').textContent = e.target.value;
});
$('zoneH').addEventListener('input', (e) => {
  memoriserGeste('hauteur de la zone');
  const h = parseFloat(e.target.value); $('zoneHV').textContent = h.toFixed(2);
  appliquerZone((ov) => { ov.h = h; });
});
// ▲/▼ multiplient : le relief INTERNE de la zone est conservé (une valeur unique l'aplatirait).
const etager = (k) => appliquerZone((ov, c) => { ov.h = Math.min(4, Math.max(0.01, (ov.h ?? c.h) * k)); });
$('zoneMonter').addEventListener('click', () => { memoriser('zone montée'); etager(1.25); majZoneUI(); });
$('zoneDescendre').addEventListener('click', () => { memoriser('zone descendue'); etager(1 / 1.25); majZoneUI(); });
$('zoneCoul').addEventListener('input', (e) => {
  memoriserGeste('couleur de la zone');
  const s = e.target.value;
  const r = parseInt(s.substr(1, 2), 16), g = parseInt(s.substr(3, 2), 16), b = parseInt(s.substr(5, 2), 16);
  appliquerZone((ov) => { ov.r = r; ov.g = g; ov.b = b; });
});
$('zoneAssoc').addEventListener('click', () => {
  const f = FAM[parseInt($('zoneFam').value, 10)]; if (!f) return;
  memoriser('zone associée à ' + f.nom);
  const c = new THREE.Color(f.sw);                 // la pastille de la famille = sa couleur
  appliquerZone((ov) => {
    ov.r = Math.round(c.r * 255); ov.g = Math.round(c.g * 255); ov.b = Math.round(c.b * 255);
    ov.h = f.h;
  });
  majZoneUI();
});
// Vider les CASES : la zone devient un trou. On garde la sélection — c'est elle qui permet
// de revenir en arrière avec « Rétablir » ; la lâcher rendrait l'effacement définitif.
$('zoneEffacer').addEventListener('click', () => {
  memoriser('zone vidée');
  deplanterZone(true);                             // sinon les arbres flottent au-dessus du trou
  appliquerZone((ov) => { ov.del = true; });
  majZoneUI();
  toast('🗑 Zone vidée — « ↺ Rétablir » la remet');
});
// Rétablir : on jette les retouches de la zone (hauteur, couleur, effacement) et les cases
// reprennent ce que l'image dit d'elles. On travaille sur les CLÉS, pas sur les cases : une
// case effacée n'existe plus dans la grille, mais sa clé est toujours dans la sélection.
$('zoneReset').addEventListener('click', () => {
  memoriser('zone rétablie');
  for (const k of zoneSel) overrides.delete(k);
  construire(); majZoneUI();
  toast('↺ Zone rétablie telle que l’image la donne');
});
// --- Planter des objets dans la zone -----------------------------------------
// C'est la rencontre des deux outils : la zone dit OÙ, la densité dit COMBIEN. Chaque
// objet est décalé au hasard dans sa case et sa taille varie de ±25 % — sans ça, une
// forêt plantée d'un clic ressemble à un damier, pas à une forêt.
const MAX_OBJETS = 900;                 // plafond : au-delà, l'affichage s'écroule

// Quels objets sont DANS la zone ? On repasse leur place relative en numéros de case.
function objetsDansZone() {
  if (!zoneSel.size || !dernierGrid) return [];
  const { C, R } = dernierGrid;
  return objets.filter((o) => zoneSel.has(
    Math.min(C - 1, Math.floor(o.u * C)) + ',' + Math.min(R - 1, Math.floor(o.v * R))
  ));
}
// Retire les objets de la zone — le pendant de « planter », sans quoi on ne peut
// qu'empiler : une zone se gère dans les deux sens ou elle ne se gère pas.
function deplanterZone(silencieux) {
  if (!silencieux) memoriser('objets retirés');   // en mode silencieux, l'appelant a déjà mémorisé
  const cibles = objetsDansZone();
  for (const o of cibles) {
    groupeObjets.remove(o.mesh);        // pas de dispose : tout est partagé avec le modèle
    objets.splice(objets.indexOf(o), 1);
  }
  majObjetsUI();
  if (!silencieux) toast(cibles.length ? `🗑 ${cibles.length} objet${cibles.length > 1 ? 's' : ''} retiré${cibles.length > 1 ? 's' : ''}` : 'Aucun objet dans cette zone');
  return cibles.length;
}

function planterDansZone() {
  if (!zoneSel.size || !dernierGrid) return;
  memoriser('plantation');
  const { C, R } = dernierGrid;
  const type = $('zonePlantType').value;
  const densite = parseInt($('zoneDens').value, 10) / 100;
  // « Remplacer » : on repart d'une zone nette. Sans ça, bouger la densité et recliquer
  // empile les couches et le résultat ne correspond plus au réglage affiché.
  if ($('zoneRemplacer').checked) deplanterZone(true);
  let n = 0, plafond = false;
  for (const c of casesZone()) {
    if (Math.random() > densite) continue;
    if (objets.length >= MAX_OBJETS) { plafond = true; break; }
    const mesh = faireObjet(type);
    groupeObjets.add(mesh);
    objets.push({
      type,                                        // retenu : c'est lui qu'un projet rechargera
      u: (c.i + 0.5 + (Math.random() - 0.5) * 0.85) / C,
      v: (c.j + 0.5 + (Math.random() - 0.5) * 0.85) / R,
      taille: tailleObjet * (0.75 + Math.random() * 0.5),
      rot: Math.random() * Math.PI * 2,
      mesh,
    });
    n++;
  }
  poserObjets(); majObjetsUI();
  toast(`🌳 ${n} posé${n > 1 ? 's' : ''} dans la zone` + (plafond ? ` — plafond de ${MAX_OBJETS} atteint` : ''));
}
// ＋ / － retaillent SEULEMENT les objets de la zone (le curseur du menu, lui, agit sur
// tous) : c'est ce qui permet une forêt de grands arbres ici et de buissons là.
function retaillerZone(k) {
  const cibles = objetsDansZone();
  if (!cibles.length) { toast('Aucun objet dans cette zone'); return; }
  for (const o of cibles) o.taille = Math.min(10, Math.max(0.05, o.taille * k));
  poserObjets();
  toast(`${k > 1 ? '＋' : '－'} ${cibles.length} objet${cibles.length > 1 ? 's' : ''} retaillé${cibles.length > 1 ? 's' : ''}`);
}
$('zonePlanter').addEventListener('click', planterDansZone);
$('zonePlus').addEventListener('click', () => retaillerZone(1.25));
$('zoneMoins').addEventListener('click', () => retaillerZone(1 / 1.25));
$('zoneDeplanter').addEventListener('click', () => deplanterZone());
$('zoneDens').addEventListener('input', (e) => { $('zoneDensV').textContent = e.target.value + ' %'; });

$('zoneFermer').addEventListener('click', deselectZone);
// Éteindre le marqueur SANS perdre la sélection : c'est ce qui permet de juger une
// couleur qu'on vient d'appliquer, puis de continuer à manipuler la même zone.
$('zoneVoir').addEventListener('change', (e) => { zoneVoir = e.target.checked; majZoneVisuel(); });

// Clic dans la scène en mode zone : la case touchée donne le point de départ.
renderer.domElement.addEventListener('pointerup', (e) => {
  if (modeFP || !modeZone || !grille) return;
  if (Math.hypot(e.clientX - downEX, e.clientY - downEY) > 5) return;   // c'était une rotation
  sou.x = (e.clientX / innerWidth) * 2 - 1; sou.y = -(e.clientY / innerHeight) * 2 + 1;
  rayEdit.setFromCamera(sou, camera);
  const hit = rayEdit.intersectObject(grille)[0];
  if (!hit || hit.instanceId == null || !dernierGrid) { deselectZone(); return; }
  const c = dernierGrid.cellules[hit.instanceId];
  if (c) { $('blocZone').open = true; selectionnerZone(c); }
});

// ============================================================
//  OBJETS POSÉS SUR LA CARTE — arbres, maisons, repères…
//
//  Ils sont fabriqués en géométries simples (pas de fichier à charger, fidèle au projet :
//  aucun build, aucun asset). Ce sont les SEULES choses inventées de la page : elles
//  n'ont donc aucun effet sur la reconstruction, elles se posent par-dessus.
//
//  Chaque objet retient sa place en coordonnées RELATIVES (u, v dans 0→1) et non en
//  (i, j) ni en position monde. C'est ce qui le rend solide : changer l'échantillonnage
//  redécoupe les cases — un objet rangé par numéro de case sauterait ailleurs — et plier
//  le globe déplace tout le monde. Avec (u, v), on retrouve la case sous l'objet, sa
//  hauteur, puis on le pose avec le même `plier()` que les cases et le marcheur.
// ============================================================
const groupeObjets = new THREE.Group();
scene.add(groupeObjets);
const objets = [];                 // { u, v, taille, rot, mesh }
let modeObjet = false, typeObjet = 'arbre', tailleObjet = 1;

// Un MODÈLE par type, fabriqué une seule fois puis cloné. `clone()` partage géométries et
// matériaux : planter 300 arbres ne crée pas 900 objets GPU distincts, seulement 300
// transformations. Corollaire : on ne libère JAMAIS ces géométries en retirant un objet —
// elles appartiennent au modèle, que les autres copies utilisent encore.
const modeles = new Map();
function faireObjet(type) {
  if (!modeles.has(type)) modeles.set(type, construireObjet(type));
  return modeles.get(type).clone();
}

// Fabrique un objet à partir de formes simples. Base au sol (y = 0), hauteur ≈ 1.
function construireObjet(type) {
  const g = new THREE.Group();
  const M = (c, r = 0.85) => new THREE.MeshStandardMaterial({ color: c, roughness: r });
  const pose = (mesh, y) => { mesh.position.y = y; g.add(mesh); return mesh; };
  const p3 = (mesh, x, y, z) => { mesh.position.set(x, y, z); g.add(mesh); return mesh; };
  if (type === 'arbre') {
    pose(new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.08, 0.4, 6), M(0x6b4a2f)), 0.2);
    pose(new THREE.Mesh(new THREE.SphereGeometry(0.3, 10, 8), M(0x4a9a52)), 0.72);
  } else if (type === 'sapin') {
    pose(new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.07, 0.3, 6), M(0x6b4a2f)), 0.15);
    pose(new THREE.Mesh(new THREE.ConeGeometry(0.3, 0.5, 8), M(0x2f7d43)), 0.5);
    pose(new THREE.Mesh(new THREE.ConeGeometry(0.22, 0.4, 8), M(0x378c4b)), 0.8);
  } else if (type === 'maison') {
    pose(new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.45, 0.45), M(0xe8e2d4)), 0.225);
    const toit = pose(new THREE.Mesh(new THREE.ConeGeometry(0.45, 0.3, 4), M(0xb4553f)), 0.6);
    toit.rotation.y = Math.PI / 4;
  } else if (type === 'tour') {
    pose(new THREE.Mesh(new THREE.CylinderGeometry(0.13, 0.2, 0.9, 8), M(0xd8d3c6)), 0.45);
    pose(new THREE.Mesh(new THREE.CylinderGeometry(0.02, 0.02, 0.3, 4), M(0x9aa0a6)), 1.05);
  } else if (type === 'rocher') {
    const r = pose(new THREE.Mesh(new THREE.IcosahedronGeometry(0.3, 0), M(0x8b8e93, 0.95)), 0.22);
    r.scale.set(1, 0.75, 0.9); r.rotation.set(0.4, 0.7, 0.2);

  // --- Êtres vivants ---------------------------------------------------------
  // Leurs proportions sont RELATIVES à l'arbre (≈ 1 de haut) : une personne fait la moitié
  // d'un arbre, un chien le quart, un chat le sixième. Sans ça, il faudrait rerégler la
  // taille à chaque changement de type — et un chat de la taille d'un sapin, ce n'est plus
  // un repère d'échelle, c'est un décor.
  } else if (type === 'personne') {
    const peau = M(0xe8c39e, 0.7), habit = M(0x4a7fd4), jean = M(0x3a4552);
    p3(new THREE.Mesh(new THREE.CylinderGeometry(0.032, 0.028, 0.2, 6), jean), -0.042, 0.1, 0);
    p3(new THREE.Mesh(new THREE.CylinderGeometry(0.032, 0.028, 0.2, 6), jean), 0.042, 0.1, 0);
    p3(new THREE.Mesh(new THREE.CapsuleGeometry(0.08, 0.15, 3, 10), habit), 0, 0.29, 0);
    p3(new THREE.Mesh(new THREE.SphereGeometry(0.072, 12, 10), peau), 0, 0.45, 0);
    const brasG = p3(new THREE.Mesh(new THREE.CapsuleGeometry(0.024, 0.13, 3, 6), habit), -0.1, 0.28, 0);
    const brasD = p3(new THREE.Mesh(new THREE.CapsuleGeometry(0.024, 0.13, 3, 6), habit), 0.1, 0.28, 0);
    brasG.rotation.z = 0.22; brasD.rotation.z = -0.22;
  } else if (type === 'chien') {
    const poil = M(0x8b5e3c), fonce = M(0x5c3d26);
    const corps = p3(new THREE.Mesh(new THREE.CapsuleGeometry(0.05, 0.12, 3, 8), poil), 0, 0.135, 0);
    corps.rotation.z = Math.PI / 2;               // le corps est couché sur l'axe X
    p3(new THREE.Mesh(new THREE.SphereGeometry(0.046, 10, 8), poil), 0.115, 0.19, 0);
    p3(new THREE.Mesh(new THREE.BoxGeometry(0.055, 0.03, 0.034), fonce), 0.155, 0.172, 0);
    for (const [x, z] of [[0.07, 0.037], [0.07, -0.037], [-0.07, 0.037], [-0.07, -0.037]]) {
      p3(new THREE.Mesh(new THREE.CylinderGeometry(0.014, 0.012, 0.14, 5), fonce), x, 0.07, z);
    }
    for (const z of [0.03, -0.03]) {
      const o = p3(new THREE.Mesh(new THREE.ConeGeometry(0.022, 0.045, 4), fonce), 0.105, 0.225, z);
      o.rotation.x = z > 0 ? 0.3 : -0.3;
    }
    const queue = p3(new THREE.Mesh(new THREE.CylinderGeometry(0.012, 0.007, 0.1, 5), poil), -0.115, 0.185, 0);
    queue.rotation.z = 1.0;
  } else if (type === 'chat') {
    const poil = M(0x77808a), clair = M(0x9aa3ad);
    const corps = p3(new THREE.Mesh(new THREE.CapsuleGeometry(0.036, 0.085, 3, 8), poil), 0, 0.1, 0);
    corps.rotation.z = Math.PI / 2;
    p3(new THREE.Mesh(new THREE.SphereGeometry(0.036, 10, 8), poil), 0.085, 0.145, 0);
    p3(new THREE.Mesh(new THREE.SphereGeometry(0.018, 8, 6), clair), 0.112, 0.132, 0);
    for (const z of [0.022, -0.022]) {            // oreilles pointues, la signature du chat
      p3(new THREE.Mesh(new THREE.ConeGeometry(0.018, 0.04, 4), poil), 0.082, 0.185, z);
    }
    for (const [x, z] of [[0.05, 0.028], [0.05, -0.028], [-0.05, 0.028], [-0.05, -0.028]]) {
      p3(new THREE.Mesh(new THREE.CylinderGeometry(0.01, 0.009, 0.1, 5), poil), x, 0.05, z);
    }
    const queue = p3(new THREE.Mesh(new THREE.CylinderGeometry(0.009, 0.006, 0.13, 5), poil), -0.095, 0.16, 0);
    queue.rotation.z = 0.55;                      // dressée : c'est ce qui la distingue du chien
  } else {                                        // repère : la seule forme non « naturelle »
    pose(new THREE.Mesh(new THREE.CylinderGeometry(0.015, 0.015, 0.7, 6), M(0xc9d1d9)), 0.35);
    pose(new THREE.Mesh(new THREE.SphereGeometry(0.13, 12, 10), M(0xf78166, 0.5)), 0.78);
  }
  g.traverse((o) => { o.castShadow = true; o.receiveShadow = true; });
  return g;
}

// Retrouver une case par ses numéros. La table n'est construite QUE si des objets
// existent (50 000 entrées à chaque reconstruction, sinon, pour rien).
function caseA(i, j) {
  if (!dernierGrid) return null;
  if (!dernierGrid.parCase) {
    dernierGrid.parCase = new Map(dernierGrid.cellules.map((c) => [c.i + ',' + c.j, c]));
  }
  return dernierGrid.parCase.get(i + ',' + j) || null;
}

// (Re)pose tous les objets : sur le relief à plat, ou sur la sphère au pourcentage courant.
function poserObjets() {
  if (!objets.length || !dernierGrid) return;
  const { C, R } = dernierGrid;
  const cell = LARGEUR / C, hMax = cell * 6, R1 = rayonPlein(C, R, cell);
  for (const o of objets) {
    const i = Math.min(C - 1, Math.floor(o.u * C)), j = Math.min(R - 1, Math.floor(o.v * R));
    const c = caseA(i, j);
    const sol = c ? Math.max(cell * 0.12, c.h * hMax * relief) : 0;   // case masquée → au sol
    const x = (o.u - 0.5) * C * cell, z = (o.v - 0.5) * R * cell;
    if (globeT <= 0.0001) {
      o.mesh.position.set(x, sol, z);
      o.mesh.quaternion.identity();
    } else {
      const g = plier(x, z, sol, R1, globeT, o.mesh.position);
      orienterPlie(g, o.mesh.quaternion);
    }
    o.mesh.rotateY(o.rot);            // orientation propre, APRÈS le calage sur la surface
    o.mesh.scale.setScalar(o.taille * LARGEUR / 30);
  }
}

function majObjetsUI() {
  const n = objets.length;
  const e = $('etatObjets'); if (e) e.textContent = n ? `${n} objet${n > 1 ? 's' : ''}` : 'aucun';
  $('btnPoser').classList.toggle('actif', modeObjet);
  $('btnObjBar').classList.toggle('actif', modeObjet);
  $('btnPoser').textContent = modeObjet ? '✓ Mode pose actif — cliquer la carte' : '➕ Poser des objets';
}
function viderObjets() {
  for (const o of objets) {
    groupeObjets.remove(o.mesh);        // pas de dispose : tout est partagé avec le modèle
  }
  objets.length = 0; majObjetsUI();
}

$('btnPoser').addEventListener('click', () => {
  modeObjet = !modeObjet;
  if (modeObjet) modeExclusif('objet');
  majObjetsUI();
});
$('btnObjBar').addEventListener('click', () => { $('btnPoser').closest('details').open = true; $('btnPoser').click(); });
$('objType').addEventListener('change', (e) => { typeObjet = e.target.value; });
// La taille vaut pour les prochains objets ET retaille en direct ceux déjà posés. On
// applique le RAPPORT de variation du curseur, pas sa valeur : chaque objet garde ainsi
// sa taille propre (celle plantée au hasard, ou celle d'un objet posé plus gros exprès),
// et tout le monde grandit ou rétrécit ensemble.
$('objTaille').addEventListener('input', (e) => {
  const nouvelle = parseFloat(e.target.value);
  const rapport = nouvelle / (tailleObjet || 1);
  tailleObjet = nouvelle;
  $('objTailleV').textContent = e.target.value;
  if (objets.length) { for (const o of objets) o.taille *= rapport; poserObjets(); }
});
$('objVider').addEventListener('click', () => { memoriser('tous les objets effacés'); viderObjets(); });

// Clic dans la scène en mode pose : sur un objet → on le retire, sur une case → on pose.
renderer.domElement.addEventListener('pointerup', (e) => {
  if (modeFP || !modeObjet || !grille) return;
  if (Math.hypot(e.clientX - downEX, e.clientY - downEY) > 5) return;   // c'était une rotation
  sou.x = (e.clientX / innerWidth) * 2 - 1; sou.y = -(e.clientY / innerHeight) * 2 + 1;
  rayEdit.setFromCamera(sou, camera);

  const surObjet = rayEdit.intersectObjects(groupeObjets.children, true)[0];
  if (surObjet) {
    let racine = surObjet.object;                 // remonter jusqu'au groupe de l'objet
    while (racine.parent && racine.parent !== groupeObjets) racine = racine.parent;
    const k = objets.findIndex((o) => o.mesh === racine);
    if (k >= 0) {
      memoriser('objet retiré');
      groupeObjets.remove(racine);      // idem : les géométries appartiennent au modèle
      objets.splice(k, 1); majObjetsUI();
      return;
    }
  }

  const hit = rayEdit.intersectObject(grille)[0];
  if (!hit || hit.instanceId == null || !dernierGrid) return;
  const c = dernierGrid.cellules[hit.instanceId];
  if (!c) return;
  memoriser('objet posé');
  const mesh = faireObjet(typeObjet);
  groupeObjets.add(mesh);
  objets.push({
    type: typeObjet,                              // retenu : c'est lui qu'un projet rechargera
    u: (c.i + 0.5) / dernierGrid.C,               // place RELATIVE : survit aux redécoupages
    v: (c.j + 0.5) / dernierGrid.R,
    taille: tailleObjet,
    rot: Math.random() * Math.PI * 2,             // un peu de variété entre deux objets voisins
    mesh,
  });
  poserObjets(); majObjetsUI();
});

// Fait S'EFFONDRER toute une COULEUR SUR PLACE : chaque bloc coloré s'affaisse (son sommet
// tombe sous la gravité), la base ne bouge JAMAIS de son emplacement — c'est ça qui reste
// réaliste. Un InstancedMesh dédié (efficace même pour des milliers de cubes).
function viderChutes() {
  for (const o of chutes) { scene.remove(o.mesh); o.mesh.geometry.dispose(); o.mesh.material.dispose(); }
  chutes.length = 0;
}
// Conditions initiales (vitesses / délais) d'un cube selon le TYPE d'animation choisi.
const rndA = () => Math.random() - 0.5;
function seedVitesses(a, k, type) {
  a.vx[k] = a.vy[k] = a.vz[k] = 0; a.sx[k] = a.sy[k] = a.sz[k] = 0; a.vTop[k] = 0; a.delai[k] = 0;
  if (type === 'effondrement') {                     // le sommet s'affaisse, base fixe
    a.delai[k] = Math.random() * 0.5;
  } else if (type === 'chute') {                     // chute droite hors champ, culbute légère
    a.vx[k] = rndA() * 0.4; a.vz[k] = rndA() * 0.4;
    a.sx[k] = rndA() * 1.1; a.sy[k] = rndA() * 0.9; a.sz[k] = rndA() * 1.1;
    a.delai[k] = Math.random() * 0.15;
  } else if (type === 'explosion') {                 // éjection vers l'extérieur + retombée + rebond
    const x = a.x0[k], z = a.z0[k], d = Math.hypot(x, z) || 1, p = 4 + Math.random() * 4;
    a.vx[k] = x / d * p + rndA() * 2.5; a.vz[k] = z / d * p + rndA() * 2.5; a.vy[k] = 4 + Math.random() * 5;
    a.sx[k] = rndA() * 9; a.sy[k] = rndA() * 7; a.sz[k] = rndA() * 9;
  } else if (type === 'enfoncement') {               // s'enfonce droit dans le sol, sur place
    a.delai[k] = Math.random() * 0.4;
  } else if (type === 'envol') {                     // s'élève et s'envole
    a.vx[k] = rndA() * 0.4; a.vz[k] = rndA() * 0.4;
    a.sx[k] = rndA() * 1.2; a.sy[k] = rndA() * 1.2; a.sz[k] = rndA() * 1.2;
    a.delai[k] = Math.random() * 0.35;
  } else if (type === 'tourbillon') {                // spirale autour du centre en montant
    a.sx[k] = rndA() * 1.5; a.sy[k] = rndA() * 1.5; a.sz[k] = rndA() * 1.5;
    a.delai[k] = Math.random() * 0.2;
  } else if (type === 'aspiration') {                // aspiré vers le centre
    a.sx[k] = rndA() * 3; a.sy[k] = rndA() * 3; a.sz[k] = rndA() * 3;
    a.delai[k] = Math.random() * 0.15;
  } else if (type === 'dislocation') {               // glisse au sol vers l'extérieur
    const x = a.x0[k], z = a.z0[k], d = Math.hypot(x, z) || 1, p = 6 + Math.random() * 4;
    a.vx[k] = x / d * p + rndA() * 1.5; a.vz[k] = z / d * p + rndA() * 1.5;
    a.sx[k] = rndA() * 1; a.sy[k] = rndA() * 2; a.sz[k] = rndA() * 1;
  } else if (type === 'rebond') {                    // saute puis retombe (élastique)
    a.vy[k] = 5 + Math.random() * 3;
    a.sx[k] = rndA() * 1; a.sz[k] = rndA() * 1;
    a.delai[k] = Math.random() * 0.1;
  }
}
// Re-largue : on remet tout au départ (position, hauteur, rotation) et on ré-amorce l'animation.
function reInit(ch) {
  const a = ch.a; ch.t = 0;
  for (let k = 0; k < ch.n; k++) {
    a.px[k] = a.x0[k]; a.py[k] = a.y0[k]; a.pz[k] = a.z0[k];
    a.rx[k] = a.ry[k] = a.rz[k] = 0; a.hCur[k] = a.hFix[k]; a.rest[k] = 0;
    seedVitesses(a, k, ch.type);
  }
}

function fallColor(f) {
  // Les chutes intègrent une gravité verticale (un bas unique) : elles n'ont pas de sens
  // sur une sphère, où le « bas » change à chaque case. On les laisse à la dalle.
  if (surGlobe()) return;
  const groupesActifs = paletteMode && PAL.length ? PAL : FAM;
  // BASCULE : re-clic sur une couleur animée → elle revient ; encore un clic → elle se ré-anime.
  const dejaIdx = chutes.findIndex((ch) => ch.groupes === groupesActifs && ch.f === f);
  if (dejaIdx >= 0) {
    const ch = chutes[dejaIdx];
    ch.retour = !ch.retour;
    if (!ch.retour) reInit(ch);                 // re-largué → on relance l'animation
    return;
  }
  if (!dernierGrid) return;
  const { C, R, cellules } = dernierGrid;
  const cell = LARGEUR / C, hMax = cell * 6;
  const cs = cellules.filter((c) => c.f === f);
  if (!cs.length) return;
  const n = cs.length, type = typeAnim;
  // Une COPIE de la forme choisie : les cubes qui tombent gardent l'allure des autres,
  // et `viderChutes()` peut disposer cette géométrie sans emporter celle de la grille.
  const inst = new THREE.InstancedMesh(geometrieCases(forme, n).clone(), new THREE.MeshStandardMaterial({ roughness: 0.9 }), n);
  inst.castShadow = true; inst.receiveShadow = true; inst.frustumCulled = false;
  // x0/y0/z0 = repère d'origine (retour) · px/py/pz = position courante · v* = vitesse ·
  // r*/s* = rotation & vitesse de rotation · sw/hFix = largeur & hauteur pleine ·
  // hCur/vTop = hauteur courante & vitesse du sommet (effondrement) · delai = amorce décalée.
  const a = {
    x0: new Float32Array(n), y0: new Float32Array(n), z0: new Float32Array(n),
    px: new Float32Array(n), py: new Float32Array(n), pz: new Float32Array(n),
    vx: new Float32Array(n), vy: new Float32Array(n), vz: new Float32Array(n),
    rx: new Float32Array(n), ry: new Float32Array(n), rz: new Float32Array(n),
    sx: new Float32Array(n), sy: new Float32Array(n), sz: new Float32Array(n),
    sw: new Float32Array(n), hFix: new Float32Array(n), hCur: new Float32Array(n),
    vTop: new Float32Array(n), delai: new Float32Array(n), rest: new Uint8Array(n),
  };
  for (let k = 0; k < n; k++) {
    const c = cs[k];
    const h = Math.max(cell * 0.12, c.h * hMax * relief);
    const x = (c.i + 0.5 - C / 2) * cell, z = (c.j + 0.5 - R / 2) * cell;
    a.x0[k] = x; a.y0[k] = h / 2; a.z0[k] = z;
    a.px[k] = x; a.py[k] = h / 2; a.pz[k] = z;
    a.sw[k] = cell * 0.99; a.hFix[k] = h; a.hCur[k] = h;
    seedVitesses(a, k, type);
    dummy.position.set(x, h / 2, z); dummy.rotation.set(0, 0, 0); dummy.scale.set(a.sw[k], h, a.sw[k]);
    dummy.updateMatrix(); inst.setMatrixAt(k, dummy.matrix);
    couleur.setRGB(c.r / 255, c.g / 255, c.b / 255).convertSRGBToLinear(); inst.setColorAt(k, couleur);
  }
  inst.instanceMatrix.needsUpdate = true; inst.instanceColor.needsUpdate = true;
  scene.add(inst);
  // La couleur quitte le rendu statique : les copies animées prennent le relais.
  chutes.push({ mesh: inst, n, a, groupes: groupesActifs, f, retour: false, t: 0, type });
  if (groupesActifs[f]) groupesActifs[f].vis = false;
  const attr = groupesActifs === PAL ? 'pvis' : 'vis';
  const box = document.querySelector(`[data-${attr}="${f}"]`); if (box) box.checked = false;
  construire();
}
// Remet toutes les chutes en place (retour animé ; le rendu statique reprend à l'arrivée).
function remettreEnPlace() {
  for (const ch of chutes) { ch.retour = true; ch.a.rest.fill(0); }
}

// ============================================================
//  Soleil pilotable — par défaut on garde l'éclairage actuel.
//  Orienté comme la carte : Nord = -Z, Est = +X, Sud = +Z, Ouest = -X.
// ============================================================
const soleilDefaut = soleil.position.clone();       // position d'origine (éclairage par défaut)
let solPilote = false, solAz = 315, solEl = 45, solOmbre = true;

// Repère visuel : une petite sphère jaune qui montre où est le soleil.
const soleilMarqueur = new THREE.Mesh(
  new THREE.SphereGeometry(1.6, 16, 12),
  new THREE.MeshBasicMaterial({ color: 0xffdf6e })
);
soleilMarqueur.visible = false;
scene.add(soleilMarqueur);

const boussole = (a) => ['N', 'NE', 'E', 'SE', 'S', 'SO', 'O', 'NO'][Math.round(a / 45) % 8];

function majSoleil() {
  if (solPilote) {
    const D = 45, a = solAz * Math.PI / 180, e = solEl * Math.PI / 180;
    soleil.position.set(
      D * Math.cos(e) * Math.sin(a),    // Est/Ouest → X
      D * Math.sin(e),                  // hauteur → Y
      -D * Math.cos(e) * Math.cos(a)    // Nord/Sud → Z (Nord = -Z)
    );
    soleilMarqueur.position.copy(soleil.position);
    soleilMarqueur.visible = true;
  } else {
    soleil.position.copy(soleilDefaut);
    soleilMarqueur.visible = false;
  }
  // Ombres : on garde le shadow map actif, on ne fait que (dé)brancher l'émission du soleil.
  soleil.castShadow = solOmbre;
  majEtats();
}

$('solPilote').addEventListener('change', (e) => {
  solPilote = e.target.checked;
  $('solCtrls').hidden = !solPilote;
  majSoleil();
});
$('solAz').addEventListener('input', (e) => {
  solAz = parseInt(e.target.value, 10); $('solAzV').textContent = boussole(solAz); majSoleil();
});
$('solEl').addEventListener('input', (e) => {
  solEl = parseInt(e.target.value, 10); $('solElV').textContent = solEl + '°'; majSoleil();
});
$('solOmbre').addEventListener('change', (e) => { solOmbre = e.target.checked; majSoleil(); });
let solAuto = false, solVit = 15;      // degrés d'azimut par seconde
$('solAuto').addEventListener('change', (e) => { solAuto = e.target.checked; });
$('solVit').addEventListener('input', (e) => { solVit = parseFloat(e.target.value); $('solVitV').textContent = e.target.value; });
$('file').addEventListener('change', (e) => {
  const f = e.target.files[0]; if (f) chargerImage(URL.createObjectURL(f), f.name);
});

// ============================================================
//  Mode PREMIÈRE PERSONNE — se balader DANS le relief (PointerLock + ZQSD/flèches).
//  On marche SUR la surface : à chaque image un rayon vers le bas trouve le bloc (ou le sol)
//  sous nos pieds et cale la hauteur des yeux au-dessus → on grimpe le relief naturellement.
//  OrbitControls est COUPÉ pendant ce temps, sinon les deux se disputeraient la caméra.
// ============================================================
//  Le marcheur est un « PORTEUR » : un repère posé au sol, dont le +Y est le haut LOCAL.
//  La caméra en est l'enfant, à hauteur d'yeux. Deux conséquences décisives :
//   · sur le globe, le porteur s'incline avec la surface — on marche la tête vers le ciel
//     local, et l'horizon reste horizontal même de l'autre côté de la boule ;
//   · PointerLockControls agit sur la rotation LOCALE de la caméra, donc son lacet tourne
//     autour du haut local et non de l'axe Y du monde : le regard reste juste partout.
//  La position du marcheur est gardée en coordonnées de la DALLE (fpX, fpZ), jamais en
//  coordonnées du monde : le pliage conserve les longueurs, donc avancer d'un mètre sur la
//  carte, c'est avancer d'un mètre sur la sphère. Le même `plier()` que les cases place
//  ensuite le porteur — le marcheur suit le pliage en direct, même en pleine animation.
const fp = new PointerLockControls(camera, renderer.domElement);
const porteur = new THREE.Group();
scene.add(porteur);
let modeFP = false;
const fovOrbite = camera.fov;
let hauteurOeil = 0.25;    // « taille » : hauteur des yeux (bas = petit/immergé, haut = géant)
let vitesseFP = 6;         // vitesse de marche (course = ×2,3), réglable dans le voile
let fpX = 0, fpZ = 0;      // où est le marcheur SUR LA CARTE (repère de la dalle)
let fpSol = 0;             // hauteur du sol sous ses pieds, lissée (pas de saut de marche)
const marche = { avant: false, arriere: false, gauche: false, droite: false, courir: false };
const rayFP = new THREE.Raycaster();
const HAUT_RAY = 100;      // d'où part le rayon qui cherche le sol, au-dessus de la surface
const tmpFP = new THREE.Vector3(), basFP = new THREE.Vector3();

function entrerFP() {
  modeFP = true;
  $('btnFP').classList.add('actif');
  controls.enabled = false; controls.autoRotate = false;
  const cb = $('autorot'); if (cb) cb.checked = false;
  editMode = false; $('btnEdit').classList.remove('actif'); $('panelEdit').hidden = true; deselect();
  fpX = 0; fpZ = 0; fpSol = 0;                  // au centre de la carte
  porteur.position.set(0, 0, 0); porteur.quaternion.identity();
  porteur.add(camera);                          // la caméra quitte la scène pour le porteur
  camera.position.set(0, hauteurOeil, 0);
  camera.quaternion.identity();                 // dans le repère du porteur : face au Nord (-Z)
  camera.fov = 72; camera.near = 0.02; camera.updateProjectionMatrix();   // large + rien de trop proche
  $('fpOverlay').hidden = false;
}
function sortirFP() {
  if (!modeFP) return;
  modeFP = false;
  $('btnFP').classList.remove('actif');
  $('fpOverlay').hidden = true;
  if (fp.isLocked) fp.unlock();
  scene.add(camera);                            // la caméra redevient enfant de la scène…
  camera.quaternion.identity();                 // …et OrbitControls reprend la main dessus
  camera.fov = fovOrbite; camera.updateProjectionMatrix();
  controls.enabled = true;
  recadrer();                                   // retrouve une vue d'ensemble propre
}
// Déplacement + suivi du relief, appelé chaque image tant qu'on est en première personne.
function deplacerFP(dt) {
  if (fp.isLocked) {
    const av = (marche.avant ? 1 : 0) - (marche.arriere ? 1 : 0);
    const dr = (marche.droite ? 1 : 0) - (marche.gauche ? 1 : 0);
    if (av || dr) {
      const v = (marche.courir ? vitesseFP * 2.3 : vitesseFP) * dt;
      const l = Math.hypot(av, dr);             // même vitesse en diagonale qu'en ligne droite
      fp.moveForward(av / l * v);
      fp.moveRight(dr / l * v);
    }
  }
  // PointerLockControls a déplacé la caméra DANS le repère du porteur : on récupère ce pas
  // et on le reporte sur la position carte, puis on recentre la caméra sur son porteur.
  fpX += camera.position.x; fpZ += camera.position.z;
  camera.position.x = 0; camera.position.z = 0;
  camera.position.y = hauteurOeil;

  const C = dernierGrid ? dernierGrid.C : 1, Rr = dernierGrid ? dernierGrid.R : 1;
  const cell = LARGEUR / C;
  // On ne quitte pas la carte : au-delà du bord il n'y a rien, et sur le globe c'est le vide.
  const bx = C * cell / 2, bz = Rr * cell / 2;
  fpX = Math.min(bx, Math.max(-bx, fpX)); fpZ = Math.min(bz, Math.max(-bz, fpZ));

  // Le sol sous les pieds : un rayon qui descend le long du haut LOCAL (vertical à plat,
  // rayon de la sphère sur le globe). L'élévation trouvée est lissée, sinon monter d'une
  // marche téléporte la caméra.
  const plie = globeT > 0.0001;
  const R1 = rayonPlein(C, Rr, cell);
  if (plie) { plier(fpX, fpZ, HAUT_RAY, R1, globeT, tmpFP); basFP.copy(vHaut).negate(); }
  else { tmpFP.set(fpX, HAUT_RAY, fpZ); basFP.set(0, -1, 0); }
  rayFP.set(tmpFP, basFP);
  const inter = grille ? rayFP.intersectObject(grille) : [];
  const elev = inter.length ? HAUT_RAY - inter[0].distance : 0;
  fpSol += (elev - fpSol) * Math.min(1, dt * 10);

  if (plie) {
    const g = plier(fpX, fpZ, fpSol, R1, globeT, porteur.position);
    orienterPlie(g, porteur.quaternion);
  } else {
    porteur.position.set(fpX, fpSol, fpZ);
    porteur.quaternion.identity();
  }
}

$('btnGlobe').addEventListener('click', basculerGlobe);
// Curseur : on tient le pliage à la main, de 0 % (la dalle) à 100 % (la sphère).
$('globePct').addEventListener('input', (e) => {
  $('globePctV').textContent = e.target.value + ' %';
  reglerGlobe(parseInt(e.target.value, 10) / 100);
});
// Réglages du pliage animé : combien, et en combien de temps.
$('globeVers').addEventListener('input', (e) => {
  $('globeVersV').textContent = e.target.value + ' %'; majGlobeUI();
});
$('globeDuree').addEventListener('input', (e) => {
  $('globeDureeV').textContent = nSec(parseFloat(e.target.value)); majGlobeUI();
});
// ▶ : part de l'état courant vers la cible réglée. Déjà arrivé → il ramène à plat.
$('btnGlobeGo').addEventListener('click', () => {
  const cible = parseInt($('globeVers').value, 10) / 100;
  const duree = parseFloat($('globeDuree').value);
  animerGlobe(Math.abs(cible - globeT) < 0.005 ? 0 : cible, duree);
});
// ============================================================
//  MODE CINÉMA — pour filmer l'écran : plus de menu, plus de boutons, la scène seule.
//  Tout reste dans le DOM (on ne masque que par une classe sur <body>) : les réglages
//  continuent d'être lus, une animation lancée avant se poursuit pendant le tournage.
//  Le retour se fait à la touche C — le bouton, lui, a disparu avec le reste.
// ============================================================
let cinema = false, minuteurToast = null;
function toast(texte, ms = 2000) {
  const t = $('toast'); if (!t) return;
  t.textContent = texte; t.classList.add('on');
  clearTimeout(minuteurToast);
  minuteurToast = setTimeout(() => t.classList.remove('on'), ms);
}
function basculerCinema() {
  cinema = !cinema;
  document.body.classList.toggle('cinema', cinema);
  toast(cinema ? '🎬 Mode cinéma — touche C pour revenir' : 'Menu de nouveau visible');
}
$('btnCine').addEventListener('click', basculerCinema);
addEventListener('keydown', (e) => {
  if (e.code !== 'KeyC' || e.ctrlKey || e.metaKey || e.altKey) return;
  const a = document.activeElement;   // pas pendant qu'on tape dans un réglage
  if (a && ['INPUT', 'SELECT', 'TEXTAREA'].includes(a.tagName)) return;
  basculerCinema();
});

$('btnFP').addEventListener('click', () => { modeFP ? sortirFP() : entrerFP(); });
$('fpEntrer').addEventListener('click', () => fp.lock());     // capture la souris (geste utilisateur requis)
$('fpSortir').addEventListener('click', sortirFP);
$('fpTaille').addEventListener('input', (e) => {
  hauteurOeil = parseFloat(e.target.value); $('fpTailleV').textContent = e.target.value;
});
$('fpVitesse').addEventListener('input', (e) => {
  vitesseFP = parseFloat(e.target.value); $('fpVitesseV').textContent = e.target.value;
});
fp.addEventListener('lock', () => { $('fpOverlay').hidden = true; });
fp.addEventListener('unlock', () => { if (modeFP) $('fpOverlay').hidden = false; });   // Échap → pause

addEventListener('keydown', (e) => {
  if (!modeFP) return;
  switch (e.code) {
    case 'KeyW': case 'ArrowUp': marche.avant = true; break;
    case 'KeyS': case 'ArrowDown': marche.arriere = true; break;
    case 'KeyA': case 'ArrowLeft': marche.gauche = true; break;
    case 'KeyD': case 'ArrowRight': marche.droite = true; break;
    case 'ShiftLeft': case 'ShiftRight': marche.courir = true; break;
    default: return;
  }
  e.preventDefault();                            // pas de défilement de page pendant la marche
});
addEventListener('keyup', (e) => {
  switch (e.code) {
    case 'KeyW': case 'ArrowUp': marche.avant = false; break;
    case 'KeyS': case 'ArrowDown': marche.arriere = false; break;
    case 'KeyA': case 'ArrowLeft': marche.gauche = false; break;
    case 'KeyD': case 'ArrowRight': marche.droite = false; break;
    case 'ShiftLeft': case 'ShiftRight': marche.courir = false; break;
  }
});

// ============================================================
//  ANNULATION (Ctrl+Z) — un instantané avant chaque geste qui modifie le travail.
//
//  On ne mémorise QUE l'ouvrage : retouches de cases, objets posés, hauteurs. Pas les
//  réglages de vue (échantillonnage, pliage, netteté) — annuler devrait défaire ce qu'on a
//  FAIT, pas le point de vue qu'on a choisi ; et une grille de 50 000 cases dans chaque
//  instantané rendrait l'historique impraticable.
//
//  Un instantané est une chaîne JSON : ni référence partagée, ni objet Three.js dedans,
//  donc rien qui puisse être modifié par accident après coup.
// ============================================================
const historique = [];
const MAX_HISTOIRE = 25;        // au-delà, on oublie les plus anciens
let gesteEnCours = false;       // pour ne mémoriser qu'UNE fois par glissement de curseur

function instantane() {
  return JSON.stringify({
    ov: [...overrides.entries()],
    obj: objets.map((o) => [o.type || 'arbre', o.u, o.v, o.taille, o.rot]),
    fam: FAM.map((f) => [f.h, f.vis]),
    pal: PAL.map((p) => [p.h, p.vis]),
  });
}
function memoriser(nom) {
  historique.push({ nom, etat: instantane() });
  if (historique.length > MAX_HISTOIRE) historique.shift();
}
// Pour les curseurs : le premier `input` d'un glissement mémorise, les suivants non.
function memoriserGeste(nom) { if (!gesteEnCours) { memoriser(nom); gesteEnCours = true; } }
addEventListener('change', () => { gesteEnCours = false; }, true);

function annuler() {
  const e = historique.pop();
  if (!e) { toast('Rien à annuler'); return; }
  const p = JSON.parse(e.etat);
  overrides.clear();
  for (const [cle, ov] of p.ov) overrides.set(cle, ov);
  p.fam.forEach(([h, vis], i) => { if (FAM[i]) { FAM[i].h = h; FAM[i].vis = vis; } });
  p.pal.forEach(([h, vis], i) => { if (PAL[i]) { PAL[i].h = h; PAL[i].vis = vis; } });
  viderObjets();
  for (const [type, u, v, taille, rot] of p.obj) {
    const mesh = faireObjet(type);
    groupeObjets.add(mesh);
    objets.push({ type, u, v, taille, rot, mesh });
  }
  rafraichirHauteursUI();
  // Les cases à cocher des familles suivent aussi : elles font partie de l'ouvrage.
  FAM.forEach((f, i) => { const c = conteneur.querySelector(`[data-vis="${i}"]`); if (c) c.checked = f.vis; });
  construire(); poserObjets(); majObjetsUI(); majEtats();
  toast(`↶ annulé : ${e.nom}${historique.length ? ` (${historique.length} en réserve)` : ''}`);
}

addEventListener('keydown', (e) => {
  if (!(e.ctrlKey || e.metaKey) || e.code !== 'KeyZ') return;
  const a = document.activeElement;
  if (a && ['INPUT', 'SELECT', 'TEXTAREA'].includes(a.tagName)) return;   // pas pendant une saisie
  e.preventDefault();
  annuler();
});
$('btnAnnuler').addEventListener('click', annuler);

// ============================================================
//  SÉQUENCE — enchaîner des étapes minutées, pour filmer une démonstration d'un geste.
//
//  Chaque étape ne fait que DÉCLENCHER ce qui existe déjà (pliage animé, montée, rotation
//  auto, course du soleil) puis attendre sa durée. Rien n'est réimplémenté : une séquence
//  est un chef d'orchestre, pas un second moteur d'animation.
// ============================================================
const sequence = [];
let seqEnCours = null;          // { i, reste } — étape courante et temps restant

const NOM_ETAPE = {
  globe: (v) => `plier vers ${v} %`,
  montee: () => 'faire monter le relief',
  tour: () => "tourner d'un tour",
  soleil: () => 'course du soleil',
  pause: () => 'attendre',
};

function majSeqUI() {
  const l = $('seqListe');
  l.innerHTML = '';
  sequence.forEach((e, i) => {
    const d = document.createElement('div');
    d.className = 'row';
    d.innerHTML = `<span style="opacity:.55;font-size:11px;min-width:1.4em;">${i + 1}.</span>` +
      `<span style="flex:1;font-size:12px;">${NOM_ETAPE[e.type](e.val)} <b>· ${nSec(e.duree)}</b></span>`;
    const b = document.createElement('button');
    b.textContent = '✕'; b.title = 'retirer cette étape';
    b.addEventListener('click', () => { sequence.splice(i, 1); majSeqUI(); });
    d.appendChild(b);
    l.appendChild(d);
  });
  const total = sequence.reduce((s, e) => s + e.duree, 0);
  $('etatSeq').textContent = sequence.length
    ? `${sequence.length} étape${sequence.length > 1 ? 's' : ''} · ${nSec(total)}`
    : 'aucune étape';
  $('seqJouer').textContent = seqEnCours ? '⏹ Arrêter' : '▶ Jouer la séquence';
}

function lancerEtape(e) {
  if (e.type === 'globe') animerGlobe(e.val / 100, e.duree);
  else if (e.type === 'montee') { animVitesse = Math.max(0.1, 1 / Math.max(0.5, e.duree * 0.8)); demarrerMontee(); }
  else if (e.type === 'tour') {
    // Un tour complet en `duree` : OrbitControls compte en tours-par-minute déguisés
    // (60 / autoRotateSpeed secondes par tour), d'où le rapport.
    controls.autoRotateSpeed = 60 / e.duree;
    controls.autoRotate = true; $('autorot').checked = true;
  } else if (e.type === 'soleil') {
    solPilote = true; $('solPilote').checked = true; $('solCtrls').hidden = false;
    solVit = 360 / Math.max(0.5, e.duree); $('solVit').value = Math.min(60, solVit);
    solAuto = true; $('solAuto').checked = true; majSoleil();
  }
}
function finirEtape(e) {
  if (e.type === 'tour') { controls.autoRotate = false; $('autorot').checked = false; }
  if (e.type === 'soleil') { solAuto = false; $('solAuto').checked = false; }
}

function jouerSequence() {
  if (seqEnCours) { arreterSequence(); return; }
  if (!sequence.length) { toast('Ajoutez au moins une étape'); return; }
  if ($('seqCine').checked && !cinema) basculerCinema();
  seqEnCours = { i: 0, reste: sequence[0].duree };
  lancerEtape(sequence[0]);
  majSeqUI();
}
function arreterSequence() {
  if (!seqEnCours) return;
  finirEtape(sequence[seqEnCours.i] || {});
  seqEnCours = null;
  if (cinema) basculerCinema();
  majSeqUI();
  toast('⏹ Séquence arrêtée');
}
// Avance la séquence, appelée à chaque image : le temps réel, pas un compte d'images.
function avancerSequence(dt) {
  if (!seqEnCours) return;
  seqEnCours.reste -= dt;
  if (seqEnCours.reste > 0) return;
  finirEtape(sequence[seqEnCours.i]);
  seqEnCours.i++;
  if (seqEnCours.i >= sequence.length) {
    seqEnCours = null;
    if (cinema) basculerCinema();
    majSeqUI(); toast('✔ Séquence terminée');
    return;
  }
  const e = sequence[seqEnCours.i];
  seqEnCours.reste = e.duree;
  lancerEtape(e);
  majSeqUI();
}

$('seqType').addEventListener('change', () => {
  // « Vers » n'a de sens que pour le pliage : on le grise pour les autres.
  const pliage = $('seqType').value === 'globe';
  $('seqVal').disabled = !pliage;
  $('seqVal').style.opacity = pliage ? '' : '.4';
});
$('seqVal').addEventListener('input', (e) => { $('seqValV').textContent = e.target.value + ' %'; });
$('seqDuree').addEventListener('input', (e) => { $('seqDureeV').textContent = nSec(parseFloat(e.target.value)); });
$('seqAjouter').addEventListener('click', () => {
  sequence.push({
    type: $('seqType').value,
    val: parseInt($('seqVal').value, 10),
    duree: parseFloat($('seqDuree').value),
  });
  majSeqUI();
});
$('seqVider').addEventListener('click', () => { arreterSequence(); sequence.length = 0; majSeqUI(); });
$('seqJouer').addEventListener('click', jouerSequence);
majSeqUI();

// ============================================================
//  ÉCHELLE RÉELLE — le zoom et la latitude donnent les mètres par pixel.
//
//    mètres_par_pixel = 156543,03 × cos(latitude) / 2^zoom
//
//  La latitude compte : à l'équateur un pixel couvre le double de ce qu'il couvre à 60°.
//  On la lit dans le .json de la capture ; sans lui, on prévient au lieu d'inventer.
// ============================================================
let metresParPixel = null;      // pour l'image d'origine, avant échantillonnage
let modeMesure = false, mesureDepart = null;
const ligneMesure = new THREE.Line(
  new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(), new THREE.Vector3()]),
  new THREE.LineBasicMaterial({ color: 0xf78166, depthTest: false })
);
ligneMesure.visible = false; ligneMesure.renderOrder = 999; scene.add(ligneMesure);

function majEchelle() {
  const t = $('echelleTexte');
  if (zoomCourant === null || !NW) {
    metresParPixel = null;
    t.innerHTML = 'Zoom inconnu : sans lui, aucune échelle réelle (le nom du fichier le porte).';
    $('etatEchelle').textContent = '—';
    return;
  }
  const lat = (bornesNoms && (bornesNoms.nord + bornesNoms.sud) / 2) ?? 0;
  metresParPixel = 156543.03 * Math.cos(lat * Math.PI / 180) / Math.pow(2, zoomCourant);
  const largeurM = metresParPixel * NW;               // largeur réelle de la carte
  const caseM = largeurM / finesse;                    // une case de la grille
  const fmt = (m) => (m >= 1000 ? (m / 1000).toFixed(m >= 10000 ? 0 : 1) + ' km' : m.toFixed(m < 10 ? 1 : 0) + ' m');
  t.innerHTML = `À z${zoomCourant} et ${lat.toFixed(1)}° de latitude : <b>${fmt(largeurM)}</b> de large, ` +
    `soit <b>${fmt(caseM)}</b> par case et <b>${fmt(metresParPixel)}</b> par pixel d'origine.`;
  $('etatEchelle').textContent = fmt(largeurM);
}

// Une distance sur la dalle → des mètres. On mesure sur la CARTE (repère de la dalle), pas
// dans le monde : plié en globe, la distance à vol d'oiseau reste celle de la carte.
function distanceReelle(a, b) {
  if (!metresParPixel || !dernierGrid) return null;
  const cell = LARGEUR / dernierGrid.C;
  const uniteM = (metresParPixel * NW) / LARGEUR;      // mètres par unité de scène
  return Math.hypot(a.x - b.x, a.z - b.z) * uniteM;
}

$('btnMesure').addEventListener('click', () => {
  modeMesure = !modeMesure;
  if (modeMesure) modeExclusif('mesure');
  mesureDepart = null; ligneMesure.visible = false;
  $('btnMesure').classList.toggle('actif', modeMesure);
  $('btnMesure').textContent = modeMesure ? '✓ Mode mesure — cliquer deux points' : '📏 Mesurer une distance';
  if (modeMesure && !metresParPixel) toast("Zoom inconnu : la mesure ne peut pas être convertie en mètres", 4000);
});

renderer.domElement.addEventListener('pointerup', (e) => {
  if (modeFP || !modeMesure || !grille) return;
  if (Math.hypot(e.clientX - downEX, e.clientY - downEY) > 5) return;
  sou.x = (e.clientX / innerWidth) * 2 - 1; sou.y = -(e.clientY / innerHeight) * 2 + 1;
  rayEdit.setFromCamera(sou, camera);
  const hit = rayEdit.intersectObject(grille)[0];
  if (!hit || hit.instanceId == null || !dernierGrid) return;
  const c = dernierGrid.cellules[hit.instanceId];
  const cell = LARGEUR / dernierGrid.C;
  const pt = { x: (c.i + 0.5 - dernierGrid.C / 2) * cell, z: (c.j + 0.5 - dernierGrid.R / 2) * cell, monde: hit.point.clone() };
  if (!mesureDepart) { mesureDepart = pt; toast('Point de départ posé — cliquez l\'arrivée'); return; }
  const d = distanceReelle(mesureDepart, pt);
  ligneMesure.geometry.setFromPoints([mesureDepart.monde, pt.monde]);
  ligneMesure.visible = true;
  toast(d === null ? 'Distance inconnue (zoom absent)' :
    `📏 ${d >= 1000 ? (d / 1000).toFixed(2) + ' km' : d.toFixed(0) + ' m'} à vol d'oiseau`, 6000);
  mesureDepart = null;
});

// ============================================================
//  EXPORT .glb — sortir le relief pour Blender, une visionneuse, une imprimante 3D.
//
//  Les cases sont un InstancedMesh : 50 000 copies d'un même cube, un objet pour la carte
//  graphique. Le format glTF ne connaît pas cette astuce telle quelle — on FUSIONNE donc
//  les cases en un seul maillage, la couleur de chacune passant par les sommets.
//
//  D'où le plafond : chaque case coûte 24 sommets une fois fusionnée. Au-delà de ~30 000
//  cases, le fichier dépasse la centaine de mégaoctets et le navigateur cale pendant la
//  fusion. On refuse alors plutôt que de faire semblant, en disant quoi baisser.
// ============================================================
const MAX_CASES_EXPORT = 30000;

async function exporterGLB() {
  if (!dernierGrid || !grille) { toast('Rien à exporter'); return; }
  const n = dernierGrid.cellules.length;
  if (n > MAX_CASES_EXPORT) {
    toast(`${n} cases : trop pour un .glb. Baissez l'échantillonnage (~${Math.floor(Math.sqrt(MAX_CASES_EXPORT * aspect))} max)`, 6000);
    return;
  }
  toast('🧊 Fusion des cases…', 60000);
  const [{ GLTFExporter }, BGU] = await Promise.all([
    import('three/addons/exporters/GLTFExporter.js'),
    import('three/addons/utils/BufferGeometryUtils.js'),
  ]);

  // On relit les matrices RÉELLEMENT posées : ainsi l'export sort la scène telle qu'elle
  // est à l'écran, pliage du globe compris, sans recalculer quoi que ce soit.
  const base = grille.geometry;
  const m = new THREE.Matrix4(), c = new THREE.Color();
  const morceaux = [];
  for (let k = 0; k < n; k++) {
    grille.getMatrixAt(k, m);
    const g = base.clone().applyMatrix4(m);
    grille.getColorAt(k, c);
    const nbSommets = g.attributes.position.count;
    const couleurs = new Float32Array(nbSommets * 3);
    for (let i = 0; i < nbSommets; i++) { couleurs[i * 3] = c.r; couleurs[i * 3 + 1] = c.g; couleurs[i * 3 + 2] = c.b; }
    g.setAttribute('color', new THREE.BufferAttribute(couleurs, 3));
    g.deleteAttribute('uv');            // inutile ici, et il empêche la fusion s'il manque ailleurs
    morceaux.push(g);
  }
  const fusion = BGU.mergeGeometries(morceaux, false);
  morceaux.forEach((g) => g.dispose());
  if (!fusion) { toast('Fusion impossible', 4000); return; }

  const scenePropre = new THREE.Scene();
  scenePropre.add(new THREE.Mesh(fusion, new THREE.MeshStandardMaterial({ vertexColors: true, roughness: 0.92 })));
  for (const o of objets) scenePropre.add(o.mesh.clone());   // les objets sont déjà à leur place monde

  toast('🧊 Écriture du fichier…', 60000);
  new GLTFExporter().parse(
    scenePropre,
    (glb) => {
      const nom = (projetCourant || (selCapture.selectedOptions[0] || {}).textContent || 'relief')
        .replace(/\.png$/, '').replace(/[^\w\- ]+/g, '') || 'relief';
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([glb], { type: 'model/gltf-binary' }));
      a.download = nom + '.glb';
      a.click();
      setTimeout(() => URL.revokeObjectURL(a.href), 10000);
      fusion.dispose();
      toast(`🧊 ${nom}.glb — ${n} cases, ${objets.length} objets, ${(glb.byteLength / 1048576).toFixed(1)} Mo`, 5000);
    },
    (err) => { console.error(err); toast('Export échoué', 4000); },
    { binary: true, onlyVisible: true }
  );
}
$('exportGlb').addEventListener('click', exporterGLB);

// ============================================================
//  PROJETS — enregistrer et retrouver un arrangement complet.
//
//  Un projet ne contient QUE ce qu'on ne peut pas recalculer : le nom de la capture, les
//  réglages, les hauteurs, les retouches de cases et les objets posés. Ni l'image (elle
//  reste dans captures/), ni la grille (elle se reconstruit), ni la palette par pixel
//  (`palIdx` se recalcule). Un projet pèse quelques kilo-octets, pas quelques mégas.
//
//  L'ordre de restitution est ce qui compte : l'image d'abord (tout en dépend et son
//  chargement est asynchrone), puis les réglages, puis une seule reconstruction. Appliquer
//  les réglages avant l'image les ferait écraser par la remise à zéro du chargement.
// ============================================================
function etatProjet() {
  return {
    version: 1,
    capture: selCapture.selectedOptions[0] ? selCapture.selectedOptions[0].dataset.nom : null,
    reglages: {
      finesse, relief, forme, nettete, globeT, montrerNoms,
      paletteMode, palTol, repartition: repartitionCourante,
      soleil: { solPilote, solAz, solEl, solOmbre, solAuto, solVit },
    },
    familles: FAM.map((f) => ({ h: f.h, vis: f.vis })),
    palette: PAL.map((p) => ({ r: p.r, g: p.g, b: p.b, h: p.h, vis: p.vis, frac: p.frac })),
    // Les retouches de cases : la Map devient un tableau, un JSON ne sait pas faire mieux.
    retouches: [...overrides.entries()].map(([cle, ov]) => ({ cle, ...ov })),
    objets: objets.map((o) => ({ type: o.type || 'arbre', u: o.u, v: o.v, taille: o.taille, rot: o.rot })),
  };
}

function appliquerReglagesProjet(p) {
  const r = p.reglages || {};
  // `$` prend un identifiant NU ; on accepte quand même la forme « #id » pour éviter
  // l'erreur silencieuse : `getElementById('#forme')` renvoie null, et le contrôle garde
  // alors l'ancienne valeur pendant que la scène, elle, a changé.
  const met = (id, val) => { const n = $(String(id).replace('#', '')); if (n) n.value = val; };

  if (Array.isArray(p.familles)) p.familles.forEach((f, i) => { if (FAM[i]) { FAM[i].h = f.h; FAM[i].vis = f.vis; } });
  if (Array.isArray(p.palette) && p.palette.length) {
    PAL = p.palette.map((c) => ({ ...c }));
    recalculerPalIdx();                    // se recalcule depuis les pixels : jamais stocké
    afficherPalette();
  }
  paletteMode = !!r.paletteMode; $('palMode').checked = paletteMode;
  if (r.palTol != null) { palTol = r.palTol; met('#palTol', palTol); $('palTolV').textContent = palTol; }

  // Les hauteurs viennent d'être remplacées dans FAM/PAL : les curseurs du menu montrent
  // encore les précédentes. On les recale, sinon le menu ment sur ce qu'on voit.
  rafraichirHauteursUI();
  repartitionCourante = r.repartition || "d'origine";

  overrides.clear();
  for (const o of p.retouches || []) { const { cle, ...reste } = o; overrides.set(cle, reste); }

  relief = r.relief ?? 1; met('#relief', relief); $('relief-val').textContent = relief;
  forme = r.forme || 'cube'; met('#forme', forme);
  montrerNoms = !!r.montrerNoms; $('montrerNoms').checked = montrerNoms; etiquettes.visible = montrerNoms;
  if (r.nettete && r.nettete !== nettete) { nettete = r.nettete; met('#nettete', String(nettete)); majNettete(); }

  const s = r.soleil || {};
  solPilote = !!s.solPilote; $('solPilote').checked = solPilote; $('solCtrls').hidden = !solPilote;
  if (s.solAz != null) { solAz = s.solAz; met('#solAz', solAz); $('solAzV').textContent = boussole(solAz); }
  if (s.solEl != null) { solEl = s.solEl; met('#solEl', solEl); $('solElV').textContent = solEl + '°'; }
  solOmbre = s.solOmbre !== false; $('solOmbre').checked = solOmbre;
  solAuto = !!s.solAuto; $('solAuto').checked = solAuto;
  if (s.solVit != null) { solVit = s.solVit; met('#solVit', solVit); $('solVitV').textContent = solVit; }
  majSoleil();

  // La finesse en dernier : c'est elle qui déclenche la reconstruction, une seule fois.
  finesse = r.finesse || 220; met('#finesse', finesse); $('finesse-val').textContent = finesse;
  construire();

  // Les objets se reposent sur la grille reconstruite.
  viderObjets();
  for (const o of p.objets || []) {
    const mesh = faireObjet(o.type);
    groupeObjets.add(mesh);
    objets.push({ type: o.type, u: o.u, v: o.v, taille: o.taille, rot: o.rot, mesh });
  }
  poserObjets(); majObjetsUI();

  // Le pliage à la fin : il repose toute la grille, objets compris.
  reglerGlobe(r.globeT || 0);
  recadrer(); majEtats();
}

function ouvrirProjet(nom) {
  fetch(`projet.php?action=lire&nom=${encodeURIComponent(nom)}`)
    .then((r) => r.json())
    .then((res) => {
      if (!res.ok) { toast('Projet illisible : ' + res.error, 3500); return; }
      const p = res.projet;
      const opt = [...selCapture.options].find((o) => o.dataset.nom === p.capture);
      $('projNom').value = nom;
      if (opt) {
        selCapture.value = opt.value;
        // L'image d'abord : son chargement remet tout à zéro, donc les réglages ensuite.
        chargerImage(opt.value + '?p=' + Date.now(), p.capture, () => appliquerReglagesProjet(p));
      } else {
        toast(`Capture « ${p.capture} » absente — réglages appliqués sur l'image courante`, 4000);
        appliquerReglagesProjet(p);
      }
      projetCourant = nom; majEtats();
    })
    .catch(() => toast('Serveur injoignable', 3000));
}

function listerProjets(selectionner) {
  return fetch('projet.php?action=liste')
    .then((r) => r.json())
    .then((res) => {
      const sel = $('projListe');
      sel.innerHTML = '';
      if (!res.ok || !res.projets.length) { sel.innerHTML = '<option value="">(aucun projet)</option>'; return; }
      for (const p of res.projets) {
        const o = document.createElement('option');
        o.value = p.nom;
        o.textContent = `${p.nom} — ${(p.capture || '?').replace('card-maps-', '')}`;
        sel.appendChild(o);
      }
      if (selectionner) sel.value = selectionner;
    })
    .catch(() => {});
}

$('projSave').addEventListener('click', () => {
  const nom = ($('projNom').value || '').trim() || `projet ${new Date().toLocaleString('fr-FR')}`;
  const body = new URLSearchParams();
  body.set('action', 'enregistrer'); body.set('nom', nom);
  body.set('data', JSON.stringify(etatProjet()));
  fetch('projet.php', { method: 'POST', body })
    .then((r) => r.json())
    .then((res) => {
      if (!res.ok) { toast('Enregistrement refusé : ' + res.error, 3500); return; }
      projetCourant = res.nom; $('projNom').value = res.nom;
      toast(`💾 « ${res.nom} » enregistré (${(res.poids / 1024).toFixed(1)} ko)`);
      majEtats();
      return listerProjets(res.nom);
    })
    .catch(() => toast('Serveur injoignable', 3000));
});

$('projOuvrir').addEventListener('click', () => {
  const nom = $('projListe').value;
  if (nom) ouvrirProjet(nom);
});

$('projSuppr').addEventListener('click', () => {
  const nom = $('projListe').value;
  if (!nom || !confirm(`Supprimer le projet « ${nom} » ?`)) return;
  const body = new URLSearchParams(); body.set('action', 'supprimer'); body.set('nom', nom);
  fetch('projet.php', { method: 'POST', body })
    .then((r) => r.json())
    .then((res) => {
      toast(res.ok ? `🗑 projet « ${nom} » supprimé` : 'Suppression impossible');
      if (projetCourant === nom) { projetCourant = null; majEtats(); }
      return listerProjets();
    })
    .catch(() => toast('Serveur injoignable', 3000));
});

listerProjets();

const horloge = new THREE.Clock();

function animer() {
  requestAnimationFrame(animer);
  // Temps réel écoulé depuis l'image précédente : c'est LUI qui rend le mouvement fluide
  // et identique quel que soit le taux de rafraîchissement (60, 120, 144 Hz…). Plafonné :
  // après un gel (onglet en arrière-plan, GC), un dt géant téléporterait les cubes à
  // travers le sol au lieu de les faire tomber.
  const dt = Math.min(horloge.getDelta(), 0.05);

  avancerSequence(dt);                   // enchaînement des étapes filmées, s'il y en a
  if (modeFP) deplacerFP(dt);            // balade première personne (OrbitControls désactivé)
  else controls.update();

  // Soleil qui tourne tout seul (cycle jour/nuit) : on fait avancer l'azimut.
  if (solPilote && solAuto) {
    solAz = (solAz + solVit * dt) % 360;
    $('solAz').value = solAz.toFixed(0);
    $('solAzV').textContent = boussole(solAz);
    majSoleil();
  }

  // Pliage / dépliage du globe : `globeT` glisse vers sa cible et TOUTES les cases sont
  // reposées. On saute la repose si la montée est en cours : sa boucle repose déjà tout
  // (avec les mêmes poser()), et le faire deux fois n'apporterait rien.
  // Pliage animé : l'avancement va de 0 à 1 en `duree` secondes — la DURÉE demandée est
  // donc tenue quel que soit le nombre d'images par seconde. Le pourcentage, lui, suit une
  // courbe adoucie (départ et arrivée en douceur) : à vitesse constante, le démarrage et
  // l'arrêt sont secs et on voit la carte « claquer » en fin de course.
  if (globeAnim) {
    globeAnim.prog = Math.min(1, globeAnim.prog + dt / globeAnim.duree);
    const e = globeAnim.prog, k = e * e * (3 - 2 * e);
    globeT = globeAnim.depart + (globeAnim.cible - globeAnim.depart) * k;
    if (globeAnim.prog >= 1) { globeT = globeAnim.cible; globeAnim = null; }
    appliquerGlobe();     // (si la montée tourne, sa boucle repose ensuite les hauteurs)
  }

  // Montée progressive du relief, case par case.
  if (animActif && grille && animCells.length) {
    animT += animVitesse * dt;
    const FADE = 0.25;
    for (let k = 0; k < animCells.length; k++) {
      const a = animCells[k];
      let f = (animT - a.phase) / FADE; f = f < 0 ? 0 : f > 1 ? 1 : f;
      const e = 1 - Math.pow(1 - f, 3);          // ease-out
      const h = Math.max(0.0001, a.hT * e);
      poser(a.i, a.j, h, a.C, a.R, a.cell);
      grille.setMatrixAt(k, dummy.matrix);
    }
    grille.instanceMatrix.needsUpdate = true;
    if (animT > 1 + FADE) animActif = false;      // terminé : tout est à pleine hauteur
  }

  // Couleurs animées par la « gravité » : plusieurs TYPES au choix (effondrement, chute,
  // explosion, enfoncement, envol). Chacune S'ANIME puis peut SE RELEVER (retour).
  if (chutes.length) {
    const hMin = 0.02;                       // dalle résiduelle après un effondrement
    const SOLBAS = -Math.max(28, LARGEUR);   // sol bas hors champ (chute libre)
    // Retour : amortissement « par image » converti en constante calée sur le temps réel.
    const kRelever = 1 - Math.pow(1 - 0.15, dt / 0.016);
    // Sous-pas fixes (~8 ms) : intégration lisse et nette même après un ralenti (dt ≤ 50 ms).
    const SP = Math.max(1, Math.ceil(dt / 0.008));
    const hpas = dt / SP;
    for (let ci = chutes.length - 1; ci >= 0; ci--) {
      const ch = chutes[ci], a = ch.a, type = ch.type;

      if (ch.retour) {                         // RETOUR : tout revient à sa place d'origine
        let enRoute = false;
        for (let k = 0; k < ch.n; k++) {
          if (type === 'effondrement') {       // la hauteur repousse, base fixe
            a.hCur[k] += (a.hFix[k] - a.hCur[k]) * kRelever;
            if (Math.abs(a.hFix[k] - a.hCur[k]) > 0.01) enRoute = true;
            const h = Math.max(0.0001, a.hCur[k]);
            dummy.position.set(a.x0[k], h / 2, a.z0[k]); dummy.rotation.set(0, 0, 0);
            dummy.scale.set(a.sw[k], h, a.sw[k]);
          } else {                             // position + rotation reviennent à l'origine
            a.px[k] += (a.x0[k] - a.px[k]) * kRelever;
            a.py[k] += (a.y0[k] - a.py[k]) * kRelever;
            a.pz[k] += (a.z0[k] - a.pz[k]) * kRelever;
            a.rx[k] += -a.rx[k] * kRelever; a.ry[k] += -a.ry[k] * kRelever; a.rz[k] += -a.rz[k] * kRelever;
            if (Math.abs(a.x0[k] - a.px[k]) + Math.abs(a.y0[k] - a.py[k]) + Math.abs(a.z0[k] - a.pz[k])
              + Math.abs(a.rx[k]) + Math.abs(a.ry[k]) + Math.abs(a.rz[k]) > 0.02) enRoute = true;
            dummy.position.set(a.px[k], a.py[k], a.pz[k]); dummy.rotation.set(a.rx[k], a.ry[k], a.rz[k]);
            dummy.scale.set(a.sw[k], a.hFix[k], a.sw[k]);
          }
          dummy.updateMatrix(); ch.mesh.setMatrixAt(k, dummy.matrix);
        }
        ch.mesh.instanceMatrix.needsUpdate = true;
        if (!enRoute) {                        // arrivé → on rend la main au rendu statique
          scene.remove(ch.mesh); ch.mesh.geometry.dispose(); ch.mesh.material.dispose();
          chutes.splice(ci, 1);
          if (ch.groupes[ch.f]) ch.groupes[ch.f].vis = true;
          const attr = ch.groupes === PAL ? 'pvis' : 'vis';
          const b = document.querySelector(`[data-${attr}="${ch.f}"]`); if (b) b.checked = true;
          construire();
        }
        continue;
      }

      // ANIMATION en cours : sous-pas de temps réel, intégration selon le type.
      let bouge = false;
      for (let s = 0; s < SP; s++) {
        ch.t += hpas;
        for (let k = 0; k < ch.n; k++) {
          if (a.rest[k]) continue;
          bouge = true;
          if (ch.t < a.delai[k]) continue;     // amorce décalée (types concernés)
          switch (type) {
            case 'effondrement':               // le sommet tombe, la base ne bouge pas
              a.vTop[k] += 16 * hpas; a.hCur[k] -= a.vTop[k] * hpas;
              if (a.hCur[k] <= hMin) { a.hCur[k] = hMin; a.rest[k] = 1; }
              break;
            case 'chute':                      // chute droite, sort par le bas
              a.vy[k] -= 20 * hpas;
              a.px[k] += a.vx[k] * hpas; a.py[k] += a.vy[k] * hpas; a.pz[k] += a.vz[k] * hpas;
              a.rx[k] += a.sx[k] * hpas; a.ry[k] += a.sy[k] * hpas; a.rz[k] += a.sz[k] * hpas;
              if (a.py[k] <= SOLBAS) { a.py[k] = SOLBAS; a.rest[k] = 1; }
              break;
            case 'explosion': {                // éjection + retombée + rebond amorti
              a.vy[k] -= 22 * hpas;
              a.px[k] += a.vx[k] * hpas; a.py[k] += a.vy[k] * hpas; a.pz[k] += a.vz[k] * hpas;
              a.rx[k] += a.sx[k] * hpas; a.ry[k] += a.sy[k] * hpas; a.rz[k] += a.sz[k] * hpas;
              const sol = a.hFix[k] / 2;
              if (a.py[k] <= sol && a.vy[k] < 0) {
                a.py[k] = sol; a.vy[k] = -a.vy[k] * 0.42;
                a.vx[k] *= 0.7; a.vz[k] *= 0.7; a.sx[k] *= 0.6; a.sy[k] *= 0.6; a.sz[k] *= 0.6;
                if (Math.abs(a.vy[k]) < 0.7) { a.vy[k] = 0; a.rest[k] = 1; }
              }
              break;
            }
            case 'enfoncement':                // s'enfonce droit dans le sol, sur place
              a.vy[k] -= 14 * hpas; a.py[k] += a.vy[k] * hpas;
              if (a.py[k] <= -a.hFix[k] / 2) { a.py[k] = -a.hFix[k] / 2; a.rest[k] = 1; }
              break;
            case 'envol':                      // s'élève et s'envole vers le haut
              a.vy[k] += 8 * hpas;
              a.px[k] += a.vx[k] * hpas; a.py[k] += a.vy[k] * hpas; a.pz[k] += a.vz[k] * hpas;
              a.rx[k] += a.sx[k] * hpas; a.ry[k] += a.sy[k] * hpas; a.rz[k] += a.sz[k] * hpas;
              if (a.py[k] >= a.y0[k] + 45) { a.py[k] = a.y0[k] + 45; a.rest[k] = 1; }
              break;
            case 'tourbillon': {               // orbite autour du centre + montée
              const ang = 2.6 * hpas, cw = Math.cos(ang), sw2 = Math.sin(ang);
              const nx = a.px[k] * cw - a.pz[k] * sw2, nz = a.px[k] * sw2 + a.pz[k] * cw;
              a.px[k] = nx; a.pz[k] = nz;
              a.vy[k] += 6 * hpas; a.py[k] += a.vy[k] * hpas;
              a.rx[k] += a.sx[k] * hpas; a.ry[k] += a.sy[k] * hpas; a.rz[k] += a.sz[k] * hpas;
              if (a.py[k] >= a.y0[k] + 40) { a.py[k] = a.y0[k] + 40; a.rest[k] = 1; }
              break;
            }
            case 'aspiration': {               // attiré vers l'axe central, en accélérant
              const dx = -a.px[k], dz = -a.pz[k], dl = Math.hypot(dx, dz) || 1;
              a.vx[k] += dx / dl * 26 * hpas; a.vz[k] += dz / dl * 26 * hpas;
              a.px[k] += a.vx[k] * hpas; a.pz[k] += a.vz[k] * hpas;
              a.rx[k] += a.sx[k] * hpas; a.ry[k] += a.sy[k] * hpas; a.rz[k] += a.sz[k] * hpas;
              if (a.px[k] * a.px[k] + a.pz[k] * a.pz[k] < 0.25) { a.px[k] = 0; a.pz[k] = 0; a.rest[k] = 1; }
              break;
            }
            case 'dislocation':                // glisse au sol vers l'extérieur, freine, s'arrête loin
              a.px[k] += a.vx[k] * hpas; a.pz[k] += a.vz[k] * hpas;
              a.vx[k] *= 0.985; a.vz[k] *= 0.985;
              a.rx[k] += a.sx[k] * hpas; a.ry[k] += a.sy[k] * hpas; a.rz[k] += a.sz[k] * hpas;
              if (a.px[k] * a.px[k] + a.pz[k] * a.pz[k] > LARGEUR * LARGEUR * 2.56) a.rest[k] = 1;
              break;
            case 'rebond': {                   // saut vertical + rebonds amortis, se pose à l'origine
              a.vy[k] -= 20 * hpas; a.py[k] += a.vy[k] * hpas;
              a.rx[k] += a.sx[k] * hpas; a.rz[k] += a.sz[k] * hpas;
              const sol = a.y0[k];
              if (a.py[k] <= sol && a.vy[k] < 0) {
                a.py[k] = sol; a.vy[k] = -a.vy[k] * 0.5; a.sx[k] *= 0.6; a.sz[k] *= 0.6;
                if (Math.abs(a.vy[k]) < 0.8) { a.vy[k] = 0; a.rx[k] = 0; a.rz[k] = 0; a.rest[k] = 1; }
              }
              break;
            }
          }
        }
      }
      if (bouge) {                             // une seule écriture des matrices par image
        for (let k = 0; k < ch.n; k++) {
          if (type === 'effondrement') {
            const h = Math.max(0.0001, a.hCur[k]);
            dummy.position.set(a.x0[k], h / 2, a.z0[k]); dummy.rotation.set(0, 0, 0);
            dummy.scale.set(a.sw[k], h, a.sw[k]);
          } else {
            dummy.position.set(a.px[k], a.py[k], a.pz[k]); dummy.rotation.set(a.rx[k], a.ry[k], a.rz[k]);
            dummy.scale.set(a.sw[k], a.hFix[k], a.sw[k]);
          }
          dummy.updateMatrix(); ch.mesh.setMatrixAt(k, dummy.matrix);
        }
        ch.mesh.instanceMatrix.needsUpdate = true;
      }
    }
  }

  if (!modeFP) {                          // le zoom molette ne s'applique qu'en vue d'ensemble
    const dir = camera.position.clone().sub(controls.target);
    const longueur = dir.length() || 1;
    const kZoom = 1 - Math.pow(1 - 0.12, dt / 0.016);   // approche du zoom, indépendante du fps
    const nouvelle = THREE.MathUtils.lerp(longueur, distCible, kZoom);
    if (Math.abs(nouvelle - longueur) > 1e-4) { dir.setLength(nouvelle); camera.position.copy(controls.target).add(dir); }
  }
  renderer.render(scene, camera);
}
animer();

addEventListener('resize', () => {
  camera.aspect = innerWidth / innerHeight;
  camera.updateProjectionMatrix();
  renderer.setSize(innerWidth, innerHeight);
});
