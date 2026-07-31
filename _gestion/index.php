<?php
/* ═══════════════════════════════════════════════════════════════════════
   GESTIONNAIRE DE FICHIERS — interface web.
   Connexion (session) puis explorateur : parcourir tous les dossiers du
   site, envoyer, éditer, créer, renommer, supprimer, télécharger.
   ═══════════════════════════════════════════════════════════════════════ */

require __DIR__ . '/lib.php';
fs_boot();                                        // ouvre la session AVANT le client SSO

/* Connexion déléguée au hub « Luvumbu ID » : le gestionnaire n'est plus une
   porte d'entrée séparée. Le formulaire local ne subsiste qu'en secours,
   quand le hub n'est pas configuré sur ce serveur. */
require_once __DIR__ . '/../sso/client.php';
$SSO_ON = sso_ready();

/* Déconnexion (globale si la session venait du hub) */
if (isset($_GET['logout'])) {
    $viaSso = !empty($_SESSION['fs_admin_sso']);
    $back   = luvumbu_url('index.php');
    $_SESSION = [];
    session_destroy();
    if ($viaSso) { header('Location: ' . rtrim(luvumbu_hub(), '/') . '/logout.php?return=' . rawurlencode($back)); exit; }
    header('Location: index.php');
    exit;
}

$err = '';

/* Identité venant du hub (consomme aussi le ?sso=… du retour). */
$ssoUser = $SSO_ON ? luvumbu_user() : null;
if ($ssoUser) {
    if (luvumbu_is_admin($ssoUser, 'gestion')) {
        $_SESSION['fs_admin']     = true;
        $_SESSION['fs_admin_sso'] = true;
    } else {
        $err = 'Ton compte Luvumbu ID n\'a pas le rôle administrateur sur le gestionnaire de fichiers.';
    }
}

/* Ni session locale ni session admin du portfolio → au hub (point d'entrée unique). */
if (!fs_authed() && $SSO_ON && !$ssoUser) {
    luvumbu_require_login('gestion');
}

