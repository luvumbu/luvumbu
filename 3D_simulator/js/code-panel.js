// Panneau de code éditable, présent sur toutes les pages de leçon.
//
// Le code affiché est lu dans le fichier lui-même (aucune recopie, donc aucune divergence).
// Il est modifiable : « Exécuter » relance la version modifiée dans une iframe posée
// par-dessus la démo d'origine. La page de la leçon, elle, n'est jamais cassée —
// « Rétablir » suffit à revenir à l'état initial.

import { extraireScriptModule } from './source.js';
import { creerEditeur } from './editeur-widget.js';
import { executer, ecouterMessages } from './bac-a-sable.js';
import { genererPage, nomDeFichier, telecharger } from './page-autonome.js';

const html = await (await fetch(location.href, { cache: 'no-store' })).text();
const codeOrigine = extraireScriptModule(html);
if (codeOrigine) construirePanneau(codeOrigine);

function construirePanneau(codeOrigine) {
  const panneau = document.createElement('aside');
  panneau.className = 'code-panel';
  panneau.innerHTML = `
    <header>
      <span>Code de la page — modifiable</span>
      <label class="auto" title="Relancer automatiquement après chaque frappe">
        <input type="checkbox" checked> auto
      </label>
      <button class="lancer" type="button">Exécuter</button>
      <button class="retablir secondaire" type="button" disabled>Rétablir</button>
      <button class="copier secondaire" type="button"
              title="Copie une page HTML complète, prête à coller dans un fichier">Copier</button>
      <button class="telecharger secondaire" type="button" title="Enregistrer en .html">↓</button>
      <button class="fermer secondaire" type="button" aria-label="Fermer">×</button>
    </header>
    <div class="zone-edition"></div>
    <ul class="console-mini"></ul>`;

  const bascule = document.createElement('button');
  bascule.className = 'code-toggle';
  bascule.type = 'button';
  bascule.textContent = `</> Éditer le code (${codeOrigine.split('\n').length} lignes)`;

  // L'iframe d'aperçu se glisse derrière le HUD et le panneau, à la place du <canvas> d'origine.
  const scene = document.createElement('div');
  scene.className = 'apercu-modifie';

  document.body.append(scene, bascule, panneau);

  const boutons = (sel) => panneau.querySelector(sel);
  const journal = panneau.querySelector('.console-mini');
  const auto = panneau.querySelector('.auto input');
  const retablir = boutons('.retablir');

  const editeur = creerEditeur(panneau.querySelector('.zone-edition'), {
    code: codeOrigine,
    onExecuter: lancer,
    onSaisie: () => {
      if (!auto.checked) return;
      clearTimeout(minuteur); // sans ce délai, chaque frappe recréerait l'iframe
      minuteur = setTimeout(lancer, 800);
    },
  });

  let minuteur;
  let modifie = false;

  // Le HUD de la leçon est recopié dans l'iframe : sans lui, un `getElementById('rough')`
  // ne trouverait rien et les curseurs de la leçon seraient inertes.
  const hud = document.querySelector('.hud');
  const options = {
    corps: hud ? hud.outerHTML : '',
    css: new URL('../css/style.css', location.href).href,
  };

  // La démo d'origine et son HUD sont masqués, pas supprimés :
  // « Rétablir » les fait réapparaître sans recharger la page.
  const originaux = () => document.querySelectorAll('body > canvas, body > .hud');

  function lancer() {
    journal.replaceChildren();

    if (!modifie) {
      originaux().forEach((el) => (el.style.display = 'none'));
      modifie = true;
      retablir.disabled = false;
    }

    executer(scene, editeur.valeur(), options);
  }

  retablir.addEventListener('click', () => {
    clearTimeout(minuteur);
    scene.replaceChildren(); // détruit l'iframe : boucle de rendu et contexte WebGL libérés
    journal.replaceChildren();
    originaux().forEach((el) => (el.style.display = ''));
    editeur.definir(codeOrigine);
    modifie = false;
    retablir.disabled = true;
  });

  ecouterMessages((type, texte) => {
    const li = document.createElement('li');
    li.className = type;
    li.textContent = texte;
    journal.append(li);
    journal.scrollTop = journal.scrollHeight;
  });

  boutons('.lancer').addEventListener('click', lancer);

  // Copier ou télécharger produit une PAGE COMPLÈTE, pas seulement le JavaScript :
  // sans l'import map qui l'accompagne, `import ... from 'three'` échouerait ailleurs.
  const titre = document.title;

  function pageAutonome() {
    const copie = hud?.cloneNode(true);

    // Les liens « ← Sommaire » ne mènent nulle part hors de ce projet. On les retire,
    // puis on nettoie les paragraphes qui ne contenaient qu'eux : sans ça, il reste
    // les séparateurs (« · ») entre des liens désormais absents.
    copie?.querySelectorAll('a').forEach((a) => a.remove());
    copie?.querySelectorAll('p').forEach((p) => {
      if (!p.textContent.replace(/[·|\s]/g, '')) p.remove();
    });

    return genererPage(editeur.valeur(), { titre, corps: copie?.outerHTML ?? '' });
  }

  const copier = boutons('.copier');
  copier.addEventListener('click', async () => {
    await navigator.clipboard.writeText(pageAutonome());
    copier.textContent = 'Page HTML copiée !';
    setTimeout(() => (copier.textContent = 'Copier'), 1600);
  });

  boutons('.telecharger').addEventListener('click', () => {
    telecharger(nomDeFichier(titre), pageAutonome());
  });

  const ouvrir = (etat) => {
    panneau.classList.toggle('ouvert', etat);
    bascule.classList.toggle('cache', etat);
  };
  bascule.addEventListener('click', () => ouvrir(true));
  boutons('.fermer').addEventListener('click', () => ouvrir(false));
  addEventListener('keydown', (e) => e.key === 'Escape' && ouvrir(false));
}
