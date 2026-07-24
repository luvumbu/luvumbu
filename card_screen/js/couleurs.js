// ============================================================
//  COULEURS — TSL et rangement d'un pixel dans une famille.
//  Aucun état, aucune dépendance : c'est la brique la plus basse du projet.
//  index fixe : 0 eau · 1 terre · 2 végétation · 3 route_p · 4 route_s · 5 rouge ·
//               6 frontière · 7 fond · 8 frontière grise (déduite au niveau case)
// ============================================================

export const FAM = [
  { cle: 'eau',        nom: 'Eau',                    sw: '#a8c8e0', h: 0.05, vis: true },
  { cle: 'terre',      nom: 'Terre / bâti (beige)',   sw: '#efe8d0', h: 0.50, vis: true },
  { cle: 'vegetation', nom: 'Végétation',             sw: '#b3d492', h: 0.30, vis: true },
  { cle: 'route_p',    nom: 'Routes principales',     sw: '#f2d98a', h: 0.15, vis: true },
  { cle: 'route_s',    nom: 'Routes secondaires',     sw: '#eabf86', h: 0.12, vis: true },
  { cle: 'rouge',      nom: 'Rouge (autoroute/front.)', sw: '#e07a6a', h: 1.00, vis: true },
  { cle: 'frontiere',  nom: 'Frontière (rosé)',       sw: '#e6c2c2', h: 0.90, vis: true },
  { cle: 'fond',       nom: 'Fond / gris neutre',     sw: '#e6e6e6', h: 0.12, vis: false },
  { cle: 'front_grise', nom: 'Frontière grise (traits)', sw: '#b9b9c0', h: 0.90, vis: true },
];

// Familles "fines" (traits colorés) : elles priment sur les surfaces même minoritaires.
// (La frontière GRISE, index 8, n'a pas de pixels propres : on la déduit au niveau de la case.)
export const FINES = [3, 4, 5, 6];

// RVB (0-255) → TSL. h en degrés, s et l en 0-1.
export function rgb2hsl(r, g, b) {
  r /= 255; g /= 255; b /= 255;
  const max = Math.max(r, g, b), min = Math.min(r, g, b), d = max - min;
  const l = (max + min) / 2;
  let h = 0, s = 0;
  if (d !== 0) {
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    if (max === r) h = ((g - b) / d) % 6;
    else if (max === g) h = (b - r) / d + 2;
    else h = (r - g) / d + 4;
    h *= 60; if (h < 0) h += 360;
  }
  return [h, s, l];
}

// Couleur d'un pixel → index de famille.
export function familleIndex(r, g, b) {
  const [h, s, l] = rgb2hsl(r, g, b);
  if (l > 0.96 && s < 0.12) return 7;                 // fond très clair
  if (s < 0.10) return 7;                             // gris neutre
  if (h >= 170 && h <= 260) return 0;                 // bleu → eau
  if (h >= 70 && h < 170) return 2;                   // vert → végétation
  if (h < 20 || h >= 345) return s > 0.35 ? 5 : 6;    // rouge saturé / rosé pâle
  if (h >= 20 && h < 45) return l > 0.85 ? 1 : 4;     // beige clair (terre) / tan (route sec.)
  if (h >= 45 && h < 70) return 3;                    // jaune → route principale
  return 1;                                           // défaut : terre
}
