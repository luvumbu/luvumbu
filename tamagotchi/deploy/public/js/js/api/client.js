/**
 * Petite couche d'accès à l'API PHP.
 * Le reste du front n'appelle QUE ces fonctions, jamais fetch() directement.
 */

// Chemin de l'API calculé par rapport à la page (public/ → ../api).
// Fonctionne quel que soit l'hébergement (localhost, luvumbu.com, sous-dossier…).
const API_BASE = new URL('../api', window.location.href).href.replace(/\/$/, '');

async function request(method, path, body = null) {
  const options = {
    method,
    headers: { 'Content-Type': 'application/json' },
  };
  if (body) options.body = JSON.stringify(body);

  const res = await fetch(API_BASE + path, options);
  const json = await res.json();

  if (!json.success) {
    throw new Error(json.error || 'Erreur API');
  }
  return json.data;
}

export const api = {
  ping:   ()           => request('GET',  '/ping'),
  pets:   ()           => request('GET',  '/pets'),
  pet:    (id)         => request('GET',  `/pets/${id}`),
  create: (name, speciesId = 1) => request('POST', '/pets', { name, species_id: speciesId }),
  feed:   (id)         => request('POST', `/pets/${id}/feed`),
  play:   (id)         => request('POST', `/pets/${id}/play`),
  sleep:  (id)         => request('POST', `/pets/${id}/sleep`),

  // Module Apprendre
  question: (topic, petId)   => request('GET',  `/learn/question?topic=${topic}&pet_id=${petId}`),
  answer:   (petId, token, ans, topic) => request('POST', '/learn/answer', { pet_id: petId, token, answer: ans, topic }),
  bonus:    (petId, count, correct) => request('POST', '/learn/bonus', { pet_id: petId, count, correct }),
  progress: (petId)          => request('GET',  `/learn/progress?pet_id=${petId}`),

  // Boutique
  shopList: ()               => request('GET',  '/shop'),
  buy:      (petId, itemId)  => request('POST', '/shop/buy', { pet_id: petId, item_id: itemId }),
};
