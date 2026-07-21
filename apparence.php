<?php
/* ═══════════════════════════════════════════════════════════════════════
   APPARENCE — page AUTONOME (tout est inline : HTML + CSS + JS).
   Aucune dépendance externe → aucun cache à contourner : ça marche direct.
   Sélecteur d'apparence (tuiles DÉSERT, OCÉAN…) + mondes, style Super Mario.
   URL : https://luvumbu.com/apparence.php
   ═══════════════════════════════════════════════════════════════════════ */
$root    = __DIR__;
$exclude = ['luvumbu','config','css','js','inc','images','vendor','node_modules','_gestion'];
$meta    = [];
$mf = $root.'/config/projets_meta.json';
if (is_file($mf)) { $t = json_decode(file_get_contents($mf), true); if (is_array($t)) $meta = $t; }

$projets = [];
foreach (scandir($root) ?: [] as $e) {
    if ($e === '' || $e[0] === '.' || in_array($e, $exclude, true)) continue;
    if (!is_dir($root.'/'.$e)) continue;
    $m = $meta[$e] ?? [];
    $projets[] = [
        'folder' => $e,
        'nom'    => $m['nom']  ?? $e,
        'icon'   => $m['icon'] ?? '🕹️',
        'img'    => $m['img']  ?? '',
        'url'    => rawurlencode($e).'/',
    ];
}
sort($projets);   // ordre stable
function ae($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Apparence de la carte — Mario</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');
  *{box-sizing:border-box}
  body{margin:0;background:#0e1526;color:#eaf0ff;
       font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:24px 14px 60px}
  .wrap{max-width:900px;margin:0 auto}
  h1{font-family:'Press Start 2P',monospace;font-size:1rem;text-align:center;margin:0 0 6px;color:#fbd000}
  .sub{text-align:center;color:#9fb0d0;font-size:.85rem;margin:0 0 22px}

  .bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:center;
       margin:0 auto 16px;padding:12px 14px;border-radius:14px;max-width:820px;
       background:rgba(0,0,0,.22);border:1px solid #2a2f4a}
  .bar-label{font-family:'Press Start 2P',monospace;font-size:.6rem;color:#8fb3ff;letter-spacing:1px;white-space:nowrap}

  /* Tuiles carrées d'apparence */
  .tiles{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
  .tile{width:82px;height:82px;border-radius:12px;cursor:pointer;color:#fff;
        display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;
        border:3px solid #000;background:var(--dot,#444);
        font-family:'Press Start 2P',monospace;font-size:.42rem;line-height:1.3;text-align:center;
        box-shadow:0 5px 0 rgba(0,0,0,.6);transition:transform .1s,box-shadow .1s}
  .tile .ico{font-size:1.5rem;line-height:1}
  .tile:hover{transform:translateY(-3px);box-shadow:0 8px 0 rgba(0,0,0,.6)}
  .tile.active{outline:3px solid #fff;outline-offset:2px;box-shadow:0 5px 0 rgba(0,0,0,.6),0 0 16px rgba(255,255,255,.55)}
  .tile:active{transform:translateY(2px);box-shadow:0 2px 0 rgba(0,0,0,.6)}

  /* Onglets de mondes */
  .wtab{min-width:70px;padding:10px 14px;border-radius:12px;cursor:pointer;color:#cfd6e6;
        border:3px solid var(--dot,#2a2f4a);background:rgba(0,0,0,.25);
        font-family:'Press Start 2P',monospace;font-size:.55rem;transition:.12s}
  .wtab:hover{transform:translateY(-2px);color:#fff}
  .wtab.active{background:var(--dot,#5b8cff);border-color:var(--dot,#5b8cff);color:#fff}

  .hud{display:flex;justify-content:space-between;align-items:center;max-width:820px;margin:0 auto 8px;
       font-family:'Press Start 2P',monospace;font-size:.6rem;color:#fbd000;padding:0 6px}

  /* LA CARTE — style Super Mario Bros */
  .map{position:relative;max-width:820px;margin:0 auto;min-height:440px;border:4px solid #000;border-radius:6px;
       overflow:hidden;image-rendering:pixelated;transition:background .4s}
  .map,.map.wt-0{--sky:#5c94fc;--sky2:#5c94fc;--hill:#00a800;--ground:#e45c10;--brick:#b03000;--accent:#fbd000}
  .map.wt-1{--sky:#f8c088;--sky2:#f8b070;--hill:#e08030;--ground:#c87c1c;--brick:#8c4c00;--accent:#fff36b}
  .map.wt-2{--sky:#3cbcfc;--sky2:#1c7cf0;--hill:#0078f8;--ground:#0058c8;--brick:#003890;--accent:#9bf3ff}
  .map.wt-3{--sky:#301818;--sky2:#180808;--hill:#7c0000;--ground:#a81000;--brick:#500000;--accent:#ff9b3d}
  .map.wt-4{--sky:#0000a8;--sky2:#000058;--hill:#184058;--ground:#183c00;--brick:#0c2000;--accent:#a0e0ff}
  .map.wt-5{--sky:#a0d0f8;--sky2:#78b0f0;--hill:#5c94fc;--ground:#88c0ff;--brick:#4c8be0;--accent:#fff}
  .map{background:
      radial-gradient(circle at 16% 20%,#fff 0 10px,transparent 11px),
      radial-gradient(circle at 22% 20%,#fff 0 14px,transparent 15px),
      radial-gradient(circle at 29% 22%,#fff 0 10px,transparent 11px),
      radial-gradient(circle at 70% 14%,#fff 0 12px,transparent 13px),
      radial-gradient(circle at 77% 14%,#fff 0 16px,transparent 17px),
      radial-gradient(circle at 22% 118%,var(--hill) 0 70px,transparent 72px),
      radial-gradient(circle at 60% 122%,var(--hill) 0 92px,transparent 94px),
      radial-gradient(circle at 88% 116%,var(--hill) 0 60px,transparent 62px),
      linear-gradient(180deg,var(--sky),var(--sky2))}
  .map::after{content:"";position:absolute;left:0;right:0;bottom:0;height:38px;z-index:0;
      background:repeating-linear-gradient(90deg,var(--brick) 0 2px,transparent 2px 32px),
                 repeating-linear-gradient(0deg,var(--brick) 0 2px,transparent 2px 19px),var(--ground);
      border-top:3px solid #000}
  svg.path{position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:1}
  .path line,.path polyline{stroke:#000;stroke-width:3;stroke-dasharray:6 10;opacity:.5;fill:none}

  .node{position:absolute;transform:translate(-50%,-50%);z-index:2;cursor:pointer;text-align:center;
        text-decoration:none;color:#fff}
  .badge{width:56px;height:56px;border-radius:12px;border:3px solid #000;background:#fff;
         display:flex;align-items:center;justify-content:center;font-size:1.6rem;
         box-shadow:0 4px 0 rgba(0,0,0,.85);position:relative;overflow:hidden}
  .badge img{width:100%;height:100%;object-fit:cover}
  .num{position:absolute;top:-8px;left:-8px;width:22px;height:22px;border-radius:50%;background:var(--accent);
       border:2px solid #000;color:#000;font-family:'Press Start 2P',monospace;font-size:.5rem;
       display:flex;align-items:center;justify-content:center}
  .nname{margin-top:6px;font-family:'Press Start 2P',monospace;font-size:.5rem;color:#fff;
         background:rgba(0,0,0,.55);border:2px solid #000;border-radius:5px;padding:3px 5px;max-width:120px}
  .node:hover .badge{transform:translateY(-3px)}
</style>
</head>
<body>
<div class="wrap">
  <h1>🍄 Apparence de la carte</h1>
  <p class="sub">Clique sur une tuile pour changer l'apparence — style Super Mario Bros</p>

  <div class="bar">
    <span class="bar-label">🎨 Apparence :</span>
    <div class="tiles" id="tiles"></div>
  </div>

  <div class="bar" id="worldBar" style="display:none">
    <span class="bar-label">🌍 Mondes :</span>
    <div class="tiles" id="worlds"></div>
  </div>

  <div class="hud"><span id="hudLabel">★ WORLD 1</span><span id="hudCount"></span></div>
  <div class="map wt-0" id="map"
       data-projets='<?= ae(json_encode($projets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'></div>
</div>

<script>
(function(){
  var map = document.getElementById('map');
  var projets = []; try { projets = JSON.parse(map.getAttribute('data-projets')||'[]'); } catch(e){}
  var WORLD_SIZE = window.matchMedia('(max-width:640px)').matches ? 4 : 6;
  var WT = 6, forced = null, cur = 0;
  var DOT = ['#00a800','#d07c1c','#0078f8','#a81000','#3a3a8f','#3c78c8'];
  var APPS = [
    {wt:0,ico:'🌳',nom:'PLAINE'},{wt:1,ico:'🏜️',nom:'DÉSERT'},{wt:2,ico:'🌊',nom:'OCÉAN'},
    {wt:3,ico:'🏰',nom:'CHÂTEAU'},{wt:4,ico:'🌙',nom:'NUIT'},{wt:5,ico:'☁️',nom:'CIEL'}
  ];
  var worlds = [];
  for (var i=0;i<projets.length;i+=WORLD_SIZE) worlds.push(projets.slice(i,i+WORLD_SIZE));
  if(!worlds.length) worlds=[[]];

  var tilesEl=document.getElementById('tiles'), worldsEl=document.getElementById('worlds'),
      worldBar=document.getElementById('worldBar'), hudLabel=document.getElementById('hudLabel'),
      hudCount=document.getElementById('hudCount');
  var esc=function(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});};

  function theme(){ return forced!==null ? forced : (cur % WT); }
  function applyTheme(){
    for(var t=0;t<WT;t++) map.classList.remove('wt-'+t);
    map.classList.add('wt-'+theme());
    [].forEach.call(tilesEl.children,function(b){ b.classList.toggle('active', +b.dataset.wt===theme()); });
  }

  function positions(n){
    var mob=window.matchMedia('(max-width:640px)').matches, cols=mob?2:Math.min(n,4), rows=Math.ceil(n/cols), p=[], i=0;
    for(var r=0;r<rows;r++){var l2r=r%2===0;for(var c=0;c<cols&&i<n;c++){var cc=l2r?c:cols-1-c;
      var x=cols===1?50:12+cc*(76/(cols-1)); var y=rows===1?50:16+r*(64/(rows-1)); y+=cc%2===0?-3:3;
      p.push({x:x,y:Math.max(10,Math.min(88,y))}); i++;}} return p;
  }

  function render(){
    map.querySelectorAll('.node,svg.path').forEach(function(n){n.remove();});
    applyTheme();
    var g = worlds[cur]||[], start=cur*WORLD_SIZE, pts=positions(g.length);
    var svg=document.createElementNS('http://www.w3.org/2000/svg','svg'); svg.setAttribute('class','path');
    svg.setAttribute('viewBox','0 0 100 100'); svg.setAttribute('preserveAspectRatio','none');
    var pl=document.createElementNS('http://www.w3.org/2000/svg','polyline');
    pl.setAttribute('points', pts.map(function(p){return p.x+','+p.y;}).join(' '));
    pl.setAttribute('vector-effect','non-scaling-stroke'); svg.appendChild(pl); map.appendChild(svg);
    g.forEach(function(p,j){
      var a=document.createElement('a'); a.className='node'; a.href=p.url||'#';
      a.style.left=pts[j].x+'%'; a.style.top=pts[j].y+'%';
      var inner=p.img?'<img src="'+esc(p.img)+'" alt="">':(p.icon||'★');
      a.innerHTML='<div class="badge"><span class="num">'+(start+j+1)+'</span>'+inner+'</div>'+
                  '<div class="nname">'+esc(p.nom||'ZONE')+'</div>';
      map.appendChild(a);
    });
    hudLabel.textContent='★ WORLD '+(cur+1);
    hudCount.textContent=g.length+' ZONE'+(g.length>1?'S':'');
    [].forEach.call(worldsEl.children,function(b,k){ b.classList.toggle('active',k===cur); });
  }

  // tuiles d'apparence
  APPS.forEach(function(ap){
    var b=document.createElement('button'); b.className='tile'; b.dataset.wt=ap.wt;
    b.style.setProperty('--dot',DOT[ap.wt]); b.title='Apparence : '+ap.nom;
    b.innerHTML='<span class="ico">'+ap.ico+'</span>'+ap.nom;
    b.onclick=function(){ forced=ap.wt; applyTheme(); };
    tilesEl.appendChild(b);
  });
  // onglets de mondes (si +1)
  if(worlds.length>1){
    worldBar.style.display='';
    worlds.forEach(function(_,k){
      var b=document.createElement('button'); b.className='wtab'; b.textContent='MONDE '+(k+1);
      b.style.setProperty('--dot',DOT[k%DOT.length]);
      b.onclick=function(){ cur=k; render(); }; worldsEl.appendChild(b);
    });
  }
  render();
})();
</script>
</body>
</html>
