// Génère `astres/` : une simulation SEULE par astre, en complément des pages groupées.
//
// Les pages groupées (debutant/03.1, 03.2, 06.1) servent à COMPARER : trois astres côte
// à côte, on voit d'un coup ce que change la gravité. Ces pages-ci servent à l'inverse —
// se poser sur un seul astre, sans rien pour distraire, avec ses chiffres et ses faits.
// Les deux sont utiles, et aucune ne remplace l'autre.
//
//   node scripts/creer-pages-astres.mjs
//
// 3 astres × 3 simulations = 9 pages. La plomberie est identique, seuls changent `g`,
// les faits, et la loi affichée — donc un script, comme pour scripts/creer-pages-formes.mjs.

import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const RACINE = join(dirname(fileURLToPath(import.meta.url)), '..');

const ASTRES = [
  {
    id: 'lune', nom: 'la Lune', titre: 'Lune', g: 1.62, couleur: 0x8b949e,
    resume: `Six fois moins que la Terre. C'est peu, et pourtant ce n'est pas rien :
             on n'y flotte pas, on y tombe — lentement.`,
    faits: [
      `<b>Une plume et un marteau y tombent ensemble</b>, et ce n'est pas une expérience de
       pensée : David Scott l'a fait devant la caméra, sur place, en 1971 (Apollo 15). Sur
       Terre, seul l'air les sépare. Cette page simule le vide — comme la Lune.`,
      `<b>Un homme y saute six fois plus haut</b>, mais tombe six fois moins vite. Les
       astronautes d'Apollo, avec 80 kg de scaphandre, se déplaçaient en bondissant : c'était
       plus efficace que marcher, parce que chaque bond durait une éternité.`,
      `<b>Sa gravité n'est pas uniforme.</b> Des concentrations de masse sous les mers de
       basalte — les « mascons » — perturbent les orbites lunaires au point qu'aucune orbite
       basse n'y est stable très longtemps. Le 1.62 de cette page est une moyenne.`,
    ],
  },
  {
    id: 'terre', nom: 'la Terre', titre: 'Terre', g: 9.81, couleur: 0x58a6ff,
    resume: `La référence, celle que votre intuition connaît par cœur. C'est justement
             pour ça qu'elle sert d'étalon aux deux autres.`,
    faits: [
      `<b>9,81 est une moyenne, pas une constante.</b> On mesure 9,78 à l'équateur et 9,83
       aux pôles : la rotation vous éjecte un peu, et la Terre est aplatie, donc vous êtes
       plus loin du centre à l'équateur. Vous pesez environ 0,5 % de moins à Singapour qu'à
       Oslo.`,
      `<b>La chute ne dépend pas de votre masse</b> — c'est contre-intuitif et pourtant
       vérifiable : la masse apparaît des deux côtés de l'équation et se simplifie. Si une
       bille de plomb tombe plus vite qu'une feuille, c'est l'<b>air</b>, jamais la gravité.
       Cette page simule le vide.`,
      `<b>Une chute réelle plafonne.</b> Dans l'air, la résistance croît avec le carré de la
       vitesse jusqu'à équilibrer le poids : un humain se stabilise vers 200 km/h. Cette
       simulation, elle, accélère indéfiniment — c'est ce qui arriverait dans le vide.`,
    ],
  },
  {
    id: 'jupiter', nom: 'Jupiter', titre: 'Jupiter', g: 24.79, couleur: 0xf78166,
    resume: `Deux fois et demie la Terre. La plus forte gravité de surface du système
             solaire, si l'on excepte le Soleil.`,
    faits: [
      `<b>Il n'y a pas de sol sur Jupiter.</b> Le « 24,79 » est mesuré au sommet des nuages,
       là où la pression vaut celle de notre atmosphère. En dessous, on s'enfonce dans un gaz
       de plus en plus dense, sans jamais rencontrer de surface. Le sol de cette page est une
       commodité, pas une réalité.`,
      `<b>Un humain de 80 kg y pèserait 200 kg</b> — et ne tiendrait pas debout. Ce n'est pas
       une question de force : le squelette humain flanche bien avant.`,
      `<b>Jupiter est plus aplati que rond.</b> Il boucle un tour en 9 h 56 malgré sa taille,
       et cette rotation le renfle à l'équateur de 7 %. Sa gravité y est donc sensiblement
       plus faible qu'aux pôles.`,
    ],
  },
];

