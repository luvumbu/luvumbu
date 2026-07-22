// Coloration syntaxique JavaScript, minimale et sans dépendance.
// Partagée par le panneau de code (js/code-panel.js) et l'éditeur (editeur.html).

const MOTS_CLES = new RegExp(
  '\\b(const|let|var|function|return|if|else|for|of|in|new|import|export|from|class|extends|' +
  'async|await|this|null|undefined|true|false|continue|break|typeof|instanceof)\\b',
  'g'
);

// Un seul passage : la première alternative qui matche gagne, ce qui évite de colorier
// un mot-clé à l'intérieur d'une chaîne ou d'un commentaire.
const JETONS = new RegExp(
  [
    '(\\/\\/[^\\n]*)',                                          // 1 commentaire
    "('(?:\\\\.|[^'\\\\])*'|\"(?:\\\\.|[^\"\\\\])*\"|`[^`]*`)", // 2 chaîne
    '(\\b0x[0-9a-fA-F]+\\b|\\b\\d+\\.?\\d*\\b)',                // 3 nombre
    '(\\b[A-Za-z_$][\\w$]*(?=\\s*\\())',                        // 4 appel de fonction
    '(\\bTHREE\\b)',                                            // 5 namespace
  ].join('|'),
  'g'
);

export const echapper = (s) =>
  s.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));

// Les mots-clés ne sont cherchés que dans le texte « neutre », hors des jetons ci-dessus.
const motsCles = (s) => s.replace(MOTS_CLES, '<span class="c-kw">$1</span>');

/** Colorie UNE ligne de code et renvoie du HTML. */
export function colorier(ligne) {
  let html = '';
  let curseur = 0;

  for (const m of ligne.matchAll(JETONS)) {
    html += motsCles(echapper(ligne.slice(curseur, m.index)));

    // `if (`, `for (`, `return (`… ressemblent à des appels : ce sont des mots-clés.
    const estMotCle = m[4] !== undefined && new RegExp(`^${MOTS_CLES.source}$`).test(m[4]);

    const classe = m[1] ? 'c-com'
      : m[2] ? 'c-str'
      : m[3] ? 'c-num'
      : m[4] ? (estMotCle ? 'c-kw' : 'c-fn')
      : 'c-ns';

    html += `<span class="${classe}">${echapper(m[0])}</span>`;
    curseur = m.index + m[0].length;
  }

  return html + motsCles(echapper(ligne.slice(curseur)));
}

/** Colorie un bloc entier. Ligne à ligne : un `//` ne peut pas déborder sur la suivante. */
export const colorierBloc = (code) => code.split('\n').map(colorier).join('\n');
