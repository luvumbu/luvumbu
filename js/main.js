/* ═══════════════════════════════════════════════════════════
   Portfolio Luvumbu — interactions
   ═══════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var doc = document;

  /* ── Année du footer ── */
  var y = doc.getElementById('year');
  if (y) y.textContent = new Date().getFullYear();

  /* ── Thème clair / sombre (mémorisé) ── */
  var toggle = doc.getElementById('themeToggle');
  var icon = toggle ? toggle.querySelector('.toggle-icon') : null;
  var saved = localStorage.getItem('pf_theme');
  if (saved === 'light') { doc.body.classList.add('light'); if (icon) icon.textContent = '☀'; }
  if (toggle) {
    toggle.addEventListener('click', function () {
      var light = doc.body.classList.toggle('light');
      localStorage.setItem('pf_theme', light ? 'light' : 'dark');
      if (icon) icon.textContent = light ? '☀' : '☾';
    });
  }

  /* ── Nav : fond au scroll + barre de progression ── */
  var nav = doc.getElementById('nav');
  var bar = doc.getElementById('scrollProgress');
  function onScroll() {
    var st = window.pageYOffset || doc.documentElement.scrollTop;
    if (nav) nav.classList.toggle('scrolled', st > 30);
    if (bar) {
      var h = doc.documentElement.scrollHeight - window.innerHeight;
      bar.style.width = (h > 0 ? (st / h) * 100 : 0) + '%';
    }
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ── Reveal au scroll ── */
  var reveals = doc.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });
    reveals.forEach(function (el) { io.observe(el); });
    // délai en cascade pour les cartes feature
    doc.querySelectorAll('.feature-list .feat').forEach(function (el, i) {
      el.style.setProperty('--d', (i * 0.07) + 's');
    });
  } else {
    reveals.forEach(function (el) { el.classList.add('in'); });
  }

  /* ── Compteurs animés (hero) ── */
  function animateCount(el) {
    var target = parseInt(el.getAttribute('data-count'), 10) || 0;
    var suffix = el.getAttribute('data-suffix') || '';
    var dur = 1600, start = null;
    function fmt(n) {
      return n >= 1000 ? n.toLocaleString('fr-FR') : String(n);
    }
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3); // easeOutCubic
      el.textContent = fmt(Math.floor(eased * target)) + suffix;
      if (p < 1) requestAnimationFrame(step);
      else el.textContent = fmt(target) + suffix;
    }
    requestAnimationFrame(step);
  }
  var counters = doc.querySelectorAll('[data-count]');
  if ('IntersectionObserver' in window && counters.length) {
    var cio = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { animateCount(e.target); cio.unobserve(e.target); }
      });
    }, { threshold: 0.5 });
    counters.forEach(function (el) { cio.observe(el); });
  } else {
    counters.forEach(animateCount);
  }

  /* ── Halo qui suit la souris sur les cartes stack ── */
  doc.querySelectorAll('.stack-card').forEach(function (card) {
    card.addEventListener('mousemove', function (e) {
      var r = card.getBoundingClientRect();
      card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
      card.style.setProperty('--my', (e.clientY - r.top) + 'px');
    });
  });

  /* ── Scroll fluide pour l'ancre nav ── */
  doc.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (ev) {
      var id = a.getAttribute('href');
      if (id.length < 2) return;
      var t = doc.querySelector(id);
      if (t) { ev.preventDefault(); t.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
  });

  /* ── Particules légères en fond ── */
  var canvas = doc.getElementById('particles');
  if (canvas && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var ctx = canvas.getContext('2d');
    var W, H, pts = [];
    function resize() {
      W = canvas.width = window.innerWidth;
      H = canvas.height = window.innerHeight;
      var n = Math.min(70, Math.floor(W * H / 22000));
      pts = [];
      for (var i = 0; i < n; i++) {
        pts.push({
          x: Math.random() * W, y: Math.random() * H,
          vx: (Math.random() - 0.5) * 0.3, vy: (Math.random() - 0.5) * 0.3,
          r: Math.random() * 1.8 + 0.4
        });
      }
    }
    function accent() {
      return doc.body.classList.contains('light') ? '108,92,231' : '139,120,255';
    }
    function draw() {
      ctx.clearRect(0, 0, W, H);
      var col = accent();
      for (var i = 0; i < pts.length; i++) {
        var p = pts[i];
        p.x += p.vx; p.y += p.vy;
        if (p.x < 0 || p.x > W) p.vx *= -1;
        if (p.y < 0 || p.y > H) p.vy *= -1;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(' + col + ',.7)';
        ctx.fill();
        for (var j = i + 1; j < pts.length; j++) {
          var q = pts[j], dx = p.x - q.x, dy = p.y - q.y, d = dx * dx + dy * dy;
          if (d < 13000) {
            ctx.beginPath();
            ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y);
            ctx.strokeStyle = 'rgba(' + col + ',' + (0.12 * (1 - d / 13000)) + ')';
            ctx.lineWidth = 1;
            ctx.stroke();
          }
        }
      }
      requestAnimationFrame(draw);
    }
    resize();
    window.addEventListener('resize', resize);
    draw();
  }

  /* ── Formulaire de contact : envoi AJAX (pas de mailto / pas d'Outlook) ── */
  var cForm = doc.getElementById('contactForm');
  if (cForm) {
    var cStatus = doc.getElementById('cfStatus');
    var cBtn = doc.getElementById('cfSubmit');
    var showStatus = function (msg, ok) {
      if (!cStatus) return;
      cStatus.textContent = msg;
      cStatus.className = 'cf-status ' + (ok ? 'ok' : 'err');
    };
    cForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var data = {
        nom: (cForm.nom.value || '').trim(),
        email: (cForm.email.value || '').trim(),
        message: (cForm.message.value || '').trim(),
        website: cForm.website ? cForm.website.value : ''
      };
      if (!data.nom || !data.email || !data.message) { showStatus('Merci de remplir tous les champs.', false); return; }
      if (cBtn) { cBtn.disabled = true; cBtn.textContent = 'Envoi…'; }
      showStatus('', true);
      fetch('contact.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      }).then(function (r) { return r.json(); }).then(function (res) {
        showStatus(res.msg || (res.ok ? 'Message envoyé.' : 'Une erreur est survenue.'), !!res.ok);
        if (res.ok) cForm.reset();
      }).catch(function () {
        showStatus('Erreur réseau — réessayez.', false);
      }).then(function () {
        if (cBtn) { cBtn.disabled = false; cBtn.textContent = 'Envoyer le message ✉️'; }
      });
    });
  }
})();
