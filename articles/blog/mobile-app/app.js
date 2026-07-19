/* ============================================================
   Mon Blog — App mobile (v14 : qualité MAX — 4096px / 96% / serveur 12 Mo)
   ============================================================ */

const APP_VERSION = 'v17';
const API_BASE = (() => {
    const here = window.location.href;
    const root = here.replace(/\/mobile-app\/.*$/, '/');
    return root + 'api';
})();

// ============ ÉTAT ============
function safeReadUser() {
    const raw = localStorage.getItem('user');
    if (!raw || raw === 'undefined' || raw === 'null') return null;
    try { return JSON.parse(raw); }
    catch { localStorage.removeItem('user'); return null; }
}
function safeReadToken() {
    const raw = localStorage.getItem('token');
    if (!raw || raw === 'undefined' || raw === 'null') return null;
    return raw;
}
const state = {
    token: safeReadToken(),
    user:  safeReadUser(),
    currentArticleId: null,
    history: [],
    galleryFiles: [], // photos sélectionnées pour la galerie
};

const MAX_IMG_BYTES = 14 * 1024 * 1024;      // limite serveur PHP (14 Mo)
const MAX_RAW_BYTES = 100 * 1024 * 1024;     // refus pur si > 100 Mo (sécurité mémoire)
const MAX_GALLERY = 10;
// Compression : on garde la QUALITÉ MAX. On ne redimensionne que si vraiment énorme.
const COMPRESS_MAX_DIM = 4096;               // 4K+ : aucune perte visible
const COMPRESS_QUALITY = 0.96;               // qualité JPEG 96% = indistinguable de l'original
// On ne re-compresse QUE si l'image dépasse la limite serveur : tout ce que le
// serveur accepte (<= 14 Mo) part INTACT, sans aucune perte de qualité.
const NO_COMPRESS_BELOW_BYTES = MAX_IMG_BYTES;

/**
 * Compresse et redimensionne automatiquement une image pour qu'elle
 * passe le quota serveur. Renvoie un File JPEG prêt à uploader.
 * Si l'image est déjà petite, la renvoie telle quelle.
 */
async function compressImage(file) {
    if (!file) return file;
    if (!/^image\//.test(file.type)) return file;
    // Les GIF animés perdraient l'animation si on les passe en canvas
    if (file.type === 'image/gif') return file;

    // Image déjà légère et pas trop grande : on ne touche pas (qualité 100% préservée)
    if (file.size < NO_COMPRESS_BELOW_BYTES) return file;

    try {
        const dataUrl = await new Promise((resolve, reject) => {
            const r = new FileReader();
            r.onload  = () => resolve(r.result);
            r.onerror = () => reject(new Error('Lecture image impossible'));
            r.readAsDataURL(file);
        });
        const img = await new Promise((resolve, reject) => {
            const i = new Image();
            i.onload  = () => resolve(i);
            i.onerror = () => reject(new Error('Image illisible'));
            i.src = dataUrl;
        });

        // Calcul des dimensions cibles (uniquement si trop grand)
        let w = img.naturalWidth;
        let h = img.naturalHeight;
        const longSide = Math.max(w, h);
        if (longSide > COMPRESS_MAX_DIM) {
            const ratio = COMPRESS_MAX_DIM / longSide;
            w = Math.round(w * ratio);
            h = Math.round(h * ratio);
        }

        // Premier essai à la qualité MAX (92%)
        let quality = COMPRESS_QUALITY;
        let blob = await encodeJpeg(img, w, h, quality);

        // Si encore trop lourd (>5 Mo limite serveur), on baisse PROGRESSIVEMENT la qualité
        // pour rester sous la limite SANS sacrifier la qualité plus que nécessaire
        while (blob && blob.size > MAX_IMG_BYTES && quality > 0.5) {
            quality -= 0.07;
            blob = await encodeJpeg(img, w, h, quality);
        }

        if (!blob) return file;

        const newName = (file.name || 'photo').replace(/\.[a-z0-9]+$/i, '') + '.jpg';
        const compressed = new File([blob], newName, { type: 'image/jpeg', lastModified: Date.now() });

        // On garde le compressé seulement s'il est vraiment plus léger
        return (compressed.size < file.size) ? compressed : file;
    } catch (e) {
        console.warn('compressImage error:', e);
        return file;
    }
}

function encodeJpeg(img, w, h, quality) {
    const canvas = document.createElement('canvas');
    canvas.width  = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    // Fond blanc au cas où PNG transparent encodé en JPEG
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, w, h);
    ctx.drawImage(img, 0, 0, w, h);
    return new Promise((resolve) => canvas.toBlob(b => resolve(b), 'image/jpeg', quality));
}

