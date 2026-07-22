<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Draw - Editeur de dessin</title>
<!-- Feuille de style principale -->
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- ============================================
     Toolbar gauche — outils de dessin
     ============================================ -->
<div id="toolbar" role="toolbar" aria-label="Outils de dessin">
  <button id="btnNew" class="toolbar-action new" title="Nouveau projet" aria-label="Nouveau projet"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg></button>
  <button id="btnResize" class="toolbar-action resize" title="Redimensionner" aria-label="Redimensionner le canvas"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg></button>
  <div class="toolbar-sep" role="separator"></div>
  <button data-tool="select" title="Selection (V)" aria-label="Selection"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3l7.07 16.97 2.51-7.39 7.39-2.51L3 3z"/></svg></button>
  <button data-tool="pencil" title="Crayon (P)" aria-label="Crayon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/></svg></button>
  <div class="toolbar-sep" role="separator"></div>
  <button data-tool="line" title="Ligne (L)" aria-label="Ligne"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="19" x2="19" y2="5"/></svg></button>
  <button data-tool="rect" title="Rectangle (R)" aria-label="Rectangle"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg></button>
  <button data-tool="circle" class="active" title="Cercle (C)" aria-label="Cercle"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg></button>
  <button data-tool="ellipse" title="Ellipse (E)" aria-label="Ellipse"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="12" rx="10" ry="6"/></svg></button>
  <button data-tool="triangle" title="Triangle (T)" aria-label="Triangle"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12,3 22,21 2,21"/></svg></button>
  <button data-tool="arrow" title="Fleche (A)" aria-label="Fleche"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="19" x2="19" y2="5"/><polyline points="12,5 19,5 19,12"/></svg></button>
  <div class="toolbar-sep" role="separator"></div>
  <button data-tool="polygon" title="Polygone (H)" aria-label="Polygone"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12,2 22,9 19,21 5,21 2,9"/></svg></button>
  <button data-tool="star" title="Etoile (J)" aria-label="Etoile"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12,2 15,9 22,9 17,14 19,22 12,18 5,22 7,14 2,9 9,9"/></svg></button>
  <button data-tool="doublearrow" title="Double Fleche (D)" aria-label="Double fleche"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="8,8 5,12 8,16"/><polyline points="16,8 19,12 16,16"/></svg></button>
  <button data-tool="measure" title="Mesure (M)" aria-label="Mesure"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="21" x2="21" y2="3"/><line x1="3" y1="21" x2="3" y2="16"/><line x1="3" y1="21" x2="8" y2="21"/><line x1="21" y1="3" x2="21" y2="8"/><line x1="21" y1="3" x2="16" y2="3"/></svg></button>
  <div class="toolbar-sep" role="separator"></div>
  <button data-tool="quadratic" title="Courbe (Q)" aria-label="Courbe quadratique"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 20 Q12 2 21 20"/><circle cx="12" cy="4" r="2" stroke-dasharray="2,2"/></svg></button>
  <button data-tool="bezier" title="Bezier (B)" aria-label="Courbe de Bezier"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 17c4-8 14-8 18 0"/><circle cx="3" cy="17" r="2"/><circle cx="21" cy="17" r="2"/><circle cx="7" cy="5" r="2"/><circle cx="17" cy="5" r="2"/><line x1="3" y1="17" x2="7" y2="5" stroke-dasharray="2,2"/><line x1="21" y1="17" x2="17" y2="5" stroke-dasharray="2,2"/></svg></button>
  <button data-tool="text" title="Texte (X)" aria-label="Texte"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4,7 4,4 20,4 20,7"/><line x1="12" y1="4" x2="12" y2="20"/><line x1="8" y1="20" x2="16" y2="20"/></svg></button>
  <div class="toolbar-sep" role="separator"></div>
  <button data-tool="eraser" title="Gomme (G)" aria-label="Gomme"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 20H7L3 16l9-9 8 8-4 4"/><line x1="14" y1="4" x2="22" y2="12"/></svg></button>
  <div class="toolbar-sep" role="separator"></div>
  <button data-tool="image" title="Image (I)" aria-label="Image"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></button>
  <div class="toolbar-sep" role="separator"></div>
  <button data-tool="picker" title="Pipette (K)" aria-label="Pipette"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.71 5.63l-2.34-2.34a1 1 0 00-1.41 0l-3.54 3.54-1.41-1.41-1.41 1.41 1.41 1.41-6.36 6.36a2 2 0 00-.59 1.42V18h1.98c.53 0 1.04-.21 1.41-.59l6.36-6.36 1.41 1.41 1.41-1.41-1.41-1.41 3.54-3.54a1 1 0 000-1.42z"/></svg></button>
