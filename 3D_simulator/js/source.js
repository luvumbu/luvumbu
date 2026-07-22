// Lecture du code d'une page de leçon.
//
// Le code affiché (panneau) et le code éditable (bac à sable) proviennent tous deux
// du fichier réel : rien n'est recopié à la main, donc rien ne peut diverger.

/** Retire l'indentation commune, sinon le code extrait d'un <script> apparaît décalé. */
export function desindenter(code) {
  const lignes = code.replace(/\t/g, '  ').split('\n');
  const utiles = lignes.filter((l) => l.trim());
  if (!utiles.length) return '';

  const marge = Math.min(...utiles.map((l) => l.match(/^ */)[0].length));
  return lignes.map((l) => l.slice(marge)).join('\n').trim();
}

/**
 * Extrait le contenu du <script type="module"> inline d'une page.
 * Les scripts avec un attribut `src` (comme code-panel.js) ont un `>` non collé
 * à `"module"` : la regex ne peut donc pas les confondre avec celui de la leçon.
 */
export function extraireScriptModule(html) {
  const m = html.match(/<script type="module">([\s\S]*?)<\/script>/);
  return m ? desindenter(m[1]) : null;
}

/** Récupère le code d'une page par son URL. */
export async function chargerCodeDe(url) {
  const reponse = await fetch(url, { cache: 'no-store' });
  if (!reponse.ok) throw new Error(`${reponse.status} sur ${url}`);
  return extraireScriptModule(await reponse.text());
}