// ============ TOAST ============
function toast(message, type = 'success') {
    const stack = document.getElementById('toast-stack');
    const t = document.createElement('div');
    t.className = 'toast toast-' + type;
    const icon = { success: '✅', error: '⚠️', info: 'ℹ️' }[type] || '✅';
    t.innerHTML = `<span class="toast-icon">${icon}</span><span class="toast-msg">${escapeHtml(message)}</span>`;
    stack.appendChild(t);
    if ('vibrate' in navigator) {
        navigator.vibrate(type === 'error' ? [100, 50, 100] : 80);
    }
    setTimeout(() => {
        t.classList.add('toast-out');
        setTimeout(() => t.remove(), 400);
    }, 3500);
}

// ============ API ============
async function api(path, options = {}) {
    const headers = { 'Content-Type': 'application/json' };
    if (state.token) headers['Authorization'] = 'Bearer ' + state.token;

    const res = await fetch(API_BASE + path, {
        ...options,
        headers: { ...headers, ...(options.headers || {}) },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const err = new Error(data.error || `Erreur ${res.status}`);
        err.status = res.status;
        throw err;
    }
    return data;
}

// ============ NAVIGATION ============
function showView(name) {
    document.querySelectorAll('.view').forEach(v => v.hidden = true);
    const target = document.getElementById('view-' + name);
    if (target) target.hidden = false;
    window.scrollTo(0, 0);
}

function goBack() {
    state.history.pop();
    const prev = state.history[state.history.length - 1] || (state.token ? 'list' : 'login');
    if (prev === 'list') loadList();
    else showView(prev);
}

document.querySelectorAll('[data-back]').forEach(b => b.addEventListener('click', goBack));

// ============ LOGIN ============
document.getElementById('form-login').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('btn-login');
    const info = document.getElementById('login-info');
    info.hidden = true;

    setLoading(btn, true);
    try {
        const data = await api('/login.php', {
            method: 'POST',
            body: JSON.stringify({
                email: form.email.value.trim(),
                password: form.password.value,
            }),
        });
        state.token = data.token;
        state.user  = data.user;
        localStorage.setItem('token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));
        form.reset();
        toast(`Connecté en tant que ${data.user.prenom} ${data.user.nom}`, 'success');
        await loadList();
    } catch (err) {
        info.textContent = '⚠️ ' + err.message;
        info.hidden = false;
        toast(err.message, 'error');
    } finally {
        setLoading(btn, false);
    }
});

// ============ LOGOUT ============
document.getElementById('btn-logout').addEventListener('click', () => {
    if (!confirm('Se déconnecter ?')) return;
    state.token = null;
    state.user  = null;
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    state.history = [];
    toast('Déconnecté', 'info');
    showView('login');
});

// ============ LISTE ============
async function loadList() {
    showView('list');
    state.history = ['list'];
    updateUserBadge();
    const list = document.getElementById('article-list');
    list.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

    try {
        const data = await api('/articles.php');
        if (data.articles.length === 0) {
            list.innerHTML = '<div class="empty"><p>Aucun article pour l\'instant.</p><p class="muted">Tape sur <strong>+</strong> pour publier le premier.</p></div>';
            return;
        }
        list.innerHTML = data.articles.map(renderCard).join('');
        list.querySelectorAll('.card').forEach(c => {
            c.addEventListener('click', () => loadArticle(+c.dataset.id));
        });
    } catch (err) {
        if (err.status === 401) { logoutSilently(); showView('login'); return; }
        list.innerHTML = `<div class="error-box">⚠️ ${escapeHtml(err.message)}</div>`;
    }
}

function renderCard(a) {
    return `
        <div class="card" data-id="${a.id}">
            <h2>${escapeHtml(a.titre)}${+a.visible === 0 ? ' <span class="badge-hidden">🔒 masqué</span>' : ''}</h2>
            <div class="meta">
                <span class="author">👤 ${escapeHtml(a.prenom + ' ' + a.nom)}</span>
                <span>· 📅 ${formatDate(a.created_at)}</span>
                <span>· 💬 ${a.nb_comments}</span>
                ${a.nb_children > 0 ? `<span>· 📑 ${a.nb_children}</span>` : ''}
            </div>
            ${a.image_url ? `<img src="${escapeAttr(a.image_url)}" alt="" loading="lazy">` : ''}
            <p class="excerpt">${escapeHtml(a.excerpt)}${a.excerpt.length >= 280 ? '…' : ''}</p>
        </div>
    `;
}

