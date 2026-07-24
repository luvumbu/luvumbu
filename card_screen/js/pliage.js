import * as THREE from 'three';

// ============================================================
//  PLIAGE — la carte se plie sur une sphère. Elle n'est pas déformée : on la COURBE.
//
//  Le pourcentage est une COURBURE, pas un mélange entre deux formes. À `t`, la carte est
//  posée sur une sphère de rayon `R1 / t` : à 1 % le rayon est énorme (la dalle bombe à
//  peine), à 100 % il vaut `R1`. Comme les angles valent `distance / rayon`, **les
//  longueurs sont conservées** : une case fait la même taille à 1 %, à 80 % et à 100 %.
//  Rien ne s'écrase, rien ne se replie sur soi.
//
//  Le rayon final `R1` est le PLUS PETIT sur lequel la carte tient sans se recouvrir : sa
//  largeur ne peut pas dépasser un tour (2πR) ni sa profondeur un demi-tour (πR).
//  Conséquence assumée : une carte n'est pas une sphère (théorème de Gauss) — seule une
//  image 2:1 la ferme exactement. Plus étroite, il reste un JOINT ouvert derrière. On
//  préfère ce joint à des cases comprimées : on ne déforme pas la carte pour boucler.
// ============================================================

// Base locale du point plié, remplie par `plier()` : à l'est, au ciel, au sud.
export const vEst = new THREE.Vector3();
export const vHaut = new THREE.Vector3();
export const vSud = new THREE.Vector3();
export const mBase = new THREE.Matrix4();

// Rayon de la sphère à 100 %, pour une dalle de C×R cases de côté `cell`.
export function rayonPlein(C, R, cell) {
  return Math.max((C * cell) / (2 * Math.PI), (R * cell) / Math.PI);
}

// Plie un point de la dalle : (x, z) + une élévation → position 3D dans `out`.
// Le centre de la carte reste à l'origine à tous les pourcentages (d'où le -Rt sur y),
// ce qui rend le pliage continu : à t → 0 on retombe exactement sur (x, elev, z).
// Remplit aussi `vHaut` (la normale) et retourne les cosinus/sinus, dont la base a besoin.
export function plier(x, z, elev, R1, t, out) {
  const Rt = R1 / t;
  const a = x / Rt, b = z / Rt;
  const ca = Math.cos(a), sa = Math.sin(a), cb = Math.cos(b), sb = Math.sin(b);
  vHaut.set(sa * cb, ca * cb, sb);
  out.copy(vHaut).multiplyScalar(Rt + elev);
  out.y -= Rt;
  return { ca, sa, cb, sb };
}

// Oriente un objet posé au point plié : X vers l'est, Y vers le ciel, Z vers le sud.
// On construit la base à la main — `setFromUnitVectors` alignerait bien Y sur la normale,
// mais laisserait l'objet tourner librement autour : sa largeur ne serait plus dans l'axe
// est-ouest, et le resserrement des colonnes s'appliquerait de travers.
export function orienterPlie(g, quaternion) {
  vEst.set(g.ca, -g.sa, 0);
  vSud.crossVectors(vEst, vHaut);
  mBase.makeBasis(vEst, vHaut, vSud);
  quaternion.setFromRotationMatrix(mBase);
}
