// Génère les 19 sous-chapitres de `formes/`, un par géométrie de Three.js.
//
// Pourquoi un script plutôt que 19 fichiers écrits à la main : la plomberie est identique
// d'une page à l'autre (des curseurs, une reconstruction, un compteur de triangles) ;
// seuls changent les paramètres et les pièges, qui sont les données ci-dessous. Un script
// garantit que les 19 pages restent cohérentes, et corriger la plomberie une fois les
// corrige toutes. C'est le même parti pris que scripts/creer-robot.py pour le modèle 3D.
//
//   node scripts/creer-pages-formes.mjs
//
// Les fichiers produits sont de vraies pages autonomes, conformes aux règles du projet :
// le <script type="module"> de la démo est le premier du fichier, le HUD porte .hud,
// et le panneau « Éditer le code » y fonctionne comme partout ailleurs.

import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const RACINE = join(dirname(fileURLToPath(import.meta.url)), '..');

// Raccourcis pour décrire un paramètre.
const n = (nom, label, min, max, step, def, aide) => ({ nom, label, min, max, step, def, aide });
const bool = (nom, label, def, aide) => ({ nom, label, type: 'check', def, aide });

// Un paramètre d'ANGLE. Le curseur est en degrés, la page convertit en radians.
//
// Ce n'est pas qu'un confort de lecture : un <input type="range"> ne peut atteindre que
// des valeurs alignées sur son `step`. Avec min="0" max="6.28" step="0.05", le maximum
// atteignable est 6.25 — donc une sphère à qui l'on demanderait phiLength ne pourrait
// JAMAIS se refermer, il resterait une fente de 2°. En degrés (0 → 360, pas de 1), le
// tour complet est exact, et le lecteur raisonne dans l'unité qu'il a en tête.
const ang = (nom, label, maxDeg, defDeg) => ({ nom, label, type: 'angle', maxDeg, defDeg });

