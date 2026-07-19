(function () {
    // ===== Aperçu image de couverture =====
    var fileInput = document.querySelector('input[type="file"][name="image"]');
    var preview = document.getElementById('image-preview');
    var previewWrap = document.getElementById('image-preview-wrap');
    var pvCoverImg  = document.getElementById('pv-cover-img');
    var pvCoverWrap = document.getElementById('pv-cover-wrap');

    if (fileInput && preview) {
        fileInput.addEventListener('change', function () {
            var file = fileInput.files && fileInput.files[0];
            if (!file) {
                previewWrap.hidden = true;
                preview.removeAttribute('src');
                return;
            }
            if (!file.type.startsWith('image/')) {
                previewWrap.hidden = true;
                alert("Le fichier sélectionné n'est pas une image.");
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                previewWrap.hidden = true;
                alert("Image trop lourde (max 5 Mo).");
                fileInput.value = '';
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                previewWrap.hidden = false;
                if (pvCoverImg && pvCoverWrap) {
                    pvCoverImg.src = e.target.result;
                    pvCoverWrap.hidden = false;
                }
            };
            reader.readAsDataURL(file);
        });
    }

    // ===== Galerie multi-photos =====
    var galleryInput = document.getElementById('gallery-input');
    var galleryList  = document.getElementById('gallery-list');
    var pvGallery    = document.getElementById('pv-gallery');

    // Captions/positions sont indexées par l'ordre — on conserve les fichiers dans un DataTransfer pour préserver l'ordre.
    var galleryFiles = []; // [{file, dataUrl, caption}]

    function rebuildInputFiles() {
        // Reconstruit le FileList de l'input à partir de galleryFiles (ordre conservé)
        var dt = new DataTransfer();
        galleryFiles.forEach(function (item) {
            dt.items.add(item.file);
        });
        galleryInput.files = dt.files;
    }

    function renderGalleryEditor() {
        if (!galleryList) return;
        galleryList.innerHTML = '';
        galleryFiles.forEach(function (item, index) {
            var row = document.createElement('div');
            row.className = 'gallery-item';

            var img = document.createElement('img');
            img.src = item.dataUrl;
            row.appendChild(img);

            var meta = document.createElement('div');
            meta.className = 'gallery-meta';

            var capInput = document.createElement('input');
            capInput.type = 'text';
            capInput.name = isEditPage() ? 'new_captions[]' : 'captions[]';
            capInput.placeholder = 'Légende (optionnel)';
            capInput.value = item.caption || '';
            capInput.addEventListener('input', function () {
                item.caption = capInput.value;
                renderGalleryPreview();
            });
            meta.appendChild(capInput);

            var posLabel = document.createElement('label');
            posLabel.className = 'row-label';
            posLabel.textContent = 'Ordre ';
            var posInput = document.createElement('input');
            posInput.type = 'number';
            posInput.name = isEditPage() ? 'new_positions[]' : 'positions[]';
            posInput.value = index;
            posInput.min = 0;
            posLabel.appendChild(posInput);
            meta.appendChild(posLabel);

            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'btn-danger';
            rm.textContent = 'Retirer';
            rm.addEventListener('click', function () {
                galleryFiles.splice(index, 1);
                rebuildInputFiles();
                renderGalleryEditor();
                renderGalleryPreview();
            });
            meta.appendChild(rm);

            row.appendChild(meta);
            galleryList.appendChild(row);
        });
    }

    function renderGalleryPreview() {
        if (!pvGallery) return;
        // Conserve les figures existantes (photos déjà en BDD) — celles-ci ont une classe spéciale
        // Ici, on régénère depuis zéro : on combine les existantes (rendues côté serveur) + nouvelles
        // Pour simplifier, on garde les existantes intactes dans le DOM initial, et on ajoute/retire les "new"
        // Mais comme c'est complexe, on remplace tout par : existantes (depuis data) + nouvelles
        // Solution simple : laisser les existantes au-dessus et n'ajouter que les nouvelles en bas.

        // Retire les anciennes "nouvelles" du preview
        Array.prototype.slice.call(pvGallery.querySelectorAll('.gallery-fig.is-new')).forEach(function (el) {
            el.remove();
        });

        galleryFiles.forEach(function (item) {
            var fig = document.createElement('figure');
            fig.className = 'gallery-fig is-new';
            var img = document.createElement('img');
            img.src = item.dataUrl;
            fig.appendChild(img);
            if (item.caption) {
                var cap = document.createElement('figcaption');
                cap.textContent = item.caption;
                fig.appendChild(cap);
            }
            pvGallery.appendChild(fig);
        });

        pvGallery.hidden = pvGallery.children.length === 0;
    }

    function isEditPage() {
        return !!document.querySelector('input[name="id"]');
    }

    if (galleryInput) {
        galleryInput.addEventListener('change', function () {
            var files = Array.prototype.slice.call(galleryInput.files);
            var remaining = files.length;
            if (remaining === 0) return;

            files.forEach(function (file, i) {
                if (!file.type.startsWith('image/')) {
                    remaining--;
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert(file.name + " : trop lourd (max 5 Mo), ignoré.");
                    remaining--;
                    return;
                }
                var reader = new FileReader();
                reader.onload = function (e) {
                    galleryFiles.push({ file: file, dataUrl: e.target.result, caption: '' });
                    remaining--;
                    if (remaining === 0) {
                        rebuildInputFiles();
                        renderGalleryEditor();
                        renderGalleryPreview();
                    }
                };
                reader.readAsDataURL(file);
            });
        });
    }

    // ===== Aperçu live du contenu =====
    var titreInput   = document.querySelector('input[name="titre"]');
    var contenuInput = document.querySelector('textarea[name="contenu"]');
    var sourcesInput = document.querySelector('textarea[name="sources"]');

    var pvTitre   = document.getElementById('pv-titre');
    var pvContenu = document.getElementById('pv-contenu');
    var pvSources = document.getElementById('pv-sources');

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function renderSources() {
        if (!pvSources || !sourcesInput) return;
        var lines = sourcesInput.value.split(/\r?\n/)
            .map(function (l) { return l.trim(); })
            .filter(function (l) { return /^https?:\/\//i.test(l); });
        if (lines.length === 0) {
            pvSources.hidden = true;
            pvSources.innerHTML = '';
            return;
        }
        pvSources.hidden = false;
        pvSources.innerHTML = '<h3>Sources</h3><ul>' +
            lines.map(function (u) {
                var safe = escapeHtml(u);
                return '<li><a href="' + safe + '" target="_blank" rel="noopener">' + safe + '</a></li>';
            }).join('') + '</ul>';
    }

    function sync() {
        if (pvTitre && titreInput) {
            pvTitre.textContent = titreInput.value || 'Titre de l\'article';
        }
        if (pvContenu && contenuInput) {
            pvContenu.innerHTML = escapeHtml(contenuInput.value).replace(/\r?\n/g, '<br>') || '<em class="muted">Commence à écrire pour voir l\'aperçu…</em>';
        }
        renderSources();
    }

    [titreInput, contenuInput, sourcesInput].forEach(function (el) {
        if (el) el.addEventListener('input', sync);
    });
    sync();

    // ===== Drag-and-drop de l'agencement + aperçu live =====
    var pvArticle   = document.getElementById('pv-article');
    var layoutList  = document.getElementById('layout-list');

    function reorderPreviewFromList() {
        if (!pvArticle || !layoutList) return;
        var order = Array.prototype.slice.call(layoutList.querySelectorAll('.layout-item'))
            .map(function (el) { return el.getAttribute('data-block'); });

        order.forEach(function (key) {
            var block = pvArticle.querySelector('[data-block="' + key + '"]');
            if (block) pvArticle.appendChild(block);
        });
    }

    function refreshPositionLabels() {
        if (!layoutList) return;
        var items = layoutList.querySelectorAll('.layout-item');
        items.forEach(function (item, i) {
            var pos = i + 1;
            var hidden = item.querySelector('input[name^="layout_pos["]');
            if (hidden) hidden.value = pos;
            var label = item.querySelector('.layout-pos-num');
            if (label) label.textContent = pos;
        });
        reorderPreviewFromList();
    }

    if (layoutList) {
        var dragging = null;

        layoutList.addEventListener('dragstart', function (e) {
            var item = e.target.closest('.layout-item');
            if (!item) return;
            dragging = item;
            item.classList.add('is-dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                // Firefox a besoin d'un setData pour démarrer le drag
                try { e.dataTransfer.setData('text/plain', item.dataset.block); } catch (_) {}
            }
        });

        layoutList.addEventListener('dragend', function (e) {
            var item = e.target.closest('.layout-item');
            if (item) item.classList.remove('is-dragging');
            dragging = null;
            refreshPositionLabels();
        });

        layoutList.addEventListener('dragover', function (e) {
            if (!dragging) return;
            e.preventDefault();
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';

            var over = e.target.closest('.layout-item');
            if (!over || over === dragging) return;
            var rect = over.getBoundingClientRect();
            var after = (e.clientY - rect.top) > rect.height / 2;
            if (after) {
                if (over.nextSibling !== dragging) layoutList.insertBefore(dragging, over.nextSibling);
            } else {
                if (over !== dragging) layoutList.insertBefore(dragging, over);
            }
            refreshPositionLabels();
        });

        layoutList.addEventListener('drop', function (e) {
            e.preventDefault();
        });

        // Init
        refreshPositionLabels();
    }
})();
