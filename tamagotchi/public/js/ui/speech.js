/**
 * Voix (synthèse vocale) pour lire les consignes aux enfants.
 *
 * Deux moteurs possibles :
 *   1. Application Android → pont natif `window.AndroidTTS` (voix du téléphone).
 *      Nécessaire car la WebView ne fait PAS marcher speechSynthesis toute seule.
 *   2. Navigateur web       → API SpeechSynthesis standard.
 */

let frVoice = null;
let enabled = true;

// --- Pont natif Android (présent uniquement dans l'application) ---
const native = (typeof window !== 'undefined' && window.AndroidTTS) ? window.AndroidTTS : null;

const supported =
  native !== null ||
  (typeof window !== 'undefined' && 'speechSynthesis' in window);

// --- Callbacks « onStart » pour synchroniser voix + animation (mode natif) ---
let seqId = 0;
const onStartMap = Object.create(null);
if (typeof window !== 'undefined') {
  // Appelé depuis Android quand un morceau COMMENCE à être prononcé.
  window.__ttsOnStart = function (id) {
    const cb = onStartMap[id];
    if (cb) { delete onStartMap[id]; try { cb(); } catch (e) { /* ignore */ } }
  };
}

/** Cherche une voix française parmi celles installées (mode navigateur). */
function loadVoice() {
  if (native || !('speechSynthesis' in window)) return;
  const voices = window.speechSynthesis.getVoices();
  frVoice = voices.find((v) => v.lang && v.lang.toLowerCase().startsWith('fr')) || null;
}

if (supported && !native) {
  loadVoice();
  // Les voix arrivent parfois de façon asynchrone.
  window.speechSynthesis.onvoiceschanged = loadVoice;
}

/**
 * Lit un texte à voix haute (en français).
 * @param {string} text
 * @param {number} rate  vitesse (0.9 par défaut ; ~0.55 pour les explications lentes)
 */
export function speak(text, rate = 0.9) {
  if (!supported || !enabled || !text) return;

  if (native) {
    native.speak(String(text), rate, 1.15, true, 'one' + (seqId++));
    return;
  }

  window.speechSynthesis.cancel();               // coupe la lecture précédente
  window.speechSynthesis.resume();               // au cas où le moteur est en pause
  const u = new SpeechSynthesisUtterance(text);
  u.lang = 'fr-FR';
  u.rate = rate;
  u.pitch = 1.15;                                // voix plus douce/aiguë
  if (frVoice) u.voice = frVoice;
  window.speechSynthesis.speak(u);
}

/**
 * Lit une SÉQUENCE de courts morceaux, en synchro avec des effets visuels.
 * Chaque morceau { text, onStart } : onStart() est appelé au moment PRÉCIS
 * où ce morceau commence à être prononcé → parfaitement synchronisé.
 * Retourne true si la voix va parler (false si coupée/non supportée).
 */
export function speakSequence(items, rate = 0.6) {
  if (!supported || !enabled) return false;

  if (native) {
    native.stop();
    items.forEach((it, i) => {
      const id = 'seq' + (seqId++);
      if (it.onStart) onStartMap[id] = it.onStart;
      // flush=true sur le 1er morceau (coupe l'ancien), puis on enchaîne (QUEUE_ADD).
      native.speak(it.text || ' ', rate, 1.15, i === 0, id);
    });
    return true;
  }

  window.speechSynthesis.cancel();
  window.speechSynthesis.resume();
  for (const it of items) {
    const u = new SpeechSynthesisUtterance(it.text || ' ');
    u.lang = 'fr-FR';
    u.rate = rate;
    u.pitch = 1.15;
    if (frVoice) u.voice = frVoice;
    if (it.onStart) u.onstart = it.onStart;
    window.speechSynthesis.speak(u);           // les morceaux s'enchaînent dans l'ordre
  }
  return true;
}

/**
 * « Réveille » la synthèse vocale. À appeler DANS un gestionnaire de clic
 * (les navigateurs exigent un geste utilisateur direct pour autoriser l'audio).
 * En mode natif Android, rien à faire.
 */
export function warmUp() {
  if (native || !supported) return;
  try {
    window.speechSynthesis.resume();
    const u = new SpeechSynthesisUtterance(' ');  // utterance quasi vide = déverrouille l'audio
    u.volume = 0;
    window.speechSynthesis.speak(u);
  } catch (e) { /* ignore */ }
}

/** Active/coupe la voix. Retourne le nouvel état. */
export function toggleVoice() {
  enabled = !enabled;
  if (!enabled) {
    if (native) native.stop();
    else if (supported) window.speechSynthesis.cancel();
  }
  return enabled;
}

export function isVoiceOn() {
  return enabled;
}

export function voiceSupported() {
  return supported;
}
