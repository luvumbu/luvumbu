/**
 * Petite couche d'accès à l'API PHP.
 * Le reste du front n'appelle QUE ces fonctions, jamais fetch() directement.
 *
 * Chaque requête transporte :
 *  - le JETON du parent connecté (Google) → sécurité côté serveur
 *  - l'ID de l'ENFANT (profil) sélectionné → chaque enfant a sa créature
 */

// Chemin de l'API calculé par rapport à la page (public/ → ../api).
const API_BASE = new URL('../api', window.location.href).href.replace(/\/$/, '');

let token   = localStorage.getItem('tama_token') || '';
let childId = localStorage.getItem('tama_child') || '';

// ---- Gestion de session (utilisé par auth.js) ----
export function setAuth(t)   { token = t || ''; localStorage.setItem('tama_token', token); }
export function getToken()   { return token; }
export function setChild(id) { childId = String(id); localStorage.setItem('tama_child', childId); }
export function getChild()   { return childId; }
export function clearAuth()  {
  token = ''; childId = '';
  localStorage.removeItem('tama_token');
  localStorage.removeItem('tama_child');
}

async function request(method, path, body = null) {
  const url = new URL(API_BASE + path, window.location.href);
  if (token) url.searchParams.set('token', token);          // jeton en query (fiable sur hébergement mutualisé)

  const options = { method, headers: { 'Content-Type': 'application/json' } };
  if (token) options.headers['Authorization'] = 'Bearer ' + token;
  if (body)  options.body = JSON.stringify(body);

  const res  = await fetch(url, options);
  const json = await res.json();
  if (!json.success) {
    throw new Error(json.error || 'Erreur API');
  }
  return json.data;
}

export const api = {
  ping:   ()           => request('GET',  '/ping'),

  // --- Connexion parent (Google) & profils enfants ---
  googleLogin: (idToken)     => request('POST', '/auth/google', { id_token: idToken }),
  me:          ()            => request('GET',  '/auth/me'),
  children:    ()            => request('GET',  '/children'),
  addChild:    (name, avatar)=> request('POST', '/children', { name, avatar }),
  delChild:    (id)          => request('POST', '/children/delete', { child_id: id }),
  deleteAccount: ()          => request('POST', '/auth/delete'),

  // --- Créatures (rattachées à l'enfant sélectionné) ---
  pets:   ()           => request('GET',  `/pets?child_id=${encodeURIComponent(childId)}`),
  pet:    (id)         => request('GET',  `/pets/${id}`),
  create: (name, speciesId = 1) =>
            request('POST', '/pets', { name, species_id: speciesId, child_id: Number(childId) }),
  feed:   (id)         => request('POST', `/pets/${id}/feed`),
  play:   (id)         => request('POST', `/pets/${id}/play`),
  sleep:  (id)         => request('POST', `/pets/${id}/sleep`),

  // Module Apprendre
  question: (topic, petId)   => request('GET',  `/learn/question?topic=${topic}&pet_id=${petId}`),
  answer:   (petId, tk, ans, topic) => request('POST', '/learn/answer', { pet_id: petId, token: tk, answer: ans, topic }),
  bonus:    (petId, count, correct) => request('POST', '/learn/bonus', { pet_id: petId, count, correct }),
  progress: (petId)          => request('GET',  `/learn/progress?pet_id=${petId}`),

  // Boutique
  shopList: ()               => request('GET',  '/shop'),
  buy:      (petId, itemId)  => request('POST', '/shop/buy', { pet_id: petId, item_id: itemId }),
};