// ============ DÉTAIL ============
async function loadArticle(id) {
    state.currentArticleId = id;
    showView('article');
    state.history.push('article');
    document.getElementById('article-title').textContent = 'Chargement…';
    const main = document.getElementById('article-detail');
    main.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

    try {
        const data = await api('/article.php?id=' + id);
        const a = data.article;
        document.getElementById('article-title').textContent = a.titre;

        // Bouton Modifier : visible pour tout utilisateur connecté.
        // L'API se charge ensuite de bloquer si la personne n'est pas l'auteur ou admin.
        const canEdit = !!state.user;
        const isAuthorOrAdmin = state.user && (
            String(a.author_id) === String(state.user.id) || +state.user.is_admin === 1
        );
        const canAddChild = isAuthorOrAdmin;

        main.innerHTML = `
            <article class="detail-card">
                <h1>${escapeHtml(a.titre)}</h1>
                <p class="meta">
                    <span class="author">👤 ${escapeHtml(a.prenom + ' ' + a.nom)}</span>
                    · 📅 ${formatDate(a.created_at)}
                    <span id="hidden-badge" class="badge-hidden"${+a.visible === 0 ? '' : ' hidden'}>· 🔒 masqué</span>
                </p>
                ${a.image_url ? `
                    <figure class="img-zoomable">
                        <img src="${escapeAttr(a.image_url)}" alt="" data-full="${escapeAttr(a.image_url)}">
                        <button type="button" class="btn-zoom" data-full="${escapeAttr(a.image_url)}">🔍 Voir en entier</button>
                    </figure>` : ''}
                <div class="body">${escapeHtml(a.contenu)}</div>
                ${renderGallery(a.gallery)}
                ${renderSources(a.sources)}
                ${canEdit || canAddChild || isAuthorOrAdmin ? `
                    <div class="actions">
                        ${canEdit ? `<button class="btn-edit-big" id="btn-edit-article">✏️ MODIFIER CET ARTICLE</button>` : ''}
                        ${isAuthorOrAdmin ? `<button class="btn-secondary btn-block" id="btn-toggle-visible">${+a.visible === 1 ? '🔒 Masquer l\'article' : '👁️ Rendre visible'}</button>` : ''}
                        ${canAddChild ? `<button class="btn-secondary btn-block" id="btn-add-child">+ Ajouter un sous-article</button>` : ''}
                        ${isAuthorOrAdmin ? `<button class="btn-danger btn-block" id="btn-delete-article">🗑️ Supprimer l'article</button>` : ''}
                    </div>
                ` : ''}
            </article>
            ${renderChildren(a.children)}
            ${renderComments(a.comments)}
        `;

        if (canEdit) {
            document.getElementById('btn-edit-article').addEventListener('click', () => openEditArticle(a));
        }
        if (canAddChild) {
            document.getElementById('btn-add-child').addEventListener('click', () => openNewArticle(id, a.titre));
        }
        if (isAuthorOrAdmin) {
            document.getElementById('btn-toggle-visible').addEventListener('click', () => toggleVisibility(a));
            document.getElementById('btn-delete-article').addEventListener('click', () => deleteArticle(a));
        }
        main.querySelectorAll('.child-item').forEach(el => {
            el.addEventListener('click', () => loadArticle(+el.dataset.id));
        });
        // Ouvre la photo en plein écran (couverture + galerie)
        main.querySelectorAll('[data-full]').forEach(el => {
            el.addEventListener('click', (e) => { e.stopPropagation(); openLightbox(el.dataset.full); });
        });
    } catch (err) {
        main.innerHTML = `<div class="error-box">⚠️ ${escapeHtml(err.message)}</div>`;
    }
}