// ─────────────────────────────── LES TROIS SIMULATIONS ───────────────────────────────

const SIMS = {
  chute: {
    titre: 'La chute',
    groupee: '../debutant/03.1-chute-et-rebond.html',
    intro: (a) => `Une bille lâchée sans vitesse, sur ${a.nom}. Rien d'autre à l'écran :
                   c'est le but. La page groupée compare les trois astres ;
                   celle-ci vous laisse regarder ${a.nom} seule.`,
    loi: 't = √(2h/g)',
    reglages: `
    <label>hauteur de lâcher — m <span class="deg" id="lh">5.0</span>
      <input type="range" id="hauteur" min="1" max="9" step="0.1" value="5"></label>
    <label>élasticité — énergie gardée au rebond <span class="deg" id="le">0.70</span>
      <input type="range" id="elasticite" min="0" max="1" step="0.01" value="0.7"></label>`,
    camera: 'camera.position.set(3.2, 3, 9); camera.lookAt(3.2, 2.4, 0);',
    corps: `
    const RAYON = 0.35;
    const bille = new THREE.Mesh(
      new THREE.SphereGeometry(RAYON, 32, 16),
      new THREE.MeshStandardMaterial({ color: COULEUR, roughness: 0.4, emissive: COULEUR, emissiveIntensity: 0.25 })
    );
    scene.add(bille);

    const P = { hauteur: 5, elasticite: 0.7 };
    let vitesse = 0, rebonds = 0, endormie = false, tContact = null, chrono = 0;

    function lancer() {
      bille.position.set(3.2, P.hauteur, 0);
      vitesse = 0; rebonds = 0; endormie = false; tContact = null; chrono = 0;
    }
    lancer();

    function pas(dt) {
      chrono += dt;
      if (endormie) return;

      // 1) INTÉGRER — la gravité change la vitesse, la vitesse change la position.
      vitesse -= G * dt;
      bille.position.y += vitesse * dt;

      // 2) CORRIGER — la position D'ABORD, la vitesse ensuite. Jamais l'inverse.
      if (bille.position.y - RAYON < 0) {
        bille.position.y = RAYON;
        if (tContact === null) tContact = chrono;
        vitesse = Math.abs(vitesse) * P.elasticite;   // Math.abs, jamais -vitesse
        rebonds++;
        if (vitesse < 0.4) { vitesse = 0; endormie = true; }
      }
    }

    function etat() {
      const theorie = Math.sqrt((2 * (P.hauteur - RAYON)) / G);
      return \`hauteur \${(bille.position.y - RAYON).toFixed(2)} m · vitesse \${vitesse.toFixed(2)} m/s\\n\` +
             (tContact !== null
               ? \`touché en \${tContact.toFixed(2)} s · théorie √(2h/g) = \${theorie.toFixed(2)} s\\n\${rebonds} rebond\${rebonds > 1 ? 's' : ''}\${endormie ? ' · au repos' : ''}\`
               : \`chute en cours · théorie √(2h/g) = \${theorie.toFixed(2)} s\`);
    }`,
    ecouteurs: `
    brancher('hauteur', 'lh', 1, lancer);
    brancher('elasticite', 'le', 2, lancer);`,
  },

  pendule: {
    titre: 'Le pendule',
    groupee: '../debutant/03.2-pendule.html',
    intro: (a) => `Un pendule sur ${a.nom}. Sa période ne dépend que de la longueur et de
                   la gravité — jamais de la masse. Et la formule du lycée n'est vraie
                   qu'aux petits angles : poussez le curseur d'angle pour la voir craquer.`,
    loi: 'T = 2π√(L/g)',
    reglages: `
    <label>longueur — m <span class="deg" id="ll">2.0</span>
      <input type="range" id="longueur" min="0.5" max="3.5" step="0.05" value="2"></label>
    <label>angle de lâcher — ° <span class="deg" id="la">20</span>
      <input type="range" id="angle" min="1" max="170" step="1" value="20"></label>`,
    camera: 'camera.position.set(1.6, 2.4, 8); camera.lookAt(1.6, 2.6, 0);',
    corps: `
    const PIVOT = 4.2, ORIGINE = 1.6;
    const matiere = new THREE.MeshStandardMaterial({ color: COULEUR, roughness: 0.4, emissive: COULEUR, emissiveIntensity: 0.25 });
    const bille = new THREE.Mesh(new THREE.SphereGeometry(0.22, 32, 16), matiere);
    // La tige fait 1 de haut : on l'étire avec scale.y plutôt que de la reconstruire.
    const tige = new THREE.Mesh(new THREE.BoxGeometry(0.04, 1, 0.04), matiere);
    scene.add(bille, tige);

    const P = { longueur: 2, angle: 20 };
    let theta = 0, omega = 0, temps = 0, dernierPassage = null, periode = 0;

    function lancer() {
      theta = THREE.MathUtils.degToRad(P.angle);
      omega = 0; temps = 0; dernierPassage = null; periode = 0;
      placer();
    }

    function placer() {
      const dx = Math.sin(theta), dy = -Math.cos(theta);
      bille.position.set(ORIGINE + dx * P.longueur, PIVOT + dy * P.longueur, 0);
      tige.position.set(ORIGINE + dx * P.longueur / 2, PIVOT + dy * P.longueur / 2, 0);
      tige.scale.y = P.longueur;
      // Une Box est alignée sur +Y : θ + π la couche le long de la tige, pointe en bas.
      tige.rotation.z = theta + Math.PI;
    }
    lancer();

    function pas(dt) {
      temps += dt;
      // 8 SOUS-PAS : à 1/60 s, Euler « coupe le virage » au point bas et la période sort
      // 15 % trop courte. La page afficherait un chiffre faux.
      const SOUS_PAS = 8, h = dt / SOUS_PAS;
      for (let i = 0; i < SOUS_PAS; i++) {
        const avant = theta;
        // L'équation EXACTE : c'est le sin(θ) qui fait craquer la formule aux grands angles.
        omega += (-(G / P.longueur) * Math.sin(theta)) * h;
        theta += omega * h;
        // Deux passages consécutifs par la verticale = une DEMI-période, qu'on double.
        if (Math.sign(avant) !== Math.sign(theta) && avant !== 0) {
          const t = temps - (SOUS_PAS - 1 - i) * h;
          if (dernierPassage !== null) periode = 2 * (t - dernierPassage);
          dernierPassage = t;
        }
      }
      placer();
    }

    function etat() {
      const theorie = 2 * Math.PI * Math.sqrt(P.longueur / G);
      if (!periode) return \`angle \${THREE.MathUtils.radToDeg(theta).toFixed(1)}°\\nT théorique 2π√(L/g) = \${theorie.toFixed(3)} s\\nT mesurée … (attendez une oscillation)\`;
      const ecart = ((periode - theorie) / theorie) * 100;
      return \`angle \${THREE.MathUtils.radToDeg(theta).toFixed(1)}°\\n\` +
             \`T théorique 2π√(L/g) = \${theorie.toFixed(3)} s\\n\` +
             \`T mesurée = \${periode.toFixed(3)} s · écart \${ecart >= 0 ? '+' : ''}\${ecart.toFixed(1)} %\`;
    }`,
    ecouteurs: `
    brancher('longueur', 'll', 1, lancer);
    brancher('angle', 'la', 0, lancer);`,
  },

  tir: {
    titre: 'Le tir',
    groupee: '../debutant/06.1-tir-au-but.html',
    intro: (a) => `Un tir sur ${a.nom}. La portée d'un vol libre va en <b>1/g</b> — deux fois
                   moins de gravité, deux fois plus loin. Cherchez l'angle qui porte le plus :
                   il ne dépend ni de g, ni de la vitesse.`,
    loi: 'portée = v²·sin(2θ)/g',
    reglages: `
    <label>angle de tir — ° <span class="deg" id="la">45</span>
      <input type="range" id="angle" min="5" max="85" step="1" value="45"></label>
    <label>vitesse de tir — m/s <span class="deg" id="lv">5.0</span>
      <input type="range" id="vitesse" min="3" max="12" step="0.5" value="5"></label>`,
    camera: 'CADRER = true;',
    corps: `
    const RAYON = 0.22;
    const DEPART = new THREE.Vector3(0, RAYON, 0);   // on part de la hauteur d'arrivée :
    // c'est ce qui rend la portée comparable à v²·sin(2θ)/g, qui suppose départ = arrivée.

    const bille = new THREE.Mesh(
      new THREE.SphereGeometry(RAYON, 24, 14),
      new THREE.MeshStandardMaterial({ color: COULEUR, roughness: 0.4, emissive: COULEUR, emissiveIntensity: 0.3 })
    );
    scene.add(bille);

    const canon = new THREE.Mesh(
      new THREE.BoxGeometry(1.2, 0.14, 0.14),
      new THREE.MeshStandardMaterial({ color: 0xe6edf3, roughness: 0.5 })
    );
    scene.add(canon);

    const P = { angle: 45, vitesse: 5 };
    const vitesse = new THREE.Vector3();
    let vol = 0, contacts = 0, endormie = true, trace = null;

    const direction = () => {
      const a = THREE.MathUtils.degToRad(P.angle);
      return new THREE.Vector3(Math.cos(a), Math.sin(a), 0);
    };

    function placerCanon() {
      canon.rotation.z = THREE.MathUtils.degToRad(P.angle);
      canon.position.copy(DEPART).addScaledVector(direction(), 0.6);
    }

    let points = [];

    function lancer() {
      if (trace) { scene.remove(trace); trace.geometry.dispose(); trace.material.dispose(); trace = null; }
      bille.position.copy(DEPART);
      vitesse.copy(direction()).multiplyScalar(P.vitesse);
      points = [DEPART.clone()];
      vol = 0; contacts = 0; endormie = false;
      cadrer();
    }
    placerCanon();
    lancer();

    function pas(dt) {
      if (endormie) return;
      // g ne touche QUE y. Jamais x. C'est pour ça qu'un tir va plus loin, pas plus vite.
      vitesse.y -= G * dt;
      bille.position.addScaledVector(vitesse, dt);

      const dernier = points[points.length - 1];
      if (dernier.distanceToSquared(bille.position) > 0.005) points.push(bille.position.clone());

      if (bille.position.y - RAYON < 0) {
        bille.position.y = RAYON;
        if (contacts === 0) vol = bille.position.x;   // la portée du VOL : celle de la formule
        contacts++;
        vitesse.y = Math.abs(vitesse.y) * 0.5;
        vitesse.x *= 0.86;
        // Le seuil porte sur la vitesse VERTICALE : sur la totale, une bille sans rebond
        // glisserait au sol et prendrait un frottement à chaque IMAGE — donc dépendant du
        // nombre d'images par seconde.
        if (vitesse.y < 0.5) {
          endormie = true;
          const g = new THREE.BufferGeometry().setFromPoints(points);
          trace = new THREE.Line(g, new THREE.LineBasicMaterial({ color: COULEUR }));
          scene.add(trace);
        }
      }
    }

    function etat() {
      const a = THREE.MathUtils.degToRad(P.angle);
      const theorie = (P.vitesse * P.vitesse * Math.sin(2 * a)) / G;
      return \`x = \${bille.position.x.toFixed(2)} m\\n\` +
             (vol ? \`portée du vol \${vol.toFixed(2)} m\` : 'en vol') +
             \` · théorie \${theorie.toFixed(2)} m\\n\` +
             \`portée maximale possible (45°) : \${((P.vitesse * P.vitesse) / G).toFixed(2)} m\`;
    }

    // La portée varie d'un facteur 15 entre la Lune et Jupiter : on cadre sur ce que
    // CET astre-ci fait, sinon la page est vide ou déborde.
    function cadrer() {
      const portee = (P.vitesse * P.vitesse) / G;
      const large = Math.max(portee * 1.25, 3);
      grille.position.x = large / 2;
      camera.position.set(large * 0.35, large * 0.28, large * 1.3 + 4);
      controls.target.set(large * 0.35, large * 0.1, 0);
      controls.update();
    }`,
    ecouteurs: `
    brancher('angle', 'la', 0, () => { placerCanon(); lancer(); });
    brancher('vitesse', 'lv', 1, () => { placerCanon(); lancer(); });`,
    orbit: true,
  },
};

