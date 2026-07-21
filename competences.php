<?php
/* ═══════════════════════════════════════════════════════════════════════
   FICHE DE COMPÉTENCES + PORTFOLIO — Luvumbu, Développeur Full-Stack
   Page autonome (HTML/CSS/JS inline). URL : /competences.php
   Source du contenu : CARNET_DE_COMPETENCES.txt + config/projets_meta.json
   ═══════════════════════════════════════════════════════════════════════ */
declare(strict_types=1);

$IDENTITE = [
  'nom'    => 'Luvumbu',
  'titre'  => 'Développeur Full-Stack',
  'sous'   => 'PHP · JavaScript · MySQL — de l\'idée à la production',
  'pitch'  => "Je conçois, développe et déploie des produits web & mobiles de bout en bout : "
            . "interfaces, API REST, bases de données, authentification, sécurité et mise en ligne. "
            . "Mon écosystème BOKONZI, en production, réunit un site, une application mobile, un espace "
            . "professionnel et une API publique alimentés par une seule source de données.",
];

$STATS = [
  ['12', 'projets réalisés'],
  ['5+', 'applications en production'],
  ['×4', 'projets avec OAuth Google'],
  ['Web · Mobile · API', 'du front au déploiement'],
];

/* Compétences transversales (synthèse du carnet) */
$SKILLS = [
  ['🧩', 'Langages & technologies', ['PHP','JavaScript','SQL / MySQL','HTML5 / CSS3','JSON','HTML5 Canvas','Arduino (C/C++)']],
  ['⚙️', 'Back-end & API',          ['API REST (JSON · CORS · cache · clé)','Architecture MVC','Architecture en couches','PDO · migrations · seed','Autoloader & routeur « maison »']],
  ['📱', 'Front-end & mobile',      ['Interfaces web dynamiques','Éditeurs WYSIWYG','Android (natif + Capacitor)','PWA installable & hors ligne']],
  ['🔒', 'Authentification & sécurité', ['OAuth 2.0 / « Google Sign-In »','Anti-bruteforce','Cookies durcis (HttpOnly/SameSite/Secure)','Prévention XSS','Uploads sécurisés (whitelist · realpath)','Secrets hors dépôt Git']],
  ['🔌', 'Intégrations & services', ['Paiement en ligne','E-mails transactionnels','Transfert de fichiers volumineux','Génération PDF','QR codes']],
  ['🚀', 'DevOps & déploiement',    ['Mise en production (Hostinger)','Noms de domaine','Assistants d\'installation web','Environnements local / prod','Git · config · secrets séparés']],
  ['🎯', 'Méthode & posture',       ['Autonomie : idée → production','Composants réutilisables','Documentation (README · archi)','UX utilisateur ET admin non-technicien']],
];