// Masquer / rendre visible (auteur ou admin). Réutilise l'endpoint d'édition.
async function toggleVisibility(a) {
    const newVisible = +a.visible === 1 ? 0 : 1;
    const btn = document.getElementById('btn-toggle-visible');
    if (btn) btn.disabled = true;
    try {
        await api('/article.php', {
            method: 'POST',
            body: JSON.stringify({
                id: a.id,
                _method: 'PUT',
                titre: a.titre,
                contenu: a.contenu,
                sources: a.sources || '',
                visible: newVisible,
            }),
        });
        a.visible = newVisible;
        toast(newVisible ? 'Article rendu visible' : 'Article masqué', 'success');
        // Mise à jour en place (sans recharger) : bouton + badge
        if (btn) btn.textContent = newVisible ? '🔒 Masquer l\'article' : '👁️ Rendre visible';
        const badge = document.getElementById('hidden-badge');
        if (badge) badge.hidden = (newVisible === 1);
    } catch (err) {
        toast(err.message || 'Erreur', 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
}

// Supprimer définitivement (auteur ou admin), avec sa descendance.
async function deleteArticle(a) {
    const hasChildren = a.children && a.children.length > 0;
    const msg = 'Supprimer définitivement cet article'
        + (hasChildren ? ' ET tous ses sous-articles' : '') + ' ?';
    if (!confirm(msg)) return;
    const btn = document.getElementById('btn-delete-article');
    if (btn) btn.disabled = true;
    try {
        await api('/article.php', {
            method: 'POST',
            body: JSON.stringify({ id: a.id, _method: 'DELETE' }),
        });
        toast('Article supprimé', 'success');
        state.history = [];
        showView('list');
        loadList();
    } catch (err) {
        toast(err.message || 'Erreur', 'error');
        if (btn) btn.disabled = false;
    }
}

function renderGallery(gallery) {
    if (!gallery || gallery.length === 0) return '';
    return `
        <div class="detail-gallery">
            <h4>🖼️ Galerie</h4>
            <div class="gallery-grid">
                ${gallery.map(g => `
                    <figure class="gallery-item img-zoomable">
                        <img src="${escapeAttr(g.url)}" alt="${escapeAttr(g.caption || '')}" loading="lazy" data-full="${escapeAttr(g.url)}">
                        <button type="button" class="btn-zoom" data-full="${escapeAttr(g.url)}" aria-label="Voir en entier">🔍</button>
                        ${g.caption ? `<figcaption>${escapeHtml(g.caption)}</figcaption>` : ''}
                    </figure>
                `).join('')}
            </div>
        </div>
    `;
}

// ============ VISIONNEUSE PLEIN ÉCRAN ============
function openLightbox(url) {
    if (!url) return;
    const lb  = document.getElementById('lightbox');
    const img = document.getElementById('lightbox-img');
    img.src = url;
    lb.hidden = false;
    document.body.style.overflow = 'hidden'; // bloque le scroll derrière
}
function closeLightbox() {
    const lb = document.getElementById('lightbox');
    if (lb.hidden) return;
    lb.hidden = true;
    document.getElementById('lightbox-img').src = '';
    document.body.style.overflow = '';
}
document.getElementById('lightbox-close').addEventListener('click', closeLightbox);
document.getElementById('lightbox').addEventListener('click', (e) => {
    // Clic sur le fond ou sur l'image -> ferme
    if (e.target.id === 'lightbox' || e.target.id === 'lightbox-img') closeLightbox();
});
window.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeLightbox(); });

