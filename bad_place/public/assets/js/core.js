/* ============================================================
   core.js — socle front partagé : détection de base, appels API,
   gestion de session (token + refresh auto), rendu de l'en-tête.
   Expose window.App
   ============================================================ */

(function () {
    'use strict';

    // --- Base de l'appli (robuste quel que soit le sous-dossier) ---
    function detectBase() {
        const s = document.querySelector('script[src*="assets/js/core.js"]');
        if (s) return s.src.replace(/\/assets\/js\/core\.js.*$/, '');
        return location.origin;
    }
    const BASE = detectBase();
    const API = BASE + '/api/v1';
    const PAGES = BASE + '/pages';   // les pages HTML (hors index) vivent dans /pages

    // --- Stockage de session ---
    const store = {
        get access() { return localStorage.getItem('bp_access'); },
        set access(v) { v ? localStorage.setItem('bp_access', v) : localStorage.removeItem('bp_access'); },
        get refresh() { return localStorage.getItem('bp_refresh'); },
        set refresh(v) { v ? localStorage.setItem('bp_refresh', v) : localStorage.removeItem('bp_refresh'); },
        get user() { try { return JSON.parse(localStorage.getItem('bp_user') || 'null'); } catch { return null; } },
        set user(v) { v ? localStorage.setItem('bp_user', JSON.stringify(v)) : localStorage.removeItem('bp_user'); },
        clear() { this.access = null; this.refresh = null; this.user = null; }
    };

    // --- Appel API bas niveau ---
    async function raw(path, { method = 'GET', body = null, auth = false, isForm = false } = {}) {
        const headers = { 'Accept': 'application/json' };
        if (auth && store.access) headers['Authorization'] = 'Bearer ' + store.access;
        let payload = body;
        if (body && !isForm) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
        const res = await fetch(API + path, { method, headers, body: payload });
        let data = null;
        try { data = await res.json(); } catch { /* pas de corps */ }
        return { res, data };
    }

    // --- Appel API avec refresh automatique en cas de 401 ---
    async function api(path, opts = {}) {
        let { res, data } = await raw(path, opts);

        if (res.status === 401 && opts.auth && store.refresh) {
            const ok = await tryRefresh();
            if (ok) ({ res, data } = await raw(path, opts));
        }
        if (!res.ok || (data && data.success === false)) {
            const err = new Error((data && data.message) || ('Erreur ' + res.status));
            err.status = res.status;
            err.errors = (data && data.errors) || {};
            err.code = data && data.code;
            throw err;
        }
        return data ? data.data : null;
    }

    async function tryRefresh() {
        try {
            const { res, data } = await raw('/auth/refresh', { method: 'POST', body: { refresh_token: store.refresh } });
            if (res.ok && data && data.success) {
                store.access = data.data.access_token;
                store.refresh = data.data.refresh_token;
                store.user = data.data.user;
                return true;
            }
        } catch { /* ignore */ }
        store.clear();
        return false;
    }

    // --- Authentification ---
    const auth = {
        isLoggedIn() { return !!store.access && !!store.user; },
        user() { return store.user; },

        async register(payload) {
            const d = await api('/auth/register', { method: 'POST', body: payload });
            store.access = d.access_token; store.refresh = d.refresh_token; store.user = d.user;
            return d.user;
        },
        async login(payload) {
            const d = await api('/auth/login', { method: 'POST', body: payload });
            store.access = d.access_token; store.refresh = d.refresh_token; store.user = d.user;
            return d.user;
        },
        async google(credential) {
            const d = await api('/auth/google', { method: 'POST', body: { credential } });
            store.access = d.access_token; store.refresh = d.refresh_token; store.user = d.user;
            return d.user;
        },
        async logout() {
            try { await api('/auth/logout', { method: 'POST', auth: true, body: { refresh_token: store.refresh } }); }
            catch { /* ignore */ }
            store.clear();
        },
        /** Redirige vers la connexion si non authentifié. Retourne true si OK. */
        requireAuth() {
            if (this.isLoggedIn()) return true;
            const next = encodeURIComponent(location.pathname.split('/').pop() + location.hash);
            location.href = PAGES + '/connexion.html?next=' + next;
            return false;
        }
    };

    // --- Rendu de l'en-tête selon l'état de connexion ---
    function renderHeaderAuth() {
        const box = document.querySelector('[data-auth-actions]');
        if (!box) return;
        if (auth.isLoggedIn()) {
            const u = auth.user();
            box.innerHTML =
                `<a href="${PAGES}/signaler.html" class="btn btn-primary">＋ Signaler</a>` +
                `<div class="user-menu">` +
                `<button class="user-btn" id="userBtn"><span class="avatar">${(u.display_name || '?')[0].toUpperCase()}</span>` +
                `<span class="user-name">${escapeHtml(u.display_name)}</span> ▾</button>` +
                `<div class="user-dropdown" id="userDrop">` +
                `<span class="ud-head">${escapeHtml(u.email || '')}</span>` +
                (u.role === 'admin' || u.role === 'moderator' ? `<a href="#" data-soon>Administration</a>` : '') +
                `<a href="#" data-soon>Mes signalements</a>` +
                `<a href="#" id="logoutBtn">Se déconnecter</a>` +
                `</div></div>`;
            const btn = document.getElementById('userBtn');
            const drop = document.getElementById('userDrop');
            btn.addEventListener('click', e => { e.stopPropagation(); drop.classList.toggle('open'); });
            document.addEventListener('click', () => drop.classList.remove('open'));
            document.getElementById('logoutBtn').addEventListener('click', async e => {
                e.preventDefault(); await auth.logout(); location.href = BASE + '/';
            });
        } else {
            box.innerHTML =
                `<a href="${PAGES}/connexion.html" class="btn btn-ghost">Se connecter</a>` +
                `<a href="${PAGES}/signaler.html" class="btn btn-primary">＋ Signaler</a>`;
        }
        bindSoon();
    }

    function bindSoon() {
        document.querySelectorAll('[data-soon]').forEach(el => {
            if (el.dataset.soonBound) return;
            el.dataset.soonBound = '1';
            el.addEventListener('click', e => {
                e.preventDefault();
                const orig = el.textContent;
                el.textContent = 'Bientôt disponible ⏳';
                setTimeout(() => { el.textContent = orig; }, 1500);
            });
        });
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    // --- Bandeau de consentement (cookies/stockage) ---
    function cookieBanner() {
        if (localStorage.getItem('bp_consent')) return;
        const el = document.createElement('div');
        el.className = 'cookie-banner show';
        el.innerHTML =
            `<p>Ce site utilise uniquement le stockage <strong>nécessaire à son fonctionnement</strong> ` +
            `(session de connexion). Aucun traçage publicitaire. En savoir plus : ` +
            `<a href="${BASE}/pages/confidentialite.html" style="color:var(--c-primary)">confidentialité</a>.</p>` +
            `<div class="actions"><button class="btn btn-primary" id="cookieOk">J'ai compris</button>` +
            `<a href="${BASE}/pages/confidentialite.html" class="btn btn-ghost">Politique de confidentialité</a></div>`;
        document.body.appendChild(el);
        el.querySelector('#cookieOk').addEventListener('click', () => {
            localStorage.setItem('bp_consent', '1');
            el.remove();
        });
    }

    // --- Bouton Google (Google Identity Services) ---
    function loadScript(src) {
        return new Promise((resolve, reject) => {
            if (document.querySelector(`script[src="${src}"]`)) { resolve(); return; }
            const s = document.createElement('script');
            s.src = src; s.async = true; s.defer = true;
            s.onload = resolve; s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    const GOOGLE_SVG = '<svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>';

    function fallbackGoogleButton(box, onError) {
        // Bouton visible même sans configuration : informe qu'il faut la clé Google.
        box.innerHTML = `<button type="button" class="btn-google">${GOOGLE_SVG}<span>Continuer avec Google</span></button>`;
        box.querySelector('button').addEventListener('click', () => {
            const msg = 'Connexion Google à configurer : ajoutez GOOGLE_CLIENT_ID dans config/.env.';
            if (onError) onError({ message: msg }); else alert(msg);
        });
        return true;
    }

    async function mountGoogle(containerId, onSuccess, onError) {
        const box = document.getElementById(containerId);
        if (!box) return false;
        let providers = null;
        try { providers = await api('/auth/providers'); } catch { return fallbackGoogleButton(box, onError); }
        if (!providers.google || !providers.google.enabled) { return fallbackGoogleButton(box, onError); }

        try { await loadScript('https://accounts.google.com/gsi/client'); }
        catch { return fallbackGoogleButton(box, onError); }
        if (!window.google || !google.accounts || !google.accounts.id) { return fallbackGoogleButton(box, onError); }

        google.accounts.id.initialize({
            client_id: providers.google.client_id,
            callback: async (resp) => {
                try {
                    await auth.google(resp.credential);
                    onSuccess && onSuccess();
                } catch (e) {
                    onError ? onError(e) : alert(e.message || 'Connexion Google échouée.');
                }
            }
        });
        google.accounts.id.renderButton(box, {
            theme: 'outline', size: 'large', text: 'continue_with',
            shape: 'rectangular', logo_alignment: 'center', locale: 'fr',
            width: Math.min(360, box.offsetWidth || 320)
        });
        return true;
    }

    // Expose
    window.App = { BASE, API, api, auth, store, renderHeaderAuth, bindSoon, escapeHtml, mountGoogle };

    document.addEventListener('DOMContentLoaded', () => { renderHeaderAuth(); cookieBanner(); });
})();
