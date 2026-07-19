/**
 * Page cours.html : affiche le cours complet d'un exercice.
 * URL : cours.html?topic=roman  (le suffixe d'âge 4-9/a est retiré automatiquement)
 */
import { COURSES } from './data/courses.js';

const params = new URLSearchParams(location.search);
const raw    = params.get('topic') || '';
const topic  = raw.replace(/[4-9a]$/, '');       // colors4 → colors, romana → roman
const course = COURSES[topic];

const el = document.getElementById('course');

// Le bouton « Faire l'exercice » relance directement ce thème dans le jeu.
if (raw) {
  document.getElementById('do-exercise').setAttribute('href', 'index.html?play=' + encodeURIComponent(raw));
}

function esc(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

if (!course) {
  el.innerHTML = `<div class="missing">📖 Cours introuvable pour « ${esc(raw)} ».<br>Reviens au jeu et réessaie.</div>`;
} else {
  let html = `
    <div class="header">
      <div class="emoji">${course.emoji}</div>
      <h1>${esc(course.title)}</h1>
      <p class="intro">${esc(course.intro)}</p>
    </div>`;

  (course.sections || []).forEach((s) => {
    html += `<div class="section"><h2>${esc(s.h)}</h2>`;
    (s.p || []).forEach((p) => { html += `<p>${esc(p)}</p>`; });
    if (s.ex && s.ex.length) {
      html += '<div class="ex">' + s.ex.map((e) => `<div>${esc(e)}</div>`).join('') + '</div>';
    }
    html += '</div>';
  });

  if (course.tips && course.tips.length) {
    html += '<div class="tips"><h2>💡 Astuces</h2><ul>' +
      course.tips.map((t) => `<li>${esc(t)}</li>`).join('') + '</ul></div>';
  }

  if (course.remember) {
    html += `<div class="remember"><span class="lbl">À RETENIR</span>${esc(course.remember)}</div>`;
  }

  el.innerHTML = html;
}

// Lecture à voix haute de tout le cours
document.getElementById('listen').addEventListener('click', () => {
  if (!course || !('speechSynthesis' in window)) return;
  const parts = [course.title, course.intro];
  (course.sections || []).forEach((s) => {
    parts.push(s.h);
    (s.p || []).forEach((p) => parts.push(p));
    (s.ex || []).forEach((e) => parts.push(e));
  });
  (course.tips || []).forEach((t) => parts.push(t));
  if (course.remember) parts.push('À retenir : ' + course.remember);

  window.speechSynthesis.cancel();
  window.speechSynthesis.resume();
  parts.forEach((text) => {
    const u = new SpeechSynthesisUtterance(text);
    u.lang = 'fr-FR';
    u.rate = 0.9;
    window.speechSynthesis.speak(u);
  });
});