function renderSources(sources) {
    if (!sources) return '';
    const urls = sources.split(/\r?\n/).map(s => s.trim()).filter(s => /^https?:\/\//i.test(s));
    if (urls.length === 0) return '';
    return `
        <div class="sources">
            <h4>📚 Sources</h4>
            ${urls.map(u => `<a href="${escapeAttr(u)}" target="_blank" rel="noopener">${escapeHtml(u)}</a>`).join('')}
        </div>
    `;
}

function renderChildren(children) {
    if (!children || children.length === 0) return '';
    return `
        <div class="children-section">
            <h3>📑 Sous-articles (${children.length})</h3>
            ${children.map(c => `
                <div class="child-item" data-id="${c.id}">
                    <h4>${escapeHtml(c.titre)}</h4>
                    <div class="meta">👤 ${escapeHtml(c.prenom + ' ' + c.nom)} · 📅 ${formatDate(c.created_at)} · 💬 ${c.nb_comments}</div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderComments(comments) {
    if (!comments || comments.length === 0) return '<div class="comments-section"><h3>💬 Aucun commentaire</h3></div>';
    return `
        <div class="comments-section">
            <h3>💬 ${comments.length} commentaire${comments.length > 1 ? 's' : ''}</h3>
            ${comments.map(c => `
                <div class="comment">
                    <div class="author-name">👤 ${escapeHtml(c.prenom + ' ' + c.nom)}</div>
                    <div class="date">📅 ${formatDate(c.created_at)}</div>
                    <div class="body">${escapeHtml(c.contenu)}</div>
                </div>
            `).join('')}
        </div>
    `;
}

// ============ NOUVEL ARTICLE ============
document.getElementById('btn-new').addEventListener('click', () => openNewArticle(null));

function openNewArticle(parentId = null, parentTitle = null) {
    const form = document.getElementById('form-new');
    form.reset();
    form.parent_id.value = parentId || '';
    document.getElementById('image-preview-wrap').hidden = true;
    document.getElementById('image-preview').removeAttribute('src');
    state.galleryFiles = [];
    renderGalleryPreview();
    document.getElementById('new-title').textContent = parentId ? 'Sous-article' : 'Nouvel article';
    const info = document.getElementById('new-parent-info');
    if (parentId) {
        info.innerHTML = `📑 Sous-article de "<strong>${escapeHtml(parentTitle)}</strong>"`;
        info.hidden = false;
    } else {
        info.hidden = true;
    }
    state.history.push('new');
    showView('new');
}

// ---- COUVERTURE : choisir / prendre / aperçu ----
const inputImage       = document.getElementById('input-image');
const inputImageCam    = document.getElementById('input-image-camera');
const coverWrap        = document.getElementById('image-preview-wrap');
const coverPreview     = document.getElementById('image-preview');

async function loadCoverFromInput(input) {
    const raw = input.files[0];
    if (!raw) return;
    if (raw.size > MAX_RAW_BYTES) {
        toast('Image vraiment trop lourde (>30 Mo)', 'error');
        input.value = '';
        return;
    }
    toast('🗜️ Traitement de l\'image…', 'info');
    const file = await compressImage(raw);
    // On synchronise vers le DataTransfer du champ "image" (compressé)
    const dt = new DataTransfer();
    dt.items.add(file);
    inputImage.files = dt.files;

    const reader = new FileReader();
    reader.onload = (ev) => {
        coverPreview.src = ev.target.result;
        coverWrap.hidden = false;
    };
    reader.readAsDataURL(file);
}

inputImage.addEventListener('change', () => loadCoverFromInput(inputImage));
inputImageCam.addEventListener('change', () => loadCoverFromInput(inputImageCam));

document.getElementById('btn-remove-img').addEventListener('click', () => {
    inputImage.value = '';
    inputImageCam.value = '';
    coverWrap.hidden = true;
});

// ---- GALERIE : choisir plusieurs / prendre une photo / aperçus ----
const inputGallery    = document.getElementById('input-gallery');
const inputGalleryCam = document.getElementById('input-gallery-camera');
const galleryPreview  = document.getElementById('gallery-preview');

async function addGalleryFiles(fileList) {
    const incoming = Array.from(fileList || []);
    if (incoming.length > 0) toast('🗜️ Traitement des photos…', 'info');
    for (const f of incoming) {
        if (state.galleryFiles.length >= MAX_GALLERY) {
            toast(`Galerie limitée à ${MAX_GALLERY} photos`, 'error');
            break;
        }
        if (f.size > MAX_RAW_BYTES) {
            toast(`"${f.name}" trop lourde (>30 Mo)`, 'error');
            continue;
        }
        if (!/^image\//.test(f.type)) {
            toast(`"${f.name}" n'est pas une image`, 'error');
            continue;
        }
        const compressed = await compressImage(f);
        state.galleryFiles.push({ file: compressed, caption: '' });
        renderGalleryPreview();
    }
}

inputGallery.addEventListener('change', (e) => {
    addGalleryFiles(e.target.files);
    e.target.value = '';
});
inputGalleryCam.addEventListener('change', (e) => {
    addGalleryFiles(e.target.files);
    e.target.value = '';
});

function renderGalleryPreview() {
    if (state.galleryFiles.length === 0) {
        galleryPreview.innerHTML = '';
        return;
    }
    galleryPreview.innerHTML = state.galleryFiles.map((_, i) => `
        <div class="gallery-thumb" data-i="${i}">
            <div class="thumb-img"><img alt="" data-thumb="${i}"></div>
            <input type="text" class="thumb-caption" data-caption="${i}" placeholder="Légende (optionnel)" maxlength="200">
            <button type="button" class="btn-remove-img" data-remove="${i}" aria-label="Retirer">✕</button>
        </div>
    `).join('');

    state.galleryFiles.forEach((g, i) => {
        const imgEl = galleryPreview.querySelector(`img[data-thumb="${i}"]`);
        const reader = new FileReader();
        reader.onload = (ev) => { imgEl.src = ev.target.result; };
        reader.readAsDataURL(g.file);

        const capEl = galleryPreview.querySelector(`input[data-caption="${i}"]`);
        capEl.value = g.caption || '';
        capEl.addEventListener('input', () => { state.galleryFiles[i].caption = capEl.value; });

        galleryPreview.querySelector(`button[data-remove="${i}"]`).addEventListener('click', () => {
            state.galleryFiles.splice(i, 1);
            renderGalleryPreview();
        });
    });
}

document.getElementById('form-new').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('btn-publish');
    const imageFile = form.image && form.image.files[0];
    const hasGallery = state.galleryFiles.length > 0;
    setLoading(btn, true);
    try {
        let res;
        if (imageFile || hasGallery) {
            // Upload multipart : couverture + galerie
            const formData = new FormData();
            formData.append('titre',   form.titre.value.trim());
            formData.append('contenu', form.contenu.value.trim());
            formData.append('sources', form.sources.value.trim());
            if (form.parent_id.value) formData.append('parent_id', form.parent_id.value);
            if (imageFile) formData.append('image', imageFile);
            state.galleryFiles.forEach((g, i) => {
                formData.append('gallery[]', g.file);
                formData.append('captions[]', g.caption || '');
                formData.append('positions[]', String(i));
            });

            res = await fetch(API_BASE + '/article.php', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + state.token },
                body: formData,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.error || `Erreur ${res.status}`);
            res = data;
        } else {
            // Pas d'image ni de galerie : JSON simple
            const body = {
                titre:   form.titre.value.trim(),
                contenu: form.contenu.value.trim(),
                sources: form.sources.value.trim(),
            };
            if (form.parent_id.value) body.parent_id = +form.parent_id.value;
            res = await api('/article.php', {
                method: 'POST',
                body: JSON.stringify(body),
            });
        }
        form.reset();
        document.getElementById('image-preview-wrap').hidden = true;
        state.galleryFiles = [];
        renderGalleryPreview();
        toast('📰 Article publié !', 'success');
        loadArticle(res.id);
    } catch (err) {
        toast(err.message, 'error');
    } finally {
        setLoading(btn, false);
    }
});

