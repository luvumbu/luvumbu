import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';

// ============================================================
//  FORME DES CASES — l'enveloppe seulement. Une case garde sa place, sa taille et sa
//  couleur : « très lisse » ne fait qu'arrondir le bloc, il n'invente aucune donnée.
//
//  Chaque forme existe en TROIS finesses. C'est indispensable : la géométrie est unique
//  (InstancedMesh) mais ses sommets sont retraités pour CHAQUE case. Un galet à 6
//  segments (~300 sommets) × 50 000 cases = 15 M de sommets par image — écran figé sur
//  une machine modeste. On choisit donc la finesse d'après le nombre de cases.
// ============================================================

const geoBloc = new THREE.BoxGeometry(1, 1, 1);

const FORMES = {
  cube:    [geoBloc, geoBloc, geoBloc],
  arrondi: [2, 3, 4].map((s) => new RoundedBoxGeometry(1, 1, 1, s, 0.16)),
  lisse:   [2, 4, 6].map((s) => new RoundedBoxGeometry(1, 1, 1, s, 0.34)),
  boule:   [[8, 6], [14, 10], [22, 14]].map(([w, h]) => new THREE.SphereGeometry(0.5, w, h)),
};

// Un bloc rond est INSCRIT dans sa case : ses coins manquent, et les voisines ne se
// touchent plus — la carte se lit en pointillé. On les élargit juste assez pour qu'elles
// se rejoignent. La case ne bouge pas, sa hauteur non plus : c'est l'enveloppe qui déborde.
export const ELARGIR = { cube: 0.99, arrondi: 1.01, lisse: 1.07, boule: 1.14 };

// n cases → la géométrie la plus fine qu'on peut se permettre.
export function geometrieCases(forme, n) {
  const jeu = FORMES[forme] || FORMES.cube;
  return jeu[n > 30000 ? 0 : n > 12000 ? 1 : 2];
}
