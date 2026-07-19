# 08 — Panel d'administration

## Accès

Le panel admin est accessible à `<blog>/pages/admin.php`. Il faut être connecté avec un compte qui a `is_admin = 1` dans la table `users`.

La fonction `require_admin()` (dans `includes/auth.php`) bloque l'accès aux non-admins :

```php
function require_admin() {
    if (!is_logged_in() || empty($_SESSION['user']['is_admin'])) {
        redirect(base_url('pages/login.php'));
    }
}
```

## Dashboard (`pages/admin.php`)

Le dashboard affiche :

1. **Grille de 6 cartes d'action** (raccourcis vers les sous-pages)
2. **Stats globales** : Utilisateurs / Articles / Commentaires / Réseaux sociaux
3. **Tableau des utilisateurs**
4. **Tableau des 50 articles récents** (édition + suppression)
5. **Tableau des 50 commentaires récents** (suppression)

### Les 6 cartes

| Carte | Page | Rôle |
|---|---|---|
| 🎨 **Apparence accueil** | `landing_settings.php` | Personnalise la landing publique |
| ⚙️ **Paramètres du site** | `settings.php` | Nom, slogan, baseline, "à propos" |
| 🔗 **Réseaux sociaux** | `social.php` | Ajout / édition des liens sociaux |
| 📤 **Envoyer vers serveur** | `sync_push.php` | Sync local → prod (clé requise) |
| 🔑 **Clés sync (serveur)** | `sync_keys.php` | Génère les clés d'autorisation |
| 📦 **Import / Export JSON** | `sync_json.php` | Sauvegarde / restauration JSON |

Chaque carte est un `<a class="admin-action <couleur>">` avec une icône colorée à gauche, un titre et une description. Hover → soulèvement avec ombre.

## Paramètres du site (`pages/settings.php`)

Édition des settings généraux stockés dans la table `settings` :

| Clé | Type | Limite |
|---|---|---|
| `site_name` | text | 100 |
| `tagline` | text | 100 |
| `header_baseline` | text | 200 |
| `about_text` | textarea | 500 |

Formulaire CSRF-protégé. À la soumission :

```php
foreach ($fields as $key => $_) {
    set_setting($key, $current[$key]);
}
flash_set('success', 'Paramètres enregistrés.');
redirect(base_url('pages/settings.php'));
```

Ces valeurs sont utilisées partout dans le site via `get_setting($key)`.

## Apparence accueil (`pages/landing_settings.php`)

C'est la page la plus riche en UX. Elle personnalise la landing publique (`/public_html/index.html`).

### Disposition