// ─────────────────────────────── LE GABARIT ───────────────────────────────

function page(astre, cle, sim) {
  const hex = '0x' + astre.couleur.toString(16).padStart(6, '0');
  const orbit = sim.orbit;

  return `<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>${sim.titre} sur ${astre.titre}</title>
  <link rel="stylesheet" href="../css/style.css">
  <script type="importmap">
  {
    "imports": {
      "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
      "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
    }
  }
  </script>
</head>
<body>
  <div class="hud hud-forme">
    <h2>${sim.titre} — ${astre.titre}</h2>
    <p class="famille">g = ${astre.g} m/s² · ${sim.loi}</p>
    <p>${sim.intro(astre).replace(/\s+/g, ' ').trim()}</p>
    <p>${astre.resume.replace(/\s+/g, ' ').trim()}</p>
${sim.reglages}
    <p><button type="button" id="relancer">↻ Relancer</button></p>
    <p><code id="lecture" class="multi"></code></p>

    <details open>
      <summary>${astre.titre} — ce qu'il faut savoir (${astre.faits.length})</summary>
      <ul>
${astre.faits.map((f) => `        <li>${f.replace(/\s+/g, ' ').trim()}</li>`).join('\n')}
      </ul>
    </details>

    <p class="famille">Les trois astres ensemble : <a href="${sim.groupee}">la page comparée →</a></p>
    <a href="../index.html">← Sommaire</a>
  </div>

  <script type="module">
    import * as THREE from 'three';${orbit ? `
    import { OrbitControls } from 'three/addons/controls/OrbitControls.js';` : ''}

    // La SEULE chose qui distingue cette page de ses deux sœurs.
    const G = ${astre.g};            // m/s², la gravité de ${astre.nom}
    const COULEUR = ${hex};

    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x0e1116);

    const camera = new THREE.PerspectiveCamera(50, window.innerWidth / window.innerHeight);

    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    document.body.appendChild(renderer.domElement);
${orbit ? `
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.maxPolarAngle = Math.PI / 2.1;
` : `    ${sim.camera}
`}
    const grille = new THREE.GridHelper(120, 60, 0x2a313c, 0x1c222b);
    scene.add(grille);

    scene.add(new THREE.AmbientLight(0xffffff, 0.6));
    const lampe = new THREE.DirectionalLight(0xffffff, 2);
    lampe.position.set(3, 6, 6);
    scene.add(lampe);

    // --- LA SIMULATION ---
${sim.corps}

    // Déclaré AVANT animer() : sinon la première image lirait une const encore dans sa
    // « zone morte temporelle » et la boucle planterait à chaque frame.
    const lecture = document.getElementById('lecture');

    const horloge = new THREE.Clock();

    function animer() {
      requestAnimationFrame(animer);
      // Plafonné : au retour d'un onglet, getDelta() peut valoir 30 s et l'objet
      // traverserait le sol en un pas.
      const dt = Math.min(horloge.getDelta(), 1 / 30);
      pas(dt);
      lecture.textContent = etat();${orbit ? `
      controls.update();   // OBLIGATOIRE avec enableDamping` : ''}
      renderer.render(scene, camera);
    }
    animer();

    // Un brancheur unique : le curseur écrit dans P, met son étiquette à jour, et relance.
    function brancher(id, etiq, decimales, apres) {
      const champ = document.getElementById(id);
      champ.addEventListener('input', () => {
        P[id] = Number(champ.value);
        document.getElementById(etiq).textContent = P[id].toFixed(decimales);
        apres();
      });
    }
${sim.ecouteurs}

    document.getElementById('relancer').addEventListener('click', lancer);
  <\/script>

  <script type="module" src="../js/code-panel.js"></script>
</body>
</html>
`;
}

// ─────────────────────────────── ÉCRITURE ───────────────────────────────

mkdirSync(join(RACINE, 'astres'), { recursive: true });

const liens = [];
for (const astre of ASTRES) {
  for (const [cle, sim] of Object.entries(SIMS)) {
    const nom = `${astre.id}-${cle}.html`;
    writeFileSync(join(RACINE, 'astres', nom), page(astre, cle, sim));
    liens.push(`      <a href="astres/${nom}"><b>${sim.titre}</b><span>${astre.titre} · g = ${astre.g}</span></a>`);
  }
}

console.log(`${ASTRES.length * Object.keys(SIMS).length} pages écrites dans astres/`);
writeFileSync(join(RACINE, 'scripts', 'astres-liens.html'), liens.join('\n') + '\n');
console.log('sommaire des liens écrit dans scripts/astres-liens.html');
