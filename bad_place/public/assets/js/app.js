/* ============================================================
   app.js — page d'accueil. S'appuie sur core.js (window.App).
   ============================================================ */

(function () {
    'use strict';
    const { api, escapeHtml } = window.App;

    // 1) État du service
    async function loadHealth() {
        const dot = document.getElementById('api-dot');
        const label = document.getElementById('api-status');
        if (!dot) return;
        try {
            const h = await api('/health');
            const ok = h.status === 'healthy';
            dot.className = 'dot ' + (ok ? 'ok' : 'ko');
            label.textContent = ok ? 'Service en ligne · base de données connectée' : 'Service dégradé';
        } catch {
            dot.className = 'dot ko';
            label.textContent = 'Service indisponible';
        }
    }

    // 2) Compteurs
    async function loadStats() {
        try {
            const m = await api('/meta/overview');
            setNum('stat-reports', m.reports);
            setNum('stat-categories', m.categories);
            setNum('stat-motifs', m.motifs);
            setNum('stat-types', m.types);
        } catch {
            ['stat-reports', 'stat-categories', 'stat-motifs', 'stat-types']
                .forEach(id => { const el = document.getElementById(id); if (el) el.textContent = '–'; });
        }
    }

    function setNum(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        const target = Number(value) || 0;
        const steps = 24;
        let i = 0;
        const timer = setInterval(() => {
            i++;
            el.textContent = Math.round((target * i) / steps).toLocaleString('fr-FR');
            if (i >= steps) { clearInterval(timer); el.textContent = target.toLocaleString('fr-FR'); }
        }, 22);
    }

    // 3) Catégories
    async function loadCategories() {
        const grid = document.getElementById('cat-grid');
        if (!grid) return;
        try {
            const groups = await api('/categories');
            grid.innerHTML = '';
            groups.forEach(g => {
                const card = document.createElement('article');
                card.className = 'cat-card';
                const chips = g.categories.slice(0, 6).map(c => `<span class="chip">${escapeHtml(c.name)}</span>`).join('');
                const more = g.categories.length > 6 ? `<span class="chip">+${g.categories.length - 6}</span>` : '';
                card.innerHTML = `<h3><span class="ci">${iconFor(g.icon)}</span>${escapeHtml(g.name)}</h3><div class="chips">${chips}${more}</div>`;
                grid.appendChild(card);
            });
        } catch {
            grid.innerHTML = '<p style="color:var(--c-text-soft)">Impossible de charger les catégories.</p>';
        }
    }

    const ICONS = {
        'shopping-bag': '🛍️', 'utensils': '🍽️', 'bed': '🛏️', 'briefcase': '💼',
        'heart-pulse': '🩺', 'graduation-cap': '🎓', 'landmark': '🏛️', 'train': '🚆',
        'wallet': '💳', 'building': '🏢', 'ticket': '🎟️', 'tag': '🏷️',
        'globe': '🌐', 'users': '👥', 'calendar': '📅', 'ellipsis': '⋯'
    };
    function iconFor(name) { return ICONS[name] || '📌'; }

    const y = document.getElementById('year');
    if (y) y.textContent = new Date().getFullYear();
    loadHealth();
    loadStats();
    loadCategories();
})();
