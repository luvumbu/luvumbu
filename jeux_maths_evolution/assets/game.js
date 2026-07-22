/* ============================================================
   LA PLANÈTE DES MATHS — jeu d'éveil mathématique (3 ans et +)
   Étape 1 : écran-titre · carte de l'univers · niveaux ·
             victoire · déblocage · sauvegarde · recommencer
   100 % hors-ligne : aucun fichier son/image (tout est dessiné
   ou synthétisé). Tactile (pointer events) + voix (fr-FR).
   ============================================================ */
(function () {
  'use strict';

  const cv = document.getElementById('jeu');
  const ctx = cv.getContext('2d');
  const W = 900, H = 600;     // résolution logique du jeu

  /* ---------- adaptation à l'écran (net, sans étirement) ---------- */
  function resize() {
    const dpr = Math.min(3, window.devicePixelRatio || 1);
    const scale = Math.min(window.innerWidth / W, window.innerHeight / H);
    const cssW = Math.round(W * scale), cssH = Math.round(H * scale);
    // taille affichée : aspect 3:2 conservé (pas d'étirement)
    cv.style.width = cssW + 'px';
    cv.style.height = cssH + 'px';
    // résolution réelle = résolution native de l'écran (pas de flou)
    cv.width = Math.round(cssW * dpr);
    cv.height = Math.round(cssH * dpr);
    // le repère de dessin reste 0..900 / 0..600
    ctx.setTransform(scale * dpr, 0, 0, scale * dpr, 0, 0);
  }
  window.addEventListener('resize', resize);
  resize();

  /* ---------- petits utilitaires ---------- */
  function rnd(n) { return Math.floor(Math.random() * n); }
  function melanger(a) { for (let i = a.length - 1; i > 0; i--) { const j = rnd(i + 1); const t = a[i]; a[i] = a[j]; a[j] = t; } return a; }
  function dans(p, b) { return p.x > b.x && p.x < b.x + b.w && p.y > b.y && p.y < b.y + b.h; }
  function roundRect(x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }
  function etoile(x, y, r, plein) {
    ctx.beginPath();
    for (let i = 0; i < 5; i++) {
      const a = -Math.PI / 2 + i * 2 * Math.PI / 5;
      const a2 = a + Math.PI / 5;
      ctx.lineTo(x + Math.cos(a) * r, y + Math.sin(a) * r);
      ctx.lineTo(x + Math.cos(a2) * r * 0.45, y + Math.sin(a2) * r * 0.45);
    }
    ctx.closePath();
    ctx.fillStyle = plein ? '#ffd23f' : '#3a4366';
    ctx.fill();
  }

  /* ---------- sons (synthétisés) + voix ---------- */
  let actx = null;
  function audio() {
    try {
      if (!actx) actx = new (window.AudioContext || window.webkitAudioContext)();
      if (actx.state === 'suspended') actx.resume();
    } catch (e) {}
    return actx;
  }
  function note(f, t0, d, v) {
    const o = actx.createOscillator(), g = actx.createGain();
    o.type = 'triangle'; o.frequency.value = f;
    o.connect(g); g.connect(actx.destination);
    g.gain.setValueAtTime(0.0001, t0);
    g.gain.exponentialRampToValueAtTime(v || 0.3, t0 + 0.02);
    g.gain.exponentialRampToValueAtTime(0.0001, t0 + d);
    o.start(t0); o.stop(t0 + d);
  }
  function sonPop(n) { if (sonOn && audio()) note(440 + n * 70, actx.currentTime, 0.16); }
  function sonOk() { if (sonOn && audio()) note(680, actx.currentTime, 0.18, 0.32); }
  function sonNon() { if (sonOn && audio()) note(170, actx.currentTime, 0.16, 0.25); }
  function sonFete() { if (sonOn && audio()) [523, 659, 784, 1047].forEach((f, i) => note(f, actx.currentTime + i * 0.12, 0.28, 0.32)); }
  function sonHit() { if (sonOn && audio()) { note(140, actx.currentTime, 0.16, 0.4); note(320, actx.currentTime + 0.02, 0.12, 0.25); } }
  function sonBoss() { if (sonOn && audio()) [220, 175, 140].forEach((f, i) => note(f, actx.currentTime + i * 0.16, 0.30, 0.32)); }

  let sonOn = true;
  try { sonOn = (localStorage.getItem('jme_son') !== 'off'); } catch (e) {}
  let voixOn = true;
  try { voixOn = (localStorage.getItem('jme_voix') !== 'off'); } catch (e) {}
  // sélection (différée) d'une voix française
  let voixFR = null;
  function chargerVoix() {
    try {
      const vs = speechSynthesis.getVoices();
      voixFR = vs.find(function (v) { return /^fr/i.test(v.lang); })
            || vs.find(function (v) { return /fran/i.test(v.name); })
            || null;
    } catch (e) {}
  }
  if ('speechSynthesis' in window) {
    chargerVoix();
    speechSynthesis.onvoiceschanged = chargerVoix;
  }
  function parler(texte) {
    if (!voixOn || !('speechSynthesis' in window)) return;
    try {
      speechSynthesis.cancel();
      const u = new SpeechSynthesisUtterance(texte);
      if (voixFR) u.voice = voixFR;
      u.lang = 'fr-FR'; u.rate = 0.95; u.pitch = 1.1;
      speechSynthesis.resume();   // contourne la mise en pause auto de certains navigateurs
      speechSynthesis.speak(u);
    } catch (e) {}
  }

  /* ---------- sauvegarde de la progression ---------- */
  function charger() {
    try { const s = JSON.parse(localStorage.getItem('jme_prog8')); if (s && s.max) return s; } catch (e) {}
    return { max: 1, etoiles: {} };
  }
  function sauver() { try { localStorage.setItem('jme_prog8', JSON.stringify(prog)); } catch (e) {} }
  let prog = charger();

  /* ---------- couleurs des bulles ---------- */
  const COULEURS = [
    { c: '#ff5c6c', nom: 'rouges' },
    { c: '#ffd23f', nom: 'jaunes' },
    { c: '#3ddc84', nom: 'vertes' },
    { c: '#4aa3ff', nom: 'bleues' }
  ];

  /* ---------- formes (mélange figures + chiffres) ---------- */
  const FIGNAMES = ['rond', 'carré', 'triangle', 'rectangle', 'pentagone'];
  function coteDe(f) { return f === 'triangle' ? 3 : f === 'pentagone' ? 5 : f === 'rond' ? 0 : 4; }
  function pluriel(f) { return f === 'rond' ? 'ronds' : f === 'carré' ? 'carrés' : f === 'triangle' ? 'triangles' : f === 'rectangle' ? 'rectangles' : f + 's'; }

  /* ---------- définition des niveaux (univers évolutif) ---------- */
  // parcours d'exercices, très progressif (figures + chiffres mélangés).
  // Un BOSS est inséré automatiquement tous les 3 exercices : il faut le battre pour continuer.
  const PARCOURS = [
    { nom: 'Compter', type: 'pop-all', n: 3 },
    { nom: 'Les couleurs', type: 'pop-color' },
    { nom: 'Les formes', type: 'shape-touch' },
    { nom: 'Combien ?', type: 'count', maxObj: 4 },
    { nom: 'Compte les ronds', type: 'shape-count', forme: 'rond', maxObj: 4 },
    { nom: 'Plus grand', type: 'compare', max: 5 },
    { nom: 'Trouve le carré', type: 'shape-touch' },
    { nom: 'Combien ?', type: 'count', maxObj: 6 },
    { nom: 'Les côtés', type: 'shape-sides' },
    { nom: '1re addition', type: 'add', max: 5 },
    { nom: 'Compte les carrés', type: 'shape-count', forme: 'carré', maxObj: 6 },
    { nom: 'Plus grand', type: 'compare', max: 8 },
    { nom: '1re soustraction', type: 'sub', max: 5 },
    { nom: 'Compte les triangles', type: 'shape-count', forme: 'triangle', maxObj: 7 },
    { nom: 'Combien ?', type: 'count', maxObj: 9 },
    { nom: 'Addition', type: 'add', max: 9 },
    { nom: 'Soustraction', type: 'sub', max: 9 },
    { nom: 'Plus grand', type: 'compare', max: 10 }
  ];
  const NIVEAUX = [];
  (function () {
    let id = 0;
    function push(o) { id++; NIVEAUX.push(Object.assign({ id: id }, o)); }
    for (let i = 0; i < PARCOURS.length; i++) {
      push(PARCOURS[i]);
      if ((i + 1) % 3 === 0) {                          // un Boss tous les 3 exercices : il valide le groupe
        const trois = PARCOURS.slice(i - 2, i + 1).map(function (e) { return Object.assign({}, e); });
        push({ nom: 'Boss', type: 'exam', steps: trois });
      }
    }
  })();
  const AVEC_BOSS = NIVEAUX.some(function (l) { return l.type === 'exam'; });
  // le dernier Boss devient le "Boss Final" : il combine tout et débloque tout
  const BOSS_FINAL = (function () { let b = null; for (const lv of NIVEAUX) if (lv.type === 'exam') b = lv; return b; })();
  if (BOSS_FINAL) {
    BOSS_FINAL.nom = 'Boss Final'; BOSS_FINAL.final = true;
    BOSS_FINAL.steps = [
      { type: 'pop-color' },
      { type: 'shape-sides' },
      { type: 'count', maxObj: 8 },
      { type: 'compare', max: 10 },
      { type: 'sub', max: 9 },
      { type: 'add', max: 10 }
    ];
  }
  const BOSS_INTER = NIVEAUX.filter(function (l) { return l.type === 'exam' && !l.final; });
  // coût en étoiles : chaque Boss demande 6 de plus que le précédent (6, 12, 18, 24, 30...)
  (function () { let r = 0; for (const lv of NIVEAUX) if (lv.type === 'exam') { r++; lv.cout = 6 * r; } })();

  const PAR_PAGE = 6;
  const NB_PAGES = Math.ceil(NIVEAUX.length / PAR_PAGE);
  const MONDES = ['Découverte', 'Formes & nombres', 'On progresse', 'Champions'];
  let pageCarte = 0;

  // 6 emplacements en serpent sur une page de carte
  const SLOTS = [];
  for (let i = 0; i < PAR_PAGE; i++) {
    const ligne = Math.floor(i / 3), col = i % 3;
    const x = (ligne % 2 === 0) ? 180 + col * 270 : 720 - col * 270;
    const y = 175 + ligne * 200;
    SLOTS.push({ x: x, y: y });
  }
  const btnPrev = { x: 24, y: H / 2 - 40, w: 56, h: 80 };
  const btnNext = { x: W - 80, y: H / 2 - 40, w: 56, h: 80 };
  function pageCourante() { return Math.floor((Math.min(NIVEAUX.length, prog.max) - 1) / PAR_PAGE); }
  function allerCarte() { pageCarte = pageCourante(); scene = 'carte'; }

  /* ---------- décor étoilé ---------- */
  const ETOILES_FOND = [];
  for (let i = 0; i < 90; i++) ETOILES_FOND.push({ x: Math.random() * W, y: Math.random() * H, r: Math.random() * 1.6 + 0.4 });
  function fondEspace() {
    const g = ctx.createLinearGradient(0, 0, 0, H);
    g.addColorStop(0, '#0b1026'); g.addColorStop(1, '#161d3f');
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = '#ffffff';
    for (const s of ETOILES_FOND) { ctx.globalAlpha = 0.5 + 0.5 * Math.abs(Math.sin((lastT + s.x) / 700)); ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2); ctx.fill(); }
    ctx.globalAlpha = 1;
  }

  /* ---------- état général ---------- */
  let scene = 'titre';        // 'titre' | 'carte' | 'niveau' | 'gagne'
  let lastT = 0;
  // état d'un niveau
  let niv = null, consigne = '', erreurs = 0, etoileGagnee = 0, gagneT = 0;
  let bulles = [], cartes = [], objA = 0, objB = 0, compte = 0, cibleCol = null;
  let figures = [], cibleForme = null, cibleCotes = 0, cibleCompte = 0;
  // mode "examen" : enchaîne plusieurs épreuves, doit toutes les réussir
  let examMode = false, examSteps = [], examIdx = 0, epreuveType = '';
  let messageRetour = '', messageT = 0, domaineRate = '';
  let bossPV = 0, bossHitT = 0, epreuveStartT = 0;

  // boutons fixes
  const btnVoix = { x: 18, y: 16, w: 52, h: 52 };
  const btnSon = { x: 76, y: 16, w: 52, h: 52 };
  const btnRetour = { x: 18, y: H - 64, w: 150, h: 46 };
  const btnTestOk = { x: 350, y: H - 64, w: 165, h: 46 };   // TEST : réussir
  const btnTestKo = { x: 530, y: H - 64, w: 150, h: 46 };   // TEST : rater
  let proposeBtns = [];

  // ---- leçons "découverte" (annexes pédagogiques) ----
  const LECONS = [
    { id: 'compter', titre: 'Apprendre à compter' },
    { id: 'couleurs', titre: 'Reconnaître les couleurs' },
    { id: 'formes', titre: 'Reconnaître les formes' },
    { id: 'cote', titre: "C'est quoi un côté ?" },
    { id: 'coin', titre: "C'est quoi un coin ?" },
    { id: 'comparer', titre: 'Plus grand / plus petit' },
    { id: 'addition', titre: "C'est quoi une addition ?" },
    { id: 'soustraction', titre: "C'est quoi une soustraction ?" }
  ];
  const btnApprendre = { x: W / 2 - 160, y: 520, w: 320, h: 52 };
  const btnRetourLecon = { x: 18, y: H - 64, w: 160, h: 46 };
  const btnNextLecon = { x: W / 2 - 170, y: 528, w: 340, h: 52 };
  const btnPasser = { x: W - 196, y: 14, w: 180, h: 46 };
  let leconsBtns = [];
  let lecon = null, lecParts = [], lecN = 0, lecMsg = '', lecConsigne = '', lecIdx = 0, lecDone = false;
  let lecExercice = null;   // l'exercice dont on suit le cours (null = leçons libres)
  // le cours dédié à chaque type d'exercice
  const LECON_DE = {
    'pop-all': 'compter', 'count': 'compter',
    'pop-color': 'couleurs',
    'shape-touch': 'formes', 'shape-count': 'formes',
    'shape-sides': 'cote',
    'add': 'addition', 'sub': 'soustraction', 'compare': 'comparer'
  };

  /* ---------- entrées tactiles ---------- */
  function pos(e) {
    const r = cv.getBoundingClientRect();
    return { x: (e.clientX - r.left) * W / r.width, y: (e.clientY - r.top) * H / r.height };
  }
  cv.addEventListener('pointerdown', function (e) {
    e.preventDefault();
    audio();
    try { if ('speechSynthesis' in window) speechSynthesis.resume(); } catch (err) {}
    const p = pos(e);
    // bouton voix : disponible partout
    if (dans(p, btnVoix)) {
      voixOn = !voixOn;
      try { localStorage.setItem('jme_voix', voixOn ? 'on' : 'off'); } catch (err) {}
      if (!voixOn) { try { speechSynthesis.cancel(); } catch (err) {} }
      return;
    }
    // bouton son (bruitages) : disponible partout
    if (dans(p, btnSon)) {
      sonOn = !sonOn;
      try { localStorage.setItem('jme_son', sonOn ? 'on' : 'off'); } catch (err) {}
      return;
    }
    // raccourci Boss : disponible à tout moment hors combat
    if (AVEC_BOSS && (scene === 'titre' || scene === 'carte' || scene === 'gagne' || scene === 'perdu' || scene === 'propose') && dans(p, btnBoss)) { tenterBoss(); return; }
    if (scene === 'titre') clicTitre(p);
    else if (scene === 'carte') clicCarte(p);
    else if (scene === 'niveau') clicNiveau(p);
    else if (scene === 'gagne') clicGagne(p);
    else if (scene === 'perdu') clicPerdu(p);
    else if (scene === 'propose') clicPropose(p);
    else if (scene === 'lecons') clicLecons(p);
    else if (scene === 'lecon') clicLecon(p);
  });

  /* ====================== SCÈNE : TITRE ====================== */
  const btnJouer = { x: W / 2 - 150, y: 340, w: 300, h: 84 };
  const btnRaz = { x: W / 2 - 120, y: 460, w: 240, h: 50 };

  function clicTitre(p) {
    if (dans(p, btnJouer)) { allerCarte(); parler("Choisis une planète"); return; }
    if (dans(p, btnApprendre)) { scene = 'lecons'; parler('Choisis une leçon'); return; }
    if (dans(p, btnRaz)) { prog = { max: 1, etoiles: {} }; sauver(); parler("On recommence l'aventure"); return; }
  }
  function dessinerTitre() {
    fondEspace();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#fff'; ctx.font = 'bold 58px Segoe UI';
    ctx.fillText('La Planète des Maths', W / 2, 150);
    ctx.font = '70px Segoe UI'; ctx.fillText('🚀  🪐  ⭐', W / 2, 250);
    ctx.fillStyle = prog.fini ? '#ffd23f' : '#9fb3d1'; ctx.font = '22px Segoe UI';
    ctx.fillText(prog.fini ? '🏆 Champion des Maths — tout est débloqué !'
      : "Explore la galaxie et deviens un champion des nombres !", W / 2, 305);

    const plus = (prog.max > 1) ? 'Continuer' : 'Jouer';
    bouton(btnJouer, '▶ ' + plus, '#7c5cff', 34);
    bouton(btnApprendre, '📘 Apprendre (les leçons)', '#3a7bd5', 22);
    boutonLeger(btnRaz, '↺ Tout recommencer');

    dessinerBoutonBoss();
    dessinerVoix();
  }

  /* ====================== SCÈNE : CARTE ====================== */
  // ---- étoiles : total, retrait, déblocage du Boss ----
  // porte-monnaie d'étoiles : rempli par les exercices (à chaque réussite), dépensé/perdu sur les Boss
  function totalEtoiles() { return prog.wallet || 0; }
  // un Boss est ouvert s'il est atteint dans la suite ET qu'on a son coût en étoiles
  function estOuvert(lv) {
    if (lv.type !== 'exam') return lv.id <= prog.max;
    return totalEtoiles() >= (lv.cout || 0);   // n'importe quel Boss selon le nombre d'étoiles
  }

  // ---- raccourci "Affronter le Boss" : vise le prochain Boss à battre ----
  // bouton du BOSS FINAL : tout à droite, jouable à tout moment, en forme de masque africain
  const btnBoss = { x: W - 92, y: H - 182, w: 78, h: 158 };
  function tenterBoss() {
    if (!BOSS_FINAL) return;
    if (totalEtoiles() >= (BOSS_FINAL.cout || 0)) demarrerNiveau(BOSS_FINAL);
    else parler('Il te faut ' + BOSS_FINAL.cout + ' étoiles pour le Boss Final');
  }

  // masque africain stylisé, centré en (cx, cy), de taille s
  function dessinerMasque(cx, cy, s) {
    ctx.fillStyle = '#6e4326'; ctx.strokeStyle = '#2f1d10'; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.ellipse(cx, cy, s * 0.62, s, 0, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
    // motifs du front
    ctx.strokeStyle = '#caa15e'; ctx.lineWidth = 3;
    for (let i = -1; i <= 1; i++) { ctx.beginPath(); ctx.moveTo(cx + i * s * 0.2, cy - s * 0.78); ctx.lineTo(cx + i * s * 0.2, cy - s * 0.42); ctx.stroke(); }
    // yeux
    ctx.fillStyle = '#120a05';
    ctx.beginPath(); ctx.ellipse(cx - s * 0.28, cy - s * 0.12, s * 0.17, s * 0.1, 0, 0, Math.PI * 2); ctx.fill();
    ctx.beginPath(); ctx.ellipse(cx + s * 0.28, cy - s * 0.12, s * 0.17, s * 0.1, 0, 0, Math.PI * 2); ctx.fill();
    // nez long
    ctx.fillStyle = '#3a2414';
    ctx.beginPath(); ctx.moveTo(cx, cy - s * 0.1); ctx.lineTo(cx - s * 0.11, cy + s * 0.4); ctx.lineTo(cx + s * 0.11, cy + s * 0.4); ctx.closePath(); ctx.fill();
    // bouche
    ctx.fillStyle = '#caa15e';
    ctx.beginPath(); ctx.ellipse(cx, cy + s * 0.62, s * 0.22, s * 0.11, 0, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#120a05'; ctx.fillRect(cx - s * 0.18, cy + s * 0.6, s * 0.36, 3);
  }
  function dessinerBoutonBoss() {
    if (!AVEC_BOSS || !BOSS_FINAL) return;
    ctx.fillStyle = 'rgba(0,0,0,.30)'; roundRect(btnBoss.x, btnBoss.y, btnBoss.w, btnBoss.h, 14); ctx.fill();
    ctx.strokeStyle = '#caa15e'; ctx.lineWidth = 2; roundRect(btnBoss.x, btnBoss.y, btnBoss.w, btnBoss.h, 14); ctx.stroke();
    dessinerMasque(btnBoss.x + btnBoss.w / 2, btnBoss.y + btnBoss.h * 0.40, btnBoss.h * 0.26);
    ctx.fillStyle = '#ffd6a0'; ctx.font = 'bold 13px Segoe UI'; ctx.textAlign = 'center'; ctx.textBaseline = 'alphabetic';
    ctx.fillText('BOSS', btnBoss.x + btnBoss.w / 2, btnBoss.y + btnBoss.h - 24);
    ctx.fillText('FINAL', btnBoss.x + btnBoss.w / 2, btnBoss.y + btnBoss.h - 9);
  }

  function clicCarte(p) {
    if (dans(p, btnRetour)) { scene = 'titre'; return; }
    if (dans(p, btnPrev) && pageCarte > 0) { pageCarte--; return; }
    if (dans(p, btnNext) && pageCarte < NB_PAGES - 1) { pageCarte++; return; }
    for (let s = 0; s < PAR_PAGE; s++) {
      const gi = pageCarte * PAR_PAGE + s;
      if (gi >= NIVEAUX.length) break;
      const lv = NIVEAUX[gi];
      const c = SLOTS[s];
      const dx = p.x - c.x, dy = p.y - c.y;
      if (dx * dx + dy * dy < 52 * 52) {
        if (estOuvert(lv)) ouvrirExercice(lv);
        else if (lv.type === 'exam') parler('Il te faut ' + lv.cout + ' étoiles pour ce Boss');
        return;
      }
    }
  }
  function flecheMonde(b, fleche) {
    ctx.fillStyle = 'rgba(255,255,255,.10)'; roundRect(b.x, b.y, b.w, b.h, 12); ctx.fill();
    ctx.strokeStyle = 'rgba(255,255,255,.3)'; ctx.lineWidth = 2; ctx.stroke();
    ctx.fillStyle = '#fff'; ctx.font = 'bold 40px Segoe UI'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(fleche, b.x + b.w / 2, b.y + b.h / 2); ctx.textBaseline = 'alphabetic';
  }
  function dessinerCarte() {
    fondEspace();
    const nomMonde = MONDES[pageCarte] || ('Monde ' + (pageCarte + 1));
    ctx.textAlign = 'center'; ctx.fillStyle = '#fff'; ctx.font = 'bold 30px Segoe UI';
    ctx.fillText('Monde ' + (pageCarte + 1) + ' · ' + nomMonde, W / 2, 52);
    // total d'étoiles (la "monnaie" pour les Boss)
    ctx.textAlign = 'right'; ctx.fillStyle = '#ffd23f'; ctx.font = 'bold 24px Segoe UI';
    ctx.fillText('⭐ ' + totalEtoiles(), W - 22, 40);

    const nb = Math.min(PAR_PAGE, NIVEAUX.length - pageCarte * PAR_PAGE);

    // chemin pointillé entre les planètes de la page
    ctx.strokeStyle = 'rgba(255,255,255,.35)'; ctx.lineWidth = 4; ctx.setLineDash([3, 14]); ctx.lineCap = 'round';
    ctx.beginPath(); ctx.moveTo(SLOTS[0].x, SLOTS[0].y);
    for (let s = 1; s < nb; s++) ctx.lineTo(SLOTS[s].x, SLOTS[s].y);
    ctx.stroke(); ctx.setLineDash([]);

    for (let s = 0; s < nb; s++) {
      const gi = pageCarte * PAR_PAGE + s;
      const lv = NIVEAUX[gi], c = SLOTS[s];
      const ouvert = estOuvert(lv);

      if (lv.type === 'exam') {                 // la planète-BOSS
        ctx.beginPath(); ctx.arc(c.x, c.y, 52, 0, Math.PI * 2);
        ctx.fillStyle = ouvert ? '#b3203a' : '#3a1a22'; ctx.fill();
        ctx.lineWidth = 4; ctx.strokeStyle = ouvert ? '#ff8aa0' : '#5a2a36'; ctx.stroke();
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.font = '44px Segoe UI';
        ctx.fillText('👾', c.x, c.y + 2); ctx.textBaseline = 'alphabetic';
        ctx.fillStyle = ouvert ? '#ffd6de' : '#7a5560'; ctx.font = 'bold 18px Segoe UI';
        ctx.fillText(lv.final ? '🏆 BOSS FINAL' : 'BOSS', c.x, c.y + 80);
        ctx.fillStyle = ouvert ? '#3ddc84' : '#c98a98'; ctx.font = '15px Segoe UI';
        ctx.fillText(ouvert ? ('À battre · coûte ★' + lv.cout) : ('★ ' + totalEtoiles() + ' / ' + lv.cout), c.x, c.y + 100);
        continue;
      }

      ctx.beginPath(); ctx.arc(c.x, c.y, 46, 0, Math.PI * 2);
      ctx.fillStyle = ouvert ? COULEURS[gi % COULEURS.length].c : '#2a3252';
      ctx.fill();
      ctx.lineWidth = 4; ctx.strokeStyle = ouvert ? '#ffffff' : '#3a4366'; ctx.stroke();
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      if (ouvert) { ctx.fillStyle = '#10203a'; ctx.font = 'bold 40px Segoe UI'; ctx.fillText(lv.id, c.x, c.y + 2); }
      else { ctx.font = '34px Segoe UI'; ctx.fillText('🔒', c.x, c.y + 2); }
      ctx.textBaseline = 'alphabetic';
      ctx.fillStyle = ouvert ? '#dce6f5' : '#5a648a'; ctx.font = '16px Segoe UI';
      ctx.fillText(lv.nom, c.x, c.y + 70);
      const e = prog.etoiles[lv.id] || 0;
      for (let k = 0; k < 3; k++) etoile(c.x - 22 + k * 22, c.y - 64, 9, k < e);
    }

    // fusée sur la planète courante (si elle est sur cette page)
    if (pageCourante() === pageCarte) {
      const c = SLOTS[(Math.min(NIVEAUX.length, prog.max) - 1) % PAR_PAGE];
      ctx.font = '40px Segoe UI'; ctx.textAlign = 'center';
      ctx.fillText('🚀', c.x + 40, c.y - 30 - Math.abs(Math.sin(lastT / 250)) * 6);
    }

    // flèches entre les mondes
    if (pageCarte > 0) flecheMonde(btnPrev, '‹');
    if (pageCarte < NB_PAGES - 1) flecheMonde(btnNext, '›');

    // points indicateurs de monde
    for (let i = 0; i < NB_PAGES; i++) {
      ctx.beginPath(); ctx.arc(W / 2 - (NB_PAGES - 1) * 11 + i * 22, H - 38, 6, 0, Math.PI * 2);
      ctx.fillStyle = i === pageCarte ? '#ffd23f' : 'rgba(255,255,255,.3)'; ctx.fill();
    }

    boutonLeger(btnRetour, '‹ Accueil');
    dessinerBoutonBoss();
    dessinerVoix();
  }

  /* ====================== SCÈNE : NIVEAU ====================== */
  function demarrerNiveau(lv) {
    niv = lv; scene = 'niveau'; erreurs = 0; compte = 0; messageRetour = '';
    if (lv.type === 'exam') {
      examMode = true; examSteps = lv.steps.slice(); examIdx = 0; bossPV = examSteps.length;
      preparerEpreuve(examSteps[0]); sonBoss();
    } else {
      examMode = false;
      preparerEpreuve(lv);
    }
    parler(consigne);
  }

  // prépare une (sous-)épreuve : remplit bulles/cartes/figures/consigne selon le type
  function preparerEpreuve(ep) {
    bulles = []; cartes = []; figures = []; compte = 0; epreuveType = ep.type; epreuveStartT = lastT;
    if (ep.type === 'pop-all') {
      const specs = [];
      for (let i = 0; i < ep.n; i++) specs.push(COULEURS[rnd(COULEURS.length)]);
      poserBulles(specs);
      consigne = 'Touche toutes les bulles';
    } else if (ep.type === 'pop-color') {
      const cible = COULEURS[rnd(COULEURS.length)];
      cibleCol = cible.c;
      const specs = [];
      for (let i = 0; i < 3 + rnd(2); i++) specs.push(cible);
      const autres = COULEURS.filter(function (x) { return x !== cible; });
      for (let i = 0; i < 2 + rnd(2); i++) specs.push(autres[rnd(autres.length)]);
      poserBulles(melanger(specs));
      consigne = 'Touche les bulles ' + cible.nom;
    } else if (ep.type === 'count') {
      objA = 1 + rnd(ep.maxObj); objB = 0;
      poserCartes(objA);
      consigne = 'Combien de pommes ?';
    } else if (ep.type === 'add') {
      const m = ep.max || 6;
      objA = 1 + rnd(m - 1); objB = 1 + rnd(m - 1);
      if (objA + objB > m) objB = Math.max(1, m - objA);
      poserCartes(objA + objB);
      consigne = 'Combien de pommes en tout ?';
    } else if (ep.type === 'shape-touch') {
      const opts = melanger(FIGNAMES.slice());
      cibleForme = opts[rnd(opts.length)];
      poserFiguresRangee(opts);
      consigne = 'Touche le ' + cibleForme;
    } else if (ep.type === 'shape-sides') {
      const trio = melanger(['triangle', 'carré', 'pentagone']);
      cibleCotes = coteDe(trio[rnd(trio.length)]);
      poserFiguresRangee(trio);
      consigne = 'Touche la figure à ' + cibleCotes + ' côtés';
    } else if (ep.type === 'shape-count') {
      cibleForme = ep.forme || 'rond';
      cibleCompte = 1 + rnd(ep.maxObj);
      poserFiguresEparpillees(cibleForme, cibleCompte);
      poserCartes(cibleCompte);
      consigne = 'Combien de ' + pluriel(cibleForme) + ' ?';
    } else if (ep.type === 'sub') {
      const m = ep.max || 6;
      objA = 2 + rnd(m - 1);                 // total
      objB = 1 + rnd(objA - 1);              // ce qu'on enlève (< total)
      cibleCompte = objA - objB;             // ce qui reste
      poserCartes(cibleCompte);
      consigne = 'Combien en reste-t-il ?';
    } else if (ep.type === 'compare') {
      const m = ep.max || 6;
      let a = 1 + rnd(m), b = 1 + rnd(m);
      while (b === a) b = 1 + rnd(m);
      cibleCompte = Math.max(a, b);
      const pw = 280, ph = 280, gap = 70, y = 180;
      cartes = [
        { x: W / 2 - pw - gap / 2, y: y, w: pw, h: ph, val: a },
        { x: W / 2 + gap / 2, y: y, w: pw, h: ph, val: b }
      ];
      consigne = 'Touche le plus grand groupe';
    }
  }

  function poserFiguresRangee(noms) {
    figures = [];
    const n = noms.length, gap = Math.min(170, (W - 200) / Math.max(1, n - 1)), x0 = W / 2 - (n - 1) * gap / 2;
    for (let i = 0; i < n; i++) {
      figures.push({ forme: noms[i], x: x0 + i * gap, y: 300, taille: 48, hit: 72, couleur: COULEURS[i % COULEURS.length].c, faux: 0 });
    }
  }
  function poserFiguresEparpillees(forme, k) {
    figures = [];
    const autres = FIGNAMES.filter(function (f) { return f !== forme; });
    const liste = [];
    for (let i = 0; i < k; i++) liste.push(forme);
    const d = 2 + rnd(3);
    for (let i = 0; i < d; i++) liste.push(autres[rnd(autres.length)]);
    melanger(liste);
    const cols = 5, cw = 130, ch = 84, x0 = W / 2 - (cols - 1) * cw / 2;
    for (let i = 0; i < liste.length; i++) {
      const r = Math.floor(i / cols), c = i % cols;
      figures.push({ forme: liste[i], x: x0 + c * cw, y: 165 + r * ch, taille: 28, hit: 40, couleur: COULEURS[rnd(COULEURS.length)].c, faux: 0 });
    }
  }

  function nomEpreuve(type) {
    return type === 'pop-all' ? 'Compter'
      : type === 'pop-color' ? 'Les couleurs'
      : type === 'count' ? 'Reconnaître le nombre'
      : type === 'add' ? 'Additionner'
      : type === 'shape-touch' ? 'Les formes'
      : type === 'shape-sides' ? 'Les côtés'
      : type === 'shape-count' ? 'Compter les formes'
      : type === 'sub' ? 'Soustraire'
      : type === 'compare' ? 'Comparer'
      : 'Maths';
  }

  // une (sous-)épreuve est réussie
  function succesEpreuve() {
    if (!examMode) { reussiteNiveau(); return; }
    bossPV = Math.max(0, bossPV - 1); bossHitT = lastT; sonHit();   // le monstre encaisse un coup
    examIdx++;
    if (examIdx >= examSteps.length) { reussiteNiveau(); return; }  // tout fait → Boss battu
    sonOk();
    preparerEpreuve(examSteps[examIdx]);
    parler('Continue ! ' + consigne);
  }

  // une (sous-)épreuve est ratée
  function echecEpreuve() {
    erreurs++; sonNon();
    if (examMode) {
      if (erreurs >= 3) {
        // 3 échecs → on perd le coût du Boss (sans descendre sous 0), et on propose d'autres Boss
        prog.wallet = Math.max(0, (prog.wallet || 0) - (niv.cout || 0)); sauver();
        scene = 'propose'; gagneT = lastT;
        parler('Tu as raté trois fois et perdu des étoiles. Entraîne-toi sur d\'autres boss !');
      } else {
        // on refait l'épreuve ratée (essai restant)
        messageRetour = 'Raté ! Essai ' + erreurs + ' / 3 — recommence';
        messageT = lastT;
        parler('Raté, recommence !');
        preparerEpreuve(examSteps[examIdx]);
      }
    } else {
      parler('Essaie encore');
    }
  }

  function poserBulles(specs) {
    bulles = specs.map(function (col) {
      return {
        x: 110 + Math.random() * (W - 220), y: 150 + Math.random() * (H - 290),
        vx: (Math.random() - 0.5) * 3.4, vy: (Math.random() - 0.5) * 3.4,
        r: 46, col: col.c, pop: false, popT: 0, num: 0, shake: 0
      };
    });
    compte = 0;
  }
  function poserCartes(bon) {
    const set = new Set([bon]);
    while (set.size < 3) { const d = Math.max(1, bon + (rnd(5) - 2)); if (d !== bon) set.add(d); }
    const vals = melanger(Array.from(set));
    const bw = 150, bh = 150, gap = 44, sx = (W - (3 * bw + 2 * gap)) / 2;
    cartes = vals.map(function (v, i) { return { x: sx + i * (bw + gap), y: 360, w: bw, h: bh, val: v, faux: 0 }; });
  }

  function reussiteNiveau() {
    etoileGagnee = erreurs === 0 ? 3 : (erreurs <= 2 ? 2 : 1);
    if (niv.type === 'exam') {
      // bonus accordé UNE SEULE FOIS, et seulement si réussi du 1er coup (0 erreur)
      if (erreurs === 0 && !(prog.bossOk && prog.bossOk[niv.id])) {
        prog.wallet = (prog.wallet || 0) + (niv.cout || 0);
        prog.bossOk = prog.bossOk || {}; prog.bossOk[niv.id] = 1;
      }
    } else {
      prog.wallet = (prog.wallet || 0) + etoileGagnee;                            // revenu à chaque réussite
      prog.etoiles[niv.id] = Math.max(prog.etoiles[niv.id] || 0, etoileGagnee);   // meilleur score (affichage)
    }
    // battre un niveau/Boss débloque tout ce qui le précède (et le suivant)
    prog.max = Math.max(prog.max, Math.min(NIVEAUX.length, niv.id + 1));
    if (niv.final) { prog.fini = true; prog.max = NIVEAUX.length; }   // le Final débloque tout
    sauver();
    scene = 'gagne'; gagneT = lastT; sonFete();
    parler(niv.final ? 'Bravo ! Tu es le grand champion ! Tout est débloqué !'
      : niv.type === 'exam' ? 'Bravo ! Tu as vaincu le Boss !' : 'Bravo !');
  }

  function clicNiveau(p) {
    if (dans(p, btnRetour)) { allerCarte(); return; }
    if (dans(p, btnTestOk)) { reussiteNiveau(); return; }   // TEST
    if (dans(p, btnTestKo)) { echecEpreuve(); return; }     // TEST
    // réécouter la consigne (en touchant le texte du haut)
    if (p.y < 120 && p.x > 110 && p.x < W - 110) { parler(consigne); return; }

    if (epreuveType === 'count' || epreuveType === 'add' || epreuveType === 'shape-count' || epreuveType === 'sub' || epreuveType === 'compare') {
      const bon = (epreuveType === 'add') ? objA + objB : (epreuveType === 'count') ? objA : cibleCompte;
      for (const c of cartes) {
        if (dans(p, c)) {
          if (c.val === bon) { succesEpreuve(); }
          else { c.faux = lastT; echecEpreuve(); }
          return;
        }
      }
      return;
    }
    if (epreuveType === 'shape-touch' || epreuveType === 'shape-sides') {
      for (const f of figures) {
        const dx = p.x - f.x, dy = p.y - f.y;
        if (dx * dx + dy * dy < f.hit * f.hit) {
          const ok = (epreuveType === 'shape-touch') ? (f.forme === cibleForme) : (coteDe(f.forme) === cibleCotes);
          if (ok) { succesEpreuve(); }
          else { f.faux = lastT; echecEpreuve(); }
          return;
        }
      }
      return;
    }
    // bulles
    for (const b of bulles) {
      if (b.pop) continue;
      const dx = p.x - b.x, dy = p.y - b.y;
      if (dx * dx + dy * dy < b.r * b.r) {
        if (epreuveType === 'pop-color' && b.col !== cibleCol) { b.shake = lastT; echecEpreuve(); return; }
        b.pop = true; b.popT = lastT; compte++; b.num = compte; sonPop(compte);
        if (epreuveType === 'pop-all') { if (bulles.every(function (x) { return x.pop; })) succesEpreuve(); }
        else { if (!bulles.some(function (x) { return !x.pop && x.col === cibleCol; })) succesEpreuve(); }
        return;
      }
    }
  }

  function dessinerBossHUD(t) {
    const shake = (bossHitT && t - bossHitT < 300) ? Math.sin((t - bossHitT) / 20) * 5 : 0;
    // le monstre
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.font = '34px Segoe UI';
    ctx.fillText('👾', W / 2 - 150 + shake, 32);
    ctx.textBaseline = 'alphabetic';
    // barre de vie
    const totalPV = examSteps.length, bx = W / 2 - 110, by = 22, bw = 200, bh = 18;
    ctx.fillStyle = '#3a1a22'; roundRect(bx, by, bw, bh, 9); ctx.fill();
    ctx.fillStyle = '#ff4d6d'; roundRect(bx, by, bw * (bossPV / totalPV), bh, 9); ctx.fill();
    ctx.strokeStyle = '#ff8aa0'; ctx.lineWidth = 2; roundRect(bx, by, bw, bh, 9); ctx.stroke();
  }

  function dessinerNiveau(t) {
    fondEspace();
    // mode BOSS : chrono (défaite si écoulé) + barre de vie
    if (examMode) {
      dessinerBossHUD(t);
    } else {
      ctx.textAlign = 'center'; ctx.fillStyle = '#9fb3d1'; ctx.font = '18px Segoe UI';
      ctx.fillText('Niveau ' + niv.id + ' · ' + niv.nom, W / 2, 36);
    }
    ctx.fillStyle = '#fff'; ctx.font = 'bold 30px Segoe UI'; ctx.textAlign = 'center';
    ctx.fillText(consigne, W / 2, 78);
    ctx.fillStyle = '#9fb3d1'; ctx.font = '15px Segoe UI';
    ctx.fillText('(touche le texte pour réécouter)', W / 2, 102);

    // barre de progression de l'examen (une pastille par épreuve)
    if (examMode) {
      const n = examSteps.length, x0 = W / 2 - (n - 1) * 26;
      for (let i = 0; i < n; i++) {
        const x = x0 + i * 52;
        ctx.beginPath(); ctx.arc(x, 126, 15, 0, Math.PI * 2);
        ctx.fillStyle = i < examIdx ? '#3ddc84' : (i === examIdx ? '#ffd23f' : 'rgba(255,255,255,.18)');
        ctx.fill();
        ctx.fillStyle = '#10203a'; ctx.font = 'bold 16px Segoe UI'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(i < examIdx ? '✓' : (i + 1), x, 127); ctx.textBaseline = 'alphabetic';
      }
    }

    if (epreuveType === 'compare') {
      for (const c of cartes) {
        ctx.fillStyle = (c.faux && t - c.faux < 400) ? '#5a2230' : '#1c2740';
        roundRect(c.x, c.y, c.w, c.h, 18); ctx.fill();
        ctx.strokeStyle = '#3d5a80'; ctx.lineWidth = 3; ctx.stroke();
        for (let i = 0; i < c.val; i++) {
          const col = i % 4, r = Math.floor(i / 4);
          ctx.fillStyle = '#ffd23f'; ctx.beginPath();
          ctx.arc(c.x + 52 + col * 60, c.y + 55 + r * 56, 18, 0, Math.PI * 2); ctx.fill();
        }
      }
    } else if (epreuveType === 'count' || epreuveType === 'add' || epreuveType === 'shape-count' || epreuveType === 'sub') {
      if (epreuveType === 'shape-count') { for (const f of figures) dessinerForme(f.forme, f.x, f.y, f.taille, f.couleur); }
      else if (epreuveType === 'sub') dessinerSoustraction();
      else dessinerPommes();
      for (const c of cartes) {
        ctx.fillStyle = (c.faux && t - c.faux < 400) ? '#ff9aa2' : '#ffffff';
        ctx.strokeStyle = '#7c5cff'; ctx.lineWidth = 5;
        roundRect(c.x, c.y, c.w, c.h, 18); ctx.fill(); ctx.stroke();
        ctx.fillStyle = '#3a2f7a'; ctx.font = 'bold 84px Segoe UI'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(c.val, c.x + c.w / 2, c.y + c.h / 2 + 4); ctx.textBaseline = 'alphabetic';
      }
    } else if (epreuveType === 'shape-touch' || epreuveType === 'shape-sides') {
      for (const f of figures) dessinerForme(f.forme, f.x, f.y, f.taille, (f.faux && t - f.faux < 400) ? '#ff9aa2' : f.couleur);
    } else {
      for (const b of bulles) {
        if (b.pop) {
          const age = (t - b.popT) / 300;
          if (age < 1) {
            ctx.globalAlpha = 1 - age; ctx.strokeStyle = b.col; ctx.lineWidth = 6;
            ctx.beginPath(); ctx.arc(b.x, b.y, b.r + age * 26, 0, Math.PI * 2); ctx.stroke(); ctx.globalAlpha = 1;
            ctx.fillStyle = '#fff'; ctx.font = 'bold 40px Segoe UI'; ctx.textAlign = 'center';
            ctx.fillText(b.num, b.x, b.y - age * 30);
          }
          continue;
        }
        b.x += b.vx; b.y += b.vy;
        if (b.x < b.r + 12 || b.x > W - b.r - 12) b.vx *= -1;
        if (b.y < b.r + 120 || b.y > H - b.r - 30) b.vy *= -1;
        const wob = (b.shake && t - b.shake < 250) ? Math.sin((t - b.shake) / 18) * 5 : 0;
        ctx.fillStyle = b.col; ctx.beginPath(); ctx.arc(b.x + wob, b.y, b.r, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = 'rgba(255,255,255,.55)'; ctx.beginPath(); ctx.arc(b.x + wob - b.r * 0.32, b.y - b.r * 0.32, b.r * 0.26, 0, Math.PI * 2); ctx.fill();
      }
      ctx.textAlign = 'left'; ctx.fillStyle = '#fff'; ctx.font = 'bold 30px Segoe UI';
      ctx.fillText('Compté : ' + compte, 90, 160);
    }

    // message "Recommence : ..." après une erreur pendant l'examen
    if (messageRetour && t - messageT < 1800) {
      ctx.fillStyle = 'rgba(224,36,94,.92)'; roundRect(W / 2 - 240, 298, 480, 60, 14); ctx.fill();
      ctx.fillStyle = '#fff'; ctx.font = 'bold 26px Segoe UI'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(messageRetour, W / 2, 329); ctx.textBaseline = 'alphabetic';
    }

    boutonLeger(btnRetour, '‹ Carte');
    bouton(btnTestOk, '✅ Réussir', '#2ecc71', 18);
    bouton(btnTestKo, '❌ Rater', '#e74c3c', 18);
    dessinerVoix();
  }

  function dessinerPommes() {
    const aw = 56, y = 215;
    const plusW = objB > 0 ? 64 : 0;
    const totalW = (objA + objB) * aw + plusW;
    let x = (W - totalW) / 2;
    for (let i = 0; i < objA; i++) { pomme(x + aw / 2, y); x += aw; }
    if (objB > 0) {
      ctx.fillStyle = '#fff'; ctx.font = 'bold 54px Segoe UI'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText('+', x + plusW / 2, y); ctx.textBaseline = 'alphabetic'; x += plusW;
      for (let i = 0; i < objB; i++) { pomme(x + aw / 2, y); x += aw; }
    }
  }
  function pomme(x, y) {
    ctx.fillStyle = '#3ddc84'; ctx.fillRect(x - 3, y - 34, 6, 12);
    ctx.fillStyle = '#ff5c6c'; ctx.beginPath(); ctx.arc(x, y, 24, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,.45)'; ctx.beginPath(); ctx.arc(x - 8, y - 8, 7, 0, Math.PI * 2); ctx.fill();
  }
  function dotsGroupe(cx, n, hi) {
    for (let i = 0; i < n; i++) {
      const col = i % 3, r = Math.floor(i / 3);
      ctx.fillStyle = hi ? '#3ddc84' : '#ffd23f';
      ctx.beginPath(); ctx.arc(cx - 60 + col * 60, 230 + r * 60, 20, 0, Math.PI * 2); ctx.fill();
    }
  }
  function dessinerSoustraction() {
    const aw = 60, y = 175;
    const totalW = objA * aw;
    let x = (W - totalW) / 2;
    for (let i = 0; i < objA; i++) {
      pomme(x + aw / 2, y);
      if (i >= objA - objB) {   // pommes enlevées : croix rouge
        ctx.strokeStyle = '#ff3b3b'; ctx.lineWidth = 5; ctx.lineCap = 'round';
        const cx = x + aw / 2;
        ctx.beginPath(); ctx.moveTo(cx - 20, y - 20); ctx.lineTo(cx + 20, y + 20);
        ctx.moveTo(cx + 20, y - 20); ctx.lineTo(cx - 20, y + 20); ctx.stroke();
      }
      x += aw;
    }
  }

  function polygone(x, y, r, n, rot) {
    ctx.beginPath();
    for (let i = 0; i < n; i++) {
      const a = (rot === undefined ? -Math.PI / 2 : rot) + i * 2 * Math.PI / n;
      const px = x + Math.cos(a) * r, py = y + Math.sin(a) * r;
      if (i === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
    }
    ctx.closePath();
  }
  function dessinerForme(forme, x, y, s, couleur) {
    ctx.fillStyle = couleur; ctx.strokeStyle = 'rgba(0,0,0,.20)'; ctx.lineWidth = 3;
    if (forme === 'rond') { ctx.beginPath(); ctx.arc(x, y, s, 0, Math.PI * 2); ctx.fill(); ctx.stroke(); }
    else if (forme === 'carré') { roundRect(x - s, y - s, s * 2, s * 2, 6); ctx.fill(); ctx.stroke(); }
    else if (forme === 'rectangle') { roundRect(x - s * 1.3, y - s * 0.72, s * 2.6, s * 1.44, 6); ctx.fill(); ctx.stroke(); }
    else if (forme === 'triangle') { polygone(x, y + s * 0.18, s * 1.15, 3); ctx.fill(); ctx.stroke(); }
    else if (forme === 'pentagone') { polygone(x, y, s * 1.1, 5); ctx.fill(); ctx.stroke(); }
  }

  /* ====================== SCÈNE : VICTOIRE ====================== */
  const btnContinuer = { x: W / 2 - 150, y: 380, w: 300, h: 76 };
  const btnRejouer = { x: W / 2 - 110, y: 478, w: 220, h: 50 };

  function clicGagne(p) {
    if (dans(p, btnContinuer)) { allerCarte(); parler('Choisis une planète'); return; }
    if (dans(p, btnRejouer)) { demarrerNiveau(niv); return; }
  }
  function dessinerGagne(t) {
    fondEspace();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#ffd23f'; ctx.font = 'bold 64px Segoe UI'; ctx.fillText('BRAVO ! 🎉', W / 2, 150);
    // les étoiles gagnées
    const pop = Math.min(1, (t - gagneT) / 600);
    for (let s = 0; s < 3; s++) {
      const r = 36 * (s < etoileGagnee ? (0.6 + 0.4 * pop) : 1);
      etoile(W / 2 - 100 + s * 100, 260, r, s < etoileGagnee);
    }
    ctx.fillStyle = '#dce6f5'; ctx.font = '24px Segoe UI';
    const fini = niv.final ? '🏆 Champion ! Tout est débloqué !'
      : (niv.type === 'exam') ? '👾 Boss vaincu ! Tu peux continuer'
      : (prog.max > niv.id) ? 'Une nouvelle planète est débloquée !'
      : 'Tu as fini cette planète !';
    ctx.fillText(fini, W / 2, 335);

    bouton(btnContinuer, '▶ Continuer', '#7c5cff', 32);
    boutonLeger(btnRejouer, '↺ Rejouer');
    dessinerBoutonBoss();
    dessinerVoix();
  }

  /* ====================== SCÈNE : DÉFAITE (Boss) ====================== */
  const btnRetourCarte = { x: W / 2 - 160, y: 440, w: 320, h: 76 };
  function clicPerdu(p) { if (dans(p, btnRetourCarte)) allerCarte(); }
  function dessinerPerdu() {
    fondEspace();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#ff5c6c'; ctx.font = 'bold 60px Segoe UI'; ctx.fillText('RATÉ ! 😣', W / 2, 140);
    ctx.fillStyle = '#fff'; ctx.font = 'bold 30px Segoe UI';
    ctx.fillText('Épreuve manquée : ' + nomEpreuve(domaineRate), W / 2, 215);
    ctx.fillStyle = '#ffd23f'; ctx.font = 'bold 34px Segoe UI';
    ctx.fillText('− 3 ⭐ en « ' + nomEpreuve(domaineRate) + ' »', W / 2, 280);
    ctx.fillStyle = '#dce6f5'; ctx.font = '22px Segoe UI';
    ctx.fillText("Réentraîne-toi pour récupérer tes étoiles,", W / 2, 345);
    ctx.fillText("puis reviens affronter le Boss !", W / 2, 378);
    bouton(btnRetourCarte, '‹ Retour à la carte', '#7c5cff', 26);
    dessinerBoutonBoss();
    dessinerVoix();
  }

  /* ============ SCÈNE : PROPOSITION (après 3 échecs d'un Boss) ============ */
  function clicPropose(p) {
    for (const b of proposeBtns) {
      if (dans(p, b)) { if (b.carte) allerCarte(); else demarrerNiveau(b.lv); return; }
    }
  }
  function dessinerPropose() {
    fondEspace();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#ffd23f'; ctx.font = 'bold 50px Segoe UI'; ctx.fillText('Pas encore ! 💪', W / 2, 96);
    ctx.fillStyle = '#dce6f5'; ctx.font = '22px Segoe UI';
    ctx.fillText("Tu as raté 3 fois. Entraîne-toi sur d'autres Boss intermédiaires :", W / 2, 140);

    proposeBtns = [];
    const bw = 440, bh = 58, gap = 14, y0 = 178;
    for (let i = 0; i < BOSS_INTER.length; i++) {
      const b = { x: W / 2 - bw / 2, y: y0 + i * (bh + gap), w: bw, h: bh, lv: BOSS_INTER[i] };
      proposeBtns.push(b);
      bouton(b, '👾 Boss intermédiaire ' + (i + 1), '#b3203a', 24);
    }
    const c = { x: W / 2 - 150, y: y0 + BOSS_INTER.length * (bh + gap) + 8, w: 300, h: 52, carte: true };
    proposeBtns.push(c);
    boutonLeger(c, '‹ Retour à la carte');

    dessinerBoutonBoss();   // le masque, pour réessayer le Boss Final
    dessinerVoix();
  }

  /* ============ LEÇONS "DÉCOUVERTE" ============ */
  function clicLecons(p) {
    for (const b of leconsBtns) {
      if (dans(p, b)) { if (b.retour) scene = 'titre'; else { lecExercice = null; demarrerLecon(b.id); } return; }
    }
  }
  function dessinerLecons() {
    fondEspace();
    ctx.textAlign = 'center';
    ctx.fillStyle = '#fff'; ctx.font = 'bold 38px Segoe UI'; ctx.fillText('📘 Apprendre', W / 2, 92);
    ctx.fillStyle = '#9fb3d1'; ctx.font = '20px Segoe UI'; ctx.fillText('Touche une leçon pour découvrir :', W / 2, 132);
    leconsBtns = [];
    const bw = 470, bh = 42, gap = 8, y0 = 128;
    for (let i = 0; i < LECONS.length; i++) {
      const b = { x: W / 2 - bw / 2, y: y0 + i * (bh + gap), w: bw, h: bh, id: LECONS[i].id };
      leconsBtns.push(b);
      bouton(b, LECONS[i].titre, '#3a7bd5', 21);
    }
    const r = { x: W / 2 - 150, y: y0 + LECONS.length * (bh + gap) + 6, w: 300, h: 44, retour: true };
    leconsBtns.push(r);
    boutonLeger(r, '‹ Accueil');
    dessinerVoix();
  }

  // ouvre l'exercice : d'abord son cours dédié (sauf Boss), puis le jeu
  function ouvrirExercice(lv) {
    if (lv.type === 'exam' || !LECON_DE[lv.type]) { demarrerNiveau(lv); return; }
    lecExercice = lv;
    demarrerLecon(LECON_DE[lv.type]);
  }

  const LCX = 450, LCY = 340, LCH = 130;   // carré de référence des leçons
  function demarrerLecon(id) {
    lecon = id; scene = 'lecon'; lecN = 0; lecMsg = ''; lecParts = [];
    lecDone = false; lecIdx = LECONS.findIndex(function (l) { return l.id === id; });
    if (id === 'cote') {
      lecConsigne = 'Touche tous les côtés du carré';
      lecParts = [
        { kind: 'side', x: LCX - LCH, y: LCY - LCH - 22, w: 2 * LCH, hh: 44, touched: false },
        { kind: 'side', x: LCX + LCH - 22, y: LCY - LCH, w: 44, hh: 2 * LCH, touched: false },
        { kind: 'side', x: LCX - LCH, y: LCY + LCH - 22, w: 2 * LCH, hh: 44, touched: false },
        { kind: 'side', x: LCX - LCH - 22, y: LCY - LCH, w: 44, hh: 2 * LCH, touched: false }
      ];
      parler("Un côté, c'est un trait droit. Touche tous les côtés du carré !");
    } else if (id === 'coin') {
      lecConsigne = 'Touche tous les coins du carré';
      lecParts = [
        { kind: 'coin', x: LCX - LCH, y: LCY - LCH, touched: false },
        { kind: 'coin', x: LCX + LCH, y: LCY - LCH, touched: false },
        { kind: 'coin', x: LCX + LCH, y: LCY + LCH, touched: false },
        { kind: 'coin', x: LCX - LCH, y: LCY + LCH, touched: false }
      ];
      parler("Un coin, c'est là où deux côtés se rencontrent. Touche tous les coins !");
    } else if (id === 'formes') {
      lecConsigne = 'Touche une forme pour entendre son nom';
      lecParts = [
        { kind: 'forme', forme: 'rond', x: 230, y: 340, flash: 0 },
        { kind: 'forme', forme: 'carré', x: 450, y: 340, flash: 0 },
        { kind: 'forme', forme: 'triangle', x: 670, y: 340, flash: 0 }
      ];
      parler("Touche une forme, je te dis son nom !");
    } else if (id === 'couleurs') {
      lecConsigne = 'Touche une couleur pour entendre son nom';
      const sing = { rouges: 'rouge', jaunes: 'jaune', vertes: 'vert', bleues: 'bleu' };
      const xs = [200, 380, 560, 740];
      lecParts = COULEURS.map(function (co, i) { return { kind: 'couleur', x: xs[i], y: 330, col: co.c, nomSing: sing[co.nom] || co.nom, touched: false, flash: 0 }; });
      parler('Touche une couleur, je te dis laquelle !');
    } else if (id === 'addition') {
      lecConsigne = "Touche l'écran pour voir l'addition";
      parler('Deux pommes, plus une pomme. Touche pour voir le résultat.');
    } else if (id === 'soustraction') {
      lecConsigne = "Touche l'écran pour voir la soustraction";
      parler('Trois pommes. On en enlève une. Touche pour voir combien il reste.');
    } else if (id === 'comparer') {
      lecConsigne = "Touche l'écran : qui en a le plus ?";
      parler('À gauche cinq, à droite trois. Touche pour voir qui en a le plus.');
    } else { // compter
      lecConsigne = "Touche l'écran pour ajouter des pommes et compter";
      parler("Touche l'écran, on compte ensemble !");
    }
  }
  function lancerDepuisCours() { const lv = lecExercice; lecExercice = null; demarrerNiveau(lv); }
  function clicLecon(p) {
    if (dans(p, btnRetourLecon)) { if (lecExercice) { lecExercice = null; allerCarte(); } else scene = 'lecons'; return; }
    // bouton "Passer" : aller directement à l'exercice (uniquement en mode cours-d'exercice)
    if (lecExercice && dans(p, btnPasser)) { lancerDepuisCours(); return; }
    if (lecDone && dans(p, btnNextLecon)) {
      if (lecExercice) { lancerDepuisCours(); }              // cours réussi → on fait l'exercice
      else if (lecIdx < LECONS.length - 1) demarrerLecon(LECONS[lecIdx + 1].id);
      else { allerCarte(); parler('Bravo ! Maintenant on passe aux exercices !'); }
      return;
    }
    if (lecon === 'couleurs') {
      for (const e of lecParts) {
        if ((p.x - e.x) * (p.x - e.x) + (p.y - e.y) * (p.y - e.y) < 56 * 56) {
          e.flash = lastT; e.touched = true; parler('La couleur ' + e.nomSing);
          if (lecParts.every(function (x) { return x.touched; })) { lecDone = true; lecMsg = 'Tu connais les couleurs !'; }
          return;
        }
      }
      return;
    }
    if (lecon === 'addition') {
      if (!lecDone) { lecDone = true; lecMsg = '2 + 1 = 3'; parler('Deux plus un égale trois !'); }
      return;
    }
    if (lecon === 'soustraction') {
      if (!lecDone) { lecDone = true; lecMsg = '3 − 1 = 2'; parler('Trois moins un égale deux !'); }
      return;
    }
    if (lecon === 'comparer') {
      if (!lecDone) { lecDone = true; lecMsg = '5 est plus grand que 3'; parler("Cinq, c'est plus grand que trois !"); }
      return;
    }
    if (lecon === 'cote' || lecon === 'coin') {
      for (const e of lecParts) {
        if (e.touched) continue;
        const hit = (e.kind === 'side')
          ? (p.x > e.x && p.x < e.x + e.w && p.y > e.y && p.y < e.y + e.hh)
          : ((p.x - e.x) * (p.x - e.x) + (p.y - e.y) * (p.y - e.y) < 34 * 34);
        if (hit) {
          e.touched = true; lecN++; sonPop(lecN); parler(String(lecN));
          if (lecN >= 4) { lecDone = true; lecMsg = (lecon === 'cote') ? 'Un carré a 4 côtés !' : 'Un carré a 4 coins !'; parler('Bravo ! ' + lecMsg); }
          return;
        }
      }
      return;
    }
    if (lecon === 'formes') {
      for (const e of lecParts) {
        if ((p.x - e.x) * (p.x - e.x) + (p.y - e.y) * (p.y - e.y) < 66 * 66) {
          e.flash = lastT; e.touched = true; parler("C'est un " + e.forme);
          if (lecParts.every(function (x) { return x.touched; })) { lecDone = true; lecMsg = 'Tu connais les formes !'; }
          return;
        }
      }
      return;
    }
    if (lecon === 'compter') {
      if (lecN < 10) { lecN++; sonPop(lecN); parler(String(lecN)); }
      if (lecN >= 5 && !lecDone) { lecDone = true; lecMsg = 'Tu sais compter !'; }
      return;
    }
  }
  function dessinerLecon(t) {
    fondEspace();
    ctx.textAlign = 'center'; ctx.fillStyle = '#fff'; ctx.font = 'bold 26px Segoe UI';
    ctx.fillText(lecConsigne, W / 2, 58);

    if (lecon === 'cote') {
      const s = [[LCX - LCH, LCY - LCH, LCX + LCH, LCY - LCH], [LCX + LCH, LCY - LCH, LCX + LCH, LCY + LCH],
                 [LCX + LCH, LCY + LCH, LCX - LCH, LCY + LCH], [LCX - LCH, LCY + LCH, LCX - LCH, LCY - LCH]];
      ctx.lineWidth = 14; ctx.lineCap = 'round';
      for (let i = 0; i < 4; i++) {
        ctx.strokeStyle = lecParts[i].touched ? '#3ddc84' : '#ffffff';
        ctx.beginPath(); ctx.moveTo(s[i][0], s[i][1]); ctx.lineTo(s[i][2], s[i][3]); ctx.stroke();
      }
      ctx.fillStyle = '#ffd23f'; ctx.font = 'bold 42px Segoe UI'; ctx.fillText(lecN + (lecN > 1 ? ' côtés' : ' côté'), LCX, LCY + 12);
    } else if (lecon === 'coin') {
      ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 8; ctx.strokeRect(LCX - LCH, LCY - LCH, 2 * LCH, 2 * LCH);
      for (const e of lecParts) { ctx.fillStyle = e.touched ? '#3ddc84' : '#ff5c6c'; ctx.beginPath(); ctx.arc(e.x, e.y, 20, 0, Math.PI * 2); ctx.fill(); }
      ctx.fillStyle = '#ffd23f'; ctx.font = 'bold 42px Segoe UI'; ctx.fillText(lecN + (lecN > 1 ? ' coins' : ' coin'), LCX, LCY + 12);
    } else if (lecon === 'formes') {
      const noms = { rond: 'rond', carré: 'carré', triangle: 'triangle' };
      for (const e of lecParts) {
        dessinerForme(e.forme, e.x, e.y, 58, (e.flash && t - e.flash < 600) ? '#ffd23f' : '#4aa3ff');
        ctx.fillStyle = '#dce6f5'; ctx.font = '22px Segoe UI'; ctx.textAlign = 'center'; ctx.fillText(noms[e.forme], e.x, e.y + 96);
      }
    } else if (lecon === 'couleurs') {
      for (const e of lecParts) {
        ctx.fillStyle = e.col; ctx.beginPath(); ctx.arc(e.x, e.y, (e.flash && t - e.flash < 500) ? 56 : 48, 0, Math.PI * 2); ctx.fill();
        if (e.touched) { ctx.fillStyle = '#dce6f5'; ctx.font = '20px Segoe UI'; ctx.textAlign = 'center'; ctx.fillText(e.nomSing, e.x, e.y + 80); }
      }
    } else if (lecon === 'addition') {
      pomme(250, 300); pomme(310, 300);
      ctx.fillStyle = '#fff'; ctx.font = 'bold 54px Segoe UI'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText('+', 400, 300); pomme(480, 300);
      ctx.fillText('=', 570, 300);
      ctx.fillStyle = '#ffd23f'; ctx.fillText(lecDone ? '3' : '?', 660, 300); ctx.textBaseline = 'alphabetic';
    } else if (lecon === 'soustraction') {
      const y = 300; pomme(320, y); pomme(410, y); pomme(500, y);
      ctx.strokeStyle = '#ff3b3b'; ctx.lineWidth = 6; ctx.lineCap = 'round';
      ctx.beginPath(); ctx.moveTo(500 - 22, y - 22); ctx.lineTo(500 + 22, y + 22); ctx.moveTo(500 + 22, y - 22); ctx.lineTo(500 - 22, y + 22); ctx.stroke();
      ctx.fillStyle = '#fff'; ctx.font = 'bold 50px Segoe UI'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText('=', 580, y);
      ctx.fillStyle = '#ffd23f'; ctx.fillText(lecDone ? '2' : '?', 660, y); ctx.textBaseline = 'alphabetic';
    } else if (lecon === 'comparer') {
      dotsGroupe(280, 5, lecDone); dotsGroupe(620, 3, false);
      ctx.fillStyle = '#fff'; ctx.font = 'bold 50px Segoe UI'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(lecDone ? '>' : '?', W / 2, 300); ctx.textBaseline = 'alphabetic';
    } else { // compter
      ctx.fillStyle = '#ffd23f'; ctx.font = 'bold 60px Segoe UI'; ctx.textAlign = 'center'; ctx.fillText(lecN, LCX, 150);
      for (let i = 0; i < lecN; i++) pomme(150 + (i % 5) * 145, 250 + Math.floor(i / 5) * 120);
    }

    if (lecMsg) { ctx.fillStyle = '#3ddc84'; ctx.font = 'bold 28px Segoe UI'; ctx.textAlign = 'center'; ctx.fillText('Bravo ! ' + lecMsg, W / 2, 498); }
    if (lecDone) {
      let txt, col;
      if (lecExercice) { txt = "▶ Faire l'exercice"; col = '#2ecc71'; }
      else if (lecIdx >= LECONS.length - 1) { txt = '▶ Aller aux exercices'; col = '#2ecc71'; }
      else { txt = 'Leçon suivante →'; col = '#3a7bd5'; }
      bouton(btnNextLecon, txt, col, 24);
    }
    boutonLeger(btnRetourLecon, lecExercice ? '‹ Carte' : '‹ Leçons');
    if (lecExercice) bouton(btnPasser, 'Passer ▶', '#e67e22', 20);
    dessinerVoix();
  }

  /* ---------- composants d'interface ---------- */
  function bouton(b, txt, couleur, taille) {
    ctx.fillStyle = couleur || '#7c5cff';
    roundRect(b.x, b.y, b.w, b.h, 16); ctx.fill();
    ctx.fillStyle = '#fff'; ctx.font = 'bold ' + (taille || 30) + 'px Segoe UI';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(txt, b.x + b.w / 2, b.y + b.h / 2); ctx.textBaseline = 'alphabetic';
  }
  function boutonLeger(b, txt) {
    ctx.fillStyle = 'rgba(255,255,255,.08)';
    roundRect(b.x, b.y, b.w, b.h, 12); ctx.fill();
    ctx.strokeStyle = 'rgba(255,255,255,.25)'; ctx.lineWidth = 2; ctx.stroke();
    ctx.fillStyle = '#cdd8ec'; ctx.font = 'bold 19px Segoe UI';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(txt, b.x + b.w / 2, b.y + b.h / 2); ctx.textBaseline = 'alphabetic';
  }
  function dessinerVoix() {
    ctx.textAlign = 'left'; ctx.textBaseline = 'alphabetic'; ctx.font = '30px Segoe UI';
    ctx.fillText(voixOn ? '🔊' : '🔇', btnVoix.x, btnVoix.y + 38);
    ctx.fillText(sonOn ? '🎵' : '🔕', btnSon.x, btnSon.y + 38);
  }

  /* ---------- boucle d'animation ---------- */
  function boucle(t) {
    lastT = t;
    if (scene === 'titre') dessinerTitre();
    else if (scene === 'carte') dessinerCarte();
    else if (scene === 'niveau') dessinerNiveau(t);
    else if (scene === 'gagne') dessinerGagne(t);
    else if (scene === 'perdu') dessinerPerdu();
    else if (scene === 'propose') dessinerPropose();
    else if (scene === 'lecons') dessinerLecons();
    else if (scene === 'lecon') dessinerLecon(t);
    requestAnimationFrame(boucle);
  }
  requestAnimationFrame(boucle);

})();