/* Projets réalisés (le 1er = phare) */
$PROJECTS = [
  [
    'flag'=>true,'emoji'=>'🏟️','name'=>'BOKONZI','sub'=>'Plateforme data (athlétisme) · en production',
    'cats'=>['Web','Mobile','API'],
    'context'=>"Écosystème complet de données sur l'athlétisme : une seule API REST alimente un site web public, une application mobile Android et un espace professionnel B2B.",
    'role'=>"Conception, développement et déploiement de A à Z (produit + infrastructure).",
    'stack'=>['PHP','MySQL','JavaScript','API REST','Capacitor','CORS','Paiement en ligne'],
    'skills'=>["API REST : 20+ endpoints JSON, cache 24 h, CORS","Architecture multi-surfaces (web · mobile · B2B) sur une source unique","Recherche multi-critères & fiches auto-générées","Data-visualisation (indicateurs croisés)","Espace B2B : tableau de bord, effectifs, export CSV","Intégration d'un paiement en ligne"],
    'result'=>"Site public, application mobile, espace pro et API publique interconnectés, en ligne.",
    'url'=>'https://bokonzi.com','urlLabel'=>'bokonzi.com',
  ],
  [
    'emoji'=>'🗺️','name'=>'Portfolio « Luvumbu Land »','sub'=>'Site vitrine ludique + back-office',
    'cats'=>['Web'],
    'context'=>"Portfolio qui transforme les dossiers-projets en un « monde de jeu » rétro (façon Mario/Sonic), doublé d'un back-office pour tout piloter sans toucher au code.",
    'role'=>"Conception, développement et administration.",
    'stack'=>['PHP','JavaScript','JSON','Upload d\'images','iframe / postMessage'],
    'skills'=>["Back-office : apparence du site + éditeur d'habillage par projet","Configuration en cascade (défauts + surcharges JSON, fusion intelligente)","Upload d'images sécurisé (whitelist, taille max, realpath)","Authentification admin multi-méthodes"],
    'result'=>"Portfolio en ligne, entièrement administrable via une interface dédiée.",
    'url'=>'https://luvumbu.com','urlLabel'=>'luvumbu.com',
  ],
  [
    'emoji'=>'📄','name'=>'CV Luvumbu','sub'=>'Création & suivi de CV',
    'cats'=>['Web','API'],
    'context'=>"Application web pour créer, mettre en forme, partager et suivre des CV, avec suivi des candidatures.",
    'role'=>"Développement full-stack et déploiement.",
    'stack'=>['PHP','MySQL','JavaScript','OAuth Google','Génération PDF','QR codes'],
    'skills'=>["Éditeur visuel WYSIWYG & modèles multiples","Rendu imprimable / export PDF","Partage public avec QR code","Connexion Google (OAuth 2.0) configurable depuis l'admin","Secrets stockés hors du dépôt Git"],
    'result'=>"Application fonctionnelle, connexion Google opérationnelle en production.",
    'url'=>'https://luvumbu.com/cv_luvumbu/','urlLabel'=>'luvumbu.com/cv_luvumbu',
  ],
  [
    'emoji'=>'🥚','name'=>'Tamagotchi','sub'=>'Jeu + démonstration d\'architecture',
    'cats'=>['API','Mobile','Jeu'],
    'context'=>"Jeu de créature virtuelle servant de démonstration d'une architecture back-end propre et évolutive.",
    'role'=>"Conception de l'architecture et développement.",
    'stack'=>['PHP (API REST)','JavaScript','MySQL','Android','PDO','Routeur maison'],
    'skills'=>["Architecture en couches (Core / Models / Repositories / Services / Controllers)","Noyau PHP « maison » : autoloader, routeur, PDO, JSON normalisé","Seule la couche public/ exposée (bonne pratique de sécurité)","Migrations & données de départ (seed) versionnées","Gameplay externalisé en configuration"],
    'result'=>"Base technique propre et documentée (docs/ARCHITECTURE.md), extensible.",
    'url'=>'https://luvumbu.com/tamagotchi/public/','urlLabel'=>'luvumbu.com/tamagotchi',
  ],
  [
    'emoji'=>'🌐','name'=>'RPN','sub'=>'Plateforme communautaire (MVC)',
    'cats'=>['Web','API'],
    'context'=>"Plateforme communautaire pour membres (articles, quiz), avec espace d'administration complet et ouverture aux services tiers via API.",
    'role'=>"Développement full-stack, architecture et sécurisation.",
    'stack'=>['PHP','MySQL','JavaScript','MVC','OAuth Google','API JSON par clé'],
    'skills'=>["Architecture MVC (responsabilités séparées, code maintenable)","Connexion Google pour les membres","Espace administrateur complet","API JSON authentifiée par clé (articles & quiz à distance), documentée","Sécurité anti-bruteforce, thèmes personnalisables"],
    'result'=>"Plateforme en ligne, documentée, ouverte aux intégrations externes.",
    'url'=>'https://bokonzi.com/rpn/','urlLabel'=>'bokonzi.com/rpn',
  ],
  [
    'emoji'=>'🎬','name'=>'Anniversaire','sub'=>'Compte à rebours partagé (PWA)',
    'cats'=>['Web','PWA'],
    'context'=>"Comptes à rebours d'anniversaires avec espaces personnels protégés, partage entre utilisateurs et installation sur mobile.",
    'role'=>"Développement full-stack, sécurisation et packaging PWA.",
    'stack'=>['PHP','MySQL','JavaScript','OAuth Google','PWA','cURL'],
    'skills'=>["Double authentification : mot de passe OU compte Google","Partage d'espaces (voir + modifier, invitations)","PWA installable Android/iOS, hors ligne","Assistant d'installation forcé (saisie & test des identifiants MySQL, création des tables)","Cookies durcis, anti-XSS, pas de fuite d'erreurs DB"],
    'result'=>"Application déployée, partageable et installable, installation autonome pour non-technicien.",
    'url'=>'https://luvumbu.com/anniversaire','urlLabel'=>'luvumbu.com/anniversaire',
  ],
  [
    'emoji'=>'📷','name'=>'DualCam','sub'=>'Backend photo indépendant',
    'cats'=>['Web','API'],
    'context'=>"Service de capture/partage photo doté de son propre backend totalement autonome (base, stockage et code dédiés).",
    'role'=>"Conception du backend et de son installateur.",
    'stack'=>['PHP','MySQL','API','OAuth Google','Installateur web'],
    'skills'=>["Service isolé et réutilisable (base + uploads + code dédiés)","Assistant d'installation web (config + tables + dossier d'upload auto)","Préfixage des tables (dualcam_) pour cohabitation propre","Documentation de déploiement pour un tiers"],
    'result'=>"Backend déployable de façon indépendante, avec installation guidée.",
    'url'=>'https://luvumbu.com/DualCam/','urlLabel'=>'luvumbu.com/DualCam',
  ],
  [
    'emoji'=>'📸','name'=>'PhotoSync','sub'=>'Synchro photos Android → serveur',
    'cats'=>['Mobile','API'],
    'context'=>"Sauvegarde automatique des médias d'un téléphone vers un serveur personnel, chacun ne voyant que ses propres photos.",
    'role'=>"Développement de l'application mobile ET du serveur (bout en bout).",
    'stack'=>['Android','PHP','HTTP (JSON + multipart)','Comptes utilisateurs'],
    'skills'=>["Client mobile Android avec envoi en arrière-plan","API serveur de réception, rangement et service de fichiers","Multi-utilisateurs avec cloisonnement des données","Transfert de fichiers volumineux (multipart)"],
    'result'=>"Chaîne complète mobile → serveur fonctionnelle, cloisonnée par compte.",
    'url'=>'','urlLabel'=>'',
  ],
  [
    'emoji'=>'🚀','name'=>'ztransfert','sub'=>'Envoi de fichiers volumineux',
    'cats'=>['Web'],
    'context'=>"Service type WeTransfer : on dépose un fichier, on reçoit un lien de téléchargement par e-mail.",
    'role'=>"Développement full-stack.",
    'stack'=>['PHP','JavaScript','MySQL','Envoi d\'e-mails','Gestion de fichiers'],
    'skills'=>["Upload de gros fichiers côté navigateur","Génération de liens + notification par e-mail","Espace d'administration des envois","Gestion du stockage et du cycle de vie des fichiers"],
    'result'=>"Service de transfert fonctionnel avec parcours d'envoi complet.",
    'url'=>'https://luvumbu.com/zt/','urlLabel'=>'luvumbu.com/zt',
  ],
  [
    'emoji'=>'🎨','name'=>'Cours HTML5 Canvas','sub'=>'Support de cours interactif',
    'cats'=>['Web','Pédagogie'],
    'context'=>"Support de cours interactif qui enseigne l'API Canvas de HTML5, étape par étape.",
    'role'=>"Conception pédagogique et développement.",
    'stack'=>['HTML5 Canvas','JavaScript','CSS'],
    'skills'=>["Vulgarisation technique & pédagogie","Dessin 2D en direct dans le navigateur","Progression du simple à l'avancé"],
    'result'=>"Cours interactif de dessin 2D, prêt à l'emploi.",
    'url'=>'https://luvumbu.com/Cours_complet_canvas/','urlLabel'=>'luvumbu.com/…/canvas',
  ],
  [
    'emoji'=>'🎵','name'=>'Arduino Nano — Lecteur MP3','sub'=>'Projet maker / électronique',
    'cats'=>['Électronique'],
    'context'=>"Lecteur MP3 piloté par un Arduino Nano via un module DFPlayer Mini (micro-SD, liaison série).",
    'role'=>"Montage, code et documentation.",
    'stack'=>['Arduino (C/C++)','DFPlayer Mini','Électronique embarquée'],
    'skills'=>["Électronique embarquée","Liaison série (D10/D11)","Documentation de projet maker (schéma + code .ino commenté)"],
    'result'=>"Montage documenté et reproductible.",
    'url'=>'https://luvumbu.com/ELECTRONIQUE/arduino-nano-musique.html','urlLabel'=>'luvumbu.com/…/arduino',
  ],
  [
    'emoji'=>'✍️','name'=>'Articles / Blog','sub'=>'Espace éditorial + app mobile',
    'cats'=>['Web','Mobile','Pédagogie'],
    'context'=>"Moteur de blog (rédaction d'articles, API et export JSON) avec application mobile et contenus pédagogiques dédiés.",
    'role'=>"Développement full-stack.",
    'stack'=>['PHP','JavaScript','JSON','Android'],
    'skills'=>["Gestion de contenu (CMS léger)","API & export du contenu en JSON","Application mobile associée","Rédaction et structuration de contenus pédagogiques"],
    'result'=>"Espace éditorial en ligne, ouvert par API, décliné en app.",
    'url'=>'https://luvumbu.com/articles/','urlLabel'=>'luvumbu.com/articles',
  ],
];

