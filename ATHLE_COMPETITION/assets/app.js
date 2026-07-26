/**
 * Carte des villes de compétition.
 *
 * Un marqueur par ville, dimensionné selon le nombre de compétitions ;
 * la liste latérale et la carte restent synchronisées dans les deux sens.
 */
(function () {
    'use strict';

    const form = document.getElementById('filters');
    const resultsEl = document.getElementById('results');
    const selectionEl = document.getElementById('selection');
    const selectionLabel = document.getElementById('selection-label');
    const mapNote = document.getElementById('map-note');

    const state = {
        view: 'competitions',
        cityId: null,
        origin: null, // { lat, lon, label } — point de référence pour les distances
        data: { competitions: [], cities: [], stats: {} },
        markers: new Map(),
        request: 0,
    };

    const STORAGE_KEY = 'athle.origin';

    // ---------------------------------------------------------------- carte

    const map = L.map('map', { zoomControl: true }).setView([50.6, 4.5], 8);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    const markerLayer = L.layerGroup().addTo(map);
    const originLayer = L.layerGroup().addTo(map);

    // ------------------------------------------------------------ utilitaires

    const DAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    function formatDate(iso) {
        if (!iso) return 'Date inconnue';
        const [y, m, d] = iso.split('-').map(Number);
        const date = new Date(Date.UTC(y, m - 1, d));
        return `${DAYS[date.getUTCDay()]} ${d} ${MONTHS[m - 1]} ${y}`;
    }

    function formatShortDate(iso) {
        if (!iso) return '—';
        const [y, m, d] = iso.split('-').map(Number);
        return `${d} ${MONTHS[m - 1].slice(0, 4)}. ${y}`;
    }

    function formatDistance(km) {
        return km < 10 ? `${km.toFixed(1)} km` : `${Math.round(km)} km`;
    }

    function envLabel(env) {
        if (env === 'in') return { css: 'in', text: 'Indoor' };
        if (env === 'out') return { css: 'out', text: 'Outdoor' };
        return { css: 'unknown', text: 'Type inconnu' };
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, (ch) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[ch]));
    }

    // ------------------------------------------------------------- chargement

    // Le menu des épreuves n'existe pas tant qu'aucune fiche détaillée n'a été
    // importée : tout ce qui le touche doit tolérer son absence.
    const eventSelect = document.getElementById('f-event');
    const eventNote = document.getElementById('event-note');

    function selectedDiscipline() {
        return eventSelect ? eventSelect.value : '';
    }

    function currentQuery() {
        const params = new URLSearchParams();
        const q = document.getElementById('f-q').value.trim();
        const from = document.getElementById('f-from').value;
        const to = document.getElementById('f-to').value;
        const env = document.getElementById('f-env').value;
        const country = document.getElementById('f-country').value;
        const past = document.getElementById('f-past').checked;
        const discipline = selectedDiscipline();

        if (q) params.set('q', q);
        if (from) params.set('from', from);
        if (to) params.set('to', to);
        if (env && env !== 'all') params.set('env', env);
        if (discipline) params.set('event', discipline);
        if (country) params.set('country', country);
        if (past) params.set('past', '1');

        if (state.origin) {
            params.set('lat', state.origin.lat);
            params.set('lon', state.origin.lon);
            const radius = document.getElementById('f-radius').value;
            if (radius) params.set('radius', radius);
            params.set('sort', document.getElementById('f-sort').value);
        }
        return params;
    }

    async function load() {
        const token = ++state.request;
        resultsEl.innerHTML = '<p class="placeholder">Chargement…</p>';

        let payload;
        try {
            const response = await fetch(`api/competitions.php?${currentQuery().toString()}`);
            payload = await response.json();
        } catch (error) {
            resultsEl.innerHTML = '<p class="placeholder">Impossible de contacter l’API.</p>';
            return;
        }

        if (token !== state.request) return; // une requête plus récente a pris la main

        if (payload.error) {
            resultsEl.innerHTML = `<p class="placeholder">${escapeHtml(payload.message || payload.error)}</p>`;
            return;
        }

        state.data = payload;

        if (eventNote) eventNote.hidden = !selectedDiscipline();

        // Une ville sélectionnée qui sort du filtre est désélectionnée.
        if (state.cityId !== null && !payload.cities.some((c) => c.id === state.cityId)) {
            setCity(null, { silent: true });
        }

        document.getElementById('stat-competitions').textContent = payload.stats.competitions;
        document.getElementById('stat-cities').textContent = payload.stats.cities;

        if (payload.stats.unlocated > 0) {
            mapNote.hidden = false;
            mapNote.textContent =
                `${payload.stats.unlocated} compétition(s) sans coordonnées : lancez « php bin/geocode.php ».`;
        } else {
            mapNote.hidden = true;
        }

        drawMarkers();
        render();
    }

    // -------------------------------------------------------------- marqueurs

    function drawMarkers() {
        markerLayer.clearLayers();
        state.markers.clear();

        const cities = state.data.cities;
        if (cities.length === 0) return;

        const max = Math.max(...cities.map((c) => c.count));

        cities.forEach((city) => {
            const size = 24 + Math.round(16 * (max > 1 ? (city.count - 1) / (max - 1) : 0));
            const kind = city.indoor && city.outdoor ? 'mixed' : city.indoor ? 'indoor' : 'outdoor';

            const marker = L.marker([city.latitude, city.longitude], {
                icon: L.divIcon({
                    className: '',
                    html: `<div class="marker-label ${kind}" style="width:${size}px;height:${size}px">${city.count}</div>`,
                    iconSize: [size, size],
                    iconAnchor: [size / 2, size / 2],
                }),
                title: `${city.name} — ${city.count} compétition(s)`,
            });

            marker.bindPopup(() => popupHtml(city));
            marker.on('click', () => setCity(state.cityId === city.id ? null : city.id));

            marker.addTo(markerLayer);
            state.markers.set(city.id, marker);
        });

        highlightMarkers();

        if (state.cityId === null) {
            const points = cities.map((c) => [c.latitude, c.longitude]);
            if (state.origin) points.push([state.origin.lat, state.origin.lon]);
            map.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 11 });
        }
    }

    // ------------------------------------------------- point de référence

    function drawOrigin() {
        originLayer.clearLayers();
        if (!state.origin) return;

        const radius = Number(document.getElementById('f-radius').value);
        if (radius > 0) {
            L.circle([state.origin.lat, state.origin.lon], {
                radius: radius * 1000,
                color: '#1f6feb',
                weight: 1,
                opacity: 0.5,
                fillColor: '#1f6feb',
                fillOpacity: 0.06,
            }).addTo(originLayer);
        }

        L.marker([state.origin.lat, state.origin.lon], {
            icon: L.divIcon({
                className: '',
                html: '<div class="marker-home" title="Mon adresse">⌂</div>',
                iconSize: [28, 28],
                iconAnchor: [14, 14],
            }),
            zIndexOffset: 1000,
        })
            .bindPopup(`<div class="popup-city">Mon point de départ</div><div class="popup-region">${escapeHtml(state.origin.label)}</div>`)
            .addTo(originLayer);
    }

    function setOrigin(origin, options = {}) {
        state.origin = origin;
        const resultEl = document.getElementById('locate-result');
        const controls = document.getElementById('distance-controls');

        if (!origin) {
            resultEl.hidden = true;
            controls.hidden = true;
            localStorage.removeItem(STORAGE_KEY);
            originLayer.clearLayers();
            if (!options.silent) load();
            return;
        }

        resultEl.hidden = false;
        resultEl.className = 'locate-result';
        resultEl.innerHTML =
            `<span>📍 ${escapeHtml(origin.label)}</span>` +
            '<button type="button" id="locate-clear" title="Oublier cette adresse">×</button>';
        resultEl.querySelector('#locate-clear').addEventListener('click', () => {
            document.getElementById('f-address').value = '';
            setOrigin(null);
        });

        controls.hidden = false;
        localStorage.setItem(STORAGE_KEY, JSON.stringify(origin));
        drawOrigin();
        if (!options.silent) load();
    }

    function showLocateError(message) {
        const resultEl = document.getElementById('locate-result');
        resultEl.hidden = false;
        resultEl.className = 'locate-result is-error';
        resultEl.textContent = message;
    }

    async function locateAddress() {
        const address = document.getElementById('f-address').value.trim();
        if (!address) {
            setOrigin(null);
            return;
        }

        const resultEl = document.getElementById('locate-result');
        resultEl.hidden = false;
        resultEl.className = 'locate-result';
        resultEl.textContent = 'Recherche de l’adresse…';

        try {
            const response = await fetch(`api/locate.php?q=${encodeURIComponent(address)}`);
            const payload = await response.json();
            if (!response.ok) {
                showLocateError(payload.error || 'Adresse introuvable.');
                return;
            }
            setOrigin({ lat: payload.lat, lon: payload.lon, label: payload.label });
        } catch (error) {
            showLocateError('Le service de géocodage est injoignable.');
        }
    }

    // ------------------------------------------------------- autocomplétion
    // Les suggestions viennent du répertoire local (api/suggest.php) : aucun
    // appel à Nominatim, dont la politique interdit l'autocomplétion.

    const addressInput = document.getElementById('f-address');
    const suggestionsEl = document.getElementById('address-suggestions');
    let suggestions = [];
    let highlighted = -1;
    let suggestTimer = null;

    function closeSuggestions() {
        suggestionsEl.hidden = true;
        suggestionsEl.innerHTML = '';
        addressInput.setAttribute('aria-expanded', 'false');
        suggestions = [];
        highlighted = -1;
    }

    function renderSuggestions() {
        if (suggestions.length === 0) {
            closeSuggestions();
            return;
        }
        suggestionsEl.innerHTML = suggestions
            .map(
                (s, i) => `
                <li role="option" data-index="${i}" class="${i === highlighted ? 'is-highlighted' : ''}"
                    aria-selected="${i === highlighted}">
                    <strong><span class="flag">${escapeHtml(s.country)}</span>${escapeHtml(s.label)}</strong>
                    <span>${escapeHtml(s.region || '')}</span>
                </li>`
            )
            .join('');
        suggestionsEl.hidden = false;
        addressInput.setAttribute('aria-expanded', 'true');
    }

    function chooseSuggestion(index) {
        const suggestion = suggestions[index];
        if (!suggestion) return;
        addressInput.value = suggestion.label;
        closeSuggestions();
        // Les coordonnées sont déjà connues : rien à géocoder.
        setOrigin({
            lat: suggestion.lat,
            lon: suggestion.lon,
            label: `${suggestion.label} (${suggestion.country_name})`,
        });
    }

    async function fetchSuggestions() {
        const value = addressInput.value.trim();
        if (value.length < 2) {
            closeSuggestions();
            return;
        }
        // Pas de filtre pays : on peut habiter Lille et courir en Belgique.
        try {
            const response = await fetch(`api/suggest.php?q=${encodeURIComponent(value)}`);
            const payload = await response.json();
            suggestions = Array.isArray(payload) ? payload : [];
            highlighted = -1;
            renderSuggestions();
        } catch (error) {
            closeSuggestions();
        }
    }

    addressInput.addEventListener('input', () => {
        clearTimeout(suggestTimer);
        suggestTimer = setTimeout(fetchSuggestions, 120);
    });

    addressInput.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (suggestions.length === 0) return;
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            highlighted = (highlighted + step + suggestions.length) % suggestions.length;
            renderSuggestions();
            return;
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            if (highlighted >= 0) {
                chooseSuggestion(highlighted);
            } else if (suggestions.length === 1) {
                chooseSuggestion(0);
            } else {
                closeSuggestions();
                locateAddress();
            }
            return;
        }
        if (event.key === 'Escape') {
            closeSuggestions();
        }
    });

    suggestionsEl.addEventListener('mousedown', (event) => {
        // mousedown plutôt que click : le blur du champ ne doit pas fermer la
        // liste avant que la sélection soit prise en compte.
        const li = event.target.closest('[data-index]');
        if (li) {
            event.preventDefault();
            chooseSuggestion(Number(li.dataset.index));
        }
    });

    addressInput.addEventListener('blur', () => setTimeout(closeSuggestions, 150));

    document.getElementById('f-locate').addEventListener('click', () => {
        closeSuggestions();
        locateAddress();
    });

    document.getElementById('f-gps').addEventListener('click', () => {
        if (!navigator.geolocation) {
            showLocateError('Votre navigateur ne fournit pas la géolocalisation.');
            return;
        }
        const resultEl = document.getElementById('locate-result');
        resultEl.hidden = false;
        resultEl.className = 'locate-result';
        resultEl.textContent = 'Localisation en cours…';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                document.getElementById('f-address').value = '';
                setOrigin({
                    lat: position.coords.latitude,
                    lon: position.coords.longitude,
                    label: 'Ma position actuelle',
                });
            },
            () => showLocateError('Position refusée ou indisponible.'),
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    document.getElementById('f-radius').addEventListener('change', drawOrigin);

    function popupHtml(city) {
        const list = state.data.competitions
            .filter((c) => c.city_id === city.id)
            .slice(0, 6)
            .map((c) => `<li><strong>${escapeHtml(c.title)}</strong><br>${formatShortDate(c.start_date)} · ${envLabel(c.environment).text}</li>`)
            .join('');

        const extra = city.count > 6 ? `<p class="popup-more">+ ${city.count - 6} autre(s)</p>` : '';

        return `
            <div class="popup-city">${escapeHtml(city.name)}</div>
            <div class="popup-region">${escapeHtml(city.region || '')} · ${city.count} compétition(s)</div>
            <ul class="popup-list">${list}</ul>${extra}`;
    }

    function highlightMarkers() {
        state.markers.forEach((marker, id) => {
            const el = marker.getElement();
            if (!el) return;
            const label = el.querySelector('.marker-label');
            if (label) label.classList.toggle('is-active', state.cityId === id);
        });
    }

    // ------------------------------------------------------------- sélection

    function setCity(cityId, options = {}) {
        state.cityId = cityId;

        if (cityId === null) {
            selectionEl.hidden = true;
        } else {
            const city = state.data.cities.find((c) => c.id === cityId);
            selectionEl.hidden = false;
            selectionLabel.textContent = city ? `Ville : ${city.name}` : '';
            if (city && !options.silent) {
                map.flyTo([city.latitude, city.longitude], Math.max(map.getZoom(), 11), { duration: 0.6 });
            }
        }

        highlightMarkers();
        if (!options.silent) render();
    }

    document.getElementById('selection-clear').addEventListener('click', () => setCity(null));

    // ---------------------------------------------------------------- rendu

    function render() {
        if (state.view === 'cities') {
            renderCities();
        } else {
            renderCompetitions();
        }
    }

    function renderCompetitions() {
        let items = state.data.competitions;
        if (state.cityId !== null) {
            items = items.filter((c) => c.city_id === state.cityId);
        }

        if (items.length === 0) {
            resultsEl.innerHTML = '<p class="placeholder">Aucune compétition pour ces critères.</p>';
            return;
        }

        const byDistance = state.origin && document.getElementById('f-sort').value === 'distance';
        const parts = [];
        let currentHeader = Symbol('none');

        items.forEach((c) => {
            // Tri par distance : plus de regroupement par jour, la date passe
            // dans la ligne elle-même.
            if (!byDistance && c.start_date !== currentHeader) {
                currentHeader = c.start_date;
                parts.push(`<div class="day-header">${escapeHtml(formatDate(c.start_date))}</div>`);
            }

            const env = envLabel(c.environment);
            const city = c.city_name ? `<span class="item-city">${escapeHtml(c.city_name)}</span>` : '';
            const organizer = c.organizer ? `<span>${escapeHtml(c.organizer)}</span>` : '';
            const participants = c.participants ? `<span>${c.participants} part.</span>` : '';
            const nogeo = c.located ? '' : '<span class="pill nogeo">Sans coordonnées</span>';
            const distance = c.distance_km !== null ? `<span class="pill dist">${formatDistance(c.distance_km)}</span>` : '';
            const date = byDistance ? `<span>${formatShortDate(c.start_date)}</span>` : '';

            parts.push(`
                <button type="button" class="item${state.cityId === c.city_id ? ' is-active' : ''}"
                        data-id="${c.id}" data-city="${c.city_id ?? ''}">
                    <div class="item-title">${escapeHtml(c.title)}</div>
                    <div class="item-meta">
                        ${distance}${city}${date}
                        <span class="pill ${env.css}">${env.text}</span>
                        ${organizer}${participants}${nogeo}
                    </div>
                </button>`);
        });

        resultsEl.innerHTML = parts.join('');
    }

    function renderCities() {
        const cities = state.data.cities;
        if (cities.length === 0) {
            resultsEl.innerHTML = '<p class="placeholder">Aucune ville pour ces critères.</p>';
            return;
        }

        resultsEl.innerHTML = cities
            .map((city) => {
                const distance =
                    city.distance_km !== null && city.distance_km !== undefined
                        ? `<span class="pill dist">${formatDistance(city.distance_km)}</span> `
                        : '';
                return `
                <button type="button" class="city-row${state.cityId === city.id ? ' is-active' : ''}" data-city="${city.id}">
                    <span>
                        <span class="city-name">${escapeHtml(city.name)}</span><br>
                        <span class="city-region">${distance}${escapeHtml(city.region || '')} · dès le ${formatShortDate(city.next_date)}</span>
                    </span>
                    <span class="city-count">${city.count}</span>
                </button>`;
            })
            .join('');
    }

    // Clic sur une compétition : ouvre sa fiche (épreuves, horaire, lieu).
    // Clic sur une ville : filtre la carte sur cette ville.
    resultsEl.addEventListener('click', (event) => {
        const competition = event.target.closest('.item[data-id]');
        if (competition) {
            openDetails(Number(competition.dataset.id));
            return;
        }

        const cityRow = event.target.closest('.city-row[data-city]');
        if (cityRow) {
            const id = Number(cityRow.dataset.city);
            setCity(state.cityId === id ? null : id);
        }
    });

    // -------------------------------------------------- fiche détaillée

    const drawer = document.getElementById('drawer');
    const drawerBody = document.getElementById('drawer-body');

    function closeDetails() {
        drawer.hidden = true;
        drawerBody.innerHTML = '';
        document.querySelectorAll('.item.is-open').forEach((el) => el.classList.remove('is-open'));
    }

    document.getElementById('drawer-close').addEventListener('click', closeDetails);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !drawer.hidden) closeDetails();
    });

    async function openDetails(id) {
        document.querySelectorAll('.item.is-open').forEach((el) => el.classList.remove('is-open'));
        const row = resultsEl.querySelector(`.item[data-id="${id}"]`);
        if (row) row.classList.add('is-open');

        drawer.hidden = false;
        drawerBody.innerHTML = '<p class="placeholder">Chargement de la fiche…</p>';

        let detail;
        try {
            const response = await fetch(`api/competition.php?id=${id}`);
            detail = await response.json();
            if (!response.ok) throw new Error(detail.error || 'Erreur');
        } catch (error) {
            drawerBody.innerHTML = '<p class="placeholder">Fiche indisponible.</p>';
            return;
        }

        drawerBody.innerHTML = detailHtml(detail);

        if (detail.latitude !== null && detail.longitude !== null) {
            map.flyTo([detail.latitude, detail.longitude], Math.max(map.getZoom(), 11), { duration: 0.6 });
        }
    }

    function detailHtml(d) {
        const env = envLabel(d.environment);

        const when = [formatDate(d.start_date)];
        if (d.end_date && d.end_date !== d.start_date) when.push(`→ ${formatDate(d.end_date)}`);

        const hours = d.start_time ? `${d.start_time}${d.end_time ? ` – ${d.end_time}` : ''}` : null;

        const place = [d.venue_address, d.venue, d.city_name].filter(Boolean)[0] || d.city_name || '';
        const placeLink = d.maps_url
            ? `<a href="${escapeHtml(d.maps_url)}" target="_blank" rel="noopener">${escapeHtml(place)} ↗</a>`
            : escapeHtml(place);

        const facts = [
            ['Quand', `${escapeHtml(when.join(' '))}${hours ? `<br><strong>${escapeHtml(hours)}</strong>` : ''}`],
            ['Où', `${placeLink}${d.region ? `<br><span class="muted">${escapeHtml(d.region)}</span>` : ''}`],
            ['Organisateur', d.organizer ? escapeHtml(d.organizer) : null],
            ['Conditions', d.conditions ? escapeHtml(d.conditions) : null],
            [
                'Inscriptions',
                d.registration_from
                    ? `du ${formatShortDate(d.registration_from)} au ${formatShortDate(d.registration_to)}`
                    : null,
            ],
            ['Participants', d.participants ? `${d.participants} inscrits` : null],
            [
                'Contact',
                d.contact_email
                    ? `<a href="mailto:${escapeHtml(d.contact_email)}">${escapeHtml(d.contact_email)}</a>`
                    : null,
            ],
        ]
            .filter(([, value]) => value)
            .map(([label, value]) => `<dt>${label}</dt><dd>${value}</dd>`)
            .join('');

        // --- épreuves par catégorie ------------------------------------------
        // L'épreuve filtrée est surlignée : dans une liste de 40 codes courts,
        // c'est le seul moyen de voir tout de suite pour quelles catégories
        // elle est ouverte.
        const wanted = selectedDiscipline();
        const times = d.event_times || {};

        let events = '<p class="drawer-empty">Les épreuves ne sont pas publiées pour cette compétition.</p>';
        if (d.blocks && d.blocks.length) {
            events = d.blocks
                .map((block) => {
                    const cats = (block.categories || [])
                        .map((c) => `<span class="cat" title="${escapeHtml(c.label || '')}">${escapeHtml(c.code)}</span>`)
                        .join('');
                    const groups = (block.groups || [])
                        .map((g) => {
                            const list = (g.events || [])
                                .map((e) => {
                                    if (!wanted || e.key !== wanted) {
                                        return `<li title="${escapeHtml(e.label || '')}">${escapeHtml(e.short)}</li>`;
                                    }
                                    // L'heure n'existe que si la compétition a
                                    // publié son horaire.
                                    const at = times[e.key]
                                        ? ` <span class="at">${escapeHtml(times[e.key])}</span>`
                                        : '';
                                    return `<li class="is-match" title="${escapeHtml(e.label || '')}">${escapeHtml(e.short)}${at}</li>`;
                                })
                                .join('');
                            return `${g.group ? `<p class="group">${escapeHtml(g.group)}</p>` : ''}<ul class="events">${list}</ul>`;
                        })
                        .join('');
                    return `<div class="block"><div class="cats">${cats}</div>${groups}</div>`;
                })
                .join('');
        }

        // --- chronologie ------------------------------------------------------
        // La fiche source ne montre que les premières lignes : le lien vers
        // l'horaire complet est donc systématique dès qu'il existe.
        const scheduleLink = d.schedule_url
            ? `<a class="head-link" href="${escapeHtml(d.schedule_url)}" target="_blank" rel="noopener">horaire complet ↗</a>`
            : '';

        let schedule = '';
        if (d.schedule && d.schedule.length) {
            schedule = `
                <h3>Horaire <span class="muted">— premières épreuves</span> ${scheduleLink}</h3>
                <table class="schedule">${d.schedule
                    .map((s) => {
                        const match = wanted && s.key === wanted ? ' class="is-match"' : '';
                        return `<tr${match}><td>${escapeHtml(s.time)}</td><td>${escapeHtml(s.event)}</td></tr>`;
                    })
                    .join('')}</table>`;
        } else if (d.schedule_url) {
            schedule = `<h3>Horaire ${scheduleLink}</h3>
                        <p class="drawer-empty">L'horaire détaillé n'est pas encore publié ici.</p>`;
        }

        return `
            <h2>${escapeHtml(d.title)}</h2>
            <div class="drawer-tags">
                <span class="pill ${env.css}">${env.text}</span>
                ${d.status ? `<span class="pill unknown">${escapeHtml(d.status)}</span>` : ''}
                ${d.distinct_events ? `<span class="pill dist">${d.distinct_events} épreuves</span>` : ''}
            </div>

            <dl class="facts">${facts}</dl>

            <h3>Épreuves ${d.categories ? `<span class="muted">— ${escapeHtml(d.categories)}</span>` : ''}</h3>
            ${events}

            ${schedule}

            <div class="drawer-actions">
                ${
                    d.registration_open
                        ? `<a class="drawer-cta primary" href="${escapeHtml(d.registration_url)}"
                              target="_blank" rel="noopener">
                               S'inscrire à cette compétition ↗
                           </a>
                           <p class="cta-note">Inscriptions ouvertes jusqu'au ${formatShortDate(d.registration_to)}</p>`
                        : d.registration_to
                          ? `<p class="cta-note closed">Inscriptions clôturées le ${formatShortDate(d.registration_to)}</p>`
                          : ''
                }
                ${secondaryActions(d)}
            </div>`;
    }

    /**
     * Liens secondaires, dédoublonnés : selon les compétitions, l'URL du
     * calendrier est déjà celle de l'horaire ou des inscrits, et deux boutons
     * identiques n'apporteraient rien.
     */
    function secondaryActions(d) {
        const seen = new Set([d.registration_url].filter(Boolean));
        return [
            [d.schedule_url, 'Horaire complet ↗'],
            [d.entrants_url, 'Voir les inscrits ↗'],
            [d.url, 'En savoir plus sur athletisme.app ↗'],
        ]
            .filter(([href]) => href && !seen.has(href) && seen.add(href))
            .map(
                ([href, label]) =>
                    `<a class="drawer-cta ghost" href="${escapeHtml(href)}" target="_blank" rel="noopener">${label}</a>`
            )
            .join('');
    }

    // --------------------------------------------------------------- onglets

    document.querySelectorAll('.tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach((t) => t.classList.toggle('is-active', t === tab));
            state.view = tab.dataset.view;
            render();
        });
    });

    // --------------------------------------------------------------- filtres

    let debounce = null;
    form.addEventListener('input', (event) => {
        // Le champ adresse ne déclenche rien tout seul : il exige un appel au
        // géocodeur, qu'on ne lance qu'à la validation explicite.
        if (event.target.id === 'f-address') return;

        const immediate = event.target.type !== 'search' && event.target.type !== 'text';
        clearTimeout(debounce);
        debounce = setTimeout(load, immediate ? 0 : 300);
    });
    form.addEventListener('submit', (event) => event.preventDefault());

    // ------------------------------------------------------------ démarrage

    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
        if (saved && typeof saved.lat === 'number' && typeof saved.lon === 'number') {
            setOrigin(saved, { silent: true });
            if (saved.label && saved.label !== 'Ma position actuelle') {
                document.getElementById('f-address').value = saved.label;
            }
        }
    } catch (error) {
        localStorage.removeItem(STORAGE_KEY);
    }

    load();
})();