</div>
<!-- ============================================
     Barre flottante texte (apparait pres du texte selectionne)
     ============================================ -->
<div id="text-toolbar" class="text-toolbar hidden">
  <select id="ftFontFamily">
    <option value="Segoe UI">Segoe UI</option>
    <option value="Arial">Arial</option>
    <option value="Georgia">Georgia</option>
    <option value="Courier New">Courier New</option>
    <option value="Times New Roman">Times New Roman</option>
    <option value="Verdana">Verdana</option>
    <option value="Impact">Impact</option>
    <option value="Comic Sans MS">Comic Sans</option>
  </select>
  <input type="number" id="ftFontSize" min="6" max="200" value="16" title="Taille">
  <button class="ft-btn" id="ftBold" title="Gras"><b>B</b></button>
  <button class="ft-btn" id="ftItalic" title="Italique"><i>I</i></button>
  <input type="color" id="ftColor" value="#00d4ff" title="Couleur">
</div>

<!-- Input cache pour l'import d'images -->
<input type="file" id="imageInput" accept="image/*" style="display:none">

<!-- ============================================
     Zone centrale — canvas de dessin
     ============================================ -->
<div id="canvas-area">
  <div id="top-bar">
    <span id="projectTitle" title="Double-cliquez pour renommer">Sans titre</span>
    <div class="separator"></div>
    <input type="color" id="strokeColor" value="#00d4ff" title="Contour">
    <input type="color" id="fillColor" value="#ffffff" title="Remplissage">
    <label><input type="checkbox" id="useFill"> Actif</label>
    <div class="separator"></div>
    <label>Fond</label>
    <input type="color" id="bgColor" value="#ffffff" aria-label="Couleur de fond">
    <div class="separator"></div>
    <label>Taille</label>
    <input type="range" id="strokeWidth" min="1" max="20" value="2">
    <span class="val" id="strokeWidthVal">2</span>
    <div class="separator"></div>
    <label>Opacite</label>
    <input type="range" id="opacity" min="5" max="100" value="100" aria-label="Opacite">
    <span class="val" id="opacityVal">100%</span>
    <div class="separator"></div>
    <label>Trait</label>
    <select id="dashStyle" aria-label="Style de trait">
      <option value="solid">Continu</option>
      <option value="dashed">Tirets</option>
      <option value="dotted">Pointilles</option>
    </select>
    <div class="separator"></div>
    <button class="top-btn" id="btnUndo" title="Ctrl+Z" aria-label="Annuler">&#8630; Annuler</button>
    <button class="top-btn" id="btnRedo" title="Ctrl+Y" aria-label="Refaire">&#8631; Refaire</button>
    <div class="separator"></div>
    <button class="top-btn" id="btnGrid" title="Grille" aria-label="Afficher la grille">&#9638; Grille</button>
    <button class="top-btn" id="btnSnapGrid" title="Snap grille" aria-label="Accrochage grille">&#9783;</button>
    <div class="separator"></div>
    <button class="top-btn success" id="btnExport" aria-label="Exporter PNG">PNG</button>
    <button class="top-btn success" id="btnExportSVG" aria-label="Exporter SVG">SVG</button>
    <button class="top-btn success" id="btnExportPDF" aria-label="Exporter PDF">PDF</button>
    <button class="top-btn" id="btnSave" aria-label="Sauvegarder">Sauvegarder</button>
    <button class="top-btn" id="btnLoad" aria-label="Charger">Charger</button>
    <button class="top-btn danger" id="btnClear" aria-label="Effacer tout">Effacer tout</button>
    <button class="top-btn" id="btnTheme" title="Changer theme" aria-label="Theme">&#9788;</button>
    <button class="top-btn" id="btnPresentation" title="Mode presentation" aria-label="Presentation">&#9654;</button>
    <button class="top-btn" id="btnShortcuts" title="?" aria-label="Raccourcis clavier">?</button>
  </div>
  <div id="recent-colors"></div>
  <div id="canvas-wrapper">
    <div id="ruler-corner"></div>
    <canvas id="rulerH" class="ruler" height="20"></canvas>
    <canvas id="rulerV" class="ruler" width="20"></canvas>
    <canvas id="myCanvas" width="900" height="600"></canvas>
    <div id="coords">X:0 Y:0</div>
    <div id="status">Pret</div>
  </div>
