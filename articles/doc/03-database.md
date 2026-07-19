# 03 — Base de données

## Vue d'ensemble

Le projet utilise MySQL (ou MariaDB) avec PDO. Le schéma est défini dans `blog/sql/schema.sql` et créé automatiquement par `install.php`.

```
settings  (key-value config)         api_tokens  (auth API)
users     (auth + identité)          sessions    (PHP natif, pas en DB)
articles  (contenu, hiérarchique)
  └── article_images
  └── comments
social_links
```

Toutes les tables utilisent `ENGINE=InnoDB` (pour les foreign keys avec cascade) et `CHARSET=utf8mb4` (support des emojis et caractères Unicode complets).

## Tables

### `settings`

Configuration globale du site (nom, slogan, paramètres de la landing, etc.).

```sql
CREATE TABLE settings (
    `key`  VARCHAR(64) PRIMARY KEY,
    value  TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Clés utilisées** :

| Clé | Description |
|---|---|
| `site_name` | Nom du site, affiché en header |
| `tagline` | Slogan sous le nom |
| `header_baseline` | Phrase d'accueil dans la barre du haut |
| `about_text` | Texte "À propos" sur la page d'accueil |
| `landing_eyebrow` | Badge en haut de la landing |
| `landing_title` | Override du titre sur landing (sinon fallback `site_name`) |
| `landing_subtitle` | Override du slogan sur landing |
| `landing_cta_text` | Texte du bouton principal de la landing |
| `landing_cta_url` | URL cible du bouton (relative, défaut `blog/`) |
| `landing_footer_text` | Texte du lien en bas de landing |
| `landing_footer_url` | URL du lien en bas |
| `landing_show_pulse` | `1` ou `0` — pulse vert dans le badge |
| `landing_bg_color` | Hex `#rrggbb` |
| `landing_text_color` | Hex |
| `landing_muted_color` | Hex |
| `landing_accent_color` | Hex — clair du dégradé du bouton |
| `landing_accent_dark` | Hex — foncé du dégradé |
| `landing_blob_1/2/3` | Hex — couleurs des 3 blobs d'ambiance |

Accès via `includes/settings.php` :

```php
$nom    = get_setting('site_name', 'Mon Blog');  // fallback si vide
set_setting('site_name', 'Nouveau nom');         // upsert
$all    = get_all_settings();                    // tout en mémoire (cache)
```

### `users`

Comptes utilisateurs (web + mobile API).

```sql
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(80)  NOT NULL,
    prenom        VARCHAR(80)  NOT NULL,
    email         VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_admin      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- `password_hash` = `password_hash($plain, PASSWORD_BCRYPT)` (bcrypt, salt automatique)
- `email` longueur 190 pour respecter la limite de 767 octets sur les index utf8mb4 (190 × 4 = 760)
- `is_admin = 1` donne accès aux pages d'admin

### `articles`

Articles + sous-articles via auto-référence.

```sql
CREATE TABLE articles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    parent_id   INT DEFAULT NULL,
    titre       VARCHAR(190) NOT NULL,
    image       VARCHAR(255) DEFAULT NULL,
    contenu     TEXT NOT NULL,
    sources     TEXT DEFAULT NULL,
    layout      VARCHAR(255) DEFAULT NULL,
    updated_at  DATETIME NULL DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_articles_parent (parent_id),
    CONSTRAINT fk_articles_user   FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_articles_parent FOREIGN KEY (parent_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- `parent_id NULL` → article racine
- `parent_id = X` → sous-article de X
- Cascade : supprimer un article supprime ses sous-articles, ses images, ses commentaires
- `image` = chemin relatif type `uploads/img-abc123.jpg` (le chemin du fichier, pas une URL)

### `article_images`

Galerie additionnelle attachée à un article.

```sql
CREATE TABLE article_images (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    path       VARCHAR(255) NOT NULL,
    caption    VARCHAR(255) DEFAULT NULL,
    position   INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_images_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- `path` = chemin relatif (ex `uploads/gallery-xyz.png`)
- `position` = ordre d'affichage dans la galerie

### `comments`

Commentaires sous les articles.

```sql
CREATE TABLE comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    user_id    INT NOT NULL,
    contenu    TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### `social_links`

Liens réseaux sociaux affichés en haut du site.

```sql
CREATE TABLE social_links (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    platform   VARCHAR(40)  NOT NULL UNIQUE,
    url        VARCHAR(255) NOT NULL,
    icon       VARCHAR(60)  NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- `platform` = nom unique (ex "twitter", "instagram")
- `icon` = classe FontAwesome (ex `fa-twitter`)

### `api_tokens` (optionnel selon la branche)

Tokens d'authentification pour l'API mobile.

```sql
CREATE TABLE api_tokens (
    token      VARCHAR(64) PRIMARY KEY,
    user_id    INT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

- Token = `bin2hex(random_bytes(32))` (64 caractères hex)
- TTL : 30 jours par défaut
- Vérifié dans `api/_auth.php` à chaque requête API protégée

## Connexion PDO

Configurée dans `blog/includes/db.php`. Constantes lues depuis `blog/config/config.php` (non commit) :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'blog');
define('DB_USER', 'root');
define('DB_PASS', '');
```

PDO est instancié avec :
- `PDO::ERRMODE_EXCEPTION` → toute erreur SQL devient une exception PHP
- `PDO::ATTR_DEFAULT_FETCH_MODE = PDO::FETCH_ASSOC` → fetch comme tableau associatif
- `PDO::ATTR_EMULATE_PREPARES = false` → prepared statements MySQL natifs (sécurité)
- charset `utf8mb4`

Toujours utiliser **prepared statements** :

```php
// BIEN
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

// PAS BIEN (SQL injection)
$pdo->query("SELECT * FROM users WHERE email = '$email'");
```

## Backup / Restore

### Backup manuel (mysqldump côté serveur — accès SSH)

```bash
mysqldump -u u489596434_admin -p u489596434_blog > backup.sql
```

### Backup via le projet (recommandé)

Dans l'admin → `📦 Import / Export JSON` → bouton `Télécharger l'export JSON`.

L'export contient toutes les tables (sauf `api_tokens` qui est régénérable) au format JSON, lisible à l'œil nu.

### Restore via JSON

Dans l'admin → `📦 Import / Export JSON` → drag-and-drop le fichier JSON → confirme.

⚠️ L'import **remplace toutes les données existantes**.

Détails techniques de l'import : voir [06-sync.md](06-sync.md).

## Limites de schéma à connaître

- `articles.contenu` est en `TEXT` (max 64 Ko). Si tu veux des articles plus longs, passer en `LONGTEXT`.
- Pas d'index sur `articles.created_at` → si tu as des milliers d'articles, ajouter un index pour accélérer la liste.
- Pas de soft delete : `DELETE` est physique. Pour un système de corbeille, ajouter une colonne `deleted_at DATETIME NULL` et filtrer dans toutes les requêtes.