/* Traitement de la connexion de SECOURS (hub indisponible) */
if (!$SSO_ON && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_pw'])) {
    $wait = fs_locked_for();
    if ($wait > 0) {
        $err = 'Trop de tentatives. Réessayez dans ' . ceil($wait / 60) . ' min.';
    } elseif (fs_check_login((string)($_POST['login_user'] ?? ''), (string)$_POST['login_pw'])) {
        session_regenerate_id(true);
        $_SESSION['fs_admin'] = true;
        $_SESSION['fs_csrf']  = bin2hex(random_bytes(32));
        fs_record_success();
        header('Location: index.php');
        exit;
    } else {
        fs_record_fail();
        $err = 'Utilisateur ou mot de passe incorrect.';
        $w = fs_locked_for();
        if ($w > 0) $err = 'Trop de tentatives. Réessayez dans ' . ceil($w / 60) . ' min.';
    }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$authed = fs_authed();
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Gestion des fichiers — luvumbu.com</title>
<style>
  :root{
    --bg:#0f1220; --panel:#171a2b; --panel2:#1e2238; --line:#2a2f4a;
    --text:#e7e9f5; --muted:#9aa0be; --accent:#5b8cff; --accent2:#3a6bff;
    --ok:#37c98b; --danger:#ff5d6c; --warn:#ffb84d; --radius:12px;
  }
  *{box-sizing:border-box}
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
       background:var(--bg);color:var(--text);font-size:15px}
  a{color:var(--accent)}
  button{font-family:inherit;font-size:14px;cursor:pointer;border:1px solid var(--line);
    background:var(--panel2);color:var(--text);padding:8px 12px;border-radius:9px}
  button:hover{border-color:var(--accent)}
  button.primary{background:var(--accent2);border-color:var(--accent2);color:#fff}
  button.danger{background:transparent;border-color:var(--danger);color:var(--danger)}
  button.danger:hover{background:var(--danger);color:#fff}
  input,textarea{font-family:inherit;background:var(--panel2);border:1px solid var(--line);
    color:var(--text);border-radius:9px;padding:10px 12px;font-size:14px;width:100%}
  textarea{width:100%;min-height:60vh;resize:vertical;font-family:ui-monospace,Consolas,monospace;
    font-size:13px;line-height:1.5;white-space:pre;tab-size:2}

  /* ── Connexion ── */
  .login{min-height:100vh;display:grid;place-items:center;padding:20px}
  .card{background:var(--panel);border:1px solid var(--line);border-radius:16px;
    padding:28px;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,.4)}
  .card h1{margin:0 0 4px;font-size:20px}
  .card p.sub{margin:0 0 20px;color:var(--muted);font-size:13px}
  .card label{display:block;margin:14px 0 6px;font-size:13px;color:var(--muted)}
  .err{background:rgba(255,93,108,.12);border:1px solid var(--danger);color:#ffb0b8;
    padding:10px 12px;border-radius:9px;font-size:13px;margin-bottom:8px}

  /* ── App ── */
  header.bar{display:flex;align-items:center;gap:12px;padding:12px 16px;
    background:var(--panel);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:5;flex-wrap:wrap}
  header.bar .brand{font-weight:700}
  header.bar .brand small{color:var(--muted);font-weight:400}
  header.bar .spacer{flex:1}
  .toolbar{display:flex;gap:8px;flex-wrap:wrap;padding:12px 16px;align-items:center}
  .crumbs{display:flex;gap:4px;align-items:center;flex-wrap:wrap;font-size:14px}
  .crumbs a{cursor:pointer;color:var(--accent);text-decoration:none}
  .crumbs span.sep{color:var(--muted)}
  main{padding:0 16px 40px}
  table{width:100%;border-collapse:collapse}
  th,td{text-align:left;padding:9px 10px;border-bottom:1px solid var(--line);font-size:14px}
  th{color:var(--muted);font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
  tr.row:hover{background:var(--panel2)}
  td.name{display:flex;align-items:center;gap:9px}
  td.name .ic{width:20px;text-align:center}
  td.name a.open{cursor:pointer;color:var(--text);text-decoration:none}
  td.name a.open:hover{color:var(--accent)}
  td.actions{white-space:nowrap;text-align:right}
  td.actions button{padding:5px 8px;font-size:13px;margin-left:4px}
  .muted{color:var(--muted)}
  .drop{border:2px dashed var(--line);border-radius:12px;margin:8px 16px;padding:14px;
    text-align:center;color:var(--muted);font-size:13px;transition:.15s}
  .drop.hot{border-color:var(--accent);color:var(--text);background:var(--panel2)}
  .empty{padding:40px;text-align:center;color:var(--muted)}

  /* ── Modale ── */
  .modal{position:fixed;inset:0;background:rgba(6,8,18,.72);display:none;z-index:20;
    padding:20px;overflow:auto}
  .modal.on{display:block}
  .modal .box{background:var(--panel);border:1px solid var(--line);border-radius:14px;
    max-width:980px;margin:24px auto;padding:18px}
  .modal .box.sm{max-width:420px}
  .modal h3{margin:0 0 12px;font-size:16px;display:flex;align-items:center;gap:8px}
  .modal .foot{display:flex;justify-content:flex-end;gap:8px;margin-top:14px}
  .modal .path{color:var(--muted);font-size:12px;word-break:break-all}
  #toast{position:fixed;left:50%;bottom:22px;transform:translateX(-50%);z-index:40;
    background:var(--panel2);border:1px solid var(--line);padding:11px 16px;border-radius:10px;
    font-size:14px;opacity:0;transition:.25s;pointer-events:none;max-width:90vw}
  #toast.on{opacity:1}
  #toast.ok{border-color:var(--ok)} #toast.bad{border-color:var(--danger)}
</style>
</head>
<body>

<?php if (!$authed && $SSO_ON): /* ── Identité valide mais rôle insuffisant ── */ ?>
<div class="login">
  <div class="card">
    <h1>🗂️ Gestion des fichiers</h1>
    <p class="sub">Connexion assurée par <b>Luvumbu ID</b>, le point d'entrée unique du site.</p>
    <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
    <div style="margin-top:18px">
      <a href="<?= h(luvumbu_hub()) ?>?app=gestion&amp;return=<?= h(urlencode(luvumbu_url('index.php'))) ?>">
        <button class="primary" style="width:100%">Changer de compte →</button></a>
    </div>
  </div>
</div>

<?php elseif (!$authed): /* ── SECOURS : hub non configuré sur ce serveur ── */ ?>
<div class="login">
  <form class="card" method="post" autocomplete="off">
    <h1>🗂️ Gestion des fichiers</h1>
    <p class="sub">Luvumbu ID indisponible — connexion de secours</p>
    <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
    <label>Utilisateur <span class="muted">(facultatif)</span></label>
    <input type="text" name="login_user" autocomplete="username">
    <label>Mot de passe <span class="muted">(vide accepté en local)</span></label>
    <input type="password" name="login_pw" autofocus autocomplete="current-password">
    <div style="margin-top:18px"><button class="primary" style="width:100%">Se connecter</button></div>
  </form>
</div>

<?php else: /* ─────────── APPLICATION ─────────── */ ?>
<header class="bar">
  <div class="brand">🗂️ Gestion <small>· luvumbu.com</small></div>
  <div class="spacer"></div>
  <button onclick="refresh()">↻ Rafraîchir</button>
  <a href="?logout=1"><button>Déconnexion</button></a>
</header>

<div class="toolbar">
  <div class="crumbs" id="crumbs"></div>
  <div class="spacer" style="flex:1"></div>
  <button onclick="promptMkdir()">📁 Nouveau dossier</button>
  <button onclick="promptNewFile()">📄 Nouveau fichier</button>
  <button class="primary" onclick="fileInput.click()">⬆ Envoyer</button>
  <input type="file" id="fileInput" multiple hidden>
</div>

<div class="drop" id="drop">Glissez-déposez des fichiers ici pour les envoyer dans le dossier courant.</div>

<main>
  <table>
    <thead><tr>
      <th>Nom</th><th style="width:110px">Taille</th>
      <th style="width:160px">Modifié</th><th style="width:220px"></th>
    </tr></thead>
    <tbody id="rows"></tbody>
  </table>
  <div class="empty" id="empty" style="display:none">Dossier vide.</div>
</main>

<!-- Modale éditeur -->
<div class="modal" id="editModal">
  <div class="box">
    <h3>✏️ <span id="editTitle"></span></h3>
    <div class="path" id="editPath"></div>
    <textarea id="editArea" spellcheck="false"></textarea>
    <div class="foot">
      <button onclick="closeModal('editModal')">Annuler</button>
      <button class="primary" onclick="saveFile()">💾 Enregistrer</button>
    </div>
  </div>
</div>

<!-- Modale saisie (nom) -->
<div class="modal" id="askModal">
  <div class="box sm">
    <h3 id="askTitle"></h3>
    <input type="text" id="askInput" autocomplete="off">
    <div class="foot">
      <button onclick="closeModal('askModal')">Annuler</button>
      <button class="primary" id="askOk">Valider</button>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
const CSRF = <?= json_encode(fs_csrf()) ?>;
let CWD = '';                              // dossier courant (relatif)
const $ = s => document.querySelector(s);
const rowsEl = $('#rows'), emptyEl = $('#empty');
const fileInput = $('#fileInput');

/* ── Utilitaires réseau ── */
async function api(action, params = {}, opts = {}) {
  const url = 'api.php?action=' + encodeURIComponent(action);
  let init = { method: opts.method || 'GET' };
  if (init.method === 'POST') {
    const fd = opts.body instanceof FormData ? opts.body : new FormData();
    if (!(opts.body instanceof FormData)) for (const k in params) fd.append(k, params[k]);
    fd.append('csrf', CSRF);
    init.body = fd;
  }
  const qs = init.method === 'GET'
    ? '&' + new URLSearchParams(params).toString() : '';
  const r = await fetch(url + qs, init);
  if (r.status === 401) { location.reload(); return {ok:false}; }
  let j; try { j = await r.json(); } catch(e){ j = {ok:false, error:'Réponse invalide'}; }
  return j;
}

function toast(msg, kind='ok') {
  const t = $('#toast'); t.textContent = msg; t.className = 'on ' + kind;
  clearTimeout(t._t); t._t = setTimeout(() => t.className = '', 2600);
}

function icon(it){
  if (it.dir) return '📁';
  const e = (it.name.split('.').pop()||'').toLowerCase();
  const map = {php:'🐘',js:'🟨',mjs:'🟨',json:'🔧',html:'🌐',htm:'🌐',css:'🎨',
    png:'🖼️',jpg:'🖼️',jpeg:'🖼️',webp:'🖼️',gif:'🖼️',svg:'🖼️',pdf:'📕',
    md:'📝',txt:'📄',sql:'🗃️',zip:'📦',mp3:'🎵',mp4:'🎬',doc:'📘',docx:'📘',xlsx:'📊'};
  return map[e] || '📄';
}
function fmtDate(ts){
  if(!ts) return '';
  const d = new Date(ts*1000);
  return d.toLocaleDateString('fr-FR') + ' ' +
         d.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'});
}

/* ── Fil d'Ariane ── */
function renderCrumbs(){
  const c = $('#crumbs'); c.innerHTML = '';
  const mk = (label, path) => {
    const a = document.createElement('a'); a.textContent = label;
    a.onclick = () => go(path); return a;
  };
  c.appendChild(mk('🏠 Racine', ''));
  let acc = '';
  (CWD ? CWD.split('/') : []).forEach(part => {
    if(!part) return;
    acc = acc ? acc + '/' + part : part;
    const s = document.createElement('span'); s.className='sep'; s.textContent='›';
    c.appendChild(s); c.appendChild(mk(part, acc));
  });
}

/* ── Liste ── */
async function go(path){ CWD = path || ''; await refresh(); }
async function refresh(){
  const j = await api('list', {path: CWD});
  if(!j.ok){ toast(j.error||'Erreur', 'bad'); return; }
  CWD = j.path; renderCrumbs();
  rowsEl.innerHTML = '';
  if(!j.items.length){ emptyEl.style.display='block'; return; }
  emptyEl.style.display='none';
  for(const it of j.items){
    const tr = document.createElement('tr'); tr.className='row';

    const td1 = document.createElement('td'); td1.className='name';
    const ic = document.createElement('span'); ic.className='ic'; ic.textContent = icon(it);
    const a = document.createElement('a'); a.className='open'; a.textContent = it.name;
    if(it.dir) a.onclick = () => go(it.path);
    else if(it.text) a.onclick = () => openEditor(it);
    else a.onclick = () => downloadFile(it);
    td1.appendChild(ic); td1.appendChild(a);
    if(it.self){ const b=document.createElement('span'); b.className='muted';
      b.style.fontSize='11px'; b.textContent=' (protégé)'; td1.appendChild(b); }

    const td2 = document.createElement('td'); td2.className='muted'; td2.textContent = it.hsize;
    const td3 = document.createElement('td'); td3.className='muted'; td3.textContent = fmtDate(it.mtime);

    const td4 = document.createElement('td'); td4.className='actions';
    if(it.text){ const b=document.createElement('button'); b.textContent='✏️';
      b.title='Éditer'; b.onclick=()=>openEditor(it); td4.appendChild(b); }
    if(!it.dir){ const b=document.createElement('button'); b.textContent='⬇';
      b.title='Télécharger'; b.onclick=()=>downloadFile(it); td4.appendChild(b); }
    const br=document.createElement('button'); br.textContent='✏ Renommer'.slice(0,2)+'…';
    br.title='Renommer'; br.onclick=()=>promptRename(it);
    if(!it.self) td4.appendChild(br);
    if(!it.self){ const b=document.createElement('button'); b.className='danger';
      b.textContent='🗑'; b.title='Supprimer'; b.onclick=()=>del(it); td4.appendChild(b); }

    tr.append(td1,td2,td3,td4); rowsEl.appendChild(tr);
  }
}

/* ── Éditeur ── */
let EDIT_PATH = '';
async function openEditor(it){
  const j = await api('read', {path: it.path});
  if(!j.ok){ toast(j.error||'Lecture impossible','bad'); return; }
  EDIT_PATH = j.path;
  $('#editTitle').textContent = it.name;
  $('#editPath').textContent = '/' + j.path;
  $('#editArea').value = j.content;
  openModal('editModal');
}
async function saveFile(){
  const j = await api('save', {path: EDIT_PATH, content: $('#editArea').value}, {method:'POST'});
  if(j.ok){ toast('✔ Enregistré'); closeModal('editModal'); }
  else toast(j.error||'Échec','bad');
}
function downloadFile(it){ location.href = 'api.php?action=download&path=' + encodeURIComponent(it.path); }

/* ── Création / renommage / suppression ── */
function ask(title, value, cb){
  $('#askTitle').textContent = title;
  const inp = $('#askInput'); inp.value = value||'';
  openModal('askModal'); setTimeout(()=>{inp.focus();inp.select();},50);
  $('#askOk').onclick = () => { const v=inp.value.trim(); if(v){ closeModal('askModal'); cb(v);} };
  inp.onkeydown = e => { if(e.key==='Enter') $('#askOk').click(); };
}
function promptMkdir(){ ask('📁 Nom du nouveau dossier','', async name=>{
  const j = await api('mkdir',{path:CWD,name},{method:'POST'});
  j.ok ? (toast('✔ Dossier créé'),refresh()) : toast(j.error||'Échec','bad'); }); }
function promptNewFile(){ ask('📄 Nom du nouveau fichier','nouveau.txt', async name=>{
  const j = await api('newfile',{path:CWD,name},{method:'POST'});
  j.ok ? (toast('✔ Fichier créé'),refresh()) : toast(j.error||'Échec','bad'); }); }
function promptRename(it){ ask('Renommer « '+it.name+' »', it.name, async name=>{
  const j = await api('rename',{path:it.path,name},{method:'POST'});
  j.ok ? (toast('✔ Renommé'),refresh()) : toast(j.error||'Échec','bad'); }); }
async function del(it){
  if(!confirm('Supprimer « '+it.name+' »'+(it.dir?' et tout son contenu':'')+' ?\nAction irréversible.')) return;
  const j = await api('delete',{path:it.path},{method:'POST'});
  j.ok ? (toast('✔ Supprimé'),refresh()) : toast(j.error||'Échec','bad');
}

/* ── Envoi de fichiers ── */
async function upload(files){
  if(!files.length) return;
  const fd = new FormData(); fd.append('path', CWD);
  for(const f of files) fd.append('files[]', f);
  toast('Envoi de '+files.length+' fichier(s)…');
  const j = await api('upload', {}, {method:'POST', body: fd});
  if(j.ok){ toast('✔ '+(j.saved?.length||0)+' envoyé(s)'+(j.errors?.length?' · '+j.errors.length+' erreur(s)':'')); refresh(); }
  else toast(j.error||'Échec de l\'envoi','bad');
}
fileInput.onchange = () => { upload([...fileInput.files]); fileInput.value=''; };

/* Glisser-déposer */
const drop = $('#drop');
['dragenter','dragover'].forEach(ev=>drop.addEventListener(ev,e=>{e.preventDefault();drop.classList.add('hot');}));
['dragleave','drop'].forEach(ev=>drop.addEventListener(ev,e=>{e.preventDefault();drop.classList.remove('hot');}));
drop.addEventListener('drop', e => upload([...(e.dataTransfer?.files||[])]));

/* ── Modales ── */
function openModal(id){ $('#'+id).classList.add('on'); }
function closeModal(id){ $('#'+id).classList.remove('on'); }
document.addEventListener('keydown', e => { if(e.key==='Escape')
  document.querySelectorAll('.modal.on').forEach(m=>m.classList.remove('on')); });
document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => {
  if(e.target===m) m.classList.remove('on'); }));

/* Go */
refresh();
</script>
<?php endif; ?>
</body>
</html>