/* Catégories pour le filtre (dans l'ordre) */
$CATS = ['Tous','Web','Mobile','API','PWA','Jeu','Pédagogie','Électronique'];

function chip(string $t): string { return '<span class="chip">'.htmlspecialchars($t).'</span>'; }
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($IDENTITE['nom'])?> — <?=htmlspecialchars($IDENTITE['titre'])?> · Compétences & Projets</title>
<meta name="description" content="Fiche de compétences et portfolio de <?=htmlspecialchars($IDENTITE['nom'])?>, développeur full-stack : PHP, JavaScript, MySQL, API REST, mobile, sécurité et déploiement.">
<style>
  :root{
    --bg:#0a0f1c; --bg2:#0e1526; --panel:#131c31; --panel2:#182444; --line:#26324f;
    --text:#eaf0ff; --muted:#98a7c8; --dim:#6f80a4;
    --accent:#5b8cff; --accent2:#22d3ee; --gold:#ffcf5b; --green:#34d399;
    --shadow:0 20px 55px rgba(0,0,0,.45);
  }
  *{box-sizing:border-box} html{scroll-behavior:smooth}
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
    color:var(--text);line-height:1.55;
    background:
      radial-gradient(1100px 520px at 82% -8%,rgba(91,140,255,.22),transparent 60%),
      radial-gradient(900px 500px at 5% 0%,rgba(34,211,238,.14),transparent 55%),
      var(--bg);}
  a{color:inherit}
  .wrap{max-width:1080px;margin:0 auto;padding:0 20px}

  /* ── En-tête ── */
  header.hero{padding:60px 0 34px}
  .kicker{display:inline-flex;align-items:center;gap:8px;font-size:.8rem;letter-spacing:.14em;
    text-transform:uppercase;color:var(--accent2);border:1px solid var(--line);
    padding:6px 12px;border-radius:999px;background:rgba(34,211,238,.06)}
  .kicker b{color:var(--green);font-weight:700}
  h1{font-size:clamp(2.1rem,5.4vw,3.4rem);margin:16px 0 6px;line-height:1.05;letter-spacing:-.02em}
  h1 .g{background:linear-gradient(90deg,var(--accent),var(--accent2));-webkit-background-clip:text;background-clip:text;color:transparent}
  .role{font-size:clamp(1.05rem,2.4vw,1.35rem);color:var(--text);font-weight:600;margin:0}
  .role small{color:var(--muted);font-weight:500}
  .pitch{max-width:70ch;color:var(--muted);margin:16px 0 0;font-size:1.02rem}

  .cta{display:flex;flex-wrap:wrap;gap:10px;margin:26px 0 4px}
  .btn{display:inline-flex;align-items:center;gap:8px;text-decoration:none;font-weight:600;
    font-size:.95rem;padding:11px 17px;border-radius:11px;border:1px solid var(--line);
    background:var(--panel);color:var(--text);transition:.18s}
  .btn:hover{border-color:var(--accent);transform:translateY(-1px)}
  .btn.primary{background:linear-gradient(90deg,var(--accent),#4f7bff);border-color:transparent;color:#fff;
    box-shadow:0 10px 26px rgba(91,140,255,.35)}
  .btn.gold{background:linear-gradient(90deg,#f6b73c,var(--gold));border-color:transparent;color:#2a1e00}

  .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:34px 0 0}
  .stat{background:linear-gradient(180deg,var(--panel),rgba(19,28,49,.5));border:1px solid var(--line);
    border-radius:14px;padding:16px 16px}
  .stat b{display:block;font-size:1.5rem;color:#fff;line-height:1;letter-spacing:-.01em}
  .stat span{color:var(--muted);font-size:.85rem;display:block;margin-top:7px}
  @media(max-width:720px){.stats{grid-template-columns:repeat(2,1fr)}}

  /* ── Sections ── */
  section{padding:40px 0}
  .head{display:flex;align-items:baseline;gap:14px;margin:0 0 22px;flex-wrap:wrap}
  .head h2{font-size:clamp(1.4rem,3.4vw,1.9rem);margin:0;letter-spacing:-.01em}
  .head .n{color:var(--accent2);font-variant-numeric:tabular-nums;font-weight:700}
  .head p{color:var(--muted);margin:0;font-size:.95rem}

  /* Compétences */
  .skills{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
  @media(max-width:760px){.skills{grid-template-columns:1fr}}
  .skill{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:18px 18px}
  .skill h3{margin:0 0 12px;font-size:1.02rem;display:flex;align-items:center;gap:10px}
  .skill h3 .ic{width:34px;height:34px;border-radius:9px;display:grid;place-items:center;font-size:1.05rem;
    background:radial-gradient(circle at 30% 25%,rgba(91,140,255,.35),rgba(34,211,238,.12));border:1px solid var(--line)}
  .chips{display:flex;flex-wrap:wrap;gap:7px}
  .chip{font-size:.82rem;color:var(--text);background:var(--panel2);border:1px solid var(--line);
    padding:5px 10px;border-radius:8px;white-space:nowrap}

  /* Filtres */
  .filters{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 22px}
  .filters button{font:inherit;cursor:pointer;font-size:.9rem;font-weight:600;padding:8px 14px;border-radius:999px;
    border:1px solid var(--line);background:var(--panel);color:var(--muted);transition:.16s}
  .filters button:hover{border-color:var(--accent);color:var(--text)}
  .filters button.on{background:var(--accent);border-color:var(--accent);color:#fff}

  /* Projets */
  .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:18px}
  @media(max-width:820px){.grid{grid-template-columns:1fr}}
  .card{background:linear-gradient(180deg,var(--panel),rgba(19,28,49,.55));border:1px solid var(--line);
    border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:12px;transition:.18s;position:relative;overflow:hidden}
  .card:hover{border-color:var(--accent);transform:translateY(-2px);box-shadow:var(--shadow)}
  .card.flag{grid-column:1 / -1;border-color:rgba(255,207,91,.4);
    background:linear-gradient(120deg,rgba(255,207,91,.08),var(--panel) 45%)}
  .card.flag::after{content:"★ Réalisation phare";position:absolute;top:14px;right:-34px;transform:rotate(38deg);
    background:linear-gradient(90deg,#f6b73c,var(--gold));color:#2a1e00;font-size:.7rem;font-weight:800;
    letter-spacing:.04em;padding:5px 44px}
  .c-top{display:flex;align-items:center;gap:14px}
  .avatar{width:52px;height:52px;flex:0 0 52px;border-radius:14px;display:grid;place-items:center;font-size:1.7rem;
    background:radial-gradient(circle at 30% 25%,rgba(91,140,255,.4),rgba(34,211,238,.15));border:1px solid var(--line)}
  .card.flag .avatar{background:radial-gradient(circle at 30% 25%,rgba(255,207,91,.45),rgba(246,183,60,.15))}
  .c-top h3{margin:0;font-size:1.16rem;letter-spacing:-.01em}
  .c-top .sub{color:var(--muted);font-size:.85rem;margin-top:2px}
  .context{color:var(--text);opacity:.92;margin:0;font-size:.95rem}
  .role-l{font-size:.85rem;color:var(--dim)}
  .role-l b{color:var(--muted);font-weight:600}
  .skills-l{margin:2px 0 0;padding-left:0;list-style:none;display:flex;flex-direction:column;gap:6px}
  .skills-l li{position:relative;padding-left:20px;font-size:.9rem;color:var(--muted)}
  .skills-l li::before{content:"";position:absolute;left:3px;top:.55em;width:7px;height:7px;border-radius:50%;
    background:linear-gradient(90deg,var(--accent),var(--accent2))}
  .result{font-size:.9rem;color:var(--green);background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.22);
    border-radius:10px;padding:9px 12px;margin-top:auto}
  .result b{color:var(--green)}
  .c-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
  .link{display:inline-flex;align-items:center;gap:7px;text-decoration:none;font-weight:700;font-size:.9rem;
    color:#2a1200;background:linear-gradient(90deg,#ff7a00,#ff9d2f);border:1px solid transparent;
    padding:8px 14px;border-radius:10px;box-shadow:0 6px 20px rgba(255,122,0,.5);transition:.16s}
  .link:hover{transform:translateY(-1px);box-shadow:0 10px 26px rgba(255,122,0,.65);
    background:linear-gradient(90deg,#ff8c1a,#ffae4d)}
  .badges{display:flex;gap:6px;flex-wrap:wrap}
  .badge{font-size:.72rem;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:var(--accent2);
    border:1px solid var(--line);border-radius:7px;padding:3px 8px;background:rgba(34,211,238,.05)}

  /* Pied de page */
  footer{border-top:1px solid var(--line);margin-top:20px;padding:34px 0 60px;text-align:center}
  footer .oneline{max-width:60ch;margin:0 auto 18px;color:var(--muted)}
  footer .oneline b{color:var(--text)}
  .print-note{color:var(--dim);font-size:.82rem;margin-top:18px}

  /* ── Impression / PDF ── */
  @media print{
    :root{--text:#101828;--muted:#3d4a63;--dim:#64748b;--line:#d7deea}
    body{background:#fff;color:#101828}
    .cta,.filters,.print-note,.card.flag::after{display:none!important}
    .card,.skill,.stat{break-inside:avoid;box-shadow:none;background:#fff;border-color:#d7deea}
    .card:hover{transform:none;box-shadow:none}
    header.hero{padding:10px 0 6px}
    h1 .g{color:#1d4ed8;-webkit-text-fill-color:#1d4ed8}
    section{padding:16px 0}
    .grid,.skills{gap:12px}
    a[href^="http"]::after{content:" (" attr(href) ")";font-size:.7em;color:#64748b}
    .chip,.badge{background:#f1f5f9;color:#334155}
    .result{background:#f0fdf4;color:#166534;border-color:#bbf7d0}
  }
</style>
</head>
<body>

<header class="hero"><div class="wrap">
  <span class="kicker">Fiche de compétences · <b>Disponible</b></span>
  <h1><?=htmlspecialchars($IDENTITE['nom'])?> — <span class="g"><?=htmlspecialchars($IDENTITE['titre'])?></span></h1>
  <p class="role"><?=htmlspecialchars($IDENTITE['sous'])?></p>
  <p class="pitch"><?=htmlspecialchars($IDENTITE['pitch'])?></p>

  <div class="cta">
    <a class="btn primary" href="https://bokonzi.com" target="_blank" rel="noopener">🏟️ Voir BOKONZI</a>
    <a class="btn" href="https://luvumbu.com" target="_blank" rel="noopener">🗺️ Portfolio</a>
    <a class="btn" href="cv_luvumbu/" >📄 Mon CV</a>
    <button class="btn gold" onclick="window.print()">🖨️ Imprimer / PDF</button>
  </div>

  <div class="stats">
    <?php foreach($STATS as [$b,$s]): ?>
      <div class="stat"><b><?=htmlspecialchars($b)?></b><span><?=htmlspecialchars($s)?></span></div>
    <?php endforeach; ?>
  </div>
</div></header>

<section id="competences"><div class="wrap">
  <div class="head">
    <h2>Compétences</h2>
    <p>Savoir-faire transversaux, démontrés par les projets ci-dessous.</p>
  </div>
  <div class="skills">
    <?php foreach($SKILLS as [$ic,$titre,$items]): ?>
      <div class="skill">
        <h3><span class="ic"><?=$ic?></span><?=htmlspecialchars($titre)?></h3>
        <div class="chips"><?php foreach($items as $it) echo chip($it); ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div></section>

<section id="projets"><div class="wrap">
  <div class="head">
    <h2>Projets réalisés <span class="n"><?=count($PROJECTS)?></span></h2>
    <p>Chaque projet : contexte, rôle, stack et compétences démontrées.</p>
  </div>

  <div class="filters" id="filters">
    <?php foreach($CATS as $i=>$cat): ?>
      <button data-cat="<?=htmlspecialchars($cat)?>"<?=$i===0?' class="on"':''?>><?=htmlspecialchars($cat)?></button>
    <?php endforeach; ?>
  </div>

  <div class="grid" id="grid">
    <?php foreach($PROJECTS as $p): ?>
      <article class="card<?=!empty($p['flag'])?' flag':''?>" data-cats="<?=htmlspecialchars(implode(',',$p['cats']))?>">
        <div class="c-top">
          <div class="avatar"><?=$p['emoji']?></div>
          <div>
            <h3><?=htmlspecialchars($p['name'])?></h3>
            <div class="sub"><?=htmlspecialchars($p['sub'])?></div>
          </div>
        </div>
        <p class="context"><?=htmlspecialchars($p['context'])?></p>
        <div class="role-l"><b>Rôle :</b> <?=htmlspecialchars($p['role'])?></div>
        <div class="chips"><?php foreach($p['stack'] as $t) echo chip($t); ?></div>
        <ul class="skills-l"><?php foreach($p['skills'] as $s) echo '<li>'.htmlspecialchars($s).'</li>'; ?></ul>
        <div class="result"><b>Résultat —</b> <?=htmlspecialchars($p['result'])?></div>
        <div class="c-foot">
          <div class="badges"><?php foreach($p['cats'] as $c) echo '<span class="badge">'.htmlspecialchars($c).'</span>'; ?></div>
          <?php if(!empty($p['url'])): ?>
            <a class="link" href="<?=htmlspecialchars($p['url'])?>" target="_blank" rel="noopener">Voir en ligne · <?=htmlspecialchars($p['urlLabel'])?> ↗</a>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div></section>

<footer><div class="wrap">
  <p class="oneline"><b>En une phrase :</b> développeur full-stack autonome, capable de porter un produit
    de l'idée à la production — conception, développement web &amp; mobile, API, sécurité et déploiement —
    comme le montre l'écosystème <b>BOKONZI</b>, en ligne et complet.</p>
  <div class="cta" style="justify-content:center">
    <a class="btn primary" href="https://luvumbu.com" target="_blank" rel="noopener">🗺️ Portfolio complet</a>
  </div>
  <p class="print-note">💡 Astuce : « Imprimer / PDF » génère une fiche propre à envoyer aux entreprises.</p>
</div></footer>

<script>
  const filters = document.querySelector('#filters');
  const cards = [...document.querySelectorAll('#grid .card')];
  filters.addEventListener('click', e => {
    const b = e.target.closest('button'); if(!b) return;
    filters.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
    const cat = b.dataset.cat;
    cards.forEach(c => {
      const show = cat === 'Tous' || c.dataset.cats.split(',').includes(cat);
      c.style.display = show ? '' : 'none';
    });
  });
</script>
</body>
</html>