</div>

<!-- ============================================
     Sidebar droite — panneau des calques
     ============================================ -->
<div id="sidebar">
  <div class="sidebar-header">
    <h3>Calques (<span id="layerCount">0</span>)</h3>
    <button class="btn-new-group" id="btnNewGroup" title="Nouveau groupe" aria-label="Nouveau groupe">+ Groupe</button>
  </div>
  <div id="align-bar" role="toolbar" aria-label="Alignement">
    <button data-align="left" title="Aligner a gauche" aria-label="Aligner a gauche">&#9664;</button>
    <button data-align="center-h" title="Centrer horizontalement" aria-label="Centrer horizontalement">&#9679;</button>
    <button data-align="right" title="Aligner a droite" aria-label="Aligner a droite">&#9654;</button>
    <button data-align="top" title="Aligner en haut" aria-label="Aligner en haut">&#9650;</button>
    <button data-align="center-v" title="Centrer verticalement" aria-label="Centrer verticalement">&#9679;</button>
    <button data-align="bottom" title="Aligner en bas" aria-label="Aligner en bas">&#9660;</button>
  </div>
  <div id="shape-props" class="shape-props-panel">
    <label>Arrondi <input type="range" id="borderRadius" min="0" max="50" value="0"><span class="val" id="borderRadiusVal">0</span></label>
    <label>Ombre <input type="checkbox" id="useShadow"><input type="color" id="shadowColor" value="#000000"></label>
    <label>Cotes <input type="number" id="polygonSides" min="3" max="20" value="6" style="width:50px"></label>
    <label>Degrade
      <select id="gradientType">
        <option value="none">Aucun</option>
        <option value="linear">Lineaire</option>
        <option value="radial">Radial</option>
      </select>
      <input type="color" id="gradientColor1" value="#00d4ff">
      <input type="color" id="gradientColor2" value="#ff6b35">
    </label>
    <label>Police
      <select id="fontFamily">
        <option value="Segoe UI">Segoe UI</option>
        <option value="Arial">Arial</option>
        <option value="Georgia">Georgia</option>
        <option value="Courier New">Courier New</option>
        <option value="Times New Roman">Times New Roman</option>
        <option value="Verdana">Verdana</option>
        <option value="Impact">Impact</option>
        <option value="Comic Sans MS">Comic Sans</option>
      </select>
      <button class="prop-btn" id="btnBold" title="Gras">B</button>
      <button class="prop-btn" id="btnItalic" title="Italique"><em>I</em></button>
    </label>
  </div>
  <div id="layerList"></div>
</div>

<!-- ============================================
     Overlay — dimensions du canvas
     ============================================ -->
