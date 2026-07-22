// Génère `ressorts/` : un régime d'amortissement SEUL par page, en complément de 3.3.
//
// debutant/03.3 met les trois côte à côte — c'est la comparaison qui fait comprendre.
// Ces pages-ci font l'inverse : un régime à l'écran, son ζ réglable librement, et ses
// lois vérifiées au compteur. Les deux servent.
//
//   node scripts/creer-pages-ressorts.mjs

import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const RACINE = join(dirname(fileURLToPath(import.meta.url)), '..');

const REGIMES = [
  {
    id: 'sous-amorti', titre: 'Sous-amorti', couleur: 0xf78166, zeta: 0.15,
    loi: 'ζ < 1 · il oscille',
    resume: `Il dépasse la cible, revient, la dépasse encore. Chaque aller-retour est plus
             petit que le précédent, mais il y en a toujours un de plus.`,
    faits: [
      `<b>Il oscille à une pulsation PLUS LENTE que sa raideur ne le voudrait :</b>
       ω_d = ω·√(1−ζ²). L'amortissement ne fait pas que réduire l'amplitude — il ralentit
       aussi le rythme. Le compteur compare la période mesurée à 2π/ω_d.`,
      `<b>Chaque oscillation est plus petite d'un facteur constant.</b> C'est le
       <i>décrément logarithmique</i> : le rapport entre deux sommets successifs vaut
       toujours <code>exp(2πζ/√(1−ζ²))</code>, quelle que soit l'amplitude de départ. On
       mesure ζ d'un système réel exactement comme ça — en comptant ses rebonds.`,
      `<b>C'est le réglage qui donne le « ressort » des interfaces</b> — le petit dépassement
       qui fait qu'un panneau semble vivant. Mais mis sur une caméra qui suit un personnage,
       il donne le mal de mer. Un dépassement se choisit, il ne se subit pas.`,
      `<b>À ζ = 0, il n'y a plus d'amortissement du tout</b> : le ressort oscillerait pour
       l'éternité, à énergie constante. Poussez le curseur à zéro — c'est un pendule parfait,
       et c'est de la science-fiction : dans le monde réel, tout finit par s'arrêter.`,
    ],
  },
  {
    id: 'critique', titre: 'Critique', couleur: 0x7ee787, zeta: 1,
    loi: 'ζ = 1 · le plus rapide sans dépassement',
    resume: `Il rejoint sa cible au plus vite <b>sans jamais la dépasser</b>. C'est le
             réglage que vous cherchez presque toujours, et il se calcule.`,
    faits: [
      `<b>Il n'y a pas à tâtonner : c = 2·√(k·m).</b> C'est la frontière exacte entre
       « ça oscille » et « ça traîne ». Un cheveu en dessous, la bille dépasse ; un cheveu
       au-dessus, elle rampe. Aucun autre réglage n'arrive avant lui sans dépasser.`,
      `<b>Il n'atteint jamais sa cible</b>, il s'en approche indéfiniment :
       <code>x(t) = x₀·(1 + ωt)·e^(−ωt)</code>. Il n'a donc pas de durée — c'est ce qui le
       rend <b>interruptible</b>. Changez la cible en plein vol, le mouvement reste continu.
       Une animation de 0,3 s, elle, doit être coupée et relancée.`,
      `<b>C'est ce que fait <code>enableDamping</code> d'OrbitControls</b>, et toutes les
       caméras de suivi correctes. Quand une caméra « colle » sans jamais osciller autour de
       sa cible, c'est ça — pas une interpolation linéaire.`,
      `<b>Le piège le plus répandu de la 3D web reste <code>x += (cible − x) * 0.1</code>.</b>
       Ça ressemble à un amortissement, ça marche, et c'est <b>faux</b> : le lissage dépend du
       nombre d'images par seconde. Sur un 144 Hz, l'objet arrive deux fois plus vite que sur
       un 60 Hz. La correction tient en une ligne :
       <code>x += (cible − x) * (1 − Math.pow(0.001, dt))</code>.`,
    ],
  },
  {
    id: 'sur-amorti', titre: 'Sur-amorti', couleur: 0x58a6ff, zeta: 3,
    loi: 'ζ > 1 · il traîne',
    resume: `Il ne dépasse pas non plus — mais il met beaucoup plus longtemps. Le frein est
             si fort qu'il l'empêche d'avancer.`,
    faits: [
      `<b>Plus d'amortissement ne veut pas dire plus rapide — c'est le contraire.</b> Passé
       ζ = 1, chaque cran de frein <i>retarde</i> l'arrivée. Le compteur le montre : à ζ = 3,
       il met plusieurs fois le temps du critique pour le même trajet, et ne dépasse pas
       davantage. C'est perdant sur les deux tableaux.`,
      `<b>Il n'oscille plus DU TOUT : sa solution n'a plus de sinus.</b> Au-delà de ζ = 1,
       les deux racines de l'équation deviennent réelles, et le mouvement est une somme de
       deux exponentielles décroissantes. Mathématiquement, ce n'est plus le même objet.`,
      `<b>C'est la lente qui commande.</b> Des deux exponentielles, celle qui décroît le plus
       lentement finit toujours par dominer — et son taux vaut ω·(ζ − √(ζ²−1)), qui <b>tend
       vers zéro</b> quand ζ grandit. Doubler le frein, c'est doubler l'attente.`,
      `<b>Ça se justifie quand même parfois</b> : un ferme-porte, un amortisseur de recul, une
       balance de précision. Partout où le moindre dépassement est pire que la lenteur. Mais
       c'est un choix, jamais un défaut de réglage.`,
    ],
  },
];