// ============ MODIFIER UN ARTICLE ============
const editState = {
    article: null,
    newCoverFile: null,        // File ou null
    removeCover: false,        // si true, supprimer couverture actuelle (sans remplacement)
    existing: {},              // { imgId: { caption, position, delete } }
    newGalleryFiles: [],       // [{ file, caption }]
};

function openEditArticle(article) {
    editState.article = article;
    editState.newCoverFile = null;
    editState.removeCover = false;
    editState.existing = {};
    (article.gallery || []).forEach(g => {
        editState.existing[g.id] = {
            caption: g.caption || '',
            position: g.position || 0,
            delete: false,
        };
    });
    editState.newGalleryFiles = [];

    const form = document.getElementById('form-edit');
    form.id.value = article.id;
    form.titre.value = article.titre;
    form.contenu.value = article.contenu;
    form.sources.value = article.sources || '';
    document.getElementById('edit-remove-image').value = '';

    // Couverture actuelle
    const curWrap = document.getElementById('edit-current-cover');
    const curImg  = document.getElementById('edit-cover-img');
    if (article.image_url) {
        curImg.src = article.image_url;
        curWrap.hidden = false;
    } else {
        curWrap.hidden = true;
    }
    document.getElementById('edit-cover-removed-info').hidden = true;
    document.getElementById('edit-new-cover-preview').hidden = true;
    document.getElementById('edit-input-image').value = '';
    document.getElementById('edit-input-image-camera').value = '';

    renderEditExistingGallery();
    renderEditNewGallery();

    state.history.push('edit');
    showView('edit');
}

// --- Couverture (édition) ---
const editInputImg    = document.getElementById('edit-input-image');
const editInputImgCam = document.getElementById('edit-input-image-camera');
const editNewCovWrap  = document.getElementById('edit-new-cover-preview');
const editNewCovImg   = document.getElementById('edit-new-cover-img');

async function loadEditCoverFromInput(input) {
    const raw = input.files[0];
    if (!raw) return;
    if (raw.size > MAX_RAW_BYTES) {
        toast('Image vraiment trop lourde (>30 Mo)', 'error');
        input.value = '';
        return;
    }
    toast('🗜️ Traitement de l\'image…', 'info');
    const file = await compressImage(raw);
    const dt = new DataTransfer();
    dt.items.add(file);
    editInputImg.files = dt.files;
    editState.newCoverFile = file;
    editState.removeCover = false;
    document.getElementById('edit-remove-image').value = '';
    document.getElementById('edit-cover-removed-info').hidden = true;

    const reader = new FileReader();
    reader.onload = (ev) => {
        editNewCovImg.src = ev.target.result;
        editNewCovWrap.hidden = false;
    };
    reader.readAsDataURL(file);
}
editInputImg.addEventListener('change',    () => loadEditCoverFromInput(editInputImg));
editInputImgCam.addEventListener('change', () => loadEditCoverFromInput(editInputImgCam));

document.getElementById('btn-edit-cancel-newcover').addEventListener('click', () => {
    editInputImg.value = '';
    editInputImgCam.value = '';
    editState.newCoverFile = null;
    editNewCovWrap.hidden = true;
});

document.getElementById('btn-edit-remove-cover').addEventListener('click', () => {
    if (!confirm('Supprimer la couverture actuelle ?')) return;
    editState.removeCover = true;
    document.getElementById('edit-remove-image').value = '1';
    document.getElementById('edit-current-cover').hidden = true;
    document.getElementById('edit-cover-removed-info').hidden = false;
});

// --- Galerie existante (édition) ---
function renderEditExistingGallery() {
    const wrap = document.getElementById('edit-existing-gallery');
    const items = (editState.article && editState.article.gallery) || [];
    if (items.length === 0) {
        wrap.innerHTML = '<p class="muted">Aucune photo pour l\'instant.</p>';
        return;
    }
    wrap.innerHTML = items.map(g => {
        const st = editState.existing[g.id] || {};
        const isDel = !!st.delete;
        return `
            <div class="gallery-thumb ${isDel ? 'thumb-deleted' : ''}" data-existing="${g.id}">
                <div class="thumb-img"><img src="${escapeAttr(g.url)}" alt=""></div>
                <input type="text" class="thumb-caption" data-existing-caption="${g.id}" placeholder="Légende (optionnel)" maxlength="200" ${isDel ? 'disabled' : ''}>
                <button type="button" class="btn-remove-img" data-existing-toggle="${g.id}" aria-label="${isDel ? 'Annuler suppression' : 'Supprimer'}">${isDel ? '↶' : '✕'}</button>
            </div>
        `;
    }).join('');

    items.forEach(g => {
        const cap = wrap.querySelector(`input[data-existing-caption="${g.id}"]`);
        cap.value = editState.existing[g.id]?.caption || '';
        cap.addEventListener('input', () => { editState.existing[g.id].caption = cap.value; });

        wrap.querySelector(`button[data-existing-toggle="${g.id}"]`).addEventListener('click', () => {
            editState.existing[g.id].delete = !editState.existing[g.id].delete;
            renderEditExistingGallery();
        });
    });
}