Layout en grille 2 colonnes :
- **Gauche** : formulaire avec sections (Textes / Comportement / Couleurs principales / Couleurs d'ambiance)
- **Droite** : **aperçu en temps réel** (sticky en haut de l'écran lors du scroll)

### Champs disponibles

```
Section Textes :
  - landing_eyebrow     (badge texte, vide = masqué)
  - landing_title       (override site_name)
  - landing_subtitle    (override tagline)
  - landing_cta_text    (texte bouton)
  - landing_cta_url     (URL bouton, défaut "blog/")
  - landing_footer_text (texte du lien bas)
  - landing_footer_url  (URL du lien bas)

Section Comportement :
  - landing_show_pulse  (point vert pulsant à côté du badge)

Section Couleurs principales :
  - landing_bg_color
  - landing_text_color
  - landing_muted_color
  - landing_accent_color (bouton clair)
  - landing_accent_dark  (bouton foncé pour dégradé)

Section Couleurs d'ambiance :
  - landing_blob_1  (top-left)
  - landing_blob_2  (bottom-right)
  - landing_blob_3  (center)
```

### Palettes préréglées

5 boutons "swatch" appliquent une palette complète :

- **Sombre** (défaut) : navy + vert
- **Clair** : blanc + teal + rose
- **Sunset** : violet sombre + orange + rouge
- **Forêt** : vert très sombre + vert vif
- **Minimal** : noir + gris

Plus un bouton **↺ Tout réinitialiser** qui restaure les valeurs par défaut de tous les champs (textes + couleurs).

### Aperçu live

Une `<div class="lp-preview">` mime la landing à petite échelle (aspect 16:11) :
- 3 blobs flous animés
- Eyebrow avec pulse
- Titre en dégradé typo Playfair
- Sous-titre
- Bouton CTA
- Lien footer

Un script JavaScript écoute tous les `<input data-field="...">` du formulaire. Au moindre changement :

```javascript
const apply = () => {
    setOrHide(elPreview.eyebrow, getVal('landing_eyebrow'));
    elPreview.cta.textContent = getVal('landing_cta_text');
    style.setProperty('--bg-1', getVal('landing_bg_color'));
    style.setProperty('--accent', getVal('landing_accent_color'));
    // ... etc
};
```

→ Aucune sauvegarde n'a lieu tant qu'on ne clique pas sur **Enregistrer**. C'est purement visuel.

### Côté landing (index.html à la racine)

Quand la landing publique se charge, elle fait :

```javascript
const res = await fetch('blog/api/site_info.php', { cache: 'no-store' });
const data = await res.json();
const l = data.landing;

// Applique les couleurs
document.documentElement.style.setProperty('--bg-1', l.bg_color);
document.documentElement.style.setProperty('--accent-color', l.accent_color);
// ... etc

// Applique les textes
document.getElementById('site-name').textContent = l.title || data.site_name;
document.getElementById('cta-label').textContent = l.cta_text;
// ... etc
```

Si l'API échoue (réseau coupé, blog déplacé), le bouton CTA reste affiché mais sans titre / sans badge → la landing reste utilisable.

## Réseaux sociaux (`pages/social.php`)

CRUD simple des liens sociaux affichés en haut du blog.

- Liste des liens existants avec icône FontAwesome
- Formulaire d'ajout : plateforme, URL, icône
- Bouton "Supprimer" par ligne

Stocké dans la table `social_links`.

## Sync (`pages/sync_keys.php`, `pages/sync_push.php`, `pages/sync_json.php`)

Détaillé dans [06-sync.md](06-sync.md). UX :

### `sync_keys.php` (côté serveur)

- Bouton "Générer une clé" avec sélecteur de durée (5 min - 24 h)
- La clé apparaît dans une zone avec bouton **📋 Copier**
- Tableau des clés actives avec **countdown** en temps réel (mis à jour chaque seconde)
- Tableau de l'historique (15 dernières clés, utilisées ou expirées)
- Bouton "Révoquer toutes les clés actives"

### `sync_push.php` (côté local)

- Formulaire : URL distante (pré-rempli) + clé à coller + checkbox de confirmation
- Au submit : **overlay plein écran avec spinner** indiquant que le travail est en cours
- Résultat affiché dans une flash bar (vert ou rouge)

### `sync_json.php` (export/import)

- **Stats** en haut : Articles, Utilisateurs, Commentaires, Images BDD, Fichiers uploads, Taille uploads
- **3 boutons d'export** côte à côte :
  - `📄 JSON (données seules)` → `?action=export`
  - `🖼️ Images (ZIP)` → `?action=export_images`
  - `📦 Export complet (JSON + images)` → `?action=export_full`
- **2 sections d'import** indépendantes (chacune avec sa dropzone + checkbox de confirmation) :
  - Import JSON → remplace toutes les données BDD
  - Import images ZIP → purge `uploads/` et extrait le ZIP

## Édition d'article (`pages/article_edit.php`)

L'éditeur d'article inclut :
- Champ titre
- Editor de contenu (textarea, peut être étendu en WYSIWYG)
- Sélecteur d'image de couverture
- Galerie multi-images (ajout/suppression/réordonnement)
- Champ "Sources" (références bibliographiques)
- Bouton "Aperçu" qui appelle `assets/js/preview.js`

## CSS partagé pour l'admin

Toutes les pages admin partagent un style cohérent défini dans `blog/assets/css/styles.css`, section "ADMIN UX" :

```css
.admin-actions       /* grille de cartes du dashboard */
.admin-action        /* une carte individuelle, variants .green .blue .amber .purple .rose .slate */
.section-block       /* groupe de champs encadré */
.section-block .section-head .ico + h3   /* en-tête avec icône */
.copy-btn            /* bouton copier-coller, avec état .copied */
.pill                /* pastille de statut, variants .pill-ok .pill-warn .pill-danger */
.dropzone            /* zone drag-and-drop, état .dragover */
.mini-stats          /* grille de petites stats inline */
.busy-overlay        /* écran d'attente avec spinner */
.swatch              /* bouton de preset couleur */
```

Ces classes peuvent être réutilisées dans toute nouvelle page admin.

## Ajouter une nouvelle page admin

1. Crée `blog/pages/ma_nouvelle_page.php` :

```php
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

// ... logique POST ici ...

$pageTitle = 'Mon titre';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-card auth-card-wide">
    <h1>Ma page</h1>
    <!-- contenu -->
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
```

2. Ajoute une carte dans `pages/admin.php` :

```php
<a class="admin-action purple" href="<?= e(base_url('pages/ma_nouvelle_page.php')) ?>">
    <span class="ico">🚀</span>
    <span class="body">
        <h3>Ma nouvelle action</h3>
        <p>Description en une ligne.</p>
    </span>
</a>
```

3. Bénéficies des classes `.section-block`, `.dropzone`, `.copy-btn`, etc. pour rester cohérent.