function page(r, precedent, suivant) {
  const hex = '0x' + r.couleur.toString(16).padStart(6, '0');
  return `<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Ressort — ${r.titre}</title>
  <link rel="stylesheet" href="../css/style.css">
  <script type="importmap">
  { "imports": { "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js" } }
  </script>
</head>
<body>
  <div class="hud hud-forme">
    <h2>Ressort — ${r.titre}</h2>
    <p class="famille">un régime, seul · ${r.loi}</p>
    <p>${r.resume.replace(/\s+/g, ' ').trim()}</p>

    <label>ζ — l'amortissement <span class="deg" id="lz">${r.zeta.toFixed(2)}</span>
      <input type="range" id="zeta" min="0" max="5" step="0.01" value="${r.zeta}"></label>
    <label>raideur — k <span class="deg" id="lk">30</span>
      <input type="range" id="raideur" min="2" max="120" step="1" value="30"></label>
    <label>écart de départ — m <span class="deg" id="ld">2.0</span>
      <input type="range" id="depart" min="0.3" max="3" step="0.1" value="2"></label>
    <label>vitesse de lancer — m/s <span class="deg" id="lv">0.0</span>
      <input type="range" id="lance" min="-8" max="8" step="0.1" value="0"></label>
    <label>vitesse de lecture <span class="deg" id="lr">×1.00</span>
      <input type="range" id="vlecture" min="-3" max="0.602" step="0.002" value="0"></label>
    <label><input type="checkbox" id="trace" checked> laisser la trace</label>

    <p><button type="button" id="relancer">↻ Relâcher</button></p>
    <p><code id="lecture" class="multi"></code></p>

    <details open>
      <summary>${r.titre} — ce qu'il faut savoir (${r.faits.length})</summary>
      <ul>
${r.faits.map((f) => `        <li>${f.replace(/\s+/g, ' ').trim()}</li>`).join('\n')}
      </ul>
    </details>

    <p class="famille">Les trois régimes ensemble :
      <a href="../debutant/03.3-ressort.html">la page comparée →</a></p>
    <a href="${precedent}">← Précédent</a> ·
    <a href="${suivant}">Suivant →</a>
  </div>

  <script type="module">
    import * as THREE from 'three';

    // La SEULE chose qui distingue cette page de ses deux sœurs : la valeur de départ
    // de ζ. Le curseur permet ensuite d'aller voir les autres régimes — c'est un
    // continuum, pas trois objets différents.
    const COULEUR = ${hex};
    const MASSE = 1;

    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x0e1116);

    const camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight);
    camera.position.set(2.4, 0, 11);
    camera.lookAt(2.4, 0, 0);

    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    document.body.appendChild(renderer.domElement);

    addEventListener('resize', () => {
      camera.aspect = window.innerWidth / window.innerHeight;
      camera.updateProjectionMatrix();
      renderer.setSize(window.innerWidth, window.innerHeight);
    });

    scene.add(new THREE.AmbientLight(0xffffff, 0.6));
    const lampe = new THREE.DirectionalLight(0xffffff, 2);
    lampe.position.set(2, 5, 6);
    scene.add(lampe);

    const X = 2.4;   // à droite du HUD

    const bille = new THREE.Mesh(
      new THREE.SphereGeometry(0.3, 32, 16),
      new THREE.MeshStandardMaterial({ color: COULEUR, roughness: 0.4, emissive: COULEUR, emissiveIntensity: 0.3 })
    );
    scene.add(bille);

    // Le trait de la cible : y = 0, là où le ressort veut ramener la bille.
    const cible = new THREE.Mesh(
      new THREE.BoxGeometry(3.4, 0.03, 0.03),
      new THREE.MeshBasicMaterial({ color: 0x2a313c })
    );
    cible.position.x = X;
    scene.add(cible);

    // --- LA SIMULATION ---

    const P = { zeta: ${r.zeta}, raideur: 30, depart: 2, lance: 0, vlecture: 1 };

    let y = 2, v = 0, temps = 0, stable = null;
    let sommets = [];        // les sommets successifs, pour le décrément logarithmique
    let dernierY = 0, montait = false;

    // La trace : la courbe du mouvement, tracée dans le temps vers la droite.
    let points = [], ligne = null;

    function lancer() {
      y = P.depart;
      v = P.lance;
      temps = 0;
      stable = null;
      sommets = [];
      dernierY = y;
      montait = false;
      points = [];
      if (ligne) { scene.remove(ligne); ligne.geometry.dispose(); ligne.material.dispose(); ligne = null; }
    }
    lancer();

    const horloge = new THREE.Clock();

    function animer() {
      requestAnimationFrame(animer);
      // La vitesse de lecture, comme sur une vidéo : elle ne touche à AUCUNE constante —
      // ni k, ni ζ, ni la masse. Elle multiplie le temps qui s'écoule entre deux images.
      const dt = Math.min(horloge.getDelta(), 1 / 30) * P.vlecture;
      temps += dt;

      // 8 sous-pas : un ressort raide oscille vite, et à 1/60 s l'énergie dériverait —
      // la bille finirait par osciller PLUS FORT qu'au départ (voir 3.2).
      // Les sous-pas SUIVENT la vitesse de lecture : accélérer le temps ne donne pas le
      // droit de faire des pas plus grands, sinon l'énergie dérive.
      const SOUS_PAS = Math.ceil(8 * Math.max(1, P.vlecture)), h = dt / SOUS_PAS;
      for (let i = 0; i < SOUS_PAS; i++) {
        // c = 2·ζ·√(k·m) — l'amortissement se DÉDUIT de ζ, on ne le tâtonne jamais.
        const amortissement = 2 * P.zeta * Math.sqrt(P.raideur * MASSE);
        // La loi de Hooke, plus un frein proportionnel à la vitesse. Deux termes, et
        // tout le mouvement lissé du monde est là-dedans.
        const force = -P.raideur * y - amortissement * v;
        v += (force / MASSE) * h;
        y += v * h;
      }

      // Les sommets : on note chaque fois que la bille cesse de monter. C'est ainsi
      // qu'on mesure ζ sur un système réel — en comparant deux sommets successifs.
      const monte = y > dernierY;
      if (montait && !monte && Math.abs(dernierY) > 0.01) sommets.push(Math.abs(dernierY));
      montait = monte;
      dernierY = y;

      if (stable === null && Math.abs(y) < 0.01 && Math.abs(v) < 0.05) stable = temps;

      bille.position.set(X, y, 0);

      // La trace avance vers la droite avec le temps : la courbe du mouvement, en direct.
      if (document.getElementById('trace').checked) {
        points.push(new THREE.Vector3(X + temps * 0.7, y, 0));
        if (points.length > 900) points.shift();
        if (ligne) { scene.remove(ligne); ligne.geometry.dispose(); }
        ligne = new THREE.Line(
          new THREE.BufferGeometry().setFromPoints(points),
          ligne ? ligne.material : new THREE.LineBasicMaterial({ color: COULEUR })
        );
        scene.add(ligne);
      }

      afficher();
      renderer.render(scene, camera);
    }

    // Déclaré AVANT animer() : sinon la première image lirait une const encore dans sa
    // « zone morte temporelle » et la boucle planterait à chaque frame.
    const lecture = document.getElementById('lecture');

    function afficher() {
      const w = Math.sqrt(P.raideur / MASSE);          // la pulsation propre
      const z = P.zeta;
      let lignes = [];

      if (z < 1) {
        // Amorti mais oscillant : la pulsation RALENTIT, ω_d = ω·√(1−ζ²).
        const wd = w * Math.sqrt(1 - z * z);
        const T = (2 * Math.PI) / wd;
        // Le décrément logarithmique : le rapport entre deux sommets successifs.
        const rapport = Math.exp((2 * Math.PI * z) / Math.sqrt(1 - z * z));
        let mesure = '…';
        if (sommets.length >= 2) {
          const r2 = sommets[sommets.length - 2] / sommets[sommets.length - 1];
          mesure = r2.toFixed(2);
        }
        lignes.push(\`régime SOUS-AMORTI · période 2π/ω_d = \${T.toFixed(3)} s\`);
        lignes.push(\`chaque sommet vaut 1/\${rapport.toFixed(2)} du précédent · mesuré 1/\${mesure}\`);
      } else if (z === 1 || Math.abs(z - 1) < 0.005) {
        lignes.push('régime CRITIQUE · le plus rapide sans dépassement');
        lignes.push(\`x(t) = x₀·(1 + ωt)·e^(−ωt) · ω = \${w.toFixed(2)} rad/s\`);
      } else {
        // Sur-amorti : deux exponentielles réelles. La plus LENTE finit par dominer.
        const lent = w * (z - Math.sqrt(z * z - 1));
        lignes.push('régime SUR-AMORTI · plus de sinus, deux exponentielles');
        lignes.push(\`la plus lente décroît en \${lent.toFixed(2)} /s → temps ≈ \${(3 / lent).toFixed(2)} s\`);
      }

      lecture.textContent =
        \`ζ = \${z.toFixed(2)} · c = 2ζ√(km) = \${(2 * z * Math.sqrt(P.raideur * MASSE)).toFixed(2)}\\n\` +
        lignes.join('\\n') + '\\n' +
        \`écart \${Math.abs(y).toFixed(3)} m\${stable !== null ? \` · posé en \${stable.toFixed(2)} s\` : ' · en cours'}\`;
    }
    animer();

    function brancher(id, etiq, dec, apres) {
      const champ = document.getElementById(id);
      champ.addEventListener('input', () => {
        P[id] = Number(champ.value);
        document.getElementById(etiq).textContent = P[id].toFixed(dec);
        if (apres) apres();
      });
    }

    brancher('zeta', 'lz', 2, lancer);
    brancher('raideur', 'lk', 0, lancer);
    brancher('depart', 'ld', 1, lancer);
    brancher('lance', 'lv', 1, lancer);
    // La vitesse de lecture a son propre écouteur, et non le brancher générique : elle
    // ne relance rien (on doit pouvoir la bouger en plein vol, comme le ×0.5 d'une
    // vidéo) et son étiquette porte un « × » que le format générique effacerait.
    //
    // Elle travaille en PUISSANCES DE 10 : la plage utile va de ×0.001 à ×4, soit un
    // facteur 4000. Un curseur linéaire mettrait 99,9 % de sa course au-dessus de ×0.1 —
    // il aurait fallu choisir entre le très lent et le rapide. En logarithmique, chaque
    // millimètre vaut le même RAPPORT, et on garde les deux bouts.
    const champVitesse = document.getElementById('vlecture');
    const etiquetteVitesse = (v) => (v >= 1
      ? '×' + v.toFixed(2)
      : '×' + Number(v.toPrecision(2)) + ' · ' + Math.round(1 / v) + '× plus lent');

    champVitesse.addEventListener('input', () => {
      P.vlecture = Math.pow(10, Number(champVitesse.value));
      document.getElementById('lr').textContent = etiquetteVitesse(P.vlecture);
    });

    document.getElementById('relancer').addEventListener('click', lancer);
    document.getElementById('trace').addEventListener('change', (e) => {
      if (!e.target.checked && ligne) {
        scene.remove(ligne); ligne.geometry.dispose(); ligne.material.dispose(); ligne = null;
        points = [];
      }
    });
  <\/script>

  <script type="module" src="../js/code-panel.js"></script>
</body>
</html>
`;
}

mkdirSync(join(RACINE, 'ressorts'), { recursive: true });

REGIMES.forEach((r, i) => {
  const precedent = i === 0 ? '../debutant/03.3-ressort.html' : `${REGIMES[i - 1].id}.html`;
  const suivant = i === REGIMES.length - 1 ? '../debutant/03.4-ecrasement.html' : `${REGIMES[i + 1].id}.html`;
  writeFileSync(join(RACINE, 'ressorts', `${r.id}.html`), page(r, precedent, suivant));
});

console.log(`${REGIMES.length} pages écrites dans ressorts/`);
