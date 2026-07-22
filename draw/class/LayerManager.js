/**
 * Draw - LayerManager
 *
 * Gere la liste des calques (formes), les groupes de calques,
 * la selection, le reordonnement, le drag-and-drop dans la sidebar,
 * et le rendu de l'interface des calques.
 *
 * Note : reference la variable globale `app` (CanvasApp) pour
 * declencher draw(), saveState(), copyShape(), deleteShape().
 *
 * Dependances : Shape, ShapeFactory (pour deserialize)
 */
class LayerManager {

  /**
   * @param {HTMLElement} listElem  - Conteneur DOM de la liste des calques (#layerList)
   * @param {HTMLElement} countElem - Element affichant le nombre de calques (#layerCount)
   */
  constructor(listElem, countElem) {
    this.layers = [];          // Tableau des formes (ordre = z-index)
    this.groups = [];          // Groupes : [{id, name, collapsed}]
    this.nextGroupId = 1;      // Compteur auto-increment pour les IDs de groupe
    this.selectedIndex = null;  // Index du calque selectionne (null = aucun)
    this._dragLayerIndex = null; // Index du calque en cours de drag
    this.listElem = listElem;
    this.countElem = countElem;

    /* --- Drag-drop : deposer sur la zone racine pour degrouper --- */
    this.listElem.addEventListener('dragover', e => {
      if (this._dragLayerIndex === null) return;
      if (e.target === this.listElem) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        this._clearDragStyles();
        this.listElem.classList.add('drag-over-root');
      }
    });
    this.listElem.addEventListener('dragleave', e => {
      this.listElem.classList.remove('drag-over-root');
    });
    this.listElem.addEventListener('drop', e => {
      if (e.target !== this.listElem) return;
      e.preventDefault();
      this._clearDragStyles();
      const fromIdx = this._dragLayerIndex;
      if (fromIdx === null) return;
      this._moveLayerToRoot(fromIdx);
    });
  }

  /* ===========================================
     Gestion des calques
     =========================================== */

  /**
   * Ajoute une forme a la liste.
   * Genere automatiquement un nom si la forme n'en a pas.
   */
  add(shape) {
    if (!shape.name) {
      const names = {
        circle: 'Cercle', rect: 'Rectangle', line: 'Ligne',
        ellipse: 'Ellipse', triangle: 'Triangle', arrow: 'Fleche',
        pencil: 'Crayon', text: 'Texte', image: 'Image', bezier: 'Bezier',
        polygon: 'Polygone', doublearrow: 'Double fleche', quadratic: 'Courbe'
      };
      const base = names[shape.type] || shape.type;
      const count = this.layers.filter(s => s.type === shape.type).length + 1;
      shape.name = base + ' ' + count;
    }
    this.layers.push(shape);
    this.selectedIndex = this.layers.length - 1;
    this.updateUI();
  }

  /** Supprime le calque a l'index i */
  remove(i) {
    if (i < 0 || i >= this.layers.length) return;
    this.layers.splice(i, 1);
    if (this.selectedIndex === i) this.selectedIndex = null;
    else if (this.selectedIndex > i) this.selectedIndex--;
    this._cleanEmptyGroups();
    this.updateUI();
  }

  /** Selectionne le calque a l'index i (null = deselectionner) */
  select(i) {
    this.selectedIndex = (i !== null && i >= 0 && i < this.layers.length) ? i : null;
    this.updateUI();
  }

  /** Retourne la forme selectionnee, ou null */
  getSelected() {
    return this.selectedIndex !== null ? this.layers[this.selectedIndex] : null;
  }

  /** Monte le calque d'un cran (z-index +1) */
  moveUp(i) {
    if (i < this.layers.length - 1) {
      [this.layers[i], this.layers[i + 1]] = [this.layers[i + 1], this.layers[i]];
      if (this.selectedIndex === i) this.selectedIndex = i + 1;
      else if (this.selectedIndex === i + 1) this.selectedIndex = i;
      this.updateUI();
    }
  }

  /** Descend le calque d'un cran (z-index -1) */
  moveDown(i) {
    if (i > 0) {
      [this.layers[i], this.layers[i - 1]] = [this.layers[i - 1], this.layers[i]];
      if (this.selectedIndex === i) this.selectedIndex = i - 1;
      else if (this.selectedIndex === i - 1) this.selectedIndex = i;
      this.updateUI();
    }
  }

  /** Efface tous les calques et groupes */
  clear() {
    this.layers = [];
    this.groups = [];
    this.nextGroupId = 1;
    this.selectedIndex = null;
    this.updateUI();
  }

  /* ===========================================
     Gestion des groupes
     =========================================== */

  /** Cree un nouveau groupe et retourne son ID */
  addGroup(name) {
    const id = this.nextGroupId++;
    this.groups.push({ id, name: name || 'Groupe ' + id, collapsed: false });
    this.updateUI();
    return id;
  }

  /** Supprime un groupe (les calques sont liberes, pas supprimes) */
  removeGroup(groupId) {
    this.layers.forEach(s => { if (s.groupId === groupId) s.groupId = null; });
    this.groups = this.groups.filter(g => g.id !== groupId);
    this.updateUI();
  }

  /** Renomme un groupe */
  renameGroup(groupId, name) {
    const g = this.groups.find(g => g.id === groupId);
    if (g) { g.name = name; this.updateUI(); }
  }

  /** Replie ou deplie un groupe */
  toggleGroup(groupId) {
    const g = this.groups.find(g => g.id === groupId);
    if (g) { g.collapsed = !g.collapsed; this.updateUI(); }
  }

  /** Assigne un calque a un groupe */
  assignToGroup(layerIndex, groupId) {
    if (layerIndex >= 0 && layerIndex < this.layers.length) {
      this.layers[layerIndex].groupId = groupId;
      this.updateUI();
    }
  }

  /** Retire un calque de son groupe */
  removeFromGroup(layerIndex) {
    if (layerIndex >= 0 && layerIndex < this.layers.length) {
      this.layers[layerIndex].groupId = null;
      this._cleanEmptyGroups();
      this.updateUI();
    }
  }

  /** Les groupes vides sont conserves (crees intentionnellement par l'utilisateur) */
  _cleanEmptyGroups() {}

  /* ===========================================
     Rendu canvas
     =========================================== */

  /** Dessine toutes les formes visibles sur le contexte canvas */
  drawAll(ctx) {
    this.layers.forEach((s, i) => {
      if (s.visible !== false) s.draw(ctx, i === this.selectedIndex);
    });
  }

  /** Envoie un calque tout en haut (premier plan) */
  bringToFront(i) {
    if (i < 0 || i >= this.layers.length) return;
    const s = this.layers.splice(i, 1)[0];
    this.layers.push(s);
    this.selectedIndex = this.layers.length - 1;
    this.updateUI();
  }

  /** Envoie un calque tout en bas (arriere-plan) */
  sendToBack(i) {
    if (i < 0 || i >= this.layers.length) return;
    const s = this.layers.splice(i, 1)[0];
    this.layers.unshift(s);
    this.selectedIndex = 0;
    this.updateUI();
  }

  /**
   * Trouve la forme la plus haute (z-index) au point (x,y).
   * Parcourt du dernier au premier (haut vers bas visuellement).
   * @returns {number} Index de la forme, ou -1 si aucune
   */
  findAt(x, y) {
    for (let i = this.layers.length - 1; i >= 0; i--) {
      const s = this.layers[i];
      if (s.visible !== false && !s.locked && s.hitTest(x, y)) return i;
    }
    return -1;
  }

  /**
   * Calcule les guides d'alignement (snap) pour la position (x,y).
   * Retourne les coordonnees X et Y les plus proches des bords/centres
   * des autres formes.
   */
  getSnapGuides(x, y, dist = 10, excludeIndex = -1) {
    let snapX = null, snapY = null;
    this.layers.forEach((s, i) => {
      if (i === excludeIndex) return;
      const b = s.getBounds();
      const cx = b.x + b.w / 2, cy = b.y + b.h / 2;
      // Tester les bords gauche, centre, droit
      [b.x, cx, b.x + b.w].forEach(v => { if (Math.abs(x - v) <= dist) snapX = v; });
      // Tester les bords haut, centre, bas
      [b.y, cy, b.y + b.h].forEach(v => { if (Math.abs(y - v) <= dist) snapY = v; });
    });
    return { snapX, snapY };
  }

  /* ===========================================
     Construction de l'UI sidebar
     =========================================== */

  /**
   * Construit l'element DOM pour un calque individuel.
   * Inclut : pastille couleur, nom (double-clic = renommer),
   * boutons d'action, drag-and-drop, assignation aux groupes.
   */
  _buildLayerDiv(s, i) {
    const div = document.createElement('div');
    div.className = 'layer' + (i === this.selectedIndex ? ' selected' : '');
    div.draggable = true;
    div.dataset.layerIndex = i;

    // Boutons de groupe (assigner ou retirer)
    const groupBtns = this.groups.length > 0
      ? (s.groupId
        ? `<button class="act-grp" title="Retirer du groupe">&#10550;</button>`
        : this.groups.map(g => `<button class="act-grp" data-gid="${g.id}" title="Ajouter a ${g.name}">&#128193;</button>`).join(''))
      : '';

    const visIcon = s.visible !== false ? '&#128065;' : '&#128064;';
    const lockIcon = s.locked ? '&#128274;' : '&#128275;';
    const dimClass = s.visible === false ? ' layer-hidden' : '';
    const lockClass = s.locked ? ' layer-locked' : '';

    div.innerHTML = `
      <button class="act-vis" title="Visibilite">${visIcon}</button>
      <button class="act-lock" title="Verrouiller">${lockIcon}</button>
      <span class="color-dot" style="background:${s.strokeColor}"></span>
      <span class="layer-name${dimClass}${lockClass}">${s.name || s.type}</span>
      <span class="layer-actions">
        ${groupBtns}
        <button class="act-front" title="Premier plan">&#8648;</button>
        <button class="act-back" title="Arriere-plan">&#8650;</button>
        <button class="act-up" title="Monter">&#9650;</button>
        <button class="act-down" title="Descendre">&#9660;</button>
        <button class="act-copy" title="Copier">&#9112;</button>
        <button class="act-del" title="Supprimer">&#10005;</button>
      </span>`;

    // Clic = selectionner le calque et charger son style
    div.addEventListener('click', () => { this.select(i); app.loadStyleFromShape(s); app.draw(); });

    /* --- Drag-and-drop du calque --- */
    div.addEventListener('dragstart', e => {
      e.stopPropagation();
      this._dragLayerIndex = i;
      div.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', i.toString());
    });
    div.addEventListener('dragend', e => {
      div.classList.remove('dragging');
      this._clearDragStyles();
      this._dragLayerIndex = null;
    });
    div.addEventListener('dragover', e => {
      e.preventDefault();
      e.stopPropagation();
      if (this._dragLayerIndex === null || this._dragLayerIndex === i) return;
      e.dataTransfer.dropEffect = 'move';
      this._clearDragStyles();
      // Indicateur visuel haut/bas selon la position de la souris
      const rect = div.getBoundingClientRect();
      const mid = rect.top + rect.height / 2;
      if (e.clientY < mid) {
        div.classList.add('drag-over-top');
      } else {
        div.classList.add('drag-over-bottom');
      }
    });
    div.addEventListener('dragleave', e => {
      div.classList.remove('drag-over-top', 'drag-over-bottom');
    });
    div.addEventListener('drop', e => {
      e.preventDefault();
      e.stopPropagation();
      this._clearDragStyles();
      const fromIdx = this._dragLayerIndex;
      if (fromIdx === null || fromIdx === i) return;
      const rect = div.getBoundingClientRect();
      const mid = rect.top + rect.height / 2;
      const targetGroupId = s.groupId;
      this._moveLayer(fromIdx, i, e.clientY < mid, targetGroupId);
    });

    /* --- Renommage par double-clic --- */
    const nameSpan = div.querySelector('.layer-name');
    nameSpan.addEventListener('dblclick', e => {
      e.stopPropagation();
      const input = document.createElement('input');
      input.type = 'text'; input.value = s.name;
      input.style.cssText = 'width:100%;background:#0d0d1a;color:#fff;border:1px solid #00d4ff;border-radius:3px;padding:1px 4px;font-size:12px;outline:none;';
      nameSpan.replaceWith(input);
      input.focus(); input.select();
      let done = false;
      const finish = () => {
        if (done) return; done = true;
        s.name = input.value.trim() || s.name;
        this.updateUI();
      };
      input.addEventListener('blur', finish);
      input.addEventListener('keydown', ev => {
        if (ev.key === 'Enter') input.blur();
        if (ev.key === 'Escape') { input.value = s.name; input.blur(); }
      });
    });

    /* --- Boutons de groupe --- */
    if (s.groupId) {
      const ungrpBtn = div.querySelector('.act-grp');
      if (ungrpBtn) ungrpBtn.addEventListener('click', e => { e.stopPropagation(); this.removeFromGroup(i); app.saveState(); });
    } else {
      div.querySelectorAll('.act-grp[data-gid]').forEach(btn => {
        btn.addEventListener('click', e => {
          e.stopPropagation();
          this.assignToGroup(i, parseInt(btn.dataset.gid));
          app.saveState();
        });
      });
    }

    /* --- Boutons visibilite / verrouillage --- */
    div.querySelector('.act-vis').addEventListener('click', e => {
      e.stopPropagation();
      s.visible = s.visible === false ? true : false;
      this.updateUI(); app.draw();
    });
    div.querySelector('.act-lock').addEventListener('click', e => {
      e.stopPropagation();
      s.locked = !s.locked;
      this.updateUI(); app.draw();
    });

    /* --- Boutons z-order / monter/descendre/copier/supprimer --- */
    div.querySelector('.act-front').addEventListener('click', e => { e.stopPropagation(); this.bringToFront(i); app.saveState(); app.draw(); });
    div.querySelector('.act-back').addEventListener('click', e => { e.stopPropagation(); this.sendToBack(i); app.saveState(); app.draw(); });
    div.querySelector('.act-up').addEventListener('click', e => { e.stopPropagation(); this.moveUp(i); app.draw(); });
    div.querySelector('.act-down').addEventListener('click', e => { e.stopPropagation(); this.moveDown(i); app.draw(); });
    div.querySelector('.act-copy').addEventListener('click', e => { e.stopPropagation(); app.copyShape(i); });
    div.querySelector('.act-del').addEventListener('click', e => { e.stopPropagation(); app.deleteShape(i); });

    return div;
  }

  /* ===========================================
     Drag-and-drop helpers
     =========================================== */

  /** Supprime tous les indicateurs visuels de drag */
  _clearDragStyles() {
    this.listElem.querySelectorAll('.drag-over-top, .drag-over-bottom, .drag-over').forEach(el => {
      el.classList.remove('drag-over-top', 'drag-over-bottom', 'drag-over');
    });
    this.listElem.classList.remove('drag-over-root');
  }

  /** Deplace un calque dans le tableau et l'assigne a un groupe */
  _moveLayer(fromIdx, toIdx, before, targetGroupId) {
    const shape = this.layers[fromIdx];
    shape.groupId = targetGroupId;
    this.layers.splice(fromIdx, 1);
    let insertIdx = toIdx;
    if (fromIdx < toIdx) insertIdx--;
    if (!before) insertIdx++;
    insertIdx = Math.max(0, Math.min(insertIdx, this.layers.length));
    this.layers.splice(insertIdx, 0, shape);
    this.selectedIndex = insertIdx;
    app.saveState();
    this.updateUI();
    app.draw();
  }

  /** Deplace un calque dans un groupe */
  _moveLayerToGroup(fromIdx, groupId) {
    this.layers[fromIdx].groupId = groupId;
    this.selectedIndex = fromIdx;
    app.saveState();
    this.updateUI();
    app.draw();
  }

  /** Retire un calque de son groupe (retour a la racine) */
  _moveLayerToRoot(fromIdx) {
    this.layers[fromIdx].groupId = null;
    this.selectedIndex = fromIdx;
    app.saveState();
    this.updateUI();
    app.draw();
  }

  /* ===========================================
     Mise a jour de l'interface sidebar
     =========================================== */

  /** Regenere entierement le HTML de la liste des calques */
  updateUI() {
    this.countElem.textContent = this.layers.length;
    this.listElem.innerHTML = '';

    /* --- Rendu des groupes --- */
    this.groups.forEach(g => {
      // Trouver les calques de ce groupe
      const groupLayers = [];
      this.layers.forEach((s, i) => { if (s.groupId === g.id) groupLayers.push(i); });

      const wrapper = document.createElement('div');
      wrapper.className = 'layer-group';

      // En-tete du groupe
      const header = document.createElement('div');
      header.className = 'layer-group-header';
      header.innerHTML = `
        <span class="group-toggle ${g.collapsed ? 'collapsed' : ''}">&#9660;</span>
        <span class="group-icon">&#128193;</span>
        <span class="group-name">${g.name}</span>
        <span class="group-count">(${groupLayers.length})</span>
        <span class="group-actions">
          <button class="act-del" title="Supprimer le groupe">&#10005;</button>
        </span>`;

      // Toggle replier/deplier
      header.querySelector('.group-toggle').addEventListener('click', e => { e.stopPropagation(); this.toggleGroup(g.id); });
      header.querySelector('.group-icon').addEventListener('click', e => { e.stopPropagation(); this.toggleGroup(g.id); });

      // Renommage du groupe par double-clic
      const gNameSpan = header.querySelector('.group-name');
      gNameSpan.addEventListener('click', e => { e.stopPropagation(); });
      gNameSpan.addEventListener('dblclick', e => {
        e.stopPropagation();
        const input = document.createElement('input');
        input.type = 'text'; input.value = g.name;
        input.style.cssText = 'width:100%;background:#0d0d1a;color:#fff;border:1px solid #00d4ff;border-radius:3px;padding:1px 4px;font-size:12px;outline:none;';
        gNameSpan.replaceWith(input);
        input.focus(); input.select();
        let done = false;
        const finish = () => {
          if (done) return; done = true;
          this.renameGroup(g.id, input.value.trim() || g.name);
        };
        input.addEventListener('blur', finish);
        input.addEventListener('keydown', ev => {
          if (ev.key === 'Enter') input.blur();
          if (ev.key === 'Escape') { input.value = g.name; input.blur(); }
        });
      });

      // Suppression du groupe
      header.querySelector('.act-del').addEventListener('click', e => {
        e.stopPropagation();
        this.removeGroup(g.id);
        app.saveState();
      });

      // Drop sur l'en-tete = ajouter au groupe
      header.addEventListener('dragover', e => {
        e.preventDefault(); e.stopPropagation();
        if (this._dragLayerIndex === null) return;
        e.dataTransfer.dropEffect = 'move';
        this._clearDragStyles();
        header.classList.add('drag-over');
      });
      header.addEventListener('dragleave', e => { header.classList.remove('drag-over'); });
      header.addEventListener('drop', e => {
        e.preventDefault(); e.stopPropagation();
        this._clearDragStyles();
        const fromIdx = this._dragLayerIndex;
        if (fromIdx === null) return;
        this._moveLayerToGroup(fromIdx, g.id);
      });

      wrapper.appendChild(header);

      // Zone enfants du groupe
      const children = document.createElement('div');
      children.className = 'layer-group-children' + (g.collapsed ? ' collapsed' : '');
      groupLayers.forEach(i => children.appendChild(this._buildLayerDiv(this.layers[i], i)));

      // Drop sur la zone enfants vide
      children.addEventListener('dragover', e => {
        e.preventDefault(); e.stopPropagation();
        if (this._dragLayerIndex === null) return;
        e.dataTransfer.dropEffect = 'move';
        if (e.target === children) {
          this._clearDragStyles();
          children.classList.add('drag-over');
        }
      });
      children.addEventListener('dragleave', e => { children.classList.remove('drag-over'); });
      children.addEventListener('drop', e => {
        e.preventDefault(); e.stopPropagation();
        this._clearDragStyles();
        const fromIdx = this._dragLayerIndex;
        if (fromIdx === null) return;
        this._moveLayerToGroup(fromIdx, g.id);
      });

      wrapper.appendChild(children);
      this.listElem.appendChild(wrapper);
    });

    /* --- Rendu des calques non groupes --- */
    this.layers.forEach((s, i) => {
      if (s.groupId) return; // Deja rendu dans un groupe
      this.listElem.appendChild(this._buildLayerDiv(s, i));
    });
  }

  /* ===========================================
     Serialisation / Deserialisation
     =========================================== */

  /** Exporte l'etat complet (calques + groupes) en objet JSON */
  serialize() {
    return {
      layers: this.layers.map(s => s.serialize()),
      groups: this.groups,
      nextGroupId: this.nextGroupId
    };
  }

  /**
   * Restaure l'etat depuis des donnees JSON.
   * Compatible avec l'ancien format (tableau simple) et le nouveau (objet avec groupes).
   */
  deserialize(data) {
    if (Array.isArray(data)) {
      // Ancien format : juste un tableau de formes
      this.layers = data.map(d => Shape.deserialize(d)).filter(Boolean);
      this.groups = [];
      this.nextGroupId = 1;
    } else {
      // Nouveau format : objet avec layers, groups, nextGroupId
      this.layers = (data.layers || []).map(d => Shape.deserialize(d)).filter(Boolean);
      this.groups = data.groups || [];
      this.nextGroupId = data.nextGroupId || 1;
    }
    this.selectedIndex = null;
    this.updateUI();
  }
}
