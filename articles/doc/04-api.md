# 04 — API JSON

## Convention générale

- **Base URL** : `https://blog.mariondelval.com/api/` (prod) ou `http://localhost/Blog/blog/api/` (local)
- **Content-Type** : `application/json` pour les réponses
- **CORS** : `Access-Control-Allow-Origin: *` (ouvert, pour permettre l'app mobile cross-domain en dev)
- **Format d'erreur** : `{ "error": "Message lisible" }` avec un code HTTP 4xx/5xx

Tous les endpoints sont dans `blog/api/`. Chacun inclut `_bootstrap.php` qui :
- Active les headers CORS
- Force `Content-Type: application/json`
- Charge `config/config.php` + `includes/db.php` (→ `$pdo` disponible)
- Définit `json_response(array, int)` et `json_error(string, int)`
- Installe un handler global d'exceptions

## Authentification

L'API mobile utilise des **tokens Bearer**. Récupère un token via `/api/login.php`, puis envoie-le dans le header `Authorization: Bearer <token>` pour tous les endpoints protégés.

Tokens : 64 caractères hex aléatoires, stockés dans la table `api_tokens`, expiration 30 jours.

## Endpoints

### POST `/api/login.php`

Authentifie un utilisateur et renvoie un token.

**Body JSON** :
```json
{ "email": "user@example.com", "password": "motdepasse" }
```

**Réponses** :
- `200 OK` :
```json
{
  "token": "a1b2c3d4...64chars",
  "user":  { "id": 1, "email": "...", "nom": "...", "prenom": "...", "is_admin": 0 },
  "expires_at": "2027-06-26 12:00:00"
}
```
- `401` : `{ "error": "Identifiants invalides" }`
- `400` : `{ "error": "Email manquant" }`

**Exemple cURL** :
```bash
curl -X POST https://blog.mariondelval.com/api/login.php \
     -H "Content-Type: application/json" \
     -d '{"email":"admin@x.com","password":"secret"}'
```

### GET `/api/articles.php`

Liste paginée des articles racines (sans les sous-articles).

**Query** :
- `?limit=20` (optionnel, défaut 20)
- `?offset=0` (optionnel, défaut 0)

**Réponse 200** :
```json
{
  "articles": [
    {
      "id": 12,
      "titre": "Mon dernier article",
      "image": "https://blog.mariondelval.com/uploads/img-abc.jpg",
      "contenu_extract": "Les 200 premiers caractères…",
      "auteur": "Marion Delval",
      "created_at": "2026-05-20 18:00:00",
      "nb_comments": 4,
      "nb_children": 2
    }
  ],
  "total": 47
}
```

L'URL des images est rendue absolue (préfixée par le domaine) pour qu'elle marche depuis l'app PWA même si l'utilisateur est offline et que `window.location.host` change.

### GET `/api/article.php?id=X`

Détail d'un article + ses sous-articles + ses commentaires + sa galerie.

**Réponse 200** :
```json
{
  "article": {
    "id": 12,
    "titre": "...",
    "contenu": "...",
    "image": "https://.../uploads/img-abc.jpg",
    "sources": "...",
    "layout": null,
    "auteur": { "id": 1, "nom": "Delval", "prenom": "Marion" },
    "created_at": "...",
    "updated_at": "..."
  },
  "parent":   null,
  "children": [ { "id": 13, "titre": "Sous-article 1", ... } ],
  "gallery":  [ { "url": "https://.../uploads/g-1.png", "caption": "..." } ],
  "comments": [
    {
      "id": 5,
      "contenu": "...",
      "auteur": { "id": 2, "nom": "Doe", "prenom": "John" },
      "created_at": "..."
    }
  ]
}
```

**Réponse 404** : `{ "error": "Article introuvable" }`

### POST `/api/article.php` (auth)

Publie un nouvel article (ou un sous-article).

**Headers** :
```
Authorization: Bearer <token>
Content-Type: multipart/form-data  (si image) OU application/json
```

**Champs** :
- `titre` (string, obligatoire)
- `contenu` (string, obligatoire)
- `sources` (string, optionnel)
- `parent_id` (int, optionnel — pour faire un sous-article)
- `image` (file, optionnel)
- `gallery[]` (files, optionnels — galerie additionnelle)

**Réponse 201** :
```json
{ "ok": true, "id": 42, "url": "https://.../pages/article.php?id=42" }
```

**Réponse 401** : `{ "error": "Token manquant ou invalide" }`

**Réponse 400** : `{ "error": "Titre obligatoire" }`

**Exemple** :
```bash
curl -X POST https://blog.mariondelval.com/api/article.php \
     -H "Authorization: Bearer a1b2c3..." \
     -F "titre=Mon article" \
     -F "contenu=Le contenu Markdown ou HTML" \
     -F "image=@/path/to/cover.jpg"
```

### GET `/api/version.php`

Renvoie la version courante de l'app mobile (pour la détection de mise à jour côté PWA).

**Réponse 200** :
```json
{
  "version":     "v16",
  "released_at": "2026-05-27"
}
```

Headers de réponse forcent `no-store` pour ne pas être mis en cache.

### GET `/api/site_info.php`

Renvoie les paramètres publics du site, utilisés par la landing page externe.

**Réponse 200** :
```json
{
  "site_name":  "Mon Blog",
  "tagline":    "Le blog",
  "about_text": "Un blog ouvert où…",
  "landing": {
    "eyebrow":      "En ligne",
    "title":        "",
    "subtitle":     "",
    "cta_text":     "Découvrir le blog",
    "cta_url":      "blog/",
    "footer_text":  "",
    "footer_url":   "blog/",
    "show_pulse":   true,
    "bg_color":     "#0f172a",
    "text_color":   "#f1f5f9",
    "muted_color":  "#94a3b8",
    "accent_color": "#16a34a",
    "accent_dark":  "#166534",
    "blob_1": "#6366f1",
    "blob_2": "#ec4899",
    "blob_3": "#22c55e"
  }
}
```

Headers : `Cache-Control: public, max-age=300` (5 min, pour réduire la charge BDD).

### POST `/api/sync_receive.php`

Endpoint qui reçoit un dump SQL + zip d'uploads d'une instance locale. Voir [06-sync.md](06-sync.md) pour le détail.

**Headers** : `Content-Type: multipart/form-data`

**Body** :
- `token` (string, clé de sync)
- `sql_dump` (file)
- `uploads_zip` (file)

**Réponse 200** : `{ "ok": true, "message": "Synchronisation terminee", "uploads": 42 }`

**Réponse 403** : `{ "error": "Cle invalide, expiree ou deja utilisee" }`

## Codes d'erreur HTTP utilisés

| Code | Cas |
|---|---|
| 200 | OK, données renvoyées |
| 201 | Created (POST article réussi) |
| 204 | No Content (preflight OPTIONS) |
| 400 | Body invalide, champ manquant |
| 401 | Non authentifié (token absent / faux) |
| 403 | Authentifié mais pas autorisé (clé sync invalide) |
| 404 | Ressource introuvable |
| 405 | Méthode non supportée |
| 500 | Erreur interne (exception PHP, BDD down) |
| 503 | Blog non installé (pas de `config.php`) |

## Sécurité API

- Tous les endpoints POST nécessitent une vérification CSRF **OU** un token API valide
- Les paramètres SQL sont **toujours** passés via prepared statements (jamais d'interpolation directe)
- Le token Bearer est validé via `hash_equals` (comparaison timing-safe)
- Les emails sont normalisés en minuscules avant comparaison
- Les hashes de mots de passe utilisent `password_hash(..., PASSWORD_BCRYPT)`
- Les uploads sont validés : type MIME, extension, taille max 12 Mo, nom random
