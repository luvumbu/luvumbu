// Génère `matieres/` : un écrasement SEUL par matière, en complément de la page groupée.
//
// debutant/03.4 met les trois côte à côte — c'est la comparaison qui fait comprendre.
// Ces pages-ci font l'inverse : une seule matière à l'écran, ses réglages à elle, ses
// chiffres à elle, et une loi qu'on peut vérifier. Les deux servent.
//
//   node scripts/creer-pages-matieres.mjs

import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const RACINE = join(dirname(fileURLToPath(import.meta.url)), '..');

const MATIERES = [
  // ─────────────────────────────── RIGIDE ───────────────────────────────
  {
    id: 'rigide', titre: 'Rigide', couleur: 0x58a6ff, loi: 'h′ = e²·h',
    resume: `Il encaisse et repart, sans une égratignure. C'est le cube de 3.1 —
             et c'est une <b>idéalisation</b> qui n'existe nulle part.`,
    reglages: `
    <label>hauteur de lâcher — m <span class="deg" id="lh">6.0</span>
      <input type="range" id="hauteur" min="1" max="12" step="0.1" value="6"></label>
    <label>élasticité — le coefficient de restitution <span class="deg" id="le">0.60</span>
      <input type="range" id="elasticite" min="0" max="0.98" step="0.01" value="0.6"></label>
    <p>
      <button type="button" data-e="0.05">Plasticine</button>
      <button type="button" data-e="0.5">Bois</button>
      <button type="button" data-e="0.6" class="actif">Acier</button>
      <button type="button" data-e="0.75">Tennis</button>
      <button type="button" data-e="0.9">Verre</button>
    </p>`,
    faits: [
      `<b>Le rebond suit une loi exacte : h′ = e²·h.</b> Le <b>carré</b>, pas e. Une balle à
       e = 0,6 lâchée de 6 m ne remonte pas à 3,6 m mais à <b>2 m</b>. Le compteur le vérifie
       sous vos yeux — c'est la seule chose à retenir de cette page.`,
      `<b>Les valeurs des boutons sont réelles.</b> Le coefficient de restitution se mesure
       en laboratoire, exactement comme ici : on lâche, on mesure le rebond, on prend
       <code>√(h′/h)</code>. Plasticine ≈ 0,05 · bois ≈ 0,5 · acier sur acier ≈ 0,6 ·
       balle de tennis ≈ 0,75 · verre ≈ 0,9.`,
      `<b>e = 1 est interdit par la thermodynamique.</b> Un rebond parfait rendrait toute
       l'énergie ; or il en part toujours en <b>son</b> et en <b>chaleur</b>. Le bruit que fait
       une balle qui tombe, c'est littéralement l'énergie qui vous échappe. Poussez le curseur
       à 0,98 : la balle rebondit presque éternellement — et c'est de la science-fiction.`,
      `<b>Aucun solide n'est rigide.</b> Un diamant se déforme aussi, de l'ordre du millionième.
       « Rigide » veut seulement dire : la déformation est trop petite et trop brève pour
       compter. C'est une approximation de calcul, pas une matière.`,
      `<b>e ne dépend pas que de l'objet, mais du COUPLE.</b> Une bille d'acier sur du marbre
       rebondit ; la même sur du sable, non. Parler de « l'élasticité d'une balle » est un
       raccourci — le sol en fait toujours la moitié.`,
    ],
    corps: `
    const P = { hauteur: 6, lance: 0, elasticite: 0.6 };
    let vitesse = 0, rebonds = 0, sommet = 0, endormi = false;

    function lancer() {
      cube.position.set(0, P.hauteur, 0);
      vitesse = -P.lance;   // vers le BAS : le curseur ajoute une vitesse à la chute
      rebonds = 0; sommet = 0; endormi = false;
    }
    lancer();

    function pas(dt) {
      if (endormi) return;
      vitesse -= G * dt;
      cube.position.y += vitesse * dt;

      if (cube.position.y - DEMI < 0) {
        cube.position.y = DEMI;                        // corriger AVANT
        vitesse = Math.abs(vitesse) * P.elasticite;    // Math.abs, jamais -vitesse
        rebonds++;
        if (vitesse < 0.4) { vitesse = 0; endormi = true; }
      }
      // On ne suit QUE le premier rebond : c'est lui que la loi h′ = e²·h prédit.
      if (rebonds === 1) sommet = Math.max(sommet, cube.position.y - DEMI);
    }

    function etat() {
      const h = P.hauteur - DEMI;
      // Lancé ou lâché, seule la VITESSE d'impact compte : v = √(v₀² + 2gh). Les deux ne
      // s'additionnent pas — c'est l'énergie qui s'ajoute, et elle va comme le carré.
      const v = Math.sqrt(P.lance ** 2 + 2 * G * h);
      // D'où la généralisation de h′ = e²·h : la hauteur ÉQUIVALENTE d'un lancer à v, c'est
      // celle d'une chute libre qui donnerait la même vitesse — v²/(2g). Sans elle, la loi
      // ne tiendrait plus dès qu'on touche au curseur de vitesse.
      const hEq = (v * v) / (2 * G);
      const theorie = P.elasticite * P.elasticite * hEq;
      const ecart = sommet > 0.02 ? ((sommet - theorie) / theorie) * 100 : null;
      return \`chute de \${h.toFixed(2)} m\${P.lance ? \` · lancé à \${P.lance.toFixed(1)} m/s\` : ''}\\n\` +
             \`impact √(v₀²+2gh) = \${v.toFixed(2)} m/s · hauteur équivalente \${hEq.toFixed(2)} m\\n\` +
             (rebonds === 0
               ? 'en chute…'
               : \`1er rebond : \${sommet.toFixed(2)} m · théorie e²·h_éq = \${theorie.toFixed(2)} m\` +
                 (ecart !== null ? \` · écart \${ecart >= 0 ? '+' : ''}\${ecart.toFixed(1)} %\` : '')) +
             \`\\n\${rebonds} rebond\${rebonds > 1 ? 's' : ''}\${endormi ? ' · au repos' : ''}\`;
    }`,
    ecouteurs: `
    brancher('hauteur', 'lh', 1, lancer);
    brancher('elasticite', 'le', 2, lancer);
    brancher('lance', 'lv', 1, lancer);

    // Les matières réelles, avec leurs vrais coefficients.
    for (const b of document.querySelectorAll('[data-e]')) {
      b.addEventListener('click', () => {
        P.elasticite = Number(b.dataset.e);
        const champ = document.getElementById('elasticite');
        champ.value = P.elasticite;
        document.getElementById('le').textContent = P.elasticite.toFixed(2);
        document.querySelectorAll('[data-e]').forEach((x) => x.classList.toggle('actif', x === b));
        lancer();
      });
    }`,
  },

  // ─────────────────────────────── MOU ───────────────────────────────
  {
    id: 'mou', titre: 'Mou', couleur: 0x7ee787, loi: 'enfoncement = 0,64 · v/ω',
    resume: `Il encaisse en se déformant, puis se relève. C'est le
             <i>squash &amp; stretch</i> — le premier des douze principes de l'animation.`,
    reglages: `
    <label>hauteur de lâcher — m <span class="deg" id="lh">6.0</span>
      <input type="range" id="hauteur" min="1" max="12" step="0.1" value="6"></label>
    <label>souplesse <span class="deg" id="ls">1.0</span>
      <input type="range" id="souplesse" min="0.2" max="3" step="0.05" value="1"></label>`,
    faits: [
      `<b>L'écrasement DOIT conserver le volume.</b> Ce qu'on perd en hauteur, il faut le
       rendre en largeur : <code>x = z = 1/√s</code>, ce qui garde <code>x·y·z = 1</code>.
       Sans ça le cube <i>rétrécit</i>, et l'œil le repère instantanément — même sans savoir
       pourquoi.`,
      `<b>Le sol est un ressort, et la déformation EST l'enfoncement.</b> On ne replace jamais
       le cube de force : on le laisse s'enfoncer, le sol le repousse d'autant plus fort, et
       sa demi-hauteur vaut alors exactement sa hauteur de centre — son bas retombe sur zéro
       tout seul. Ma première version le repositionnait à la main : elle <b>saccadait</b>, et
       ça se voyait.`,
      `<b>Profondeur et durée sont le MÊME réglage</b>, et c'est frustrant : l'enfoncement
       vaut <code>v/ω</code> et le contact dure <code>π/ω</code>, avec ω = √k. Un ressort
       raide s'écrase peu <i>et</i> vite ; un ressort mou creuse <i>et</i> dure. On ne peut
       pas avoir un écrasement profond et bref, ni superficiel et long. Le compteur affiche
       les deux.`,
      `<b>Le <code>v/ω</code> qu'on lit partout suppose un ressort SANS amortissement.</b>
       Le nôtre en a (ζ = 0,35), et il creuse donc <b>36 % de moins</b> — le facteur exact
       sort de la solution de l'oscillateur amorti. La page affichait d'abord 21 % là où
       elle en mesurait 49 : la mesure avait raison, c'était la formule qui était trop
       simple. Une prédiction qui ne colle pas à la mesure est une prédiction fausse, pas
       une simulation cassée.`,
      `<b>Le contact doit être testé plus souvent que l'image.</b> Une seule détection par
       frame, et à 10 m/s le cube s'enfonce de 17 cm avant qu'on s'en aperçoive — le remonter
       d'un coup est une téléportation, et l'œil la voit. D'où les 12 sous-pas : l'enfoncement
       tombe à 1,4 cm. C'est pour ça que les moteurs physiques tournent à 240 Hz pendant que
       l'écran est à 60.`,
      `<b>Le squash & stretch donne le poids.</b> Un objet parfaitement rigide paraît
       <b>mort</b>. Même une balle de golf se déforme à l'impact — trop peu et trop vite pour
       qu'on le voie. En animation, on exagère : c'est ce qui fait sentir la masse.`,
      `<b>Attention au <code>scale</code> nul.</b> Une échelle à zéro rend la matrice de
       l'objet non inversible : les normales deviennent absurdes, l'objet vire au noir ou
       disparaît. Le code plafonne à 0,15 — jamais 0.`,
    ],
    corps: `
    const P = { hauteur: 6, lance: 0, souplesse: 1 };
    let vitesse = 0, ecrase = 1, endormi = false, creux = 1, impact = 0;

    function lancer() {
      cube.position.set(0, P.hauteur, 0);
      cube.scale.set(1, 1, 1);
      vitesse = -P.lance;   // vers le BAS
      ecrase = 1; endormi = false; creux = 1; impact = 0;
    }
    lancer();

    // Souple = ressort mou : s'écrase plus, et le contact dure plus longtemps.
    const raideur = () => 700 / P.souplesse;

    function pas(dt) {
      if (endormi && ecrase > 0.999) return;

      // 12 SOUS-PAS, et ce n'est pas cosmétique : le contact n'est testé qu'une fois par
      // pas. À 10 m/s et 1/60 s, le cube s'enfoncerait de 17 cm avant qu'on le remarque.
      // Les sous-pas SUIVENT la vitesse de lecture : accélérer le temps ne donne pas le
      // droit de faire des pas plus grands, sinon le tunneling revient.
      const SOUS_PAS = Math.ceil(12 * Math.max(1, LECTURE.valeur)), h = dt / SOUS_PAS;
      const k = raideur();

      for (let i = 0; i < SOUS_PAS; i++) {
        let acc = -G;

        // LE SOL EST UN RESSORT. On ne replace jamais le cube de force : on le laisse
        // s'enfoncer, et le sol le repousse d'autant plus fort. Rien ne se téléporte —
        // la déformation EST l'enfoncement, image après image.
        if (cube.position.y < DEMI) {
          if (!impact) impact = Math.abs(vitesse);   // ICI, avant que le sol ne freine
          // ζ ≈ 0.35 : un rebond mou. À ζ = 1 il se poserait sans jamais repartir.
          acc += k * (DEMI - cube.position.y) - 2 * Math.sqrt(k) * 0.35 * vitesse;
        }

        vitesse += acc * h;
        cube.position.y += vitesse * h;

        // Garde-fou : un ressort trop mou pour la vitesse laisserait passer le cube.
        if (cube.position.y < DEMI * 0.15) {
          cube.position.y = DEMI * 0.15;
          vitesse = Math.abs(vitesse) * 0.3;
        }
      }

      // La déformation ne se calcule pas : elle se LIT. Le cube est enfoncé de
      // (DEMI − y), donc sa demi-hauteur vaut y, donc son échelle vaut y/DEMI.
      // Et son bas retombe exactement sur zéro, tout seul.
      ecrase = THREE.MathUtils.clamp(cube.position.y / DEMI, 0.15, 1);   // JAMAIS zéro
      creux = Math.min(creux, ecrase);

      // LA CONSERVATION DU VOLUME : (1/√s) · s · (1/√s) = 1.
      const large = 1 / Math.sqrt(ecrase);
      cube.scale.set(large, ecrase, large);

      // Le repos exige d'avoir TOUCHÉ : sans \`impact\`, la première image le déclarerait
      // au repos — il vient d'être lâché, sa vitesse vaut encore 0,16 — et il se
      // téléporterait au sol avant de tomber. Un objet lent n'est pas un objet posé.
      if (impact && Math.abs(cube.position.y - DEMI) < 0.01 && Math.abs(vitesse) < 0.4) {
        vitesse = 0;
        cube.position.y = DEMI;
        endormi = true;
      }
    }

    const ZETA = 0.35;   // le taux d'amortissement du contact

    // L'enfoncement maximal d'un ressort AMORTI lancé à v. Le v/ω qu'on lit partout ne
    // vaut que sans amortissement : ici il surestime le creux d'un bon tiers. Le facteur
    // correctif sort de la solution exacte de l'oscillateur amorti — et il valait mieux
    // le poser que le deviner : ma première version affichait 21 % là où la page en
    // mesurait 49.
    const RACINE = Math.sqrt(1 - ZETA * ZETA);
    const FACTEUR = Math.exp((-ZETA / RACINE) * Math.atan(RACINE / ZETA));

    function etat() {
      const h = P.hauteur - DEMI;
      const v = Math.sqrt(P.lance ** 2 + 2 * G * h);   // lancé ou lâché : seule v compte
      const w = Math.sqrt(raideur());
      const enfoncement = (v / w) * FACTEUR;
      const prevu = Math.max(1 - enfoncement / DEMI, 0.15);
      // La durée du contact : une DEMI-période amortie, π/(ω·√(1−ζ²)).
      const contact = (Math.PI / (w * RACINE)) * 1000;
      const volume = (1 / Math.sqrt(ecrase)) ** 2 * ecrase;
      return \`impact √(2gh) = \${v.toFixed(2)} m/s\${impact ? \` · mesuré \${impact.toFixed(2)}\` : ''}\\n\` +
             \`hauteur actuelle : \${(ecrase * 100).toFixed(0)} % · creux atteint \${(creux * 100).toFixed(0)} %\\n\` +
             \`creux prévu : \${(prevu * 100).toFixed(0)} % · contact \${contact.toFixed(0)} ms\\n\` +
             \`volume x·y·z = \${volume.toFixed(6)} (doit valoir 1)\`;
    }`,
    ecouteurs: `
    brancher('hauteur', 'lh', 1, lancer);
    brancher('souplesse', 'ls', 1, lancer);
    brancher('lance', 'lv', 1, lancer);`,
  },

  // ─────────────────────────────── CASSANT ───────────────────────────────
  {
    id: 'cassant', titre: 'Cassant', couleur: 0xf78166, loi: 'E = m·g·h',
    resume: `Il n'encaisse pas : il cède. Un seul impact, et il n'existe plus —
             seulement ses morceaux.`,
    reglages: `
    <label>hauteur de lâcher — m <span class="deg" id="lh">6.0</span>
      <input type="range" id="hauteur" min="1" max="12" step="0.1" value="6"></label>
    <label>fragilité <span class="deg" id="lf">1.0</span>
      <input type="range" id="fragilite" min="0.3" max="3" step="0.05" value="1"></label>
    <label>découpe — N×N×N cellules <span class="deg" id="ln">5</span>
      <input type="range" id="decoupe" min="2" max="64" step="1" value="5"></label>`,
    faits: [
      `<b>Les morceaux SONT le cube.</b> On le découpe en N×N×N cellules, chacune à sa vraie
       place et à sa vraie taille : leur volume total vaut exactement le sien, par
       construction — le compteur l'affiche. Ma première version faisait <i>apparaître</i>
       120 petits cubes dans une flaque au sol : 0,37 m³ pour un cube qui en fait 1. Les
       deux tiers de la matière s'évaporaient à l'impact.`,
      `<b>Un objet qui casse ne s'arrête pas de tomber.</b> Les morceaux gardent tous la
       vitesse d'impact — rien ne les a freinés. Ce sont les cellules du <b>bas</b> qui
       touchent le sol ; celles du haut continuent leur chute et s'écrasent sur le tas. Ma
       première version les faisait <b>exploser vers le ciel</b> : ça n'arrive jamais.`,
      `<b>Ce qui projette la matière, c'est l'écrasement — pas une explosion.</b> La couche du
       bas est comprimée et n'a nulle part où aller sauf sur les côtés, comme une goutte qui
       s'étale. D'où l'éjection radiale <b>proportionnelle à la profondeur dans le tas</b> :
       forte en bas, nulle en haut. C'est ce qui donne la gerbe plate d'un objet qui se brise.`,
      `<b>Cette fracture triche encore, et il faut le dire.</b> Casser un objet réel
       <i>consomme</i> de l'énergie (créer des surfaces coûte : c'est la ténacité du
       matériau), les morceaux ne s'entrechoquent pas ici, et la découpe est une grille — le
       réel donnerait des cellules de <b>Voronoï</b> de tailles très inégales.`,
      `<b>Une vraie rupture est un autre métier.</b> On découpe l'objet à l'avance en cellules
       de <b>Voronoï</b>, on simule les contraintes internes, et on ne détache que ce qui
       dépasse le seuil. C'est le domaine de moteurs comme Rapier — pas de quinze lignes.`,
      `<b>Les morceaux sont un seul <code>InstancedMesh</code></b> (5.3). À 400 morceaux, un
       <code>Mesh</code> chacun ferait 400 appels de dessin pour des cubes de 16 cm. Poussez
       le curseur : le compteur d'appels ne bouge pas d'un poil. C'est exactement le cas
       d'usage de l'instanciation — beaucoup d'objets identiques, sans vie propre.`,
      `<b>L'énergie disponible est m·g·h — proportionnelle à la HAUTEUR</b>, pas à sa racine.
       C'est la vitesse qui va en √h (3.1) ; l'énergie, elle, double quand la hauteur double.
       Deux lois différentes pour la même chute, et on les confond tout le temps.`,
      `<b>Le vrai coût n'est pas les morceaux, c'est leur nombre d'objets.</b> Une explosion
       de jeu vidéo ne simule presque jamais 400 corps rigides : elle joue une animation
       cuite, ou des particules sans collision. Ici chaque morceau a sa physique — c'est
       jouable à 400, ça ne le serait pas à 40 000.`,
    ],
    corps: `
    const P = { hauteur: 6, lance: 0, fragilite: 1, decoupe: 5 };

    // LES MORCEAUX SONT LE CUBE. On le découpe en N×N×N cellules — chacune à sa vraie
    // place, à sa vraie taille. Leur volume total vaut N³·(COTE/N)³ = COTE³ : celui du
    // cube, exactement, par construction. Rien n'apparaît, rien ne s'évapore.
    let N = 5, DEBRIS = 125, TAILLE = COTE / 5;
    let CELLULES = [];

    // Un cube UNITAIRE : chaque instance sera mise à l'échelle par sa matrice.
    const formeDebris = new THREE.BoxGeometry(1, 1, 1);
    let debris = null;
    let bouts = [];

    const _mat = new THREE.Matrix4();
    const _q = new THREE.Quaternion();
    const _p = new THREE.Vector3();
    const _radial = new THREE.Vector3();
    const _taille = new THREE.Vector3();

    let vitesse = 0, casse = false, impact = 0, rayon2 = 0, tousPoses = false;

    function decouper() {
      N = Math.round(P.decoupe);
      DEBRIS = N * N * N;
      TAILLE = COTE / N;
      _taille.set(TAILLE, TAILLE, TAILLE);

      CELLULES = [];
      for (let ix = 0; ix < N; ix++) for (let iy = 0; iy < N; iy++) for (let iz = 0; iz < N; iz++) {
        CELLULES.push(new THREE.Vector3(
          ((ix + 0.5) / N - 0.5) * COTE,
          ((iy + 0.5) / N - 0.5) * COTE,
          ((iz + 0.5) / N - 0.5) * COTE,
        ));
      }
      bouts = CELLULES.map(() => ({
        pos: new THREE.Vector3(), vit: new THREE.Vector3(),
        rot: new THREE.Euler(), spin: new THREE.Vector3(),
      }));

      if (debris) { scene.remove(debris); debris.dispose(); }
      // Le nombre d'instances est FIGÉ à la création : pour en changer, on refabrique.
      debris = new THREE.InstancedMesh(formeDebris, matiere, DEBRIS);
      debris.visible = false;
      scene.add(debris);
    }

    function lancer() {
      decouper();
      cube.position.set(0, P.hauteur, 0);
      cube.visible = true;
      vitesse = -P.lance;   // vers le BAS
      casse = false; impact = 0; rayon2 = 0;
    }
    lancer();

    function briser() {
      casse = true;
      cube.visible = false;
      debris.visible = true;
      const v = Math.abs(vitesse);

      CELLULES.forEach((local, i) => {
        const b = bouts[i];

        // 1) Chaque morceau est LÀ OÙ IL ÉTAIT. Il ne naît pas : il était déjà là.
        b.pos.set(local.x, cube.position.y + local.y, local.z);

        // 2) Ils gardent tous la vitesse d'impact — rien ne les a freinés. Un cube qui
        //    tombe à 10 m/s ne s'arrête pas parce qu'il casse. Ce sont les cellules du
        //    BAS qui touchent le sol ; celles du haut continuent leur chute.
        b.vit.set(0, -v, 0);

        // 3) La matière du bas est CHASSÉE sur les côtés : comprimée, elle n'a nulle part
        //    où aller — exactement comme une goutte qui s'étale. D'où l'éjection radiale
        //    proportionnelle à la profondeur dans le tas : forte en bas, nulle en haut.
        //    C'est ce qui donne une gerbe plate, et non un feu d'artifice.
        const depuisLeBas = (local.y + DEMI) / COTE;
        const ejection = v * 0.5 * P.fragilite * (1 - depuisLeBas) ** 1.5;

        _radial.set(local.x, 0, local.z);
        if (_radial.lengthSq() < 1e-6) _radial.set(Math.random() - 0.5, 0, Math.random() - 0.5);
        _radial.normalize();
        b.vit.addScaledVector(_radial, ejection * (0.7 + Math.random() * 0.6));
        b.vit.y += v * 0.1 * Math.random() * (1 - depuisLeBas);

        b.rot.set(0, 0, 0);
        b.dort = false;
        // Le tournoiement suit la vitesse : un morceau qui part vite tourne vite.
        const vm = b.vit.length();
        b.spin.set((Math.random() - 0.5) * vm * 2, (Math.random() - 0.5) * vm * 2, (Math.random() - 0.5) * vm * 2);
      });
    }

    function pas(dt) {
      // Les mêmes sous-pas que partout : sans eux, un morceau à 10 m/s s'enfonce de
      // 17 cm avant qu'on le détecte, puis est remonté d'un coup — ça grouille.
      const SOUS = Math.ceil(6 * Math.max(1, LECTURE.valeur)), h = dt / SOUS;

      if (!casse) {
        for (let s = 0; s < SOUS; s++) {
          vitesse -= G * h;
          cube.position.y += vitesse * h;
          if (cube.position.y - DEMI < 0) { impact = Math.abs(vitesse); briser(); break; }
        }
        return;
      }

      const RAYON = TAILLE / 2;
      // LE SOMMEIL, et c'est lui qui permet les 30 000 morceaux : on ne refait pas la
      // physique d'un morceau posé, et on ne réenvoie pas sa matrice pour rien.
      //
      // ATTENTION : la branche « il dort » doit être VIDE. Ma première version y
      // recalculait le rayon d'éparpillement avec Math.hypot — 32 768 appels par image
      // une fois tout posé, et Math.hypot est lent (il protège contre les débordements).
      // Résultat : la page était PLUS lente au repos qu'en pleine explosion. Un sommeil
      // qui calcule quelque chose n'est pas un sommeil.
      let bouge = false;
      for (let i = 0; i < DEBRIS; i++) {
        const b = bouts[i];
        if (b.dort) continue;
        bouge = true;
        for (let s = 0; s < SOUS; s++) {
          b.vit.y -= G * h;
          b.pos.addScaledVector(b.vit, h);
          if (b.pos.y < RAYON) {
            b.pos.y = RAYON;
            // Un éclat ne rebondit pas comme une balle : il claque et il glisse.
            b.vit.y = Math.abs(b.vit.y) * 0.12;
            // Le frottement PAR SECONDE, jamais par contact : sinon le freinage
            // dépendrait du nombre d'images par seconde.
            const frein = Math.exp(-6 * h);
            b.vit.x *= frein;
            b.vit.z *= frein;
            if (b.vit.lengthSq() < 0.04) { b.vit.set(0, 0, 0); b.dort = true; }
          }
        }
        if (!b.dort) {
          b.rot.x += b.spin.x * dt;
          b.rot.y += b.spin.y * dt;
          b.rot.z += b.spin.z * dt;
        }
        // Un max COURANT, jamais remis à zéro : recalculer l'agrégat sur tous les
        // morceaux à chaque image annulerait le sommeil. Et un sqrt à la main plutôt
        // que Math.hypot, qui est dix fois plus lent pour la même chose.
        const r2 = b.pos.x * b.pos.x + b.pos.z * b.pos.z;
        if (r2 > rayon2) rayon2 = r2;

        _p.copy(b.pos);
        _q.setFromEuler(b.rot);
        _mat.compose(_p, _q, _taille);   // la cellule, à sa vraie taille
        debris.setMatrixAt(i, _mat);
      }
      // Le téléversement coûte : 30 000 matrices, c'est 2 Mo par image. Quand plus rien
      // ne bouge, on ne renvoie rien. Sans ça, un tas immobile coûterait autant qu'une
      // explosion en cours.
      if (bouge) debris.instanceMatrix.needsUpdate = true;
      tousPoses = !bouge;
    }

    function etat() {
      const h = P.hauteur - DEMI;
      // L'énergie par kilo : ½v₀² pour le lancer, plus g·h pour la chute. Les ÉNERGIES
      // s'additionnent — c'est justement pourquoi les vitesses, elles, ne le font pas.
      const energie = 0.5 * P.lance ** 2 + G * h;
      const volume = DEBRIS * TAILLE ** 3;
      return \`énergie de chute m·g·h = \${energie.toFixed(1)} J (pour 1 kg)\\n\` +
             \`\${N}×\${N}×\${N} = \${DEBRIS} morceaux de \${(TAILLE * 100).toFixed(0)} cm · \` +
             \`volume \${volume.toFixed(3)} m³ (le cube : \${(COTE ** 3).toFixed(3)})\\n\` +
             (casse ? \`brisé à \${impact.toFixed(1)} m/s · éparpillé sur \${Math.sqrt(rayon2).toFixed(2)} m\${tousPoses ? ' · tous posés' : ''}\` : 'en chute…') +
             \`\\nappels de dessin : \${renderer.info.render.calls} — les \${DEBRIS} morceaux n'en coûtent qu'UN\`;
    }`,
    ecouteurs: `
    brancher('hauteur', 'lh', 1, lancer);
    brancher('fragilite', 'lf', 1, lancer);
    brancher('decoupe', 'ln', 0, lancer);
    brancher('lance', 'lv', 1, lancer);`,
  },
];

