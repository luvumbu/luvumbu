/**
 * Point d'entrée de l'application.
 * Enchaîne : Connexion parent (Google) → Choix du profil enfant → Jeu.
 */
import { api, setAuth, getToken, setChild, clearAuth } from '../api/client.js';
import { startGame } from '../core/game.js';

const CLIENT_ID = '878381681024-6qnsarrvcrj935f56vln5uugc091gg7c.apps.googleusercontent.com';

const AVATARS = ['🦊', '🐨', '🐰', '🐼', '🐯', '🦁', '🐸', '🐵', '🦄', '🐧', '🐙', '🐬'];

const authScreen     = document.getElementById('auth-screen');
const childrenScreen = document.getElementById('children-screen');
const gameEl         = document.getElementById('game');
const authMsg        = document.getElementById('auth-msg');

const show = (el) => { if (el) el.hidden = false; };
const hide = (el) => { if (el) el.hidden = true; };

// ---------- Démarrage ----------
async function boot() {
  hide(gameEl); hide(childrenScreen); hide(authScreen);

  if (getToken()) {
    try {
      const data = await api.me();          // le jeton est-il encore valable ?
      return showChildren(data.children);
    } catch (e) {
      clearAuth();                           // jeton expiré → reconnexion
    }
  }
  showLogin();
}

// L'app Android expose window.AndroidAuth (connexion Google native).
const nativeAuth = (typeof window !== 'undefined' && window.AndroidAuth) ? window.AndroidAuth : null;

// ---------- Connexion Google ----------
function showLogin() {
  hide(childrenScreen); hide(gameEl); show(authScreen);

  if (nativeAuth) {
    // Dans l'APPLICATION : bouton maison → connexion Google NATIVE.
    const gbtn = document.getElementById('gbtn');
    gbtn.innerHTML =
      '<button class="native-google-btn">' +
      '<span style="font-weight:900">G</span>&nbsp; Se connecter avec Google</button>';
    gbtn.querySelector('button').onclick = () => {
      if (authMsg) authMsg.textContent = 'Connexion…';
      nativeAuth.signIn();
    };
    return;
  }

  // Dans le NAVIGATEUR : bouton officiel Google Identity Services.
  whenGoogleReady(() => {
    google.accounts.id.initialize({ client_id: CLIENT_ID, callback: onGoogle });
    google.accounts.id.renderButton(
      document.getElementById('gbtn'),
      { theme: 'filled_blue', size: 'large', text: 'signin_with', shape: 'pill', width: 260 }
    );
  });
}

// Appelés par l'app Android après la connexion native.
window.__onGoogleToken = (idToken) => handleIdToken(idToken);
window.__onGoogleError = (msg) => {
  if (authMsg) authMsg.textContent = '❌ Connexion annulée : ' + msg;
};

function whenGoogleReady(cb, tries = 0) {
  if (window.google && google.accounts && google.accounts.id) return cb();
  if (tries > 50) {
    if (authMsg) authMsg.textContent = '⚠️ Impossible de charger Google. Vérifie ta connexion Internet.';
    return;
  }
  setTimeout(() => whenGoogleReady(cb, tries + 1), 100);
}

function onGoogle(resp) {
  handleIdToken(resp.credential);
}

async function handleIdToken(idToken) {
  if (authMsg) authMsg.textContent = 'Connexion…';
  try {
    const data = await api.googleLogin(idToken);
    setAuth(data.token);
    if (authMsg) authMsg.textContent = '';
    showChildren(data.children);
  } catch (e) {
    if (authMsg) authMsg.textContent = '❌ Connexion refusée : ' + e.message;
  }
}

// ---------- Choix / création d'un enfant ----------
function showChildren(children) {
  hide(authScreen); hide(gameEl); show(childrenScreen);
  renderChildren(children || []);
}

function renderChildren(children) {
  const grid = document.getElementById('children-grid');
  grid.innerHTML = '';

  children.forEach((c) => {
    const tile = document.createElement('div');
    tile.className = 'child-tile';
    tile.innerHTML =
      `<span class="child-del" title="Supprimer ce profil">🗑️</span>` +
      `<span class="child-ava">${c.avatar || '🐣'}</span>` +
      `<span class="child-name">${escapeHtml(c.name)}</span>`;
    tile.onclick = () => pickChild(c);
    // 🗑️ Supprimer ce profil (créature + progression)
    tile.querySelector('.child-del').onclick = async (e) => {
      e.stopPropagation();
      if (!confirm(`Supprimer le profil de ${c.name} ?\nSa créature et toute sa progression seront effacées.`)) return;
      try {
        await api.delChild(c.id);
        const data = await api.children();
        renderChildren(data.children);
      } catch (err) { alert('Erreur : ' + err.message); }
    };
    grid.appendChild(tile);
  });

  const add = document.createElement('div');
  add.className = 'child-tile child-add';
  add.innerHTML = `<span class="child-ava">➕</span><span class="child-name">Ajouter</span>`;
  add.onclick = showAddForm;
  grid.appendChild(add);
}

function pickChild(child) {
  setChild(child.id);
  hide(childrenScreen); hide(authScreen); show(gameEl);
  startGame();
}

// Petit formulaire d'ajout (prénom + avatar) sans quitter l'écran.
function showAddForm() {
  const box = document.getElementById('add-form');
  box.hidden = false;
  const input = document.getElementById('add-name');
  input.value = '';
  input.focus();

  const avaWrap = document.getElementById('add-avatars');
  avaWrap.innerHTML = '';
  let chosen = AVATARS[0];
  AVATARS.forEach((a, i) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ava-pick' + (i === 0 ? ' on' : '');
    btn.textContent = a;
    btn.onclick = () => {
      chosen = a;
      avaWrap.querySelectorAll('.ava-pick').forEach((x) => x.classList.remove('on'));
      btn.classList.add('on');
    };
    avaWrap.appendChild(btn);
  });

  document.getElementById('add-ok').onclick = async () => {
    const name = input.value.trim();
    if (!name) { input.focus(); return; }
    try {
      await api.addChild(name, chosen);
      box.hidden = true;
      const data = await api.children();
      renderChildren(data.children);
    } catch (e) {
      alert('Erreur : ' + e.message);
    }
  };
  document.getElementById('add-cancel').onclick = () => { box.hidden = true; };
}

// ---------- Déconnexion / changement de profil ----------
document.getElementById('logout-btn')?.addEventListener('click', () => {
  clearAuth();
  showLogin();
});

// 🗑️ Effacer TOUTES les données (compte + enfants + créatures). Double confirmation.
document.getElementById('wipe-btn')?.addEventListener('click', async () => {
  if (!confirm('⚠️ Effacer TOUTES tes données ?\n\nTous les profils, créatures et progressions seront supprimés définitivement.')) return;
  if (!confirm('Es-tu vraiment sûr ? Cette action est IRRÉVERSIBLE.')) return;
  try {
    await api.deleteAccount();
    clearAuth();
    alert('✅ Toutes les données ont été effacées.');
    showLogin();
  } catch (e) {
    alert('Erreur : ' + e.message);
  }
});
document.getElementById('switch-profile')?.addEventListener('click', async () => {
  try {
    const data = await api.children();
    showChildren(data.children);
  } catch (e) {
    showLogin();
  }
});

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

boot();
