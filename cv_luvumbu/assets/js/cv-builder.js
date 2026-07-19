/* =====================================================================
   CV Builder — moteur WYSIWYG porté depuis cv_enligne.
   L'aperçu en direct est STRICTEMENT le rendu final (= ce qui s'imprime).
   Persistance : window.__CV_PROFILE__ (injecté par PHP) + POST vers
   window.__CV_SAVE_URL__ (avec window.__CV_CSRF__).
   ===================================================================== */
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const esc = (s) => (s ?? '').toString()
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  const toast = (msg) => {
    const t = $('toast');
    if (!t) return;
    t.textContent = msg; t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2200);
  };

    const MONTH_NAMES = ['janvier','février','mars','avril','mai','juin',
                         'juillet','août','septembre','octobre','novembre','décembre'];
    function normalizeMonth(v) {
      if (!v) return '';
      const s = String(v).trim();
      if (/^\d{4}-\d{2}$/.test(s)) return s;
      if (/^\d{4}$/.test(s)) return s + '-01';
      return '';
    }
    function formatItemDate(monthStr, format) {
      if (!monthStr) return '';
      const m = /^(\d{4})-(\d{2})$/.exec(monthStr);
      if (!m) return monthStr;
      if (format === 'year') return m[1];
      const idx = parseInt(m[2], 10) - 1;
      if (idx < 0 || idx > 11) return m[1];
      return `${MONTH_NAMES[idx]} ${m[1]}`;
    }
    function renderItemDates(f, profile) {
      // Priorité : format propre à l'item > format global du profil > 'full'
      const fmt = f.dateFormat || profile.dateFormat || 'full';
      const s = formatItemDate(f.start, fmt);
      const e = formatItemDate(f.end,   fmt);
      if (!s && !e) return '';
      return `<div class="f-dates">${esc(s)}${s && e ? ' — ' : ''}${esc(e)}</div>`;
    }

    function renderItemDescriptions(f) {
      const list = (f.descriptions || []).filter(d => !d.hidden && (d.text || '').trim());
      if (!list.length) return '';
      return `<ul class="item-desc">${list.map(d => `<li>${esc(d.text)}</li>`).join('')}</ul>`;
    }

    const DATE_FORMAT_FIELD = {
      key: 'dateFormat', flex: '120px', type: 'select',
      options: [
        { value: '',     label: 'Par défaut' },
        { value: 'full', label: 'Mois + année' },
        { value: 'year', label: 'Année seule' },
      ],
    };

    const SECTION_DEFS = {
      formation: {
        label: 'Formation',
        addLabel: '+ Ajouter une formation',
        fields: [
          { key: 'name',  placeholder: 'Nom de la formation',  flex: '1fr'   },
          { key: 'level', placeholder: 'Niveau (Bac, BTS…)',   flex: '130px' },
          { key: 'start', placeholder: 'Début',                flex: '140px', type: 'month' },
          { key: 'end',   placeholder: 'Fin',                  flex: '140px', type: 'month' },
          DATE_FORMAT_FIELD,
        ],
        renderItem: (f, profile) => `
          <li>
            <div class="f-head">
              <strong>${esc(f.name || '')}</strong>
              ${f.level ? `<span class="f-tag">${esc(f.level)}</span>` : ''}
            </div>
            ${renderItemDates(f, profile)}
            ${renderItemDescriptions(f)}
          </li>`,
      },
      experience: {
        label: 'Expérience professionnelle',
        addLabel: '+ Ajouter une expérience',
        fields: [
          { key: 'role',    placeholder: 'Poste (Développeur…)', flex: '1fr'   },
          { key: 'company', placeholder: 'Entreprise',           flex: '1fr'   },
          { key: 'start',   placeholder: 'Début',                flex: '140px', type: 'month' },
          { key: 'end',     placeholder: 'Fin (vide = en cours)', flex: '140px', type: 'month' },
          DATE_FORMAT_FIELD,
        ],
        renderItem: (f, profile) => `
          <li>
            <div class="f-head">
              <strong>${esc(f.role || '')}</strong>
              ${f.company ? `<span class="f-tag">${esc(f.company)}</span>` : ''}
            </div>
            ${renderItemDates(f, profile)}
            ${renderItemDescriptions(f)}
          </li>`,
      },
      langues: {
        label: 'Langues',
        addLabel: '+ Ajouter une langue',
        fields: [
          { key: 'name',  placeholder: 'Langue (Anglais, Espagnol…)', flex: '1fr',
            suggest: ['Anglais', 'Espagnol', 'Allemand', 'Italien', 'Portugais', 'Néerlandais',
                      'Russe', 'Arabe', 'Chinois (mandarin)', 'Japonais', 'Coréen', 'Hindi',
                      'Turc', 'Polonais', 'Suédois', 'Grec', 'Hébreu', 'Latin',
                      'Langue des signes française (LSF)'] },
          { key: 'level', placeholder: 'Niveau (B2, Courant, Bilingue…)', flex: '1fr',
            suggest: ['Notions', 'Scolaire', 'Intermédiaire', 'Courant', 'Bilingue', 'Langue maternelle',
                      'A1', 'A2', 'B1', 'B2', 'C1', 'C2'] },
        ],
        renderItem: (f) => `
          <li>
            <div class="f-head">
              <strong>${esc(f.name || '')}</strong>
              ${f.level ? `<span class="f-tag">${esc(f.level)}</span>` : ''}
            </div>
            ${renderItemDescriptions(f)}
          </li>`,
      },
      loisirs: {
        label: 'Loisirs',
        addLabel: '+ Ajouter un loisir',
        fields: [
          { key: 'name', placeholder: 'Loisir (Photographie, Course à pied…)', flex: '1fr',
            suggest: ['Lecture', 'Cinéma', 'Musique', 'Photographie', 'Voyages', 'Cuisine',
                      'Course à pied', 'Natation', 'Vélo / Cyclisme', 'Randonnée', 'Yoga', 'Fitness',
                      'Football', 'Basketball', 'Tennis', 'Escalade', 'Ski', 'Surf',
                      'Arts martiaux', 'Danse', 'Théâtre', 'Chant', 'Guitare', 'Piano',
                      'Dessin', 'Peinture', 'Écriture', 'Bricolage', 'Jardinage',
                      'Jeux vidéo', 'Jeux de société', 'Échecs', 'Bénévolat associatif',
                      'Méditation', 'Mécanique', 'Programmation (projets perso)'] },
        ],
        renderItem: (f) => `
          <li>
            <div class="f-head">
              <strong>${esc(f.name || '')}</strong>
            </div>
            ${renderItemDescriptions(f)}
          </li>`,
      },
      habilitations: {
        label: 'Habilitations',
        addLabel: '+ Ajouter une habilitation',
        fields: [
          { key: 'name', placeholder: 'Habilitation (B1, B2, BR, H0…)', flex: '1fr',
            suggest: ['B0', 'B1', 'B1V', 'B2', 'B2V', 'BR', 'BC', 'BE', 'H0', 'H1', 'H2', 'HC',
                      'CACES', 'AIPR', 'SST', 'Travail en hauteur'] },
        ],
        renderItem: (f) => `<li><div class="f-head"><strong>${esc(f.name || '')}</strong></div></li>`,
      },
      competences: {
        label: 'Compétences',
        addLabel: '+ Ajouter une compétence',
        fields: [
          { key: 'title', placeholder: 'Compétence (Tableaux & armoires…)', flex: '1fr' },
          { key: 'desc',  placeholder: 'Détail (fabrication & câblage de TGBT…)', flex: '1fr' },
        ],
        renderItem: (f) => `<li><div class="f-head"><strong>${esc(f.title || '')}</strong></div>${f.desc ? `<div class="f-dates">${esc(f.desc)}</div>` : ''}</li>`,
      },
      logiciels: {
        label: 'Logiciels',
        addLabel: '+ Ajouter un logiciel',
        fields: [
          { key: 'name', placeholder: 'Logiciel (AutoCAD, Caneco BT…)', flex: '1fr',
            suggest: ['AutoCAD', 'Caneco BT', 'See Electrical', 'SolidWorks', 'EPLAN',
                      'Revit', 'DIALux', 'Excel', 'Word', 'Pack Office'] },
        ],
        renderItem: (f) => `<li><div class="f-head"><strong>${esc(f.name || '')}</strong></div></li>`,
      },
    };

    // Titre affiché d'une section : titre personnalisé de l'utilisateur si présent,
    // sinon libellé par défaut du type de section. Permet de renommer chaque section.
    function sectionTitle(section) {
      const def = SECTION_DEFS[section.type] || {};
      const custom = (section && typeof section.title === 'string') ? section.title.trim() : '';
      return custom || def.label || '';
    }

    const COLOR_DEFAULT = { main: '#7c6cf7', secondary: '#a99ffe' };
    // Overrides optionnels appliqués globalement à tous les modèles via !important.
    // Chaque entrée : clé interne, libellé UI, couleur initiale du picker (non appliquée tant que la case n'est pas cochée).
    const COLOR_EXTRAS = [
      { key: 'text',       label: '✏️ Texte',     fallback: '#1e293b' },
      { key: 'background', label: '🎨 Fond',      fallback: '#ffffff' },
      { key: 'sidebar',    label: '📊 Sidebar',   fallback: '#7c6cf7' },
      { key: 'title',      label: '🏷️ Titres',    fallback: '#7c6cf7' },
      { key: 'border',     label: '➖ Bordures', fallback: '#a99ffe' },
      { key: 'badge',      label: '🔖 Badges',    fallback: '#7c6cf7' },
    ];
    const COLOR_PRESETS = [
      { name: 'Violet',   main: '#7c6cf7', secondary: '#a99ffe' },
      { name: 'Bleu',     main: '#2563eb', secondary: '#93c5fd' },
      { name: 'Vert',     main: '#10b981', secondary: '#6ee7b7' },
      { name: 'Rouge',    main: '#dc2626', secondary: '#fca5a5' },
      { name: 'Orange',   main: '#ea580c', secondary: '#fdba74' },
      { name: 'Rose',     main: '#db2777', secondary: '#f9a8d4' },
      { name: 'Sobre',    main: '#475569', secondary: '#94a3b8' },
      { name: 'Noir',     main: '#111827', secondary: '#6b7280' },
      { name: 'Cyberpunk',main: '#00f0ff', secondary: '#ff00aa' },
      { name: 'Néon',     main: '#39ff14', secondary: '#ff2bd6' },
      { name: 'Vogue',    main: '#e30613', secondary: '#000000' },
      { name: 'Vogue Rose',main: '#ff3d7f', secondary: '#000000' },
      { name: 'Mode',     main: '#1a1a1a', secondary: '#d4a373' },
    ];

    function defaultProfile() {
      // Apparence par défaut : modèle dc2scale, bleu marine + or (style du CV de référence).
      return {
        firstName: '', lastName: '', headline: '', summary: '',
        contact: { location: '', phone: '', email: '', website: '', permis: '' },
        birthDate: '', birthDisplay: 'none',
        photo: null, photoHidden: false, photoSize: 120, photoPosition: 'left', photoShape: 'circle',
        template: 'dc2scale',
        singlePage: true,
        freeLayout: false,
        canvasBlocks: [],
        colors: { main: '#1d2b4d', secondary: '#d4a23c' },
        dateFormat: 'year',
        fontScale: 100,
        sections: [
          { type: 'experience',    hidden: false, items: [{}] },
          { type: 'formation',     hidden: false, items: [{}] },
          { type: 'habilitations', hidden: false, items: [{}] },
          { type: 'competences',   hidden: false, items: [{}] },
          { type: 'logiciels',     hidden: false, items: [{}] },
          { type: 'langues',       hidden: false, items: [{}] },
        ],
      };
    }
    function loadProfile() {
      let p = null;
      try {
        p = window.__CV_PROFILE__ ? JSON.parse(JSON.stringify(window.__CV_PROFILE__)) : null;
      } catch (_) {}
      if (!p || !Object.keys(p).length) return defaultProfile();
      // Migration depuis l'ancien format { formations: [...] }
      if (!p.sections) {
        p.sections = [
          { type: 'formation',  hidden: false, items: p.formations || [{}] },
          { type: 'experience', hidden: false, items: [{}] },
        ];
        delete p.formations;
      }
      // Ajout des sections langues/loisirs pour les profils antérieurs
      if (!p.sections.some(s => s.type === 'langues')) {
        p.sections.push({ type: 'langues', hidden: false, items: [{}] });
      }
      if (!p.sections.some(s => s.type === 'loisirs')) {
        p.sections.push({ type: 'loisirs', hidden: false, items: [{}] });
      }
      // Champs d'en-tête étendus (titre/poste, résumé, coordonnées) — optionnels.
      if (typeof p.headline !== 'string') p.headline = '';
      if (typeof p.summary  !== 'string') p.summary  = '';
      if (!p.contact || typeof p.contact !== 'object') p.contact = {};
      ['location', 'phone', 'email', 'website', 'permis'].forEach(k => {
        if (typeof p.contact[k] !== 'string') p.contact[k] = '';
      });
      // Coordonnées dynamiques : migre les anciens champs typés vers une liste { icon, text }.
      if (!Array.isArray(p.contact.items)) {
        p.contact.items = [
          ['📍', p.contact.location], ['📞', p.contact.phone], ['✉️', p.contact.email],
          ['🌐', p.contact.website],  ['🚗', p.contact.permis],
        ].filter(pair => (pair[1] || '').trim())
         .map(pair => ({ icon: pair[0], text: String(pair[1]).trim() }));
      }
      if (!p.colors) p.colors = { ...COLOR_DEFAULT };
      if (typeof p.colors.main      !== 'string') p.colors.main      = COLOR_DEFAULT.main;
      if (typeof p.colors.secondary !== 'string') p.colors.secondary = COLOR_DEFAULT.secondary;
      // Les overrides avancés (text, background, sidebar, title, border, badge) restent optionnels :
      // ils ne sont présents dans p.colors que s'ils ont été explicitement activés.
      if (!p.dateFormat) p.dateFormat = 'full';
      if (typeof p.fontScale !== 'number') p.fontScale = 100;
      if (typeof p.photoSize !== 'number') p.photoSize = 100;
      if (p.photoPosition !== 'left' && p.photoPosition !== 'right') p.photoPosition = 'right';
      if (!['circle','rounded','square','portrait','hexagon'].includes(p.photoShape)) p.photoShape = 'circle';
      if (!p.template || !CV_TEMPLATES[p.template]) p.template = 'classique';
      if (typeof p.singlePage !== 'boolean') p.singlePage = false;
      if (typeof p.freeLayout !== 'boolean') p.freeLayout = false;
      if (!Array.isArray(p.canvasBlocks)) p.canvasBlocks = [];
      // Normalisation des dates + migration descriptions
      p.sections.forEach(sec => {
        if (typeof sec.fontScale !== 'number') sec.fontScale = 100;
        if (typeof sec.title !== 'string') sec.title = '';
        sec.items.forEach(it => {
          if ('start' in it) it.start = normalizeMonth(it.start);
          if ('end'   in it) it.end   = normalizeMonth(it.end);
          if (!Array.isArray(it.descriptions)) it.descriptions = [];
        });
      });
      return p;
    }
    function saveProfileData(p) {
      // Source de vérité côté client + persistance serveur (DB).
      window.__CV_PROFILE__ = p;
      if (!window.__CV_SAVE_URL__) return;
      fetch(window.__CV_SAVE_URL__, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: window.__CV_CSRF__ || '', profile: p }),
      })
        .then((r) => r.json().catch(() => ({})))
        .then((j) => {
          if (!j || !j.ok) {
            toast('Échec de l\'enregistrement' + (j && j.error ? ' : ' + j.error : ''));
          }
        })
        .catch(() => toast('Échec de l\'enregistrement (réseau)'));
    }

    // Déplace un élément d'un tableau de l'index `from` vers l'index `to`.
    function moveArrayItem(arr, from, to) {
      if (from === to || from < 0 || to < 0 || from >= arr.length || to >= arr.length) return;
      const [el] = arr.splice(from, 1);
      arr.splice(to, 0, el);
    }

    // Active le glisser-déposer libre sur les enfants directs de `container`.
    // La poignée `.drag-handle` déclenche le drag ; `onReorder(from, to)` applique le déplacement.
    function setupDragSort(container, itemSelector, onReorder) {
      const items = Array.from(container.children).filter(el => el.matches(itemSelector));
      let dragIdx = null;
      const clearMarks = () => items.forEach(el =>
        el.classList.remove('drag-over-top', 'drag-over-bottom'));

      items.forEach((item, idx) => {
        const handle = item.querySelector('.drag-handle');
        if (!handle) return;

        // Le drag n'est autorisé que lorsqu'on saisit la poignée.
        handle.addEventListener('mousedown', () => {
          item.draggable = true;
          const reset = () => { item.draggable = false; document.removeEventListener('mouseup', reset); };
          document.addEventListener('mouseup', reset);
        });

        item.addEventListener('dragstart', (e) => {
          dragIdx = idx;
          item.classList.add('dragging');
          e.dataTransfer.effectAllowed = 'move';
          try { e.dataTransfer.setData('text/plain', String(idx)); } catch (_) {}
        });

        item.addEventListener('dragend', () => {
          item.draggable = false;
          item.classList.remove('dragging');
          clearMarks();
          dragIdx = null;
        });

        item.addEventListener('dragover', (e) => {
          if (dragIdx === null || dragIdx === idx) return;
          e.preventDefault();
          e.dataTransfer.dropEffect = 'move';
          const rect = item.getBoundingClientRect();
          const after = (e.clientY - rect.top) > rect.height / 2;
          clearMarks();
          item.classList.add(after ? 'drag-over-bottom' : 'drag-over-top');
        });

        item.addEventListener('drop', (e) => {
          if (dragIdx === null || dragIdx === idx) return;
          e.preventDefault();
          const rect = item.getBoundingClientRect();
          const after = (e.clientY - rect.top) > rect.height / 2;
          let to = idx + (after ? 1 : 0);
          if (dragIdx < to) to--;
          const from = dragIdx;
          dragIdx = null;
          clearMarks();
          if (to !== from) onReorder(from, to);
        });
      });
    }

    function renderProfileRow(section, item, idx, total) {
      const def = SECTION_DEFS[section.type];
      const fieldsGrid = def.fields.map(f => f.flex).join(' ');
      const row = document.createElement('div');
      row.className = 'profile-row' + (item.hidden ? ' hidden-item' : '');
      row.innerHTML = `
        <input type="checkbox" class="row-include" title="Afficher dans le CV" ${item.hidden ? '' : 'checked'}>
        <div class="fields" style="grid-template-columns: ${fieldsGrid}">
          ${def.fields.map(f => {
            if (f.type === 'select') {
              return `<select data-f="${f.key}" title="Format des dates de cette ligne">
                ${f.options.map(o => `<option value="${esc(o.value)}"${(item[f.key] || '') === o.value ? ' selected' : ''}>${esc(o.label)}</option>`).join('')}
              </select>`;
            }
            if (f.suggest && f.suggest.length) {
              const dlId = `dl-${section.type}-${f.key}`;
              return `<input type="${f.type || 'text'}" data-f="${f.key}" list="${dlId}"
                       placeholder="${esc(f.placeholder || '')}" value="${esc(item[f.key] || '')}">
                      <datalist id="${dlId}">${f.suggest.map(s => `<option value="${esc(s)}">`).join('')}</datalist>`;
            }
            return `<input type="${f.type || 'text'}" data-f="${f.key}" placeholder="${esc(f.placeholder || '')}" value="${esc(item[f.key] || '')}">`;
          }).join('')}
        </div>
        <div class="row-actions">
          <button type="button" class="icon-btn drag-handle" title="Glisser pour déplacer la ligne où vous voulez">⠿</button>
          <button type="button" class="icon-btn" data-act="up"     title="Monter"   ${idx === 0 ? 'disabled' : ''}>↑</button>
          <button type="button" class="icon-btn" data-act="down"   title="Descendre" ${idx === total - 1 ? 'disabled' : ''}>↓</button>
          <button type="button" class="icon-btn danger" data-act="remove" title="Supprimer">✕</button>
        </div>
      `;
      row.querySelector('.row-include').addEventListener('change', (e) => {
        item.hidden = !e.target.checked;
        row.classList.toggle('hidden-item', item.hidden);
      });
      row.querySelectorAll('[data-f]').forEach(el => {
        const ev = el.tagName === 'SELECT' ? 'change' : 'input';
        el.addEventListener(ev, () => { item[el.dataset.f] = el.value; });
      });
      row.querySelectorAll('.icon-btn[data-act]').forEach(btn => {
        btn.addEventListener('click', () => {
          const act = btn.dataset.act;
          if (act === 'remove') {
            section.items.splice(idx, 1);
            if (!section.items.length) section.items.push({});
          } else if (act === 'up' && idx > 0) {
            [section.items[idx - 1], section.items[idx]] = [section.items[idx], section.items[idx - 1]];
          } else if (act === 'down' && idx < section.items.length - 1) {
            [section.items[idx + 1], section.items[idx]] = [section.items[idx], section.items[idx + 1]];
          }
          renderProfileSections();
        });
      });

      // ── Zone descriptions (multi, avec toggle afficher / supprimer) ──
      if (!Array.isArray(item.descriptions)) item.descriptions = [];
      const descArea = document.createElement('div');
      descArea.className = 'profile-row-desc';
      const renderDescList = () => {
        descArea.innerHTML = '';
        item.descriptions.forEach((desc, dIdx) => {
          const dRow = document.createElement('div');
          dRow.className = 'profile-row-desc-item' + (desc.hidden ? ' hidden-item' : '');
          dRow.innerHTML = `
            <input type="checkbox" class="desc-include" title="Afficher dans le CV" ${desc.hidden ? '' : 'checked'}>
            <textarea class="desc-text" rows="1" placeholder="Description (Ex : Développé une API REST en Node.js)"></textarea>
            <button type="button" class="icon-btn danger desc-remove" title="Supprimer la description">✕</button>
          `;
          const ta = dRow.querySelector('.desc-text');
          ta.value = desc.text || '';
          const autosize = () => { ta.style.height = 'auto'; ta.style.height = (ta.scrollHeight + 2) + 'px'; };
          ta.addEventListener('input', () => { desc.text = ta.value; autosize(); refreshPreview(); });
          requestAnimationFrame(autosize);
          dRow.querySelector('.desc-include').addEventListener('change', (e) => {
            desc.hidden = !e.target.checked;
            dRow.classList.toggle('hidden-item', desc.hidden);
            refreshPreview();
          });
          dRow.querySelector('.desc-remove').addEventListener('click', () => {
            item.descriptions.splice(dIdx, 1);
            renderDescList();
            refreshPreview();
          });
          descArea.appendChild(dRow);
        });
        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'profile-row-desc-add';
        addBtn.textContent = '+ Ajouter une description';
        addBtn.addEventListener('click', () => {
          item.descriptions.push({ text: '', hidden: false });
          renderDescList();
          refreshPreview();
        });
        descArea.appendChild(addBtn);
      };
      renderDescList();
      row.appendChild(descArea);

      return row;
    }

    function renderProfileSection(section, idx, total) {
      const def = SECTION_DEFS[section.type];
      const wrap = document.createElement('div');
      wrap.className = 'profile-section' + (section.hidden ? ' hidden-section' : '');
      wrap.innerHTML = `
        <div class="profile-section-head">
          <input type="checkbox" class="section-include" title="Afficher cette section dans le CV" ${section.hidden ? '' : 'checked'}>
          <input type="text" class="title title-input" value="${esc(section.title || def.label)}"
                 placeholder="${esc(def.label)}"
                 title="Titre de la section — modifiable. Laisse vide pour le titre par défaut (${esc(def.label)}).">
          <button type="button" class="icon-btn title-reset" title="Rétablir le titre par défaut (${esc(def.label)})">↺</button>
          <label class="section-size" title="Taille du texte de cette section dans le CV">
            <span class="section-size-icon">A</span>
            <input type="range" class="section-size-range" min="60" max="150" step="5" value="${section.fontScale || 100}">
            <span class="section-size-val">${section.fontScale || 100} %</span>
          </label>
          <div class="section-actions">
            <button type="button" class="icon-btn drag-handle" title="Glisser pour déplacer la section où vous voulez">⠿</button>
            <button type="button" class="icon-btn" data-act="up"   title="Monter la section"   ${idx === 0 ? 'disabled' : ''}>↑</button>
            <button type="button" class="icon-btn" data-act="down" title="Descendre la section" ${idx === total - 1 ? 'disabled' : ''}>↓</button>
          </div>
        </div>
        <div class="profile-section-rows"></div>
        <button type="button" class="row-add">${esc(def.addLabel)}</button>
      `;
      wrap.querySelector('.section-include').addEventListener('change', (e) => {
        section.hidden = !e.target.checked;
        wrap.classList.toggle('hidden-section', section.hidden);
      });
      // Titre de section éditable : met à jour section.title (l'aperçu se rafraîchit
      // via l'écouteur global sur .modal-body). Le bouton ↺ rétablit le titre par défaut.
      const titleInput = wrap.querySelector('.title-input');
      titleInput.addEventListener('input', () => { section.title = titleInput.value; });
      wrap.querySelector('.title-reset').addEventListener('click', () => {
        section.title = '';
        titleInput.value = def.label;
        refreshPreview();
      });
      wrap.querySelectorAll('.section-actions .icon-btn[data-act]').forEach(btn => {
        btn.addEventListener('click', () => {
          const act = btn.dataset.act;
          const arr = currentProfile.sections;
          if (act === 'up' && idx > 0) {
            [arr[idx - 1], arr[idx]] = [arr[idx], arr[idx - 1]];
          } else if (act === 'down' && idx < arr.length - 1) {
            [arr[idx + 1], arr[idx]] = [arr[idx], arr[idx + 1]];
          }
          renderProfileSections();
        });
      });
      // Curseur de taille de la section : met à jour la donnée + l'étiquette %
      const sizeRange = wrap.querySelector('.section-size-range');
      const sizeVal   = wrap.querySelector('.section-size-val');
      sizeRange.addEventListener('input', () => {
        section.fontScale = Number(sizeRange.value) || 100;
        sizeVal.textContent = section.fontScale + ' %';
      });
      const rowsWrap = wrap.querySelector('.profile-section-rows');
      section.items.forEach((item, i) => {
        rowsWrap.appendChild(renderProfileRow(section, item, i, section.items.length));
      });
      // Glisser-déposer libre des lignes à l'intérieur de la section
      setupDragSort(rowsWrap, '.profile-row', (from, to) => {
        moveArrayItem(section.items, from, to);
        renderProfileSections();
      });
      wrap.querySelector('.row-add').addEventListener('click', () => {
        section.items.push({});
        renderProfileSections();
      });
      return wrap;
    }

    let currentProfile = null;
    function renderProfileSections() {
      const wrap = $('profileSections');
      wrap.innerHTML = '';
      currentProfile.sections.forEach((s, i) => {
        wrap.appendChild(renderProfileSection(s, i, currentProfile.sections.length));
      });
      // Glisser-déposer libre des sections entières
      setupDragSort(wrap, '.profile-section', (from, to) => {
        moveArrayItem(currentProfile.sections, from, to);
        renderProfileSections();
      });
      refreshPreview();
    }

    function readHeaderFields() {
      currentProfile.firstName    = $('profileFirstName').value.trim();
      currentProfile.lastName     = $('profileLastName').value.trim();
      // Champs étendus (titre/poste, résumé, coordonnées) — présents uniquement dans l'éditeur.
      const headlineEl = $('profileHeadline');
      if (headlineEl) currentProfile.headline = headlineEl.value.trim();
      const summaryEl = $('profileSummary');
      if (summaryEl) currentProfile.summary = summaryEl.value;
      // Coordonnées : UI dynamique (liste { icon, text }) maintenue par renderContactRows.
      // Rien à relire ici quand la liste est active ; sinon, repli sur d'éventuels champs typés.
      if (!$('contactList')) {
        const loc = $('profileLocation'), ph = $('profilePhone'),
              em = $('profileEmail'),    pm = $('profilePermis');
        if (loc || ph || em || pm) {
          currentProfile.contact = {
            location: loc ? loc.value.trim() : '',
            phone:    ph  ? ph.value.trim()  : '',
            email:    em  ? em.value.trim()  : '',
            permis:   pm  ? pm.value.trim()  : '',
          };
        }
      }
      currentProfile.birthDate    = $('profileBirthDate').value;
      currentProfile.birthDisplay = $('profileBirthDisplay').value;
      currentProfile.photoHidden  = !$('profilePhotoInclude').checked;
      currentProfile.dateFormat   = $('profileDateFormat').value;
      currentProfile.fontScale    = Number($('profileFontScale').value) || 100;
      currentProfile.photoSize    = Number($('profilePhotoSize').value) || 100;
      currentProfile.singlePage   = $('profileSinglePage').checked;
      if (currentProfile.photoPosition !== 'left' && currentProfile.photoPosition !== 'right') {
        currentProfile.photoPosition = 'right';
      }
      currentProfile.colors = {
        main:      $('profileColorMain').value,
        secondary: $('profileColorSecondary').value,
      };
      COLOR_EXTRAS.forEach(c => {
        const chk = $(`profileColorOn_${c.key}`);
        const pic = $(`profileColorVal_${c.key}`);
        if (chk && chk.checked && pic) currentProfile.colors[c.key] = pic.value;
        else delete currentProfile.colors[c.key];
      });
    }

    // ===== Coordonnées dynamiques (ajout / suppression d'infos) =====
    const CONTACT_ICONS = ['📍', '📞', '📱', '✉️', '🌐', '🔗', '💼', '🚗', '📅', '🏠', '💬'];

    function contactItems() {
      if (!currentProfile.contact || typeof currentProfile.contact !== 'object') currentProfile.contact = {};
      if (!Array.isArray(currentProfile.contact.items)) currentProfile.contact.items = [];
      return currentProfile.contact.items;
    }

    // Remplace (une seule fois) les champs fixes de coordonnées par une liste dynamique + bouton « + ».
    function ensureContactUI() {
      if ($('contactList')) return;
      const anchor = $('profileLocation') || $('profileEmail') || $('profilePhone');
      if (!anchor) return;
      const grid  = anchor.parentElement;     // grille des anciens champs
      const group = grid.parentElement;        // bloc .settings-group
      const list = document.createElement('div');
      list.id = 'contactList';
      list.style.cssText = 'display:flex; flex-direction:column; gap:8px;';
      const addBtn = document.createElement('button');
      addBtn.type = 'button';
      addBtn.id = 'contactAddBtn';
      addBtn.textContent = '+ Ajouter une info';
      addBtn.style.cssText = 'margin-top:8px; padding:8px 14px; border-radius:8px; '
        + 'border:1px dashed var(--border-light); background:transparent; color:var(--text-primary); '
        + 'font:inherit; cursor:pointer;';
      addBtn.addEventListener('click', () => {
        contactItems().push({ icon: '📍', text: '' });
        renderContactRows();
        refreshPreview();
      });
      grid.replaceWith(list);   // retire les anciens champs fixes
      group.appendChild(addBtn);
    }

    // (Re)dessine les lignes de coordonnées d'après currentProfile.contact.items.
    function renderContactRows() {
      const wrap = $('contactList');
      if (!wrap) return;
      const items = contactItems();
      wrap.innerHTML = '';
      const inputCss = 'padding:10px 12px; border-radius:var(--radius-sm); border:1px solid var(--border-light); '
        + 'background:var(--bg-input); color:var(--text-primary); font:inherit;';
      items.forEach((it) => {
        const row = document.createElement('div');
        row.setAttribute('data-c-row', '');
        row.style.cssText = 'display:flex; gap:8px; align-items:center;';

        const sel = document.createElement('select');
        sel.className = 'c-icon';
        sel.style.cssText = inputCss + ' flex:0 0 auto; cursor:pointer;';
        CONTACT_ICONS.forEach(ic => {
          const o = document.createElement('option');
          o.value = ic; o.textContent = ic;
          if (ic === (it.icon || '📍')) o.selected = true;
          sel.appendChild(o);
        });

        const inp = document.createElement('input');
        inp.type = 'text';
        inp.className = 'c-text';
        inp.value = it.text || '';
        inp.placeholder = 'Information (ville, téléphone, site web…)';
        inp.style.cssText = inputCss + ' flex:1;';

        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'c-del';
        del.title = 'Retirer cette info';
        del.textContent = '✕';
        del.style.cssText = 'flex:0 0 auto; padding:9px 12px; border-radius:var(--radius-sm); '
          + 'border:1px solid var(--border-light); background:transparent; color:var(--text-primary); '
          + 'cursor:pointer; font:inherit;';

        sel.addEventListener('change', () => { it.icon = sel.value; refreshPreview(); });
        inp.addEventListener('input',  () => { it.text = inp.value; refreshPreview(); });
        del.addEventListener('click',  () => {
          const i = items.indexOf(it);
          if (i >= 0) items.splice(i, 1);
          renderContactRows();
          refreshPreview();
        });

        row.append(sel, inp, del);
        wrap.appendChild(row);
      });
    }

    // Charge un fichier image, redimensionne (max 400px) et renvoie un dataURL JPEG
    function processPhoto(file) {
      return new Promise((resolve, reject) => {
        if (!file.type.startsWith('image/')) {
          reject(new Error('Le fichier sélectionné n\'est pas une image.'));
          return;
        }
        const reader = new FileReader();
        reader.onerror = () => reject(reader.error);
        reader.onload = (e) => {
          const img = new Image();
          img.onerror = () => reject(new Error('Image invalide.'));
          img.onload = () => {
            const MAX = 400;
            const scale = Math.min(1, MAX / Math.max(img.width, img.height));
            const w = Math.max(1, Math.round(img.width * scale));
            const h = Math.max(1, Math.round(img.height * scale));
            const canvas = document.createElement('canvas');
            canvas.width = w; canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, w, h);
            resolve(canvas.toDataURL('image/jpeg', 0.85));
          };
          img.src = e.target.result;
        };
        reader.readAsDataURL(file);
      });
    }

    function refreshPhotoUI() {
      const has = !!currentProfile.photo;
      const preview     = $('profilePhotoPreview');
      const img         = $('profilePhotoImg');
      const placeholder = $('profilePhotoPlaceholder');
      const removeBtn   = $('profilePhotoRemove');
      const includeLbl  = $('profilePhotoIncludeLabel');
      const includeChk  = $('profilePhotoInclude');
      const chooseBtn   = $('profilePhotoBtn');
      const sizeGroup   = $('profilePhotoSizeGroup');
      preview.classList.toggle('has-image', has);
      if (has) {
        img.src = currentProfile.photo;
        img.hidden = false;
        placeholder.hidden = true;
        removeBtn.hidden = false;
        includeLbl.hidden = false;
        chooseBtn.textContent = '📷 Changer la photo';
      } else {
        img.hidden = true;
        img.removeAttribute('src');
        placeholder.hidden = false;
        removeBtn.hidden = true;
        includeLbl.hidden = true;
        chooseBtn.textContent = '📷 Choisir une photo';
      }
      sizeGroup.hidden = !has;
      document.querySelectorAll('.photo-pos-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.pos === currentProfile.photoPosition);
      });
      document.querySelectorAll('.photo-shape-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.shape === currentProfile.photoShape);
      });
      includeChk.checked = !currentProfile.photoHidden;
    }

    function renderTemplatePresets() {
      const wrap = $('profileTemplates');
      wrap.innerHTML = '';
      Object.entries(CV_TEMPLATES).forEach(([key, tpl]) => {
        const active = currentProfile.template === key;
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'template-preset' + (active ? ' active' : '');
        b.title = tpl.label;
        b.innerHTML = `
          <span class="swatches">
            <span class="swatch" style="background:${tpl.swatch[0]}"></span>
            <span class="swatch" style="background:${tpl.swatch[1]}"></span>
          </span>
          <span>${esc(tpl.label)}</span>
        `;
        b.addEventListener('click', () => {
          currentProfile.template = key;
          renderTemplatePresets();
          refreshPreview();
        });
        wrap.appendChild(b);
      });
    }

    function renderColorPresets() {
      const wrap = $('profileColorPresets');
      const cur = currentProfile.colors || COLOR_DEFAULT;
      wrap.innerHTML = '';
      COLOR_PRESETS.forEach(preset => {
        const active = preset.main.toLowerCase() === cur.main.toLowerCase()
                    && preset.secondary.toLowerCase() === cur.secondary.toLowerCase();
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'color-preset' + (active ? ' active' : '');
        b.title = `${preset.name} — ${preset.main} / ${preset.secondary}`;
        b.innerHTML = `
          <span class="swatches">
            <span class="swatch" style="background:${preset.main}"></span>
            <span class="swatch" style="background:${preset.secondary}"></span>
          </span>
          <span>${esc(preset.name)}</span>
        `;
        b.addEventListener('click', () => {
          $('profileColorMain').value      = preset.main;
          $('profileColorSecondary').value = preset.secondary;
          // Un preset reprend la main : on efface les overrides avancés pour qu'il s'applique pleinement.
          currentProfile.colors = { main: preset.main, secondary: preset.secondary };
          renderColorPresets();
          renderColorExtras();
          refreshPreview();
        });
        wrap.appendChild(b);
      });
    }

    function renderColorExtras() {
      const wrap = $('profileColorExtras');
      if (!wrap) return;
      const cur = currentProfile.colors || {};
      wrap.innerHTML = '';
      COLOR_EXTRAS.forEach(c => {
        const active = typeof cur[c.key] === 'string';
        const value  = active ? cur[c.key] : c.fallback;
        const row = document.createElement('label');
        row.className = 'color-extra-row' + (active ? ' on' : '');
        row.innerHTML = `
          <input type="checkbox" id="profileColorOn_${c.key}" ${active ? 'checked' : ''}>
          <span class="lbl">${c.label}</span>
          <input type="color" id="profileColorVal_${c.key}" value="${value}" ${active ? '' : 'disabled'}>
        `;
        const chk = row.querySelector('input[type="checkbox"]');
        const pic = row.querySelector('input[type="color"]');
        const sync = () => {
          row.classList.toggle('on', chk.checked);
          pic.disabled = !chk.checked;
          if (chk.checked) currentProfile.colors[c.key] = pic.value;
          else delete currentProfile.colors[c.key];
          refreshPreview();
        };
        chk.addEventListener('change', sync);
        pic.addEventListener('input', sync);
        wrap.appendChild(row);
      });
    }

    function openProfile() {
      currentProfile = loadProfile();
      $('profileFirstName').value      = currentProfile.firstName || '';
      $('profileLastName').value       = currentProfile.lastName  || '';
      // Champs étendus (présents seulement si le markup éditeur les contient).
      if ($('profileHeadline')) $('profileHeadline').value = currentProfile.headline || '';
      if ($('profileSummary'))  $('profileSummary').value  = currentProfile.summary  || '';
      // Coordonnées dynamiques : construit la liste (+/−) et l'affiche.
      ensureContactUI();
      renderContactRows();
      $('profileBirthDate').value      = currentProfile.birthDate || '';
      $('profileBirthDisplay').value   = currentProfile.birthDisplay || 'date';
      $('profileColorMain').value      = currentProfile.colors?.main      || COLOR_DEFAULT.main;
      $('profileColorSecondary').value = currentProfile.colors?.secondary || COLOR_DEFAULT.secondary;
      $('profileDateFormat').value     = currentProfile.dateFormat || 'full';
      $('profileFontScale').value      = currentProfile.fontScale || 100;
      $('profileFontScaleVal').textContent = ($('profileFontScale').value) + ' %';
      $('profilePhotoSize').value      = currentProfile.photoSize || 100;
      $('profilePhotoSizeVal').textContent = ($('profilePhotoSize').value) + ' px';
      $('profileSinglePage').checked   = !!currentProfile.singlePage;
      $('profileFreeLayout').checked   = !!currentProfile.freeLayout;
      refreshPhotoUI();
      renderTemplatePresets();
      renderColorPresets();
      renderColorExtras();
      renderProfileSections();
      $('profileOverlay').hidden = false;
      setPreviewActive(true);
      ensureTourButton();
    }
    function closeProfile() {
      $('profileOverlay').hidden = true;
      $('profilePreviewView').hidden = true;
    }

    function fmtBirth(iso) {
      if (!iso) return '';
      const d = new Date(iso);
      if (isNaN(d)) return iso;
      return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });
    }
    function computeAge(iso) {
      if (!iso) return null;
      const d = new Date(iso);
      if (isNaN(d)) return null;
      const now = new Date();
      let age = now.getFullYear() - d.getFullYear();
      const m = now.getMonth() - d.getMonth();
      if (m < 0 || (m === 0 && now.getDate() < d.getDate())) age--;
      return age;
    }
    function birthLineForCv(p) {
      if (!p.birthDate || p.birthDisplay === 'none') return '';
      if (p.birthDisplay === 'age') {
        const a = computeAge(p.birthDate);
        return a == null ? '' : `${a} ans`;
      }
      return `Né(e) le ${fmtBirth(p.birthDate)}`;
    }

    function isItemFilled(item) {
      return Object.entries(item).some(([k, v]) => {
        if (k === 'hidden') return false;
        if (Array.isArray(v)) return v.some(d => d && (d.text || '').toString().trim());
        return (v || '').toString().trim();
      });
    }

    const CV_TEMPLATES = {
      classique: {
        label: 'Classique',
        swatch: ['#7c6cf7', '#ffffff'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 18mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1e293b; margin: 0;
         line-height: 1.5; font-size: ${sz(12)}; }
  .header { display: flex; align-items: center; gap: 20px;
            border-bottom: 3px solid ${main}; padding-bottom: 12px; margin-bottom: 24px; }
  .header-text { flex: 1; min-width: 0; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 50%;
                  object-fit: cover; border: 3px solid ${main}; flex-shrink: 0; }
  h1 { margin: 0; font-size: ${sz(28)}; color: #0f172a; letter-spacing: 0.5px; }
  .meta { margin-top: 6px; color: #64748b; font-size: ${sz(11)}; }
  section { margin-bottom: 22px; }
  h2 { font-size: ${sz(14)}; color: ${main}; text-transform: uppercase;
       letter-spacing: 1.5px; margin: 0 0 12px; padding-bottom: 4px;
       border-bottom: 1px solid #e2e8f0; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 14px; padding-left: 14px; border-left: 2px solid ${secondary}; }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(13)}; color: #0f172a; }
  .f-tag { background: ${main}1a; color: ${main}; padding: 2px 8px;
           border-radius: 4px; font-size: ${sz(10)}; font-weight: 600; }
  .f-dates { color: #64748b; font-size: ${sz(11)}; margin-top: 2px; }
  .empty { color: #94a3b8; font-style: italic; }
  @media print { .no-print { display: none; } html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: ${main}; color: #fff; padding: 10px 18px;
              border: none; border-radius: 6px; font-weight: 700;
              cursor: pointer; box-shadow: 0 4px 14px ${main}66; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="header">
    ${photoSrc && profile.photoPosition === 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    <div class="header-text">
      <h1>${esc(fullName)}</h1>
      ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
    </div>
    ${photoSrc && profile.photoPosition !== 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
  </div>
  ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      moderne: {
        label: 'Moderne',
        swatch: ['#2563eb', '#f8fafc'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, mainSections, sideSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; }
  body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1e293b; margin: 0;
         line-height: 1.5; font-size: ${sz(12)}; display: flex; min-height: 297mm;
         background: linear-gradient(to right, ${main} 0 35%, #fff 35% 100%);
         -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .sidebar { width: 35%; background: ${main}; color: #fff; align-self: stretch;
             padding: 20mm 14mm; display: flex; flex-direction: column; gap: 22px; }
  .sidebar h1 { margin: 0; font-size: ${sz(22)}; color: #fff;
                letter-spacing: 0.5px; line-height: 1.2; font-weight: 700; }
  .sidebar .meta { color: #ffffffcc; font-size: ${sz(11)}; margin-top: 4px; }
  .sidebar h2 { color: #fff; font-size: ${sz(12)}; text-transform: uppercase;
                letter-spacing: 1.5px; margin: 0 0 10px; padding-bottom: 4px;
                border-bottom: 1px solid #ffffff66; }
  .sidebar ul { list-style: none; padding: 0; margin: 0; }
  .sidebar li { margin-bottom: 10px; padding-left: 0; border-left: none; }
  .sidebar .f-head strong { color: #fff; font-size: ${sz(12)}; }
  .sidebar .f-tag { background: #ffffff22; color: #fff; padding: 2px 8px;
                    border-radius: 4px; font-size: ${sz(10)}; font-weight: 600; }
  .sidebar-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 50%;
                   object-fit: cover; border: 3px solid #fff; align-self: flex-start; }
  .main { flex: 1; padding: 20mm 16mm; }
  .main h2 { font-size: ${sz(14)}; color: ${main}; text-transform: uppercase;
             letter-spacing: 1.5px; margin: 0 0 12px; padding-bottom: 4px;
             border-bottom: 2px solid ${main}; display: inline-block; }
  .main section { margin-bottom: 22px; }
  .main ul { list-style: none; padding: 0; margin: 0; }
  .main li { margin-bottom: 14px; padding-left: 14px; border-left: 3px solid ${secondary}; }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .main .f-head strong { font-size: ${sz(13)}; color: #0f172a; }
  .main .f-tag { background: ${main}1a; color: ${main}; padding: 2px 8px;
                 border-radius: 4px; font-size: ${sz(10)}; font-weight: 600; }
  .f-dates { color: #64748b; font-size: ${sz(11)}; margin-top: 2px; }
  .sidebar .f-dates { color: #ffffffaa; }
  .empty { color: #94a3b8; font-style: italic; }
  @media print { .no-print { display: none; } html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: ${main}; color: #fff; padding: 10px 18px;
              border: none; border-radius: 6px; font-weight: 700;
              cursor: pointer; box-shadow: 0 4px 14px ${main}66; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <aside class="sidebar">
    ${photoSrc ? `<img class="sidebar-photo" src="${photoSrc}" alt="">` : ''}
    <div>
      <h1>${esc(fullName)}</h1>
      ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
    </div>
    ${sideSections}
  </aside>
  <div class="main">
    ${mainSections || '<p class="empty">Aucune section principale à afficher.</p>'}
  </div>
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      dc2scale: {
        label: 'Tableautier (DC2Scale)',
        swatch: ['#1d2b4d', '#d4a23c'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, photoSrc, autoPrint }) => {
          const navy = main || '#1d2b4d';
          const gold = secondary || '#d4a23c';
          const c = profile.contact || {};
          const fmt = profile.dateFormat || 'year';
          const vis = (s) => s && !s.hidden;
          const itemsOf = (t) => {
            const s = (profile.sections || []).find(x => x.type === t && vis(x));
            return s ? s.items.filter(it => !it.hidden && isItemFilled(it)) : [];
          };
          // Titre affiché d'une section (personnalisé sinon libellé par défaut).
          const titleOf = (t) => {
            const s = (profile.sections || []).find(x => x.type === t);
            return esc(s ? sectionTitle(s) : ((SECTION_DEFS[t] || {}).label || ''));
          };
          const dateRange = (it) => {
            const a = formatItemDate(it.start, fmt), b = formatItemDate(it.end, fmt);
            if (!a && !b) return '';
            return esc(a) + (a && b ? ' – ' : '') + esc(b);
          };
          const bullets = (it) => {
            const l = (it.descriptions || []).filter(d => !d.hidden && (d.text || '').toString().trim());
            return l.length ? `<ul>${l.map(d => `<li>${esc(d.text)}</li>`).join('')}</ul>` : '';
          };
          const parts = (fullName || '').trim().split(/\s+/);
          const initials = (parts.map(w => w[0] || '').join('').slice(0, 2).toUpperCase()) || '?';
          const nameHtml = parts.length > 1
            ? `${esc(parts[0])}<br>${esc(parts.slice(1).join(' '))}`
            : esc(fullName || 'Mon CV');

          const habil = itemsOf('habilitations');
          const comp  = itemsOf('competences');
          const logi  = itemsOf('logiciels');
          const langs = itemsOf('langues');
          const exps  = itemsOf('experience');
          const forms = itemsOf('formation');

          const contactItemsList = (Array.isArray(c.items) && c.items.length)
            ? c.items
            : [
                c.location && { icon: '📍', text: c.location },
                c.phone    && { icon: '📞', text: c.phone },
                c.email    && { icon: '✉️', text: c.email },
                c.website  && { icon: '🌐', text: c.website },
                c.permis   && { icon: '🚗', text: c.permis },
              ].filter(Boolean);
          const contactHtml = contactItemsList
            .filter(it => (it.text || '').trim())
            .map(it => `<div class="contact">${esc(it.icon || '')} <span>${esc(it.text)}</span></div>`)
            .join('');
          const sideHabil = habil.length ? `<h2>${titleOf('habilitations')}</h2><div class="badges">${habil.map(h => `<span class="badge">${esc(h.name || '')}</span>`).join('')}</div>` : '';
          const sideComp  = comp.length ? `<h2>${titleOf('competences')}</h2>${comp.map(k => `<div class="skill"><div class="t">${esc(k.title || '')}</div>${k.desc ? `<div class="d">${esc(k.desc)}</div>` : ''}</div>`).join('')}` : '';
          const sideLogi  = logi.length ? `<h2>${titleOf('logiciels')}</h2><div class="softs">${logi.map(l => `<span class="soft">${esc(l.name || '')}</span>`).join('')}</div>` : '';
          const sideLang  = langs.length ? `<h2>${titleOf('langues')}</h2>${langs.map(l => `<div class="lang"><span>${esc(l.name || '')}</span><span class="lv">${esc(l.level || '')}</span></div>`).join('')}` : '';

          const expHtml = exps.length ? `<section><h2>${titleOf('experience')}</h2>${exps.map(x => `<div class="exp"><div class="row"><span class="h">${esc(x.role || '')}${x.company ? ' — ' + esc(x.company) : ''}</span>${dateRange(x) ? `<span class="date">${dateRange(x)}</span>` : ''}</div>${bullets(x)}</div>`).join('')}</section>` : '';
          const eduHtml = forms.length ? `<section><h2>${titleOf('formation')}</h2>${forms.map(f => {
            const det = (f.descriptions || []).filter(d => !d.hidden && (d.text || '').trim()).map(d => esc(d.text)).join(' · ');
            const rest = [esc(f.level || ''), dateRange(f), det].filter(Boolean).join(' · ');
            return `<div class="edu"><span class="t">${esc(f.name || '')}</span>${rest ? ` <span class="r">— ${rest}</span>` : ''}</div>`;
          }).join('')}</section>` : '';

          return `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: 'Segoe UI', system-ui, Arial, sans-serif; color: #2b2b2b;
         font-size: ${sz(11.5)}; line-height: 1.45; display: flex; min-height: 297mm;
         background: linear-gradient(to right, ${navy} 0 35%, #fff 35% 100%);
         -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .side { width: 35%; background: ${navy}; color: #cdd5e3; padding: 16mm 11mm;
          align-self: stretch; }
  .avatar { width: ${photoPx}px; height: ${photoPx}px; border-radius: 50%; margin: 0 auto 14px;
            border: 4px solid ${gold}; background: #33415f center/cover no-repeat; overflow: hidden;
            display: flex; align-items: center; justify-content: center; color: #fff;
            font-size: ${sz(26)}; font-weight: 700; }
  .avatar img.sidebar-photo { width: 100%; height: 100%; object-fit: cover; border: none; }
  .side h1 { color: ${gold}; text-align: center; font-size: ${sz(24)}; line-height: 1.05;
             margin: 0 0 6px; font-weight: 800; }
  .side .role { text-align: center; color: #fff; font-size: ${sz(10)}; letter-spacing: .06em;
                font-weight: 700; margin-bottom: 16px; text-transform: uppercase; }
  .contact { font-size: ${sz(10.5)}; margin-bottom: 7px; display: flex; gap: 8px; }
  .side h2 { color: ${gold}; font-size: ${sz(11)}; letter-spacing: .08em; text-transform: uppercase;
             margin: 18px 0 9px; padding-bottom: 5px; border-bottom: 1px solid #ffffff2e; }
  .badges { display: flex; flex-wrap: wrap; gap: 7px; }
  .badge { border: 1px solid ${gold}; color: #fff; border-radius: 6px; padding: 3px 10px;
           font-size: ${sz(10.5)}; font-weight: 600; }
  .softs { display: flex; flex-wrap: wrap; gap: 6px; }
  .soft { display: inline-block; background: #ffffff1f; color: #fff; border-radius: 5px;
          padding: 4px 9px; font-size: ${sz(10)}; }
  .skill { margin-bottom: 10px; }
  .skill .t { color: ${gold}; font-weight: 700; font-size: ${sz(10.5)}; text-transform: uppercase; letter-spacing: .02em; }
  .skill .d { color: #b9c2d4; font-size: ${sz(10.5)}; }
  .lang { display: flex; justify-content: space-between; font-size: ${sz(10.5)}; padding: 3px 0; }
  .lang .lv { color: #93a0ba; }
  .main { flex: 1; padding: 16mm 13mm; }
  .summary { border-left: 3px solid ${gold}; padding-left: 13px; color: #44423f;
             font-size: ${sz(11.5)}; margin-bottom: 18px; }
  .main h2 { color: ${navy}; font-size: ${sz(15)}; letter-spacing: .02em; margin: 0 0 11px;
             padding-bottom: 5px; border-bottom: 2px solid ${gold}; text-transform: uppercase; }
  section { margin-bottom: 18px; }
  .exp { margin-bottom: 13px; }
  .exp .row { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; }
  .exp .h { color: ${navy}; font-weight: 700; font-size: ${sz(12)}; }
  .exp .date { color: #6b7280; font-size: ${sz(10.5)}; white-space: nowrap; font-weight: 600; }
  .exp ul { margin: 4px 0 0; padding: 0; list-style: none; }
  .exp li { position: relative; padding-left: 15px; margin-bottom: 3px; color: #444; }
  .exp li::before { content: "▸"; position: absolute; left: 0; color: ${gold}; }
  .edu { margin-bottom: 8px; }
  .edu .t { color: ${navy}; font-weight: 700; }
  .edu .r { color: #6b7280; }
  @media print { .no-print { display: none; } html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } }
  .no-print { position: fixed; top: 12px; right: 12px; background: ${gold}; color: ${navy};
              padding: 10px 18px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;
              box-shadow: 0 4px 14px rgba(0,0,0,.3); }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <aside class="side">
    <div class="avatar">${photoSrc ? `<img class="sidebar-photo" src="${photoSrc}" alt="">` : esc(initials)}</div>
    <h1>${nameHtml}</h1>
    ${profile.headline ? `<div class="role">${esc(profile.headline)}</div>` : ''}
    ${contactHtml}
    ${sideHabil}
    ${sideComp}
    ${sideLogi}
    ${sideLang}
  </aside>
  <main class="main">
    ${profile.summary ? `<div class="summary">${esc(profile.summary).replace(/\n/g, '<br>')}</div>` : ''}
    ${expHtml}
    ${eduHtml}
  </main>
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`;
        }
      },

      minimaliste: {
        label: 'Minimaliste',
        swatch: ['#1a1a1a', '#ffffff'],
        build: ({ fullName, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 18mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif; color: #1a1a1a; margin: 0;
         line-height: 1.55; font-size: ${sz(12)}; }
  .header { text-align: center; margin-bottom: 26px; padding-bottom: 16px;
            border-bottom: 1px solid #e5e5e5; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 50%;
                  object-fit: cover; margin: 0 auto 12px; display: block;
                  border: 1px solid #d4d4d4; }
  h1 { margin: 0; font-size: ${sz(24)}; color: #1a1a1a; font-weight: 300;
       letter-spacing: 4px; text-transform: uppercase; }
  .meta { margin-top: 6px; color: #737373; font-size: ${sz(11)}; letter-spacing: 0.5px; }
  section { margin-bottom: 22px; }
  h2 { font-size: ${sz(11)}; color: #1a1a1a; text-transform: uppercase;
       letter-spacing: 3px; font-weight: 600;
       margin: 0 0 14px; padding-bottom: 6px;
       border-bottom: 1px solid #1a1a1a; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 12px; padding-left: 0; border-left: none; }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(13)}; color: #1a1a1a; font-weight: 600; }
  .f-tag { color: #737373; padding: 0; background: transparent;
           font-size: ${sz(11)}; font-weight: 400; font-style: italic; }
  .f-tag::before { content: '— '; }
  .f-dates { color: #737373; font-size: ${sz(11)}; margin-top: 2px; }
  .empty { color: #a3a3a3; font-style: italic; }
  @media print { .no-print { display: none; } html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: #1a1a1a; color: #fff; padding: 10px 18px;
              border: none; border-radius: 6px; font-weight: 700;
              cursor: pointer; box-shadow: 0 4px 14px rgba(0,0,0,0.3); }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="header">
    ${photoSrc ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    <h1>${esc(fullName)}</h1>
    ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
  </div>
  ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      elegant: {
        label: 'Élégant',
        swatch: ['#7a5c3e', '#fdfaf3'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 20mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Georgia', 'Times New Roman', serif; color: #2a2a2a; margin: 0;
         line-height: 1.6; font-size: ${sz(12)}; }
  .header { display: flex; align-items: center; gap: 24px;
            padding: 18px 0; margin-bottom: 28px;
            border-top: 2px solid ${main}; border-bottom: 2px solid ${main}; }
  .header-text { flex: 1; min-width: 0; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 4px;
                  object-fit: cover; border: 1px solid ${secondary}; flex-shrink: 0; }
  h1 { margin: 0; font-size: ${sz(28)}; color: #1a1a1a; font-weight: 400;
       letter-spacing: 1px; font-variant: small-caps; }
  .meta { margin-top: 4px; color: #6b6b6b; font-size: ${sz(11)}; font-style: italic; }
  section { margin-bottom: 24px; }
  h2 { font-size: ${sz(15)}; color: ${main}; font-style: italic;
       font-weight: 400; margin: 0 0 12px; padding-bottom: 4px;
       border-bottom: 1px solid ${secondary}; }
  h2::before { content: '\\2766  '; color: ${secondary}; font-style: normal; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 14px; padding-left: 16px; position: relative; border-left: none; }
  li::before { content: '•'; color: ${main}; position: absolute; left: 0;
               font-size: ${sz(14)}; line-height: 1.4; }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(13)}; color: #1a1a1a; font-weight: 600; }
  .f-tag { color: ${main}; padding: 0; background: transparent;
           font-size: ${sz(11)}; font-weight: 400; font-style: italic; }
  .f-tag::before { content: '— '; color: #6b6b6b; }
  .f-dates { color: #6b6b6b; font-size: ${sz(11)}; margin-top: 2px; font-style: italic; }
  .empty { color: #a3a3a3; font-style: italic; }
  @media print { .no-print { display: none; } html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: ${main}; color: #fff; padding: 10px 18px;
              border: none; border-radius: 6px; font-weight: 700;
              cursor: pointer; box-shadow: 0 4px 14px ${main}66; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="header">
    ${photoSrc && profile.photoPosition === 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    <div class="header-text">
      <h1>${esc(fullName)}</h1>
      ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
    </div>
    ${photoSrc && profile.photoPosition !== 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
  </div>
  ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      tech: {
        label: 'Tech',
        swatch: ['#0f172a', '#22d3ee'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 18mm; }
  * { box-sizing: border-box; }
  body { font-family: 'JetBrains Mono', 'Consolas', 'Courier New', monospace; color: #0f172a; margin: 0;
         line-height: 1.5; font-size: ${sz(11)}; background: #fff; }
  .header { display: flex; align-items: center; gap: 20px;
            padding: 14px 16px; margin-bottom: 22px;
            background: #0f172a; color: #e2e8f0; border-radius: 6px; }
  .header-text { flex: 1; min-width: 0; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 6px;
                  object-fit: cover; border: 2px solid ${secondary}; flex-shrink: 0; }
  h1 { margin: 0; font-size: ${sz(22)}; color: ${secondary}; letter-spacing: 0.5px;
       font-weight: 700; }
  h1::before { content: '> '; color: #94a3b8; }
  .meta { margin-top: 4px; color: #94a3b8; font-size: ${sz(10)}; }
  .meta::before { content: '// '; color: ${secondary}; }
  section { margin-bottom: 18px; }
  h2 { font-size: ${sz(12)}; color: ${main}; font-weight: 700;
       margin: 0 0 10px; padding-bottom: 4px;
       border-bottom: 1px dashed ${main}; text-transform: lowercase; }
  h2::before { content: '## '; color: ${secondary}; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 10px; padding-left: 18px; position: relative; border-left: none; }
  li::before { content: '▸'; color: ${secondary}; position: absolute; left: 0; top: 0; }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(12)}; color: #0f172a; font-weight: 700; }
  .f-tag { background: #0f172a; color: ${secondary}; padding: 1px 6px;
           border-radius: 3px; font-size: ${sz(9)}; font-weight: 600;
           font-family: inherit; }
  .f-tag::before { content: '['; }
  .f-tag::after  { content: ']'; }
  .f-dates { color: #64748b; font-size: ${sz(10)}; margin-top: 2px; }
  .empty { color: #94a3b8; font-style: italic; }
  @media print { .no-print { display: none; } html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: ${secondary}; color: #0f172a; padding: 10px 18px;
              border: none; border-radius: 6px; font-weight: 700;
              cursor: pointer; box-shadow: 0 4px 14px ${secondary}66; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="header">
    ${photoSrc && profile.photoPosition === 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    <div class="header-text">
      <h1>${esc(fullName)}</h1>
      ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
    </div>
    ${photoSrc && profile.photoPosition !== 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
  </div>
  ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      corporate: {
        label: 'Corporate',
        swatch: ['#1e3a8a', '#cbd5e1'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 18mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Calibri', 'Segoe UI', Arial, sans-serif; color: #1e293b; margin: 0;
         line-height: 1.55; font-size: ${sz(12)}; }
  .header { display: flex; align-items: stretch; gap: 0;
            margin-bottom: 24px; border-top: 4px solid ${main}; }
  .header-text { flex: 1; padding: 16px 0 12px; min-width: 0; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 0;
                  object-fit: cover; flex-shrink: 0; }
  h1 { margin: 0; font-size: ${sz(26)}; color: ${main}; letter-spacing: 1px;
       font-weight: 700; text-transform: uppercase; }
  .meta { margin-top: 6px; color: #475569; font-size: ${sz(11)}; }
  section { margin-bottom: 22px; }
  h2 { font-size: ${sz(13)}; color: #fff; background: ${main};
       padding: 6px 12px; margin: 0 0 12px; letter-spacing: 1px;
       text-transform: uppercase; font-weight: 700; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 12px; padding-left: 12px; border-left: 3px solid ${main}; }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(13)}; color: ${main}; font-weight: 700; }
  .f-tag { background: ${main}; color: #fff; padding: 2px 8px;
           border-radius: 0; font-size: ${sz(10)}; font-weight: 600;
           text-transform: uppercase; letter-spacing: 0.5px; }
  .f-dates { color: #64748b; font-size: ${sz(11)}; margin-top: 2px;
             font-style: italic; }
  .empty { color: #94a3b8; font-style: italic; }
  @media print { .no-print { display: none; } html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: ${main}; color: #fff; padding: 10px 18px;
              border: none; border-radius: 0; font-weight: 700;
              cursor: pointer; box-shadow: 0 4px 14px ${main}66; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="header">
    ${photoSrc && profile.photoPosition === 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    <div class="header-text">
      <h1>${esc(fullName)}</h1>
      ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
    </div>
    ${photoSrc && profile.photoPosition !== 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
  </div>
  ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      timeline: {
        label: 'Timeline',
        swatch: ['#0ea5e9', '#bae6fd'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 18mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1e293b; margin: 0;
         line-height: 1.5; font-size: ${sz(12)}; }
  .header { display: flex; align-items: center; gap: 20px;
            padding-bottom: 16px; margin-bottom: 28px;
            border-bottom: 1px solid #e2e8f0; }
  .header-text { flex: 1; min-width: 0; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 50%;
                  object-fit: cover; border: 4px solid ${secondary}; flex-shrink: 0; }
  h1 { margin: 0; font-size: ${sz(28)}; color: ${main}; letter-spacing: 0.5px;
       font-weight: 700; }
  .meta { margin-top: 6px; color: #64748b; font-size: ${sz(11)}; }
  section { margin-bottom: 22px; }
  h2 { font-size: ${sz(14)}; color: ${main}; text-transform: uppercase;
       letter-spacing: 1.5px; margin: 0 0 16px; padding-bottom: 4px; }
  ul { list-style: none; padding: 0; margin: 0; position: relative; }
  ul::before { content: ''; position: absolute; left: 7px; top: 6px; bottom: 6px;
               width: 2px; background: ${secondary}; }
  li { margin-bottom: 14px; padding-left: 28px; position: relative; border-left: none; }
  li::before { content: ''; position: absolute; left: 2px; top: 6px;
               width: 12px; height: 12px; border-radius: 50%;
               background: #fff; border: 3px solid ${main}; box-sizing: border-box; }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(13)}; color: #0f172a; font-weight: 700; }
  .f-tag { background: ${main}1a; color: ${main}; padding: 2px 8px;
           border-radius: 12px; font-size: ${sz(10)}; font-weight: 600; }
  .f-dates { color: #64748b; font-size: ${sz(11)}; margin-top: 2px; font-weight: 600; }
  .empty { color: #94a3b8; font-style: italic; }
  @media print { .no-print { display: none; } html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: ${main}; color: #fff; padding: 10px 18px;
              border: none; border-radius: 6px; font-weight: 700;
              cursor: pointer; box-shadow: 0 4px 14px ${main}66; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="header">
    ${photoSrc && profile.photoPosition === 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    <div class="header-text">
      <h1>${esc(fullName)}</h1>
      ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
    </div>
    ${photoSrc && profile.photoPosition !== 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
  </div>
  ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      compact: {
        label: 'Compact',
        swatch: ['#475569', '#94a3b8'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 14mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1e293b; margin: 0;
         line-height: 1.4; font-size: ${sz(10.5)}; }
  .header { display: flex; align-items: center; gap: 14px;
            padding-bottom: 8px; margin-bottom: 14px;
            border-bottom: 1px solid ${main}; }
  .header-text { flex: 1; min-width: 0; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 4px;
                  object-fit: cover; flex-shrink: 0; }
  h1 { margin: 0; font-size: ${sz(20)}; color: #0f172a; letter-spacing: 0.5px;
       font-weight: 700; }
  .meta { margin-top: 2px; color: #64748b; font-size: ${sz(10)}; }
  section { margin-bottom: 12px; }
  h2 { font-size: ${sz(11)}; color: ${main}; text-transform: uppercase;
       letter-spacing: 1.5px; margin: 0 0 6px; padding-bottom: 2px;
       border-bottom: 1px solid #e2e8f0; font-weight: 700; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 6px; padding-left: 10px; border-left: 2px solid ${secondary}; }
  .f-head { display: flex; gap: 8px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(11)}; color: #0f172a; font-weight: 700; }
  .f-tag { background: ${main}1a; color: ${main}; padding: 1px 6px;
           border-radius: 3px; font-size: ${sz(9)}; font-weight: 600; }
  .f-dates { color: #64748b; font-size: ${sz(10)}; margin-top: 1px; }
  .empty { color: #94a3b8; font-style: italic; }
  @media print { .no-print { display: none; } html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: ${main}; color: #fff; padding: 10px 18px;
              border: none; border-radius: 6px; font-weight: 700;
              cursor: pointer; box-shadow: 0 4px 14px ${main}66; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="header">
    ${photoSrc && profile.photoPosition === 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    <div class="header-text">
      <h1>${esc(fullName)}</h1>
      ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
    </div>
    ${photoSrc && profile.photoPosition !== 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
  </div>
  ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      creatif: {
        label: 'Créatif',
        swatch: ['#8b5cf6', '#fbbf24'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, mainSections, sideSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; }
  body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1e293b; margin: 0;
         line-height: 1.5; font-size: ${sz(12)}; min-height: 297mm; position: relative; }
  body::before { content: ''; position: absolute; top: 0; left: 0;
                 width: 35%; height: 200px; background: ${main};
                 clip-path: polygon(0 0, 100% 0, 100% 70%, 0 100%); z-index: 0; }
  body::after { content: ''; position: absolute; top: 60px; right: 0;
                width: 25%; height: 160px; background: ${secondary};
                clip-path: polygon(0 30%, 100% 0, 100% 100%, 0 100%); z-index: 0; }
  .wrap { position: relative; z-index: 1; padding: 22mm 18mm; }
  .header { display: flex; align-items: center; gap: 24px; margin-bottom: 30px; }
  .header-text { flex: 1; min-width: 0; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 50%;
                  object-fit: cover; border: 5px solid #fff;
                  box-shadow: 0 6px 20px rgba(15, 23, 42, 0.18); flex-shrink: 0; }
  h1 { margin: 0; font-size: ${sz(30)}; color: #fff; letter-spacing: 0.5px;
       font-weight: 800; text-shadow: 0 2px 8px rgba(15, 23, 42, 0.3); }
  .meta { margin-top: 6px; color: #fff; font-size: ${sz(11)}; opacity: 0.92; }
  .columns { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
  section { margin-bottom: 22px; }
  h2 { font-size: ${sz(14)}; color: ${main}; text-transform: uppercase;
       letter-spacing: 1.5px; margin: 0 0 12px; padding-bottom: 4px;
       border-bottom: 3px solid ${secondary}; font-weight: 800;
       display: inline-block; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 12px; padding-left: 14px; border-left: 3px solid ${secondary}; }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(13)}; color: #0f172a; font-weight: 700; }
  .f-tag { background: ${secondary}; color: #1e293b; padding: 2px 10px;
           border-radius: 12px; font-size: ${sz(10)}; font-weight: 700; }
  .f-dates { color: #64748b; font-size: ${sz(11)}; margin-top: 2px; }
  .side h2 { font-size: ${sz(13)}; }
  .side li { margin-bottom: 8px; }
  .empty { color: #94a3b8; font-style: italic; }
  @media print { .no-print { display: none; } html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: ${main}; color: #fff; padding: 10px 18px;
              border: none; border-radius: 6px; font-weight: 700;
              cursor: pointer; box-shadow: 0 4px 14px ${main}66; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="wrap">
    <div class="header">
      ${photoSrc && profile.photoPosition === 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
      <div class="header-text">
        <h1>${esc(fullName)}</h1>
        ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
      </div>
      ${photoSrc && profile.photoPosition !== 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    </div>
    <div class="columns">
      <div class="primary">
        ${mainSections || '<p class="empty">Aucune section principale à afficher.</p>'}
      </div>
      <div class="side">
        ${sideSections}
      </div>
    </div>
  </div>
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      academique: {
        label: 'Académique',
        swatch: ['#7f1d1d', '#fef2f2'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 22mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Garamond', 'Georgia', 'Times New Roman', serif; color: #1a1a1a; margin: 0;
         line-height: 1.55; font-size: ${sz(12)}; }
  .header { display: flex; align-items: center; gap: 24px;
            padding-bottom: 14px; margin-bottom: 22px;
            border-bottom: 3px double ${main}; }
  .header-text { flex: 1; min-width: 0; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 0;
                  object-fit: cover; border: 1px solid #1a1a1a; flex-shrink: 0; }
  h1 { margin: 0; font-size: ${sz(24)}; color: #1a1a1a; letter-spacing: 0.5px;
       font-weight: 700; }
  .meta { margin-top: 4px; color: #525252; font-size: ${sz(11)}; font-style: italic; }
  section { margin-bottom: 18px; }
  h2 { font-size: ${sz(13)}; color: ${main}; text-transform: uppercase;
       letter-spacing: 2px; margin: 0 0 10px; padding-bottom: 3px;
       font-weight: 700; border-bottom: 1px solid ${main}; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 10px; padding-left: 20px; position: relative; border-left: none; }
  li::before { content: '§'; color: ${main}; position: absolute; left: 0; top: 0;
               font-weight: 700; }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(12)}; color: #1a1a1a; font-weight: 700; }
  .f-tag { color: ${main}; padding: 0; background: transparent;
           font-size: ${sz(11)}; font-weight: 600; font-variant: small-caps;
           letter-spacing: 1px; }
  .f-tag::before { content: '— '; color: #525252; }
  .f-dates { color: #525252; font-size: ${sz(11)}; margin-top: 2px; font-style: italic; }
  .empty { color: #a3a3a3; font-style: italic; }
  @media print { .no-print { display: none; } html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: ${main}; color: #fff; padding: 10px 18px;
              border: none; border-radius: 0; font-weight: 700;
              cursor: pointer; box-shadow: 0 4px 14px ${main}66; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="header">
    ${photoSrc && profile.photoPosition === 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    <div class="header-text">
      <h1>${esc(fullName)}</h1>
      ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
    </div>
    ${photoSrc && profile.photoPosition !== 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
  </div>
  ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      magazine: {
        label: 'Magazine',
        swatch: ['#dc2626', '#0f172a'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 16mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1a1a1a; margin: 0;
         line-height: 1.45; font-size: ${sz(12)}; }
  .header { display: flex; align-items: flex-end; gap: 22px;
            padding-bottom: 14px; margin-bottom: 26px;
            border-bottom: 6px solid ${main}; }
  .header-text { flex: 1; min-width: 0; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 0;
                  object-fit: cover; flex-shrink: 0; filter: grayscale(0.3); }
  h1 { margin: 0; font-size: ${sz(40)}; color: #0f172a; letter-spacing: -1px;
       font-weight: 900; line-height: 0.95; text-transform: uppercase; }
  h1 span { color: ${main}; }
  .meta { margin-top: 6px; color: #525252; font-size: ${sz(11)};
          text-transform: uppercase; letter-spacing: 2px; font-weight: 600; }
  section { margin-bottom: 22px; }
  h2 { font-size: ${sz(11)}; color: #fff; background: #0f172a;
       padding: 4px 12px; margin: 0 0 12px; letter-spacing: 3px;
       text-transform: uppercase; font-weight: 800; display: inline-block; }
  ul { list-style: none; padding: 0; margin: 0;
       column-count: 1; }
  section[data-type="loisirs"] ul,
  section[data-type="langues"] ul { column-count: 2; column-gap: 24px; }
  li { margin-bottom: 12px; padding-left: 12px; border-left: 4px solid ${main};
       break-inside: avoid; }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(13)}; color: #0f172a; font-weight: 800;
                   text-transform: uppercase; }
  .f-tag { background: transparent; color: ${main}; padding: 0;
           font-size: ${sz(10)}; font-weight: 700; text-transform: uppercase;
           letter-spacing: 1px; }
  .f-tag::before { content: '/ '; }
  .f-dates { color: #525252; font-size: ${sz(11)}; margin-top: 2px;
             font-style: italic; }
  .empty { color: #94a3b8; font-style: italic; }
  @media print { .no-print { display: none; } html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: ${main}; color: #fff; padding: 10px 18px;
              border: none; border-radius: 0; font-weight: 700;
              cursor: pointer; box-shadow: 0 4px 14px ${main}66; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="header">
    ${photoSrc && profile.photoPosition === 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    <div class="header-text">
      <h1>${esc(fullName).split(' ').map((w, i, a) => i === a.length - 1 && a.length > 1 ? `<span>${w}</span>` : w).join(' ')}</h1>
      ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
    </div>
    ${photoSrc && profile.photoPosition !== 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
  </div>
  ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      pastel: {
        label: 'Pastel',
        swatch: ['#fbcfe8', '#bfdbfe'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 14mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Quicksand', 'Helvetica Neue', Arial, sans-serif; color: #3f3d56; margin: 0;
         line-height: 1.55; font-size: ${sz(12)}; background: #fdfcff; }
  .header { display: flex; align-items: center; gap: 22px;
            padding: 18px 22px; margin-bottom: 22px;
            background: linear-gradient(135deg, ${main}55, ${secondary}55);
            border-radius: 24px; }
  .header-text { flex: 1; min-width: 0; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 50%;
                  object-fit: cover; border: 5px solid #fff;
                  box-shadow: 0 4px 14px rgba(63, 61, 86, 0.12); flex-shrink: 0; }
  h1 { margin: 0; font-size: ${sz(26)}; color: #3f3d56; letter-spacing: 0.3px;
       font-weight: 700; }
  .meta { margin-top: 6px; color: #6b6986; font-size: ${sz(11)}; }
  section { margin-bottom: 18px; padding: 14px 18px;
            background: #fff; border-radius: 18px;
            box-shadow: 0 2px 10px rgba(63, 61, 86, 0.06); }
  h2 { font-size: ${sz(13)}; color: #3f3d56; margin: 0 0 10px;
       letter-spacing: 1px; font-weight: 700;
       display: inline-block; padding: 3px 12px;
       background: ${main}66; border-radius: 999px; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 10px; padding-left: 14px; position: relative; border-left: none; }
  li::before { content: '✿'; color: ${main}; position: absolute; left: 0; top: 0; }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(13)}; color: #3f3d56; font-weight: 700; }
  .f-tag { background: ${secondary}88; color: #3f3d56; padding: 2px 10px;
           border-radius: 999px; font-size: ${sz(10)}; font-weight: 600; }
  .f-dates { color: #6b6986; font-size: ${sz(11)}; margin-top: 2px; }
  .empty { color: #b6b3cb; font-style: italic; }
  @media print { .no-print { display: none; } html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: ${main}; color: #3f3d56; padding: 10px 18px;
              border: none; border-radius: 999px; font-weight: 700;
              cursor: pointer; box-shadow: 0 4px 14px ${main}66; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="header">
    ${photoSrc && profile.photoPosition === 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    <div class="header-text">
      <h1>${esc(fullName)}</h1>
      ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
    </div>
    ${photoSrc && profile.photoPosition !== 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
  </div>
  ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      nordique: {
        label: 'Nordique',
        swatch: ['#334155', '#f1f5f9'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 24mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Helvetica Neue', 'Inter', Arial, sans-serif; color: #1f2937; margin: 0;
         line-height: 1.7; font-size: ${sz(11)}; font-weight: 300; }
  .header { display: flex; align-items: center; gap: 26px;
            padding-bottom: 18px; margin-bottom: 36px;
            border-bottom: 1px solid #d1d5db; }
  .header-text { flex: 1; min-width: 0; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 50%;
                  object-fit: cover; flex-shrink: 0; filter: grayscale(0.4); }
  h1 { margin: 0; font-size: ${sz(28)}; color: #111827; letter-spacing: 4px;
       font-weight: 200; text-transform: uppercase; }
  .meta { margin-top: 8px; color: #6b7280; font-size: ${sz(10)};
          letter-spacing: 1.5px; text-transform: uppercase; }
  section { margin-bottom: 28px; }
  h2 { font-size: ${sz(10)}; color: ${main}; text-transform: uppercase;
       letter-spacing: 4px; margin: 0 0 16px; font-weight: 600; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 14px; padding-left: 0; border-left: none; }
  .f-head { display: flex; gap: 12px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(13)}; color: #111827; font-weight: 500;
                   letter-spacing: 0.3px; }
  .f-tag { color: #6b7280; padding: 0; background: transparent;
           font-size: ${sz(11)}; font-weight: 300; }
  .f-tag::before { content: '· '; color: #9ca3af; }
  .f-dates { color: #9ca3af; font-size: ${sz(10)}; margin-top: 4px;
             letter-spacing: 1px; text-transform: uppercase; }
  .empty { color: #d1d5db; font-style: italic; }
  @media print { .no-print { display: none; } html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: ${main}; color: #fff; padding: 10px 18px;
              border: none; border-radius: 0; font-weight: 600;
              cursor: pointer; box-shadow: 0 4px 14px ${main}55;
              letter-spacing: 1px; text-transform: uppercase; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="header">
    ${photoSrc && profile.photoPosition === 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    <div class="header-text">
      <h1>${esc(fullName)}</h1>
      ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
    </div>
    ${photoSrc && profile.photoPosition !== 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
  </div>
  ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      brutaliste: {
        label: 'Brutaliste',
        swatch: ['#000000', '#facc15'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 14mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Courier New', 'Consolas', monospace; color: #000; margin: 0;
         line-height: 1.4; font-size: ${sz(11)}; background: ${secondary}; }
  .header { display: flex; align-items: center; gap: 20px;
            padding: 14px; margin-bottom: 20px;
            background: #000; color: ${secondary};
            border: 4px solid #000; }
  .header-text { flex: 1; min-width: 0; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 0;
                  object-fit: cover; border: 4px solid ${secondary}; flex-shrink: 0;
                  filter: grayscale(1) contrast(1.4); }
  h1 { margin: 0; font-size: ${sz(28)}; color: ${secondary}; letter-spacing: 0.5px;
       font-weight: 900; text-transform: uppercase; }
  .meta { margin-top: 4px; color: ${secondary}; font-size: ${sz(11)};
          text-transform: uppercase; }
  section { margin-bottom: 16px; padding: 12px;
            background: #fff; border: 4px solid #000; }
  h2 { font-size: ${sz(13)}; color: #000; margin: -12px -12px 10px;
       padding: 4px 12px; background: ${secondary};
       border-bottom: 4px solid #000;
       text-transform: uppercase; font-weight: 900; letter-spacing: 1px; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 8px; padding-left: 0; border-left: none;
       padding-bottom: 8px; border-bottom: 2px solid #000; }
  li:last-child { border-bottom: none; padding-bottom: 0; }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(12)}; color: #000; font-weight: 900;
                   text-transform: uppercase; }
  .f-tag { background: #000; color: ${secondary}; padding: 1px 6px;
           border-radius: 0; font-size: ${sz(10)}; font-weight: 700;
           text-transform: uppercase; }
  .f-dates { color: #000; font-size: ${sz(10)}; margin-top: 2px; font-weight: 700; }
  .empty { color: #525252; font-style: italic; }
  @media print {
    .no-print { display: none; }
    body { background: ${secondary}; }
    html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
  }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: #000; color: ${secondary}; padding: 10px 18px;
              border: 3px solid #000; border-radius: 0; font-weight: 900;
              cursor: pointer; text-transform: uppercase; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="header">
    ${photoSrc && profile.photoPosition === 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    <div class="header-text">
      <h1>${esc(fullName)}</h1>
      ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
    </div>
    ${photoSrc && profile.photoPosition !== 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
  </div>
  ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      xmen: {
        label: 'X-Men',
        swatch: ['#fbbf24', '#1e1b4b'],
        build: ({ profile, fullName, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => {
          const xYellow = '#fbbf24';
          const xBlue   = '#1e1b4b';
          const xRed    = '#dc2626';
          return `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; }
  body { font-family: 'Bebas Neue', 'Impact', 'Helvetica Neue', Arial, sans-serif;
         color: #f8fafc; margin: 0; line-height: 1.45; font-size: ${sz(12)};
         background: ${xBlue};
         background-image:
           linear-gradient(45deg, transparent 48%, ${xYellow}10 49%, ${xYellow}10 51%, transparent 52%),
           linear-gradient(-45deg, transparent 48%, ${xYellow}10 49%, ${xYellow}10 51%, transparent 52%);
         background-size: 60px 60px; }
  .header { position: relative; padding: 24mm 18mm 16mm;
            background: linear-gradient(135deg, ${xBlue} 0%, #312e81 100%);
            border-bottom: 6px solid ${xYellow}; overflow: hidden; }
  .header::before { content: 'X'; position: absolute; top: 50%; left: 50%;
                    transform: translate(-50%, -50%) rotate(-12deg);
                    font-family: 'Impact', 'Arial Black', sans-serif;
                    font-size: 360px; font-weight: 900; color: ${xYellow}1a;
                    line-height: 1; letter-spacing: -20px; pointer-events: none; z-index: 0; }
  .header-inner { position: relative; z-index: 1; display: flex;
                  align-items: center; gap: 26px; }
  .header-text { flex: 1; min-width: 0; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px;
                  object-fit: cover; flex-shrink: 0;
                  border: 4px solid ${xYellow};
                  clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
                  background: ${xBlue}; }
  h1 { margin: 0; font-size: ${sz(44)}; color: ${xYellow};
       letter-spacing: 4px; font-weight: 900;
       text-transform: uppercase; line-height: 0.9;
       text-shadow: 3px 3px 0 ${xRed}, 6px 6px 0 #000; }
  .meta { margin-top: 10px; color: ${xYellow}; font-size: ${sz(11)};
          letter-spacing: 4px; text-transform: uppercase;
          font-family: 'Helvetica Neue', Arial, sans-serif; font-weight: 700;
          padding: 3px 10px; background: ${xRed}; display: inline-block;
          transform: skewX(-12deg); }
  .meta > span { display: inline-block; transform: skewX(12deg); }
  .content { padding: 18mm; background: ${xBlue}; }
  section { margin-bottom: 18px; }
  h2 { font-size: ${sz(16)}; color: ${xBlue}; margin: 0 0 12px;
       padding: 4px 14px; background: ${xYellow};
       text-transform: uppercase; letter-spacing: 3px; font-weight: 900;
       display: inline-block; transform: skewX(-12deg);
       box-shadow: 4px 4px 0 ${xRed}; }
  h2 > span { display: inline-block; transform: skewX(12deg); }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 10px; padding: 10px 14px;
       background: #312e81; border-left: 4px solid ${xYellow};
       border-radius: 0; position: relative; }
  li::before { content: 'X'; position: absolute; right: 10px; top: 6px;
               color: ${xYellow}33; font-size: ${sz(28)}; font-weight: 900;
               font-family: 'Impact', sans-serif; line-height: 1; }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(14)}; color: ${xYellow}; font-weight: 900;
                   text-transform: uppercase; letter-spacing: 1.5px; }
  .f-tag { background: ${xRed}; color: #fff; padding: 2px 10px;
           border-radius: 0; font-size: ${sz(10)}; font-weight: 700;
           text-transform: uppercase; letter-spacing: 1px;
           font-family: 'Helvetica Neue', Arial, sans-serif;
           transform: skewX(-12deg); display: inline-block; }
  .f-tag > span { display: inline-block; transform: skewX(12deg); }
  .f-dates { color: #c7d2fe; font-size: ${sz(11)}; margin-top: 3px;
             font-family: 'Helvetica Neue', Arial, sans-serif;
             font-style: italic; letter-spacing: 1px; }
  .empty { color: #6366f1; font-style: italic; }
  @media print {
    .no-print { display: none; }
    body { background: ${xBlue} !important;
           -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: ${xYellow}; color: ${xBlue}; padding: 10px 18px;
              border: 3px solid ${xBlue}; border-radius: 0; font-weight: 900;
              cursor: pointer; text-transform: uppercase; letter-spacing: 1px;
              box-shadow: 4px 4px 0 ${xRed}; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="header">
    <div class="header-inner">
      ${photoSrc && profile.photoPosition === 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
      <div class="header-text">
        <h1>${esc(fullName)}</h1>
        ${birthLine ? `<div class="meta"><span>${esc(birthLine)}</span></div>` : ''}
      </div>
      ${photoSrc && profile.photoPosition !== 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    </div>
  </div>
  <div class="content">
    ${(allSections || '<p class="empty">Aucune section à afficher.</p>')
      .replace(/<h2>([^<]+)<\/h2>/g, '<h2><span>$1</span></h2>')
      .replace(/<span class="f-tag">([^<]+)<\/span>/g, '<span class="f-tag"><span>$1</span></span>')}
  </div>
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`;
        }
      },

      spiderman: {
        label: 'Spider-Man',
        swatch: ['#c8102e', '#0c1c5e'],
        build: ({ profile, fullName, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => {
          const sRed   = '#c8102e';
          const sRedD  = '#8b0a1f';
          const sBlue  = '#0c1c5e';
          const sBlueL = '#1e3a8a';
          // Vraie toile d'araignée : 8 rayons + cercles polygonaux concentriques
          const webSvg = (color, opacity) => {
            const g = `<g fill='none' stroke='${color}' stroke-width='1.2' stroke-opacity='${opacity}' stroke-linecap='round'>` +
              // 8 rayons partant du centre (100,100)
              `<line x1='100' y1='100' x2='100' y2='-30'/>` +
              `<line x1='100' y1='100' x2='192' y2='8'/>` +
              `<line x1='100' y1='100' x2='230' y2='100'/>` +
              `<line x1='100' y1='100' x2='192' y2='192'/>` +
              `<line x1='100' y1='100' x2='100' y2='230'/>` +
              `<line x1='100' y1='100' x2='8' y2='192'/>` +
              `<line x1='100' y1='100' x2='-30' y2='100'/>` +
              `<line x1='100' y1='100' x2='8' y2='8'/>` +
              // 4 anneaux polygonaux qui forment la toile
              `<polygon points='100,75 118,82 125,100 118,118 100,125 82,118 75,100 82,82'/>` +
              `<polygon points='100,50 135,65 150,100 135,135 100,150 65,135 50,100 65,65'/>` +
              `<polygon points='100,25 153,47 175,100 153,153 100,175 47,153 25,100 47,47'/>` +
              `<polygon points='100,0 170,30 200,100 170,170 100,200 30,170 0,100 30,30'/>` +
              `</g>`;
            return `data:image/svg+xml;utf8,` +
              encodeURIComponent(`<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 200' preserveAspectRatio='none'>${g}</svg>`);
          };
          const webWhite = `url("${webSvg('white', '0.45')}")`;
          const webBlue  = `url("${webSvg(sBlue, '0.07')}")`;
          // Petite toile d'angle pour le coin de la photo
          const cornerWebSvg = `data:image/svg+xml;utf8,` + encodeURIComponent(
            `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>` +
              `<g fill='none' stroke='${sBlue}' stroke-width='1.5' stroke-linecap='round'>` +
              `<path d='M 0 0 Q 30 12 0 30 Q 50 18 0 50 Q 70 30 0 70 Q 90 45 0 90'/>` +
              `<line x1='0' y1='0' x2='80' y2='80'/>` +
              `<line x1='0' y1='0' x2='100' y2='30'/>` +
              `<line x1='0' y1='0' x2='30' y2='100'/>` +
              `</g></svg>`);
          return `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; }
  body { font-family: 'Bebas Neue', 'Impact', 'Helvetica Neue', Arial, sans-serif;
         color: #1a1a1a; margin: 0; line-height: 1.45; font-size: ${sz(12)};
         background: #fff; }
  .header { position: relative; padding: 24mm 20mm 20mm;
            background: linear-gradient(135deg, ${sRed} 0%, ${sRedD} 100%);
            color: #fff; overflow: hidden;
            clip-path: polygon(0 0, 100% 0, 100% calc(100% - 28px),
                               calc(75% + 28px) calc(100% - 28px),
                               75% 100%, calc(75% - 28px) calc(100% - 28px),
                               0 calc(100% - 28px)); }
  .header::before { content: ''; position: absolute; inset: 0;
                    background-image: ${webWhite};
                    background-size: 360px 360px;
                    background-position: -80px -80px;
                    background-repeat: no-repeat;
                    opacity: 0.85; pointer-events: none; }
  .header::after { content: ''; position: absolute; inset: 0;
                   background-image: ${webWhite};
                   background-size: 360px 360px;
                   background-position: calc(100% + 80px) calc(100% + 80px);
                   background-repeat: no-repeat;
                   opacity: 0.55; pointer-events: none; transform: scaleX(-1); }
  .header-inner { position: relative; z-index: 2; display: flex;
                  align-items: center; gap: 28px; }
  .header-text { flex: 1; min-width: 0; }
  .photo-wrap { position: relative; flex-shrink: 0;
                width: ${photoPx + 16}px; height: ${photoPx + 16}px; }
  .photo-wrap::before { content: ''; position: absolute; inset: 0;
                        border-radius: 50%;
                        background: conic-gradient(${sBlue} 0 25%, #fff 25% 50%,
                                                   ${sBlue} 50% 75%, #fff 75% 100%); }
  .photo-wrap::after { content: ''; position: absolute; inset: 4px;
                       border-radius: 50%; background: #fff; }
  .header-photo { position: absolute; inset: 8px;
                  width: ${photoPx}px; height: ${photoPx}px; border-radius: 50%;
                  object-fit: cover; z-index: 1;
                  box-shadow: 0 4px 12px rgba(0,0,0,0.35); }
  .photo-corner { position: absolute; bottom: -6px; right: -6px;
                  width: 38px; height: 38px; z-index: 2;
                  background-image: url("${cornerWebSvg}");
                  background-size: contain; background-repeat: no-repeat;
                  transform: rotate(180deg); }
  h1 { margin: 0; font-size: ${sz(46)}; color: #fff;
       letter-spacing: 4px; font-weight: 900;
       text-transform: uppercase; line-height: 0.92;
       text-shadow: 3px 3px 0 ${sBlue}, 6px 6px 0 #000,
                    -1px -1px 0 #fff, 1px -1px 0 #fff,
                    -1px 1px 0 #fff, 1px 1px 0 #fff; }
  .meta { margin-top: 14px; color: #fff; font-size: ${sz(11)};
          letter-spacing: 3px; text-transform: uppercase;
          font-family: 'Helvetica Neue', Arial, sans-serif; font-weight: 800;
          padding: 4px 14px; background: ${sBlue}; display: inline-block;
          border: 2px solid #fff;
          box-shadow: 3px 3px 0 #000;
          clip-path: polygon(8px 0, 100% 0, calc(100% - 8px) 100%, 0 100%); }
  .content { padding: 22mm 18mm 18mm; background: #fff;
             background-image: ${webBlue};
             background-size: 420px 420px;
             background-position: top right;
             background-repeat: no-repeat; }
  section { margin-bottom: 16px; padding: 14px 18px 14px 18px;
            background: #fff;
            border: 3px solid ${sBlue};
            box-shadow: 5px 5px 0 ${sRed};
            position: relative; }
  section::before { content: ''; position: absolute; top: -3px; right: -3px;
                    width: 30px; height: 30px;
                    background: ${sRed};
                    clip-path: polygon(100% 0, 0 0, 100% 100%); }
  section::after { content: ''; position: absolute; top: 2px; right: 2px;
                   width: 22px; height: 22px;
                   background-image: url("${cornerWebSvg}");
                   background-size: contain; background-repeat: no-repeat;
                   transform: scaleX(-1) rotate(180deg);
                   opacity: 0.6; pointer-events: none; }
  h2 { font-size: ${sz(17)}; color: #fff; margin: -14px -18px 14px -18px;
       padding: 6px 22px 6px 18px; background: ${sRed};
       text-transform: uppercase; letter-spacing: 2.5px; font-weight: 900;
       clip-path: polygon(0 0, 100% 0, calc(100% - 14px) 100%, 0 100%);
       border-bottom: 3px solid ${sBlue}; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 10px; padding: 9px 12px 9px 32px;
       background: #f8fafc;
       border-left: 5px solid ${sRed};
       border-right: 1px solid #e2e8f0;
       position: relative; }
  li::before { content: ''; position: absolute; left: 8px; top: 50%;
               transform: translateY(-50%);
               width: 16px; height: 16px; border-radius: 50%;
               background:
                 radial-gradient(circle at center,
                   ${sBlue} 0 28%, #fff 30% 36%,
                   ${sBlue} 38% 50%, transparent 52%); }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(14)}; color: ${sRed}; font-weight: 900;
                   text-transform: uppercase; letter-spacing: 1.2px; }
  .f-tag { background: ${sBlue}; color: #fff; padding: 2px 12px;
           font-size: ${sz(10)}; font-weight: 700;
           text-transform: uppercase; letter-spacing: 1px;
           font-family: 'Helvetica Neue', Arial, sans-serif;
           border: 2px solid #fff;
           box-shadow: 2px 2px 0 ${sRed};
           clip-path: polygon(6px 0, 100% 0, calc(100% - 6px) 100%, 0 100%); }
  .f-dates { color: ${sBlue}; font-size: ${sz(11)}; margin-top: 3px;
             font-family: 'Helvetica Neue', Arial, sans-serif;
             font-style: italic; letter-spacing: 0.5px; font-weight: 600; }
  .empty { color: #94a3b8; font-style: italic; }
  @media print {
    .no-print { display: none; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: ${sRed}; color: #fff; padding: 10px 18px;
              border: 3px solid ${sBlue}; border-radius: 0; font-weight: 900;
              cursor: pointer; text-transform: uppercase; letter-spacing: 1px;
              box-shadow: 4px 4px 0 #000; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="header">
    <div class="header-inner">
      ${photoSrc && profile.photoPosition === 'left' ? `<div class="photo-wrap"><img class="header-photo" src="${photoSrc}" alt=""><div class="photo-corner"></div></div>` : ''}
      <div class="header-text">
        <h1>${esc(fullName)}</h1>
        ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
      </div>
      ${photoSrc && profile.photoPosition !== 'left' ? `<div class="photo-wrap"><img class="header-photo" src="${photoSrc}" alt=""><div class="photo-corner"></div></div>` : ''}
    </div>
  </div>
  <div class="content">
    ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  </div>
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`;
        }
      },

      colore: {
        label: 'Coloré',
        swatch: ['#db2777', '#ea580c'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; }
  body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1e293b; margin: 0;
         line-height: 1.5; font-size: ${sz(12)}; }
  .banner { background: linear-gradient(135deg, ${main}, ${secondary}); color: #fff;
            padding: 24mm 18mm 18mm; display: flex; align-items: center; gap: 24px; }
  .banner-text { flex: 1; min-width: 0; }
  .banner-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 50%;
                  object-fit: cover; border: 4px solid #ffffffcc; flex-shrink: 0; }
  h1 { margin: 0; font-size: ${sz(28)}; color: #fff; letter-spacing: 0.5px;
       font-weight: 700; }
  .banner .meta { margin-top: 6px; color: #ffffffd9; font-size: ${sz(11)}; }
  .content { padding: 18mm; }
  section { margin-bottom: 22px; }
  h2 { font-size: ${sz(14)}; color: ${main}; text-transform: uppercase;
       letter-spacing: 1.5px; margin: 0 0 12px; padding-bottom: 4px;
       border-bottom: 2px solid ${main}; display: inline-block; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 12px; padding: 8px 12px; background: #f8fafc;
       border-left: 4px solid ${secondary}; border-radius: 0 6px 6px 0; }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(13)}; color: #0f172a; }
  .f-tag { background: ${main}; color: #fff; padding: 2px 10px;
           border-radius: 12px; font-size: ${sz(10)}; font-weight: 600; }
  .f-dates { color: #64748b; font-size: ${sz(11)}; margin-top: 2px; }
  .empty { color: #94a3b8; font-style: italic; }
  @media print { .no-print { display: none; } html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; } }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: #fff; color: ${main}; padding: 10px 18px;
              border: none; border-radius: 6px; font-weight: 700;
              cursor: pointer; box-shadow: 0 4px 14px ${main}66; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="banner">
    ${photoSrc && profile.photoPosition === 'left' ? `<img class="banner-photo" src="${photoSrc}" alt="">` : ''}
    <div class="banner-text">
      <h1>${esc(fullName)}</h1>
      ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
    </div>
    ${photoSrc && profile.photoPosition !== 'left' ? `<img class="banner-photo" src="${photoSrc}" alt="">` : ''}
  </div>
  <div class="content">
    ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  </div>
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      cyberpunk: {
        label: 'Cyberpunk',
        swatch: ['#00f0ff', '#ff00aa'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; }
  html, body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { font-family: 'JetBrains Mono', 'Courier New', ui-monospace, monospace;
         color: #e8f7ff; margin: 0; line-height: 1.55; font-size: ${sz(11.5)};
         background: #07070d;
         background-image:
           linear-gradient(${main}14 1px, transparent 1px),
           linear-gradient(90deg, ${main}14 1px, transparent 1px),
           radial-gradient(circle at 80% 0%, ${secondary}33, transparent 50%),
           radial-gradient(circle at 0% 100%, ${main}33, transparent 55%);
         background-size: 28px 28px, 28px 28px, 100% 100%, 100% 100%;
         padding: 16mm 14mm; }
  .frame { border: 1px solid ${main}; box-shadow: 0 0 0 1px ${main}33,
           inset 0 0 60px ${main}1a, 0 0 40px ${main}55; padding: 12mm; position: relative; }
  .frame::before, .frame::after { content: ''; position: absolute; width: 18px; height: 18px;
                                  border: 2px solid ${secondary}; }
  .frame::before { top: -2px; left: -2px; border-right: none; border-bottom: none; }
  .frame::after  { bottom: -2px; right: -2px; border-left: none; border-top: none; }
  .header { display: flex; align-items: center; gap: 18px; padding-bottom: 12px;
            border-bottom: 1px dashed ${main}aa; margin-bottom: 18px; position: relative; }
  .header::after { content: '> SYS://identity.dat'; position: absolute; right: 0; top: -8px;
                   font-size: ${sz(9)}; color: ${secondary}; letter-spacing: 1px; }
  .header-text { flex: 1; min-width: 0; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 0;
                  object-fit: cover; border: 2px solid ${main};
                  box-shadow: 0 0 18px ${main}aa, 0 0 0 4px #07070d, 0 0 0 5px ${secondary}aa;
                  filter: contrast(1.05) saturate(1.1); flex-shrink: 0; }
  h1 { margin: 0; font-size: ${sz(26)}; color: #fff; letter-spacing: 2px;
       text-transform: uppercase; font-weight: 800;
       text-shadow: 0 0 6px ${main}, 0 0 14px ${main}aa, 2px 0 0 ${secondary}88; }
  .meta { margin-top: 6px; color: ${main}; font-size: ${sz(10.5)};
          letter-spacing: 1px; text-transform: uppercase; }
  .meta::before { content: '// '; color: ${secondary}; }
  section { margin-bottom: 18px; }
  h2 { font-size: ${sz(13)}; color: ${secondary}; text-transform: uppercase;
       letter-spacing: 3px; margin: 0 0 10px; padding: 4px 8px;
       background: linear-gradient(90deg, ${secondary}22, transparent 80%);
       border-left: 3px solid ${secondary};
       text-shadow: 0 0 8px ${secondary}aa; }
  h2::before { content: '▸ '; color: ${main}; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 12px; padding: 8px 12px; background: #0c0c18;
       border: 1px solid ${main}55; border-left: 3px solid ${main};
       position: relative; }
  li:hover { box-shadow: 0 0 12px ${main}88; }
  .f-head { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(12)}; color: #fff; font-weight: 700;
                   letter-spacing: 0.5px; text-transform: uppercase; }
  .f-tag { background: ${main}; color: #07070d; padding: 1px 8px;
           border-radius: 0; font-size: ${sz(9.5)}; font-weight: 700;
           letter-spacing: 1px; text-transform: uppercase;
           box-shadow: 0 0 10px ${main}aa, 2px 2px 0 ${secondary}; }
  .f-dates { color: ${secondary}; font-size: ${sz(10)}; margin-top: 2px;
             letter-spacing: 1px; }
  .f-dates::before { content: '[ '; }
  .f-dates::after  { content: ' ]'; }
  .empty { color: ${main}aa; font-style: italic; }
  @media print {
    .no-print { display: none; }
    html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
  }
  .no-print { position: fixed; top: 12px; right: 12px;
              background: ${main}; color: #07070d; padding: 10px 18px;
              border: 2px solid ${secondary}; border-radius: 0; font-weight: 800;
              letter-spacing: 2px; text-transform: uppercase; cursor: pointer;
              font-family: inherit; box-shadow: 0 0 18px ${main}aa, 4px 4px 0 ${secondary}; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">⚡ Imprimer / PDF</button>' : ''}
  <div class="frame">
    <div class="header">
      ${photoSrc && profile.photoPosition === 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
      <div class="header-text">
        <h1>${esc(fullName)}</h1>
        ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
      </div>
      ${photoSrc && profile.photoPosition !== 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    </div>
    ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  </div>
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`
      },

      vogue: {
        label: 'Vogue',
        swatch: ['#e30613', '#000000'],
        build: ({ profile, fullName, main, secondary, sz, photoPx, allSections, birthLine, photoSrc, autoPrint }) => {
          const parts = (fullName || 'Mon CV').trim().split(/\s+/);
          const firstWord = parts[0] || 'CV';
          const restWords = parts.slice(1).join(' ');
          return `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; }
  html, body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { font-family: 'Didot', 'Bodoni 72', 'Bodoni MT', 'Playfair Display', Georgia, serif;
         color: #0a0a0a; margin: 0; line-height: 1.45; font-size: ${sz(11)};
         background: #fff; }

  /* ─── HEADER (compact, photo à côté du texte) ─── */
  .cover { position: relative; padding: 12mm 14mm 14mm;
           background: linear-gradient(135deg, ${main} 0%, ${main} 60%, #1a1a1a 100%);
           color: #fff; overflow: hidden;
           display: grid; grid-template-columns: ${photoSrc ? (profile.photoPosition === 'left' ? `${photoPx}px 1fr` : `1fr ${photoPx}px`) : '1fr'};
           gap: 18px; align-items: center; }
  .cover-text { min-width: 0; ${photoSrc && profile.photoPosition === 'left' ? 'order: 2;' : ''} }
  .issue-bar { display: flex; justify-content: space-between;
               align-items: center; padding: 4px 0 8px;
               border-bottom: 1px solid #ffffff66;
               font-family: 'Helvetica Neue', Arial, sans-serif;
               font-size: ${sz(7.5)}; letter-spacing: 3px; text-transform: uppercase;
               font-weight: 700; margin-bottom: 10px; }
  .issue-bar .price { background: #000; color: #fff; padding: 2px 8px; letter-spacing: 1.5px; }
  .wordmark { font-family: 'Didot', 'Bodoni 72', 'Bodoni MT', Georgia, serif;
              font-size: ${sz(54)}; line-height: 0.9; letter-spacing: -2px;
              color: #fff; font-weight: 400; text-transform: uppercase;
              text-shadow: 2px 2px 0 #000;
              margin: 0; padding: 0; }
  .wordmark .last { display: block; color: #000; font-size: ${sz(20)};
                    letter-spacing: 6px; line-height: 1.2;
                    margin-top: 4px; text-shadow: none; font-weight: 400; }
  .cover-photo { display: block; width: ${photoPx}px;
                 height: ${photoPx}px; object-fit: cover;
                 border: 3px solid #fff; box-shadow: 4px 4px 0 #000;
                 ${profile.photoPosition === 'left' ? 'order: 1; justify-self: start;' : 'justify-self: end;'} }
  .cover-tagline { margin-top: 8px; font-family: 'Didot', 'Bodoni 72', Georgia, serif;
                   font-style: italic; font-size: ${sz(11)}; color: #ffffffdd;
                   letter-spacing: 0.5px; }

  /* ─── CORPS ─── */
  .interior { padding: 14mm 16mm 18mm; background: #fff; }
  .interior-sub { text-align: center; font-family: 'Helvetica Neue', Arial, sans-serif;
                  font-size: ${sz(8.5)}; letter-spacing: 6px; text-transform: uppercase;
                  color: #999; margin: 0 0 22px; padding-bottom: 10px;
                  border-bottom: 1px solid #000; font-weight: 700; }

  section { margin-bottom: 32px; break-inside: avoid; page-break-inside: avoid; }
  h2 { font-family: 'Didot', 'Bodoni 72', Georgia, serif;
       font-size: ${sz(48)}; line-height: 1; color: #fff;
       background: ${main}; padding: 10px 16px;
       margin: 0 0 18px; text-transform: uppercase;
       letter-spacing: -1px; font-weight: 400;
       display: inline-block; box-shadow: 6px 6px 0 #000;
       transform: rotate(-1.5deg); }
  section:nth-of-type(even) h2 { background: #000; color: ${main};
                                  box-shadow: 6px 6px 0 ${main};
                                  transform: rotate(1.5deg); }
  ul { list-style: none; padding: 0; margin: 0;
       column-count: 1; }
  li { margin-bottom: 18px; padding: 14px 18px;
       background: #fafafa; border-left: 6px solid ${main};
       break-inside: avoid; page-break-inside: avoid;
       position: relative; }
  li::before { content: '✦'; position: absolute; right: 14px; top: 12px;
               color: ${main}; font-size: ${sz(16)}; }
  .f-head { display: flex; gap: 12px; align-items: baseline; flex-wrap: wrap;
            margin-bottom: 6px; }
  .f-head strong { font-family: 'Didot', 'Bodoni 72', Georgia, serif;
                   font-size: ${sz(20)}; color: #000; font-weight: 700;
                   letter-spacing: 0; line-height: 1.1; }
  .f-tag { font-family: 'Helvetica Neue', Arial, sans-serif;
           background: ${main}; color: #fff;
           padding: 3px 10px; font-size: ${sz(9)}; font-weight: 800;
           letter-spacing: 3px; text-transform: uppercase; }
  .f-dates { font-family: 'Didot', 'Bodoni 72', Georgia, serif;
             font-style: italic; color: ${main};
             font-size: ${sz(13)}; margin-top: 4px;
             letter-spacing: 0.5px; font-weight: 600; }
  .empty { color: #999; font-style: italic; text-align: center; padding: 40px; font-size: ${sz(14)}; }

  @media print {
    .no-print { display: none; }
    html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
  }
  .no-print { position: fixed; top: 12px; right: 12px; z-index: 999;
              background: ${main}; color: #fff; padding: 14px 26px;
              border: 3px solid #000; border-radius: 0;
              font-family: 'Helvetica Neue', Arial, sans-serif;
              font-weight: 900; letter-spacing: 4px; text-transform: uppercase;
              font-size: 12px; cursor: pointer;
              box-shadow: 6px 6px 0 #000; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">Imprimer / PDF</button>' : ''}

  <div class="cover">
    <div class="cover-text">
      <div class="issue-bar">
        <span>Édition ${new Date().getFullYear()} · Vol. I</span>
        <span class="price">CV № ${String(new Date().getFullYear()).slice(-2)}</span>
      </div>
      <h1 class="wordmark">
        ${esc(firstWord.toUpperCase())}
        ${restWords ? `<span class="last">${esc(restWords.toUpperCase())}</span>` : ''}
      </h1>
      ${birthLine ? `<div class="cover-tagline">${esc(birthLine)}</div>` : ''}
    </div>
    ${photoSrc ? `<img class="cover-photo" src="${photoSrc}" alt="">` : ''}
  </div>

  <div class="interior">
    ${allSections || '<p class="empty">Aucune section à afficher.</p>'}
  </div>

  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`;
        }
      },
    };

    // Page A4 « canvas » : header + sections posés en absolu (disposition libre).
    function buildFreeLayoutPage(o) {
      const { profile, fullName, main, secondary, sz, photoPx,
              allSections, birthLine, photoSrc, autoPrint } = o;
      const hp = profile.headerPos || {};
      const hx = Number(hp.x) || 0, hy = Number(hp.y) || 0, hw = Number(hp.w) || 754;
      const hh = Number(hp.h) || 0;
      return `<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>CV — ${esc(fullName)}</title>
<style>
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; background: #e8eaf0; }
  body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1e293b; line-height: 1.5; }
  .cv-page { position: relative; width: 794px; min-height: 1123px;
             margin: 0; background: #fff; }
  [data-sec-index] { position: absolute; background: #fff; padding: 4px 6px; }
  .cv-head { display: flex; align-items: center; gap: 18px; }
  .cv-head .ht { flex: 1; min-width: 0; }
  .cv-head h1 { margin: 0; font-size: ${sz(26)}; color: #0f172a; letter-spacing: 0.5px; }
  .cv-head .meta { color: #64748b; font-size: ${sz(11)}; margin-top: 4px; }
  .header-photo { width: ${photoPx}px; height: ${photoPx}px; border-radius: 50%;
                  object-fit: cover; border: 3px solid ${main}; flex-shrink: 0; }
  h2 { font-size: ${sz(14)}; color: ${main}; text-transform: uppercase;
       letter-spacing: 1px; margin: 0 0 8px; padding-bottom: 4px;
       border-bottom: 2px solid ${main}; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { margin-bottom: 10px; padding-left: 12px; border-left: 2px solid ${secondary}; }
  .f-head { display: flex; gap: 8px; align-items: baseline; flex-wrap: wrap; }
  .f-head strong { font-size: ${sz(13)}; color: #0f172a; }
  .f-tag { background: ${main}1a; color: ${main}; padding: 1px 7px;
           border-radius: 4px; font-size: ${sz(10)}; font-weight: 600; }
  .f-dates { color: #64748b; font-size: ${sz(11)}; margin-top: 2px; }
  .empty { color: #94a3b8; font-style: italic; }
  .cv-textblock { font-size: ${sz(13)}; color: #1e293b; line-height: 1.5; }
  .cv-tb-text { white-space: pre-wrap; word-break: break-word; outline: none; min-height: 1.2em; }
  .cv-tb-text:focus { outline: 1px solid #7c6cf7; outline-offset: 2px; }
  .cv-imgblock { overflow: hidden; }
  .cv-imgblock img { width: 100%; height: 100%; object-fit: contain; display: block; }
  .cv-img-empty { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
                  color: #94a3b8; font-size: ${sz(11)}; border: 1px dashed #cbd5e1; box-sizing: border-box; }
  @media print {
    html, body { background: #fff; }
    .no-print { display: none; }
    html, body, * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
  }
  .no-print { position: fixed; top: 12px; right: 12px; background: ${main}; color: #fff;
              padding: 10px 18px; border: none; border-radius: 6px; font-weight: 700;
              cursor: pointer; box-shadow: 0 4px 14px ${main}66; z-index: 99999; }
</style>
</head><body>
  ${autoPrint ? '<button class="no-print" onclick="window.print()">📄 Imprimer / Sauvegarder en PDF</button>' : ''}
  <div class="cv-page">
    <div class="cv-head" data-sec-index="header" style="position:absolute;left:${hx}px;top:${hy}px;width:${hw}px;${hh ? 'min-height:' + hh + 'px;' : ''}">
      ${photoSrc && profile.photoPosition === 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
      <div class="ht">
        <h1>${esc(fullName)}</h1>
        ${birthLine ? `<div class="meta">${esc(birthLine)}</div>` : ''}
      </div>
      ${photoSrc && profile.photoPosition !== 'left' ? `<img class="header-photo" src="${photoSrc}" alt="">` : ''}
    </div>
    ${allSections || ''}
    ${(profile.canvasBlocks || []).map(b => {
      const bx = Number(b.x) || 0, by = Number(b.y) || 0;
      const bw = Number(b.w) || 240, bh = Number(b.h) || 60;
      if (b.type === 'image') {
        return `<div class="cv-imgblock" data-sec-index="tb-${esc(b.id)}" `
          + `style="position:absolute;left:${bx}px;top:${by}px;width:${bw}px;height:${bh}px;">`
          + (b.src ? `<img src="${b.src}" alt="">` : `<div class="cv-img-empty">Image</div>`)
          + `</div>`;
      }
      const align = (b.align === 'center' || b.align === 'right') ? b.align : 'left';
      return `<div class="cv-textblock" data-sec-index="tb-${esc(b.id)}" `
        + `style="position:absolute;left:${bx}px;top:${by}px;width:${bw}px;min-height:${bh}px;">`
        + `<div class="cv-tb-text" contenteditable="true" style="text-align:${align}">`
        + `${esc(b.text || '')}</div></div>`;
    }).join('')}
  </div>
  <script>
  (function () {
    // Étend la page pour contenir tous les blocs (gère le multi-page A4).
    function fit() {
      var pg = document.querySelector('.cv-page'); if (!pg) return;
      var max = 1123;
      pg.querySelectorAll('[data-sec-index]').forEach(function (el) {
        var b = el.offsetTop + el.offsetHeight; if (b > max) max = b;
      });
      pg.style.minHeight = (Math.ceil(max / 1123) * 1123) + 'px';
    }
    if (document.readyState !== 'loading') fit();
    else document.addEventListener('DOMContentLoaded', fit);
  })();
  <\/script>
  ${autoPrint ? '<script>window.addEventListener(\'load\', () => setTimeout(() => window.print(), 300));<\/script>' : ''}
</body></html>`;
    }

    function buildCvHtml(profile, { autoPrint = false } = {}) {
      const fullName  = [profile.firstName, profile.lastName].filter(Boolean).join(' ') || 'Mon CV';
      const main      = profile.colors?.main      || COLOR_DEFAULT.main;
      const secondary = profile.colors?.secondary || COLOR_DEFAULT.secondary;
      const fs        = (profile.fontScale || 100) / 100;
      const photoPx   = profile.photoSize || 100;
      const sz        = (pt) => `${(pt * fs).toFixed(1)}pt`;

      const renderSec = (s) => {
        const def = SECTION_DEFS[s.type];
        const items = s.items.filter(it => !it.hidden && isItemFilled(it));
        if (!items.length) return '';
        // data-sec-index = position réelle dans profile.sections (pour l'édition directe).
        const realIdx = profile.sections.indexOf(s);
        let css = '';
        if (profile.freeLayout) {
          // Disposition libre : la section est posée en absolu où l'utilisateur veut.
          const p = s.pos || {};
          css = `position:absolute;left:${Number(p.x) || 0}px;top:${Number(p.y) || 0}px;width:${Number(p.w) || 320}px;`;
          if (Number(p.h) > 0) css += `min-height:${Number(p.h)}px;`;
        } else {
          // Mode flux : taille propre à la section via zoom (texte + espacements).
          const secScale = (s.fontScale || 100) / 100;
          if (secScale !== 1) css = `zoom:${secScale};`;
        }
        const styleAttr = css ? ` style="${css}"` : '';
        return `<section data-type="${s.type}" data-sec-index="${realIdx}"${styleAttr}><h2>${esc(sectionTitle(s))}</h2><ul>${items.map(it => def.renderItem(it, profile)).join('')}</ul></section>`;
      };
      const visible = profile.sections.filter(s => !s.hidden);
      const SIDEBAR_TYPES = new Set(['langues', 'loisirs', 'habilitations', 'competences', 'logiciels']);
      const allSections  = visible.map(renderSec).filter(Boolean).join('');
      const mainSections = visible.filter(s => !SIDEBAR_TYPES.has(s.type)).map(renderSec).filter(Boolean).join('');
      const sideSections = visible.filter(s =>  SIDEBAR_TYPES.has(s.type)).map(renderSec).filter(Boolean).join('');

      const birthLine = birthLineForCv(profile);
      const photoSrc  = (profile.photo && !profile.photoHidden) ? profile.photo : null;

      let html;
      if (profile.freeLayout) {
        // Disposition libre (canvas) : page A4 où chaque bloc est posé en absolu.
        html = buildFreeLayoutPage({
          profile, fullName, main, secondary, sz, photoPx,
          allSections, birthLine, photoSrc, autoPrint,
        });
      } else {
        const tpl = CV_TEMPLATES[profile.template] || CV_TEMPLATES.classique;
        html = tpl.build({
          profile, fullName, main, secondary, fs, photoPx, sz,
          allSections, mainSections, sideSections,
          birthLine, photoSrc, autoPrint,
        });
      }

      // Style global pour les descriptions multiples (s'applique à tous les templates)
      const descCss = `<style>
  ul.item-desc { list-style: disc !important; padding: 4px 0 0 22px !important;
                 margin: 6px 0 0 !important; border: none !important;
                 background: transparent !important;
                 column-count: 1 !important; column-gap: 0 !important;
                 box-shadow: none !important; transform: none !important; }
  ul.item-desc > li { margin: 2px 0 !important; padding: 0 !important;
                      background: transparent !important; border: none !important;
                      border-left: none !important; border-bottom: none !important;
                      box-shadow: none !important; transform: none !important;
                      color: inherit !important; opacity: 1 !important;
                      font-size: 0.92em !important; line-height: 1.45 !important;
                      page-break-inside: avoid !important; break-inside: avoid !important;
                      list-style: disc !important; }
  ul.item-desc > li::before, ul.item-desc > li::after { content: none !important; }
</style>`;
      html = html.replace('</head>', descCss + '</head>');

      // Overrides de couleurs avancées : s'appliquent à TOUS les modèles via !important.
      // Chaque override est opt-in (présent dans profile.colors seulement s'il a été activé dans l'UI).
      const cx = profile.colors || {};
      const rules = [];
      if (cx.text) {
        rules.push(`body, body *:not(svg):not(path):not(circle):not(rect) { color: ${cx.text} !important; }`);
      }
      if (cx.background) {
        rules.push(`html, body { background: ${cx.background} !important; }`);
      }
      if (cx.sidebar) {
        // S'applique aux modèles qui ont une sidebar (.sidebar pour Moderne, etc.).
        rules.push(`.sidebar, aside.sidebar { background: ${cx.sidebar} !important; }`);
      }
      if (cx.title) {
        rules.push(`h2 { color: ${cx.title} !important; border-color: ${cx.title} !important; }`);
        rules.push(`h2::before, h2::after { color: ${cx.title} !important; }`);
      }
      if (cx.border) {
        rules.push(`li { border-left-color: ${cx.border} !important; border-color: ${cx.border} !important; }`);
        rules.push(`li::before { color: ${cx.border} !important; }`);
      }
      if (cx.badge) {
        rules.push(`.f-tag { background: ${cx.badge} !important; }`);
      }
      if (rules.length) {
        const overrideCss = `<style id="cv-color-overrides">\n  ${rules.join('\n  ')}\n</style>`;
        html = html.replace('</head>', overrideCss + '</head>');
      }

      // Override CSS pour la forme de la photo (s'applique à tous les templates)
      if (photoSrc) {
        const shape = profile.photoShape || 'circle';
        const w = photoPx;
        const h = (shape === 'portrait') ? Math.round(photoPx * 4 / 3) : photoPx;
        const radius = shape === 'circle'  ? '50%'
                     : shape === 'rounded' ? '14px'
                     : shape === 'square'  ? '0'
                     : shape === 'portrait' ? '4px'
                     : '0';
        const clip = shape === 'hexagon'
          ? 'polygon(25% 5%, 75% 5%, 100% 50%, 75% 95%, 25% 95%, 0% 50%)'
          : 'none';
        const photoOverride = `<style>
  .header-photo, .banner-photo, .sidebar-photo, .cover-photo {
    width: ${w}px !important;
    height: ${h}px !important;
    border-radius: ${radius} !important;
    clip-path: ${clip} !important;
    -webkit-clip-path: ${clip} !important;
    object-fit: cover !important;
  }
</style>`;
        html = html.replace('</head>', photoOverride + '</head>');
      }

      if (profile.singlePage && !profile.freeLayout) {
        // Compression auto pour GARANTIR une seule page A4.
        // 'zoom' réduit la VRAIE taille de mise en page (donc la hauteur imprimée),
        // contrairement à transform: scale qui n'agit qu'au rendu visuel.
        // Boucle itérative (le rendu des retours à la ligne n'est pas parfaitement
        // linéaire) sans plancher bloquant : la page ne déborde JAMAIS sur une 2e feuille.
        const fitScript = `
<script>
(function () {
  const A4_H = 297 * 96 / 25.4;   // hauteur utile d'une page A4, en px CSS
  const MIN  = 0.2;               // garde-fou anti-zéro (jamais atteint en pratique)
  function fit() {
    document.body.style.zoom = '';
    let scale = 1;
    for (let i = 0; i < 12; i++) {
      const h = document.documentElement.scrollHeight;
      if (h <= A4_H + 1) break;                            // tient : terminé
      scale = Math.max(MIN, scale * (A4_H / h) * 0.995);   // 0,5 % de marge de sécurité
      document.body.style.zoom = scale;
      if (scale <= MIN) break;
    }
  }
  // 2 passes : la seconde après le reflow des polices web (rendu plus précis).
  function run() { fit(); requestAnimationFrame(fit); }
  if (document.readyState === 'complete') run();
  else window.addEventListener('load', run);
})();
<\/script>`;
        html = html.replace('</body>', fitScript + '</body>');
      }
      return html;
    }

    function generateCvPdf() {
      readHeaderFields();
      saveProfileData(currentProfile);
      const html = buildCvHtml(currentProfile, { autoPrint: true });
      const w = window.open('', '_blank');
      if (!w) { toast('Autorise les pop-ups pour générer le CV'); return; }
      w.document.open(); w.document.write(html); w.document.close();
    }

    // Aperçu en direct : rafraîchit l'iframe à chaque changement
    let previewActive = true;
    let previewTimer  = null;
    function refreshPreview() {
      if (!previewActive) return;
      // L'iframe n'existe que quand le modal est ouvert
      const iframe = $('profilePreviewFrame');
      if (!iframe) return;
      clearTimeout(previewTimer);
      previewTimer = setTimeout(() => {
        readHeaderFields();
        iframe.srcdoc = buildCvHtml(currentProfile, { autoPrint: false });
      }, 80);
    }
    function setPreviewActive(on) {
      previewActive = on;
      $('profilePreviewView').hidden = !on;
      // Aperçu masqué → le panneau de réglages prend toute la largeur de la page.
      $('profileOverlay').classList.toggle('preview-off', !on);
      $('profilePreviewToggle').classList.toggle('active', on);
      $('profilePreviewToggle').textContent = on ? '👁️ Aperçu visible' : '👁️ Aperçu caché';
      if (on) refreshPreview();
    }

    // ===================================================================
    //  DIDACTICIEL INTERACTIF — visite guidée des options de l'éditeur
    // ===================================================================
    const TOUR_STEPS = [
      { sel: '#profilePhotoPreview', title: '📷 Ta photo',
        text: 'Clique ici pour ajouter ou changer ta photo. Tu peux aussi régler sa taille et sa forme (cercle, carré…).' },
      { sel: '#profileTemplates', title: '🎨 Modèle de CV',
        text: 'Choisis l’apparence générale : Tableautier, Moderne, etc. Le rendu s’adapte instantanément.' },
      { sel: '#profileColorPresets', title: '🌈 Couleurs',
        text: 'Choisis une palette prête à l’emploi, ou définis tes couleurs principale et secondaire juste au-dessus.' },
      { sel: '#profileColorExtras', title: '✨ Couleurs avancées',
        text: 'Pour aller plus loin : personnalise le texte, le fond, les bordures, les badges…' },
      { sel: '#profileFirstName', title: '🪪 Nom complet',
        text: 'Ton prénom et ton nom, affichés en grand en haut du CV.' },
      { sel: '#profileHeadline', title: '🏷️ Titre / poste',
        text: 'Ton intitulé professionnel (ex. « Électricien — Bâtiment, Tertiaire & Industriel »).' },
      { sel: '#contactList', title: '📇 Coordonnées',
        text: 'Chaque info a son icône (📍 lieu, 📞 téléphone, 🌐 site web…). Modifie le texte, ou retire une info avec ✕.' },
      { sel: '#contactAddBtn', title: '➕ Ajouter une info',
        text: 'Ajoute autant d’informations que tu veux : téléphone, e-mail, site web, LinkedIn…' },
      { sel: '#profileSummary', title: '📝 Profil / accroche',
        text: 'Ton résumé de présentation, affiché en haut du CV. Quelques phrases qui te résument.' },
      { sel: '#profileSections', title: '🧩 Sections',
        text: 'Expérience, Formation, Compétences, Langues… Ajoute, retire et réorganise les éléments de chaque section.' },
      { sel: 'label[for="profileSinglePage"]', title: '📄 Tenir sur une page',
        text: 'Active cette option pour forcer le CV à tenir sur une seule page A4 (compression automatique).' },
      { sel: '#profileFontScale', title: '🔤 Taille du texte',
        text: 'Ajuste la densité : agrandis ou réduis le texte pour mieux remplir la page.' },
      { sel: '#profilePreviewToggle', title: '👁️ Aperçu en direct',
        text: 'Affiche ou masque l’aperçu. Masqué, le formulaire occupe toute la largeur de la page.' },
      { sel: '#profileGenerate', title: '📄 Générer le PDF',
        text: 'Ouvre la version imprimable de ton CV, prête à enregistrer en PDF.' },
      { sel: '#profileViewLink', title: '🔍 Aperçu plein écran',
        text: 'Ouvre l’aperçu façon CV dans un nouvel onglet (idéal pour vérifier le rendu final).' },
      { sel: '#profileSave', title: '💾 Enregistrer',
        text: 'Sauvegarde tes modifications. Tes CV sont aussi enregistrés automatiquement au fil de l’eau.' },
      { sel: '#profileExport', title: '📤 Export / Import',
        text: 'Sauvegarde ton profil dans un fichier, ou recharge-le plus tard. Pratique pour faire des copies.' },
    ];

    let tourIndex = 0, tourSpot = null, tourTip = null, tourBlock = null, tourOnResize = null, tourOnKey = null;

    function injectTourStyles() {
      if (document.getElementById('cvTourStyles')) return;
      const s = document.createElement('style');
      s.id = 'cvTourStyles';
      s.textContent = ''
        + '.cvtour-block{position:fixed;inset:0;z-index:99999;}'
        + '.cvtour-spot{position:fixed;z-index:100000;border-radius:10px;pointer-events:none;'
        + 'box-shadow:0 0 0 3px #5b95ff,0 0 0 9999px rgba(8,14,28,.66);transition:all .2s ease;}'
        + '.cvtour-tip{position:fixed;z-index:100001;max-width:320px;background:#1d2b4d;color:#eef3fb;'
        + 'border:1px solid #3c5078;border-radius:12px;padding:16px;box-shadow:0 16px 40px rgba(0,0,0,.5);'
        + 'font:14px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;}'
        + '.cvtour-tip h4{margin:0 0 6px;font-size:15px;color:#38d8ee;}'
        + '.cvtour-tip p{margin:0 0 14px;color:#cdd9ef;}'
        + '.cvtour-row{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;row-gap:10px;}'
        + '.cvtour-count{font-size:12px;color:#aabbd6;}'
        + '.cvtour-btns{display:flex;gap:6px;flex-wrap:wrap;}'
        + '.cvtour-btns button{font:inherit;border:none;border-radius:8px;padding:7px 12px;cursor:pointer;font-weight:600;font-size:13px;}'
        + '.cvtour-skip{background:transparent;color:#aabbd6;}'
        + '.cvtour-prev{background:#16264a;color:#bcd0ef;}'
        + '.cvtour-next{background:linear-gradient(135deg,#5b95ff,#b39bff);color:#fff;}';
      document.head.appendChild(s);
    }

    function startTour() {
      if (typeof openProfile === 'function' && $('profileOverlay') && $('profileOverlay').hidden) {
        try { openProfile(); } catch (e) {}
      }
      injectTourStyles();
      if (!tourBlock) {
        tourBlock = document.createElement('div'); tourBlock.className = 'cvtour-block';
        tourSpot  = document.createElement('div'); tourSpot.className  = 'cvtour-spot';
        tourTip   = document.createElement('div'); tourTip.className   = 'cvtour-tip';
        document.body.append(tourBlock, tourSpot, tourTip);
      }
      tourOnResize = () => {
        const st = TOUR_STEPS[tourIndex]; const el = st && document.querySelector(st.sel);
        if (el) positionTour(el, st, tourIndex);
      };
      tourOnKey = (e) => {
        if (e.key === 'Escape') endTour();
        else if (e.key === 'ArrowRight') tourGoto(tourIndex + 1, 1);
        else if (e.key === 'ArrowLeft')  tourGoto(tourIndex - 1, -1);
      };
      window.addEventListener('resize', tourOnResize);
      document.addEventListener('keydown', tourOnKey);
      setTimeout(() => tourGoto(0, 1), 200);
    }

    function endTour() {
      [tourBlock, tourSpot, tourTip].forEach(el => el && el.remove());
      tourBlock = tourSpot = tourTip = null;
      if (tourOnResize) window.removeEventListener('resize', tourOnResize);
      if (tourOnKey) document.removeEventListener('keydown', tourOnKey);
      try { localStorage.setItem('cvTourDone', '1'); } catch (e) {}
    }

    // Avance/recule en sautant les étapes dont la cible est absente ou masquée.
    function tourGoto(start, dir) {
      let i = start;
      while (i >= 0 && i < TOUR_STEPS.length) {
        const el = document.querySelector(TOUR_STEPS[i].sel);
        if (el && el.offsetParent !== null) { renderTourStep(i, el); return; }
        i += dir;
      }
      if (dir > 0) endTour();
    }

    function renderTourStep(i, el) {
      tourIndex = i;
      el.scrollIntoView({ block: 'center' });   // instantané : position fiable
      setTimeout(() => positionTour(el, TOUR_STEPS[i], i), 120);
    }

    function positionTour(el, step, i) {
      if (!tourTip || !tourSpot) return;
      const r = el.getBoundingClientRect();
      const pad = 6;
      tourSpot.style.left   = (r.left - pad) + 'px';
      tourSpot.style.top    = (r.top - pad) + 'px';
      tourSpot.style.width  = (r.width + pad * 2) + 'px';
      tourSpot.style.height = (r.height + pad * 2) + 'px';

      const last = (i === TOUR_STEPS.length - 1);
      tourTip.innerHTML = '<h4>' + step.title + '</h4><p>' + step.text + '</p>'
        + '<div class="cvtour-row"><span class="cvtour-count">' + (i + 1) + ' / ' + TOUR_STEPS.length + '</span>'
        + '<span class="cvtour-btns">'
        + '<button class="cvtour-skip">Quitter</button>'
        + '<button class="cvtour-prev">Précédent</button>'
        + '<button class="cvtour-next">' + (last ? 'Terminer' : 'Suivant') + '</button>'
        + '</span></div>';
      tourTip.querySelector('.cvtour-skip').onclick = endTour;
      tourTip.querySelector('.cvtour-prev').onclick = () => tourGoto(i - 1, -1);
      tourTip.querySelector('.cvtour-next').onclick = () => tourGoto(i + 1, 1);

      const tipW = 320, vw = window.innerWidth, vh = window.innerHeight;
      tourTip.style.left = '0px'; tourTip.style.top = '0px';
      const th = tourTip.offsetHeight;
      let top = r.bottom + 12;
      if (top + th > vh - 8) top = r.top - th - 12;      // pas de place dessous → au-dessus
      top = Math.max(8, Math.min(top, vh - th - 8));      // garde TOUTE la bulle (boutons inclus) visible
      let left = r.left;
      if (left + tipW > vw - 8) left = vw - tipW - 8;
      if (left < 8) left = 8;
      tourTip.style.left = left + 'px';
      tourTip.style.top  = top + 'px';
    }

    // Ajoute le bouton « 🎓 Tutoriel » dans l'en-tête des réglages (une seule fois).
    function ensureTourButton() {
      if (document.getElementById('profileTourBtn')) return;
      const tgl = $('profilePreviewToggle');
      if (!tgl) return;
      const b = document.createElement('button');
      b.id = 'profileTourBtn';
      b.type = 'button';
      b.className = tgl.className;
      b.textContent = '🎓 Tutoriel';
      b.title = 'Visite guidée des options';
      b.addEventListener('click', startTour);
      tgl.parentElement.insertBefore(b, tgl);
    }

    // ===================================================================
    //  ÉDITION DIRECTE DANS L'APERÇU
    //  Mode flux   : ✥ déplacer (réordonner) · ⤡ taille (%) de la section.
    //  Mode canvas : ✥ déplacer (XY libre) · ↔ largeur · ↕ hauteur · ⤡ les deux.
    //  Les poignées n'apparaissent QUE dans l'aperçu (jamais dans le PDF).
    // ===================================================================
    const PREVIEW_EDIT_CSS = `
  [data-sec-index] { position: relative; }
  [data-sec-index]:hover { outline: 1px dashed #7c6cf7; outline-offset: 3px; }
  .cv-handle {
    position: absolute; width: 22px; height: 22px; box-sizing: border-box;
    display: flex; align-items: center; justify-content: center;
    background: #7c6cf7; color: #fff; border-radius: 6px;
    font-size: 13px; line-height: 1;
    box-shadow: 0 2px 8px rgba(0,0,0,.35); z-index: 9999;
    user-select: none; -webkit-user-select: none; touch-action: none;
  }
  .cv-move-handle   { top: 6px;    right: 6px; cursor: grab; }
  .cv-resize-handle { bottom: 6px; right: 6px; cursor: nwse-resize; }
  .cv-handle-se { bottom: 6px; right: 6px; cursor: nwse-resize; }
  .cv-handle-e  { top: 50%; right: 6px; margin-top: -11px; cursor: ew-resize; }
  .cv-handle-s  { bottom: 6px; left: 50%; margin-left: -11px; cursor: ns-resize; }
  .cv-del-handle { top: 6px; left: 6px; background: #ef4444; cursor: pointer; }
  .cv-handle:active { cursor: grabbing; }
  /* Repères d'alignement magnétique (snap) */
  .cv-guide { position: absolute; z-index: 9998; pointer-events: none;
              display: none; background: #ff2d9b; }
  .cv-guide-v { width: 1px;  top: 0; bottom: 0; }
  .cv-guide-h { height: 1px; left: 0; right: 0; }
  .cv-dragging { opacity: .45; }
  .cv-active   { outline: 2px solid #7c6cf7 !important; outline-offset: 3px; }
  .cv-size-badge {
    position: absolute; top: 7px; right: 34px;
    background: #0e1325; color: #fff; padding: 4px 10px;
    border-radius: 999px; font-size: 12px; font-weight: 700;
    z-index: 9999; opacity: 0; transition: opacity .1s;
    pointer-events: none; white-space: nowrap;
  }
  .cv-size-badge.show { opacity: 1; }
  /* Barre d'alignement du texte d'un bloc libre */
  .cv-align-bar { position: absolute; top: -30px; left: 0;
                  display: flex; gap: 3px; z-index: 9999; }
  .cv-align-btn {
    width: 24px; height: 24px; box-sizing: border-box;
    display: flex; align-items: center; justify-content: center;
    background: #0e1325; color: #fff; border-radius: 5px; cursor: pointer;
    font-size: 12px; font-weight: 700; box-shadow: 0 2px 6px rgba(0,0,0,.3);
    user-select: none; -webkit-user-select: none; touch-action: none;
  }
  .cv-align-btn.active { background: #7c6cf7; }
  @media print { .cv-handle, .cv-size-badge, .cv-align-bar { display: none !important; } }
`;

    // Injecte les poignées d'édition dans l'aperçu après chaque rafraîchissement.
    function setupPreviewEditing() {
      const iframe = $('profilePreviewFrame');
      let doc;
      try { doc = iframe.contentDocument; } catch (_) { return; }
      if (!doc || !doc.body) return;

      if (!doc.getElementById('cv-edit-style')) {
        const st = doc.createElement('style');
        st.id = 'cv-edit-style';
        st.textContent = PREVIEW_EDIT_CSS;
        (doc.head || doc.body).appendChild(st);
      }

      const mkHandle = (cls, sym, title) => {
        const h = doc.createElement('div');
        h.className = 'cv-handle ' + cls;
        h.textContent = sym;
        h.title = title;
        h.setAttribute('contenteditable', 'false'); // isole la poignée d'un bloc éditable
        return h;
      };

      const free = !!currentProfile.freeLayout;
      doc.querySelectorAll('[data-sec-index]').forEach(sec => {
        if (sec.querySelector(':scope > .cv-handle')) return; // déjà équipé
        const badge = doc.createElement('div');
        badge.className = 'cv-size-badge';
        badge.setAttribute('contenteditable', 'false');

        const mv = mkHandle('cv-move-handle', '✥',
          free ? 'Glisser pour déplacer ce bloc où tu veux'
               : 'Glisser pour déplacer cette section');
        sec.appendChild(mv);
        attachPreviewMove(doc, sec, mv);

        // Bloc de texte libre : édition du texte en place + alignement + supprimer.
        const sid = sec.dataset.secIndex || '';
        if (sid.indexOf('tb-') === 0) {
          const blockOf = () => (currentProfile.canvasBlocks || []).find(x => x.id === sid.slice(3));
          const isImage = (blockOf() || {}).type === 'image';
          if (!isImage) {
            // Bloc de texte : édition en place + barre d'alignement.
            const editable = sec.querySelector('.cv-tb-text');
            if (editable) {
              editable.addEventListener('input', () => {
                const b = blockOf();
                if (b) b.text = editable.innerText;
              });
            }
            // Barre d'alignement du texte : Gauche / Centre / Droite
            const bar = doc.createElement('div');
            bar.className = 'cv-align-bar';
            bar.setAttribute('contenteditable', 'false');
            [['left', 'G', 'Aligner le texte à gauche'],
             ['center', 'C', 'Centrer le texte'],
             ['right', 'D', 'Aligner le texte à droite']].forEach(([val, label, title]) => {
              const btn = doc.createElement('div');
              btn.className = 'cv-align-btn';
              btn.textContent = label;
              btn.title = title;
              const cur = (blockOf() || {}).align || 'left';
              if (cur === val) btn.classList.add('active');
              btn.addEventListener('pointerdown', (ev) => {
                ev.preventDefault();
                ev.stopPropagation();
                const b = blockOf();
                if (!b) return;
                b.align = val;
                if (editable) editable.style.textAlign = val;
                bar.querySelectorAll('.cv-align-btn').forEach(x => x.classList.remove('active'));
                btn.classList.add('active');
              });
              bar.appendChild(btn);
            });
            sec.appendChild(bar);
          }

          const del = mkHandle('cv-del-handle', '✕', isImage ? 'Supprimer cette image' : 'Supprimer ce bloc de texte');
          sec.appendChild(del);
          del.addEventListener('pointerdown', (ev) => {
            ev.preventDefault();
            ev.stopPropagation();
            currentProfile.canvasBlocks = (currentProfile.canvasBlocks || [])
              .filter(x => x.id !== sid.slice(3));
            refreshPreview();
          });
        }

        if (free) {
          // Canvas : 3 poignées de redimensionnement indépendantes.
          const he  = mkHandle('cv-handle-e',  '↔', 'Glisser : largeur seulement');
          const hs  = mkHandle('cv-handle-s',  '↕', 'Glisser : hauteur seulement');
          const hse = mkHandle('cv-handle-se', '⤡', 'Glisser : largeur + hauteur');
          sec.appendChild(he);
          sec.appendChild(hs);
          sec.appendChild(hse);
          attachCanvasResize(doc, sec, he,  badge, 'x');
          attachCanvasResize(doc, sec, hs,  badge, 'y');
          attachCanvasResize(doc, sec, hse, badge, 'xy');
        } else {
          // Mode flux : une poignée pour la taille (%) de la section.
          const rz = mkHandle('cv-resize-handle', '⤡',
            'Glisser pour agrandir / rétrécir cette section');
          sec.appendChild(rz);
          attachFlowResize(doc, sec, rz, badge);
        }
        sec.appendChild(badge);
      });
    }

    // Renvoie l'objet position {x,y,w} d'un bloc de l'aperçu (le crée si besoin).
    function canvasPosOf(el) {
      const id = el.dataset.secIndex;
      if (id === 'header') {
        if (!currentProfile.headerPos) currentProfile.headerPos = { x: 32, y: 24, w: 730 };
        return currentProfile.headerPos;
      }
      if (id.indexOf('tb-') === 0) {
        // Bloc de texte libre : l'objet bloc porte directement x/y/w/h.
        return (currentProfile.canvasBlocks || []).find(b => b.id === id.slice(3)) || null;
      }
      const s = currentProfile.sections[Number(id)];
      if (!s) return null;
      if (!s.pos) s.pos = { x: 32, y: 24, w: 360 };
      return s.pos;
    }

    // Recalcule la hauteur de la page canvas (multiple de A4) après une édition.
    function refitCanvasPage(doc) {
      const pg = doc.querySelector('.cv-page');
      if (!pg) return;
      let max = 1123;
      pg.querySelectorAll('[data-sec-index]').forEach(el => {
        const b = el.offsetTop + el.offsetHeight;
        if (b > max) max = b;
      });
      pg.style.minHeight = (Math.ceil(max / 1123) * 1123) + 'px';
    }

    // Crée (une fois) et renvoie les 2 repères d'alignement magnétique.
    function getSnapGuides(doc) {
      const host = doc.querySelector('.cv-page') || doc.body;
      let gx = doc.getElementById('cv-guide-x');
      if (!gx) {
        gx = doc.createElement('div');
        gx.id = 'cv-guide-x';
        gx.className = 'cv-guide cv-guide-v';
        host.appendChild(gx);
      }
      let gy = doc.getElementById('cv-guide-y');
      if (!gy) {
        gy = doc.createElement('div');
        gy.id = 'cv-guide-y';
        gy.className = 'cv-guide cv-guide-h';
        host.appendChild(gy);
      }
      return { gx, gy };
    }

    // ── Déplacer un bloc en le glissant dans l'aperçu ──
    function attachPreviewMove(doc, sec, handle) {
      handle.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        e.stopPropagation();
        try { handle.setPointerCapture(e.pointerId); } catch (_) {}

        // === Disposition libre : déplacement XY absolu avec aimant d'alignement ===
        if (currentProfile.freeLayout) {
          const pos = canvasPosOf(sec);
          if (!pos) return;
          const startX = e.clientX, startY = e.clientY;
          const baseX = Number(pos.x) || 0, baseY = Number(pos.y) || 0;
          const w = sec.offsetWidth, h = sec.offsetHeight;
          sec.classList.add('cv-active');

          // Cibles d'alignement : bord gauche / centre / bord droit (page + autres
          // blocs) pour X, et bord haut / centre / bas pour Y.
          const page  = doc.querySelector('.cv-page');
          const pageW = page ? page.offsetWidth : 794;
          const xTargets = [0, pageW / 2, pageW];
          const yTargets = [];
          doc.querySelectorAll('[data-sec-index]').forEach(el => {
            if (el === sec) return;
            const l = el.offsetLeft, t = el.offsetTop;
            const ew = el.offsetWidth, eh = el.offsetHeight;
            xTargets.push(l, l + ew / 2, l + ew);
            yTargets.push(t, t + eh / 2, t + eh);
          });

          const SNAP = 7; // distance d'accrochage en px
          const { gx, gy } = getSnapGuides(doc);

          const onMove = (ev) => {
            let nx = Math.round(baseX + (ev.clientX - startX));
            let ny = Math.round(baseY + (ev.clientY - startY));

            // Aimant horizontal (axe X)
            let snapGX = null, bestX = SNAP + 1;
            [[nx, 0], [nx + w / 2, w / 2], [nx + w, w]].forEach(([line, off]) => {
              xTargets.forEach(tg => {
                const d = Math.abs(line - tg);
                if (d <= SNAP && d < bestX) { bestX = d; snapGX = tg; nx = Math.round(tg - off); }
              });
            });
            // Aimant vertical (axe Y)
            let snapGY = null, bestY = SNAP + 1;
            [[ny, 0], [ny + h / 2, h / 2], [ny + h, h]].forEach(([line, off]) => {
              yTargets.forEach(tg => {
                const d = Math.abs(line - tg);
                if (d <= SNAP && d < bestY) { bestY = d; snapGY = tg; ny = Math.round(tg - off); }
              });
            });

            sec.style.left = nx + 'px';
            sec.style.top  = ny + 'px';
            pos.x = nx; pos.y = ny;

            // Repères « aimant » : ligne rose là où c'est aligné
            if (snapGX !== null) { gx.style.left = snapGX + 'px'; gx.style.display = 'block'; }
            else gx.style.display = 'none';
            if (snapGY !== null) { gy.style.top = snapGY + 'px'; gy.style.display = 'block'; }
            else gy.style.display = 'none';
          };
          const onUp = () => {
            doc.removeEventListener('pointermove', onMove);
            doc.removeEventListener('pointerup', onUp);
            doc.removeEventListener('pointercancel', onUp);
            sec.classList.remove('cv-active');
            gx.style.display = 'none';
            gy.style.display = 'none';
            refitCanvasPage(doc);
          };
          doc.addEventListener('pointermove', onMove);
          doc.addEventListener('pointerup', onUp);
          doc.addEventListener('pointercancel', onUp);
          return;
        }

        // === Mode flux : réordonnancement vertical ===
        const parent = sec.parentElement;
        let moved = false;
        sec.classList.add('cv-dragging', 'cv-active');
        const onMove = (ev) => {
          const sibs = Array.from(parent.children).filter(c =>
            c.tagName === 'SECTION' && c.hasAttribute('data-sec-index') && c !== sec);
          for (const sib of sibs) {
            const r = sib.getBoundingClientRect();
            const mid = r.top + r.height / 2;
            const secAfterSib = sib.compareDocumentPosition(sec) & 4; // DOCUMENT_POSITION_FOLLOWING
            if (ev.clientY < mid && secAfterSib) {
              parent.insertBefore(sec, sib); moved = true; break;
            }
            if (ev.clientY > mid && !secAfterSib) {
              parent.insertBefore(sec, sib.nextSibling); moved = true; break;
            }
          }
        };
        const onUp = () => {
          doc.removeEventListener('pointermove', onMove);
          doc.removeEventListener('pointerup', onUp);
          doc.removeEventListener('pointercancel', onUp);
          sec.classList.remove('cv-dragging', 'cv-active');
          if (moved) commitPreviewReorder();
        };
        doc.addEventListener('pointermove', onMove);
        doc.addEventListener('pointerup', onUp);
        doc.addEventListener('pointercancel', onUp);
      });
    }

    // Lit l'ordre visuel des sections dans l'aperçu et l'applique aux données.
    function commitPreviewReorder() {
      const doc = $('profilePreviewFrame').contentDocument;
      if (!doc) return;
      const order = Array.from(doc.querySelectorAll('section[data-sec-index]'))
        .map(el => Number(el.dataset.secIndex));
      const slots  = order.slice().sort((a, b) => a - b);
      const picked = order.map(i => currentProfile.sections[i]);
      slots.forEach((slot, k) => { currentProfile.sections[slot] = picked[k]; });
      renderProfileSections(); // resynchronise le formulaire + l'aperçu
    }

    // ── Canvas : redimensionner un bloc selon un axe (x = largeur, y = hauteur, xy = les deux) ──
    function attachCanvasResize(doc, sec, handle, badge, axis) {
      handle.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        e.stopPropagation();
        try { handle.setPointerCapture(e.pointerId); } catch (_) {}
        const pos = canvasPosOf(sec);
        if (!pos) return;
        const startX = e.clientX, startY = e.clientY;
        const baseW = Number(pos.w) || sec.offsetWidth  || 320;
        const baseH = Number(pos.h) || sec.offsetHeight || 120;
        const wantX = axis === 'x' || axis === 'xy';
        const wantY = axis === 'y' || axis === 'xy';
        sec.classList.add('cv-active');
        badge.classList.add('show');
        const refreshBadge = () => {
          const w = Math.round(pos.w || baseW), h = Math.round(pos.h || baseH);
          badge.textContent = axis === 'x' ? w + ' px (L)'
                            : axis === 'y' ? h + ' px (H)'
                            : w + ' × ' + h + ' px';
        };
        refreshBadge();
        const onMove = (ev) => {
          if (wantX) {
            let w = Math.round(baseW + (ev.clientX - startX));
            w = Math.max(120, Math.min(794, w));
            sec.style.width = w + 'px';
            pos.w = w;
          }
          if (wantY) {
            let h = Math.round(baseH + (ev.clientY - startY));
            h = Math.max(40, Math.min(2200, h));
            // Image = hauteur fixe ; bloc texte = hauteur mini (s'étire avec le contenu).
            if (sec.classList.contains('cv-imgblock')) sec.style.height = h + 'px';
            else sec.style.minHeight = h + 'px';
            pos.h = h;
          }
          refreshBadge();
        };
        const onUp = () => {
          doc.removeEventListener('pointermove', onMove);
          doc.removeEventListener('pointerup', onUp);
          doc.removeEventListener('pointercancel', onUp);
          sec.classList.remove('cv-active');
          badge.classList.remove('show');
          refitCanvasPage(doc);
        };
        doc.addEventListener('pointermove', onMove);
        doc.addEventListener('pointerup', onUp);
        doc.addEventListener('pointercancel', onUp);
      });
    }

    // ── Mode flux : la poignée règle la TAILLE (%) de la section ──
    function attachFlowResize(doc, sec, handle, badge) {
      handle.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        e.stopPropagation();
        try { handle.setPointerCapture(e.pointerId); } catch (_) {}
        const idx = Number(sec.dataset.secIndex);
        const target = currentProfile.sections[idx];
        if (!target) return;
        const startScale = target.fontScale || 100;
        const startX = e.clientX, startY = e.clientY;
        let current = startScale, moved = false;
        sec.classList.add('cv-active');
        badge.classList.add('show');
        badge.textContent = current + ' %';
        const onMove = (ev) => {
          moved = true;
          const delta = ((ev.clientX - startX) + (ev.clientY - startY)) / 2;
          current = Math.round((startScale + delta * 0.5) / 5) * 5; // pas de 5 %
          current = Math.max(60, Math.min(150, current));
          sec.style.zoom = current / 100;
          badge.textContent = current + ' %';
        };
        const onUp = () => {
          doc.removeEventListener('pointermove', onMove);
          doc.removeEventListener('pointerup', onUp);
          doc.removeEventListener('pointercancel', onUp);
          sec.classList.remove('cv-active');
          badge.classList.remove('show');
          if (moved && current !== startScale) {
            target.fontScale = current;
            renderProfileSections(); // resynchronise le curseur du formulaire + l'aperçu
          }
        };
        doc.addEventListener('pointermove', onMove);
        doc.addEventListener('pointerup', onUp);
        doc.addEventListener('pointercancel', onUp);
      });
    }

    // Bascule en disposition libre : empile les blocs sous le header pour un
    // point de départ propre, puis l'utilisateur les place comme il veut.
    function enterFreeLayout() {
      const iframe = $('profilePreviewFrame');
      let doc;
      try { doc = iframe.contentDocument; } catch (_) {}
      let cy = 120;
      if (doc) {
        doc.querySelectorAll('section[data-sec-index]').forEach(el => {
          const s = currentProfile.sections[Number(el.dataset.secIndex)];
          if (!s || s.pos) return;
          const h = el.getBoundingClientRect().height || 130;
          s.pos = { x: 32, y: Math.round(cy), w: 730 };
          cy += h * 1.4 + 48;
        });
      }
      // Sections sans aperçu mesurable : empilées avec un écart fixe.
      currentProfile.sections.forEach(s => {
        if (s.pos) return;
        s.pos = { x: 32, y: Math.round(cy), w: 730 };
        cy += 210;
      });
      if (!currentProfile.headerPos) currentProfile.headerPos = { x: 32, y: 24, w: 730 };
      currentProfile.freeLayout = true;
      refreshPreview();
    }

    // Liaisons de l'éditeur : actives uniquement sur la page builder (présence du DOM).
    if (document.getElementById('profileOverlay')) {
    $('profileClose').addEventListener('click', closeProfile);
    $('profileOverlay').addEventListener('click', (e) => {
      if (e.target.id === 'profileOverlay') closeProfile();
    });
    $('profilePreviewToggle').addEventListener('click', () => setPreviewActive(!previewActive));
    // À chaque rebuild de l'aperçu, on réinjecte les poignées d'édition directe.
    $('profilePreviewFrame').addEventListener('load', () => {
      if (previewActive) setupPreviewEditing();
    });
    // Bascule disposition libre (canvas) ⇄ flux normal.
    $('profileFreeLayout').addEventListener('change', (e) => {
      if (e.target.checked) {
        enterFreeLayout();
      } else {
        currentProfile.freeLayout = false;
        refreshPreview();
      }
    });
    // Ajoute un bloc de texte libre sur le canvas.
    $('profileAddTextBlock').addEventListener('click', () => {
      if (!Array.isArray(currentProfile.canvasBlocks)) currentProfile.canvasBlocks = [];
      const n = currentProfile.canvasBlocks.length;
      currentProfile.canvasBlocks.push({
        id: 'b' + Date.now().toString(36),
        x: 70 + (n % 6) * 26, y: 110 + (n % 6) * 26,
        w: 300, h: 64, text: 'Nouveau bloc de texte', align: 'left',
      });
      if (!currentProfile.freeLayout) {
        $('profileFreeLayout').checked = true;
        enterFreeLayout();        // bascule en canvas (déclenche refreshPreview)
      } else {
        refreshPreview();
      }
      toast('Bloc ajouté — clique dedans dans l\'aperçu pour écrire');
    });
    // Ajoute une image libre sur le canvas (séparée de la photo de profil).
    $('profileAddImageBlock').addEventListener('click', () => $('profileAddImageInput').click());
    $('profileAddImageInput').addEventListener('change', async (e) => {
      const file = e.target.files && e.target.files[0];
      e.target.value = ''; // autorise de re-choisir le même fichier
      if (!file) return;
      let src;
      try { src = await processPhoto(file); }
      catch (err) { toast(err.message || 'Image invalide'); return; }
      if (!Array.isArray(currentProfile.canvasBlocks)) currentProfile.canvasBlocks = [];
      const n = currentProfile.canvasBlocks.length;
      currentProfile.canvasBlocks.push({
        id: 'i' + Date.now().toString(36),
        type: 'image',
        x: 80 + (n % 6) * 26, y: 130 + (n % 6) * 26,
        w: 160, h: 160, src,
      });
      if (!currentProfile.freeLayout) {
        $('profileFreeLayout').checked = true;
        enterFreeLayout();        // bascule en canvas (déclenche refreshPreview)
      } else {
        refreshPreview();
      }
      toast('Image ajoutée — glisse ✥ pour la placer, ⤡ pour la taille');
    });
    // Tout changement de champ rafraîchit l'aperçu (délégation sur le formulaire)
    document.querySelector('#profileOverlay .modal-body').addEventListener('input',  refreshPreview);
    document.querySelector('#profileOverlay .modal-body').addEventListener('change', refreshPreview);
    $('profileSave').addEventListener('click', () => {
      readHeaderFields();
      saveProfileData(currentProfile);
      toast('Profil enregistré');
    });
    $('profileGenerate').addEventListener('click', generateCvPdf);

    $('profileExport').addEventListener('click', () => {
      readHeaderFields();
      const data = JSON.stringify(currentProfile, null, 2);
      const blob = new Blob([data], { type: 'application/json' });
      const url  = URL.createObjectURL(blob);
      const a    = document.createElement('a');
      const today = new Date().toISOString().slice(0, 10);
      const slug  = (currentProfile.firstName || currentProfile.lastName)
        ? `${currentProfile.firstName || ''}-${currentProfile.lastName || ''}`.toLowerCase()
            .replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '')
        : 'profil';
      a.href = url;
      a.download = `cv-profile-${slug}-${today}.json`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
      toast('Profil exporté');
    });

    $('profileImport').addEventListener('click', () => $('profileImportFile').click());
    $('profileImportFile').addEventListener('change', async (e) => {
      const file = e.target.files && e.target.files[0];
      e.target.value = ''; // permet de re-sélectionner le même fichier
      if (!file) return;
      if (!confirm('Remplacer le profil actuel par le contenu de ce fichier ? Le profil en cours sera écrasé.')) return;
      try {
        const text = await file.text();
        const parsed = JSON.parse(text);
        if (!parsed || typeof parsed !== 'object') throw new Error('JSON invalide');
        // On passe par window.__CV_PROFILE__ + loadProfile() pour la migration auto
        window.__CV_PROFILE__ = parsed;
        currentProfile = loadProfile();
        saveProfileData(currentProfile);
        // Re-render complet du modal et de l'aperçu
        openProfile();
        toast('Profil importé');
      } catch (err) {
        toast('Erreur d\'import : ' + (err.message || 'fichier invalide'));
      }
    });
    ['profileColorMain', 'profileColorSecondary'].forEach(id => {
      $(id).addEventListener('input', () => {
        // On garde les overrides avancés (text, background, sidebar…) intacts.
        currentProfile.colors.main      = $('profileColorMain').value;
        currentProfile.colors.secondary = $('profileColorSecondary').value;
        renderColorPresets();
      });
    });
    $('profileColorReset').addEventListener('click', () => {
      $('profileColorMain').value      = COLOR_DEFAULT.main;
      $('profileColorSecondary').value = COLOR_DEFAULT.secondary;
      currentProfile.colors = { ...COLOR_DEFAULT };
      renderColorPresets();
      renderColorExtras();
      refreshPreview();
    });

    $('profilePhotoBtn').addEventListener('click', () => $('profilePhotoInput').click());
    $('profilePhotoPreview').addEventListener('click', () => $('profilePhotoInput').click());
    $('profilePhotoInput').addEventListener('change', async (e) => {
      const file = e.target.files?.[0];
      if (!file) return;
      try {
        currentProfile.photo = await processPhoto(file);
        currentProfile.photoHidden = false;
        refreshPhotoUI();
        refreshPreview();
      } catch (err) {
        toast('Erreur photo : ' + err.message);
      } finally {
        e.target.value = '';
      }
    });
    $('profilePhotoRemove').addEventListener('click', () => {
      currentProfile.photo = null;
      currentProfile.photoHidden = false;
      refreshPhotoUI();
      refreshPreview();
    });
    $('profilePhotoInclude').addEventListener('change', (e) => {
      currentProfile.photoHidden = !e.target.checked;
    });
    $('profileFontScale').addEventListener('input', (e) => {
      $('profileFontScaleVal').textContent = e.target.value + ' %';
    });
    $('profilePhotoSize').addEventListener('input', (e) => {
      $('profilePhotoSizeVal').textContent = e.target.value + ' px';
    });
    document.querySelectorAll('.photo-pos-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        currentProfile.photoPosition = btn.dataset.pos === 'left' ? 'left' : 'right';
        document.querySelectorAll('.photo-pos-btn').forEach(b => {
          b.classList.toggle('active', b.dataset.pos === currentProfile.photoPosition);
        });
        refreshPreview();
      });
    });
    document.querySelectorAll('.photo-shape-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        currentProfile.photoShape = btn.dataset.shape;
        document.querySelectorAll('.photo-shape-btn').forEach(b => {
          b.classList.toggle('active', b.dataset.shape === currentProfile.photoShape);
        });
        refreshPreview();
      });
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !$('profileOverlay').hidden) closeProfile();
    });

      openProfile();
    }

  // Exposé global : permet à cv_view.php (lecture seule) de rendre le CV
  // EXACTEMENT comme l'aperçu de l'éditeur, sans charger toute l'UI d'édition.
  window.renderCvDocument = function (autoPrint) {
    return buildCvHtml(loadProfile(), { autoPrint: !!autoPrint });
  };
})();