// --- Nouvelles photos galerie (édition) ---
const editInputGal    = document.getElementById('edit-input-gallery');
const editInputGalCam = document.getElementById('edit-input-gallery-camera');
const editNewGalWrap  = document.getElementById('edit-new-gallery-preview');

async function addEditGalleryFiles(fileList) {
    const incoming = Array.from(fileList || []);
    if (incoming.length > 0) toast('🗜️ Traitement des photos…', 'info');
    for (const f of incoming) {
        if (editState.newGalleryFiles.length >= MAX_GALLERY) {
            toast(`Limite : ${MAX_GALLERY} nouvelles photos`, 'error'); break;
        }
        if (f.size > MAX_RAW_BYTES) { toast(`"${f.name}" trop lourde (>30 Mo)`, 'error'); continue; }
        if (!/^image\//.test(f.type)) { toast(`"${f.name}" n'est pas une image`, 'error'); continue; }
        const compressed = await compressImage(f);
        editState.newGalleryFiles.push({ file: compressed, caption: '' });
        renderEditNewGallery();
    }
}
editInputGal.addEventListener('change', (e) => { addEditGalleryFiles(e.target.files); e.target.value = ''; });
editInputGalCam.addEventListener('change', (e) => { addEditGalleryFiles(e.target.files); e.target.value = ''; });

function renderEditNewGallery() {
    if (editState.newGalleryFiles.length === 0) {
        editNewGalWrap.innerHTML = '';
        return;
    }
    editNewGalWrap.innerHTML = editState.newGalleryFiles.map((_, i) => `
        <div class="gallery-thumb" data-new="${i}">
            <div class="thumb-img"><img alt="" data-new-thumb="${i}"></div>
            <input type="text" class="thumb-caption" data-new-caption="${i}" placeholder="Légende (optionnel)" maxlength="200">
            <button type="button" class="btn-remove-img" data-new-remove="${i}" aria-label="Retirer">✕</button>
        </div>
    `).join('');
    editState.newGalleryFiles.forEach((g, i) => {
        const imgEl = editNewGalWrap.querySelector(`img[data-new-thumb="${i}"]`);
        const r = new FileReader();
        r.onload = (ev) => { imgEl.src = ev.target.result; };
        r.readAsDataURL(g.file);
        const cap = editNewGalWrap.querySelector(`input[data-new-caption="${i}"]`);
        cap.value = g.caption || '';
        cap.addEventListener('input', () => { editState.newGalleryFiles[i].caption = cap.value; });
        editNewGalWrap.querySelector(`button[data-new-remove="${i}"]`).addEventListener('click', () => {
            editState.newGalleryFiles.splice(i, 1);
            renderEditNewGallery();
        });
    });
}

// --- Submit édition ---
document.getElementById('form-edit').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('btn-save-edit');
    setLoading(btn, true);
    try {
        const fd = new FormData();
        fd.append('_method', 'PUT');
        fd.append('id',      form.id.value);
        fd.append('titre',   form.titre.value.trim());
        fd.append('contenu', form.contenu.value.trim());
        fd.append('sources', form.sources.value.trim());
        if (editState.removeCover) fd.append('remove_image', '1');
        if (editState.newCoverFile) fd.append('image', editState.newCoverFile);

        // Existantes : caption, position, delete
        Object.entries(editState.existing).forEach(([imgId, data]) => {
            if (data.delete) {
                fd.append(`existing[${imgId}][delete]`, '1');
            } else {
                fd.append(`existing[${imgId}][caption]`, data.caption || '');
                fd.append(`existing[${imgId}][position]`, String(data.position || 0));
            }
        });

        // Nouvelles
        editState.newGalleryFiles.forEach((g, i) => {
            fd.append('gallery[]', g.file);
            fd.append('captions[]', g.caption || '');
            fd.append('positions[]', String(100 + i));
        });

        const res = await fetch(API_BASE + '/article.php', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + state.token },
            body: fd,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || `Erreur ${res.status}`);
        toast('💾 Article modifié !', 'success');
        loadArticle(+form.id.value);
    } catch (err) {
        toast(err.message, 'error');
    } finally {
        setLoading(btn, false);
    }
});

