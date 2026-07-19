# 09 — Sécurité

Vue d'ensemble du modèle de sécurité du projet, des protections en place, et des points d'attention.

## Surfaces d'attaque

| Surface | Risque | Mitigation |
|---|---|---|
| Login web (sessions) | Brute-force, vol de cookie | bcrypt + HTTPS + cookies httponly |
| Login API (mobile) | Brute-force, vol de token | bcrypt + tokens 30j révocables |
| Formulaires POST | CSRF | Token CSRF obligatoire |
| Uploads images | Upload de PHP exécutable | Validation MIME + extension + max 12 Mo + nom random |
| SQL | Injection | Prepared statements PDO partout |
| Sync endpoint | Vol de données / écrasement BDD | Clés à usage unique, expiration courte, HTTPS |
| BDD credentials | Vol via Git | `config.php` gitignore, jamais commité |
| Clés sync | Vol via Git ou backup | Stockées hors BDD, gitignore, FTP-exclues |
| Export JSON | Hashes mots de passe en clair | `.gitignore` `blog-export-*.json` |
| APK signing key | Vol → attaquant peut signer des MAJ malveillantes | Jamais commité, conservée localement |

## Authentification web (sessions)

Implémentée dans `blog/includes/auth.php` + sessions PHP natives.

### Login

```php
$user = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = ?')
            ->execute([strtolower($email)])
            ->fetch();
if (!$user || !password_verify($password, $user['password_hash'])) {
    return false; // identifiants invalides
}
session_regenerate_id(true);   // anti session-fixation
$_SESSION['user'] = $user;
```

- `password_verify` est timing-safe
- `session_regenerate_id(true)` régénère l'ID de session pour empêcher la fixation de session

### Logout

```php
$_SESSION = [];
session_destroy();
// Détruit aussi le cookie côté client
setcookie(session_name(), '', time() - 3600, '/');
```

### Cookies session

PHP utilise par défaut `PHPSESSID`. Le projet ajoute :

```php
// blog/.htaccess (prod)
php_value session.cookie_httponly 1
php_value session.cookie_secure 1
php_value session.cookie_samesite Lax
```

- `httponly` : pas accessible en JavaScript (anti-XSS volé)
- `secure` : transmis uniquement sur HTTPS
- `samesite=Lax` : pas envoyé sur les cross-site POST (anti-CSRF de base)

## Authentification API (tokens Bearer)

Implémentée dans `blog/api/login.php` + `blog/api/_auth.php`.

### Génération du token

```php
$token = bin2hex(random_bytes(32));   // 64 hex chars = 256 bits d'entropie
$expires = (new DateTime('+30 days'))->format('Y-m-d H:i:s');
$pdo->prepare('INSERT INTO api_tokens (token, user_id, expires_at) VALUES (?,?,?)')
    ->execute([$token, $user['id'], $expires]);
```

### Vérification

```php
// api/_auth.php
function require_api_token(PDO $pdo): array {
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/', $hdr, $m)) {
        json_error('Token manquant', 401);
    }
    $row = $pdo->prepare(
        'SELECT u.* FROM api_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.token = ? AND t.expires_at > NOW()'
    )->execute([$m[1]])->fetch();
    if (!$row) json_error('Token invalide ou expire', 401);
    return $row;
}
```

- Pas de timing-safe explicit ici car la regex filtre déjà la forme exacte → moins d'utilité
- Si tu veux durcir : faire `hash_equals($row['token'], $given)` mais ça coûte un SELECT all

### Révocation

Suppression de la ligne dans `api_tokens`. À implémenter sur logout API et sur changement de mot de passe.

## CSRF

Implémenté dans `blog/includes/helpers.php` :

```php
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check($token) {
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$token);
}
```

Tous les formulaires POST inclus :

```html
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
```

Et la page de traitement :

```php
if (!csrf_check($_POST['csrf'] ?? '')) {
    $errors[] = 'Jeton CSRF invalide, recharge la page.';
}
```

`hash_equals` est timing-safe.

## Uploads d'images

`blog/includes/upload.php` valide :

```php
const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const ALLOWED_EXTS  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const MAX_SIZE_MB   = 12;
```

À l'upload :

