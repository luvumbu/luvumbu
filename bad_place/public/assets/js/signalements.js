/* ============================================================
   signalements.js — liste paginée et filtrable des signalements publiés.
   ============================================================ */

(function () {
    'use strict';
    const { api, escapeHtml } = window.App;

    let page = 1, pages = 1, timer = null;

    const listEl = document.getElementById('list');
    const pagerEl = document.getElementById('pager');
    const qEl = document.getElementById('q');
    const catEl = document.getElementById('categoryFilter');
    const cityEl = document.getElementById('cityFilter');

    // Pré-remplit les filtres depuis l'URL (?category_id=, ?q=)
    const params = new URLSearchParams(location.search);
    if (params.get('q')) qEl.value = params.get('q');
    if (params.get('city')) cityEl.value = params.get('city');
    const preCat = params.get('category_id');

    async function loadCategories() {
        try {
            const groups = await api('/categories');
            groups.forEach(g => {
                const og = document.createElement('optgroup');
                og.label = g.name;
                g.categories.forEach(c => {
                    const o = document.createElement('option');
                    o.value = c.id; o.textContent = c.name;
                    if (preCat && String(preCat) === String(c.id)) o.selected = true;
                    og.appendChild(o);
                });
                catEl.appendChild(og);
            });
        } catch { /* ignore */ }
    }

    // api() ne renvoie que "data" ; ici on a besoin de "meta" (pagination),
    // donc on lit la réponse complète via fetch.
    async function loadWithMeta() {
        listEl.innerHTML = '<div class="list-empty">Chargement…</div>';
        const qs = new URLSearchParams({ page, per_page: 10 });
        if (qEl.value.trim()) qs.set('q', qEl.value.trim());
        if (catEl.value) qs.set('category_id', catEl.value);
        if (cityEl.value.trim()) qs.set('city', cityEl.value.trim());

        try {
            const r = await fetch(window.App.API + '/reports?' + qs.toString(), { headers: { Accept: 'application/json' } });
            const body = await r.json();
            renderList(body.data || []);
            renderPager(body.meta || { page: 1, pages: 1 });
        } catch {
            listEl.innerHTML = '<div class="list-empty">Erreur de chargement.</div>';
        }
    }

    function renderList(items) {
        if (!items.length) {
            listEl.innerHTML = '<div class="list-empty">Aucun signalement pour ces critères.</div>';
            return;
        }
        listEl.innerHTML = items.map(it => {
            const org = it.organization || {};
            const cat = it.category || {};
            const meta = [org.city, it.incident_date ? 'le ' + formatDate(it.incident_date) : null].filter(Boolean).join(' · ');
            return `<a class="report-card" href="signalement.html?id=${encodeURIComponent(it.uuid)}">
                <div class="rc-top">
                    <h3>${escapeHtml(org.name || 'Signalement')}</h3>
                    <span class="rc-cat">${escapeHtml(cat.name || '')}</span>
                </div>
                <div class="rc-meta">${escapeHtml(meta)}</div>
                <div class="rc-excerpt">${escapeHtml(it.excerpt || '')}</div>
                <div class="rc-foot">
                    <span>👤 ${escapeHtml(it.author || 'Anonyme')}</span>
                    <span>👍 ${it.similar_count || 0} situations similaires</span>
                </div>
            </a>`;
        }).join('');
    }

    function renderPager(meta) {
        page = meta.page || 1; pages = meta.pages || 1;
        if (pages <= 1) { pagerEl.innerHTML = ''; return; }
        pagerEl.innerHTML =
            `<button class="btn btn-ghost" ${page <= 1 ? 'disabled' : ''} id="prev">← Précédent</button>` +
            `<span style="color:var(--c-text-soft)">Page ${page} / ${pages}</span>` +
            `<button class="btn btn-ghost" ${page >= pages ? 'disabled' : ''} id="next">Suivant →</button>`;
        const prev = document.getElementById('prev'), next = document.getElementById('next');
        if (prev) prev.addEventListener('click', () => { page--; loadWithMeta(); window.scrollTo({ top: 0, behavior: 'smooth' }); });
        if (next) next.addEventListener('click', () => { page++; loadWithMeta(); window.scrollTo({ top: 0, behavior: 'smooth' }); });
    }

    function debounced() { clearTimeout(timer); timer = setTimeout(() => { page = 1; loadWithMeta(); }, 300); }
    qEl.addEventListener('input', debounced);
    cityEl.addEventListener('input', debounced);
    catEl.addEventListener('change', () => { page = 1; loadWithMeta(); });

    function formatDate(d) {
        try { return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }); }
        catch { return d; }
    }

    loadCategories();
    loadWithMeta();
})();
