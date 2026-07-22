/**
 * Draw - CanvasApp
 *
 * Controleur principal de l'application de dessin.
 * Gere : les outils, les evenements souris/clavier,
 * les poignees de redimensionnement/rotation,
 * la barre d'outils superieure, l'export PNG,
 * la sauvegarde/chargement localStorage, et l'outil pipette.
 *
 * Dependances : toutes les classes Shape, LayerManager, History
 */
class CanvasApp {

  /**
   * @param {HTMLCanvasElement} canvas - Le canvas de dessin
   * @param {LayerManager} layerManager - Le gestionnaire de calques
   */
  constructor(canvas, layerManager) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');
    this.lm = layerManager;         // Reference au LayerManager
    this.history = new History();     // Pile undo/redo
    this.currentTool = 'circle';     // Outil actif par defaut
    this.isDrawing = false;          // En train de dessiner ?
    this.startX = 0; this.startY = 0; // Point de depart du dessin

    // Etat du deplacement
    this.movingIndex = -1;
    this.moveOffsetX = 0; this.moveOffsetY = 0;

    // Etat du redimensionnement, rotation
    this.resizing = null;   // {handleId, type, origBounds, origRatio, startX, startY}
    this.rotating = null;   // {cx, cy, startAngle, origRotation}
    this.skewing = null;    // (plus utilise directement, conserve pour Escape)

    // Copier/coller
    this.copiedShape = null;
    this.ghostShape = null;  // Forme fantome suivant la souris

    // Outil crayon
    this.pencilPoints = [];

    // Constantes de l'interface
    this.HANDLE_SIZE = 8;        // Taille des poignees (px)
    this.ROT_DIST = 30;          // Distance poignee rotation (px)
    this.SNAP_THRESHOLD = 10;    // Seuil d'accrochage (px)
    this.HIT_MARGIN = 4;         // Marge de detection (px)

    // Grille
    this.showGrid = false;
    this.gridSize = 20;

    // Zoom / Pan
    this.zoom = 1;
    this.panX = 0;
    this.panY = 0;
    this._isPanning = false;
    this._panStartX = 0;
    this._panStartY = 0;

    // Couleur de fond
    this.bgColor = '#ffffff';

    // Nom du projet
    this.projectName = 'Sans titre';

    // Auto-save
    this._autoSaveInterval = null;
    this._lastAutoSave = 0;

    // Multi-selection
    this.multiSelection = [];  // Indices des formes selectionnees

    // Snap to grid
    this.snapToGrid = false;

    // Couleurs recentes
    this.recentColors = [];

    // Theme
    this.lightTheme = false;

    // Outil mesure
    this._measureStart = null;

    // Reperes personnalises
    this.guides = []; // [{axis:'h'|'v', pos:number}]

    // Quadratic points
    this._quadPoints = null;

    // References DOM
    this.coordsElem = document.getElementById('coords');
    this.statusElem = document.getElementById('status');

