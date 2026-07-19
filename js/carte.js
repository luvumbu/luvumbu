/* ═══════════════════════════════════════════════════════════
   CARTE DES PROJETS — génère les nœuds en serpentin + le chemin,
   et gère la popup d'entrée (même logique que LUVUMBU LAND).
   Données : data-projets sur #carteMap (JSON injecté par PHP).
   ═══════════════════════════════════════════════════════════ */
(function () {
  'use strict';
  var map = document.getElementById('carteMap');
  if (!map) return;

  var projets = [];
  try { projets = JSON.parse(map.getAttribute('data-projets') || '[]'); } catch (e) { projets = []; }
  if (!projets.length) return;

  var mobile = window.matchMedia('(max-width:640px)').matches;

  /* ── Positions en serpentin (%) ── */
  function positions(n) {
    var pts = [], cols = mobile ? 2 : Math.min(n, 4), rows = Math.ceil(n / cols);
    var i = 0;
    for (var r = 0; r < rows; r++) {
      var leftToRight = r % 2 === 0;
      for (var c = 0; c < cols && i < n; c++) {
        var cc = leftToRight ? c : (cols - 1 - c);
        var x = cols === 1 ? 50 : (12 + cc * (76 / (cols - 1)));
        var y = rows === 1 ? 50 : (16 + r * (68 / (rows - 1)));
        // léger décalage organique
        y += (cc % 2 === 0 ? -4 : 4);
        pts.push({ x: x, y: Math.max(8, Math.min(92, y)) });
        i++;
      }
    }
    return pts;
  }

  var pts = positions(projets.length);

  /* ── Chemin SVG reliant les nœuds ── */
  var svgNS = 'http://www.w3.org/2000/svg';
  var svg = document.createElementNS(svgNS, 'svg');
  svg.setAttribute('class', 'carte-path');
  svg.setAttribute('viewBox', '0 0 100 100');
  svg.setAttribute('preserveAspectRatio', 'none');
  var d = 'M ' + pts.map(function (p) { return p.x + ' ' + p.y; }).join(' L ');
  var path = document.createElementNS(svgNS, 'path');
  path.setAttribute('class', 'path-line');
  path.setAttribute('d', d);
  path.setAttribute('vector-effect', 'non-scaling-stroke');
  svg.appendChild(path);
  map.appendChild(svg);

  /* ── Nœuds ── */
  projets.forEach(function (p, idx) {
    var locked = (p.etat === 'verrou');
    var node = document.createElement('div');
    node.className = 'carte-node' + (locked ? ' locked' : '');
    node.style.left = pts[idx].x + '%';
    node.style.top = pts[idx].y + '%';
    node.style.animationDelay = (idx * 0.08) + 's';
    var badgeInner = p.img
      ? '<img class="node-img" src="' + escapeHtml(p.img) + '" alt="' + escapeHtml(p.nom || '') + '">'
      : (p.icon || '★');
    node.innerHTML =
      (p.folder ? '<div class="node-folder">📁 ' + escapeHtml(p.folder) + '</div>' : '') +
      '<div class="node-badge' + (p.img ? ' has-img' : '') + '"><span class="node-num">' + (idx + 1) + '</span>' + badgeInner + '</div>' +
      '<div class="node-name">' + escapeHtml(p.nom || 'ZONE') + '</div>';
    node.style.cursor = 'pointer';
    node.title = locked ? 'Zone privée' : ('Entrer : ' + (p.nom || ''));
    node.addEventListener('click', function () { enterProject(p, locked, idx); });
    map.appendChild(node);
  });

  /* ── Clic sur une zone → ENTRE DIRECTEMENT dans le projet ── */
  function enterProject(p, locked, idx) {
    if (locked) { focusCard(idx); return; }           // zone privée : on montre juste la carte
    var url = (p && p.url) ? p.url : '';
    if (!url || url === '#') { focusCard(idx); return; }
    if (/^https?:\/\//.test(url)) { window.open(url, '_blank', 'noopener'); }  // externe : nouvel onglet
    else { window.location.href = url; }               // interne (projet à la racine) : on entre
  }

  /* met en avant la description à l'extérieur (fallback pour zone privée) */
  function focusCard(i) {
    var card = document.getElementById('cd-' + i);
    if (!card) return;
    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    card.classList.remove('flash');
    void card.offsetWidth;        // reflow pour rejouer l'animation
    card.classList.add('flash');
  }

  /* ── Cartes de description : cliquer n'importe où = entrer dans le projet ── */
  document.querySelectorAll('.cd-card').forEach(function (card) {
    var go = card.querySelector('.cd-go');
    if (!go) return;              // zone privée (pas de lien) → non cliquable
    card.style.cursor = 'pointer';
    card.addEventListener('click', function (e) {
      if (e.target.closest('a, button')) return;   // laisser le bouton/lien gérer
      if (go.getAttribute('target') === '_blank') window.open(go.href, '_blank', 'noopener');
      else window.location.href = go.href;
    });
  });

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ── « Voir autrement » : change UNIQUEMENT la carte-frame (partie basse).
     La description et les boutons restent. Le mode par défaut = carte intégrée ;
     les autres modes chargent LUVUMBU LAND dans une iframe (même page). ── */
  var frame = document.getElementById('carteFrame');
  var defaultBox = document.getElementById('carteDefault');
  var viewsBox = document.querySelector('.carte-views');
  if (frame && viewsBox) {
    var lvUrl = frame.getAttribute('data-luvumbu') || 'luvumbu/';
    var iframe = null;
    var currentMode = 'default';
    var currentBiome = '';
    var modeCards = viewsBox.querySelectorAll('.cv-card');
    var biomeChips = viewsBox.querySelectorAll('.cv-biome');

    // 1er mode « jeu » (pour basculer si on choisit une apparence depuis la vue Carte)
    var firstGameMode = null;
    modeCards.forEach(function (c) {
      var m = c.getAttribute('data-mode');
      if (!firstGameMode && m && m !== 'default') firstGameMode = m;
    });

    function setActive(list, el) {
      list.forEach(function (x) { x.classList.remove('active'); });
      if (el) el.classList.add('active');
    }
    function markMode(mode) {
      modeCards.forEach(function (c) { c.classList.toggle('active', c.getAttribute('data-mode') === mode); });
    }

    function render() {
      if (currentMode === 'default') {
        if (iframe) iframe.style.display = 'none';
        if (defaultBox) defaultBox.style.display = '';
        frame.classList.remove('is-embed');
        return;
      }
      if (defaultBox) defaultBox.style.display = 'none';
      if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.className = 'carte-embed';
        iframe.setAttribute('title', 'Réalisations');
        iframe.setAttribute('loading', 'lazy');
        frame.appendChild(iframe);
      }
      iframe.style.display = '';
      frame.classList.add('is-embed');
      iframe.src = lvUrl + '?embed=1&mode=' + encodeURIComponent(currentMode) +
        (currentBiome ? '&biome=' + encodeURIComponent(currentBiome) : '') + '&v=7';
    }

    modeCards.forEach(function (btn) {
      btn.addEventListener('click', function () {
        currentMode = btn.getAttribute('data-mode') || 'default';
        setActive(modeCards, btn);
        render();
      });
    });

    biomeChips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        currentBiome = chip.getAttribute('data-biome') || '';
        setActive(biomeChips, chip);
        // si on est sur la carte intégrée, on bascule vers un mode jeu pour voir l'apparence
        if (currentMode === 'default' && firstGameMode) {
          currentMode = firstGameMode;
          markMode(firstGameMode);
        }
        render();
      });
    });

    /* ── Applique le mode/univers PAR DÉFAUT réglé dans l'admin (au chargement) ── */
    var dMode = frame.getAttribute('data-default-mode') || 'default';
    var dBiome = frame.getAttribute('data-default-biome') || '';
    if (dBiome) currentBiome = dBiome;
    if (dMode && dMode !== 'default') { currentMode = dMode; render(); }
  }

  /* ── Espace admin en MODALE : on reste sur le portfolio ── */
  var adminOpen = document.getElementById('adminOpen');
  var adminModal = document.getElementById('adminModal');
  var adminClose = document.getElementById('adminClose');
  var adminFrame = document.getElementById('adminFrame');
  if (adminOpen && adminModal) {
    var openAdmin = function () {
      if (adminFrame && !adminFrame.getAttribute('src')) {
        adminFrame.setAttribute('src', adminFrame.getAttribute('data-src'));
      }
      adminModal.classList.add('open');
      adminModal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    };
    var closeAdmin = function () {
      adminModal.classList.remove('open');
      adminModal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    };
    adminOpen.addEventListener('click', openAdmin);
    if (adminClose) adminClose.addEventListener('click', closeAdmin);
    adminModal.addEventListener('click', function (e) { if (e.target === adminModal) closeAdmin(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAdmin(); });
  }
})();
