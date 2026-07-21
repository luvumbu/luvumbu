<?php
/* ═══════════════════════════════════════════════════════════════════════
   ÉLAN — organiseur d'objectifs (sport & pro), style Kanban / Trello.
   Colonnes + cartes déplaçables, catégories 🏃 Sport / 💼 Pro, priorité,
   échéance. Protégé par le SSO Luvumbu ID (si présent). Données par
   utilisateur en JSON (voir api.php).
   ═══════════════════════════════════════════════════════════════════════ */
const APP_NOM = 'Élan';   // ← nom affiché (change ici pour renommer l'app)

/* Connexion via SSO Luvumbu ID (repli : ouvert si le SSO n'est pas déployé) */
$ssoClient = __DIR__ . '/../sso/client.php';
$user = null;
if (is_file($ssoClient)) {
    require_once $ssoClient;
    if (isset($_GET['logout'])) { luvumbu_logout(true, 'https://luvumbu.com/objectifs/'); exit; }  // déconnexion → réaffiche l'écran de connexion
    $user = luvumbu_require_login('objectifs');   // redirige vers le hub si non connecté
}
$displayName = $user['name'] ?? 'Invité';
function he($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<link rel="manifest" href="manifest.webmanifest">
<meta name="theme-color" content="#0d1320">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Élan">
<link rel="apple-touch-icon" href="icon-192.png">
<title><?= he(APP_NOM) ?> — Mes objectifs</title><style>
  :root{
    --bg:#0d1320; --panel:#151d2e; --col:#12192a; --card:#1b2740; --line:#28324c;
    --text:#eaf0ff; --muted:#93a1c0; --accent:#5b8cff;
    --sport:#37c98b; --pro:#ffab3d;
    --ph:#ff5d6c; --pm:#ffb84d; --pb:#63b3ff;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
  button,input,textarea,select{font-family:inherit}
  button{cursor:pointer;border:1px solid var(--line);background:var(--panel);color:var(--text);border-radius:9px;padding:8px 12px;font-size:14px}
  button:hover{border-color:var(--accent)}
  button.primary{background:var(--accent);border-color:var(--accent);color:#fff;font-weight:600}
  input,textarea,select{background:var(--col);border:1px solid var(--line);color:var(--text);border-radius:9px;padding:10px 12px;font-size:14px;width:100%}

  header.bar{display:flex;align-items:center;gap:12px;padding:14px 18px;background:var(--panel);
    border-bottom:1px solid var(--line);position:sticky;top:0;z-index:5;flex-wrap:wrap}
  header .logo{font-size:1.3rem;font-weight:800}.logo b{color:var(--accent)}
  header .who{color:var(--muted);font-size:.85rem}
  header .spacer{flex:1}
  .filters{display:flex;gap:6px;align-items:center}
  .chip{padding:6px 12px;border-radius:20px;font-size:.85rem;border:1px solid var(--line);background:var(--col)}
  .chip.on{background:var(--accent);border-color:var(--accent);color:#fff}
  .save-state{font-size:.78rem;color:var(--muted);min-width:80px}

  .board{display:flex;gap:16px;padding:18px;align-items:flex-start;overflow-x:auto;min-height:calc(100vh - 66px)}
  .column{background:var(--col);border:1px solid var(--line);border-radius:14px;width:300px;flex:none;
    display:flex;flex-direction:column;max-height:calc(100vh - 100px)}
  .col-head{display:flex;align-items:center;gap:8px;padding:12px 14px;border-bottom:1px solid var(--line)}
  .col-head .ctitle{font-weight:700;flex:1;background:transparent;border:none;color:var(--text);font-size:.98rem;padding:2px}
  .col-head .count{background:var(--panel);border:1px solid var(--line);border-radius:20px;padding:1px 9px;font-size:.75rem;color:var(--muted)}
  .col-del{opacity:.5;font-size:.85rem;padding:3px 7px}
  .col-del:hover{opacity:1;border-color:var(--ph);color:var(--ph)}
  .cards{padding:10px;overflow-y:auto;flex:1;min-height:40px;display:flex;flex-direction:column;gap:9px}
  .cards.drop-hot{outline:2px dashed var(--accent);outline-offset:-6px;border-radius:10px}

  .card{background:var(--card);border:1px solid var(--line);border-radius:11px;padding:11px 12px;cursor:grab;
    border-left:4px solid var(--line)}
  .card:active{cursor:grabbing}
  .card.dragging{opacity:.4}
  .card.cat-sport{border-left-color:var(--sport)}
  .card.cat-pro{border-left-color:var(--pro)}
  .card .ctitre{font-weight:600;font-size:.95rem;margin-bottom:6px;word-break:break-word}
  .card .meta{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
  .tag{font-size:.72rem;padding:2px 8px;border-radius:20px;border:1px solid var(--line)}
  .tag.sport{color:var(--sport);border-color:var(--sport)}
  .tag.pro{color:var(--pro);border-color:var(--pro)}
  .tag.prio-h{color:var(--ph);border-color:var(--ph)}
  .tag.prio-m{color:var(--pm);border-color:var(--pm)}
  .tag.prio-b{color:var(--pb);border-color:var(--pb)}
  .tag.due{color:var(--muted)}
  .tag.due.late{color:var(--ph);border-color:var(--ph)}
  .add-card{margin:0 10px 10px;color:var(--muted);text-align:left;background:transparent;border:1px dashed var(--line)}
  .add-card:hover{color:var(--text)}
  .add-col{width:220px;flex:none}

  /* Modale */
  .modal{position:fixed;inset:0;background:rgba(5,8,16,.7);display:none;z-index:20;padding:20px;overflow:auto}
  .modal.on{display:block}
  .box{background:var(--panel);border:1px solid var(--line);border-radius:16px;max-width:600px;margin:26px auto;padding:24px}
  @media(max-width:560px){ .modal{padding:8px} .box{margin:8px auto;padding:16px} }
  .box h3{margin:0 0 14px}
  /* Modale d'aide */
  .infobody{font-size:.9rem;line-height:1.5}
  .infobody h4{margin:16px 0 4px;font-size:.98rem}
  .infobody p{margin:4px 0;color:var(--muted)}
  .infobody b{color:var(--text)}
  .infobody code{background:var(--col);border:1px solid var(--line);border-radius:5px;padding:1px 5px;font-size:.85em;color:var(--text)}
  .infolist{margin:6px 0;padding-left:18px;color:var(--muted)}
  .infolist li{margin:3px 0}
  .infonow{background:linear-gradient(120deg,rgba(91,140,255,.16),var(--col));border:1px solid var(--line);
    border-radius:10px;padding:11px 13px;margin-bottom:6px;font-size:1rem}
  .mgtitle{margin:14px 0 6px;font-size:.85rem;font-weight:700;color:var(--muted)}
  .mgallery{display:flex;flex-direction:column;gap:8px}
  .mgrow{display:flex;align-items:center;gap:10px;background:var(--col);border:1px solid var(--line);border-radius:10px;padding:8px 10px}
  .mgh{width:96px;flex:0 0 96px;font-size:.82rem;font-weight:600}
  .mgcells{display:flex;gap:6px;flex:1;flex-wrap:wrap}
  .mgc{display:flex;flex-direction:column;align-items:center;gap:2px}
  .mgc canvas{background:radial-gradient(circle at 50% 34%,#1b2c4a,#0a0f1a);border:1px solid var(--line);border-radius:7px}
  .mgc span{font-size:.66rem;color:var(--muted)}
  /* 📊 Progrès */
  .statgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:6px 0 10px}
  @media(max-width:560px){.statgrid{grid-template-columns:1fr}}
  .statcard{background:var(--col);border:1px solid var(--line);border-radius:10px;padding:10px 12px;font-size:.85rem}
  .statcard h4{margin:0 0 6px;font-size:.82rem;color:var(--muted)}
  #statsModal canvas{background:var(--col);border:1px solid var(--line);border-radius:10px;margin-bottom:4px}
  #statsModal h4{margin:14px 0 4px;font-size:.95rem}
  .pbar{height:7px;background:var(--bg,#0a0f1a);border:1px solid var(--line);border-radius:5px;overflow:hidden;margin:3px 0 6px}
  .pbar i{display:block;height:100%;background:linear-gradient(90deg,var(--sport,#5b8cff),var(--accent,#22d3ee));transition:width .5s}
  .badges2{display:flex;flex-wrap:wrap;gap:7px}
  .bdg{font-size:.78rem;padding:5px 10px;border-radius:20px;border:1px solid var(--line);background:var(--col);color:var(--muted);opacity:.55}
  .bdg.on{opacity:1;color:#2a1500;background:linear-gradient(90deg,#f6b73c,#ffcf5b);border-color:transparent;font-weight:700}
  .runlist{display:flex;flex-direction:column;gap:5px}
  .runrow{display:flex;align-items:center;gap:10px;font-size:.82rem;background:var(--col);border:1px solid var(--line);border-radius:8px;padding:6px 10px}
  .runrow span{color:var(--muted)} .runrow b{color:var(--text)} .runrow .rpace{margin-left:auto;color:var(--accent2,#22d3ee);font-weight:700}
  .jlist{display:flex;flex-direction:column;gap:5px;max-height:240px;overflow:auto}
  .jrow{display:flex;align-items:center;gap:10px;font-size:.82rem;background:var(--col);border:1px solid var(--line);border-radius:8px;padding:6px 10px}
  .jrow span{color:var(--muted)} .jrow b{color:var(--text);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .jrow .jp{color:var(--green,#34d399);font-weight:700}
  .jdel{background:transparent;border:1px solid var(--line);color:var(--muted);border-radius:6px;padding:1px 7px;cursor:pointer;font-size:.8rem}
  .jdel:hover{border-color:var(--ph,#ff6978);color:var(--ph,#ff6978)}
  .jclear{font-size:.72rem;font-weight:600;padding:2px 9px;border-radius:20px;border:1px solid var(--line);background:var(--col);color:var(--muted);cursor:pointer;vertical-align:middle;margin-left:6px}
  .jclear:hover{border-color:var(--ph,#ff6978);color:var(--ph,#ff6978)}
  .warnbox{background:rgba(255,105,120,.10);border:1px solid rgba(255,105,120,.4);border-radius:10px;
    padding:11px 13px;font-size:.85rem;color:var(--text);margin-bottom:8px}
  .undobtn{margin-left:10px;background:var(--accent,#5b8cff);color:#fff;border:none;border-radius:7px;
    padding:4px 10px;font-weight:700;font-size:.82rem;cursor:pointer}
  .undobtn:hover{filter:brightness(1.1)}
  .box label{display:block;margin:12px 0 5px;font-size:.85rem;color:var(--muted)}
  .row{display:flex;gap:10px}.row>*{flex:1}
  .foot{display:flex;justify-content:space-between;gap:8px;margin-top:18px}
  .empty{color:var(--muted);text-align:center;font-size:.85rem;padding:14px}
  #toast{position:fixed;left:50%;bottom:20px;transform:translateX(-50%);background:var(--panel);
    border:1px solid var(--accent);padding:10px 16px;border-radius:10px;opacity:0;transition:.25s;z-index:40}
  #toast.on{opacity:1}

  /* Actions / muscles dans la modale carte */
  .actions-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:4px}
  .actions-grid label{display:flex;align-items:center;gap:7px;background:var(--col);border:1px solid var(--line);
    border-radius:8px;padding:7px 9px;font-size:.82rem;color:var(--text);margin:0;cursor:pointer}
  .actions-grid input{width:auto}
  /* Exercices groupés (modale carte) */
  #cmActions{display:block;max-height:300px;overflow:auto;margin-top:4px;
    border:1px solid var(--line);border-radius:10px;padding:6px}
  .exgrp{border-bottom:1px solid var(--line)}
  .exgrp:last-child{border-bottom:0}
  .exgrp>summary{cursor:pointer;list-style:none;padding:8px 6px;font-size:.86rem;font-weight:600;
    color:var(--text);display:flex;align-items:center;gap:8px}
  .exgrp>summary::-webkit-details-marker{display:none}
  .exgrp>summary::before{content:'▸';color:var(--muted);transition:transform .15s}
  .exgrp[open]>summary::before{transform:rotate(90deg)}
  .exgrp .gc{margin-left:auto;font-size:.72rem;color:var(--muted);background:var(--col);
    border:1px solid var(--line);border-radius:20px;padding:1px 9px;font-weight:600}
  .exlist{display:grid;grid-template-columns:1fr 1fr;gap:6px;padding:4px 4px 10px}
  @media(max-width:520px){.exlist{grid-template-columns:1fr}}
  .exitem{display:flex;align-items:center;gap:7px;background:var(--col);border:1px solid var(--line);
    border-radius:8px;padding:6px 9px;font-size:.8rem;color:var(--text);margin:0;cursor:pointer}
  .exitem input{width:auto}
  .exitem .exn{flex:1}
  .exitem .exp{font-size:.7rem;font-weight:700;padding:1px 7px;border-radius:20px;min-width:26px;text-align:center}
  .exp.niv1{background:rgba(52,211,153,.15);color:#34d399}
  .exp.niv2{background:rgba(245,183,60,.15);color:#f5b73c}
  .exp.niv3{background:rgba(255,105,120,.15);color:#ff6978}
  .exitem .exq{width:52px;flex:0 0 52px;font-size:.72rem;text-align:center;padding:2px 4px;
    border:1px solid var(--line);border-radius:6px;background:var(--bg,#0a0f1a);color:var(--text)}
  .exitem .exq::placeholder{color:var(--muted);opacity:.6}
  .exqwrap{display:inline-flex;gap:4px}
  .exqwrap .exq{width:46px;flex:0 0 46px}
  .exqwrap .exs,.exqwrap .exr{width:34px;flex:0 0 34px}
  .exqwrap .exw{width:42px;flex:0 0 42px}
  /* Exercices affichés sur la carte (avec quantités) */
  .cx{display:flex;flex-wrap:wrap;gap:4px;margin-top:8px}
  .cxi{font-size:.72rem;background:var(--col);border:1px solid var(--line);border-radius:6px;
    padding:2px 7px;color:var(--muted)}
  .cxi b{color:var(--sport,#5b8cff);font-weight:700;margin-left:3px}
  /* Bloc niveau / XP (modale avatar) */
  .lvlbox{background:linear-gradient(120deg,rgba(91,140,255,.14),var(--col));border:1px solid var(--line);
    border-radius:12px;padding:12px 14px;margin-bottom:12px}
  .lvlrow{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
  .lvlrow .rang{font-weight:800;font-size:1.02rem;color:#fff}
  .lvlrow .niv{font-size:.82rem;color:var(--muted);background:var(--col);border:1px solid var(--line);
    border-radius:20px;padding:2px 10px;font-weight:700}
  .xptrack{height:10px;background:var(--col);border:1px solid var(--line);border-radius:6px;overflow:hidden}
  .xptrack i{display:block;height:100%;background:linear-gradient(90deg,#5b8cff,#22d3ee);transition:width .5s}
  .xptxt{font-size:.76rem;color:var(--muted);margin-top:6px}
  /* Bibliothèque de séances */
  .liblist{display:flex;flex-direction:column;gap:10px;max-height:60vh;overflow:auto}
  .libcard{background:var(--col);border:1px solid var(--line);border-radius:12px;padding:12px 14px}
  .libtop{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:5px}
  .libtop b{font-size:.98rem}
  .libpts{font-size:.74rem;font-weight:700;color:#22d3ee;background:rgba(34,211,238,.1);
    border:1px solid var(--line);border-radius:20px;padding:2px 9px;white-space:nowrap}
  .libex{color:var(--muted);font-size:.8rem;margin-bottom:10px}
  .libcard button{width:100%}
  /* Avatar */
  #avatarSvg .mz{stroke:#0a0f1a;stroke-width:1.5;transition:fill .5s,filter .5s}
  #avatarSvg .skin{fill:#2a3450}
  #avatarSvg .body{fill:#1b2740;stroke:#0a0f1a;stroke-width:1.5}
  .astats{background:var(--col);border:1px solid var(--line);border-radius:10px;padding:12px 14px;margin-bottom:12px;
    font-size:.92rem;display:flex;flex-direction:column;gap:7px}
  .astats b{color:#fff}
  .mbars{display:flex;flex-direction:column;gap:7px}
  .mbar{display:flex;align-items:center;gap:8px;font-size:.82rem}
  .mbar>span{width:82px;color:var(--muted)}
  .mtrack{flex:1;height:9px;background:var(--col);border:1px solid var(--line);border-radius:6px;overflow:hidden}
  .mtrack i{display:block;height:100%;background:linear-gradient(90deg,var(--sport),var(--accent));transition:width .5s}
  .mbar>b{width:40px;text-align:right}
  /* 📊 Stats des zones travaillées */
  .zstats{margin-top:14px;background:var(--col);border:1px solid var(--line);border-radius:10px;padding:12px 14px}
  .zhead{font-weight:700;font-size:.9rem;margin-bottom:9px}
  .zrow{display:flex;align-items:center;gap:8px;font-size:.82rem;margin-bottom:6px}
  .zrow .zn{width:120px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .ztrack{flex:1;height:9px;background:var(--bg,#0a0f1a);border:1px solid var(--line);border-radius:6px;overflow:hidden}
  .ztrack i{display:block;height:100%;background:linear-gradient(90deg,#f6b73c,#ff7a00);transition:width .5s}
  .zrow .zv{width:42px;text-align:right;color:#fff}
  .zrow .zp{width:38px;text-align:right;color:var(--muted)}
  .zreco{margin-top:9px;font-size:.8rem;color:var(--muted)}
  .zreco b{color:var(--text)}
  .avcap{margin-top:8px;font-size:.9rem;color:var(--muted)}
  .avcap b{color:var(--text);font-size:1rem}
  .bmibox{margin-top:10px;display:flex;flex-direction:column;gap:6px;align-items:center}
  .bmibox label{font-size:.8rem;color:var(--muted);display:flex;align-items:center;justify-content:space-between;gap:8px;width:150px}
  .bmibox input{width:74px;padding:4px 6px;border:1px solid var(--line);border-radius:6px;background:var(--bg,#0a0f1a);color:var(--text);font-size:.85rem;text-align:center}
  .hint{color:var(--muted);font-size:.8rem}
  .skinsw{width:24px;height:24px;border-radius:50%;border:2px solid var(--line);cursor:pointer;padding:0;
    margin-left:5px;vertical-align:middle;transition:.12s}
  .skinsw:hover{transform:scale(1.12)}
  .skinsw.on{border-color:#fff;box-shadow:0 0 0 2px var(--accent)}

  /* ══════════ THÈME FUTURISTE (activable dans ⚙️ Paramètres) ══════════ */
  body[data-theme="futur"]{
    --bg:#05060f; --panel:rgba(18,24,52,.62); --col:rgba(14,20,44,.55); --card:rgba(24,32,64,.60);
    --line:rgba(110,140,255,.30); --text:#eaf2ff; --muted:#93a6d6; --accent:#00e0ff;
    --sport:#00e0ff; --pro:#b06bff; --ph:#ff4d7d; --pm:#ffc04d; --pb:#5ba8ff;
    background:
      linear-gradient(rgba(120,150,255,.045) 1px,transparent 1px) 0 0/44px 44px,
      linear-gradient(90deg,rgba(120,150,255,.045) 1px,transparent 1px) 0 0/44px 44px,
      radial-gradient(1200px 700px at 82% -12%, rgba(176,107,255,.22), transparent 60%),
      radial-gradient(1000px 640px at -5% 8%, rgba(0,224,255,.18), transparent 58%),
      #05060f fixed;
  }
  body[data-theme="futur"] header.bar{ background:rgba(9,13,32,.62); backdrop-filter:blur(14px);
    border-bottom:1px solid rgba(0,224,255,.35); box-shadow:0 6px 34px rgba(0,224,255,.10) }
  body[data-theme="futur"] .logo b{ text-shadow:0 0 14px rgba(0,224,255,.75) }
  body[data-theme="futur"] .box, body[data-theme="futur"] .column{ backdrop-filter:blur(12px);
    box-shadow:0 0 0 1px rgba(120,150,255,.15), 0 12px 44px rgba(0,224,255,.09) }
  body[data-theme="futur"] .card{ backdrop-filter:blur(6px); box-shadow:0 2px 14px rgba(0,0,0,.4) }
  body[data-theme="futur"] .card:hover{ border-color:rgba(0,224,255,.55); box-shadow:0 0 20px rgba(0,224,255,.35) }
  body[data-theme="futur"] button{ background:rgba(30,40,80,.5) }
  body[data-theme="futur"] button:hover{ border-color:var(--accent); box-shadow:0 0 12px rgba(0,224,255,.35) }
  body[data-theme="futur"] button.primary, body[data-theme="futur"] .chip.on{
    background:linear-gradient(90deg,#00e0ff,#7b5bff); border-color:transparent; color:#04101f; font-weight:700;
    box-shadow:0 0 20px rgba(0,224,255,.5) }
  body[data-theme="futur"] h2, body[data-theme="futur"] h3, body[data-theme="futur"] h4{ letter-spacing:.02em }
  body[data-theme="futur"] .mtrack i, body[data-theme="futur"] .xptrack i, body[data-theme="futur"] .pbar i,
  body[data-theme="futur"] .ztrack i{ box-shadow:0 0 10px rgba(0,224,255,.6) }
  body[data-theme="futur"] .bdg.on{ box-shadow:0 0 14px rgba(255,213,74,.5) }
  body[data-theme="futur"] .avcap b, body[data-theme="futur"] .head .n{ text-shadow:0 0 10px rgba(0,224,255,.5) }
</style>
</head>
<body>
<header class="bar">
  <div class="logo">🔥 <b><?= he(APP_NOM) ?></b></div>
  <div class="who">· Mes objectifs de <?= he($displayName) ?></div>
  <div class="spacer"></div>
  <div class="filters">
    <span class="chip on" data-filter="all" onclick="setFilter(this)">Tout</span>
    <span class="chip" data-filter="sport" onclick="setFilter(this)">🏃 Sport</span>
    <span class="chip" data-filter="pro" onclick="setFilter(this)">💼 Pro</span>
  </div>
  <button onclick="openLib()" style="margin-left:4px">📚 Séances</button>
  <button onclick="openAvatar()">🏋️ Avatar</button>
  <button onclick="openStats()" title="Mes progrès">📊 Progrès</button>
  <button onclick="openInfo()" title="Comment ça marche ?">ℹ️ Aide</button>
  <button onclick="openSettings()" title="Paramètres">⚙️</button>
  <span class="save-state" id="saveState"></span>
  <?php if ($user): ?><a href="?logout=1"><button>Quitter</button></a><?php endif; ?>
</header>

<div class="board" id="board"></div>

<!-- Modale carte -->
<div class="modal" id="cardModal">
  <div class="box">
    <h3 id="cmTitle">Nouvel objectif / tâche</h3>
    <label>Titre</label>
    <input type="text" id="cmTitre" placeholder="Ex. Courir 10 km sous 50 min" maxlength="120">
    <div class="row">
      <div><label>Catégorie</label>
        <select id="cmCat"><option value="sport">🏃 Sport</option><option value="pro">💼 Pro</option></select></div>
      <div><label>Priorité</label>
        <select id="cmPrio"><option value="h">🔴 Haute</option><option value="m" selected>🟠 Moyenne</option><option value="b">🔵 Basse</option></select></div>
    </div>
    <label>Échéance</label>
    <input type="date" id="cmDue">
    <label>Détails</label>
    <textarea id="cmDesc" rows="3" maxlength="1000" placeholder="Notes, sous-étapes…"></textarea>
    <label>💪 Actions / muscles travaillés <span style="color:var(--muted);font-weight:400;font-size:.85em">— cochées = alimentent l'avatar quand la séance est validée</span></label>
    <div id="cmActions" class="actions-grid"></div>
    <div class="foot">
      <button class="danger" id="cmDelete" onclick="deleteCard()" style="border-color:var(--ph);color:var(--ph)">🗑 Supprimer</button>
      <div style="display:flex;gap:8px">
        <button onclick="closeModal()">Annuler</button>
        <button class="primary" onclick="saveCard()">Enregistrer</button>
      </div>
    </div>
  </div>
</div>

<!-- Modale avatar musculaire -->
<div class="modal" id="avatarModal">
  <div class="box" style="max-width:860px">
    <h3>🏋️ Mon avatar — silhouette selon l'entraînement</h3>
    <div style="display:flex;gap:24px;flex-wrap:wrap">
      <div style="flex:0 0 300px;text-align:center">
        <canvas id="avCanvas" width="300" height="470" style="width:300px;height:470px;max-width:100%;margin:0 auto;border:1px solid var(--line);border-radius:14px;background:radial-gradient(circle at 50% 34%,#1b2c4a,#0a0f1a)"></canvas>
        <div id="avCaption" class="avcap"></div>
        <div class="bmibox">
          <label>Taille <input id="avH" type="number" min="80" max="250" step="1" placeholder="cm" oninput="setBody()"></label>
          <label>Poids <input id="avW" type="number" min="20" max="300" step="0.1" placeholder="kg" oninput="setBody()"></label>
          <div id="avBmi" class="hint"></div>
        </div>
      </div>
      <div style="flex:1;min-width:230px">
        <div id="avatarStats" class="astats"></div>
        <div id="skinPicker" style="margin-bottom:12px"></div>
        <div id="avatarBars" class="mbars"></div>
        <div id="zoneStats" class="zstats"></div>
      </div>
    </div>
    <div class="foot">
      <span class="hint">Valide une séance (glisse-la vers ✅ Terminé) pour développer tes muscles.</span>
      <button class="primary" onclick="closeAvatar()">Fermer</button>
    </div>
  </div>
</div>

<!-- Modale bibliothèque de séances -->
<div class="modal" id="libModal">
  <div class="box" style="max-width:720px">
    <h3>📚 Bibliothèque de séances</h3>
    <p class="hint" style="margin:2px 0 12px">Ajoute une séance toute prête à ton tableau (colonne « À faire »). Ses exercices sont déjà cochés : glisse-la vers ✅ Terminé pour gagner les points.</p>
    <div id="libList" class="liblist"></div>
    <div class="foot"><span></span><button class="primary" onclick="closeLib()">Fermer</button></div>
  </div>
</div>

<!-- Modale AIDE / infos globales -->
<div class="modal" id="infoModal">
  <div class="box" style="max-width:860px">
    <h3>ℹ️ Comment ça marche ?</h3>
    <div class="infobody">
      <div id="infoNow" class="infonow"></div>

      <h4>🎯 Le principe</h4>
      <p>Élan est un tableau <b>Kanban</b> pour organiser tes objectifs <b>🏃 Sport</b> et <b>💼 Pro</b>.
      Tu crées des cartes (objectifs, tâches, séances) et tu les fais glisser d'une colonne à l'autre
      (À faire → En cours → ✅ Terminé). Les filtres en haut n'affichent que le sport ou que le pro.</p>

      <h4>💪 Gagner des points</h4>
      <p>Une carte peut contenir des <b>exercices</b> (bouton « Modifier » d'une carte → coche-les par partie du corps).
      Quand tu glisses cette carte vers une colonne <b>✅ Terminé</b>, la séance est <b>validée</b> :
      tu gagnes des points sur chaque muscle travaillé, plus de l'<b>XP</b> globale.</p>

      <h4>🔢 Quantités (répétitions / séries)</h4>
      <p>Pour la <b>muscu</b>, trois champs : <b>séries</b> × <b>répétitions</b> × <b>poids</b> (<code>4 · 10 · 60</code> kg).
      Pour la <b>course/cardio</b>, <b>distance</b> (<code>5 km</code>) + <b>temps</b> (<code>25 min</code>). Mobilité : une <b>durée</b>.</p>
      <h4>📊 Progrès</h4>
      <p>Le bouton <b>📊 Progrès</b> montre tes graphiques (séances/semaine, poids corporel), ta <b>série</b> de jours,
      le <b>défi du mois</b>, tes <b>records</b> (allure, distance), tes <b>badges</b> et l'historique de tes courses.</p>
      <h4>⚡ Allure → bonus de points</h4>
      <p>Quand tu renseignes <b>distance + temps</b> d'une course, l'appli calcule ton <b>allure</b> (min/km) et
      <b>ajuste les points</b> : plus tu cours vite, plus tu gagnes.</p>
      <ul class="infolist">
        <li>≤ 4 min/km → <b>×1,5</b> · ≤ 5 → ×1,25 · ≤ 6 → ×1,12</li>
        <li>~ 7 min/km → ×1,0 (allure moyenne)</li>
        <li>plus lent → ×0,9 puis ×0,8 (marche)</li>
      </ul>

      <h4>🏋️ L'avatar : 4 physiques × 5 corps</h4>
      <p>Ton avatar évolue <b>automatiquement</b> selon ton <b>volume d'entraînement cumulé</b> (il ne baisse jamais).
      Il y a 20 étapes : 4 morphotypes, chacun avec 5 niveaux de corps.</p>
      <ul class="infolist">
        <li>🦴 <b>Très maigre</b> — au démarrage (corps 1→5)</li>
        <li>🧍 <b>Maigre</b> — un peu d'entraînement</li>
        <li>💪 <b>Musclé</b> — bien développé (pecs &amp; abdos dessinés)</li>
        <li>🐻 <b>Gros</b> — volume énorme, silhouette massive</li>
      </ul>
      <div class="mgtitle">🖼️ Les 4 corpulences × 5 corps (20 silhouettes) :</div>
      <div id="infoGallery" class="mgallery"></div>
      <p>💡 Renseigne ta <b>taille</b> et ton <b>poids</b> dans la fenêtre Avatar : ta silhouette s'ajuste à ton
      <b>IMC</b> réel (maigreur &lt;18,5 · normal 18,5–25 · surpoids 25–30 · obésité &gt;30). L'<b>IMC</b> pilote la
      <b>corpulence</b> (fin ↔ large) et ton <b>entraînement</b> la <b>masse musculaire</b> : un même IMC élevé donne
      un physique <b>musclé</b> si tu t'entraînes, sinon <b>gros</b>.</p>
      <p class="hint">Tu peux aussi changer le <b>teint</b> de la peau dans la fenêtre Avatar.</p>

      <h4>📊 Zones travaillées</h4>
      <p>Dans la fenêtre Avatar, le bloc « Zones travaillées » classe tes parties du corps par <b>volume cumulé</b>
      et t'indique la zone la <b>plus travaillée</b> et celle <b>à renforcer</b> — pour t'entraîner de façon équilibrée.</p>

      <h4>🏅 Niveau, rang &amp; régularité</h4>
      <p>Ton <b>XP</b> total fait monter ton <b>niveau</b> et ton <b>rang</b> (🥉 Débutant → 👑 Légende).
      Ta <b>fréquence</b> (séances/semaine sur 4 semaines) donne un <b>bonus de points</b> (jusqu'à ×1,3 si tu t'entraînes beaucoup).
      À l'inverse, après plus de 7 jours sans séance, les muscles perdent un peu de volume (mais pas ton volume cumulé, donc l'avatar ne régresse pas).</p>

      <h4>📚 Bibliothèque de séances</h4>
      <p>Le bouton « 📚 Séances » ajoute des séances toutes prêtes (abdos, jambes, course VMA, dos salle…)
      directement dans « À faire », exercices déjà cochés. Glisse-les vers ✅ Terminé pour valider.</p>
    </div>
    <div class="foot"><span class="hint">Tout est sauvegardé automatiquement.</span><button class="primary" onclick="closeInfo()">J'ai compris</button></div>
  </div>
</div>

<!-- Modale PROGRÈS / stats -->
<div class="modal" id="statsModal">
  <div class="box" style="max-width:820px">
    <h3>📊 Mes progrès</h3>
    <div id="statHeadline" class="infonow"></div>
    <div class="statgrid">
      <div class="statcard"><h4>🔥 Série</h4><div id="statStreak"></div></div>
      <div class="statcard"><h4>🎯 Défi du mois</h4><div id="statMonth"></div></div>
      <div class="statcard"><h4>🏆 Records</h4><div id="statPR"></div></div>
    </div>
    <h4>Séances par semaine <span class="hint">(12 dernières)</span></h4>
    <canvas id="chartSess" width="760" height="150" style="width:100%;height:auto"></canvas>
    <h4>Poids corporel</h4>
    <canvas id="chartWeight" width="760" height="150" style="width:100%;height:auto"></canvas>
    <div id="statWeightNote" class="hint"></div>
    <h4>🏅 Badges</h4>
    <div id="statBadges" class="badges2"></div>
    <h4>🏃 Dernières courses</h4>
    <div id="statRuns" class="runlist"></div>
    <h4>🗂️ Journal des séances terminées</h4>
    <p class="hint" style="margin:0 0 6px">Tout part d'ici : chaque carte glissée vers ✅ Terminé s'ajoute. Journal vide = stats à zéro. Pour effacer, va dans <b>⚙️ Paramètres</b>.</p>
    <div id="statJournal" class="jlist"></div>
    <div class="foot"><span class="hint">Mets à jour tes séances pour faire grimper les courbes.</span><button class="primary" onclick="closeStats()">Fermer</button></div>
  </div>
</div>

<!-- Modale ⚙️ PARAMÈTRES (zone sensible) -->
<div class="modal" id="settingsModal">
  <div class="box" style="max-width:640px">
    <h3>⚙️ Paramètres</h3>
    <h4>🎨 Thème d'affichage</h4>
    <div id="setTheme" style="display:flex;gap:8px;margin-bottom:14px"></div>
    <div class="warnbox">⚠️ <b>Zone sensible.</b> Les actions ci-dessous effacent tes données de progression. Elles demandent une confirmation et sont <b>irréversibles</b>.</div>
    <div id="setRestore"></div>
    <h4>🗂️ Journal des séances terminées (<span id="setCount">0</span>)</h4>
    <p class="hint" style="margin:0 0 6px">Retire une séance précise (✕) ou vide tout. Les statistiques se recalculent aussitôt à partir du journal.</p>
    <div id="setJournal" class="jlist"></div>
    <div style="margin-top:12px">
      <button class="danger" onclick="clearCompleted()" style="border-color:var(--ph);color:var(--ph)">🗑 Vider tout le journal (remise à zéro)</button>
    </div>
    <div class="foot"><span class="hint">Rien n'est effacé sans confirmation.</span><button class="primary" onclick="closeSettings()">Fermer</button></div>
  </div>
</div>

<div id="toast"></div>

<script>
const $ = s => document.querySelector(s);
const boardEl = $('#board');
let DATA = { columns: [] };
let filter = 'all';
let editing = null;   // {colId, cardId} en édition, ou {colId} pour nouveau

/* ═══ Groupes (regroupement des exercices par zone) ═══ */
const GROUPES = {
  abdos:    {nom:'Abdos & Gainage',      emo:'🔥'},
  pecs:     {nom:'Pectoraux',            emo:'🎽'},
  dos:      {nom:'Dos & Trapèzes',       emo:'🔙'},
  epaules:  {nom:'Épaules',              emo:'🦾'},
  biceps:   {nom:'Biceps',               emo:'💪'},
  triceps:  {nom:'Triceps',              emo:'🦾'},
  avantbras:{nom:'Avant-bras',           emo:'✊'},
  fessiers: {nom:'Fessiers',             emo:'🍑'},
  cuisses:  {nom:'Cuisses',              emo:'🦵'},
  mollets:  {nom:'Mollets',              emo:'🦿'},
  cardio:   {nom:'Cardio & Course',      emo:'🏃'},
  mobilite: {nom:'Mobilité & Souplesse', emo:'🧘'},
};
/* ═══ Catalogue d'exercices → muscles (alimentent l'avatar + le score) ═══
   grp = groupe · niv = difficulté (1 débutant · 2 inter · 3 avancé) · m = points par muscle
   ⚠ les 14 clés d'origine sont conservées (compat. données utilisateur). */
const ACTIONS = {
  /* ── Abdos & gainage ── */
  abdos:            {nom:'Crunch (abdos)',              grp:'abdos', niv:1, m:{abdos:4}},
  releve_jambes:    {nom:'Relevé de jambes',            grp:'abdos', niv:1, m:{abdos:5}},
  sit_up:           {nom:'Sit-up',                      grp:'abdos', niv:1, m:{abdos:5}},
  gainage:          {nom:'Gainage / planche',           grp:'abdos', niv:2, m:{abdos:6}},
  planche_laterale: {nom:'Planche latérale (obliques)', grp:'abdos', niv:2, m:{abdos:5}},
  russian_twist:    {nom:'Russian twist (obliques)',    grp:'abdos', niv:2, m:{abdos:5}},
  mountain_climbers:{nom:'Mountain climbers',           grp:'abdos', niv:2, m:{abdos:4, cardio:3}},
  vacuum:           {nom:'Vacuum (transverse)',         grp:'abdos', niv:2, m:{abdos:4}},
  hollow_hold:      {nom:'Hollow hold',                 grp:'abdos', niv:3, m:{abdos:6}},
  releve_suspendu:  {nom:'Relevé jambes suspendu',      grp:'abdos', niv:3, m:{abdos:7}},
  roue_abdo:        {nom:'Roue abdominale',             grp:'abdos', niv:3, m:{abdos:8}},
  /* ── Pectoraux ── */
  pompes:           {nom:'Pompes',                      grp:'pecs', niv:1, m:{pecs:6, triceps:3, epaules:2}},
  pompes_inclinees: {nom:'Pompes inclinées',            grp:'pecs', niv:1, m:{pecs:5, epaules:2}},
  pompes_serrees:   {nom:'Pompes serrées (diamant)',    grp:'pecs', niv:2, m:{pecs:5, triceps:5}},
  dev_couche:       {nom:'Développé couché',            grp:'pecs', niv:2, m:{pecs:8, triceps:3, epaules:2}},
  dev_incline:      {nom:'Développé incliné',           grp:'pecs', niv:2, m:{pecs:7, epaules:3, triceps:2}},
  ecarte:           {nom:'Écarté (haltères/poulie)',    grp:'pecs', niv:2, m:{pecs:6}},
  dips_pecs:        {nom:'Dips (pectoraux)',            grp:'pecs', niv:3, m:{pecs:6, triceps:4, epaules:2}},
  /* ── Dos ── */
  rowing:           {nom:'Rowing (barre/haltère)',      grp:'dos', niv:2, m:{dos:6, biceps:3, trapezes:3, avantbras:2}},
  tirage_vertical:  {nom:'Tirage vertical',             grp:'dos', niv:2, m:{dos:6, biceps:3}},
  tirage_horizontal:{nom:'Tirage horizontal',           grp:'dos', niv:2, m:{dos:6, biceps:3, trapezes:2}},
  traction:         {nom:'Traction',                    grp:'dos', niv:3, m:{dos:7, biceps:4, avantbras:2}},
  sdt:              {nom:'Soulevé de terre',            grp:'dos', niv:3, m:{dos:6, fessiers:4, ischios:4, trapezes:3}},
  superman:         {nom:'Superman (lombaires)',        grp:'dos', niv:1, m:{dos:4, fessiers:2}},
  shrug:            {nom:'Shrug (trapèzes)',            grp:'dos', niv:1, m:{trapezes:6}},
  /* ── Épaules ── */
  epaules:          {nom:'Développé épaules',           grp:'epaules', niv:2, m:{epaules:6, triceps:2, trapezes:2}},
  dev_militaire:    {nom:'Développé militaire',         grp:'epaules', niv:2, m:{epaules:7, triceps:3, trapezes:2}},
  elevations_lat:   {nom:'Élévations latérales',        grp:'epaules', niv:1, m:{epaules:6}},
  elevations_front: {nom:'Élévations frontales',        grp:'epaules', niv:1, m:{epaules:5}},
  oiseau:           {nom:"Oiseau (arrière d'épaule)",   grp:'epaules', niv:2, m:{epaules:5, trapezes:2}},
  pike_pushup:      {nom:'Pompes piquées',              grp:'epaules', niv:2, m:{epaules:6, triceps:3}},
  /* ── Biceps ── */
  curl:             {nom:'Curl biceps',                 grp:'biceps', niv:1, m:{biceps:7, avantbras:2}},
  curl_marteau:     {nom:'Curl marteau',                grp:'biceps', niv:1, m:{biceps:5, avantbras:4}},
  /* ── Triceps ── */
  triceps:          {nom:'Extension triceps',           grp:'triceps', niv:1, m:{triceps:7}},
  dips_triceps:     {nom:'Dips triceps (banc)',         grp:'triceps', niv:2, m:{triceps:6, epaules:2}},
  barre_front:      {nom:'Barre au front',              grp:'triceps', niv:2, m:{triceps:7}},
  /* ── Avant-bras ── */
  avantbras_ex:     {nom:'Flexions avant-bras',         grp:'avantbras', niv:1, m:{avantbras:6}},
  /* ── Cuisses (quadriceps & ischios) ── */
  squat:            {nom:'Squat',                       grp:'cuisses', niv:2, m:{quads:6, fessiers:5}},
  squat_bulgare:    {nom:'Squat bulgare',               grp:'cuisses', niv:3, m:{quads:6, fessiers:5, ischios:2}},
  fentes:           {nom:'Fentes',                      grp:'cuisses', niv:2, m:{quads:5, fessiers:4, ischios:3}},
  presse:           {nom:'Presse à cuisses',            grp:'cuisses', niv:2, m:{quads:7, fessiers:4}},
  chaise:           {nom:'Chaise (isométrie)',          grp:'cuisses', niv:1, m:{quads:6}},
  jambes_tendues:   {nom:'Soulevé jambes tendues',      grp:'cuisses', niv:2, m:{ischios:7, fessiers:4, dos:2}},
  leg_curl:         {nom:'Leg curl (ischios)',          grp:'cuisses', niv:2, m:{ischios:7}},
  box_jump:         {nom:'Box jump',                    grp:'cuisses', niv:2, m:{quads:5, fessiers:4, mollets:3, cardio:2}},
  /* ── Fessiers ── */
  hip_thrust:       {nom:'Hip thrust (fessiers)',       grp:'fessiers', niv:2, m:{fessiers:8, ischios:3}},
  /* ── Mollets ── */
  mollets:          {nom:'Mollets (extensions)',        grp:'mollets', niv:1, m:{mollets:7}},
  /* ── Cardio & course ── */
  marche:           {nom:'Marche rapide',               grp:'cardio', niv:1, m:{cardio:3}},
  footing:          {nom:'Footing / endurance',         grp:'cardio', niv:1, m:{cardio:6, ischios:2}},
  course_libre:     {nom:'Course (distance libre)',     grp:'cardio', niv:2, m:{cardio:7, quads:2, ischios:2}},
  course1:          {nom:'Course 1 km',                 grp:'cardio', niv:1, m:{cardio:4, quads:1}},
  course:           {nom:'Course 5 km',                 grp:'cardio', niv:2, m:{cardio:8, quads:2, ischios:2}},
  course10:         {nom:'Course 10 km',                grp:'cardio', niv:3, m:{cardio:10, quads:3, ischios:2}},
  fractionne:       {nom:'Fractionné / HIIT',           grp:'cardio', niv:3, m:{cardio:9, quads:3}},
  sprint:           {nom:'Sprint',                      grp:'cardio', niv:2, m:{cardio:6, quads:3, ischios:2}},
  corde:            {nom:'Corde à sauter',              grp:'cardio', niv:1, m:{cardio:6, mollets:3}},
  velo:             {nom:'Vélo',                        grp:'cardio', niv:1, m:{cardio:6, quads:3}},
  natation:         {nom:'Natation',                    grp:'cardio', niv:2, m:{cardio:7, dos:3, epaules:3}},
  rameur:           {nom:'Rameur',                      grp:'cardio', niv:2, m:{cardio:7, dos:4, biceps:2}},
  burpees:          {nom:'Burpees',                     grp:'cardio', niv:3, m:{cardio:7, pecs:2, quads:2, abdos:2}},
  stepper:          {nom:"Montée d'escalier",           grp:'cardio', niv:1, m:{cardio:5, quads:3, fessiers:3, mollets:2}},
  /* ── Mobilité & souplesse ── */
  etirements:       {nom:'Étirements complets',         grp:'mobilite', niv:1, m:{}},
  yoga:             {nom:'Yoga / mobilité',             grp:'mobilite', niv:1, m:{abdos:2, dos:2}},

  /* ═══ AJOUTS — séries complètes par type (avec / sans machine) ═══ */
  /* ── Abdos & gainage (suite) ── */
  crunch_bicyclette:{nom:'Crunch bicyclette (obliques)', grp:'abdos', niv:2, m:{abdos:5}},
  crunch_inverse:   {nom:'Crunch inversé (bas du ventre)',grp:'abdos', niv:2, m:{abdos:5}},
  crunch_machine:   {nom:'Crunch à la machine',          grp:'abdos', niv:1, m:{abdos:6}},
  crunch_poulie:    {nom:'Crunch à la poulie (câble)',   grp:'abdos', niv:2, m:{abdos:6}},
  releve_bassin:    {nom:'Relevé de bassin',             grp:'abdos', niv:1, m:{abdos:4}},
  planche_dynamique:{nom:'Planche dynamique (haut/bas)', grp:'abdos', niv:2, m:{abdos:6, epaules:2}},
  gainage_swiss:    {nom:'Gainage sur ballon (swiss ball)',grp:'abdos', niv:2, m:{abdos:6}},
  oblique_poulie:   {nom:'Rotation oblique à la poulie', grp:'abdos', niv:2, m:{abdos:5}},
  ab_rot_machine:   {nom:'Machine à obliques (rotation)',grp:'abdos', niv:1, m:{abdos:5}},
  wood_chop:        {nom:'Wood chop / bûcheron (câble)', grp:'abdos', niv:2, m:{abdos:6}},
  essuie_glace:     {nom:'Essuie-glace (windshield)',    grp:'abdos', niv:3, m:{abdos:7}},
  toes_to_bar:      {nom:'Toes to bar (barre)',          grp:'abdos', niv:3, m:{abdos:7, avantbras:2}},
  l_sit:            {nom:'L-sit',                        grp:'abdos', niv:3, m:{abdos:7}},
  dragon_flag:      {nom:'Dragon flag',                  grp:'abdos', niv:3, m:{abdos:8}},
  /* ── Pectoraux (suite) ── */
  dev_haltere:      {nom:'Développé haltères',           grp:'pecs', niv:2, m:{pecs:7, triceps:3, epaules:2}},
  dev_decline:      {nom:'Développé décliné',            grp:'pecs', niv:2, m:{pecs:7, triceps:3}},
  pec_deck:         {nom:'Pec deck (machine)',           grp:'pecs', niv:1, m:{pecs:6}},
  dev_machine_pecs: {nom:'Développé à la machine',       grp:'pecs', niv:1, m:{pecs:7, triceps:2}},
  ecarte_poulie:    {nom:'Écarté poulie (cross-over)',   grp:'pecs', niv:2, m:{pecs:6}},
  pull_over:        {nom:'Pull-over (pecs/dos)',         grp:'pecs', niv:2, m:{pecs:5, dos:3}},
  pompes_lestees:   {nom:'Pompes lestées',               grp:'pecs', niv:3, m:{pecs:7, triceps:3, epaules:2}},
  /* ── Dos (suite) ── */
  rowing_machine:   {nom:'Rowing à la machine',          grp:'dos', niv:1, m:{dos:6, biceps:2}},
  tirage_poitrine:  {nom:'Tirage poitrine (lat pulldown)',grp:'dos', niv:2, m:{dos:6, biceps:3}},
  rowing_unilateral:{nom:'Rowing haltère 1 bras',        grp:'dos', niv:2, m:{dos:6, biceps:3, trapezes:2}},
  tirage_serre:     {nom:'Tirage prise serrée',          grp:'dos', niv:2, m:{dos:6, biceps:3}},
  traction_lestee:  {nom:'Traction lestée',              grp:'dos', niv:3, m:{dos:8, biceps:4}},
  face_pull:        {nom:'Face pull (poulie)',           grp:'dos', niv:1, m:{epaules:4, trapezes:4, dos:2}},
  hyperextension:   {nom:'Extensions lombaires (banc)',  grp:'dos', niv:1, m:{dos:5, fessiers:3, ischios:2}},
  /* ── Épaules (suite) ── */
  dev_arnold:       {nom:'Développé Arnold',             grp:'epaules', niv:2, m:{epaules:7, triceps:2}},
  dev_epaules_mach: {nom:'Développé épaules machine',    grp:'epaules', niv:1, m:{epaules:6, triceps:2}},
  elevations_poulie:{nom:'Élévations latérales poulie',  grp:'epaules', niv:2, m:{epaules:6}},
  rowing_menton:    {nom:'Rowing menton (tirage vertical)',grp:'epaules', niv:2, m:{epaules:5, trapezes:4, biceps:2}},
  handstand_pushup: {nom:'Pompes en équilibre',          grp:'epaules', niv:3, m:{epaules:8, triceps:4}},
  /* ── Biceps (suite) ── */
  curl_barre:       {nom:'Curl à la barre',              grp:'biceps', niv:1, m:{biceps:7}},
  curl_pupitre:     {nom:'Curl pupitre (Larry Scott)',   grp:'biceps', niv:2, m:{biceps:7}},
  curl_poulie:      {nom:'Curl à la poulie',             grp:'biceps', niv:1, m:{biceps:6}},
  curl_concentre:   {nom:'Curl concentré',               grp:'biceps', niv:1, m:{biceps:6}},
  /* ── Triceps (suite) ── */
  triceps_poulie:   {nom:'Extension triceps poulie',     grp:'triceps', niv:1, m:{triceps:7}},
  triceps_corde:    {nom:'Triceps à la corde',           grp:'triceps', niv:1, m:{triceps:6}},
  kickback_triceps: {nom:'Kickback triceps',             grp:'triceps', niv:1, m:{triceps:6}},
  /* ── Avant-bras (suite) ── */
  curl_poignets:    {nom:'Curl poignets (avant-bras)',   grp:'avantbras', niv:1, m:{avantbras:6}},
  curl_inverse:     {nom:'Curl inversé (avant-bras)',    grp:'avantbras', niv:1, m:{avantbras:5, biceps:2}},
  suspension_barre: {nom:'Suspension à la barre (grip)', grp:'avantbras', niv:2, m:{avantbras:6}},
  /* ── Cuisses (suite) ── */
  squat_barre:      {nom:'Squat barre (arrière)',        grp:'cuisses', niv:3, m:{quads:7, fessiers:6}},
  front_squat:      {nom:'Squat avant (front squat)',    grp:'cuisses', niv:3, m:{quads:7, fessiers:4}},
  goblet_squat:     {nom:'Goblet squat',                 grp:'cuisses', niv:2, m:{quads:6, fessiers:5}},
  pistol_squat:     {nom:'Pistol squat (1 jambe)',       grp:'cuisses', niv:3, m:{quads:7, fessiers:4}},
  fentes_marchees:  {nom:'Fentes marchées',              grp:'cuisses', niv:2, m:{quads:5, fessiers:5, ischios:3}},
  step_up:          {nom:'Montée sur banc (step-up)',    grp:'cuisses', niv:2, m:{quads:5, fessiers:5}},
  leg_extension:    {nom:'Leg extension (machine quads)',grp:'cuisses', niv:1, m:{quads:7}},
  sdt_roumain:      {nom:'Soulevé de terre roumain',     grp:'cuisses', niv:3, m:{ischios:7, fessiers:5, dos:3}},
  adducteurs:       {nom:'Machine adducteurs',           grp:'cuisses', niv:1, m:{quads:4}},
  /* ── Fessiers (suite) ── */
  abducteurs:       {nom:'Machine abducteurs (fessiers)',grp:'fessiers', niv:1, m:{fessiers:5}},
  kickback_fessier: {nom:'Kickback fessier (poulie)',    grp:'fessiers', niv:1, m:{fessiers:6}},
  pont_fessier:     {nom:'Pont fessier (glute bridge)',  grp:'fessiers', niv:1, m:{fessiers:5, ischios:2}},
  squat_sumo:       {nom:'Squat sumo',                   grp:'fessiers', niv:2, m:{fessiers:5, quads:4}},
  fire_hydrant:     {nom:'Fire hydrant (abduction)',     grp:'fessiers', niv:1, m:{fessiers:4}},
  donkey_kick:      {nom:'Donkey kick',                  grp:'fessiers', niv:1, m:{fessiers:5}},
  /* ── Mollets (suite) ── */
  mollets_assis:    {nom:'Mollets assis (machine)',      grp:'mollets', niv:1, m:{mollets:6}},
  mollets_presse:   {nom:'Mollets à la presse',          grp:'mollets', niv:1, m:{mollets:6}},
  mollets_debout:   {nom:'Mollets debout (barre)',       grp:'mollets', niv:1, m:{mollets:6}},
  mollets_unilat:   {nom:'Mollets sur 1 jambe',          grp:'mollets', niv:2, m:{mollets:6}},
  /* ── Cardio & course à pied (suite) ── */
  footing_recup:    {nom:'Footing récup (léger)',        grp:'cardio', niv:1, m:{cardio:4}},
  course3:          {nom:'Course 3 km',                  grp:'cardio', niv:1, m:{cardio:6, quads:2}},
  tempo_run:        {nom:'Tempo run (allure seuil)',     grp:'cardio', niv:2, m:{cardio:9, quads:3}},
  vma:              {nom:'Séance VMA (30/30, intervalles)',grp:'cardio', niv:3, m:{cardio:10, quads:3}},
  cote_course:      {nom:'Course en côte',               grp:'cardio', niv:3, m:{cardio:9, quads:4, fessiers:3, mollets:2}},
  escaliers_course: {nom:"Course d'escaliers",           grp:'cardio', niv:2, m:{cardio:7, quads:4, mollets:3, fessiers:2}},
  sortie_longue:    {nom:'Sortie longue (>15 km)',       grp:'cardio', niv:3, m:{cardio:12, quads:4, ischios:3}},
  semi_marathon:    {nom:'Semi-marathon (21 km)',        grp:'cardio', niv:3, m:{cardio:14, quads:4, ischios:3}},
  marathon:         {nom:'Marathon (42 km)',             grp:'cardio', niv:3, m:{cardio:16, quads:5, ischios:4}},
  trail:            {nom:'Trail / course nature',        grp:'cardio', niv:3, m:{cardio:11, quads:4, mollets:3, fessiers:2}},
  rando:            {nom:'Randonnée',                    grp:'cardio', niv:1, m:{cardio:5, quads:3, mollets:2}},
  elliptique:       {nom:'Vélo elliptique',              grp:'cardio', niv:1, m:{cardio:6, quads:2, fessiers:2}},
  spinning:         {nom:'Vélo spinning / RPM',          grp:'cardio', niv:2, m:{cardio:8, quads:4}},
  boxe_cardio:      {nom:'Boxe / cardio-boxing',         grp:'cardio', niv:2, m:{cardio:8, epaules:3, abdos:2}},
  /* ── Mobilité & souplesse (suite) ── */
  echauffement_dyn: {nom:'Échauffement dynamique',       grp:'mobilite', niv:1, m:{}},
  foam_roller:      {nom:'Auto-massage (foam roller)',   grp:'mobilite', niv:1, m:{}},
  mobilite_hanches: {nom:'Mobilité des hanches',         grp:'mobilite', niv:1, m:{}},
  mobilite_epaules: {nom:'Mobilité des épaules',         grp:'mobilite', niv:1, m:{epaules:1}},
  pilates:          {nom:'Pilates',                      grp:'mobilite', niv:1, m:{abdos:3, dos:2}},
};
/* Séances prêtes à l'emploi (bibliothèque + tableau par défaut) */
const SEANCES = [
  {nom:'🔥 Abdos express',        cat:'sport', prio:'m', acts:['abdos','gainage','releve_jambes','russian_twist']},
  {nom:'🦵 Jambes & Fessiers',    cat:'sport', prio:'m', acts:['squat','fentes','hip_thrust','mollets']},
  {nom:'🎽 Pectoraux',            cat:'sport', prio:'m', acts:['pompes','dev_couche','ecarte','dips_pecs']},
  {nom:'🔙 Dos & Tractions',      cat:'sport', prio:'m', acts:['traction','rowing','tirage_vertical','superman']},
  {nom:'🦾 Épaules & Bras',       cat:'sport', prio:'m', acts:['dev_militaire','elevations_lat','curl','triceps']},
  {nom:'💪 Haut du corps',        cat:'sport', prio:'m', acts:['pompes','rowing','epaules','curl','triceps']},
  {nom:'🏃 Cardio / Course',      cat:'sport', prio:'m', acts:['course','corde']},
  {nom:'⚡ HIIT full-body',        cat:'sport', prio:'h', acts:['burpees','mountain_climbers','squat','pompes']},
  {nom:'🏋️ Full-body débutant',   cat:'sport', prio:'m', acts:['squat','pompes','gainage','rowing','mollets']},
  {nom:'🧘 Mobilité / Récup',     cat:'sport', prio:'b', acts:['etirements','yoga']},
  {nom:'🔥 Abdos complet',        cat:'sport', prio:'m', acts:['crunch_bicyclette','crunch_inverse','planche_laterale','wood_chop','gainage']},
  {nom:'🔥 Abdos machine (salle)',cat:'sport', prio:'m', acts:['crunch_machine','crunch_poulie','ab_rot_machine','releve_suspendu']},
  {nom:'🦵 Jambes machine (salle)',cat:'sport', prio:'m', acts:['presse','leg_extension','leg_curl','abducteurs','mollets_assis']},
  {nom:'🎽 Pecs salle',           cat:'sport', prio:'m', acts:['dev_couche','dev_incline','pec_deck','ecarte_poulie']},
  {nom:'🔙 Dos salle',            cat:'sport', prio:'m', acts:['tirage_poitrine','rowing_machine','face_pull','hyperextension']},
  {nom:'💪 Bras (biceps/triceps)',cat:'sport', prio:'m', acts:['curl_barre','curl_pupitre','triceps_poulie','triceps_corde']},
  {nom:'🏃 Course — Fractionné VMA',cat:'sport', prio:'h', acts:['echauffement_dyn','vma','etirements']},
  {nom:'🏃 Course — Endurance (tempo)',cat:'sport', prio:'m', acts:['footing_recup','tempo_run','etirements']},
  {nom:'🏔️ Course en côte',       cat:'sport', prio:'m', acts:['cote_course','escaliers_course','foam_roller']},
  {nom:'🏃 Sortie longue',        cat:'sport', prio:'m', acts:['sortie_longue','etirements']},
  {nom:'🥊 Cardio-boxing',        cat:'sport', prio:'m', acts:['boxe_cardio','corde','gainage']},
];
const MUSCLES = { pecs:'Pectoraux', epaules:'Épaules', trapezes:'Trapèzes', biceps:'Biceps',
  triceps:'Triceps', avantbras:'Avant-bras', dos:'Dos', abdos:'Abdos', fessiers:'Fessiers',
  quads:'Cuisses', ischios:'Ischios', mollets:'Mollets', cardio:'Cardio' };
/* Rangs selon le score total (XP) */
const RANGS = [
  {min:0,    nom:'Débutant', emo:'🥉'}, {min:300,  nom:'Amateur',  emo:'🥈'},
  {min:800,  nom:'Confirmé', emo:'🥇'}, {min:1600, nom:'Athlète',  emo:'🏅'},
  {min:3000, nom:'Élite',    emo:'💎'}, {min:5000, nom:'Légende',  emo:'👑'},
];
/* niveau : paliers croissants ; renvoie niveau, progression et rang */
function levelInfo(){
  ensureStats(); const xp=DATA.stats.xp||0;
  let niveau=1, need=100, acc=0;
  while(xp>=acc+need){ acc+=need; niveau++; need=100+(niveau-1)*40; }
  let rang=RANGS[0]; for(const r of RANGS) if(xp>=r.min) rang=r;
  return {xp, niveau, into:xp-acc, toNext:need, pct:Math.round((xp-acc)/need*100), rang:rang.emo+' '+rang.nom};
}

function emptyMuscles(){ const o={}; for(const k in MUSCLES) o[k]=0; return o; }
function ensureStats(){
  if(!DATA.stats) DATA.stats={muscles:emptyMuscles(), history:[], lastDecay:today()};
  if(!DATA.stats.muscles) DATA.stats.muscles=emptyMuscles();
  for(const k in MUSCLES) if(typeof DATA.stats.muscles[k]!=='number') DATA.stats.muscles[k]=0;
  if(!Array.isArray(DATA.stats.history)) DATA.stats.history=[];
  if(typeof DATA.stats.xp!=='number') DATA.stats.xp=0;
  if(!DATA.stats.lastDecay) DATA.stats.lastDecay=today();
  if(!DATA.stats.skin) DATA.stats.skin='#3d2413';   // teint par défaut (brun foncé)
  // volume CUMULÉ par muscle (jamais plafonné ni dégradé) → stats des zones travaillées
  if(!DATA.stats.vol || typeof DATA.stats.vol!=='object') DATA.stats.vol={};
  for(const k in MUSCLES) if(typeof DATA.stats.vol[k]!=='number')
    DATA.stats.vol[k]=Math.round(DATA.stats.muscles[k]||0); // amorçage depuis l'existant
  if(DATA.stats.taille===undefined) DATA.stats.taille=null; // cm
  if(DATA.stats.poids===undefined)  DATA.stats.poids=null;  // kg
  if(DATA.stats.theme===undefined)  DATA.stats.theme='classic'; // 'classic' | 'futur'
  if(!Array.isArray(DATA.stats.xplog))  DATA.stats.xplog=[];  // [{d,g}] points par séance
  if(!Array.isArray(DATA.stats.runs))   DATA.stats.runs=[];   // [{d,ex,km,min,pace}] courses
  if(!Array.isArray(DATA.stats.weights))DATA.stats.weights=[];// [{d,w}] poids corporel
  // ── Journal des séances TERMINÉES = source unique des statistiques ──
  if(!Array.isArray(DATA.stats.completed)){
    DATA.stats.completed=[];
    const had=(DATA.stats.xp||0)>0 || (DATA.stats.history||[]).length ||
              Object.values(DATA.stats.vol||{}).some(v=>v>0);
    if(had){   // importer l'existant en une entrée pour ne pas perdre la progression
      DATA.stats.completed.push({d:(DATA.stats.history||[]).slice(-1)[0]||today(),
        titre:'Historique importé', raw:true, gained:DATA.stats.xp||0,
        mus:Object.assign({},DATA.stats.vol||{}), runs:(DATA.stats.runs||[]).slice()});
    }
  }
}
/* Association muscle → zone du corps (pour les stats regroupées) */
const ZONEMAP={ abdos:'abdos', pecs:'pecs', dos:'dos', trapezes:'dos', epaules:'epaules',
  biceps:'biceps', triceps:'triceps', avantbras:'avantbras', fessiers:'fessiers',
  quads:'cuisses', ischios:'cuisses', mollets:'mollets', cardio:'cardio' };
const SKINS=['#c99a6e','#a5754c','#824f2f','#5e3a20','#4a2c17','#3d2413','#2a1a0e','#1c1109'];
function renderSkinPicker(){
  const el=$('#skinPicker'); if(!el) return; ensureStats();
  el.innerHTML='<span class="hint" style="margin-right:4px">Teint :</span>'+
    SKINS.map(c=>`<button class="skinsw${DATA.stats.skin===c?' on':''}" style="background:${c}" onclick="setSkin('${c}')" title="${c}"></button>`).join('');
}
function setSkin(hex){ ensureStats(); DATA.stats.skin=hex; drawAvatar(); renderSkinPicker(); save(); }
/* Fréquence : séances sur 28 j → par semaine + multiplicateur de gains */
function frequency(){
  ensureStats(); const now=Date.parse(today());
  const last28=DATA.stats.history.filter(d=>(now-Date.parse(d))<=28*864e5).length;
  const perWeek=last28/4; let mult=0.8, label='À améliorer';
  if(perWeek>=4){mult=1.3;label='Excellent 🔥';}
  else if(perWeek>=2){mult=1.1;label='Bien 💪';}
  else if(perWeek>=1){mult=1;label='Correct';}
  return {perWeek, mult, label, total:DATA.stats.history.length};
}
/* Décroissance douce si inactif > 7 j (1×/jour) */
function applyDecay(){
  ensureStats(); const h=DATA.stats.history; if(!h.length) return false;
  const idle=(Date.parse(today())-Date.parse(h[h.length-1]))/864e5;
  if(idle>7 && DATA.stats.lastDecay!==today()){
    for(const k in DATA.stats.muscles) DATA.stats.muscles[k]=Math.max(0, DATA.stats.muscles[k]-1.5);
    DATA.stats.lastDecay=today(); return true;
  }
  return false;
}
/* Parse une distance en km ("5 km", "8,2 km", "400 m"). */
function parseKm(str){
  if(!str) return 0; const s=(''+str).toLowerCase().replace(',','.');
  const m=s.match(/[\d.]+/); if(!m) return 0; const n=parseFloat(m[0]); if(isNaN(n)) return 0;
  if(/km/.test(s)) return n;
  if(/\bm\b|m$|metre|mètre/.test(s)) return n/1000;   // mètres
  return n;                                            // sinon : km par défaut
}
/* Parse un temps en minutes ("25 min", "1h05", "90 s", "5"). */
function parseMin(str){
  if(!str) return 0; const s=(''+str).toLowerCase().replace(',','.'); let min=0, used=false;
  const h=s.match(/(\d+(?:\.\d+)?)\s*h/); if(h){ min+=parseFloat(h[1])*60; used=true;
    const hm=s.match(/h\s*(\d+)/); if(hm) min+=parseInt(hm[1],10); }
  const mn=s.match(/(\d+(?:\.\d+)?)\s*(?:min|mn)/); if(mn){ min+=parseFloat(mn[1]); used=true; }
  const sec=s.match(/(\d+)\s*s\b/); if(sec){ min+=parseInt(sec[1],10)/60; used=true; }
  if(!used){ const n=parseFloat((s.match(/[\d.]+/)||[])[0]); if(!isNaN(n)) min=n; } // nombre seul = minutes
  return min;
}
/* Multiplicateur selon l'allure (min/km) : plus c'est rapide, plus ça rapporte. */
function paceMult(pace){
  if(!pace || !isFinite(pace) || pace<=0) return 1;
  if(pace<=4)   return 1.5;   // très rapide
  if(pace<=4.5) return 1.35;
  if(pace<=5)   return 1.25;
  if(pace<=6)   return 1.12;
  if(pace<=7)   return 1.0;   // allure moyenne
  if(pace<=9)   return 0.9;
  return 0.8;                 // marche / très lent
}
/* Facteur distance : neutre (~×1) vers 5 km, plus la course est longue plus ça rapporte. */
function distMult(km){ return Math.max(0.7, Math.min(1.8, 0.7+km*0.06)); }
/* Valider une séance → accumule les muscles (× fréquence, × allure pour la course) */
/* Contribution PURE d'une séance (sans effet de temps) → points, muscles, courses. */
function sessionGain(card){
  const acts=card.actions||[], reps=card.reps||{}, mus={}, runs=[]; let gained=0;
  acts.forEach(a=>{ const def=ACTIONS[a]; if(!def||!def.m)return;
    let em=1;
    if(def.grp==='cardio'){ const r=reps[a];
      if(r && typeof r==='object'){ const km=parseKm(r.d), min=parseMin(r.t);
        if(km>0 && min>0){ const pace=min/km; em*=paceMult(pace)*distMult(km);
          runs.push({ex:def.nom, km:Math.round(km*100)/100, min:Math.round(min*10)/10, pace:Math.round(pace*100)/100}); } } }
    for(const mm in def.m){ const p=def.m[mm]*em; mus[mm]=(mus[mm]||0)+p; gained+=p; } });
  return {gained:Math.round(gained), mus, runs};
}
/* Recalcule TOUTES les stats à partir du seul journal `completed`. Journal vide → zéro. */
function recomputeStats(){
  ensureStats();
  const mus=emptyMuscles(), vol={}; for(const k in MUSCLES) vol[k]=0;
  const history=[], xplog=[], runs=[]; let xp=0;
  DATA.stats.completed.forEach(e=>{
    const g = e.raw ? {gained:e.gained||0, mus:e.mus||{}, runs:e.runs||[]}
                    : sessionGain({actions:e.actions, reps:e.reps});
    xp+=g.gained; history.push(e.d); xplog.push({d:e.d, g:g.gained});
    for(const m in g.mus){ if(vol[m]===undefined) vol[m]=0; vol[m]+=g.mus[m]; }
    (g.runs||[]).forEach(r=>runs.push(Object.assign({d:e.d}, r)));
  });
  for(const k in MUSCLES) mus[k]=Math.min(100, vol[k]||0);
  DATA.stats.muscles=mus; DATA.stats.vol=vol; DATA.stats.xp=Math.round(xp);
  DATA.stats.history=history; DATA.stats.xplog=xplog; DATA.stats.runs=runs;
}
/* Valider une séance → l'ARCHIVE dans le journal, puis recalcule les stats. */
function completeSession(card){
  ensureStats(); if(!(card.actions && card.actions.length)) return;
  const g=sessionGain(card);
  DATA.stats.completed.push({ d:today(), titre:card.titre||'Séance', cat:card.cat||'sport',
    actions:card.actions.slice(), reps:card.reps?JSON.parse(JSON.stringify(card.reps)):{} });
  if(DATA.stats.completed.length>500) DATA.stats.completed=DATA.stats.completed.slice(-500);
  recomputeStats();
  const lv=levelInfo();
  const fast=g.runs.some(r=>paceMult(r.pace)>1.05), slow=g.runs.some(r=>paceMult(r.pace)<0.95);
  toast(`💪 Terminé · +${g.gained} pts${fast?' · allure ⚡':(slow?' · allure 🐢':'')} — ${lv.rang} (niv. ${lv.niveau})`);
}
/* ── Avatar ── */
/* ── Avatar 2D (canvas) — 4 silhouettes × 5 corps, selon l'entraînement ── */
const MORPHOS=[
  {nom:'Très maigre', emo:'🦴'},
  {nom:'Maigre',      emo:'🧍'},
  {nom:'Musclé',      emo:'💪'},
  {nom:'Gros',        emo:'🐻'},
];
/* Progression 0..19 à partir du VOLUME CUMULÉ (jamais perdu) → 4 morphotypes × 5 corps. */
function bmiLabel(b){ return b<18.5?'maigreur':b<25?'corpulence normale':b<30?'surpoids':'obésité'; }
/* Profil : la CORPULENCE vient de l'IMC (taille/poids), la MUSCULATURE de l'entraînement.
   → morphotype (très maigre / maigre / musclé / gros) + niveau 1..5. */
function avatarProgress(){
  ensureStats();
  const tot=Object.values(DATA.stats.vol||{}).reduce((a,b)=>a+(+b||0),0);
  const muscle=Math.max(0,Math.min(1, tot/1710));               // 0..1 (entraînement)
  const h=+DATA.stats.taille||0, w=+DATA.stats.poids||0;
  const bmi=(h>0&&w>0)? w/Math.pow(h/100,2) : null;
  // corpulence 0..1 depuis l'IMC (16→0 … 34→1) ; sans IMC → basée sur l'entraînement
  const corp=(bmi!=null)? Math.max(0,Math.min(1,(bmi-16)/18)) : muscle;
  let type;
  if(bmi!=null){
    if(bmi<18.5) type=0;                        // très maigre
    else if(bmi<25) type=(muscle>=0.5?2:1);     // normal : musclé si entraîné, sinon maigre
    else if(bmi<30) type=(muscle>=0.6?2:3);     // surpoids : musclé si très entraîné, sinon gros
    else type=(muscle>=0.8?2:3);                // obésité : gros sauf énorme masse musculaire
  } else {
    type=Math.min(3,Math.floor(muscle*3.999));  // sans IMC : progression par entraînement
  }
  const inten=(type===2)?muscle:corp;           // niveau 1..5 selon l'axe dominant
  const niv=Math.max(1,Math.min(5,Math.round(inten*4)+1));
  const step=type*5+(niv-1);
  return {tot:Math.round(tot), bmi, corp, muscle, type, niv, step, morph:MORPHOS[type]};
}
function openAvatar(){
  ensureStats();
  $('#avatarModal').classList.add('on');
  $('#avH').value=DATA.stats.taille||''; $('#avW').value=DATA.stats.poids||'';
  setTimeout(renderAvatar, 30);
}
function closeAvatar(){ $('#avatarModal').classList.remove('on'); }
/* Saisie taille/poids → recalcule l'IMC et la silhouette (sans réécrire les champs en cours de frappe) */
function setBody(){
  ensureStats();
  const h=parseFloat($('#avH').value)||0, w=parseFloat($('#avW').value)||0;
  DATA.stats.taille = h>0 ? h : null;
  DATA.stats.poids  = w>0 ? w : null;
  if(w>0){ const day=today(), arr=DATA.stats.weights, last=arr[arr.length-1];
    if(last && last.d===day) last.w=w; else arr.push({d:day, w});
    if(arr.length>400) DATA.stats.weights=arr.slice(-400); }
  renderAvatar(); save();
}
/* Éclaircit (+) / assombrit (−) une couleur hex. */
function shade(hex,amt){
  hex=(hex||'#c99a6e').replace('#',''); if(hex.length===3) hex=hex.split('').map(c=>c+c).join('');
  const n=parseInt(hex,16), cl=v=>Math.max(0,Math.min(255,v));
  return 'rgb('+cl(((n>>16)&255)+amt)+','+cl(((n>>8)&255)+amt)+','+cl((n&255)+amt)+')';
}
/* Membre arrondi (capsule effilée r1→r2) entre deux articulations. */
function limb(ctx,x1,y1,r1,x2,y2,r2){
  const dx=x2-x1,dy=y2-y1,L=Math.hypot(dx,dy)||1,px=-dy/L,py=dx/L;
  ctx.beginPath();
  ctx.moveTo(x1+px*r1,y1+py*r1); ctx.lineTo(x2+px*r2,y2+py*r2);
  ctx.lineTo(x2-px*r2,y2-py*r2); ctx.lineTo(x1-px*r1,y1-py*r1);
  ctx.closePath(); ctx.fill();
  ctx.beginPath(); ctx.arc(x1,y1,r1,0,7); ctx.fill();
  ctx.beginPath(); ctx.arc(x2,y2,r2,0,7); ctx.fill();
}
/* Corps paramétré (muscle 0..1, corpulence 0..1), rendu lissé + ombré.
   Réutilisé par l'avatar ET la galerie d'aide. */
function paintBody(ctx, W, H, muscle, corp, skin){
  ctx.clearRect(0,0,W,H); skin=skin||'#c99a6e';
  const cx=W/2;
  const sh   = W*(0.20+muscle*0.16+corp*0.03);   // demi-épaule
  const chest= W*(0.15+muscle*0.12+corp*0.07);
  const waist= W*(0.09+corp*0.20+muscle*0.02);
  const hip  = W*(0.12+corp*0.12+muscle*0.03);
  const uArm = W*(0.035+muscle*0.055+corp*0.015);
  const fArm = W*(0.028+muscle*0.032+corp*0.012);
  const thigh= W*(0.058+muscle*0.045+corp*0.045);
  const calf = W*(0.040+muscle*0.030+corp*0.022);
  const belly= Math.max(0, corp-muscle*0.4);
  const headR=W*0.082, headCy=H*0.10, neckY=headCy+headR*0.86,
        shY=H*0.205, chestY=H*0.30, waistY=H*0.43, hipY=H*0.53,
        kneeY=H*0.735, ankY=H*0.945;
  ctx.lineJoin='round'; ctx.lineCap='round';
  // ombre au sol
  ctx.save(); ctx.fillStyle='rgba(0,0,0,.30)';
  ctx.beginPath(); ctx.ellipse(cx, ankY+H*0.02, hip*1.7, H*0.014,0,0,7); ctx.fill(); ctx.restore();
  // couleur de peau UNIE (une seule couleur partout)
  ctx.fillStyle=skin;
  // jambes (cuisse + mollet + galbes musculaires)
  [-1,1].forEach(s=>{
    const hipX=cx+s*hip*0.55, kneeX=cx+s*hip*0.42, ankX=cx+s*hip*0.32;
    limb(ctx, hipX,hipY, thigh, kneeX,kneeY, calf*0.95);           // cuisse
    limb(ctx, kneeX,kneeY, calf*0.95, ankX,ankY, calf*0.5);        // mollet
    // galbe quadriceps + mollet (léger si maigre, marqué si musclé) — même couleur
    ctx.beginPath(); ctx.ellipse((hipX+kneeX)/2, (hipY+kneeY)/2, thigh*(0.6+muscle*0.28), (kneeY-hipY)*0.4, 0,0,7); ctx.fill();
    ctx.beginPath(); ctx.ellipse((kneeX+ankX)/2, (kneeY+ankY)*0.5-H*0.015, calf*(0.7+muscle*0.3), (ankY-kneeY)*0.3, 0,0,7); ctx.fill();
    // pied allongé vers l'avant
    ctx.beginPath(); ctx.ellipse(ankX+s*calf*0.55, ankY+H*0.006, calf*1.3, H*0.02,0,0,7); ctx.fill();
  });
  // bras (biceps + avant-bras + main)
  [-1,1].forEach(s=>{
    const shX=cx+s*sh*0.92, elbX=cx+s*(waist+uArm*1.3), wrX=cx+s*(hip+fArm*0.6);
    limb(ctx, shX,shY+H*0.01, uArm, elbX,waistY, fArm*1.15);        // haut du bras
    limb(ctx, elbX,waistY, fArm*1.05, wrX,hipY+H*0.03, fArm*0.7);   // avant-bras
    // galbe biceps
    ctx.beginPath(); ctx.ellipse((shX+elbX)/2, (shY+waistY)/2+H*0.005, uArm*(0.72+muscle*0.4), (waistY-shY)*0.32, 0,0,7); ctx.fill();
    // main : paume allongée + pouce
    const hy=hipY+H*0.045+fArm*0.5;
    ctx.beginPath(); ctx.ellipse(wrX, hy, fArm*0.78, fArm*1.15, 0,0,7); ctx.fill();
    ctx.beginPath(); ctx.arc(wrX-s*fArm*0.55, hy-fArm*0.35, fArm*0.36,0,7); ctx.fill();
  });
  // torse lissé (bidon éventuel)
  const bx=belly*W*0.12;
  ctx.beginPath();
  ctx.moveTo(cx, shY-H*0.015);
  ctx.lineTo(cx+sh*0.96, shY);
  ctx.quadraticCurveTo(cx+chest, chestY, cx+waist+bx, waistY);
  ctx.quadraticCurveTo(cx+hip+bx*0.5, hipY-H*0.015, cx+hip*0.7, hipY);
  ctx.lineTo(cx-hip*0.7, hipY);
  ctx.quadraticCurveTo(cx-hip-bx*0.5, hipY-H*0.015, cx-waist-bx, waistY);
  ctx.quadraticCurveTo(cx-chest, chestY, cx-sh*0.96, shY);
  ctx.closePath(); ctx.fill();
  // deltoïdes ronds
  [-1,1].forEach(s=>{ ctx.beginPath(); ctx.arc(cx+s*sh*0.9, shY+H*0.006, uArm*1.05,0,7); ctx.fill(); });
  // cou + tête
  ctx.beginPath(); ctx.moveTo(cx-W*0.045,neckY); ctx.lineTo(cx+W*0.045,neckY);
  ctx.lineTo(cx+W*0.035,shY+H*0.012); ctx.lineTo(cx-W*0.035,shY+H*0.012); ctx.closePath(); ctx.fill();
  ctx.beginPath(); ctx.ellipse(cx,headCy,headR*0.92,headR,0,0,7); ctx.fill();
  // formes de muscle : TOUJOURS visibles (légères si maigre, marquées si musclé)
  {
    const a=Math.min(0.6, 0.12+muscle*0.5);
    ctx.save(); ctx.globalAlpha=a; ctx.strokeStyle=shade(skin,-70);
    ctx.lineWidth=Math.max(1,W*0.005); ctx.lineJoin='round'; ctx.lineCap='round';
    // pectoraux
    ctx.beginPath(); ctx.moveTo(cx-chest*0.72,chestY-H*0.015); ctx.quadraticCurveTo(cx,chestY+H*0.035,cx+chest*0.72,chestY-H*0.015); ctx.stroke();
    // ligne médiane du torse
    ctx.beginPath(); ctx.moveTo(cx,chestY); ctx.lineTo(cx,waistY+H*0.015); ctx.stroke();
    // tablettes d'abdos
    for(let r=0;r<3;r++){ const y=chestY+H*0.06+r*H*0.038;
      ctx.beginPath(); ctx.moveTo(cx-waist*0.42,y); ctx.lineTo(cx+waist*0.42,y); ctx.stroke(); }
    // séparation du biceps sur chaque bras
    [-1,1].forEach(s=>{ const mx=cx+s*((sh*0.92)+(waist+uArm*1.3))/2, my=(shY+waistY)/2;
      ctx.beginPath(); ctx.moveTo(mx, my-H*0.028); ctx.lineTo(mx, my+H*0.028); ctx.stroke(); });
    ctx.restore();
  }
  // nombril si bidon
  if(belly>0.25){ ctx.save(); ctx.globalAlpha=0.25; ctx.fillStyle=shade(skin,-60);
    ctx.beginPath(); ctx.ellipse(cx,waistY+H*0.05,W*0.012,H*0.012,0,0,7); ctx.fill(); ctx.restore(); }
}
/* Avatar principal (selon le profil réel). */
function drawAvatar(){
  const cv=document.getElementById('avCanvas'); if(!cv||!cv.getContext) return null;
  const pr=avatarProgress();
  paintBody(cv.getContext('2d'), cv.width, cv.height, pr.muscle, pr.corp, DATA.stats.skin||'#c99a6e');
  return pr;
}
/* Presets (muscle, corpulence) pour chaque morphotype × niveau 1..5 (galerie d'aide). */
function presetBody(type, niv){
  const t=(niv-1)/4;
  if(type===0) return {muscle:0.02+t*0.10, corp:0.02+t*0.12};   // très maigre
  if(type===1) return {muscle:0.10+t*0.20, corp:0.18+t*0.14};   // maigre
  if(type===2) return {muscle:0.55+t*0.45, corp:0.35+t*0.12};   // musclé
  return          {muscle:0.05+t*0.08, corp:0.60+t*0.40};       // gros
}
/* Galerie : les 4 corpulences × 5 corps dessinées en miniature dans l'aide. */
function renderMorphoGallery(){
  const host=$('#infoGallery'); if(!host) return;
  const skin=DATA.stats.skin||'#c99a6e';
  host.innerHTML=MORPHOS.map((mo,type)=>{
    let cells='';
    for(let n=1;n<=5;n++) cells+=`<div class="mgc"><canvas width="54" height="86" data-t="${type}" data-n="${n}"></canvas><span>${n}</span></div>`;
    return `<div class="mgrow"><div class="mgh">${mo.emo} ${mo.nom}</div><div class="mgcells">${cells}</div></div>`;
  }).join('');
  host.querySelectorAll('canvas').forEach(cv=>{
    const p=presetBody(+cv.dataset.t, +cv.dataset.n);
    paintBody(cv.getContext('2d'), cv.width, cv.height, p.muscle, p.corp, skin);
  });
}

function renderAvatar(){
  const m=DATA.stats.muscles;
  const prog=drawAvatar();
  const cap=$('#avCaption');
  if(prog && cap) cap.innerHTML=`<b>${prog.morph.emo} ${prog.morph.nom}</b> · corps ${prog.niv}/5 <span class="hint">(étape ${prog.step+1}/20)</span>`;
  const bmiEl=$('#avBmi');
  if(bmiEl) bmiEl.textContent = (prog && prog.bmi!=null)
    ? `IMC ${prog.bmi.toFixed(1)} — ${bmiLabel(prog.bmi)}`
    : 'Renseigne ta taille et ton poids ↑';
  renderSkinPicker();
  $('#avatarBars').innerHTML=Object.keys(MUSCLES).map(k=>{
    const v=Math.round(m[k]||0);
    return `<div class="mbar"><span>${MUSCLES[k]}</span><div class="mtrack"><i style="width:${v}%"></i></div><b>${v}%</b></div>`;
  }).join('');
  const f=frequency(); const lv=levelInfo();
  const glob=Math.round(Object.values(m).reduce((a,b)=>a+b,0)/Object.keys(MUSCLES).length);
  $('#avatarStats').innerHTML=`
    <div class="lvlbox">
      <div class="lvlrow"><span class="rang">${lv.rang}</span><span class="niv">Niveau ${lv.niveau}</span></div>
      <div class="xptrack"><i style="width:${lv.pct}%"></i></div>
      <div class="xptxt">${lv.into} / ${lv.toNext} XP vers niv. ${lv.niveau+1} · total <b>${lv.xp}</b></div>
    </div>
    <div>🎯 Développement global : <b>${glob}%</b></div>
    <div>✅ Séances validées : <b>${f.total}</b></div>
    <div>📅 Fréquence : <b>${f.perWeek.toFixed(1)}/sem</b> — ${f.label}</div>`;
  renderZoneStats();
}
/* 📊 Statistiques des zones du corps travaillées (volume cumulé regroupé) */
function zoneStats(){
  ensureStats(); const vol=DATA.stats.vol||{}; const z={};
  for(const mu in vol){ const zone=ZONEMAP[mu]; if(!zone) continue; z[zone]=(z[zone]||0)+(vol[mu]||0); }
  const rows=Object.keys(z).map(k=>({zone:k, val:Math.round(z[k]),
    nom:(GROUPES[k]&&GROUPES[k].nom)||k, emo:(GROUPES[k]&&GROUPES[k].emo)||'•'}))
    .sort((a,b)=>b.val-a.val);
  const total=rows.reduce((s,r)=>s+r.val,0), max=rows.length?rows[0].val:0;
  return {rows, total, max};
}
function renderZoneStats(){
  const el=$('#zoneStats'); if(!el) return;
  const {rows,total,max}=zoneStats();
  if(!total){ el.innerHTML='<div class="zhead">📊 Zones travaillées</div><div class="hint">Valide des séances pour voir tes zones se remplir.</div>'; return; }
  const worked=rows.filter(r=>r.val>0);
  const top=worked[0], low=worked[worked.length-1];
  el.innerHTML='<div class="zhead">📊 Zones travaillées <span class="hint">(volume cumulé)</span></div>'+
    rows.map(r=>{
      const pct=total?Math.round(r.val/total*100):0;
      const w=max?Math.round(r.val/max*100):0;
      return `<div class="zrow"><span class="zn">${r.emo} ${r.nom}</span>
        <div class="ztrack"><i style="width:${w}%"></i></div>
        <b class="zv">${r.val}</b><span class="zp">${pct}%</span></div>`;
    }).join('')+
    `<div class="zreco">🏆 Zone la + travaillée : <b>${top.emo} ${top.nom}</b>`+
    (low && low!==top ? ` · 👀 à renforcer : <b>${low.emo} ${low.nom}</b>` : '')+`</div>`;
}
let lastAuto='';
function autoBlock(sel){
  const list=sel.map(k=>ACTIONS[k]?('- '+ACTIONS[k].nom):null).filter(Boolean).join('\n');
  return list ? ('💪 Exercices :\n'+list) : '';
}
/* Coche/décoche un exercice → met à jour le bloc « Exercices » dans les Détails
   sans effacer les notes perso écrites en dessous. */
function syncActionsToText(){
  const sel=[...document.querySelectorAll('#cmActions input[type=checkbox]:checked')].map(i=>i.value);
  const auto=autoBlock(sel), ta=$('#cmDesc');
  let rest=ta.value;
  if(lastAuto && rest.slice(0,lastAuto.length)===lastAuto) rest=rest.slice(lastAuto.length).replace(/^\n+/,'');
  ta.value = auto + (rest ? ((auto?'\n\n':'')+rest) : '');
  lastAuto=auto;
}
function renderActionChecks(sel, reps){ sel=sel||[]; reps=reps||{};
  const byGrp={}; for(const k in GROUPES) byGrp[k]=[];
  for(const [k,v] of Object.entries(ACTIONS)){ (byGrp[v.grp]||(byGrp[v.grp]=[])).push([k,v]); }
  $('#cmActions').innerHTML=Object.keys(GROUPES).map(g=>{
    const items=byGrp[g]||[]; if(!items.length) return '';
    const chk=items.filter(([k])=>sel.includes(k)).length;
    return `<details class="exgrp"${chk?' open':''}>
      <summary>${GROUPES[g].emo} ${GROUPES[g].nom} <span class="gc">${chk?chk+' ✓':items.length}</span></summary>
      <div class="exlist">`+items.map(([k,v])=>{
        const pts=Object.values(v.m||{}).reduce((a,b)=>a+b,0);
        const rv=reps[k], isObj=(rv&&typeof rv==='object'); let qty;
        const gv=(f)=>isObj?(rv[f]||''):''; const es=(x)=>x?esc(x):'';
        if(v.grp==='cardio'){                       // course/cardio → distance + temps
          const od=isObj?(rv.d||''):(typeof rv==='string'?rv:'');
          qty=`<span class="exqwrap">`+
            `<input class="exq exd" data-ex="${k}" data-role="d" type="text" maxlength="10" placeholder="dist." title="Distance, ex. 5 km" value="${es(od)}" onclick="event.stopPropagation()">`+
            `<input class="exq ext" data-ex="${k}" data-role="t" type="text" maxlength="10" placeholder="temps" title="Temps, ex. 25 min" value="${es(gv('t'))}" onclick="event.stopPropagation()"></span>`;
        } else if(v.grp==='mobilite'){              // mobilité → durée
          const ov=(typeof rv==='string')?rv:'';
          qty=`<input class="exq" data-ex="${k}" type="text" maxlength="12" placeholder="durée" title="Ex. 10 min" value="${es(ov)}" onclick="event.stopPropagation()">`;
        } else {                                    // muscu → séries / reps / poids
          qty=`<span class="exqwrap">`+
            `<input class="exq exs" data-ex="${k}" data-role="s" type="text" maxlength="4" placeholder="sér." title="Séries" value="${es(gv('s'))}" onclick="event.stopPropagation()">`+
            `<input class="exq exr" data-ex="${k}" data-role="r" type="text" maxlength="5" placeholder="rép." title="Répétitions" value="${es(gv('r'))}" onclick="event.stopPropagation()">`+
            `<input class="exq exw" data-ex="${k}" data-role="w" type="text" maxlength="6" placeholder="kg" title="Poids (kg)" value="${es(gv('w'))}" onclick="event.stopPropagation()"></span>`;
        }
        return `<label class="exitem"><input type="checkbox" value="${k}" ${sel.includes(k)?'checked':''}>
          <span class="exn">${v.nom}</span>${qty}
          <span class="exp niv${v.niv}" title="Niveau ${v.niv} · +${pts} pts">${pts?'+'+pts:'·'}</span></label>`;
      }).join('')+`</div></details>`;
  }).join('');
  lastAuto=autoBlock(sel);   // aligne sur les actions déjà présentes (édition d'une carte)
  $('#cmActions').querySelectorAll('input[type=checkbox]').forEach(i=>i.addEventListener('change', ()=>{ syncActionsToText(); refreshGroupCounts(); }));
  // saisir une quantité coche automatiquement l'exercice
  $('#cmActions').querySelectorAll('.exq').forEach(q=>q.addEventListener('input', ()=>{
    const item=q.closest('.exitem'); const cb=item&&item.querySelector('input[type=checkbox]');
    if(q.value.trim() && cb && !cb.checked){ cb.checked=true; syncActionsToText(); refreshGroupCounts(); }
  }));
}
function refreshGroupCounts(){
  document.querySelectorAll('#cmActions .exgrp').forEach(d=>{
    const n=d.querySelectorAll('input:checked').length, tot=d.querySelectorAll('input').length;
    const gc=d.querySelector('.gc'); if(gc) gc.textContent = n?`${n} ✓`:tot;
  });
}
/* ─── Bibliothèque de séances prêtes à l'emploi ─── */
function seancePts(acts){ return acts.reduce((t,k)=>t+Object.values(ACTIONS[k]&&ACTIONS[k].m||{}).reduce((a,b)=>a+b,0),0); }
function openInfo(){
  const pr=avatarProgress(); const el=$('#infoNow');
  if(el) el.innerHTML=`<b>${pr.morph.emo} ${pr.morph.nom}</b> · corps ${pr.niv}/5 `+
    (pr.bmi!=null?`<span class="hint">· IMC ${pr.bmi.toFixed(1)} (${bmiLabel(pr.bmi)})</span> `:'')+
    `<span class="hint">(étape ${pr.step+1}/20 · ${pr.tot} pts de volume)</span>`;
  $('#infoModal').classList.add('on');
  renderMorphoGallery();
}
function closeInfo(){ $('#infoModal').classList.remove('on'); }

/* ─── 📊 Progrès (graphiques, série, défis, records, badges) ─── */
function ymd(ts){ const d=new Date(ts); return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }
function fmtPace(p){ if(!p||!isFinite(p)) return '—'; const m=Math.floor(p), s=Math.round((p-m)*60); return m+':'+String(s).padStart(2,'0')+' /km'; }
function openStats(){ ensureStats(); $('#statsModal').classList.add('on'); setTimeout(renderProgress,30); }
function closeStats(){ $('#statsModal').classList.remove('on'); }
function renderProgress(){
  ensureStats();
  const lv=levelInfo(), el=$('#statHeadline');
  if(el) el.innerHTML=`${lv.rang} · Niveau <b>${lv.niveau}</b> · <b>${lv.xp}</b> XP · <b>${DATA.stats.history.length}</b> séances`;
  renderSessChart(); renderWeightChart(); renderStreak(); renderMonth(); renderRecords(); renderBadges(); renderRuns(); renderJournal();
}
function renderJournal(){
  const el=$('#statJournal'); if(!el) return; const list=DATA.stats.completed||[];
  if(!list.length){ el.innerHTML='<span class="hint">Journal vide → toutes les statistiques sont à zéro. Fais glisser une carte vers ✅ Terminé.</span>'; return; }
  el.innerHTML=list.map((e,i)=>({e,i})).reverse().map(({e,i})=>{
    const g = e.raw ? (e.gained||0) : sessionGain({actions:e.actions, reps:e.reps}).gained;
    return `<div class="jrow"><span>${fmtDate(e.d)}</span><b>${esc(e.titre||'Séance')}</b><span class="jp">+${g}</span></div>`;
  }).join('');
}
/* ─── ⚙️ Paramètres (zone sensible : effacements protégés) ─── */
function openSettings(){ ensureStats(); $('#settingsModal').classList.add('on'); setTimeout(renderSettings,20); }
function closeSettings(){ $('#settingsModal').classList.remove('on'); }
function renderSettings(){
  const c=$('#setCount'); if(c) c.textContent=(DATA.stats.completed||[]).length;
  const th=$('#setTheme'); if(th){ const cur=DATA.stats.theme||'classic';
    th.innerHTML=`<button class="chip${cur==='classic'?' on':''}" onclick="setTheme('classic')">Actuel</button>`+
      `<button class="chip${cur==='futur'?' on':''}" onclick="setTheme('futur')">Futuriste ✨</button>`; }
  const r=$('#setRestore'); if(r) r.innerHTML = lastDeletedCol
    ? `<div class="warnbox" style="background:rgba(52,211,153,.10);border-color:rgba(52,211,153,.4)">↩️ Colonne « ${esc(lastDeletedCol.col.title)} » récemment supprimée — <button class="jclear" onclick="undoDelCol();renderSettings()">Restaurer</button></div>`
    : '';
  const el=$('#setJournal'); if(!el) return; const list=DATA.stats.completed||[];
  if(!list.length){ el.innerHTML='<span class="hint">Journal vide — statistiques à zéro.</span>'; return; }
  el.innerHTML=list.map((e,i)=>({e,i})).reverse().map(({e,i})=>{
    const g = e.raw ? (e.gained||0) : sessionGain({actions:e.actions, reps:e.reps}).gained;
    return `<div class="jrow"><span>${fmtDate(e.d)}</span><b>${esc(e.titre||'Séance')}</b><span class="jp">+${g}</span><button class="jdel" onclick="delCompleted(${i})" title="Retirer cette séance">✕</button></div>`;
  }).join('');
}
function delCompleted(i){ ensureStats(); const e=(DATA.stats.completed||[])[i]; if(!e) return;
  if(!confirm('⚠️ Retirer « '+(e.titre||'Séance')+' » du '+fmtDate(e.d)+' ?\n\nLes statistiques seront recalculées. Action irréversible.')) return;
  DATA.stats.completed.splice(i,1); recomputeStats(); save(); renderSettings(); renderProgress();
  toast('🗑 Séance retirée · stats recalculées'); }
function clearCompleted(){ ensureStats(); const n=(DATA.stats.completed||[]).length;
  if(!n){ alert('Le journal est déjà vide.'); return; }
  if(!confirm('⚠️ ATTENTION — Vider TOUT le journal ('+n+' séance'+(n>1?'s':'')+') ?\n\nToutes tes statistiques (XP, niveau, muscles, avatar, zones, records, badges) repartiront de ZÉRO.\nCette action est IRRÉVERSIBLE.')) return;
  if(!confirm('Dernière confirmation : tout effacer et remettre à zéro ?')) return;
  DATA.stats.completed=[]; recomputeStats(); save(); renderSettings(); renderProgress();
  alert('✅ Journal vidé. Toutes les statistiques sont à zéro.'); }
function renderSessChart(){
  const cv=$('#chartSess'); if(!cv||!cv.getContext) return; const ctx=cv.getContext('2d'), W=cv.width, H=cv.height; ctx.clearRect(0,0,W,H);
  const now=Date.parse(today()), b=[];
  for(let i=11;i>=0;i--) b.push({s:now-(i+1)*7*864e5, e:now-i*7*864e5, c:0});
  DATA.stats.history.forEach(d=>{ const t=Date.parse(d); b.forEach(x=>{ if(t>=x.s && t<x.e) x.c++; }); });
  const max=Math.max(1,...b.map(x=>x.c)), pad=16, bw=(W-pad*2)/b.length;
  ctx.strokeStyle='rgba(255,255,255,.12)'; ctx.beginPath(); ctx.moveTo(pad,H-pad); ctx.lineTo(W-pad,H-pad); ctx.stroke();
  b.forEach((x,i)=>{ const h=(x.c/max)*(H-pad*2), bx=pad+i*bw;
    ctx.fillStyle='#5b8cff'; ctx.fillRect(bx+bw*0.18, H-pad-h, bw*0.64, h);
    if(x.c){ ctx.fillStyle='#cdd6ea'; ctx.font='10px sans-serif'; ctx.textAlign='center'; ctx.fillText(x.c, bx+bw*0.5, H-pad-h-3); }
  });
}
function renderWeightChart(){
  const cv=$('#chartWeight'); if(!cv||!cv.getContext) return; const ctx=cv.getContext('2d'), W=cv.width, H=cv.height; ctx.clearRect(0,0,W,H);
  const w=DATA.stats.weights, note=$('#statWeightNote');
  if(!w.length){ if(note) note.textContent='Renseigne ton poids dans la fenêtre 🏋️ Avatar pour suivre son évolution.'; return; }
  const vals=w.map(x=>x.w), mn=Math.min(...vals), mx=Math.max(...vals), range=(mx-mn)||1, pad=26;
  const X=i=> pad+(w.length===1?0.5:i/(w.length-1))*(W-pad*2), Y=v=> H-pad-((v-mn)/range)*(H-pad*2);
  ctx.strokeStyle='#34d399'; ctx.lineWidth=2; ctx.lineJoin='round'; ctx.beginPath();
  w.forEach((x,i)=>{ const px=X(i),py=Y(x.w); i?ctx.lineTo(px,py):ctx.moveTo(px,py); }); ctx.stroke();
  ctx.fillStyle='#34d399'; w.forEach((x,i)=>{ ctx.beginPath(); ctx.arc(X(i),Y(x.w),2.5,0,7); ctx.fill(); });
  ctx.fillStyle='#98a7c8'; ctx.font='10px sans-serif'; ctx.textAlign='left'; ctx.fillText(mx+' kg',2,12); ctx.fillText(mn+' kg',2,H-4);
  if(note){ const f=w[0].w, l=w[w.length-1].w, diff=Math.round((l-f)*10)/10;
    note.textContent=`De ${f} à ${l} kg (${diff>0?'+':''}${diff} kg) sur ${w.length} mesure${w.length>1?'s':''}.`; }
}
function renderStreak(){
  const el=$('#statStreak'); if(!el) return; const set=new Set(DATA.stats.history);
  let n=0, d=Date.parse(today()); if(!set.has(ymd(d))) d-=864e5;
  while(set.has(ymd(d))){ n++; d-=864e5; }
  el.innerHTML=`<b style="font-size:1.5rem">${n}</b> jour${n>1?'s':''} d'affilée`;
}
function pbar(v,g){ const p=Math.min(100,Math.round(v/g*100)); return `<div class="pbar"><i style="width:${p}%"></i></div>`; }
function renderMonth(){
  const el=$('#statMonth'); if(!el) return; const m=today().slice(0,7);
  const sess=DATA.stats.history.filter(d=>d.slice(0,7)===m).length;
  const km=DATA.stats.runs.filter(r=>String(r.d).slice(0,7)===m).reduce((a,r)=>a+(r.km||0),0);
  el.innerHTML=`<div>Séances <b>${sess}/12</b>${pbar(sess,12)}</div><div>Distance <b>${Math.round(km*10)/10}/50 km</b>${pbar(km,50)}</div>`;
}
function renderRecords(){
  const el=$('#statPR'); if(!el) return; const runs=DATA.stats.runs;
  if(!runs.length){ el.innerHTML='<span class="hint">Aucune course enregistrée.</span>'; return; }
  const longest=runs.reduce((a,r)=>r.km>a.km?r:a), fastest=runs.reduce((a,r)=>r.pace<a.pace?r:a);
  el.innerHTML=`Plus longue : <b>${longest.km} km</b><br>Meilleure allure : <b>${fmtPace(fastest.pace)}</b>`;
}
function renderBadges(){
  const el=$('#statBadges'); if(!el) return;
  const totalKm=DATA.stats.runs.reduce((a,r)=>a+(r.km||0),0), sess=DATA.stats.history.length,
        xp=DATA.stats.xp||0, longest=DATA.stats.runs.reduce((a,r)=>Math.max(a,r.km||0),0);
  const defs=[
    {emo:'🏃',nom:'5 km',ok:longest>=5},{emo:'🎽',nom:'10 km',ok:longest>=10},
    {emo:'🏅',nom:'Semi 21 km',ok:longest>=21},{emo:'🛣️',nom:'100 km cumulés',ok:totalKm>=100},
    {emo:'💪',nom:'10 séances',ok:sess>=10},{emo:'🔥',nom:'50 séances',ok:sess>=50},
    {emo:'⭐',nom:'1000 XP',ok:xp>=1000},{emo:'👑',nom:'5000 XP',ok:xp>=5000},
  ];
  el.innerHTML=defs.map(d=>`<span class="bdg${d.ok?' on':''}">${d.emo} ${d.nom}${d.ok?' ✓':''}</span>`).join('');
}
function renderRuns(){
  const el=$('#statRuns'); if(!el) return; const runs=DATA.stats.runs.slice(-10).reverse();
  if(!runs.length){ el.innerHTML='<span class="hint">Valide une course (avec distance + temps) pour la voir ici.</span>'; return; }
  el.innerHTML=runs.map(r=>`<div class="runrow"><span>${fmtDate(r.d)}</span><b>${r.km} km</b><span>${r.min} min</span><span class="rpace">${fmtPace(r.pace)}</span></div>`).join('');
}
function openLib(){ renderLib(); $('#libModal').classList.add('on'); }
function closeLib(){ $('#libModal').classList.remove('on'); }
function renderLib(){
  $('#libList').innerHTML=SEANCES.map((s,i)=>{
    const ex=s.acts.map(k=>ACTIONS[k]?ACTIONS[k].nom:k).join(' · ');
    return `<div class="libcard">
      <div class="libtop"><b>${esc(s.nom)}</b><span class="libpts">+${seancePts(s.acts)} pts</span></div>
      <div class="libex">${esc(ex)}</div>
      <button class="primary" onclick="addSeance(${i})">＋ Ajouter au tableau</button>
    </div>`;
  }).join('');
}
function addSeance(i){
  const s=SEANCES[i]; if(!s) return;
  const target=DATA.columns.find(c=>/faire/i.test(c.title))||DATA.columns[0];
  target.cards.push({id:col(), titre:s.nom, cat:s.cat, prio:s.prio, due:'', desc:'Séance prédéfinie — glisse-la vers ✅ Terminé pour valider.', actions:s.acts.slice()});
  save(); render(); closeLib(); toast('✅ Séance ajoutée : '+s.nom);
}
function toast(msg){ const t=$('#toast'); t.textContent=msg; t.classList.add('on'); clearTimeout(t._t); t._t=setTimeout(()=>t.classList.remove('on'),2600); }
/* Toast avec bouton « Annuler » (fenêtre de 9 s). */
function showUndo(msg, fn){ const t=$('#toast'); t.innerHTML=esc(msg)+' <button class="undobtn">↩️ Annuler</button>';
  t.classList.add('on'); clearTimeout(t._t);
  const b=t.querySelector('.undobtn'); if(b) b.onclick=()=>{ fn(); t.classList.remove('on'); };
  t._t=setTimeout(()=>t.classList.remove('on'), 9000); }

/* ─── Chargement ─── */
async function load(){
  try{
    const r = await fetch('api.php?action=load');
    if(r.status===401){ location.href='../sso/?app=objectifs&return='+encodeURIComponent(location.href); return; }
    const j = await r.json();
    DATA = (j && j.ok && j.data && j.data.columns) ? j.data : defaultBoard();
  }catch(e){ DATA = defaultBoard(); }
  ensureStats();
  applyTheme();
  render();
}
function applyTheme(){ ensureStats(); document.body.setAttribute('data-theme', DATA.stats.theme==='futur'?'futur':'classic'); }
function setTheme(t){ ensureStats(); DATA.stats.theme=(t==='futur')?'futur':'classic'; applyTheme(); save(); renderSettings(); }
function defaultBoard(){
  return { columns:[
    {id:col(), title:'🎯 Objectifs', cards:[
      {id:col(), titre:'Marathon en octobre', cat:'sport', prio:'h', due:'', desc:'Objectif de l\'année'},
      {id:col(), titre:'Décrocher un nouveau client', cat:'pro', prio:'h', due:'', desc:''}]},
    {id:col(), title:'📋 À faire', cards:[
      {id:col(), titre:'Plan d\'entraînement semaine', cat:'sport', prio:'m', due:'', desc:''},
      {id:col(), titre:'🔥 Abdos express', cat:'sport', prio:'m', due:'', desc:'Séance prédéfinie — glisse-la vers ✅ Terminé pour valider.', actions:['abdos','gainage','releve_jambes','russian_twist']},
      {id:col(), titre:'🦵 Jambes & Fessiers', cat:'sport', prio:'m', due:'', desc:'Séance prédéfinie — glisse-la vers ✅ Terminé pour valider.', actions:['squat','fentes','hip_thrust','mollets']},
      {id:col(), titre:'🏃 Cardio / Course', cat:'sport', prio:'m', due:'', desc:'Séance prédéfinie — glisse-la vers ✅ Terminé pour valider.', actions:['course','corde']}]},
    {id:col(), title:'🔄 En cours', cards:[]},
    {id:col(), title:'✅ Terminé', cards:[]},
  ]};
}
function col(){ return 'x'+Math.floor(performance.now()*1000).toString(36)+Math.floor(Math.random()*1e6).toString(36); }

/* ─── Rendu ─── */
function render(){
  boardEl.innerHTML='';
  DATA.columns.forEach(c=>{
    const visible = c.cards.filter(matchFilter);
    const column=document.createElement('div'); column.className='column'; column.dataset.col=c.id;
    const head=document.createElement('div'); head.className='col-head';
    head.innerHTML=`<input class="ctitle" value="${esc(c.title)}" onchange="renameCol('${c.id}',this.value)">
      <span class="count">${visible.length}</span>
      <button class="col-del" title="Supprimer la colonne" onclick="delCol('${c.id}')">✕</button>`;
    const cards=document.createElement('div'); cards.className='cards'; cards.dataset.col=c.id;
    cards.addEventListener('dragover',e=>{e.preventDefault();cards.classList.add('drop-hot');});
    cards.addEventListener('dragleave',()=>cards.classList.remove('drop-hot'));
    cards.addEventListener('drop',e=>{e.preventDefault();cards.classList.remove('drop-hot');onDrop(c.id,e);});
    visible.forEach(card=>cards.appendChild(renderCard(c.id,card)));
    if(!visible.length) cards.innerHTML='<div class="empty">—</div>';
    const add=document.createElement('button'); add.className='add-card'; add.textContent='＋ Ajouter';
    add.onclick=()=>openCard(c.id,null);
    column.append(head,cards,add); boardEl.appendChild(column);
  });
  const addCol=document.createElement('button'); addCol.className='column add-col';
  addCol.style.cssText='align-items:center;justify-content:center;min-height:60px;color:var(--muted)';
  addCol.textContent='＋ Nouvelle colonne'; addCol.onclick=addColumn;
  boardEl.appendChild(addCol);
}
function renderCard(colId,card){
  const el=document.createElement('div'); el.className='card cat-'+card.cat; el.draggable=true;
  el.dataset.card=card.id; el.dataset.col=colId;
  const late = card.due && card.due < today();
  const reps=card.reps||{};
  const exHtml=(card.actions&&card.actions.length)
    ? `<div class="cx">`+card.actions.map(k=>{ const n=ACTIONS[k]?ACTIONS[k].nom:k; const q=reps[k];
        let qs='';
        if(q&&typeof q==='object'){
          if('s' in q || 'r' in q || 'w' in q){ const sr=[q.s,q.r].filter(Boolean).join('×'); qs=[sr, q.w?q.w+' kg':''].filter(Boolean).join(' · '); }
          else { qs=[q.d,q.t].filter(Boolean).join(' · '); }
        } else if(typeof q==='string'){ qs=q; }
        return `<span class="cxi">${esc(n)}${qs?`<b>${esc(qs)}</b>`:''}</span>`; }).join('')+`</div>`
    : '';
  el.innerHTML=`<div class="ctitre">${esc(card.titre)}</div><div class="meta">
    <span class="tag ${card.cat}">${card.cat==='sport'?'🏃 Sport':'💼 Pro'}</span>
    <span class="tag prio-${card.prio}">${card.prio==='h'?'Haute':card.prio==='m'?'Moyenne':'Basse'}</span>
    ${card.due?`<span class="tag due ${late?'late':''}">📅 ${fmtDate(card.due)}</span>`:''}
  </div>${exHtml}`;
  el.addEventListener('dragstart',e=>{e.dataTransfer.setData('text',JSON.stringify({colId,cardId:card.id}));el.classList.add('dragging');});
  el.addEventListener('dragend',()=>el.classList.remove('dragging'));
  el.addEventListener('click',()=>openCard(colId,card.id));
  return el;
}
function matchFilter(card){ return filter==='all' || card.cat===filter; }

/* ─── Drag & drop ─── */
function onDrop(destCol,e){
  let d; try{ d=JSON.parse(e.dataTransfer.getData('text')); }catch(_){ return; }
  const src=findCol(d.colId), dst=findCol(destCol); if(!src||!dst) return;
  const i=src.cards.findIndex(c=>c.id===d.cardId); if(i<0) return;
  const done=t=>/termin|fait|✅/i.test(t||'');
  const [card]=src.cards.splice(i,1); dst.cards.push(card);
  if(done(dst.title) && !done(src.title)) completeSession(card);   // séance validée → muscles
  render(); save();
}

/* ─── Colonnes ─── */
function addColumn(){ DATA.columns.push({id:col(),title:'Nouvelle colonne',cards:[]}); render(); save(); }
function renameCol(id,v){ const c=findCol(id); if(c){c.title=v.trim()||'Sans titre';} save(); }
let lastDeletedCol=null;   // dernière colonne supprimée (pour Annuler / restaurer)
function delCol(id){ const c=findCol(id); if(!c)return;
  if(c.cards.length && !confirm('Supprimer « '+c.title+' » et ses '+c.cards.length+' carte(s) ?\n\nTu pourras l\'annuler juste après (bouton « Annuler »), ou la restaurer dans ⚙️ Paramètres.'))return;
  const idx=DATA.columns.findIndex(x=>x.id===id);
  lastDeletedCol={col:JSON.parse(JSON.stringify(c)), idx};
  DATA.columns=DATA.columns.filter(x=>x.id!==id); render(); save();
  showUndo('🗑 Colonne « '+c.title+' » supprimée', undoDelCol); }
function undoDelCol(){ if(!lastDeletedCol) return;
  const d=lastDeletedCol; DATA.columns.splice(Math.min(d.idx, DATA.columns.length), 0, d.col);
  lastDeletedCol=null; render(); save(); toast('↩️ Colonne restaurée'); }

/* ─── Cartes (modale) ─── */
function openCard(colId,cardId){
  editing={colId,cardId};
  const card = cardId ? findCol(colId).cards.find(c=>c.id===cardId) : null;
  $('#cmTitle').textContent = card ? 'Modifier' : 'Nouvel objectif / tâche';
  $('#cmTitre').value = card?card.titre:''; $('#cmCat').value=card?card.cat:(filter==='pro'?'pro':'sport');
  $('#cmPrio').value=card?card.prio:'m'; $('#cmDue').value=card?card.due||'':''; $('#cmDesc').value=card?card.desc||'':'';
  $('#cmDelete').style.display = card ? '' : 'none';
  renderActionChecks(card?card.actions:[], card?card.reps:{});
  $('#cardModal').classList.add('on'); setTimeout(()=>$('#cmTitre').focus(),50);
}
function saveCard(){
  const titre=$('#cmTitre').value.trim(); if(!titre){$('#cmTitre').focus();return;}
  const c=findCol(editing.colId); if(!c)return;
  const actions=[...document.querySelectorAll('#cmActions input[type=checkbox]:checked')].map(i=>i.value);
  const reps={};
  actions.forEach(k=>{
    const ins=[...document.querySelectorAll('#cmActions .exq[data-ex="'+k+'"]')];
    const g=r=>{ const el=ins.find(i=>i.dataset.role===r); return el?el.value.trim():''; };
    if(ins.length===2){                                  // cardio : distance + temps
      const d=g('d'), t=g('t'); if(d||t) reps[k]={d,t};
    } else if(ins.length===3){                           // muscu : séries / reps / poids
      const s=g('s'), rr=g('r'), w=g('w'); if(s||rr||w) reps[k]={s,r:rr,w};
    } else if(ins.length===1){                           // mobilité : durée
      const v=ins[0].value.trim(); if(v) reps[k]=v;
    }
  });
  const data={titre,cat:$('#cmCat').value,prio:$('#cmPrio').value,due:$('#cmDue').value,desc:$('#cmDesc').value.trim(),actions,reps};
  if(editing.cardId){ Object.assign(c.cards.find(x=>x.id===editing.cardId),data); }
  else{ c.cards.push({id:col(),...data}); }
  closeModal(); render(); save();
}
function deleteCard(){
  if(!editing.cardId)return; const c=findCol(editing.colId); if(!c)return;
  c.cards=c.cards.filter(x=>x.id!==editing.cardId); closeModal(); render(); save();
}
function closeModal(){ $('#cardModal').classList.remove('on'); }

/* ─── Sauvegarde serveur (auto) ─── */
let saveTimer=null;
function save(){
  $('#saveState').textContent='💾 …';
  clearTimeout(saveTimer);
  saveTimer=setTimeout(async()=>{
    try{
      const r=await fetch('api.php?action=save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(DATA)});
      const j=await r.json();
      $('#saveState').textContent = (j&&j.ok)?'✓ enregistré':'⚠ erreur';
    }catch(e){ $('#saveState').textContent='⚠ hors ligne'; }
    setTimeout(()=>$('#saveState').textContent='',1800);
  },500);
}

/* ─── Filtres / utils ─── */
function setFilter(el){ document.querySelectorAll('.chip').forEach(c=>c.classList.remove('on')); el.classList.add('on'); filter=el.dataset.filter; render(); }
function findCol(id){ return DATA.columns.find(c=>c.id===id); }
function esc(s){ return String(s).replace(/[&<>"]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m])); }
function today(){ const d=new Date(); return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }
function fmtDate(s){ const p=s.split('-'); return p.length===3?p[2]+'/'+p[1]:s; }
document.addEventListener('keydown',e=>{ if(e.key==='Escape')closeModal(); });
$('#cardModal').addEventListener('click',e=>{ if(e.target.id==='cardModal')closeModal(); });

load();
if('serviceWorker' in navigator){ window.addEventListener('load',()=>navigator.serviceWorker.register('sw.js').catch(()=>{})); }
</script>
</body>
</html>