    // Initialisation
    this.saveState();
    this.bindEvents();
    this.draw();
    this._startAutoSave();
  }

  /* ===========================================
     Style — lecture/application depuis la top-bar
     =========================================== */

  /** Lit les valeurs actuelles de la barre d'outils */
  getStyle() {
    return {
      strokeColor: document.getElementById('strokeColor').value,
      fillColor: document.getElementById('fillColor').value,
      useFill: document.getElementById('useFill').checked,
      lineWidth: parseInt(document.getElementById('strokeWidth').value),
      opacity: parseInt(document.getElementById('opacity').value) / 100,
      dashStyle: document.getElementById('dashStyle').value
    };
  }

  /** Applique le style courant de la top-bar sur une forme */
  applyStyleToShape(shape) {
    const s = this.getStyle();
    shape.strokeColor = s.strokeColor;
    shape.fillColor = s.fillColor;
    shape.useFill = s.useFill;
    shape.lineWidth = s.lineWidth;
    shape.opacity = s.opacity;
    shape.dashStyle = s.dashStyle;
  }

  /** Charge les proprietes d'une forme dans la top-bar */
  loadStyleFromShape(shape) {
    if (!shape) return;
    document.getElementById('strokeColor').value = shape.strokeColor;

    document.getElementById('fillColor').value = shape.fillColor;

    document.getElementById('useFill').checked = shape.useFill;
    document.getElementById('strokeWidth').value = shape.lineWidth;
    document.getElementById('strokeWidthVal').textContent = shape.lineWidth;
    const opVal = Math.round(shape.opacity * 100);
    document.getElementById('opacity').value = opVal;
    document.getElementById('opacityVal').textContent = opVal + '%';
    document.getElementById('dashStyle').value = shape.dashStyle || 'solid';
    document.getElementById('borderRadius').value = shape.borderRadius || 0;
    document.getElementById('borderRadiusVal').textContent = shape.borderRadius || 0;
    document.getElementById('useShadow').checked = !!shape.shadowColor;
    if (shape.shadowColor) document.getElementById('shadowColor').value = shape.shadowColor;
    if (shape.sides !== undefined) document.getElementById('polygonSides').value = shape.sides;
    document.getElementById('gradientType').value = shape.gradientType || 'none';
    document.getElementById('gradientColor1').value = shape.gradientColor1 || '#00d4ff';
    document.getElementById('gradientColor2').value = shape.gradientColor2 || '#ff6b35';
    document.getElementById('fontFamily').value = shape.fontFamily || 'Segoe UI';
    document.getElementById('btnBold').classList.toggle('active', !!shape.fontBold);
    document.getElementById('btnItalic').classList.toggle('active', !!shape.fontItalic);

    // Barre flottante texte
    this._updateTextToolbar(shape);
  }

  /** Affiche/masque la barre flottante texte pres de la forme */
  _updateTextToolbar(shape) {
    const tb = document.getElementById('text-toolbar');
    if (!shape || shape.type !== 'text') {
      tb.classList.add('hidden');
      return;
    }
    // Remplir les valeurs
    document.getElementById('ftFontFamily').value = shape.fontFamily || 'Segoe UI';
    document.getElementById('ftFontSize').value = shape.fontSize;
    document.getElementById('ftBold').classList.toggle('active', !!shape.fontBold);
    document.getElementById('ftItalic').classList.toggle('active', !!shape.fontItalic);
    document.getElementById('ftColor').value = shape.useFill ? shape.fillColor : shape.strokeColor;

    // Positionner au-dessus de la forme
    const b = shape.getBounds();
    const canvasRect = this.canvas.getBoundingClientRect();
    const scaleX = canvasRect.width / this.canvas.width;
    const scaleY = canvasRect.height / this.canvas.height;
    const left = canvasRect.left + (b.x * this.zoom + this.panX) * scaleX;
    const top = canvasRect.top + (b.y * this.zoom + this.panY) * scaleY - 46;

    tb.style.left = Math.max(0, left) + 'px';
    tb.style.top = Math.max(0, top) + 'px';
    tb.classList.remove('hidden');
  }

  /** Met a jour la forme selectionnee avec les valeurs de la top-bar */
  updateSelectedShape() {
    const shape = this.lm.getSelected();
    if (!shape) return;
    this.applyStyleToShape(shape);
    this.lm.updateUI();
    this.draw();
  }

  /* ===========================================
     Outil actif
     =========================================== */

  /** Change l'outil actif. L'outil 'image' ouvre le selecteur de fichier puis passe en mode trace. */
  setTool(tool) {
    // L'outil image ouvre le file picker, puis on dessine un rectangle
    if (tool === 'image' && !this._pendingImageData) {
      document.getElementById('imageInput').click();
      return;
    }
    this.currentTool = tool;
    this.ghostShape = null;

    // Mettre a jour le bouton actif dans la toolbar
    document.querySelectorAll('#toolbar button').forEach(b => b.classList.remove('active'));
    const btn = document.querySelector(`#toolbar button[data-tool="${tool}"]`);
    if (btn) btn.classList.add('active');

    // Curseur adapte a l'outil
    this.canvas.style.cursor = tool === 'select' ? 'default' : tool === 'eraser' ? 'pointer' : 'crosshair';
    this.setStatus(this._toolName(tool));
  }

  /** Retourne le nom francais d'un outil */
  _toolName(t) {
    const n = {
      select: 'Selection', pencil: 'Crayon', line: 'Ligne',
      rect: 'Rectangle', circle: 'Cercle', ellipse: 'Ellipse',
      triangle: 'Triangle', arrow: 'Fleche', text: 'Texte',
      eraser: 'Gomme', image: 'Image', picker: 'Pipette', bezier: 'Bezier',
      polygon: 'Polygone', star: 'Etoile', doublearrow: 'Double fleche',
      measure: 'Mesure', quadratic: 'Courbe quadratique'
    };
    return n[t] || t;
  }

  /** Affiche un message dans la barre de statut */
  setStatus(msg) { this.statusElem.textContent = msg; }

  /* ===========================================
     Undo / Redo
     =========================================== */

  saveState() { this.history.push(this.lm.serialize()); }

  undo() {
    const s = this.history.undo();
    if (s) { this.lm.deserialize(s); this.draw(); this.setStatus('Annule'); }
  }

  redo() {
    const s = this.history.redo();
    if (s) { this.lm.deserialize(s); this.draw(); this.setStatus('Refait'); }
  }

  /* ===========================================
     Copier / Supprimer
     =========================================== */

  /** Copie la forme a l'index i et active le mode "coller" */
  copyShape(i) {
    if (i >= 0 && i < this.lm.layers.length) {
      this.copiedShape = this.lm.layers[i].clone();
      this.setStatus('Copie — cliquez pour coller');
      this.ghostShape = this.copiedShape;
    }
  }

  /** Supprime la forme a l'index i */
  deleteShape(i) {
    this.lm.remove(i);
    this.saveState();
    this.draw();
  }

  /* ===========================================
     Utilitaire position souris
     =========================================== */

  /** Applique le snap to grid si actif */
  _snapToGrid(val) {
    if (!this.snapToGrid) return val;
    return Math.round(val / this.gridSize) * this.gridSize;
  }

  /** Convertit les coordonnees de l'evenement en coordonnees canvas (avec zoom/pan) */
  getMousePos(e) {
    const rect = this.canvas.getBoundingClientRect();
    const sx = this.canvas.width / rect.width;
    const sy = this.canvas.height / rect.height;
    return {
      x: (e.clientX - rect.left) * sx / this.zoom - this.panX / this.zoom,
      y: (e.clientY - rect.top) * sy / this.zoom - this.panY / this.zoom
    };
  }

  /* ===========================================
     Poignees de selection (resize, rotate)
     =========================================== */

  /**
   * Retourne la liste des poignees pour la forme selectionnee.
   * 3 types :
   *   - proportional (carres blancs, coins) : redimensionnement proportionnel
   *   - free (losanges oranges, milieux bords) : etirement libre
   *   - rotate (cercle vert, au-dessus) : rotation
   */
  _getHandles(shape) {
    if (!shape) return [];
    const b = shape.getBounds();
    const s = this.HANDLE_SIZE;
    return [
      // Coins — redimensionnement proportionnel (carres blancs)
      { id: 'tl', type: 'proportional', x: b.x - s/2, y: b.y - s/2, cursor: 'nwse-resize' },
      { id: 'tr', type: 'proportional', x: b.x + b.w - s/2, y: b.y - s/2, cursor: 'nesw-resize' },
      { id: 'bl', type: 'proportional', x: b.x - s/2, y: b.y + b.h - s/2, cursor: 'nesw-resize' },
      { id: 'br', type: 'proportional', x: b.x + b.w - s/2, y: b.y + b.h - s/2, cursor: 'nwse-resize' },
      // Bords — etirement libre (losanges oranges)
      { id: 'tm', type: 'free', x: b.x + b.w/2 - s/2, y: b.y - s/2, cursor: 'ns-resize' },
      { id: 'bm', type: 'free', x: b.x + b.w/2 - s/2, y: b.y + b.h - s/2, cursor: 'ns-resize' },
      { id: 'ml', type: 'free', x: b.x - s/2, y: b.y + b.h/2 - s/2, cursor: 'ew-resize' },
      { id: 'mr', type: 'free', x: b.x + b.w - s/2, y: b.y + b.h/2 - s/2, cursor: 'ew-resize' },
      // Rotation (cercle vert)
      { id: 'rot', type: 'rotate', x: b.x + b.w/2 - s/2, y: b.y - this.ROT_DIST - s/2, cursor: 'grab' },
    ];
  }

  /** Teste si la souris est sur une poignee. Retourne la poignee ou null. */
  _hitHandle(x, y) {
    const shape = this.lm.getSelected();
    if (!shape) return null;
    const s = this.HANDLE_SIZE + 4; // Zone de detection elargie
    for (const h of this._getHandles(shape)) {
      const cx = h.x + this.HANDLE_SIZE/2, cy = h.y + this.HANDLE_SIZE/2;
      if (x >= cx - s/2 && x <= cx + s/2 && y >= cy - s/2 && y <= cy + s/2) return h;
    }
    return null;
  }

  /** Dessine les poignees autour de la forme selectionnee */
  _drawHandles() {
    const shape = this.lm.getSelected();
    if (!shape || this.currentTool !== 'select') return;
    const ctx = this.ctx;
    const s = this.HANDLE_SIZE;
    const b = shape.getBounds();

    // Rectangle englobant en pointilles
    ctx.strokeStyle = '#00d4ff';
    ctx.lineWidth = 1;
    ctx.setLineDash([4, 4]);
    ctx.strokeRect(b.x, b.y, b.w, b.h);
    ctx.setLineDash([]);

    // Tige vers la poignee de rotation
    ctx.strokeStyle = '#2ecc71';
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(b.x + b.w/2, b.y);
    ctx.lineTo(b.x + b.w/2, b.y - this.ROT_DIST);
    ctx.stroke();

    // Dessin de chaque poignee
    for (const h of this._getHandles(shape)) {
      const cx = h.x + s/2, cy = h.y + s/2;
      if (h.type === 'rotate') {
        // Cercle VERT = rotation
        ctx.fillStyle = '#2ecc71';
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(cx, cy, s/2 + 2, 0, Math.PI * 2);
        ctx.fill(); ctx.stroke();
      } else if (h.type === 'free') {
        // Losange ORANGE = etirement libre (deforme)
        ctx.fillStyle = '#e67e22';
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 2;
        const d = s/2 + 2;
        ctx.beginPath();
        ctx.moveTo(cx, cy - d); ctx.lineTo(cx + d, cy); ctx.lineTo(cx, cy + d); ctx.lineTo(cx - d, cy);
        ctx.closePath(); ctx.fill(); ctx.stroke();
      } else {
        // Carre BLANC = proportionnel
        ctx.fillStyle = '#ffffff';
        ctx.strokeStyle = '#00d4ff';
        ctx.lineWidth = 2;
        ctx.fillRect(h.x - 1, h.y - 1, s + 2, s + 2);
        ctx.strokeRect(h.x - 1, h.y - 1, s + 2, s + 2);
      }
    }
  }

  /* ===========================================
     Calcul du redimensionnement
     =========================================== */

  /** Redimensionnement libre (chaque bord bouge independamment) */
  _applyResize(handleId, dx, dy, origBounds) {
    let { x, y, w, h } = { ...origBounds };
    if (handleId.includes('l')) { x += dx; w -= dx; }
    if (handleId.endsWith('r')) { w += dx; }
    if (handleId.startsWith('t')) { y += dy; h -= dy; }
    if (handleId.startsWith('b')) { h += dy; }
    if (w < 4) w = 4;
    if (h < 4) h = 4;
    return { x, y, w, h };
  }

  /** Redimensionnement proportionnel (ratio conserve, coins uniquement) */
  _applyResizeProportional(handleId, dx, dy, origBounds, ratio) {
    let { x, y, w, h } = { ...origBounds };
    let delta;
    if (handleId === 'tl') {
      delta = (-dx - dy) / 2;
      w += delta; h = w / ratio;
      x = origBounds.x + origBounds.w - w;
      y = origBounds.y + origBounds.h - h;
    } else if (handleId === 'tr') {
      delta = (dx - dy) / 2;
      w += delta; h = w / ratio;
      y = origBounds.y + origBounds.h - h;
    } else if (handleId === 'bl') {
      delta = (-dx + dy) / 2;
      w += delta; h = w / ratio;
      x = origBounds.x + origBounds.w - w;
    } else if (handleId === 'br') {
      delta = (dx + dy) / 2;
      w += delta; h = w / ratio;
    }
    if (w < 4) { w = 4; h = w / ratio; }
    if (h < 4) { h = 4; w = h * ratio; }
    return { x, y, w, h };
  }

  /* ===========================================
     Outil pipette
     =========================================== */

  /** Lit la couleur du pixel sous la souris et l'applique aux controles */
  pickColor(x, y) {
    const pixel = this.ctx.getImageData(Math.round(x), Math.round(y), 1, 1).data;
    const hex = '#' + ((1 << 24) + (pixel[0] << 16) + (pixel[1] << 8) + pixel[2]).toString(16).slice(1);
    document.getElementById('strokeColor').value = hex;

    document.getElementById('fillColor').value = hex;

    this.setStatus('Couleur : ' + hex);
  }

  /* ===========================================
     Evenements (souris, clavier, controles)
     =========================================== */

  /** Attache tous les event listeners */
  bindEvents() {
    const c = this.canvas;

    /* --- MOUSEDOWN --- */
    c.addEventListener('mousedown', e => {
      // Pan : middle click ou espace+clic
      if (e.button === 1 || (e.button === 0 && e.getModifierState && e.getModifierState(' ')) || (e.button === 0 && this.canvas.style.cursor === 'grab')) {
        e.preventDefault();
        this._isPanning = true;
        this._panStartX = e.clientX - this.panX;
        this._panStartY = e.clientY - this.panY;
        this.canvas.style.cursor = 'grabbing';
        return;
      }
      const { x, y } = this.getMousePos(e);

      // Coller la forme fantome
      if (this.ghostShape && this.copiedShape) {
        this.ghostShape.move(x - this.ghostShape.getBounds().x - this.ghostShape.getBounds().w / 2,
                             y - this.ghostShape.getBounds().y - this.ghostShape.getBounds().h / 2);
        this.lm.add(this.ghostShape);
        this.ghostShape = null; this.copiedShape = null;
        this.saveState(); this.draw();
        return;
      }

      // Outil selection
      if (this.currentTool === 'select') {
        // Verifier les poignees en priorite
        const handle = this._hitHandle(x, y);
        if (handle) {
          const shape = this.lm.getSelected();
          const b = shape.getBounds();
          if (handle.type === 'rotate') {
            this.rotating = {
              cx: b.x + b.w/2, cy: b.y + b.h/2,
              startAngle: Math.atan2(y - (b.y + b.h/2), x - (b.x + b.w/2)),
              origRotation: shape.rotation
            };
            return;
          }
          this.resizing = {
            handleId: handle.id, type: handle.type,
            origBounds: { ...b }, origRatio: b.w / (b.h || 1),
            startX: x, startY: y
          };
          return;
        }
        // Sinon, selectionner ou deselectionner
        const i = this.lm.findAt(x, y);
        if (i >= 0) {
          this.lm.select(i);
          this.loadStyleFromShape(this.lm.layers[i]);
          this.movingIndex = i;
          this.moveOffsetX = x; this.moveOffsetY = y;
          this.draw();
        } else {
          this.lm.select(null); this._updateTextToolbar(null); this.draw();
        }
        return;
      }

      // Outil gomme
      if (this.currentTool === 'eraser') {
        const i = this.lm.findAt(x, y);
        if (i >= 0) { this.deleteShape(i); }
        return;
      }

      // Outil texte : prompt puis placement
      if (this.currentTool === 'text') {
        const text = prompt('Entrez votre texte (\\n pour saut de ligne) :');
        if (text) {
          const fontSize = parseInt(document.getElementById('strokeWidth').value) * 6 + 4;
          const shape = new TextShape(x, y, text.replace(/\\n/g, '\n'), fontSize);
          shape.fontFamily = document.getElementById('fontFamily').value;
          shape.fontBold = document.getElementById('btnBold').classList.contains('active');
          shape.fontItalic = document.getElementById('btnItalic').classList.contains('active');
          this.applyStyleToShape(shape);
          this.lm.add(shape);
          this.saveState(); this.draw();
        }
        return;
      }

      // Outil bezier : placement des 4 points
      if (this.currentTool === 'bezier') {
        if (!this._bezierPoints) this._bezierPoints = [];
        this._bezierPoints.push({ x, y });
        if (this._bezierPoints.length >= 4) {
          const pts = this._bezierPoints;
          const shape = new BezierShape(pts[0], pts[1], pts[2], pts[3]);
          this.applyStyleToShape(shape);
          this.lm.add(shape);
          this._bezierPoints = null;
          this.saveState(); this.draw();
        } else {
          this.setStatus('Bezier : point ' + this._bezierPoints.length + '/4');
          this.draw();
          // Dessiner les points deja places
          this._bezierPoints.forEach(p => {
            this.ctx.fillStyle = '#00d4ff';
            this.ctx.beginPath();
            this.ctx.arc(p.x, p.y, 4, 0, Math.PI * 2);
            this.ctx.fill();
          });
        }
        return;
      }

      // Outil quadratique : 3 points
      if (this.currentTool === 'quadratic') {
        if (!this._quadPoints) this._quadPoints = [];
        this._quadPoints.push({ x, y });
        if (this._quadPoints.length >= 3) {
          const pts = this._quadPoints;
          const shape = new QuadraticShape(pts[0], pts[1], pts[2]);
          this.applyStyleToShape(shape);
          this.lm.add(shape);
          this._quadPoints = null;
          this.saveState(); this.draw();
        } else {
          this.setStatus('Courbe : point ' + this._quadPoints.length + '/3');
          this.draw();
          this._quadPoints.forEach(p => {
            this.ctx.fillStyle = '#00d4ff';
            this.ctx.beginPath();
            this.ctx.arc(p.x, p.y, 4, 0, Math.PI * 2);
            this.ctx.fill();
          });
        }
        return;
      }

      // Outil image : on dessine un rectangle si une image est en attente
      if (this.currentTool === 'image') {
        if (!this._pendingImageData) {
          document.getElementById('imageInput').click();
          return;
        }
        // Laisser tomber dans le dessin normal (isDrawing = true plus bas)
      }

      // Outil mesure
      if (this.currentTool === 'measure') {
        if (!this._measureStart) {
          this._measureStart = { x, y };
          this.setStatus('Cliquez sur le second point');
        } else {
          const dx = x - this._measureStart.x, dy = y - this._measureStart.y;
          const dist = Math.sqrt(dx * dx + dy * dy);
          this.setStatus(`Distance : ${Math.round(dist)} px`);
          // Dessiner la ligne de mesure temporairement
          this.draw();
          const ctx = this.ctx;
          ctx.save();
          ctx.setTransform(this.zoom, 0, 0, this.zoom, this.panX, this.panY);
          ctx.strokeStyle = '#e74c3c'; ctx.lineWidth = 1; ctx.setLineDash([4, 4]);
          ctx.beginPath(); ctx.moveTo(this._measureStart.x, this._measureStart.y); ctx.lineTo(x, y); ctx.stroke();
          ctx.setLineDash([]);
          ctx.fillStyle = '#e74c3c'; ctx.font = '13px monospace';
          ctx.fillText(Math.round(dist) + ' px', (this._measureStart.x + x) / 2 + 8, (this._measureStart.y + y) / 2 - 8);
          ctx.restore();
          this._measureStart = null;
        }
        return;
      }

      // Outil pipette
      if (this.currentTool === 'picker') {
        this.pickColor(x, y);
        return;
      }

      // Debut du dessin pour les autres outils
      this.isDrawing = true; this.startX = x; this.startY = y;
      if (this.currentTool === 'pencil') { this.pencilPoints = [{ x, y }]; }
    });

    /* --- MOUSEMOVE --- */
    c.addEventListener('mousemove', e => {
      // Pan en cours
      if (this._isPanning) {
        this.panX = e.clientX - this._panStartX;
        this.panY = e.clientY - this._panStartY;
        this.draw();
        return;
      }
      const { x, y } = this.getMousePos(e);
      this.coordsElem.textContent = `X:${Math.round(x)} Y:${Math.round(y)}`;

      // Rotation en cours
      if (this.rotating && e.buttons === 1) {
        const angle = Math.atan2(y - this.rotating.cy, x - this.rotating.cx);
        const shape = this.lm.getSelected();
        if (shape) {
          shape.rotation = this.rotating.origRotation + (angle - this.rotating.startAngle);
          this.draw();
          this.setStatus('Rotation : ' + Math.round(shape.rotation * 180 / Math.PI) + '\u00B0');
        }
        return;
      }

      // Redimensionnement en cours (proportionnel ou libre)
      if (this.resizing && e.buttons === 1) {
        const dx = x - this.resizing.startX, dy = y - this.resizing.startY;
        let nb;
        if (this.resizing.type === 'proportional') {
          nb = this._applyResizeProportional(this.resizing.handleId, dx, dy, this.resizing.origBounds, this.resizing.origRatio);
        } else {
          nb = this._applyResize(this.resizing.handleId, dx, dy, this.resizing.origBounds);
        }
        const shape = this.lm.getSelected();
        if (shape) { shape.resize(nb); this.draw(); }
        return;
      }

      // Changement de curseur au survol des poignees
      if (this.currentTool === 'select' && !this.isDrawing && this.movingIndex < 0) {
        const h = this._hitHandle(x, y);
        this.canvas.style.cursor = h ? h.cursor : 'default';
      }

      // Deplacement, deformation (Shift), rotation (Alt)
      if (this.movingIndex >= 0 && e.buttons === 1) {
        const shape = this.lm.layers[this.movingIndex];
        const dx = x - this.moveOffsetX, dy = y - this.moveOffsetY;
        if (e.shiftKey) {
          // Shift + drag = deformation (skew)
          const b = shape.getBounds();
          shape.skewX += dx / (b.w || 100);
          shape.skewY += dy / (b.h || 100);
          this.moveOffsetX = x; this.moveOffsetY = y;
          this.setStatus('Deformation : skewX=' + shape.skewX.toFixed(2) + ' skewY=' + shape.skewY.toFixed(2));
        } else if (e.altKey) {
          // Alt + drag = rotation
          const b = shape.getBounds();
          const cx = b.x + b.w/2, cy = b.y + b.h/2;
          const angle = Math.atan2(y - cy, x - cx) - Math.atan2(this.moveOffsetY - cy, this.moveOffsetX - cx);
          shape.rotation += angle;
          this.moveOffsetX = x; this.moveOffsetY = y;
          this.setStatus('Rotation : ' + Math.round(shape.rotation * 180 / Math.PI) + '\u00B0');
        } else {
          // Deplacement normal
          shape.move(dx, dy);
          this.moveOffsetX = x; this.moveOffsetY = y;
        }
        this.draw(); return;
      }

      // Forme fantome (copier-coller)
      if (this.ghostShape) {
        this.draw();
        const b = this.ghostShape.getBounds();
        this.ghostShape.move(x - b.x - b.w / 2, y - b.y - b.h / 2);
        this.ghostShape.draw(this.ctx, false, true);
        return;
      }

      if (!this.isDrawing) return;
      this.draw();

      // Guides d'alignement (snap)
      const snap = this.lm.getSnapGuides(x, y);
      const finalX = snap.snapX !== null ? snap.snapX : x;
      const finalY = snap.snapY !== null ? snap.snapY : y;
      this.ctx.setLineDash([5, 5]); this.ctx.strokeStyle = 'rgba(0,212,255,0.4)'; this.ctx.lineWidth = 1;
      if (snap.snapX !== null) { this.ctx.beginPath(); this.ctx.moveTo(snap.snapX, 0); this.ctx.lineTo(snap.snapX, this.canvas.height); this.ctx.stroke(); }
      if (snap.snapY !== null) { this.ctx.beginPath(); this.ctx.moveTo(0, snap.snapY); this.ctx.lineTo(this.canvas.width, snap.snapY); this.ctx.stroke(); }
      this.ctx.setLineDash([]);

      // Apercu de la forme en cours de dessin
      if (this.currentTool === 'pencil') {
        this.pencilPoints.push({ x, y });
        const preview = new Pencil([...this.pencilPoints]);
        this.applyStyleToShape(preview);
        preview.draw(this.ctx, true);
      } else if (this.currentTool === 'image' && this._pendingImageData) {
        // Apercu rectangle pointille pour la zone image
        this.ctx.strokeStyle = '#00d4ff';
        this.ctx.lineWidth = 2;
        this.ctx.setLineDash([8, 4]);
        this.ctx.strokeRect(this.startX, this.startY, finalX - this.startX, finalY - this.startY);
        this.ctx.setLineDash([]);
        // Afficher dimensions
        const pw = Math.abs(finalX - this.startX), ph = Math.abs(finalY - this.startY);
        this.ctx.fillStyle = '#00d4ff';
        this.ctx.font = '12px monospace';
        this.ctx.fillText(`${Math.round(pw)} x ${Math.round(ph)}`, Math.min(this.startX, finalX) + 4, Math.min(this.startY, finalY) - 6);
      } else {
        const preview = this._createShape(this.startX, this.startY, finalX, finalY);
        if (preview) { this.applyStyleToShape(preview); preview.draw(this.ctx, true); }
      }
    });

    /* --- MOUSEUP --- */
    c.addEventListener('mouseup', e => {
      // Fin de pan
      if (this._isPanning) {
        this._isPanning = false;
        this.canvas.style.cursor = this.currentTool === 'select' ? 'default' : 'crosshair';
        return;
      }
      // Fin de rotation
      if (this.rotating) {
        this.rotating = null;
        this.saveState(); this.draw();
        return;
      }
      // Fin de redimensionnement
      if (this.resizing) {
        this.resizing = null;
        this.saveState(); this.draw();
        return;
      }
      // Fin de deplacement
      if (this.movingIndex >= 0) {
        this.movingIndex = -1;
        this.saveState();
        return;
      }
      if (!this.isDrawing) return;
      this.isDrawing = false;

      const { x, y } = this.getMousePos(e);
      const snap = this.lm.getSnapGuides(x, y);
      const finalX = snap.snapX !== null ? snap.snapX : x;
      const finalY = snap.snapY !== null ? snap.snapY : y;

      // Creation de la forme finale
      let shape;
      if (this.currentTool === 'pencil') {
        this.pencilPoints.push({ x, y });
        shape = new Pencil([...this.pencilPoints]);
        this.pencilPoints = [];
      } else if (this.currentTool === 'image' && this._pendingImageData) {
        // Image : creer l'ImageShape dans la zone tracee ou par simple clic
        const iw = Math.abs(finalX - this.startX);
        const ih = Math.abs(finalY - this.startY);
        if (iw > 10 && ih > 10) {
          // Glisser-deposer : taille definie par le trace
          const ix = Math.min(this.startX, finalX);
          const iy = Math.min(this.startY, finalY);
          shape = new ImageShape(Math.round(ix), Math.round(iy), Math.round(iw), Math.round(ih), this._pendingImageData);
        } else {
          // Simple clic : taille auto adaptee au canvas
          const tmpImg = new Image();
          tmpImg.src = this._pendingImageData;
          let w = tmpImg.width || 200, h = tmpImg.height || 200;
          const maxW = this.canvas.width * 0.5, maxH = this.canvas.height * 0.5;
          if (w > maxW) { h *= maxW / w; w = maxW; }
          if (h > maxH) { w *= maxH / h; h = maxH; }
          const ix = Math.round(this.startX - w / 2);
          const iy = Math.round(this.startY - h / 2);
          shape = new ImageShape(ix, iy, Math.round(w), Math.round(h), this._pendingImageData);
        }
        shape.opacity = parseInt(document.getElementById('opacity').value) / 100;
        this._pendingImageData = null;
        this.setTool('select');
      } else {
        shape = this._createShape(this.startX, this.startY, finalX, finalY);
      }

      if (shape) {
        if (this.currentTool !== 'image') this.applyStyleToShape(shape);
        this.lm.add(shape);
        this.saveState();
      }
      this.draw();
    });

    /* --- RACCOURCIS CLAVIER --- */
    document.addEventListener('keydown', e => {
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

      if (e.ctrlKey && e.key === 'z') { e.preventDefault(); this.undo(); }
      else if (e.ctrlKey && e.key === 'y') { e.preventDefault(); this.redo(); }
      else if (e.ctrlKey && e.key === 'c') {
        e.preventDefault();
        if (this.lm.selectedIndex !== null) this.copyShape(this.lm.selectedIndex);
      }
      else if (e.ctrlKey && e.key === 'v') {
        e.preventDefault();
        if (this.copiedShape) { this.ghostShape = this.copiedShape.clone(); this.setStatus('Cliquez pour coller'); }
      }
      else if (e.ctrlKey && e.key === 's') { e.preventDefault(); this.openSaveOverlay('save'); }
      else if (e.ctrlKey && e.shiftKey && e.key === 'E') { e.preventDefault(); this.exportSVG(); }
      else if (e.ctrlKey && e.key === 'e') { e.preventDefault(); this.exportPNG(); }
      else if (e.ctrlKey && e.key === 'a') {
        // Ctrl+A : selectionner la derniere forme (tout n'est pas multi-select)
        e.preventDefault();
        if (this.lm.layers.length > 0) {
          this.lm.select(this.lm.layers.length - 1);
          this.loadStyleFromShape(this.lm.getSelected());
          this.draw();
        }
      }
      else if (e.ctrlKey && e.key === 'd') {
        // Ctrl+D : dupliquer la forme selectionnee
        e.preventDefault();
        if (this.lm.selectedIndex !== null) {
          const clone = this.lm.layers[this.lm.selectedIndex].clone();
          clone.move(20, 20);
          this.lm.add(clone);
          this.saveState(); this.draw();
          this.setStatus('Duplique');
        }
      }
      else if (e.key === 'Delete' || e.key === 'Backspace') {
        if (this.lm.selectedIndex !== null) { e.preventDefault(); this.deleteShape(this.lm.selectedIndex); }
      }
      else if (e.key === 'Escape') {
        this.isDrawing = false; this.ghostShape = null; this.copiedShape = null;
        this.movingIndex = -1; this.resizing = null; this.rotating = null; this.skewing = null;
        this._isPanning = false; this._bezierPoints = null; this._quadPoints = null;
        this.draw(); this.setStatus('Annule');
      }
      else if (e.key === '+' || e.key === '=') {
        // + : augmenter taille du trait
        const sw = document.getElementById('strokeWidth');
        sw.value = Math.min(20, parseInt(sw.value) + 1);
        document.getElementById('strokeWidthVal').textContent = sw.value;
        this.updateSelectedShape();
      }
      else if (e.key === '-') {
        // - : diminuer taille du trait
        const sw = document.getElementById('strokeWidth');
        sw.value = Math.max(1, parseInt(sw.value) - 1);
        document.getElementById('strokeWidthVal').textContent = sw.value;
        this.updateSelectedShape();
      }
      else if (e.key === '0' && e.ctrlKey) {
        // Ctrl+0 : reset zoom
        e.preventDefault();
        this.zoom = 1; this.panX = 0; this.panY = 0;
        this.draw(); this.setStatus('Zoom 100%');
      }
      else if (e.key === ' ') {
        // Espace : activer le mode pan
        e.preventDefault();
        this.canvas.style.cursor = 'grab';
      }
      else {
        // Raccourcis outils (une lettre)
        const keyMap = {
          v: 'select', p: 'pencil', l: 'line', r: 'rect', c: 'circle',
          e: 'ellipse', t: 'triangle', a: 'arrow', x: 'text',
          g: 'eraser', i: 'image', k: 'picker', b: 'bezier',
          h: 'polygon', j: 'star', d: 'doublearrow', m: 'measure',
          q: 'quadratic'
        };
        if (!e.ctrlKey && !e.altKey && keyMap[e.key.toLowerCase()]) {
          e.preventDefault();
          this.setTool(keyMap[e.key.toLowerCase()]);
        }
      }
    });

    document.addEventListener('keyup', e => {
      if (e.key === ' ') {
        this.canvas.style.cursor = this.currentTool === 'select' ? 'default' : 'crosshair';
        this._isPanning = false;
      }
    });

    /* --- ZOOM (molette) --- */
    c.addEventListener('wheel', e => {
      e.preventDefault();
      const delta = e.deltaY > 0 ? 0.9 : 1.1;
      const newZoom = Math.max(0.1, Math.min(5, this.zoom * delta));
      // Zoom vers le curseur
      const rect = this.canvas.getBoundingClientRect();
      const mx = (e.clientX - rect.left) * (this.canvas.width / rect.width);
      const my = (e.clientY - rect.top) * (this.canvas.height / rect.height);
      this.panX = mx - (mx - this.panX) * (newZoom / this.zoom);
      this.panY = my - (my - this.panY) * (newZoom / this.zoom);
      this.zoom = newZoom;
      this.draw();
    }, { passive: false });

    /* --- DOUBLE-CLIC : edition texte inline --- */
    c.addEventListener('dblclick', e => {
      const { x, y } = this.getMousePos(e);
      const i = this.lm.findAt(x, y);
      if (i >= 0 && this.lm.layers[i].type === 'text') {
        this.lm.select(i);
        this._editTextInline(this.lm.layers[i]);
      }
    });

    /* --- CONTROLES TOP-BAR — mise a jour en direct de la forme selectionnee --- */
    document.getElementById('strokeColor').addEventListener('input', () => this.updateSelectedShape());
    document.getElementById('strokeColor').addEventListener('change', () => { this._addRecentColor(document.getElementById('strokeColor').value); if (this.lm.getSelected()) this.saveState(); });
    document.getElementById('fillColor').addEventListener('input', () => this.updateSelectedShape());
    document.getElementById('fillColor').addEventListener('change', () => { this._addRecentColor(document.getElementById('fillColor').value); if (this.lm.getSelected()) this.saveState(); });
    document.getElementById('useFill').addEventListener('change', () => { this.updateSelectedShape(); if (this.lm.getSelected()) this.saveState(); });
    document.getElementById('strokeWidth').addEventListener('input', e => {
      document.getElementById('strokeWidthVal').textContent = e.target.value;
      this.updateSelectedShape();
    });
    document.getElementById('strokeWidth').addEventListener('change', () => { if (this.lm.getSelected()) this.saveState(); });
    document.getElementById('opacity').addEventListener('input', e => {
      document.getElementById('opacityVal').textContent = e.target.value + '%';
      this.updateSelectedShape();
    });
    document.getElementById('opacity').addEventListener('change', () => { if (this.lm.getSelected()) this.saveState(); });
    document.getElementById('dashStyle').addEventListener('change', () => { this.updateSelectedShape(); if (this.lm.getSelected()) this.saveState(); });
    document.getElementById('bgColor').addEventListener('input', e => {
      this.bgColor = e.target.value;
      this.draw();
    });

    /* --- BOUTONS ALIGNEMENT --- */
    document.querySelectorAll('[data-align]').forEach(btn => {
      btn.addEventListener('click', () => this.alignShapes(btn.dataset.align));
    });

    /* --- BOUTONS TOP-BAR --- */
    document.getElementById('btnUndo').addEventListener('click', () => this.undo());
    document.getElementById('btnRedo').addEventListener('click', () => this.redo());
    document.getElementById('btnClear').addEventListener('click', () => {
      if (confirm('Effacer tout le dessin ?')) { this.lm.clear(); this.saveState(); this.draw(); }
    });
    document.getElementById('btnExport').addEventListener('click', () => this.exportPNG());
    document.getElementById('btnExportSVG').addEventListener('click', () => this.exportSVG());
    document.getElementById('btnExportPDF').addEventListener('click', () => this.exportPDF());
    document.getElementById('btnSave').addEventListener('click', () => this.openSaveOverlay('save'));
    document.getElementById('btnLoad').addEventListener('click', () => this.openSaveOverlay('load'));
    document.getElementById('btnGrid').addEventListener('click', () => this.toggleGrid());
    document.getElementById('btnNew').addEventListener('click', () => this.openResizeOverlay('new'));
    document.getElementById('btnResize').addEventListener('click', () => this.openResizeOverlay('resize'));
    document.getElementById('resize-confirm').addEventListener('click', () => this._confirmResize());
    document.getElementById('resize-cancel').addEventListener('click', () => this.closeResizeOverlay());
    document.getElementById('resize-overlay').addEventListener('click', e => {
      if (e.target === e.currentTarget) this.closeResizeOverlay();
    });
    document.querySelectorAll('.preset-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('resizeW').value = btn.dataset.w;
        document.getElementById('resizeH').value = btn.dataset.h;
      });
    });
    document.getElementById('btnShortcuts').addEventListener('click', () => document.getElementById('shortcuts-overlay').classList.add('show'));
    document.getElementById('shortcuts-overlay').addEventListener('click', e => {
      if (e.target === e.currentTarget) e.currentTarget.classList.remove('show');
    });
    document.getElementById('btnSnapGrid').addEventListener('click', () => {
      this.snapToGrid = !this.snapToGrid;
      document.getElementById('btnSnapGrid').classList.toggle('active', this.snapToGrid);
      this.setStatus(this.snapToGrid ? 'Snap grille active' : 'Snap grille desactive');
    });
    document.getElementById('btnTheme').addEventListener('click', () => this.toggleTheme());
    document.getElementById('btnPresentation').addEventListener('click', () => this.togglePresentation());

    // Barre flottante texte
    document.getElementById('ftFontFamily').addEventListener('change', e => {
      const shape = this.lm.getSelected();
      if (shape && shape.type === 'text') {
        shape.fontFamily = e.target.value;
        document.getElementById('fontFamily').value = e.target.value;
        this.draw(); this.saveState();
      }
    });
    document.getElementById('ftFontSize').addEventListener('change', e => {
      const shape = this.lm.getSelected();
      if (shape && shape.type === 'text') {
        shape.fontSize = Math.max(6, parseInt(e.target.value) || 16);
        this.draw(); this.saveState();
        this._updateTextToolbar(shape);
      }
    });
    document.getElementById('ftBold').addEventListener('click', () => {
      const shape = this.lm.getSelected();
      if (shape && shape.type === 'text') {
        shape.fontBold = !shape.fontBold;
        document.getElementById('ftBold').classList.toggle('active', shape.fontBold);
        document.getElementById('btnBold').classList.toggle('active', shape.fontBold);
        this.draw(); this.saveState();
      }
    });
    document.getElementById('ftItalic').addEventListener('click', () => {
      const shape = this.lm.getSelected();
      if (shape && shape.type === 'text') {
        shape.fontItalic = !shape.fontItalic;
        document.getElementById('ftItalic').classList.toggle('active', shape.fontItalic);
        document.getElementById('btnItalic').classList.toggle('active', shape.fontItalic);
        this.draw(); this.saveState();
      }
    });
    document.getElementById('ftColor').addEventListener('input', e => {
      const shape = this.lm.getSelected();
      if (shape && shape.type === 'text') {
        shape.strokeColor = e.target.value;
        document.getElementById('strokeColor').value = e.target.value;
        this.draw();
      }
    });
    document.getElementById('ftColor').addEventListener('change', () => { if (this.lm.getSelected()) this.saveState(); });

    // Controles forme avancees
    document.getElementById('borderRadius').addEventListener('input', e => {
      document.getElementById('borderRadiusVal').textContent = e.target.value;
      const shape = this.lm.getSelected();
      if (shape && shape.borderRadius !== undefined) { shape.borderRadius = parseInt(e.target.value); this.draw(); }
    });
    document.getElementById('borderRadius').addEventListener('change', () => { if (this.lm.getSelected()) this.saveState(); });
    document.getElementById('useShadow').addEventListener('change', e => {
      const shape = this.lm.getSelected();
      if (shape) {
        shape.shadowColor = e.target.checked ? document.getElementById('shadowColor').value : '';
        this.draw(); this.saveState();
      }
    });
    document.getElementById('shadowColor').addEventListener('input', e => {
      const shape = this.lm.getSelected();
      if (shape && document.getElementById('useShadow').checked) { shape.shadowColor = e.target.value; this.draw(); }
    });
    document.getElementById('polygonSides').addEventListener('change', e => {
      const shape = this.lm.getSelected();
      if (shape && shape.sides !== undefined) { shape.sides = parseInt(e.target.value); this.draw(); this.saveState(); }
    });

    // Degrade
    document.getElementById('gradientType').addEventListener('change', e => {
      const shape = this.lm.getSelected();
      if (shape) { shape.gradientType = e.target.value; this.draw(); this.saveState(); }
    });
    document.getElementById('gradientColor1').addEventListener('input', e => {
      const shape = this.lm.getSelected();
      if (shape) { shape.gradientColor1 = e.target.value; this.draw(); }
    });
    document.getElementById('gradientColor2').addEventListener('input', e => {
      const shape = this.lm.getSelected();
      if (shape) { shape.gradientColor2 = e.target.value; this.draw(); }
    });

    // Font
    document.getElementById('fontFamily').addEventListener('change', e => {
      const shape = this.lm.getSelected();
      if (shape) { shape.fontFamily = e.target.value; this.draw(); this.saveState(); }
    });
    document.getElementById('btnBold').addEventListener('click', () => {
      const shape = this.lm.getSelected();
      if (shape) { shape.fontBold = !shape.fontBold; document.getElementById('btnBold').classList.toggle('active', shape.fontBold); this.draw(); this.saveState(); }
    });
    document.getElementById('btnItalic').addEventListener('click', () => {
      const shape = this.lm.getSelected();
      if (shape) { shape.fontItalic = !shape.fontItalic; document.getElementById('btnItalic').classList.toggle('active', shape.fontItalic); this.draw(); this.saveState(); }
    });

    /* --- TOUCH EVENTS (mobile/tablette) --- */
    let touchId = null;
    c.addEventListener('touchstart', e => {
      if (e.touches.length === 1) {
        e.preventDefault();
        touchId = e.touches[0].identifier;
        const t = e.touches[0];
        c.dispatchEvent(new MouseEvent('mousedown', { clientX: t.clientX, clientY: t.clientY, button: 0 }));
      }
    }, { passive: false });
    c.addEventListener('touchmove', e => {
      if (e.touches.length === 1) {
        e.preventDefault();
        const t = e.touches[0];
        c.dispatchEvent(new MouseEvent('mousemove', { clientX: t.clientX, clientY: t.clientY, buttons: 1 }));
      }
    }, { passive: false });
    c.addEventListener('touchend', e => {
      e.preventDefault();
      const t = e.changedTouches[0];
      c.dispatchEvent(new MouseEvent('mouseup', { clientX: t.clientX, clientY: t.clientY, button: 0 }));
      touchId = null;
    }, { passive: false });

    /* --- REPERES : clic sur les regles pour ajouter/supprimer --- */
    const rulerH = document.getElementById('rulerH');
    const rulerV = document.getElementById('rulerV');
    if (rulerH) rulerH.addEventListener('click', e => {
      const rect = rulerH.getBoundingClientRect();
      const pos = (e.clientX - rect.left - this.panX) / this.zoom;
      const existing = this.guides.findIndex(g => g.axis === 'v' && Math.abs(g.pos - pos) < 5);
      if (existing >= 0) { this.guides.splice(existing, 1); } else { this.guides.push({ axis: 'v', pos: Math.round(pos) }); }
      this.draw();
    });
    if (rulerV) rulerV.addEventListener('click', e => {
      const rect = rulerV.getBoundingClientRect();
      const pos = (e.clientY - rect.top - this.panY) / this.zoom;
      const existing = this.guides.findIndex(g => g.axis === 'h' && Math.abs(g.pos - pos) < 5);
      if (existing >= 0) { this.guides.splice(existing, 1); } else { this.guides.push({ axis: 'h', pos: Math.round(pos) }); }
      this.draw();
    });

    /* --- INPUT FICHIER IMAGE --- */
    document.getElementById('imageInput').addEventListener('change', e => {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = ev => {
        // Stocker l'image et passer en mode trace via setTool
        this._pendingImageData = ev.target.result;
        this.setTool('image');
        this.setStatus('Tracez la zone pour placer l\'image');
      };
      reader.readAsDataURL(file);
      e.target.value = '';
    });
  }

  /* ===========================================
     Creation de formes
     =========================================== */

  /** Cree une forme selon l'outil actif et les coordonnees de dessin */
  _createShape(x1, y1, x2, y2) {
    switch (this.currentTool) {
      case 'circle': return new Circle(x1, y1, Math.sqrt((x2 - x1) ** 2 + (y2 - y1) ** 2));
      case 'rect':   return new Rect(x1, y1, x2 - x1, y2 - y1);
      case 'line':   return new LineShape(x1, y1, x2, y2);
      case 'ellipse': {
        const cx = (x1 + x2) / 2, cy = (y1 + y2) / 2;
        return new EllipseShape(cx, cy, Math.abs(x2 - x1) / 2, Math.abs(y2 - y1) / 2);
      }
      case 'triangle': {
        const cx = (x1 + x2) / 2;
        return new Triangle(cx, y1, x1, y2, x2, y2);
      }
      case 'arrow': return new Arrow(x1, y1, x2, y2);
      case 'doublearrow': return new DoubleArrow(x1, y1, x2, y2);
      case 'polygon': {
        const cx = (x1 + x2) / 2, cy = (y1 + y2) / 2;
        const r = Math.sqrt((x2 - x1) ** 2 + (y2 - y1) ** 2) / 2;
        const sides = parseInt(document.getElementById('polygonSides').value) || 6;
        return new PolygonShape(cx, cy, r, sides, false);
      }
      case 'star': {
        const cx = (x1 + x2) / 2, cy = (y1 + y2) / 2;
        const r = Math.sqrt((x2 - x1) ** 2 + (y2 - y1) ** 2) / 2;
        const sides = parseInt(document.getElementById('polygonSides').value) || 5;
        return new PolygonShape(cx, cy, r, sides, true);
      }
      default: return null;
    }
  }

  /* ===========================================
     Rendu
     =========================================== */

  /** Efface le canvas et redessine toutes les formes + poignees */
  draw() {
    const ctx = this.ctx;
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

    // Fond
    ctx.fillStyle = this.bgColor;
    ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

    // Dessiner la grille AVANT le zoom pour couvrir tout le canvas
    if (this.showGrid) this._drawGrid();

    // Appliquer zoom/pan
    ctx.setTransform(this.zoom, 0, 0, this.zoom, this.panX, this.panY);
    this.lm.drawAll(ctx);
    this._drawHandles();
    this._drawGuides(ctx);

    // Reset transform
    ctx.setTransform(1, 0, 0, 1, 0, 0);

    // Regles
    this.drawRulers();

    // Indicateur zoom
    if (this.zoom !== 1) {
      ctx.fillStyle = 'rgba(22,33,62,0.9)';
      ctx.fillRect(this.canvas.width - 80, 10, 70, 24);
      ctx.fillStyle = '#00d4ff';
      ctx.font = '12px monospace';
      ctx.fillText(Math.round(this.zoom * 100) + '%', this.canvas.width - 72, 26);
    }
  }

  /** Dessine la grille sur toute la surface du canvas (en pixels ecran) */
  _drawGrid() {
    const ctx = this.ctx;
    const w = this.canvas.width;
    const h = this.canvas.height;

    // Espacement en pixels ecran (tient compte du zoom)
    const gap = this.gridSize * this.zoom;

    // Decalage pour aligner avec le pan
    const offsetX = this.panX % gap;
    const offsetY = this.panY % gap;

    ctx.strokeStyle = this.lightTheme ? 'rgba(0,0,0,0.1)' : 'rgba(255,255,255,0.08)';
    ctx.lineWidth = 0.5;
    ctx.beginPath();

    // Lignes verticales de haut en bas
    for (let x = offsetX; x <= w; x += gap) {
      ctx.moveTo(x, 0);
      ctx.lineTo(x, h);
    }

    // Lignes horizontales de gauche a droite
    for (let y = offsetY; y <= h; y += gap) {
      ctx.moveTo(0, y);
      ctx.lineTo(w, y);
    }

    ctx.stroke();
  }

  /* ===========================================
     Dimensions du canvas (Nouveau / Redimensionner)
     =========================================== */

  /** Ouvre l'overlay de dimensions */
  openResizeOverlay(mode) {
    this._resizeMode = mode;
    const overlay = document.getElementById('resize-overlay');
    const title = document.getElementById('resize-title');
    const nameField = document.getElementById('projectNameField');
    title.textContent = mode === 'new' ? 'Nouveau projet' : 'Redimensionner le canvas';
    nameField.style.display = mode === 'new' ? '' : 'none';
    document.getElementById('projectName').value = mode === 'new' ? '' : this.projectName;
    document.getElementById('resizeW').value = this.canvas.width;
    document.getElementById('resizeH').value = this.canvas.height;
    overlay.classList.add('show');
    if (mode === 'new') {
      document.getElementById('projectName').focus();
    } else {
      document.getElementById('resizeW').focus();
    }
  }

  /** Ferme l'overlay */
  closeResizeOverlay() {
    document.getElementById('resize-overlay').classList.remove('show');
  }

  /** Confirme le changement de dimensions */
  _confirmResize() {
    const w = Math.max(100, Math.min(4000, parseInt(document.getElementById('resizeW').value) || 900));
    const h = Math.max(100, Math.min(4000, parseInt(document.getElementById('resizeH').value) || 600));

    if (this._resizeMode === 'new') {
      const name = document.getElementById('projectName').value.trim() || 'Sans titre';
      this.setProjectName(name);
      this.lm.clear();
      this.history = new History();
    }

    this.canvas.width = w;
    this.canvas.height = h;
    this.saveState();
    this.draw();
    this.closeResizeOverlay();
    this.setStatus(this._resizeMode === 'new'
      ? `Projet "${this.projectName}" cree (${w}x${h})`
      : `Canvas redimensionne ${w}x${h}`);
  }

  /** Met a jour le nom du projet dans l'UI et le titre de la page */
  setProjectName(name) {
    this.projectName = name;
    document.getElementById('projectTitle').textContent = name;
    document.title = name + ' — Draw';
  }

  /** Active/desactive la grille */
  toggleGrid() {
    this.showGrid = !this.showGrid;
    const btn = document.getElementById('btnGrid');
    if (btn) btn.classList.toggle('active', this.showGrid);
    this.draw();
  }

  /* ===========================================
     Export / Sauvegarde / Chargement
     =========================================== */

  /** Exporte le canvas en PNG et declenche le telechargement */
  exportPNG() {
    const link = document.createElement('a');
    link.download = 'dessin.png';
    link.href = this.canvas.toDataURL('image/png');
    link.click();
    this.setStatus('PNG exporte');
  }

  /** Exporte le dessin en SVG et declenche le telechargement */
  exportSVG() {
    const w = this.canvas.width, h = this.canvas.height;
    let svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">\n`;
    svg += `<rect width="${w}" height="${h}" fill="#ffffff"/>\n`;

    for (const shape of this.lm.layers) {
      svg += this._shapeToSVG(shape);
    }

    svg += '</svg>';
    const blob = new Blob([svg], { type: 'image/svg+xml' });
    const link = document.createElement('a');
    link.download = 'dessin.svg';
    link.href = URL.createObjectURL(blob);
    link.click();
    URL.revokeObjectURL(link.href);
    this.setStatus('SVG exporte');
  }

  /** Convertit une forme en element SVG */
  _shapeToSVG(shape) {
    const stroke = shape.strokeColor;
    const fill = shape.useFill ? shape.fillColor : 'none';
    const sw = shape.lineWidth;
    const op = shape.opacity;
    let dash = '';
    if (shape.dashStyle === 'dashed') dash = ' stroke-dasharray="12,6"';
    else if (shape.dashStyle === 'dotted') dash = ' stroke-dasharray="2,4"';

    let transform = '';
    if (shape.rotation || shape.skewX || shape.skewY) {
      const b = shape.getBounds();
      const cx = b.x + b.w / 2, cy = b.y + b.h / 2;
      const parts = [];
      if (shape.rotation) parts.push(`rotate(${(shape.rotation * 180 / Math.PI).toFixed(2)} ${cx.toFixed(2)} ${cy.toFixed(2)})`);
      if (shape.skewX) parts.push(`skewX(${(Math.atan(shape.skewX) * 180 / Math.PI).toFixed(2)})`);
      if (shape.skewY) parts.push(`skewY(${(Math.atan(shape.skewY) * 180 / Math.PI).toFixed(2)})`);
      transform = ` transform="${parts.join(' ')}"`;
    }

    const common = `stroke="${stroke}" fill="${fill}" stroke-width="${sw}" opacity="${op}"${dash}${transform}`;

    switch (shape.type) {
      case 'circle':
        return `<circle cx="${shape.x}" cy="${shape.y}" r="${shape.r}" ${common}/>\n`;
      case 'rect':
        return `<rect x="${Math.min(shape.x, shape.x + shape.w)}" y="${Math.min(shape.y, shape.y + shape.h)}" width="${Math.abs(shape.w)}" height="${Math.abs(shape.h)}" ${common}/>\n`;
      case 'line':
        return `<line x1="${shape.x1}" y1="${shape.y1}" x2="${shape.x2}" y2="${shape.y2}" ${common}/>\n`;
      case 'ellipse':
        return `<ellipse cx="${shape.x}" cy="${shape.y}" rx="${shape.rx}" ry="${shape.ry}" ${common}/>\n`;
      case 'triangle':
        return `<polygon points="${shape.x1},${shape.y1} ${shape.x2},${shape.y2} ${shape.x3},${shape.y3}" ${common}/>\n`;
      case 'arrow': {
        const headLen = 14;
        const angle = Math.atan2(shape.y2 - shape.y1, shape.x2 - shape.x1);
        const ax1 = shape.x2 - headLen * Math.cos(angle - Math.PI / 6);
        const ay1 = shape.y2 - headLen * Math.sin(angle - Math.PI / 6);
        const ax2 = shape.x2 - headLen * Math.cos(angle + Math.PI / 6);
        const ay2 = shape.y2 - headLen * Math.sin(angle + Math.PI / 6);
        return `<g ${common}><line x1="${shape.x1}" y1="${shape.y1}" x2="${shape.x2}" y2="${shape.y2}"/><line x1="${shape.x2}" y1="${shape.y2}" x2="${ax1.toFixed(1)}" y2="${ay1.toFixed(1)}"/><line x1="${shape.x2}" y1="${shape.y2}" x2="${ax2.toFixed(1)}" y2="${ay2.toFixed(1)}"/></g>\n`;
      }
      case 'pencil': {
        if (shape.points.length < 2) return '';
        const pts = shape.points.map(p => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ');
        return `<polyline points="${pts}" fill="none" stroke="${stroke}" stroke-width="${sw}" opacity="${op}"${dash}${transform}/>\n`;
      }
      case 'text': {
        const textFill = shape.useFill ? shape.fillColor : shape.strokeColor;
        const escaped = shape.text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return `<text x="${shape.x}" y="${shape.y + shape.fontSize}" font-family="'Segoe UI', sans-serif" font-size="${shape.fontSize}" fill="${textFill}" opacity="${op}"${transform}>${escaped}</text>\n`;
      }
      case 'image':
        return `<image x="${shape.x}" y="${shape.y}" width="${shape.w}" height="${shape.h}" href="${shape.dataUrl}" opacity="${op}"${transform}/>\n`;
      case 'quadratic':
        return `<path d="M${shape.p0.x},${shape.p0.y} Q${shape.p1.x},${shape.p1.y} ${shape.p2.x},${shape.p2.y}" fill="none" ${common}/>\n`;
      case 'bezier':
        return `<path d="M${shape.p0.x},${shape.p0.y} C${shape.p1.x},${shape.p1.y} ${shape.p2.x},${shape.p2.y} ${shape.p3.x},${shape.p3.y}" fill="none" ${common}/>\n`;
      case 'polygon': {
        const pts = shape._getPoints().map(p => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ');
        return `<polygon points="${pts}" ${common}/>\n`;
      }
      case 'doublearrow': {
        const headLen = 14;
        const a = Math.atan2(shape.y2 - shape.y1, shape.x2 - shape.x1);
        const a2 = a + Math.PI;
        return `<g ${common}><line x1="${shape.x1}" y1="${shape.y1}" x2="${shape.x2}" y2="${shape.y2}"/><line x1="${shape.x2}" y1="${shape.y2}" x2="${(shape.x2 - headLen * Math.cos(a - Math.PI / 6)).toFixed(1)}" y2="${(shape.y2 - headLen * Math.sin(a - Math.PI / 6)).toFixed(1)}"/><line x1="${shape.x2}" y1="${shape.y2}" x2="${(shape.x2 - headLen * Math.cos(a + Math.PI / 6)).toFixed(1)}" y2="${(shape.y2 - headLen * Math.sin(a + Math.PI / 6)).toFixed(1)}"/><line x1="${shape.x1}" y1="${shape.y1}" x2="${(shape.x1 - headLen * Math.cos(a2 - Math.PI / 6)).toFixed(1)}" y2="${(shape.y1 - headLen * Math.sin(a2 - Math.PI / 6)).toFixed(1)}"/><line x1="${shape.x1}" y1="${shape.y1}" x2="${(shape.x1 - headLen * Math.cos(a2 + Math.PI / 6)).toFixed(1)}" y2="${(shape.y1 - headLen * Math.sin(a2 + Math.PI / 6)).toFixed(1)}"/></g>\n`;
      }
      default:
        return '';
    }
  }

  /* ===========================================
     Alignement de formes
     =========================================== */

  /** Aligne toutes les formes sur un axe donne */
  alignShapes(alignment) {
    if (this.lm.layers.length < 2) return;
    const bounds = this.lm.layers.map(s => s.getBounds());
    const canvasW = this.canvas.width, canvasH = this.canvas.height;

    this.lm.layers.forEach((shape, i) => {
      const b = bounds[i];
      let dx = 0, dy = 0;
      switch (alignment) {
        case 'left': dx = -b.x; break;
        case 'center-h': dx = (canvasW / 2) - (b.x + b.w / 2); break;
        case 'right': dx = canvasW - (b.x + b.w); break;
        case 'top': dy = -b.y; break;
        case 'center-v': dy = (canvasH / 2) - (b.y + b.h / 2); break;
        case 'bottom': dy = canvasH - (b.y + b.h); break;
      }
      shape.move(dx, dy);
    });
    this.saveState();
    this.draw();
    this.setStatus('Aligne : ' + alignment);
  }

  /* ===========================================
     Systeme de sauvegardes (style jeu video)
     =========================================== */

  /** Cle localStorage pour l'index des sauvegardes */
  static SAVES_KEY = 'draw_saves';

  /** Recupere la liste des sauvegardes */
  _getSaves() {
    try {
      return JSON.parse(localStorage.getItem(CanvasApp.SAVES_KEY)) || [];
    } catch { return []; }
  }

  /** Ecrit la liste des sauvegardes */
  _setSaves(saves) {
    localStorage.setItem(CanvasApp.SAVES_KEY, JSON.stringify(saves));
  }

  /** Genere une miniature du canvas (petit data URL) */
  _generateThumbnail() {
    const thumb = document.createElement('canvas');
    const tw = 160, th = Math.round(this.canvas.height * 160 / this.canvas.width);
    thumb.width = tw; thumb.height = th;
    const tctx = thumb.getContext('2d');
    tctx.drawImage(this.canvas, 0, 0, tw, th);
    return thumb.toDataURL('image/png', 0.6);
  }

  /** Sauvegarde dans un nouveau slot ou ecrase un slot existant */
  saveToSlot(slotIndex = -1, customName = '') {
    try {
      const saves = this._getSaves();
      const entry = {
        name: customName || 'Sauvegarde ' + (saves.length + 1),
        date: new Date().toISOString(),
        shapeCount: this.lm.layers.length,
        thumbnail: this._generateThumbnail(),
        projectName: this.projectName,
        canvasWidth: this.canvas.width,
        canvasHeight: this.canvas.height,
        bgColor: this.bgColor,
        data: this.lm.serialize()
      };

      if (slotIndex >= 0 && slotIndex < saves.length) {
        entry.name = customName || saves[slotIndex].name;
        saves[slotIndex] = entry;
      } else {
        saves.push(entry);
      }

      this._setSaves(saves);
      this.setStatus('Sauvegarde "' + entry.name + '" enregistree');
      return true;
    } catch (e) {
      this.setStatus('Erreur : espace de stockage insuffisant');
      console.error('Save failed:', e);
      return false;
    }
  }

  /** Charge un slot de sauvegarde */
  loadFromSlot(slotIndex) {
    try {
      const saves = this._getSaves();
      if (slotIndex < 0 || slotIndex >= saves.length) return;
      const entry = saves[slotIndex];
      if (entry.canvasWidth) this.canvas.width = entry.canvasWidth;
      if (entry.canvasHeight) this.canvas.height = entry.canvasHeight;
      if (entry.projectName) this.setProjectName(entry.projectName);
      if (entry.bgColor) { this.bgColor = entry.bgColor; document.getElementById('bgColor').value = entry.bgColor; }
      this.lm.deserialize(entry.data);
      this.saveState();
      this.draw();
      this.setStatus('Charge "' + entry.name + '" (' + this.lm.layers.length + ' formes)');
    } catch (e) {
      this.setStatus('Erreur : sauvegarde corrompue');
      console.error('Load failed:', e);
    }
  }

  /** Supprime un slot de sauvegarde */
  deleteSlot(slotIndex) {
    const saves = this._getSaves();
    if (slotIndex < 0 || slotIndex >= saves.length) return;
    const name = saves[slotIndex].name;
    saves.splice(slotIndex, 1);
    this._setSaves(saves);
    this.setStatus('Sauvegarde "' + name + '" supprimee');
  }

  /** Renomme un slot */
  renameSlot(slotIndex, newName) {
    const saves = this._getSaves();
    if (slotIndex < 0 || slotIndex >= saves.length) return;
    saves[slotIndex].name = newName;
    this._setSaves(saves);
  }

  /** Ouvre l'overlay de sauvegarde/chargement */
  openSaveOverlay(mode = 'save') {
    const overlay = document.getElementById('saves-overlay');
    overlay.dataset.mode = mode;
    overlay.classList.add('show');
    this._renderSaveSlots();
  }

  /** Ferme l'overlay */
  closeSaveOverlay() {
    document.getElementById('saves-overlay').classList.remove('show');
  }

  /** Genere le contenu HTML de l'overlay */
  _renderSaveSlots() {
    const overlay = document.getElementById('saves-overlay');
    const mode = overlay.dataset.mode;
    const container = document.getElementById('saves-list');
    const saves = this._getSaves();
    const title = document.getElementById('saves-title');
    title.textContent = mode === 'save' ? 'Sauvegarder' : 'Charger une sauvegarde';

    const newSlotBtn = document.getElementById('saves-new');
    newSlotBtn.style.display = mode === 'save' ? '' : 'none';

    container.innerHTML = '';

    if (saves.length === 0 && mode === 'load') {
      container.innerHTML = '<div class="save-empty">Aucune sauvegarde</div>';
      return;
    }

    saves.forEach((entry, i) => {
      const div = document.createElement('div');
      div.className = 'save-slot';

      const date = new Date(entry.date);
      const dateStr = date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

      div.innerHTML = `
        <img class="save-thumb" src="${entry.thumbnail}" alt="Apercu">
        <div class="save-info">
          <span class="save-name">${entry.projectName ? entry.projectName + ' — ' : ''}${entry.name}</span>
          <span class="save-meta">${dateStr} &mdash; ${entry.shapeCount} forme${entry.shapeCount > 1 ? 's' : ''}${entry.canvasWidth ? ` &mdash; ${entry.canvasWidth}x${entry.canvasHeight}` : ''}</span>
        </div>
        <div class="save-actions">
          ${mode === 'save' ? '<button class="save-btn save-overwrite" title="Ecraser">&#128190;</button>' : '<button class="save-btn save-load" title="Charger">&#9654;</button>'}
          <button class="save-btn save-rename" title="Renommer">&#9998;</button>
          <button class="save-btn save-delete" title="Supprimer">&#10005;</button>
        </div>`;

      // Charger / Ecraser
      if (mode === 'load') {
        div.querySelector('.save-load').addEventListener('click', e => {
          e.stopPropagation();
          this.loadFromSlot(i);
          this.closeSaveOverlay();
        });
        // Double-clic sur le slot pour charger
        div.addEventListener('dblclick', () => {
          this.loadFromSlot(i);
          this.closeSaveOverlay();
        });
      } else {
        div.querySelector('.save-overwrite').addEventListener('click', e => {
          e.stopPropagation();
          if (confirm('Ecraser "' + entry.name + '" ?')) {
            this.saveToSlot(i);
            this._renderSaveSlots();
          }
        });
      }

      // Renommer
      div.querySelector('.save-rename').addEventListener('click', e => {
        e.stopPropagation();
        const nameSpan = div.querySelector('.save-name');
        const input = document.createElement('input');
        input.type = 'text';
        input.value = entry.name;
        input.className = 'save-rename-input';
        nameSpan.replaceWith(input);
        input.focus();
        input.select();
        let done = false;
        const finish = () => {
          if (done) return; done = true;
          const val = input.value.trim();
          if (val && val !== entry.name) {
            this.renameSlot(i, val);
          }
          this._renderSaveSlots();
        };
        input.addEventListener('blur', finish);
        input.addEventListener('keydown', ev => {
          if (ev.key === 'Enter') input.blur();
          if (ev.key === 'Escape') { input.value = entry.name; input.blur(); }
        });
      });

      // Supprimer
      div.querySelector('.save-delete').addEventListener('click', e => {
        e.stopPropagation();
        if (confirm('Supprimer "' + entry.name + '" ?')) {
          this.deleteSlot(i);
          this._renderSaveSlots();
        }
      });

      container.appendChild(div);
    });
  }

  /* ===========================================
     Edition texte inline
     =========================================== */

  /** Cree un editeur texte inline a la position donnee (nouvel outil texte) */
  _createInlineTextEditor(x, y) {
    const fontSize = parseInt(document.getElementById('strokeWidth').value) * 6 + 4;
    const fontFamily = document.getElementById('fontFamily').value;
    const ta = document.createElement('textarea');
    ta.className = 'canvas-text-input';
    ta.placeholder = 'Tapez votre texte...';
    ta.rows = 1;
    const textColor = document.getElementById('strokeColor').value;
    ta.style.cssText = `position:fixed;font-size:${fontSize * this.zoom}px;color:${textColor};background:rgba(255,255,255,0.9);border:2px solid #00d4ff;border-radius:4px;outline:none;padding:4px 6px;font-family:'${fontFamily}',sans-serif;resize:none;overflow:hidden;min-width:100px;box-shadow:0 2px 12px rgba(0,0,0,0.3);z-index:1000;`;

    // Positionner directement par rapport a la fenetre
    const canvasRect = this.canvas.getBoundingClientRect();
    const scaleX = canvasRect.width / this.canvas.width;
    const scaleY = canvasRect.height / this.canvas.height;
    ta.style.left = (canvasRect.left + (x * this.zoom + this.panX) * scaleX) + 'px';
    ta.style.top = (canvasRect.top + (y * this.zoom + this.panY) * scaleY) + 'px';

    document.body.appendChild(ta);
    ta.focus();

    // Auto-resize
    ta.addEventListener('input', () => { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; });

    const finish = () => {
      const text = ta.value.trim();
      if (text) {
        const shape = new TextShape(x, y, text, fontSize);
        shape.fontFamily = fontFamily;
        shape.fontBold = document.getElementById('btnBold').classList.contains('active');
        shape.fontItalic = document.getElementById('btnItalic').classList.contains('active');
        this.applyStyleToShape(shape);
        this.lm.add(shape);
        this.saveState(); this.draw();
      }
      ta.remove();
    };
    ta.addEventListener('blur', finish);
    ta.addEventListener('keydown', ev => {
      if (ev.key === 'Escape') { ta.value = ''; ta.blur(); }
      // Shift+Enter = nouvelle ligne, Enter seul = valider
      if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); ta.blur(); }
    });
  }

  /** Edite un TextShape existant inline */
  _editTextInline(shape) {
    const ta = document.createElement('textarea');
    ta.className = 'canvas-text-input';
    ta.value = shape.text;
    const font = shape._getFont ? shape._getFont() : `${shape.fontSize}px '${shape.fontFamily || 'Segoe UI'}', sans-serif`;
    const textColor = shape.useFill ? shape.fillColor : shape.strokeColor;
    ta.style.cssText = `position:fixed;font:${font};font-size:${shape.fontSize * this.zoom}px;color:${textColor};background:rgba(255,255,255,0.9);border:2px solid #00d4ff;border-radius:4px;outline:none;padding:4px 6px;resize:none;overflow:hidden;min-width:100px;box-shadow:0 2px 12px rgba(0,0,0,0.3);z-index:1000;`;

    const canvasRect = this.canvas.getBoundingClientRect();
    const scaleX = canvasRect.width / this.canvas.width;
    const scaleY = canvasRect.height / this.canvas.height;
    ta.style.left = (canvasRect.left + (shape.x * this.zoom + this.panX) * scaleX) + 'px';
    ta.style.top = (canvasRect.top + (shape.y * this.zoom + this.panY) * scaleY) + 'px';

    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    ta.style.height = ta.scrollHeight + 'px';

    ta.addEventListener('input', () => { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; });

    let done = false;
    const finish = () => {
      if (done) return; done = true;
      const text = ta.value.trim();
      if (text) { shape.text = text; this.saveState(); }
      ta.remove();
      this.draw();
    };
    ta.addEventListener('blur', finish);
    ta.addEventListener('keydown', ev => {
      if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); ta.blur(); }
      if (ev.key === 'Escape') { done = true; ta.remove(); this.draw(); }
    });
  }

  /* ===========================================
     Auto-save
     =========================================== */

  _startAutoSave() {
    this._autoSaveInterval = setInterval(() => {
      const saves = this._getSaves();
      if (this.lm.layers.length === 0) return;
      // Ecraser le dernier slot auto-save ou en creer un
      const autoIdx = saves.findIndex(s => s.name && s.name.startsWith('[Auto] '));
      if (autoIdx >= 0) {
        this.saveToSlot(autoIdx, '[Auto] ' + this.projectName);
      } else {
        this.saveToSlot(-1, '[Auto] ' + this.projectName);
      }
    }, 120000); // 2 minutes
  }

  /* ===========================================
     Theme clair/sombre
     =========================================== */

  toggleTheme() {
    this.lightTheme = !this.lightTheme;
    document.body.classList.toggle('light-theme', this.lightTheme);
    this.setStatus(this.lightTheme ? 'Theme clair' : 'Theme sombre');
  }

  /* ===========================================
     Mode presentation (plein ecran)
     =========================================== */

  togglePresentation() {
    const wrapper = document.getElementById('canvas-wrapper');
    if (!document.fullscreenElement) {
      wrapper.requestFullscreen().catch(() => {});
      this.setStatus('Mode presentation');
    } else {
      document.exitFullscreen();
    }
  }

  /* ===========================================
     Palette couleurs recentes
     =========================================== */

  _addRecentColor(color) {
    if (!color || color === '#000000') return;
    this.recentColors = this.recentColors.filter(c => c !== color);
    this.recentColors.unshift(color);
    if (this.recentColors.length > 12) this.recentColors.pop();
    this._renderRecentColors();
  }

  _renderRecentColors() {
    const container = document.getElementById('recent-colors');
    if (!container) return;
    container.innerHTML = '';
    this.recentColors.forEach(c => {
      const swatch = document.createElement('div');
      swatch.className = 'recent-swatch';
      swatch.style.background = c;
      swatch.title = c;
      swatch.addEventListener('click', () => {
        document.getElementById('strokeColor').value = c;

        this.updateSelectedShape();
      });
      swatch.addEventListener('contextmenu', e => {
        e.preventDefault();
        document.getElementById('fillColor').value = c;

        document.getElementById('useFill').checked = true;
        this.updateSelectedShape();
      });
      container.appendChild(swatch);
    });
  }

  /* ===========================================
     Regles (rulers)
     =========================================== */

  /** Dessine les reperes personnalises */
  _drawGuides(ctx) {
    if (this.guides.length === 0) return;
    ctx.strokeStyle = '#e74c3c';
    ctx.lineWidth = 1;
    ctx.setLineDash([6, 4]);
    const w = this.canvas.width / this.zoom;
    const h = this.canvas.height / this.zoom;
    this.guides.forEach(g => {
      ctx.beginPath();
      if (g.axis === 'v') {
        ctx.moveTo(g.pos, -h); ctx.lineTo(g.pos, h * 2);
      } else {
        ctx.moveTo(-w, g.pos); ctx.lineTo(w * 2, g.pos);
      }
      ctx.stroke();
    });
    ctx.setLineDash([]);
  }

  /** Snap aux reperes + formes pendant le deplacement */
  _snapMove(x, y) {
    let sx = x, sy = y;
    const threshold = this.SNAP_THRESHOLD;

    // Snap aux reperes
    this.guides.forEach(g => {
      if (g.axis === 'v' && Math.abs(x - g.pos) < threshold) sx = g.pos;
      if (g.axis === 'h' && Math.abs(y - g.pos) < threshold) sy = g.pos;
    });

    // Snap aux formes
    const snap = this.lm.getSnapGuides(x, y, threshold, this.movingIndex);
    if (snap.snapX !== null) sx = snap.snapX;
    if (snap.snapY !== null) sy = snap.snapY;

    return { x: sx, y: sy };
  }

  /** Export PDF (via canvas -> image dans un document imprimable) */
  exportPDF() {
    // Dessiner le canvas propre (sans poignees)
    this.lm.select(null);
    this.draw();

    const dataUrl = this.canvas.toDataURL('image/png');
    const w = this.canvas.width;
    const h = this.canvas.height;

    const html = `<!DOCTYPE html><html><head><title>${this.projectName}</title><style>@page{size:${w}px ${h}px;margin:0}body{margin:0}img{width:100%;height:100%}</style></head><body><img src="${dataUrl}"></body></html>`;
    const win = window.open('', '_blank');
    win.document.write(html);
    win.document.close();
    win.onload = () => { win.print(); };
    this.setStatus('PDF : impression...');
  }

  drawRulers() {
    const step = this.gridSize;
    const gap = step * this.zoom;
    const majorEvery = 5; // graduation majeure tous les 5 pas
    const bgColor = this.lightTheme ? '#e0e0e0' : '#16213e';
    const fgColor = this.lightTheme ? '#333' : '#8892b0';

    // --- Regle horizontale ---
    const rH = document.getElementById('rulerH');
    if (!rH) return;
    const rHParent = rH.parentElement || rH.closest('#canvas-wrapper');
    rH.width = rHParent ? rHParent.clientWidth : this.canvas.width;
    const ctxH = rH.getContext('2d');
    ctxH.fillStyle = bgColor;
    ctxH.fillRect(0, 0, rH.width, 20);
    ctxH.fillStyle = fgColor;
    ctxH.font = '9px monospace';

    // Premier pas de grille visible (en pixels ecran)
    const offsetX = this.panX % gap;
    const startLogicalX = -this.panX / this.zoom;
    const startStepX = Math.floor(startLogicalX / step);

    for (let i = 0; ; i++) {
      const sx = offsetX + i * gap;
      if (sx > rH.width) break;
      if (sx < 0) continue;
      const logicalX = (startStepX + i) * step;
      if (Math.round(logicalX / step) % majorEvery === 0) {
        ctxH.fillRect(Math.round(sx), 12, 1, 8);
        ctxH.fillText(Math.round(logicalX), Math.round(sx) + 3, 10);
      } else {
        ctxH.fillRect(Math.round(sx), 15, 1, 5);
      }
    }

    // --- Regle verticale ---
    const rV = document.getElementById('rulerV');
    if (!rV) return;
    const rVParent = rV.parentElement || rV.closest('#canvas-wrapper');
    rV.height = rVParent ? rVParent.clientHeight : this.canvas.height;
    const ctxV = rV.getContext('2d');
    ctxV.fillStyle = bgColor;
    ctxV.fillRect(0, 0, 20, rV.height);
    ctxV.fillStyle = fgColor;
    ctxV.font = '9px monospace';

    const offsetY = this.panY % gap;
    const startLogicalY = -this.panY / this.zoom;
    const startStepY = Math.floor(startLogicalY / step);

    for (let i = 0; ; i++) {
      const sy = offsetY + i * gap;
      if (sy > rV.height) break;
      if (sy < 0) continue;
      const logicalY = (startStepY + i) * step;
      if (Math.round(logicalY / step) % majorEvery === 0) {
        ctxV.fillRect(12, Math.round(sy), 8, 1);
        ctxV.save();
        ctxV.translate(10, Math.round(sy) + 3);
        ctxV.rotate(-Math.PI / 2);
        ctxV.fillText(Math.round(logicalY), 0, 0);
        ctxV.restore();
      } else {
        ctxV.fillRect(15, Math.round(sy), 5, 1);
      }
    }
  }
}
