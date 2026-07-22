// Zone de saisie de code, réutilisée par le panneau des leçons et par le bac à sable.
//
// Technique : un <pre> coloré au fond, un <textarea> transparent par-dessus.
// Le texte lisible est celui du <pre> ; le curseur et la saisie sont ceux du <textarea>.
// Les deux doivent partager police, taille, interligne et marges, sinon le curseur se décale.

import { colorierBloc } from './highlight.js';

/**
 * @param {HTMLElement} hote        élément qui recevra l'éditeur (positionné en `relative`)
 * @param {object}   options
 * @param {string}   options.code       code initial
 * @param {Function} [options.onSaisie] appelée à chaque frappe
 * @param {Function} [options.onExecuter] appelée sur Ctrl/Cmd + Entrée
 */
export function creerEditeur(hote, { code = '', onSaisie, onExecuter } = {}) {
  hote.classList.add('editeur');
  hote.innerHTML = `
    <pre class="surlignage" aria-hidden="true"><code></code></pre>
    <textarea spellcheck="false" autocomplete="off" autocapitalize="off"
              aria-label="Éditeur de code"></textarea>`;

  const surlignage = hote.querySelector('.surlignage');
  const cible = hote.querySelector('.surlignage code');
  const saisie = hote.querySelector('textarea');

  // Le \n final évite que la dernière ligne soit rognée quand elle est vide.
  const rafraichir = () => (cible.innerHTML = colorierBloc(saisie.value) + '\n');

  const definir = (nouveau) => {
    saisie.value = nouveau;
    rafraichir();
  };

  saisie.addEventListener('input', () => {
    rafraichir();
    onSaisie?.(saisie.value);
  });

  // Le <pre> ne défile pas tout seul : on le suit à la trace.
  saisie.addEventListener('scroll', () => {
    surlignage.scrollTop = saisie.scrollTop;
    surlignage.scrollLeft = saisie.scrollLeft;
  });

  saisie.addEventListener('keydown', (e) => {
    if (e.key === 'Tab') {
      e.preventDefault(); // sinon Tab quitte le champ au lieu d'indenter
      saisie.setRangeText('  ', saisie.selectionStart, saisie.selectionEnd, 'end');
      rafraichir();
      onSaisie?.(saisie.value);
    } else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
      e.preventDefault();
      onExecuter?.(saisie.value);
    } else if (e.key === 'Escape') {
      e.stopPropagation(); // Échap dans l'éditeur ne doit pas fermer le panneau
      saisie.blur();
    }
  });

  definir(code);
  return { valeur: () => saisie.value, definir };
}