// ============ HELPERS ============
function setLoading(btn, loading) {
    btn.disabled = loading;
    btn.querySelector('.btn-label').hidden = loading;
    btn.querySelector('.btn-loader').hidden = !loading;
}

function updateUserBadge() {
    const el = document.getElementById('topbar-user');
    if (state.user) {
        el.innerHTML = `👤 ${escapeHtml(state.user.prenom)}`;
    }
}

function logoutSilently() {
    state.token = null;
    state.user = null;
    localStorage.removeItem('token');
    localStorage.removeItem('user');
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}
function escapeAttr(s) { return escapeHtml(s); }
function formatDate(d) {
    if (!d) return '';
    const dt = new Date(d.replace(' ', 'T'));
    if (isNaN(dt)) return d;
    return dt.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ============ MISE À JOUR DE L'APP ============
// Vide TOUS les caches navigateur + service workers, force le re-fetch des
// fichiers critiques en bypassant le cache, puis recharge la page.
async function forceUpdate() {
    toast('🔄 Mise à jour en cours…', 'info');
    try {
        // 1) Désinscrit tous les service workers
        if ('serviceWorker' in navigator) {
            const regs = await navigator.serviceWorker.getRegistrations();
            await Promise.all(regs.map(r => r.unregister()));
        }
        // 2) Vide tous les Cache Storage
        if (window.caches) {
            const keys = await caches.keys();
            await Promise.all(keys.map(k => caches.delete(k)));
        }
        // 3) Force le re-fetch des fichiers critiques (réseau uniquement, ignore cache)
        const ts = Date.now();
        const base = window.location.href.replace(/\/index\.html.*$/, '/').replace(/\?.*$/, '');
        await Promise.all([
            fetch(base + 'index.html?_=' + ts, { cache: 'reload' }).catch(()=>{}),
            fetch(base + 'app.js?_=' + ts,     { cache: 'reload' }).catch(()=>{}),
            fetch(base + 'style.css?_=' + ts,  { cache: 'reload' }).catch(()=>{}),
            fetch(base + 'manifest.json?_=' + ts, { cache: 'reload' }).catch(()=>{}),
            fetch(base + 'icon-192.png?_=' + ts, { cache: 'reload' }).catch(()=>{}),
            fetch(base + 'icon-512.png?_=' + ts, { cache: 'reload' }).catch(()=>{}),
        ]);
    } catch (e) {
        console.warn('Cache cleanup error:', e);
    }
    // 4) Navigation vers une URL fraîche
    const reloadUrl = new URL(window.location.href);
    reloadUrl.searchParams.set('_', String(Date.now()));
    window.location.replace(reloadUrl.toString());
}

document.getElementById('btn-update-now').addEventListener('click', forceUpdate);

// Bouton 🔄 de la barre : vérifie explicitement s'il existe une mise à jour
// et donne un retour clair (à jour / nouvelle version dispo).
document.getElementById('btn-refresh').addEventListener('click', () => checkForUpdate({ manual: true }));

// Vérifie côté serveur s'il y a une nouvelle version.
//   opts.manual = true  -> déclenché par l'utilisateur : on affiche un retour (toast).
//   opts.manual = false -> vérification silencieuse au démarrage.
async function checkForUpdate(opts = {}) {
    const manual = opts.manual === true;
    const btn = document.getElementById('btn-refresh');
    if (manual) {
        toast('Recherche de mises à jour…', 'info');
        if (btn) btn.disabled = true;
    }
    try {
        const res = await fetch(API_BASE + '/version.php?_=' + Date.now(), {
            cache: 'no-store',
            headers: { 'Cache-Control': 'no-cache' },
        });
        if (!res.ok) {
            if (manual) toast('Vérification impossible (serveur indisponible)', 'error');
            return;
        }
        const data = await res.json();
        if (data.version && data.version !== APP_VERSION) {
            console.log(`Update available: serveur=${data.version}, local=${APP_VERSION}`);
            document.getElementById('update-banner').hidden = false;
            if (manual) toast('Nouvelle version disponible !', 'success');
        } else if (manual) {
            toast('Tu es déjà à jour (' + APP_VERSION + ')', 'success');
        }
    } catch (e) {
        // Silencieux au démarrage (offline par exemple) ; retour explicite si manuel.
        if (manual) toast('Vérification impossible (hors ligne ?)', 'error');
    } finally {
        if (manual && btn) btn.disabled = false;
    }
}

// ============ DÉMARRAGE ============
async function start() {
    // En arrière-plan : vérifie si une nouvelle version est dispo
    checkForUpdate();

    if (!state.token) {
        showView('login');
        return;
    }
    // Token présent : on vérifie qu'il marche encore
    try {
        await api('/articles.php?limit=1');
        loadList();
    } catch (err) {
        logoutSilently();
        showView('login');
        if (err.status === 401) toast('Session expirée, reconnecte-toi', 'info');
    }
}
start();