const FORMES = [
  // ─────────────────────────────── LES PRIMITIVES ───────────────────────────────
  {
    id: '01-box', classe: 'BoxGeometry', titre: 'La boîte', famille: 'Les primitives',
    intro: `La forme la plus utilisée de toute la 3D, et la moins chère : <b>12 triangles</b>.
            Un mur, une caisse, un immeuble, un sol épais — tout commence par elle.`,
    params: [
      n('width', 'width — largeur (X)', 0.2, 3, 0.1, 1.2),
      n('height', 'height — hauteur (Y)', 0.2, 3, 0.1, 1.2),
      n('depth', 'depth — profondeur (Z)', 0.2, 3, 0.1, 1.2),
      n('widthSegments', 'widthSegments', 1, 8, 1, 1),
      n('heightSegments', 'heightSegments', 1, 8, 1, 1),
      n('depthSegments', 'depthSegments', 1, 8, 1, 1),
    ],
    appel: 'new THREE.BoxGeometry(P.width, P.height, P.depth, P.widthSegments, P.heightSegments, P.depthSegments)',
    pieges: [
      `Les trois <code>Segments</code> ne changent <b>rien</b> à la forme : le cube reste un
       cube, il a juste plus de triangles. Montez-les et regardez le compteur — vous payez
       sans rien gagner. Ils ne servent que dans deux cas : déformer les sommets ensuite
       (une vague, un drapeau), ou éclairer une grande face avec un shader par sommet.`,
      `L'origine est au <b>centre</b>. Pour poser la boîte sur un sol en y = 0, il faut
       <code>position.y = height / 2</code>. C'est la cause n°1 des objets à moitié enfoncés.`,
      `Il n'existe pas de « CubeGeometry » : c'est <code>BoxGeometry</code> avec trois valeurs
       égales. L'ancien nom a disparu en r125.`,
    ],
  },
  {
    id: '02-sphere', classe: 'SphereGeometry', titre: 'La sphère', famille: 'Les primitives',
    intro: `Sept paramètres, dont quatre que presque personne n'utilise — et qui permettent
            pourtant de découper des hémisphères et des quartiers sans toucher un modeleur.`,
    camera: 3.2,
    params: [
      n('radius', 'radius — rayon', 0.2, 1.5, 0.05, 1),
      n('widthSegments', 'widthSegments — méridiens', 3, 64, 1, 32),
      n('heightSegments', 'heightSegments — parallèles', 2, 32, 1, 16),
      ang('phiStart', 'phiStart — début en longitude', 360, 0),
      ang('phiLength', 'phiLength — arc de longitude', 360, 360),
      ang('thetaStart', 'thetaStart — début en latitude', 180, 0),
      ang('thetaLength', 'thetaLength — arc de latitude', 180, 180),
    ],
    appel: 'new THREE.SphereGeometry(P.radius, P.widthSegments, P.heightSegments, P.phiStart, P.phiLength, P.thetaStart, P.thetaLength)',
    pieges: [
      `<b>phi</b> tourne autour de l'axe Y (la longitude, 0 → 2π) ; <b>theta</b> va du pôle
       nord au pôle sud (la latitude, 0 → π). D'où l'asymétrie des valeurs par défaut :
       un tour complet fait 2π, mais un demi-tour de pôle à pôle ne fait que π.`,
      `<code>thetaLength = π/2</code> donne un <b>dôme</b> ; <code>phiLength = π</code> donne
       une demi-sphère coupée verticalement. Ce sont des surfaces <b>ouvertes</b> : leur
       intérieur devient visible, donc <code>side: THREE.DoubleSide</code> (étape 1b).`,
      `Gardez <code>widthSegments ≈ 2 × heightSegments</code> : la sphère fait le tour en
       longitude mais seulement un demi-tour en latitude. À l'inverse, les triangles sont
       étirés. Et ils s'agglutinent toujours aux pôles — c'est le défaut de cette
       construction ; pour des triangles réguliers, prenez <code>IcosahedronGeometry(1, 3)</code>.`,
    ],
  },
  {
    id: '03-cylinder', classe: 'CylinderGeometry', titre: 'Le cylindre', famille: 'Les primitives',
    intro: `Le couteau suisse du catalogue : selon ses deux rayons, il devient un cylindre,
            un cône, un tronc de cône, une pyramide ou un tuyau.`,
    params: [
      n('radiusTop', 'radiusTop — rayon du haut', 0, 1.2, 0.05, 0.6),
      n('radiusBottom', 'radiusBottom — rayon du bas', 0, 1.2, 0.05, 0.6),
      n('height', 'height — hauteur', 0.2, 2.5, 0.1, 1.4),
      n('radialSegments', 'radialSegments — côtés', 3, 64, 1, 32),
      n('heightSegments', 'heightSegments', 1, 8, 1, 1),
      bool('openEnded', 'openEnded — sans couvercles', false),
      ang('thetaStart', 'thetaStart', 360, 0),
      ang('thetaLength', 'thetaLength — arc', 360, 360),
    ],
    appel: 'new THREE.CylinderGeometry(P.radiusTop, P.radiusBottom, P.height, P.radialSegments, P.heightSegments, P.openEnded, P.thetaStart, P.thetaLength)',
    pieges: [
      `Mettez <code>radiusTop = 0</code> : vous obtenez un <b>cône</b>. C'est littéralement
       ainsi que Three.js le fabrique — <code>ConeGeometry</code> hérite de cette classe et
       ne fait que forcer ce zéro.`,
      `Mettez <code>radialSegments = 3</code>, <code>4</code> ou <code>6</code> : vous obtenez
       un prisme triangulaire, une boîte posée sur la pointe, un hexagone. Beaucoup de formes
       « anguleuses » ne sont qu'un cylindre à peu de côtés.`,
      `<code>openEnded: true</code> retire les deux disques et laisse un <b>tuyau</b>. On voit
       alors l'intérieur, donc il faut <code>side: THREE.DoubleSide</code> — sinon le tuyau
       paraît troué.`,
      `Les côtés d'un cylindre sont lisses (les normales sont interpolées) mais les couvercles
       sont plats : la jonction se voit sur les gros rayons. C'est normal, pas un bug.`,
    ],
  },
  {
    id: '04-cone', classe: 'ConeGeometry', titre: 'Le cône', famille: 'Les primitives',
    intro: `Un cylindre déguisé. Sa seule raison d'être est le confort : ne pas avoir à
            écrire un <code>0</code> en premier argument.`,
    params: [
      n('radius', 'radius — rayon de la base', 0.1, 1.2, 0.05, 0.65),
      n('height', 'height — hauteur', 0.2, 2.5, 0.1, 1.4),
      n('radialSegments', 'radialSegments — côtés', 3, 64, 1, 32),
      n('heightSegments', 'heightSegments', 1, 8, 1, 1),
      bool('openEnded', 'openEnded — sans base', false),
      ang('thetaStart', 'thetaStart', 360, 0),
      ang('thetaLength', 'thetaLength — arc', 360, 360),
    ],
    appel: 'new THREE.ConeGeometry(P.radius, P.height, P.radialSegments, P.heightSegments, P.openEnded, P.thetaStart, P.thetaLength)',
    pieges: [
      `<code>class ConeGeometry extends CylinderGeometry</code> — c'est écrit tel quel dans
       le source de Three.js. Un cône <b>est</b> un cylindre dont le rayon du haut vaut 0.
       D'où l'absence de <code>radiusTop</code> dans sa signature.`,
      `<code>radialSegments = 4</code> donne une <b>pyramide</b> à base carrée. Il n'existe pas
       de PyramidGeometry : c'est ce cône-là.`,
      `La pointe est un point unique où convergent tous les triangles. L'éclairage y est
       toujours un peu faux, et les textures s'y pincent. C'est inhérent à la forme.`,
    ],
  },
  {
    id: '05-capsule', classe: 'CapsuleGeometry', titre: 'La capsule', famille: 'Les primitives',
    intro: `Un cylindre coiffé de deux demi-sphères. C'est <b>la</b> forme du corps d'un
            personnage dans presque tous les moteurs de jeu, et pour une bonne raison.`,
    params: [
      n('radius', 'radius — rayon', 0.1, 1, 0.05, 0.4),
      n('length', 'length — longueur du DROIT', 0, 2, 0.05, 0.8),
      n('capSegments', 'capSegments — finesse des calottes', 1, 16, 1, 8),
      n('radialSegments', 'radialSegments — côtés', 3, 48, 1, 16),
    ],
    appel: 'new THREE.CapsuleGeometry(P.radius, P.length, P.capSegments, P.radialSegments)',
    pieges: [
      `<code>length</code> n'est <b>pas</b> la hauteur totale : c'est seulement la partie
       droite, entre les deux calottes. La hauteur réelle vaut
       <code>length + 2 × radius</code>. Mettez <code>length = 0</code> : il reste une sphère.`,
      `<code>class CapsuleGeometry extends LatheGeometry</code> : une capsule est un profil
       arrondi qu'on fait tourner. Ce n'est pas un cylindre auquel on colle des sphères.`,
      `C'est la forme des collisions de personnage parce qu'elle n'a <b>aucune arête</b> :
       elle glisse le long des murs et monte les marches sans jamais s'accrocher.`,
      `Elle coûte cher en triangles pour sa taille — regardez le compteur. Deux calottes
       sphériques, ça se paie.`,
    ],
  },

  // ───────────────────────── LES TORES ET LES SURFACES PLATES ─────────────────────────
  {
    id: '06-torus', classe: 'TorusGeometry', titre: 'Le tore', famille: 'Tores et surfaces plates',
    intro: `Le beignet. Deux rayons à ne pas confondre, et deux comptes de segments qui ne
            font pas du tout la même chose.`,
    params: [
      n('radius', 'radius — rayon du trou au tube', 0.1, 1.2, 0.05, 0.7),
      n('tube', 'tube — épaisseur du boudin', 0.02, 0.6, 0.02, 0.28),
      n('radialSegments', 'radialSegments — autour du tube', 3, 32, 1, 12),
      n('tubularSegments', 'tubularSegments — le long', 3, 128, 1, 48),
      ang('arc', 'arc — portion dessinée', 360, 360),
    ],
    appel: 'new THREE.TorusGeometry(P.radius, P.tube, P.radialSegments, P.tubularSegments, P.arc)',
    pieges: [
      `<code>radius</code> va du centre au <b>milieu du tube</b>, pas au bord extérieur. Le
       rayon extérieur vaut <code>radius + tube</code>, et le trou se ferme quand
       <code>tube ≥ radius</code> — poussez le curseur, le beignet devient une boule ridée.`,
      `Les deux segments sont souvent inversés par erreur : <code>radialSegments</code> fait
       le tour du <b>boudin</b> (la coupe), <code>tubularSegments</code> suit le <b>cercle</b>.
       Le second doit être bien plus grand — d'où les valeurs par défaut 12 et 48.`,
      `<code>arc</code> ouvre le tore comme un tuyau coudé. Les deux bouts restent béants :
       <code>DoubleSide</code> obligatoire.`,
    ],
  },
  {
    id: '07-torusknot', classe: 'TorusKnotGeometry', titre: 'Le nœud torique', famille: 'Tores et surfaces plates',
    intro: `La forme mascotte de Three.js, celle de tous les exemples. Derrière la démo,
            deux entiers qui viennent de la théorie des nœuds.`,
    params: [
      n('radius', 'radius', 0.2, 1.2, 0.05, 0.7),
      n('tube', 'tube — épaisseur', 0.02, 0.4, 0.02, 0.22),
      n('tubularSegments', 'tubularSegments', 8, 256, 4, 96),
      n('radialSegments', 'radialSegments', 3, 24, 1, 12),
      n('p', 'p — tours autour de l’axe', 1, 8, 1, 2),
      n('q', 'q — tours dans le trou', 1, 8, 1, 3),
    ],
    appel: 'new THREE.TorusKnotGeometry(P.radius, P.tube, P.tubularSegments, P.radialSegments, P.p, P.q)',
    pieges: [
      `<b>p</b> et <b>q</b> doivent être <b>premiers entre eux</b> pour donner un vrai nœud.
       Sinon la courbe se referme trop tôt et vous obtenez un enlacement, pas un nœud.
       Essayez (2, 4) : ce n'est plus un nœud. (2, 3) est le trèfle, le plus simple qui existe.`,
      `Si <code>p</code> ou <code>q</code> vaut <b>1</b>, il n'y a aucun nœud : c'est un
       simple tore déformé. Le curseur le montre en un geste.`,
      `Baissez <code>tubularSegments</code> : la courbe devient un polygone et le tube se
       casse en morceaux. Cette forme a besoin de beaucoup de segments le long — c'est la
       plus chère du catalogue à qualité égale.`,
    ],
  },
  {
    id: '08-plane', classe: 'PlaneGeometry', titre: 'Le plan', famille: 'Tores et surfaces plates',
    intro: `Une simple feuille. C'est le sol de 90 % des scènes, l'écran de toute vidéo,
            et le support de tout terrain déformé.`,
    params: [
      n('width', 'width — largeur', 0.2, 3, 0.1, 1.6),
      n('height', 'height — hauteur', 0.2, 3, 0.1, 1.6),
      n('widthSegments', 'widthSegments', 1, 32, 1, 1),
      n('heightSegments', 'heightSegments', 1, 32, 1, 1),
    ],
    appel: 'new THREE.PlaneGeometry(P.width, P.height, P.widthSegments, P.heightSegments)',
    pieges: [
      `Un plan naît <b>debout</b>, dans le plan X/Y, face à vous. Ce n'est pas un sol.
       Pour le coucher : <code>rotation.x = -Math.PI / 2</code>. Avec
       <code>+Math.PI / 2</code> il est couché aussi, mais vous en voyez le <b>dos</b>, et
       il paraît noir.`,
      `Une seule face est dessinée. Vu de derrière, un plan <b>disparaît</b>. C'est
       <code>side: THREE.DoubleSide</code> — ou une rotation dans le bon sens.`,
      `Ici les segments servent vraiment : c'est la grille de sommets qu'on déforme pour
       faire un terrain, une mer ou un drapeau. Un plan à 1 segment n'a que 4 sommets, il
       n'y a rien à déplacer.`,
    ],
  },
  {
    id: '09-circle', classe: 'CircleGeometry', titre: 'Le disque', famille: 'Tores et surfaces plates',
    intro: `Un éventail de triangles partant du centre. Plat, donc invisible de profil.`,
    params: [
      n('radius', 'radius — rayon', 0.1, 1.5, 0.05, 0.9),
      n('segments', 'segments — côtés', 3, 64, 1, 32),
      ang('thetaStart', 'thetaStart — angle de départ', 360, 0),
      ang('thetaLength', 'thetaLength — angle balayé', 360, 360),
    ],
    appel: 'new THREE.CircleGeometry(P.radius, P.segments, P.thetaStart, P.thetaLength)',
    pieges: [
      `<code>thetaLength</code> découpe une <b>part de camembert</b>. C'est la façon la plus
       simple de faire un diagramme circulaire en 3D, sans aucune bibliothèque.`,
      `<code>segments = 3</code> donne un triangle, <code>5</code> un pentagone : c'est aussi
       le générateur de polygones réguliers du catalogue.`,
      `Un disque n'a <b>pas d'épaisseur</b> : de profil, il disparaît complètement. Pour une
       rondelle épaisse, prenez un <code>CylinderGeometry</code> très plat.`,
    ],
  },
  {
    id: '10-ring', classe: 'RingGeometry', titre: 'L’anneau', famille: 'Tores et surfaces plates',
    intro: `Un disque avec un trou — et toujours aucune épaisseur. À ne pas confondre avec
            le tore, qui est un vrai volume.`,
    params: [
      n('innerRadius', 'innerRadius — rayon du trou', 0, 1.2, 0.05, 0.4),
      n('outerRadius', 'outerRadius — rayon extérieur', 0.1, 1.5, 0.05, 0.9),
      n('thetaSegments', 'thetaSegments — côtés', 3, 64, 1, 32),
      n('phiSegments', 'phiSegments — anneaux concentriques', 1, 12, 1, 1),
      ang('thetaStart', 'thetaStart', 360, 0),
      ang('thetaLength', 'thetaLength — arc', 360, 360),
    ],
    appel: 'new THREE.RingGeometry(P.innerRadius, P.outerRadius, P.thetaSegments, P.phiSegments, P.thetaStart, P.thetaLength)',
    pieges: [
      `<code>phiSegments</code> ne change <b>rien</b> à la silhouette : il ajoute des anneaux
       concentriques de sommets à l'intérieur de la surface. Laissez-le à 1, sauf pour
       déformer. Montez-le en wireframe pour comprendre.`,
      `Si <code>innerRadius = 0</code>, c'est un disque — mais moins efficace qu'un
       <code>CircleGeometry</code>, qui fait un vrai éventail.`,
      `Un anneau est <b>plat</b>. Une bague qu'on peut regarder de côté, c'est un
       <code>TorusGeometry</code>.`,
    ],
  },

  // ──────────────────────────────── LES POLYÈDRES ────────────────────────────────
  ...[
    ['11-tetrahedron', 'TetrahedronGeometry', 'Le tétraèdre', '4 faces triangulaires — le plus simple des volumes fermés. En dessous, on ne ferme plus rien.'],
    ['12-octahedron', 'OctahedronGeometry', 'L’octaèdre', '8 faces triangulaires, deux pyramides collées base à base.'],
    ['13-icosahedron', 'IcosahedronGeometry', 'L’icosaèdre', '20 faces triangulaires. C’est le meilleur point de départ pour une sphère régulière.'],
    ['14-dodecahedron', 'DodecahedronGeometry', 'Le dodécaèdre', '12 faces pentagonales — que Three.js découpe en triangles, comme tout le reste.'],
  ].map(([id, classe, titre, quoi]) => ({
    id, classe, titre, famille: 'Les polyèdres',
    intro: `${quoi} L'un des cinq <b>solides de Platon</b>, et l'un des quatre que Three.js
            fournit. Deux paramètres seulement — mais le second est un piège.`,
    params: [
      n('radius', 'radius — rayon de la sphère circonscrite', 0.2, 1.5, 0.05, 1),
      n('detail', 'detail — subdivisions', 0, 4, 1, 0),
    ],
    appel: `new THREE.${classe}(P.radius, P.detail)`,
    pieges: [
      `<b>detail n'est pas un réglage de finesse.</b> Poussez le curseur : votre solide
       devient une <b>sphère</b>. Il subdivise chaque face, puis repousse les nouveaux
       sommets sur la sphère circonscrite. Il ne lisse pas la forme, il la remplace.`,
      `C'est pour ça que <code>new THREE.IcosahedronGeometry(1, 3)</code> est la bonne façon
       de faire une sphère : ses triangles sont <b>réguliers partout</b>, sans le pincement
       aux pôles de <code>SphereGeometry</code>. C'est la « géosphère » des modeleurs.`,
      `<code>radius</code> est le rayon de la sphère <b>circonscrite</b> : la distance du
       centre aux <b>sommets</b>, pas aux faces. Le solide est donc toujours plus petit que
       la sphère de même rayon.`,
      `Ces quatre classes héritent toutes de <code>PolyhedronGeometry</code> et n'ajoutent
       rien : juste une table de sommets. Il manque le cinquième solide de Platon, le cube —
       parce que <code>BoxGeometry</code> existe déjà.`,
    ],
  })),
  {
    id: '15-polyhedron', classe: 'PolyhedronGeometry', titre: 'Le polyèdre libre', famille: 'Les polyèdres',
    intro: `La classe mère des quatre précédentes, et la seule où <b>vous</b> donnez les
            sommets et les triangles. Ici : une pyramide à base carrée, écrite à la main.`,
    prelude: `// Les sommets, en triplets (x, y, z) mis bout à bout dans un seul tableau plat.
    const sommets = [
       0,  1,  0,   // 0 — la pointe
      -1, -1,  1,   // 1 ┐
       1, -1,  1,   // 2 │ la base carrée,
       1, -1, -1,   // 3 │ dans le sens antihoraire vue de dessous
      -1, -1, -1,   // 4 ┘
    ];

    // Les faces, en triplets d'INDICES dans le tableau ci-dessus.
    // L'ordre des trois indices décide de quel côté la face regarde (voir le piège n°2).
    const faces = [
      0, 1, 2,   0, 2, 3,   0, 3, 4,   0, 4, 1,   // les quatre flancs
      1, 4, 3,   1, 3, 2,                          // le dessous, en deux triangles
    ];`,
    params: [
      n('radius', 'radius — mise à l’échelle', 0.2, 1.5, 0.05, 0.9),
      n('detail', 'detail — subdivisions', 0, 4, 1, 0),
    ],
    appel: 'new THREE.PolyhedronGeometry(sommets, faces, P.radius, P.detail)',
    pieges: [
      `<code>radius</code> ne met pas à l'échelle : il <b>projette tous les sommets sur une
       sphère</b> de ce rayon. Vos proportions sont donc écrasées — la pyramide devient
       régulière. Cette classe suppose un solide inscrit dans une sphère ; pour une forme
       libre, c'est <code>BufferGeometry</code> qu'il faut.`,
      `L'<b>ordre des trois indices</b> d'une face décide de son orientation : dans le sens
       antihoraire, elle regarde vers vous. À l'envers, elle est invisible en
       <code>FrontSide</code>. C'est la règle de la main droite, et c'est l'erreur qu'on fait
       tous en écrivant des faces à la main.`,
      `Une face n'est <b>jamais</b> un carré ou un pentagone : uniquement des triangles. Le
       dodécaèdre à faces pentagonales est en réalité découpé en triangles, comme tout le
       reste. La carte graphique ne connaît rien d'autre.`,
    ],
  },

  // ─────────────────────────────── LES GÉNÉRATIVES ───────────────────────────────
  {
    id: '16-lathe', classe: 'LatheGeometry', titre: 'Le tour de potier', famille: 'Les génératives',
    intro: `Vous donnez une <b>demi-silhouette</b>, elle la fait tourner autour de l'axe Y.
            C'est le geste du tour de potier — et c'est ainsi qu'on fait un vase, un verre,
            un pied de lampe, une quille.`,
    prelude: `// Le profil : une demi-silhouette dans le plan X/Y. x = distance à l'axe,
    // y = hauteur. C'est ce tableau que la rotation va balayer.
    // Il est reconstruit à chaque mouvement de curseur, d'où la fonction.
    function profil() {
      const points = [];
      for (let i = 0; i < 14; i++) {
        const t = i / 13;
        points.push(new THREE.Vector2(
          Math.sin(t * Math.PI * P.ondulations) * P.galbe + 0.12,   // x : jamais négatif !
          t * 1.4 - 0.7,                                             // y : du bas vers le haut
        ));
      }
      return points;
    }`,
    params: [
      n('segments', 'segments — finesse de la rotation', 3, 64, 1, 24),
      ang('phiLength', 'phiLength — portion tournée', 360, 360),
      n('ondulations', 'le profil : nombre de ventres', 0.5, 4, 0.5, 1),
      n('galbe', 'le profil : amplitude', 0.05, 0.6, 0.05, 0.35),
    ],
    appel: 'new THREE.LatheGeometry(profil(), P.segments, 0, P.phiLength)',
    pieges: [
      `Un <code>x</code> <b>négatif</b> dans le profil retourne la surface : elle passe de
       l'autre côté de l'axe et son intérieur devient extérieur. Gardez tous les x ≥ 0.
       D'où le <code>+ 0.12</code> dans le code.`,
      `Le profil est une liste de <code>Vector2</code>, pas de <code>Vector3</code> : il n'y
       a que deux dimensions à décrire, la troisième est créée par la rotation.`,
      `Le résultat est <b>creux et ouvert</b> en haut et en bas — c'est une coquille, pas un
       volume. <code>DoubleSide</code> est quasiment obligatoire.`,
      `<code>phiLength &lt; 2π</code> laisse le vase ouvert comme une gouttière : idéal pour
       montrer l'intérieur d'une pièce tournée.`,
    ],
  },
  {
    id: '17-tube', classe: 'TubeGeometry', titre: 'Le tuyau le long d’une courbe', famille: 'Les génératives',
    intro: `Vous donnez une <b>courbe dans l'espace</b>, elle l'habille d'un tuyau. Un câble,
            une route, une trajectoire, un toboggan.`,
    prelude: `// Une courbe 3D. CatmullRomCurve3 passe PAR tous les points donnés
    // (contrairement à une Bézier, dont les points de contrôle sont hors de la courbe).
    const courbe = new THREE.CatmullRomCurve3([
      new THREE.Vector3(-0.9, -0.7,  0.2),
      new THREE.Vector3( 0.7, -0.2,  0.6),
      new THREE.Vector3(-0.5,  0.4, -0.5),
      new THREE.Vector3( 0.8,  0.9,  0.2),
    ]);`,
    params: [
      n('tubularSegments', 'tubularSegments — le long de la courbe', 4, 200, 2, 64),
      n('radius', 'radius — épaisseur', 0.02, 0.4, 0.01, 0.12),
      n('radialSegments', 'radialSegments — tour du tuyau', 3, 24, 1, 10),
      bool('closed', 'closed — refermer la boucle', false),
    ],
    appel: 'new THREE.TubeGeometry(courbe, P.tubularSegments, P.radius, P.radialSegments, P.closed)',
    pieges: [
      `<code>CatmullRomCurve3</code> passe <b>par</b> les points que vous donnez. Une
       <code>CubicBezierCurve3</code>, non : ses points du milieu sont des aimants, la courbe
       ne les touche jamais. C'est la confusion classique.`,
      `Baissez <code>tubularSegments</code> : le tuyau se casse en segments droits. Une courbe
       très sinueuse en demande beaucoup — c'est ici que le compteur de triangles s'envole.`,
      `Les deux bouts sont <b>ouverts</b> : un tube n'a pas de bouchons. Il faut les ajouter
       soi-même (deux <code>CircleGeometry</code>) ou vivre avec.`,
      `<code>closed: true</code> ne ferme que si le départ et l'arrivée coïncident à peu
       près. Sinon la courbe fait un raccourci brutal pour se rejoindre.`,
    ],
  },
  {
    id: '18-shape', classe: 'ShapeGeometry', titre: 'Le contour dessiné', famille: 'Les génératives',
    intro: `Vous <b>dessinez</b> un contour 2D comme au crayon — <code>moveTo</code>,
            <code>lineTo</code>, des courbes — et elle le remplit. Toute forme plate
            imaginable, sans modeleur.`,
    prelude: `// Un THREE.Shape se dessine comme avec un canvas 2D : on lève le crayon (moveTo),
    // on trace (lineTo), et il existe aussi bezierCurveTo, quadraticCurveTo, absarc…
    function etoile() {
      const s = new THREE.Shape();
      const branches = Math.round(P.branches);
      for (let i = 0; i < branches * 2; i++) {
        const r = i % 2 ? P.rInterieur : P.rExterieur;
        const a = (i / (branches * 2)) * Math.PI * 2 + Math.PI / 2;
        const [x, y] = [Math.cos(a) * r, Math.sin(a) * r];
        i === 0 ? s.moveTo(x, y) : s.lineTo(x, y);
      }
      s.closePath();   // un contour DOIT être fermé, sinon le remplissage est imprévisible
      return s;
    }`,
    params: [
      n('branches', 'branches de l’étoile', 3, 12, 1, 5),
      n('rExterieur', 'rayon extérieur', 0.3, 1.2, 0.05, 0.9),
      n('rInterieur', 'rayon intérieur', 0.05, 1, 0.05, 0.38),
      n('curveSegments', 'curveSegments — finesse des courbes', 1, 24, 1, 12),
    ],
    appel: 'new THREE.ShapeGeometry(etoile(), P.curveSegments)',
    pieges: [
      `<code>closePath()</code> n'est pas facultatif. Un contour ouvert donne un remplissage
       imprévisible : Three.js relie le dernier point au premier comme il peut.`,
      `<code>curveSegments</code> ne sert <b>qu'aux courbes</b>. Notre étoile n'est faite que
       de <code>lineTo</code> : bougez le curseur, le compteur de triangles ne change pas
       d'un poil. Ce serait différent avec un <code>bezierCurveTo</code>.`,
      `Un <code>Shape</code> accepte des <b>trous</b> : <code>shape.holes.push(autreChemin)</code>.
       C'est ainsi qu'on fait un « A » ou une rondelle avec un contour libre.`,
      `Le résultat est <b>plat</b>, dans le plan X/Y, et n'a qu'une face utile. Pour lui
       donner une épaisseur, c'est <code>ExtrudeGeometry</code> — la page suivante, avec le
       même contour.`,
    ],
  },
  {
    id: '19-extrude', classe: 'ExtrudeGeometry', titre: 'Le contour extrudé', famille: 'Les génératives',
    intro: `Le <b>même contour qu'à la page précédente</b>, mais poussé en profondeur, avec un
            biseau sur les bords. C'est la géométrie la plus riche du catalogue — et celle
            qui transforme un dessin en objet.`,
    prelude: `// Exactement le même contour qu'à ShapeGeometry : c'est tout l'intérêt.
    // Un Shape n'est pas une géométrie, c'est un DESSIN — deux classes savent le lire.
    function etoile() {
      const s = new THREE.Shape();
      const branches = Math.round(P.branches);
      for (let i = 0; i < branches * 2; i++) {
        const r = i % 2 ? P.rInterieur : P.rExterieur;
        const a = (i / (branches * 2)) * Math.PI * 2 + Math.PI / 2;
        const [x, y] = [Math.cos(a) * r, Math.sin(a) * r];
        i === 0 ? s.moveTo(x, y) : s.lineTo(x, y);
      }
      s.closePath();
      return s;
    }`,
    params: [
      n('branches', 'branches de l’étoile', 3, 12, 1, 5),
      n('rExterieur', 'rayon extérieur', 0.3, 1.2, 0.05, 0.85),
      n('rInterieur', 'rayon intérieur', 0.05, 1, 0.05, 0.36),
      n('depth', 'depth — épaisseur', 0.05, 1.5, 0.05, 0.4),
      bool('bevelEnabled', 'bevelEnabled — biseauter les bords', true),
      n('bevelThickness', 'bevelThickness — profondeur du biseau', 0.01, 0.3, 0.01, 0.06),
      n('bevelSize', 'bevelSize — largeur du biseau', 0.01, 0.3, 0.01, 0.06),
      n('bevelSegments', 'bevelSegments — arrondi du biseau', 1, 8, 1, 2),
      n('steps', 'steps — tranches en profondeur', 1, 12, 1, 1),
    ],
    appel: `new THREE.ExtrudeGeometry(etoile(), {
      depth: P.depth,
      bevelEnabled: P.bevelEnabled,
      bevelThickness: P.bevelThickness,
      bevelSize: P.bevelSize,
      bevelSegments: P.bevelSegments,
      steps: P.steps,
      curveSegments: 12,
    }).center()`,
    pieges: [
      `L'extrusion part de <code>z = 0</code> et va vers <code>z = depth</code> : le résultat
       n'est <b>pas centré</b> sur l'origine. D'où le <code>.center()</code> à la fin, qui le
       recadre sur sa boîte englobante. Sans lui, l'objet tourne de travers.`,
      `<code>bevelSize</code> <b>déborde</b> du contour : l'objet est plus large que ce que
       vous avez dessiné. Avec <code>bevelSize</code> trop grand par rapport au rayon
       intérieur de l'étoile, les biseaux s'auto-intersectent et la forme se replie sur
       elle-même. Poussez le curseur pour le voir.`,
      `<code>steps</code> ne change <b>rien</b> à la silhouette : il découpe l'épaisseur en
       tranches. Utile seulement pour déformer ensuite, ou pour extruder le long d'une
       courbe (<code>extrudePath</code>).`,
      `Le biseau est ce qui distingue un objet crédible d'un objet plat : dans le monde réel,
       <b>aucune arête n'est parfaitement vive</b>, et c'est le liseré de lumière sur les
       bords qui donne la matière. Décochez la case pour voir tout de suite la différence.`,
    ],
  },
];