<div id="resize-overlay">
  <div id="resize-box">
    <h3 id="resize-title">Nouveau projet</h3>
    <div class="resize-field resize-name-field" id="projectNameField">
      <label for="projectName">Nom du projet</label>
      <input type="text" id="projectName" placeholder="Mon dessin" maxlength="60">
    </div>
    <div class="resize-fields">
      <div class="resize-field">
        <label for="resizeW">Largeur (px)</label>
        <input type="number" id="resizeW" min="100" max="4000" value="900">
      </div>
      <div class="resize-field">
        <label for="resizeH">Hauteur (px)</label>
        <input type="number" id="resizeH" min="100" max="4000" value="600">
      </div>
    </div>
    <div class="resize-presets">
      <button class="preset-btn" data-w="800" data-h="600">800x600</button>
      <button class="preset-btn" data-w="1024" data-h="768">1024x768</button>
      <button class="preset-btn" data-w="1280" data-h="720">HD 720</button>
      <button class="preset-btn" data-w="1920" data-h="1080">Full HD</button>
      <button class="preset-btn" data-w="1080" data-h="1080">Carre</button>
      <button class="preset-btn" data-w="1080" data-h="1350">Portrait</button>
    </div>
    <div id="resize-footer">
      <button class="top-btn success" id="resize-confirm">Confirmer</button>
      <button class="top-btn close-btn" id="resize-cancel">Annuler</button>
    </div>
  </div>
</div>

<!-- ============================================
     Overlay — sauvegardes (style jeu video)
     ============================================ -->
<div id="saves-overlay">
  <div id="saves-box">
    <h3 id="saves-title">Sauvegarder</h3>
    <div id="saves-list"></div>
    <div id="saves-footer">
      <button class="top-btn success" id="saves-new">+ Nouvelle sauvegarde</button>
      <button class="top-btn close-btn" id="saves-close">Fermer</button>
    </div>
  </div>
</div>

<!-- ============================================
     Overlay — raccourcis clavier
     ============================================ -->
<div id="shortcuts-overlay">
  <div id="shortcuts-box">
    <h3>Raccourcis clavier</h3>
    <table>
      <tr><td>V</td><td>Selection</td></tr>
      <tr><td>P</td><td>Crayon</td></tr>
      <tr><td>L</td><td>Ligne</td></tr>
      <tr><td>R</td><td>Rectangle</td></tr>
      <tr><td>C</td><td>Cercle</td></tr>
      <tr><td>E</td><td>Ellipse</td></tr>
      <tr><td>T</td><td>Triangle</td></tr>
      <tr><td>A</td><td>Fleche</td></tr>
      <tr><td>X</td><td>Texte</td></tr>
      <tr><td>G</td><td>Gomme</td></tr>
      <tr><td>I</td><td>Image</td></tr>
      <tr><td>K</td><td>Pipette</td></tr>
      <tr><td>Ctrl+Z</td><td>Annuler</td></tr>
      <tr><td>Ctrl+Y</td><td>Refaire</td></tr>
      <tr><td>Ctrl+C</td><td>Copier la forme selectionnee</td></tr>
      <tr><td>Ctrl+V</td><td>Coller</td></tr>
      <tr><td>B</td><td>Courbe de Bezier</td></tr>
      <tr><td>Suppr</td><td>Supprimer la forme selectionnee</td></tr>
      <tr><td>Ctrl+S</td><td>Sauvegarder</td></tr>
      <tr><td>Ctrl+D</td><td>Dupliquer la forme</td></tr>
      <tr><td>Ctrl+E</td><td>Exporter PNG</td></tr>
      <tr><td>Ctrl+Shift+E</td><td>Exporter SVG</td></tr>
      <tr><td>Ctrl+0</td><td>Reset zoom</td></tr>
      <tr><td>+/-</td><td>Taille du trait</td></tr>
      <tr><td>Molette</td><td>Zoom</td></tr>
      <tr><td>Espace+Drag</td><td>Deplacer la vue (pan)</td></tr>
      <tr><td>Esc</td><td>Annuler l'action en cours</td></tr>
      <tr><td>Shift+Drag</td><td>Deformer l'element selectionne</td></tr>
      <tr><td>Alt+Drag</td><td>Rotation de l'element selectionne</td></tr>
      <tr><td>Dbl-clic</td><td>Editer texte / Renommer calque</td></tr>
    </table>
    <button class="top-btn close-btn" onclick="document.getElementById('shortcuts-overlay').classList.remove('show')">Fermer</button>
  </div>
</div>

<!-- ============================================
     Scripts — charges dans l'ordre des dependances
     ============================================ -->

<!-- Classe de base -->
<script src="class/Shape.js"></script>

