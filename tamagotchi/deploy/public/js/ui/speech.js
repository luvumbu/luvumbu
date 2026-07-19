/**
 * Voix (synthèse vocale) pour lire les consignes aux enfants.
 * Utilise l'API navigateur SpeechSynthesis — aucune dépendance, hors-ligne.
 */

let frVoice = null;
let enabled = true;

const supported = typeof window !== 'undefined' && 'speechSynthesis' in window;

/** Cherche une voix française parmi celles installées sur l'appareil. */
function loadVoice() {
  if (!supported) return;
  const voices = window.speechSynthesis.getVoices();
  frVoice = voices.find((v) => v.lang && v.lang.toLowerCase().startsWith('fr')) || null;
}

if (supported) {
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
 */
export function warmUp() {
  if (!supported) return;
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
  if (!enabled && supported) window.speechSynthesis.cancel();
  return enabled;
}

export function isVoiceOn() {
  return enabled;
}

export function voiceSupported() {
  return supported;
}