// ─────────────────────────────── LE GABARIT ───────────────────────────────

function page(m, precedent, suivant) {
  const hex = '0x' + m.couleur.toString(16).padStart(6, '0');
  return `<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Écrasement — ${m.titre}</title>
  <link rel="stylesheet" href="../css/style.css">
  <script type="importmap">
  { "imports": { "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js" } }
  </script>
</head>
<body>
  <div class="hud hud-forme">
    <h2>Écrasement — ${m.titre}</h2>
    <p class="famille">une matière, seule · ${m.loi}</p>
    <p>${m.resume.replace(/\s+/g, ' ').trim()}</p>
    <label>vitesse de lecture <span class="deg" id="lr">×0.25 · 4× plus lent</span>
      <input type="range" id="vlecture" min="-3" max="0.602" step="0.002" value="-0.6"></label>
${m.reglages}
    <label>vitesse de lancer — m/s vers le bas <span class="deg" id="lv">0.0</span>
      <input type="range" id="lance" min="0" max="30" step="0.5" value="0"></label>
    <p><button type="button" id="relancer">↻ Le lâcher</button></p>
    <p><code id="lecture" class="multi"></code></p>

    <details open>
      <summary>${m.titre} — ce qu'il faut savoir (${m.faits.length})</summary>
      <ul>
${m.faits.map((f) => `        <li>${f.replace(/\s+/g, ' ').trim()}</li>`).join('\n')}
      </ul>
    </details>

    <p class="famille">Les trois matières ensemble :
      <a href="../debutant/03.4-ecrasement.html">la page comparée →</a></p>
    <a href="${precedent}">← Précédent</a> ·
    <a href="${suivant}">Suivant →</a>
  </div>

  <script type="module">
    import * as THREE from 'three';

    // La SEULE chose qui distingue cette page de ses deux sœurs : la matière.
    const COULEUR = ${hex};
    const G = 9.81;      // on reste sur Terre : ici la variable est la MATIÈRE
    const COTE = 1;
    const DEMI = COTE / 2;

    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x0e1116);

    const camera = new THREE.PerspectiveCamera(50, window.innerWidth / window.innerHeight);
    camera.position.set(2.6, 3.4, 11);
    camera.lookAt(2.6, 2.6, 0);

    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    document.body.appendChild(renderer.domElement);

    addEventListener('resize', () => {
      camera.aspect = window.innerWidth / window.innerHeight;
      camera.updateProjectionMatrix();
      renderer.setSize(window.innerWidth, window.innerHeight);
    });

    scene.add(new THREE.GridHelper(16, 16, 0x2a313c, 0x2a313c));
    scene.add(new THREE.AmbientLight(0xffffff, 0.6));
    const lampe = new THREE.DirectionalLight(0xffffff, 2.2);
    lampe.position.set(2, 7, 6);
    scene.add(lampe);

    const matiere = new THREE.MeshStandardMaterial({
      color: COULEUR, roughness: 0.45, emissive: COULEUR, emissiveIntensity: 0.2,
    });
    const cube = new THREE.Mesh(new THREE.BoxGeometry(COTE, COTE, COTE), matiere);
    scene.add(cube);

    // La vitesse de lecture vit à part : elle ne doit RIEN relancer, on doit pouvoir la bouger en
    // plein vol — comme le ×0.5 d'une vidéo. D'où l'objet plutôt qu'une variable, pour
    // que la boucle lise toujours la valeur courante.
    // Le curseur travaille en PUISSANCES DE 10 : la plage utile va de ×0.001 à ×4, soit
    // un facteur 4000. Un curseur linéaire mettrait 99,9 % de sa course au-dessus de
    // ×0.1 — il aurait fallu choisir entre le très lent et le rapide. En logarithmique,
    // chaque millimètre vaut le même RAPPORT, et on garde les deux bouts.
    const LECTURE = { valeur: 0.25 };

    // À ×0.001, l'impact de 60 ms s'étale sur une minute. « ×0.004 » ne parle à
    // personne ; « 250× plus lent », si.
    const etiquetteVitesse = (v) => (v >= 1
      ? '×' + v.toFixed(2)
      : '×' + Number(v.toPrecision(2)) + ' · ' + Math.round(1 / v) + '× plus lent');

    document.getElementById('vlecture').addEventListener('input', (e) => {
      LECTURE.valeur = Math.pow(10, Number(e.target.value));
      document.getElementById('lr').textContent = etiquetteVitesse(LECTURE.valeur);
    });

    // --- LA SIMULATION ---
${m.corps}

    // Déclaré AVANT animer() : sinon la première image lirait une const encore dans sa
    // « zone morte temporelle » et la boucle planterait à chaque frame.
    const lecture = document.getElementById('lecture');

    const horloge = new THREE.Clock();

    function animer() {
      requestAnimationFrame(animer);
      // Plafonné : au retour d'un onglet, getDelta() peut valoir 30 s et le cube
      // traverserait le sol en un seul pas.
      //
      // Puis RALENTI. Un impact réel dure 60 ms — quatre images : l'œil n'y voit qu'un
      // clac. Ralentir le temps ne triche pas sur la physique, chaque pas reste juste.
      // C'est exactement pour ça qu'on filme les crash-tests à 10 000 images/s.
      const dt = Math.min(horloge.getDelta(), 1 / 30) * LECTURE.valeur;
      pas(dt);
      renderer.render(scene, camera);
      lecture.textContent = etat();   // APRÈS le rendu : renderer.info n'est à jour qu'ensuite
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
${m.ecouteurs}

    document.getElementById('relancer').addEventListener('click', lancer);
  <\/script>

  <script type="module" src="../js/code-panel.js"></script>
</body>
</html>
`;
}

mkdirSync(join(RACINE, 'matieres'), { recursive: true });

MATIERES.forEach((m, i) => {
  const precedent = i === 0 ? '../debutant/03.4-ecrasement.html' : `${MATIERES[i - 1].id}.html`;
  const suivant = i === MATIERES.length - 1 ? '../debutant/04-lumiere.html' : `${MATIERES[i + 1].id}.html`;
  writeFileSync(join(RACINE, 'matieres', `${m.id}.html`), page(m, precedent, suivant));
});

console.log(`${MATIERES.length} pages écrites dans matieres/`);
