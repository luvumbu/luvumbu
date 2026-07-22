/* ============================================================
   carte.js — carte interactive Leaflet.
   Marqueurs colorés par gravité (🟢🟠🔴), mise à jour temps réel, heatmap.
   ============================================================ */

(function () {
    'use strict';
    const { api, escapeHtml } = window.App;

    const LEVEL_COLORS = { low: '#22c55e', medium: '#f59e0b', high: '#ef4444' };
    const LEVEL_LABELS = { low: 'Vigilance faible', medium: 'Vigilance modérée', high: 'Vigilance élevée' };
    const REFRESH_MS = 15000;

    let currentLevel = '';
    let heatOn = false;
    let heatLayer = null;

    // --- Carte ---
    const map = L.map('map', { zoomControl: true }).setView([46.6, 2.4], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; contributeurs <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    const markersLayer = L.layerGroup().addTo(map);
    const zonesLayer = L.layerGroup().addTo(map);
    let zonesOn = true;   // zones affichées par défaut

    // --- Rendu des points ---
    function renderPoints(points) {
        markersLayer.clearLayers();
        document.getElementById('pointCount').textContent = points.length;

        points.forEach(p => {
            const color = LEVEL_COLORS[p.level] || LEVEL_COLORS.low;
            const radius = 8 + Math.min(p.count * 2, 22);
            const marker = L.circleMarker([p.lat, p.lng], {
                radius, color: '#fff', weight: 2, fillColor: color, fillOpacity: 0.85
            });
            marker.bindPopup(popupHtml(p));
            markersLayer.addLayer(marker);
        });
    }

    function popupHtml(p) {
        const color = LEVEL_COLORS[p.level] || LEVEL_COLORS.low;
        const label = LEVEL_LABELS[p.level] || LEVEL_LABELS.low;
        const cat = p.category ? ' · ' + escapeHtml(p.category) : '';
        const s = p.count > 1 ? 's' : '';
        return `<div class="pop-title">${escapeHtml(p.name)}</div>` +
            `<div class="pop-meta">${escapeHtml(p.city || '')}${cat}</div>` +
            `<span class="pop-badge" style="background:${color}">${label}</span>` +
            `<div class="pop-meta" style="margin:6px 0 0">${p.count} témoignage${s} · non vérifié${s}</div>` +
            (p.report_uuid
                ? `<a href="signalement.html?id=${encodeURIComponent(p.report_uuid)}" style="display:inline-block;margin-top:8px;color:var(--c-primary);font-weight:600;text-decoration:none">Voir le détail →</a>`
                : '');
    }

    // --- Chargement des données ---
    async function loadPoints() {
        try {
            const q = currentLevel ? ('?level=' + currentLevel) : '';
            const points = await api('/map/points' + q);
            if (!heatOn) renderPoints(points);
            return points;
        } catch (e) {
            console.error('Chargement carte échoué', e);
            return [];
        }
    }

    async function loadHeat() {
        try {
            const data = await api('/map/heatmap');
            if (heatLayer) { map.removeLayer(heatLayer); heatLayer = null; }
            const max = data.reduce((m, d) => Math.max(m, d[2]), 1);
            heatLayer = L.heatLayer(data.map(d => [d[0], d[1], d[2] / max]), {
                radius: 28, blur: 20, maxZoom: 12
            }).addTo(map);
        } catch (e) { console.error(e); }
    }

    // --- Zones (agrégation par ville) ---
    let lastZones = [];

    function zoneRadiusMeters(z) {
        const base = { low: 700, medium: 1200, high: 1800 }[z.level] || 700;
        return Math.min(3000, base + z.total * 60);
    }

    function drawZoneCircles(zones) {
        zonesLayer.clearLayers();
        zones.forEach(z => {
            const color = LEVEL_COLORS[z.level] || LEVEL_COLORS.low;
            const circle = L.circle([z.lat, z.lng], {
                radius: zoneRadiusMeters(z), color, weight: 2, fillColor: color,
                fillOpacity: z.level === 'high' ? 0.22 : (z.level === 'medium' ? 0.16 : 0.08)
            });
            const s = z.total > 1 ? 's' : '';
            circle.bindPopup(
                `<div class="pop-title">Zone : ${escapeHtml(z.city)}</div>` +
                `<div class="pop-meta">${z.department ? escapeHtml(z.department) : ''}</div>` +
                `<span class="pop-badge" style="background:${color}">${LEVEL_LABELS[z.level]}</span>` +
                `<div class="pop-meta" style="margin:6px 0 0">${z.total} témoignage${s} · ${z.places} lieu${z.places > 1 ? 'x' : ''}</div>` +
                `<a href="signalements.html?city=${encodeURIComponent(z.city)}" style="display:inline-block;margin-top:8px;color:var(--c-primary);font-weight:600;text-decoration:none">Voir les signalements →</a>`
            );
            zonesLayer.addLayer(circle);
        });
    }

    function renderZonesPanel(zones) {
        const watched = zones.filter(z => z.under_watch);
        const panel = document.getElementById('zonesPanel');
        const list = document.getElementById('zonesList');
        if (!watched.length) { panel.style.display = 'none'; return; }
        panel.style.display = 'block';
        list.innerHTML = watched.map((z, i) =>
            `<div class="zone-item" data-i="${i}"><span class="zd" style="background:${LEVEL_COLORS[z.level]}"></span>` +
            `${escapeHtml(z.city)}<b>${z.total}</b></div>`).join('');
        list.querySelectorAll('.zone-item').forEach(el => {
            el.addEventListener('click', () => {
                const z = watched[+el.dataset.i];
                map.flyTo([z.lat, z.lng], 12);
                if (!zonesOn) { zonesOn = true; document.getElementById('zonesToggle').classList.add('active'); }
                drawZoneCircles(lastZones);
            });
        });
    }

    async function loadZones() {
        try {
            lastZones = await api('/map/zones');
            renderZonesPanel(lastZones);
            if (zonesOn) drawZoneCircles(lastZones); else zonesLayer.clearLayers();
        } catch (e) { console.error('Zones', e); }
    }

    document.getElementById('zonesToggle').addEventListener('click', e => {
        zonesOn = !zonesOn;
        e.target.classList.toggle('active', zonesOn);
        if (zonesOn) drawZoneCircles(lastZones); else zonesLayer.clearLayers();
    });

    // --- Filtres par niveau ---
    document.getElementById('levelFilters').addEventListener('click', e => {
        const btn = e.target.closest('button');
        if (!btn) return;
        currentLevel = btn.dataset.level;
        document.querySelectorAll('#levelFilters button').forEach(b => b.classList.toggle('active', b === btn));
        loadPoints();
    });

    // --- Bascule heatmap ---
    document.getElementById('heatToggle').addEventListener('click', async e => {
        heatOn = !heatOn;
        e.target.classList.toggle('active', heatOn);
        if (heatOn) {
            markersLayer.clearLayers();
            await loadHeat();
        } else {
            if (heatLayer) { map.removeLayer(heatLayer); heatLayer = null; }
            loadPoints();
        }
    });

    // --- Temps réel : rafraîchissement périodique ---
    setInterval(() => {
        if (heatOn) loadHeat(); else loadPoints();
        loadZones();
    }, REFRESH_MS);

    // Recharge aussi quand on revient sur l'onglet
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) { heatOn ? loadHeat() : loadPoints(); loadZones(); }
    });

    // Init
    document.getElementById('zonesToggle').classList.add('active'); // zones actives par défaut
    loadPoints();
    loadZones();
})();
