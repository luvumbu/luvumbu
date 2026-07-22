/* ============================================================
   signalement.js — page détail d'un signalement.
   Affiche le lieu, la cause (motifs/types) et toutes les infos.
   ============================================================ */

(function () {
    'use strict';
    const { api, API, escapeHtml } = window.App;

    const VIGIL = {
        low:    { color: '#22c55e', label: '🟢 Vigilance faible' },
        medium: { color: '#f59e0b', label: '🟡 Vigilance modérée' },
        high:   { color: '#ef4444', label: '🔴 Vigilance élevée' }
    };
    const STATUS = { published: 'Publié', pending: 'En attente', rejected: 'Rejeté', hidden: 'Masqué', removed: 'Retiré' };

    const uuid = new URLSearchParams(location.search).get('id');

    if (!uuid) { showNotFound(); return; }
    load();

    async function load() {
        try {
            const r = await api('/reports/' + encodeURIComponent(uuid), { auth: true });
            render(r);
        } catch (e) {
            showNotFound();
        }
    }

    function showNotFound() {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('notfound').style.display = 'block';
    }

    function render(r) {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('content').style.display = 'block';

        const org = r.organization;

        // En-tête
        document.getElementById('orgName').textContent = org.name;
        const parts = [r.category.group + ' · ' + r.category.name];
        if (org.city) parts.push(org.city);
        if (r.incident_date) parts.push('le ' + formatDate(r.incident_date));
        document.getElementById('orgSub').textContent = parts.join(' — ');

        const v = VIGIL[org.activity_level] || VIGIL.low;
        const badge = document.getElementById('vigilBadge');
        badge.textContent = v.label;
        badge.style.background = v.color;

        // Lien droit de réponse (préremplit la référence)
        const contestUrl = 'contestation.html?report=' + encodeURIComponent(r.uuid);
        document.getElementById('contestLink').href = contestUrl;
        document.getElementById('contestBtn').href = contestUrl;

        // Le lieu
        const kv = document.getElementById('placeKv');
        kv.innerHTML = row('Nom', org.name)
            + (org.brand_name ? row('Marque', org.brand_name) : '')
            + row('Type', typeLabel(org.type))
            + (org.address ? row('Adresse', org.address) : '')
            + (org.city ? row('Ville', (org.postal_code ? org.postal_code + ' ' : '') + org.city) : '')
            + row('Catégorie', r.category.name);

        // Mini-carte
        if (org.latitude && org.longitude && window.L) {
            const mapEl = document.getElementById('detailMap');
            mapEl.style.display = 'block';
            const map = L.map('detailMap', { scrollWheelZoom: false, zoomControl: true })
                .setView([org.latitude, org.longitude], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19, attribution: '&copy; OpenStreetMap'
            }).addTo(map);
            L.circleMarker([org.latitude, org.longitude], {
                radius: 12, color: '#fff', weight: 2, fillColor: v.color, fillOpacity: .85
            }).addTo(map);
            setTimeout(() => map.invalidateSize(), 200);
        }

        // La cause
        document.getElementById('motifs').innerHTML = chips(r.motifs);
        document.getElementById('types').innerHTML = chips(r.types);

        // Témoignage
        document.getElementById('description').textContent = r.description;
        let author = r.author;
        if (r.incident_time) author += ' · à ' + r.incident_time.slice(0, 5);
        const when = r.published_at || r.created_at;
        document.getElementById('authorLine').textContent =
            'Par ' + author + (when ? ' — publié le ' + formatDate(when) : '');

        // Médias
        if (r.media && r.media.length) {
            document.getElementById('mediaBlock').style.display = 'block';
            document.getElementById('mediaGrid').innerHTML = r.media.map(m => {
                if (m.type === 'image') {
                    return `<a href="${API}/media/${m.uuid}" target="_blank" rel="noopener">` +
                        `<img src="${API}/media/${m.uuid}/thumb" alt="${escapeHtml(m.name || '')}" loading="lazy"></a>`;
                }
                const icon = m.type === 'video' ? '🎬' : '📄';
                return `<a href="${API}/media/${m.uuid}" target="_blank" rel="noopener" title="${escapeHtml(m.name || '')}"><span class="media-doc">${icon}</span></a>`;
            }).join('');
        }

        // Chiffres neutres
        document.getElementById('cSimilar').textContent = r.similar_count;
        document.getElementById('cNot').textContent = r.not_observed_count;
        document.getElementById('cOrg').textContent = org.reports_count;
        document.getElementById('cStatus').textContent = STATUS[r.status] || r.status;
        document.getElementById('cStatus').style.fontSize = '1rem';

        setupAbuse(r.uuid);
    }

    // --- Signalement d'abus ---
    function setupAbuse(reportUuid) {
        const btn = document.getElementById('abuseBtn');
        const form = document.getElementById('abuseForm');
        const alert = document.getElementById('abuseAlert');
        btn.addEventListener('click', () => {
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
        document.getElementById('abuseSubmit').addEventListener('click', async () => {
            alert.className = 'form-alert';
            try {
                const res = await api('/reports/' + reportUuid + '/abuse', {
                    method: 'POST', auth: true,
                    body: {
                        reason: document.getElementById('abuseReason').value,
                        details: document.getElementById('abuseDetails').value.trim()
                    }
                });
                alert.className = 'form-alert success';
                alert.textContent = res.message || 'Merci, signalement transmis.';
                document.getElementById('abuseSubmit').disabled = true;
            } catch (e) {
                alert.className = 'form-alert error';
                alert.textContent = e.message || 'Envoi impossible.';
            }
        });
    }

    // --- Helpers ---
    function row(k, v) { return `<dt>${escapeHtml(k)}</dt><dd>${escapeHtml(v)}</dd>`; }
    function chips(arr) { return (arr || []).map(x => `<span class="chip">${escapeHtml(x)}</span>`).join('') || '<span style="color:var(--c-text-soft)">—</span>'; }
    function typeLabel(t) {
        return { place: 'Lieu physique', company: 'Entreprise', brand: 'Marque', online_service: 'Service en ligne', other: 'Autre' }[t] || t;
    }
    function formatDate(d) {
        try { return new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }); }
        catch { return d; }
    }
})();