// ─────────────────────────────── LE GABARIT ───────────────────────────────

/** `new THREE.X(P.a, P.b)` → `new THREE.X(${fmt(P.a)}, ${fmt(P.b)})`, pour l'affichage en direct. */
const enTemplate = (appel) => appel.replace(/\bP\.(\w+)/g, '${fmt(P.$1)}');

function widgets(params) {
  return params.map((p) => {
    if (p.type === 'check') {
      return `      <label><input type="checkbox" data-p="${p.nom}"${p.def ? ' checked' : ''}> ${p.label}</label>`;
    }
    if (p.type === 'angle') {
      return `      <label>${p.label} <span class="deg" data-deg="${p.nom}">${p.defDeg}°</span>
        <input type="range" data-p="${p.nom}" data-angle min="0" max="${p.maxDeg}" step="1" value="${p.defDeg}"></label>`;
    }
    return `      <label>${p.label}
        <input type="range" data-p="${p.nom}" min="${p.min}" max="${p.max}" step="${p.step}" value="${p.def}"></label>`;
  }).join('\n');
}

function page(forme, precedent, suivant) {
  const defauts = forme.params
    .map((p) => p.type === 'angle'
      ? `      ${p.nom}: THREE.MathUtils.degToRad(${p.defDeg}),   // ${p.defDeg}° en radians`
      : `      ${p.nom}: ${p.def},`)
    .join('\n');

  return `<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>${forme.classe} — ${forme.titre}</title>
  <link rel="stylesheet" href="../css/style.css">
  <script type="importmap">
  { "imports": { "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js" } }
  </script>
</head>
<body>
  <div class="hud hud-forme">
    <h2>${forme.classe}</h2>
    <p class="famille">${forme.famille} · ${forme.titre}</p>
    <p>${forme.intro}</p>

${widgets(forme.params)}
    <label><input type="checkbox" id="fil"> wireframe — voir les triangles</label>

    <p class="appel"><code id="appel"></code></p>
    <p><code id="tris"></code></p>

    <details open>
      <summary>Ce qu'il faut savoir (${forme.pieges.length})</summary>
      <ul>
${forme.pieges.map((p) => `        <li>${p.replace(/\s+/g, ' ').trim()}</li>`).join('\n')}
      </ul>
    </details>

    <a href="${precedent}">← Précédent</a> ·
    <a href="../debutant/05b-toutes-les-formes.html">Le catalogue</a> ·
    <a href="${suivant}">Suivant →</a>
  </div>

  <script type="module">
    import * as THREE from 'three';

    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x0e1116);

    const camera = new THREE.PerspectiveCamera(50, window.innerWidth / window.innerHeight);
    camera.position.set(1.6, 1.2, ${forme.camera ?? 3.4});
    camera.lookAt(0, 0, 0);

    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    document.body.appendChild(renderer.domElement);

    scene.add(new THREE.AmbientLight(0xffffff, 0.5));
    const soleil = new THREE.DirectionalLight(0xffffff, 2.5);
    soleil.position.set(3, 5, 6);
    scene.add(soleil);

    // DoubleSide : beaucoup de ces formes sont des surfaces ouvertes, elles
    // disparaîtraient de dos (voir étape 1b).
    const materiau = new THREE.MeshStandardMaterial({
      color: 0x58a6ff, roughness: 0.45, metalness: 0.1, side: THREE.DoubleSide,
    });

    // Les paramètres du constructeur de ${forme.classe}, dans l'ordre de sa signature.
    const P = {
${defauts}
    };
${forme.prelude ? '\n    ' + forme.prelude.trim() + '\n' : ''}
    let maille = null;

    // Pour l'affichage de l'appel : un entier reste entier, un flottant est arrondi.
    // Sans ça, un angle s'afficherait « 6.283185307179586 ».
    const fmt = (v) => (typeof v === 'number' && !Number.isInteger(v) ? v.toFixed(2) : v);

    function construire() {
      // On libère l'ancienne géométrie AVANT de la remplacer : JavaScript ramasse ses
      // miettes, WebGL non. Sans dispose(), chaque mouvement de curseur fuirait du GPU.
      if (maille) {
        maille.geometry.dispose();
        scene.remove(maille);
      }

      const geometrie = ${forme.appel};

      maille = new THREE.Mesh(geometrie, materiau);
      scene.add(maille);

      // Le compte exact : avec un index, les sommets sont réutilisés entre triangles.
      const nb = (geometrie.index ? geometrie.index.count : geometrie.attributes.position.count) / 3;
      document.getElementById('tris').textContent = nb.toLocaleString('fr') + ' triangles';
      document.getElementById('appel').textContent = \`${enTemplate(forme.appel)}\`;
    }
    construire();

    function animer() {
      requestAnimationFrame(animer);
      maille.rotation.y += 0.006;
      renderer.render(scene, camera);
    }
    animer();

    // Un seul gestionnaire pour tous les curseurs : chacun porte le nom de son paramètre
    // dans data-p, donc il suffit de le recopier dans P et de reconstruire.
    for (const champ of document.querySelectorAll('[data-p]')) {
      champ.addEventListener('input', () => {
        if (champ.type === 'checkbox') {
          P[champ.dataset.p] = champ.checked;
        } else if (champ.dataset.angle !== undefined) {
          // Le curseur est en DEGRÉS — aucune fonction de Three.js n'en accepte.
          const deg = Number(champ.value);
          P[champ.dataset.p] = THREE.MathUtils.degToRad(deg);
          document.querySelector(\`[data-deg="\${champ.dataset.p}"]\`).textContent = deg + '°';
        } else {
          P[champ.dataset.p] = Math.round(Number(champ.value) * 100) / 100; // sinon 0.30000000000000004
        }
        construire();
      });
    }

    document.getElementById('fil').addEventListener('change', (e) => {
      materiau.wireframe = e.target.checked;
    });
  </script>

  <script type="module" src="../js/code-panel.js"></script>
</body>
</html>
`;
}

// ─────────────────────────────── ÉCRITURE ───────────────────────────────

mkdirSync(join(RACINE, 'formes'), { recursive: true });

FORMES.forEach((forme, i) => {
  const precedent = i === 0
    ? '../debutant/05b-toutes-les-formes.html'
    : `${FORMES[i - 1].id}.html`;
  const suivant = i === FORMES.length - 1
    ? '../debutant/06-souris.html'
    : `${FORMES[i + 1].id}.html`;

  writeFileSync(join(RACINE, 'formes', `${forme.id}.html`), page(forme, precedent, suivant));
});

console.log(`${FORMES.length} pages écrites dans formes/`);

// Le sommaire des 19, à recopier dans index.html et dans le catalogue.
const liens = FORMES.map((f) =>
  `      <a href="formes/${f.id}.html"><b>${f.classe}</b><span>${f.titre}</span></a>`
).join('\n');
writeFileSync(join(RACINE, 'scripts', 'formes-liens.html'), liens + '\n');
console.log('sommaire des liens écrit dans scripts/formes-liens.html');
