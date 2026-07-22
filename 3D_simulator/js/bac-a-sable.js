// Exécution du code utilisateur dans une iframe isolée.
//
// Pourquoi une iframe ? Le code édité crée une boucle de rendu infinie et des objets WebGL.
// Le relancer dans la page courante empilerait les boucles et les contextes GPU.
// Détruire puis recréer l'iframe garantit un état propre à chaque exécution,
// et une erreur de syntaxe n'emporte que l'aperçu, jamais l'éditeur.

import { IMPORT_MAP } from './page-autonome.js';

// Ce script tourne AVANT le code utilisateur, en JavaScript classique (non-module),
// donc il est garanti d'être exécuté même si le module échoue à la compilation.
const PRELUDE_JS = `
  const envoyer = (type, texte) => parent.postMessage({ source: 'bac-a-sable', type, texte }, '*');

  // Rediriger la console de l'iframe vers le panneau de l'éditeur.
  for (const niveau of ['log', 'warn', 'error']) {
    const original = console[niveau];
    console[niveau] = (...args) => {
      envoyer(niveau, args.map(a => {
        try { return typeof a === 'object' ? JSON.stringify(a) : String(a); }
        catch { return String(a); }
      }).join(' '));
      original.apply(console, args);
    };
  }

  addEventListener('error', (e) => {
    const ligne = e.lineno ? ' (ligne ' + Math.max(1, e.lineno - LIGNES_PRELUDE) + ')' : '';
    envoyer('error', (e.message || 'Erreur') + ligne);
  });
  addEventListener('unhandledrejection', (e) => envoyer('error', 'Promesse rejetée : ' + e.reason));

  // Un exemple du bac à sable peut viser un élément qui n'existe pas (id mal tapé,
  // HUD non recopié). Plutôt que de planter sur un null, on renvoie un élément détaché
  // et on le signale : le reste du code continue de s'exécuter.
  const parId = document.getElementById.bind(document);
  document.getElementById = (id) => {
    const trouve = parId(id);
    if (trouve) return trouve;
    envoyer('warn', 'Élément #' + id + ' introuvable : un élément vide le remplace.');
    return Object.assign(document.createElement('div'), { id });
  };
`;

/**
 * @param {string} code   le module ES à exécuter
 * @param {object} options
 * @param {string} [options.corps]  HTML injecté dans le <body> (le HUD d'une leçon)
 * @param {string} [options.css]    feuille de style externe à charger
 */
function construirePage(code, { corps = '', css = '' } = {}) {
  const entete = [
    '<!DOCTYPE html>',
    '<html lang="fr"><head><meta charset="utf-8">',
    // Un lien cliqué dans le HUD recopié doit sortir de l'iframe, pas naviguer dedans.
    '<base target="_top">',
    css ? `<link rel="stylesheet" href="${css}">` : '',
    `<script type="importmap">${IMPORT_MAP}<\/script>`, // la même que la page exportée
    '<style>body{margin:0;overflow:hidden;background:#0e1116;color:#e6edf3;' +
      'font:14px system-ui,sans-serif}canvas{display:block}</style>',
    '</head><body>',
    corps.replace(/\n/g, ' '), // sur une seule ligne : le décalage des erreurs reste juste
  ];

  // Le numéro de ligne d'une erreur est compté depuis le début du document.
  // On mesure donc précisément ce qui précède le code utilisateur pour le soustraire.
  const prelude = `<script>const LIGNES_PRELUDE = %N%;${PRELUDE_JS}<\/script>`;
  const avant = [...entete, prelude, '<script type="module">'];
  const decalage = avant.join('\n').split('\n').length;

  return [
    ...entete,
    prelude.replace('%N%', decalage),
    '<script type="module">',
    // Un `</script>` dans une chaîne du code utilisateur fermerait la balise prématurément.
    code.replace(/<\/script>/gi, '<\\/script>'),
    '<\/script></body></html>',
  ].join('\n');
}

/**
 * Remplace l'iframe existante par une nouvelle qui exécute `code`.
 * @param {HTMLElement} conteneur  où insérer l'iframe
 * @param {string} code            le code utilisateur (un module ES)
 * @param {object} [options]       voir construirePage
 * @returns {HTMLIFrameElement}
 */
export function executer(conteneur, code, options) {
  conteneur.replaceChildren(); // détruit l'iframe précédente : boucle de rendu et contexte WebGL libérés

  const iframe = document.createElement('iframe');
  iframe.className = 'apercu';
  iframe.setAttribute('title', 'Aperçu du rendu');
  // `allow-same-origin` est nécessaire : sans lui, l'import map et le CDN sont bloqués.
  // `allow-top-navigation-by-user-activation` : les liens du HUD recopié (« ← Sommaire »)
  // doivent pouvoir quitter l'iframe quand l'utilisateur clique dessus.
  iframe.setAttribute(
    'sandbox',
    'allow-scripts allow-same-origin allow-top-navigation-by-user-activation'
  );
  iframe.srcdoc = construirePage(code, options);

  conteneur.append(iframe);
  return iframe;
}

/** S'abonne aux messages (console, erreurs) émis par l'iframe. */
export function ecouterMessages(callback) {
  addEventListener('message', (e) => {
    if (e.data?.source === 'bac-a-sable') callback(e.data.type, e.data.texte);
  });
}