1. Vérifie `$file['error']` (UPLOAD_ERR_OK)
2. Vérifie `$file['size']` ≤ 12 Mo
3. Vérifie `mime_content_type()` (lecture du contenu, pas confiance au header client)
4. Vérifie l'extension dans le whitelist
5. Génère un nom random : `bin2hex(random_bytes(8)) . '.' . $ext`
6. Sauvegarde dans `blog/uploads/`

Conséquence : impossible d'uploader un fichier `.php` ou `.htaccess`.

### Bonus prod : `.htaccess` dans uploads/

Ajouter dans `blog/uploads/.htaccess` (à créer si pas déjà) :

```apache
# Refuse d'exécuter du PHP dans uploads/
<FilesMatch "\.(php|phtml|phar|inc|pl|py|rb|sh)$">
    Require all denied
</FilesMatch>
# Pas de listing
Options -Indexes
```

→ Même si un attaquant arrive à uploader un `.php`, Apache refuse de l'exécuter.

## SQL Injection

Tout passe par `PDO::prepare()` + `execute([$params])`. **Aucune concaténation directe** de variable dans une requête SQL.

À vérifier régulièrement avec :

```bash
grep -rn "query.*\$" blog/  # toute query avec interpolation directe = à corriger
```

## Synchronisation locale → serveur

Détaillé dans [06-sync.md](06-sync.md). Récap sécurité :

- **Clés à usage unique** : 64 hex chars (256 bits), random_bytes
- **Expiration courte** : 5 min - 24 h max
- **Stockage hors BDD** : `blog/config/sync_keys.json` (gitignore, FTP-exclu)
- **Validation timing-safe** : `hash_equals`
- **Admin requis** sur les 2 instances pour générer et consommer
- **CSRF** sur les formulaires de génération/consommation
- **HTTPS forcé** sur le POST (CURLOPT_SSL_VERIFYPEER = true)

Point d'attention : `sync_apply_sql_dump()` exécute du SQL en direct. Si une clé est compromise, l'attaquant peut injecter ce qu'il veut. Mitigation : il faut **aussi** avoir un compte admin sur l'instance source ET sur l'instance cible. Et l'expiration limite la fenêtre.

## Secrets jamais commités

Vérifié par `.gitignore` à plusieurs niveaux :

`Blog/.gitignore` :
```
blog-export-*.json
*.bak
```

`Blog/blog/.gitignore` :
```
config/config.php
config/sync_keys.json
uploads/*
!uploads/.gitkeep
_diagnostic.php
_fix_db.php
APK/
*.apk
*.aab
*.keystore
signing-key-info.txt
```

Pour vérifier qu'un fichier sensible n'est jamais entré dans git :

```bash
git log --all --full-history -- "blog/config/config.php"
# Si vide : ok, jamais commité
```

## Hashing des mots de passe

Algorithme : **bcrypt** via `password_hash($plain, PASSWORD_BCRYPT)`.
- Salt généré automatiquement par PHP
- Cost factor par défaut (10) → ~50 ms par hash sur du matériel normal
- `password_verify` est timing-safe

Pour renforcer (matériel récent) :
```php
password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
```

## Headers HTTP de sécurité

À ajouter dans `blog/.htaccess` (prod) :

```apache
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "geolocation=(), camera=(), microphone=()"
# Content-Security-Policy : à adapter selon ce qu'on charge
# Header always set Content-Security-Policy "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'"
```

## Audit régulier à faire

| Quoi | Fréquence | Comment |
|---|---|---|
| Liste des admins | Mensuel | `SELECT id, email FROM users WHERE is_admin = 1` |
| Tokens API actifs | Trimestriel | `SELECT user_id, expires_at FROM api_tokens` |
| Clés sync persistantes | Mensuel | Inspecter `config/sync_keys.json` |
| Logs Apache | Hebdomadaire | Repérer brute-force, IPs anormales |
| Mises à jour PHP | Quand Hostinger en propose | hPanel → version PHP |

## Incidents documentés

Aucun à ce jour. Si compromission :

1. **Désactiver** le site en mettant un `.htaccess` qui retourne 503
2. **Régénérer** les credentials BDD via hPanel
3. **Faire pivoter** les mots de passe de tous les admins
4. **Régénérer** tous les tokens API : `TRUNCATE TABLE api_tokens`
5. **Auditer** les fichiers de la racine `/public_html/blog/` pour shells PHP (`shell.php`, `cmd.php`, fichiers anormaux récents)
6. **Restaurer** depuis le dernier export JSON connu propre
7. **Examiner** les logs Apache de la période suspecte
