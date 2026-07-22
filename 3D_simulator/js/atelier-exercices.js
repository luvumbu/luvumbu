// Les exercices de l'atelier (atelier.html).
//
// DIFFÉRENCE DE FOND avec js/exemples.js (le bac à sable) : là-bas, le code EST la page,
// et « Exécuter » recrée tout dans une iframe jetable. Ici, la scène tourne déjà dans la
// page, et votre script AGIT dessus — il ne la remplace pas. C'est la différence entre
// réécrire un programme et lui parler.
//
// Un script n'est donc PAS un module : pas d'`import`. Il reçoit ce dont il a besoin en
// arguments (voir atelier.html), et il est rejoué à chaque « Exécuter » sur la même scène.

export const EXERCICES = [
  {
    id: '0',
    titre: '0 — L’écran vide (mais qui tourne déjà)',
    consigne: `L'écran est noir <b>parce que la scène est vide</b> — pas parce que rien ne
               marche. La boucle de rendu tourne depuis que vous avez ouvert la page, et
               elle appelle déjà votre script. Prouvons-le.`,
    essais: [
      'Ajoutez <code>log(camera.position)</code> : la caméra existe, elle est à (0, 0, 5).',
      'Ajoutez <code>scene.background.set("tomato")</code> — <b>l’écran change tout de suite</b>. Vous n’avez rien rechargé.',
      'Écrivez <code>chaqueImage((t) => log(t))</code> puis regardez la console : elle se remplit. Votre code tourne 60 fois par seconde.',
    ],
    code: `// Votre script agit sur la scène QUI TOURNE DÉJÀ. Vous ne la recréez pas.
//
// Ce qu'on vous donne, sans rien importer :
//   THREE              la bibliothèque
//   scene, camera      la scène en cours — modifiez-la, ça se voit tout de suite
//   ajouter(objet)     l'ajoute à la scène, et le retire au prochain lancement
//   chaqueImage(fn)    appelle fn(t, dt) à chaque image · t = secondes écoulées
//   log(...)           écrit dans la console, en bas à droite

log('objets dans la scène :', scene.children.length);
log('la caméra est en z =', camera.position.z);
log('Rien à voir : la scène est vide. C’est normal, et c’est le point de départ.');
`,
  },

  {
    id: '0b',
    titre: '0b — La couleur du fond, par script',
    consigne: `<code>scene.background</code> est un <code>THREE.Color</code> qui existe
               <b>déjà</b>. Votre script écrit dedans, et l'écran change — sans rechargement,
               sans iframe. C'est ça, interagir avec la page.`,
    essais: [
      'Changez le <code>"#1c3a5e"</code> : <code>"tomato"</code>, <code>0xff0000</code>, <code>"rgb(28,58,94)"</code> — les trois marchent.',
      'Décommentez le <code>chaqueImage</code> : la couleur se met à tourner. <b>Vous venez d’animer la page.</b>',
      'Un clignotement : <code>scene.background.setScalar(Math.sin(t * 4) > 0 ? 0.8 : 0.1)</code>.',
      'Le piège : essayez <code>scene.background = 0xff0000</code>. Ça casse — on ÉCRIT dans la couleur, on ne la remplace pas.',
    ],
    code: `// On ÉCRIT dans la couleur existante.
//    scene.background.set(...)   ✔
//    scene.background = 0xff0000 ✘  — on écraserait l'objet Color par un nombre
scene.background.set('#1c3a5e');

log('Fond posé. Décommentez la boucle ci-dessous pour l’animer.');

// ─────────────── À VOUS ───────────────
// chaqueImage() enregistre une fonction appelée à CHAQUE image, avec le temps écoulé.
// C'est le seul moyen de faire bouger quelque chose : le rendu ne devine rien.

// chaqueImage((t) => {
//   // setHSL(teinte, saturation, luminosité) — les trois entre 0 et 1.
//   scene.background.setHSL((t * 0.1) % 1, 0.55, 0.35);
// });
`,
    solution: `// Un lever de soleil qui boucle : nuit → aube → plein jour → nuit.
// Trois couleurs, et une interpolation pilotée par la hauteur du soleil.

const NUIT = new THREE.Color(0x070a10);
const AUBE = new THREE.Color(0xc9622f);
const JOUR = new THREE.Color(0x4a86c8);

chaqueImage((t) => {
  // Un cycle de 12 s. hauteur va de −1 (minuit) à +1 (midi).
  const hauteur = Math.sin((t / 12) * Math.PI * 2);

  if (hauteur <= 0) {
    // La nuit ne vire à l'aube que dans le dernier huitième : sinon le lever est brutal.
    scene.background.copy(NUIT).lerp(AUBE, Math.max(0, 1 + hauteur * 8));
  } else {
    scene.background.copy(AUBE).lerp(JOUR, Math.min(hauteur * 3, 1));
  }
});

log('Cycle de 12 secondes lancé.');
`,
  },
];
