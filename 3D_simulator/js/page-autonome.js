// Génère une page HTML complète et autonome à partir du code d'une démo.
//
// Le but : que « Copier » ou « Télécharger » produise un fichier qui fonctionne SEUL,
// sans XAMPP, sans ce projet, sans npm. On y met donc tout ce dont le code a besoin :
//   - l'import map, sinon `import ... from 'three'` échoue ;
//   - le CSS du HUD, puisque css/style.css n'existera pas à côté du fichier copié ;
//   - le HUD lui-même, sans quoi les curseurs de la leçon ne trouvent pas leurs éléments.

export const CDN = 'https://cdn.jsdelivr.net/npm/three@0.160.0';

export const IMPORT_MAP =
  `{"imports":{"three":"${CDN}/build/three.module.js",` +
  `"three/addons/":"${CDN}/examples/jsm/"}}`;

// Version réduite de css/style.css : juste de quoi afficher le HUD correctement.
const CSS_HUD = `
    body { margin: 0; overflow: hidden; background: #0e1116; color: #e6edf3;
           font-family: system-ui, "Segoe UI", sans-serif; }
    canvas { display: block; }
    .hud { position: fixed; top: 16px; left: 16px; max-width: 340px;
           background: rgba(22,27,34,.92); border: 1px solid #2a313c; border-radius: 8px;
           padding: 14px 16px; font-size: .85rem; line-height: 1.5; }
    .hud h2 { margin: 0 0 6px; font-size: 1rem; }
    .hud p { margin: 0 0 8px; color: #8b949e; }
    .hud a { color: #58a6ff; }
    .hud label { display: block; margin-top: 8px; color: #8b949e; }
    .hud input[type="range"] { width: 100%; }
    .hud kbd { background: #0e1116; border: 1px solid #2a313c; border-radius: 4px;
               padding: 1px 5px; font-size: .8em; }
    .hud select { width: 100%; margin-top: 3px; background: #0e1116; color: #e6edf3;
                  border: 1px solid #2a313c; border-radius: 6px; padding: 5px 7px;
                  font-family: inherit; font-size: .78rem; }
    .hud button { background: #161b22; color: #e6edf3; border: 1px solid #2a313c;
                  border-radius: 6px; padding: 5px 9px; margin: 2px 2px 0 0;
                  font-family: inherit; font-size: .78rem; cursor: pointer; }
    .hud button.actif { border-color: #58a6ff; color: #58a6ff; }
    .hud input[type="color"] { width: 100%; height: 26px; margin-top: 3px; padding: 0;
                  background: #0e1116; border: 1px solid #2a313c; border-radius: 6px;
                  cursor: pointer; }
    .hud-forme { max-width: 380px; max-height: calc(100vh - 32px); overflow-y: auto; }
    .hud .famille { color: #58a6ff; font-size: .72rem; text-transform: uppercase;
                    letter-spacing: .07em; margin-bottom: 8px; }
    .hud .appel { margin-top: 12px; }
    .hud .appel code { color: #7ee787; font-size: .78rem; }
    .hud details { margin: 12px 0; border-top: 1px solid #2a313c; padding-top: 10px; }
    .hud summary { cursor: pointer; font-size: .8rem; font-weight: 600; color: #e6edf3; }
    .hud details ul { margin: 10px 0 0; padding-left: 18px; color: #8b949e; }
    .hud details li { margin-bottom: 8px; }`;

/** Indente un bloc de texte, pour que le HTML produit reste lisible. */
const indenter = (texte, marge) =>
  texte.split('\n').map((l) => (l.trim() ? marge + l : l)).join('\n');

/**
 * @param {string} code            le module ES de la démo
 * @param {object} [options]
 * @param {string} [options.titre] titre de la page
 * @param {string} [options.corps] HTML à placer dans le <body> (le HUD)
 * @returns {string} un document HTML complet
 */
export function genererPage(code, { titre = 'Démo Three.js', corps = '' } = {}) {
  return `<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${titre}</title>

  <style>${CSS_HUD}
  </style>

  <!-- L'import map indique au navigateur où trouver le module "three".
       Aucune installation, aucun bundler : les modules ES sont chargés nativement.
       Elle doit apparaître AVANT le premier script de type "module". -->
  <script type="importmap">
  ${IMPORT_MAP}
  </script>
</head>
<body>
${corps ? indenter(corps, '  ') + '\n' : ''}
  <script type="module">
${indenter(code, '    ')}
  <\/script>
</body>
</html>
`;
}

/** Un nom de fichier utilisable, dérivé d'un titre. */
export const nomDeFichier = (titre) =>
  (titre || 'demo')
    .toLowerCase()
    .normalize('NFD').replace(/\p{Diacritic}/gu, '') // « éclairé » → « eclaire »
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 60) + '.html';

/** Déclenche le téléchargement d'un fichier texte, sans passer par un serveur. */
export function telecharger(nom, contenu) {
  const url = URL.createObjectURL(new Blob([contenu], { type: 'text/html' }));
  const lien = Object.assign(document.createElement('a'), { href: url, download: nom });
  lien.click();
  URL.revokeObjectURL(url);
}
