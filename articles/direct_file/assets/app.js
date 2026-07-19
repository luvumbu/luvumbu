/* Logique du chat : polling AJAX, envoi de messages, gestion du pseudo. */
(function () {
    'use strict';

    var body = document.body;
    var code = body.dataset.code;
    var base = (body.dataset.base || '').replace(/\/+$/, ''); // racine de l'app, sans slash final

    var messagesEl = document.getElementById('messages');
    var sendForm   = document.getElementById('send-form');
    var msgInput   = document.getElementById('msg-input');

    var pseudoForm   = document.getElementById('pseudo-form');
    var pseudoInput  = document.getElementById('pseudo-input');
    var editPseudo   = document.getElementById('edit-pseudo');
    var currentPseudo = document.getElementById('current-pseudo');

    var lastId = 0;
    var firstLoad = true;

    /* --- Affiche un bandeau d'erreur visible en haut du flux --- */
    function showError(text) {
        var bar = document.getElementById('error-bar');
        if (!bar) {
            bar = document.createElement('div');
            bar.id = 'error-bar';
            bar.style.cssText = 'background:#7f1d1d;color:#fecaca;padding:8px 12px;'
                + 'border-radius:8px;margin-bottom:10px;font-size:13px';
            messagesEl.prepend(bar);
        }
        bar.textContent = '⚠️ ' + text;
    }
    function clearError() {
        var bar = document.getElementById('error-bar');
        if (bar) { bar.remove(); }
    }

    /* --- Échappement HTML côté client --- */
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    /* --- Ajoute un message au flux --- */
    function appendMessage(m) {
        var wrap = document.createElement('div');
        wrap.className = 'msg' + (m.mine ? ' mine' : '');
        wrap.innerHTML =
            '<div class="bubble">' +
                '<span class="author">' + esc(m.pseudo) + '</span>' +
                '<span class="text">' + esc(m.content) + '</span>' +
                '<span class="time">' + esc(m.time) + '</span>' +
            '</div>';
        messagesEl.appendChild(wrap);
    }

    /* --- Récupère les nouveaux messages --- */
    function poll() {
        fetch(base + '/api/messages?code=' + encodeURIComponent(code) + '&after=' + lastId, {
            headers: { 'X-Requested-With': 'fetch' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) { showError(data.error || 'Erreur inconnue'); return; }
            clearError();

            if (firstLoad) {
                messagesEl.innerHTML = '';
                firstLoad = false;
            }

            var nearBottom = messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight < 120;

            data.messages.forEach(function (m) {
                appendMessage(m);
                if (m.id > lastId) { lastId = m.id; }
            });

            // Auto-scroll si on était déjà en bas (ou au premier chargement).
            if (data.messages.length && nearBottom) {
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }
        })
        .catch(function () { showError('Connexion au serveur impossible (réessai en cours…)'); });
    }

    /* --- Envoi d'un message --- */
    sendForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var content = msgInput.value.trim();
        if (!content) { return; }

        var fd = new FormData();
        fd.append('code', code);
        fd.append('content', content);

        msgInput.value = '';
        msgInput.focus();

        fetch(base + '/api/messages', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) { poll(); }        // rafraîchit immédiatement
                else { showError(data.error || 'Envoi impossible'); }
            })
            .catch(function () { showError('Envoi impossible (serveur injoignable)'); });
    });

    /* --- Gestion du pseudo --- */
    editPseudo.addEventListener('click', function () {
        pseudoForm.style.display = 'flex';
        pseudoInput.focus();
    });

    pseudoForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var pseudo = pseudoInput.value.trim();
        if (!pseudo) { return; }

        var fd = new FormData();
        fd.append('code', code);
        fd.append('pseudo', pseudo);

        fetch(base + '/api/pseudo', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    currentPseudo.textContent = data.pseudo;
                    pseudoForm.style.display = 'none';
                }
            });
    });

    /* --- Couleurs personnelles (locales au navigateur) --- */
    (function () {
        var STORE   = 'df_user_theme';
        var toggle  = document.getElementById('color-toggle');
        var panel   = document.getElementById('color-panel');
        var reset   = document.getElementById('color-reset');
        var inputs  = panel ? panel.querySelectorAll('input[data-var]') : [];
        if (!toggle || !panel) { return; }

        function read() {
            try { return JSON.parse(localStorage.getItem(STORE) || '{}'); }
            catch (e) { return {}; }
        }
        function write(obj) { localStorage.setItem(STORE, JSON.stringify(obj)); }

        // Valeur courante d'une variable CSS (depuis l'override ou la feuille de style).
        function currentVar(name) {
            var v = getComputedStyle(document.documentElement).getPropertyValue('--' + name).trim();
            return /^#[0-9a-fA-F]{6}$/.test(v) ? v : '#000000';
        }

        // Initialise les sélecteurs avec les couleurs en vigueur.
        function syncInputs() {
            inputs.forEach(function (inp) { inp.value = currentVar(inp.dataset.var); });
        }
        syncInputs();

        toggle.addEventListener('click', function () { panel.hidden = !panel.hidden; });

        inputs.forEach(function (inp) {
            inp.addEventListener('input', function () {
                var name = inp.dataset.var;
                document.documentElement.style.setProperty('--' + name, inp.value);
                var saved = read();
                saved[name] = inp.value;
                write(saved);
            });
        });

        if (reset) {
            reset.addEventListener('click', function () {
                read(); // (no-op si vide)
                inputs.forEach(function (inp) {
                    document.documentElement.style.removeProperty('--' + inp.dataset.var);
                });
                localStorage.removeItem(STORE);
                syncInputs();
            });
        }
    })();

    /* --- Démarrage : polling toutes les 1,5 s --- */
    poll();
    setInterval(poll, 1500);
})();
