/* ============================================================
   signaler.js — formulaire de signalement (réservé aux membres).
   ============================================================ */

(function () {
    'use strict';
    const { api, auth, escapeHtml } = window.App;

    // 1) Accès réservé aux membres connectés
    if (!auth.requireAuth()) return;

    const form = document.getElementById('reportForm');
    const alertBox = document.getElementById('alert');
    const submitBtn = document.getElementById('submitBtn');
    let selectedFiles = [];

    // Pré-remplit le nom affiché
    const u = auth.user();
    if (u) document.getElementById('reporterDisplay').value = u.display_name || '';

    // Date max = aujourd'hui
    document.getElementById('incidentDate').max = new Date().toISOString().split('T')[0];

    // --- Chargement des référentiels ---
    async function loadReferences() {
        try {
            const [groups, motifs, types] = await Promise.all([
                api('/categories'), api('/motifs'), api('/discrimination-types')
            ]);
            // Catégories groupées
            const sel = document.getElementById('categorySelect');
            sel.innerHTML = '<option value="">— Choisir —</option>';
            groups.forEach(g => {
                const og = document.createElement('optgroup');
                og.label = g.name;
                g.categories.forEach(c => {
                    const o = document.createElement('option');
                    o.value = c.id; o.textContent = c.name;
                    og.appendChild(o);
                });
                sel.appendChild(og);
            });
            renderChips('motifsBox', 'motifs', motifs);
            renderChips('typesBox', 'discrimination_types', types);
        } catch (e) {
            showAlert('Impossible de charger le formulaire. Rechargez la page.');
        }
    }

    function renderChips(boxId, name, items) {
        const box = document.getElementById(boxId);
        box.innerHTML = '';
        items.forEach(it => {
            const wrap = document.createElement('span');
            wrap.className = 'chip-check';
            const id = name + '_' + it.id;
            wrap.innerHTML = `<input type="checkbox" id="${id}" value="${it.id}" name="${name}[]"><label for="${id}">${escapeHtml(it.name)}</label>`;
            box.appendChild(wrap);
        });
    }

    // --- Compteur de caractères ---
    const desc = form.querySelector('[name=description]');
    const charCount = document.getElementById('charCount');
    desc.addEventListener('input', () => { charCount.textContent = desc.value.length; });

    // --- Anonyme : masque le nom affiché ---
    const isAnon = document.getElementById('isAnon');
    const displayField = document.getElementById('f-reporter_display');
    isAnon.addEventListener('change', () => {
        displayField.style.display = isAnon.checked ? 'none' : 'block';
    });

    // --- Géolocalisation ---
    document.getElementById('geoBtn').addEventListener('click', () => {
        if (!navigator.geolocation) { showAlert('La géolocalisation n\'est pas disponible.'); return; }
        const btn = document.getElementById('geoBtn');
        btn.textContent = '📍 Localisation…'; btn.disabled = true;
        navigator.geolocation.getCurrentPosition(
            pos => {
                document.getElementById('lat').value = pos.coords.latitude.toFixed(7);
                document.getElementById('lng').value = pos.coords.longitude.toFixed(7);
                document.getElementById('geoResult').classList.add('show');
                btn.textContent = '📍 Position enregistrée'; btn.disabled = false;
            },
            () => { btn.textContent = '📍 Utiliser ma position actuelle'; btn.disabled = false; showAlert('Impossible d\'obtenir votre position.'); },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    // --- Autocomplétion d'adresse (Nominatim via le backend) ---
    (function initAddressAutocomplete() {
        const input = document.getElementById('addressInput');
        const list = document.getElementById('acList');
        let timer = null, items = [], activeIdx = -1;

        function close() { list.classList.remove('open'); list.innerHTML = ''; activeIdx = -1; }
        function open() { list.classList.add('open'); }

        input.addEventListener('input', () => {
            const q = input.value.trim();
            clearTimeout(timer);
            // Dès qu'on retape, l'adresse n'est plus « validée »
            document.getElementById('lat').value = '';
            document.getElementById('lng').value = '';
            document.getElementById('geoResult').classList.remove('show');
            if (q.length < 3) { close(); return; }
            list.innerHTML = '<li class="ac-loading">Recherche…</li>'; open();
            timer = setTimeout(() => search(q), 350);
        });

        async function search(q) {
            try {
                items = await api('/geo/search?q=' + encodeURIComponent(q), { auth: true });
                render();
            } catch {
                list.innerHTML = '<li><span class="ac-empty">Recherche indisponible</span></li>';
            }
        }

        function render() {
            if (!items.length) { list.innerHTML = '<li><span class="ac-empty">Aucune adresse trouvée</span></li>'; return; }
            list.innerHTML = items.map((it, i) =>
                `<li data-i="${i}"><span class="pin">📍</span><span>${escapeHtml(it.label)}</span></li>`).join('');
            open();
            list.querySelectorAll('li').forEach(li => {
                li.addEventListener('click', () => choose(items[+li.dataset.i]));
            });
        }

        function choose(it) {
            input.value = it.address || it.label.split(',')[0];
            document.getElementById('cityInput').value = it.city || '';
            document.getElementById('postalInput').value = (it.postal_code || '').slice(0, 20);
            document.getElementById('deptInput').value = it.department || '';
            document.getElementById('regionInput').value = it.region || '';
            if (it.lat && it.lng) {
                document.getElementById('lat').value = Number(it.lat).toFixed(7);
                document.getElementById('lng').value = Number(it.lng).toFixed(7);
                const gr = document.getElementById('geoResult');
                gr.textContent = 'Adresse validée ✓'; gr.classList.add('show');
            }
            close();
        }

        // Navigation clavier
        input.addEventListener('keydown', e => {
            const lis = list.querySelectorAll('li[data-i]');
            if (!list.classList.contains('open') || !lis.length) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = Math.min(activeIdx + 1, lis.length - 1); highlight(lis); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(activeIdx - 1, 0); highlight(lis); }
            else if (e.key === 'Enter' && activeIdx >= 0) { e.preventDefault(); choose(items[activeIdx]); }
            else if (e.key === 'Escape') { close(); }
        });
        function highlight(lis) { lis.forEach((li, i) => li.classList.toggle('active', i === activeIdx)); }

        document.addEventListener('click', e => { if (!e.target.closest('.autocomplete')) close(); });
    })();

    // --- Fichiers ---
    const fileDrop = document.getElementById('fileDrop');
    const fileInput = document.getElementById('fileInput');
    const fileList = document.getElementById('fileList');

    fileDrop.addEventListener('click', () => fileInput.click());
    fileDrop.addEventListener('dragover', e => { e.preventDefault(); fileDrop.classList.add('drag'); });
    fileDrop.addEventListener('dragleave', () => fileDrop.classList.remove('drag'));
    fileDrop.addEventListener('drop', e => {
        e.preventDefault(); fileDrop.classList.remove('drag');
        addFiles(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', () => addFiles(fileInput.files));

    function addFiles(list) {
        for (const f of list) {
            if (f.size > 20 * 1024 * 1024) { showAlert(`« ${f.name} » dépasse 20 Mo.`); continue; }
            selectedFiles.push(f);
        }
        renderFiles();
    }
    function renderFiles() {
        fileList.innerHTML = '';
        selectedFiles.forEach((f, i) => {
            const el = document.createElement('div');
            el.className = 'file-item';
            el.innerHTML = `<span>${escapeHtml(f.name)}</span><button type="button" title="Retirer">✕</button>`;
            el.querySelector('button').addEventListener('click', () => { selectedFiles.splice(i, 1); renderFiles(); });
            fileList.appendChild(el);
        });
    }

    // --- Soumission ---
    form.addEventListener('submit', async e => {
        e.preventDefault();
        clearErrors();

        // Validation légère côté client
        const checkedMotifs = form.querySelectorAll('[name="motifs[]"]:checked').length;
        const checkedTypes = form.querySelectorAll('[name="discrimination_types[]"]:checked').length;
        if (!checkedMotifs) { fieldError('motifs', 'Sélectionnez au moins un motif.'); return; }
        if (!checkedTypes) { fieldError('discrimination_types', 'Sélectionnez au moins un type.'); return; }

        const fd = new FormData(form);
        // Ajoute les fichiers gérés manuellement
        fd.delete('media[]');
        selectedFiles.forEach(f => fd.append('media[]', f));
        // Anonyme => pas de nom affiché
        if (isAnon.checked) fd.delete('reporter_display');

        submitBtn.disabled = true; submitBtn.textContent = 'Envoi…';
        try {
            const res = await api('/reports', { method: 'POST', body: fd, isForm: true, auth: true });
            showSuccess(res);
        } catch (err) {
            if (err.errors && Object.keys(err.errors).length) {
                for (const [k, v] of Object.entries(err.errors)) fieldError(k, Array.isArray(v) ? v[0] : v);
                showAlert('Veuillez corriger les champs indiqués.');
            } else {
                showAlert(err.message || 'Envoi impossible.');
            }
            submitBtn.disabled = false; submitBtn.textContent = 'Envoyer le signalement';
        }
    });

    function showSuccess(res) {
        document.getElementById('formWrap').style.display = 'none';
        const w = document.getElementById('successWrap');
        w.style.display = 'block';
        document.getElementById('successTitle').textContent =
            res.status === 'published' ? 'Signalement publié' : 'Signalement envoyé';
        document.getElementById('successMsg').textContent = res.message || '';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // --- Helpers UI ---
    function showAlert(msg) { alertBox.classList.add('error'); alertBox.textContent = msg; window.scrollTo({ top: 0, behavior: 'smooth' }); }
    function clearErrors() {
        alertBox.classList.remove('error'); alertBox.textContent = '';
        document.querySelectorAll('.field').forEach(f => f.classList.remove('has-error'));
    }
    function fieldError(id, msg) {
        const f = document.getElementById('f-' + id);
        if (f) { f.classList.add('has-error'); const e = f.querySelector('.err'); if (e) e.textContent = msg; }
    }

    loadReferences();
})();