<!-- Formes (LineShape avant Arrow et Pencil qui utilisent _distToSegment) -->
<script src="class/Circle.js"></script>
<script src="class/Rect.js"></script>
<script src="class/LineShape.js"></script>
<script src="class/EllipseShape.js"></script>
<script src="class/Triangle.js"></script>
<script src="class/Arrow.js"></script>
<script src="class/Pencil.js"></script>
<script src="class/TextShape.js"></script>
<script src="class/ImageShape.js"></script>
<script src="class/BezierShape.js"></script>
<script src="class/PolygonShape.js"></script>
<script src="class/DoubleArrow.js"></script>
<script src="class/QuadraticShape.js"></script>

<!-- Factory de deserialisation (a besoin de toutes les formes) -->
<script src="class/ShapeFactory.js"></script>

<!-- Infrastructure -->
<script src="class/History.js"></script>
<script src="class/LayerManager.js"></script>

<!-- Application principale -->
<script src="class/CanvasApp.js"></script>

<!-- Initialisation -->
<script>
  // Creation du gestionnaire de calques
  const layerManager = new LayerManager(
    document.getElementById('layerList'),
    document.getElementById('layerCount')
  );

  // Creation de l'application principale
  const app = new CanvasApp(
    document.getElementById('myCanvas'),
    layerManager
  );

  // Liaison des boutons d'outils de la toolbar
  document.querySelectorAll('#toolbar button[data-tool]').forEach(btn => {
    btn.addEventListener('click', () => app.setTool(btn.dataset.tool));
  });

  // Bouton nouveau groupe
  document.getElementById('btnNewGroup').addEventListener('click', () => {
    layerManager.addGroup();
    app.saveState();
  });

  // Double-clic sur le nom du projet pour renommer
  document.getElementById('projectTitle').addEventListener('dblclick', () => {
    const titleElem = document.getElementById('projectTitle');
    const input = document.createElement('input');
    input.type = 'text';
    input.value = app.projectName;
    input.id = 'projectTitleInput';
    titleElem.replaceWith(input);
    input.focus();
    input.select();
    let done = false;
    const finish = () => {
      if (done) return; done = true;
      const val = input.value.trim() || app.projectName;
      const span = document.createElement('span');
      span.id = 'projectTitle';
      span.title = 'Double-cliquez pour renommer';
      span.textContent = val;
      input.replaceWith(span);
      app.setProjectName(val);
      // Re-attacher le double-clic
      span.addEventListener('dblclick', arguments.callee);
    };
    input.addEventListener('blur', finish);
    input.addEventListener('keydown', ev => {
      if (ev.key === 'Enter') input.blur();
      if (ev.key === 'Escape') { input.value = app.projectName; input.blur(); }
    });
  });

  // Overlay sauvegardes
  document.getElementById('saves-close').addEventListener('click', () => app.closeSaveOverlay());
  document.getElementById('saves-overlay').addEventListener('click', e => {
    if (e.target === e.currentTarget) app.closeSaveOverlay();
  });
  document.getElementById('saves-new').addEventListener('click', () => {
    const name = prompt('Nom de la sauvegarde :', 'Sauvegarde ' + (app._getSaves().length + 1));
    if (name !== null) {
      app.saveToSlot(-1, name.trim());
      app._renderSaveSlots();
    }
  });

  // Migration : ancienne sauvegarde unique vers le nouveau systeme
  const oldSave = localStorage.getItem('draw_save');
  if (oldSave) {
    try {
      const saves = app._getSaves();
      if (saves.length === 0) {
        const data = JSON.parse(oldSave);
        saves.push({
          name: 'Sauvegarde importee',
          date: new Date().toISOString(),
          shapeCount: (data.layers || data).length,
          thumbnail: '',
          data: data
        });
        app._setSaves(saves);
      }
      // Charger la derniere sauvegarde automatiquement
      app.loadFromSlot(saves.length - 1);
    } catch (e) { console.error('Migration failed:', e); }
  } else {
    const saves = app._getSaves();
    if (saves.length > 0) {
      app.loadFromSlot(saves.length - 1);
    } else {
      // Premier lancement : demander le nom du projet
      app.openResizeOverlay('new');
    }
  }
</script>

</body>
</html>
