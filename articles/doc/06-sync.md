# 06 — Synchronisation local → serveur

Le système de synchronisation permet d'envoyer **toutes les données** d'une instance locale vers une instance distante :
- Base de données complète (articles, commentaires, utilisateurs, settings, etc.)
- Fichiers du dossier `uploads/` (images attachées aux articles)

C'est complémentaire du déploiement GitHub Actions :
- **GitHub Actions** = déploie le **code**
- **Sync** = déploie les **données**

## Modèle de sécurité

Le défi : permettre à un local d'écraser entièrement une BDD prod, **sans** stocker un secret partagé permanent quelque part (parce que ce secret deviendrait une cible évidente).

Solution choisie : **clés à usage unique générées sur le serveur**.

```
1. Admin se connecte sur le serveur            ┐
2. Génère une clé sur sync_keys.php (serveur)  │  Action humaine
3. Copie/colle la clé sur le local             │  intentionnelle
4. Envoie depuis sync_push.php (local)         ┘
5. Serveur consomme la clé, traite, OK ou KO
```

Propriétés :
- Aucune clé permanente en `config.php` (donc pas de fuite via Git ou backup)
- Chaque sync nécessite une intervention manuelle côté serveur
- Clés à usage unique : utilisée → invalidée
- Expiration courte (5 min à 24 h max)
- Stockées **en dehors** de la BDD pour qu'une sync ne puisse pas s'auto-révoquer

## Fichiers impliqués

```
blog/
├── includes/
│   ├── sync_keys.php       (gestion fichier-stockage des clés)
│   └── sync_dump.php       (dump SQL + zip uploads + import JSON)
│
├── pages/
│   ├── sync_keys.php       (page admin — génération de clés, côté serveur)
│   ├── sync_push.php       (page admin — envoi des données, côté local)
│   └── sync_json.php       (export/import JSON manuel)
│
├── api/
│   └── sync_receive.php    (endpoint qui reçoit le dump)
│
└── config/
    └── sync_keys.json      (storage, gitignored + FTP-excluded)
```

## Cheminement complet d'une sync

### Étape 1 — Génération de la clé (côté serveur)

```
admin loggé → https://blog.mariondelval.com/pages/sync_keys.php
```

L'admin choisit une durée de validité (5 min à 24 h) et clique **Générer une nouvelle clé**.

**Code derrière** (`pages/sync_keys.php`) :

```php
$newKey = sync_key_generate($ttl);  // includes/sync_keys.php
```

Ce qui se passe dans `sync_key_generate()` :

```php
function sync_key_generate(int $ttlSeconds = 3600): array {
    $keys = _sync_keys_read();         // lit config/sync_keys.json
    // Purge les clés expirées ou consommées
    $keys = array_filter($keys, function ($k) use ($now) {
        return empty($k['used_at']) && ($k['expires_at'] ?? 0) > $now;
    });

    $token = bin2hex(random_bytes(32));  // 64 hex chars
    $entry = [
        'token'      => $token,
        'created_at' => date('Y-m-d H:i:s'),
        'expires_at' => $now + $ttlSeconds,
        'used_at'    => null,
    ];
    $keys[] = $entry;
    _sync_keys_write($keys);             // réécrit config/sync_keys.json
    return $entry;
}
```

Le fichier `blog/config/sync_keys.json` ressemble à :

```json
[
  {
    "token": "78f83005679570476774cdfdb6c8c65f9c88252e44e678b6565129a5d35d694d",
    "created_at": "2026-05-27 21:42:13",
    "expires_at": 1748382133,
    "used_at": null
  }
]
```

La page affiche la clé en clair une seule fois avec un bouton **📋 Copier**.

### Étape 2 — Envoi des données (côté local)

```
admin loggé → http://localhost/Blog/blog/pages/sync_push.php
```

L'admin colle la clé, vérifie l'URL de destination, coche la case de confirmation, clique **Envoyer maintenant**.

**Code derrière** (`pages/sync_push.php`) :

```php
// 1. Génère le dump SQL dans un fichier temporaire
sync_build_sql_dump($pdo, $sqlFile);

// 2. Génère un ZIP du dossier uploads/
$nbFiles = sync_build_uploads_zip($uploadsDir, $zipFile);

// 3. POST en multipart/form-data
$ch = curl_init($remoteUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 900,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_POSTFIELDS     => [
        'token'        => $token,
        'sql_dump'     => new CURLFile($sqlFile, 'application/sql', 'dump.sql'),
        'uploads_zip'  => new CURLFile($zipFile, 'application/zip', 'uploads.zip'),
    ],
]);
$response = curl_exec($ch);
```

Pendant l'opération, un **overlay plein écran avec spinner** s'affiche pour indiquer que le travail est en cours.

#### Format du dump SQL généré

`sync_build_sql_dump($pdo, $outputFile)` produit un fichier SQL avec ce format :

```sql
-- Sync dump genere le 2026-05-27T21:50:00+00:00
SET FOREIGN_KEY_CHECKS=0;

-- Table settings
TRUNCATE TABLE `settings`;
INSERT INTO `settings` (`key`,`value`) VALUES ('site_name','Mon Blog');
INSERT INTO `settings` (`key`,`value`) VALUES ('tagline','Le blog');
...

-- Table users
TRUNCATE TABLE `users`;
INSERT INTO `users` (`id`,`nom`,`prenom`,`email`,`password_hash`,`is_admin`,`created_at`) VALUES (1,'Delval','Marion','admin@...','$2y$10$...',1,'2026-01-01 12:00:00');
...

-- (autres tables)

SET FOREIGN_KEY_CHECKS=1;
```

Les valeurs sont quotées via `$pdo->quote()`, ce qui échappe les caractères spéciaux et les apostrophes.

#### Format du ZIP des uploads

`sync_build_uploads_zip($uploadsDir, $outputFile)` crée un ZIP qui contient le contenu de `blog/uploads/` à plat :

```
uploads.zip
├── img-abc123.jpg
├── gallery-xyz.png
└── ...
```

Le `.gitkeep` est exclu, les sous-dossiers éventuels sont préservés.

### Étape 3 — Réception serveur

```
POST /api/sync_receive.php
multipart/form-data:
  - token = "78f83..."
  - sql_dump = <fichier .sql>
  - uploads_zip = <fichier .zip>
```

**Code derrière** (`api/sync_receive.php`) :

```php
// 1. Validation de la clé
if (!sync_key_consume($token)) {
    json_error('Cle invalide, expiree ou deja utilisee', 403);
}

// 2. Application du dump SQL
sync_apply_sql_dump($pdo, $_FILES['sql_dump']['tmp_name']);

// 3. Extraction du zip uploads
$count = sync_apply_uploads_zip($_FILES['uploads_zip']['tmp_name'], $uploadsDir);

json_response(['ok' => true, 'message' => 'Synchronisation terminee', 'uploads' => $count]);
```

`sync_key_consume()` :

```php
function sync_key_consume(string $token): bool {
    $keys = _sync_keys_read();
    foreach ($keys as $i => $k) {
        if (!empty($k['used_at'])) continue;
        if (($k['expires_at'] ?? 0) <= time()) continue;
        if (hash_equals((string)$k['token'], $token)) {
            $keys[$i]['used_at'] = date('Y-m-d H:i:s');
            _sync_keys_write($keys);
            return true;
        }
    }
    return false;
}
```

Utilise `hash_equals` (comparaison timing-safe) pour éviter les attaques par timing.

`sync_apply_sql_dump()` :

```php
function sync_apply_sql_dump(PDO $pdo, string $sqlFile): void {
    $sql = file_get_contents($sqlFile);
    $statements = preg_split("/;\s*\n/", $sql);
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || preg_match('/^--/', $stmt)) continue;
        $pdo->exec($stmt);
    }
}
```

Pas de transaction PDO ici : `TRUNCATE TABLE` est une DDL MySQL qui provoque un **commit implicite**, ce qui invaliderait un `rollBack` ultérieur. Les erreurs remontent telles quelles.

`sync_apply_uploads_zip()` :

```php
function sync_apply_uploads_zip(string $zipFile, string $uploadsDir): int {
    // Vide le dossier existant (sauf .gitkeep)
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $f) {
        if ($f->getFilename() === '.gitkeep') continue;
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    // Extrait le ZIP
    $zip = new ZipArchive();
    $zip->open($zipFile);
    $zip->extractTo($uploadsDir);
    $zip->close();
}
```

### Étape 4 — Retour au local

Le local reçoit la réponse JSON et affiche le résultat :

- ✅ "Synchronisation réussie (42 fichiers uploads transmis)"
- ❌ "Cle invalide, expiree ou deja utilisee (HTTP 403)"

## Import / Export manuel (alternative sans clé)

`pages/sync_json.php` propose trois opérations d'export et deux d'import, sans clé / sans serveur distant :

### Exports disponibles

| Action GET | Helper | Sortie |
|---|---|---|
| `?action=export` | `sync_export_json()` | `.json` (BDD seulement, pas d'images) |
| `?action=export_images` | `sync_build_uploads_zip()` | `.zip` (contenu de `uploads/`) |
| `?action=export_full` | `sync_build_full_export()` | `.zip` (contient `data.json` + dossier `uploads/`) |

```php
// Export JSON
$data = sync_export_json($pdo);
header('Content-Disposition: attachment; filename="blog-export-2026-05-27.json"');
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Export complet : un ZIP qui contient data.json + uploads/
$tmp = tempnam(sys_get_temp_dir(), 'fullexp_');
sync_build_full_export($pdo, $UPLOADS_DIR, $tmp);
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="blog-full-' . date('Y-m-d') . '.zip"');
readfile($tmp);
```

### Imports disponibles

Deux formulaires séparés (sur la même page) :
- **JSON** (`action=import`) → remplace toutes les tables BDD via `sync_import_json()`
- **Images ZIP** (`action=import_images`) → purge `uploads/` puis extrait le ZIP via `sync_apply_uploads_zip()`

L'admin glisse-dépose un `.json` dans la dropzone. PHP fait :

```php
$data = json_decode(file_get_contents($_FILES['json_file']['tmp_name']), true);
$imported = sync_import_json($pdo, $data);
```

`sync_import_json()` :

```php
function sync_import_json(PDO $pdo, array $data): array {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (SYNC_TABLES as $table) {
        if (!isset($data[$table])) continue;
        try {
            $pdo->exec("TRUNCATE TABLE `{$table}`");
            foreach ($data[$table] as $row) {
                $cols = '`' . implode('`,`', array_keys($row)) . '`';
                $placeholders = implode(',', array_fill(0, count($row), '?'));
                $stmt = $pdo->prepare("INSERT INTO `{$table}` ({$cols}) VALUES ({$placeholders})");
                $stmt->execute(array_values($row));
            }
            $imported[$table] = count($data[$table]);
        } catch (Throwable $e) {
            $errors[$table] = $e->getMessage();
        }
    }
    return $imported;
}
```

Try/catch par table : une table foireuse ne casse plus l'import des autres.

⚠️ Le JSON export contient les **hashes de mots de passe** : ne jamais commit, ne jamais partager sans précaution. Le pattern `blog-export-*.json` est dans `.gitignore`.

## Cas d'usage typique

```
SEMAINE 1
  Lundi    → tu écris 3 articles en local
  Mercredi → tu retouches une image
  Vendredi → tu pousses tout sur le serveur :
             1. Sur le serveur : génère une clé (1h)
             2. Sur le local : sync_push.php avec la clé
             3. Vérification : visite blog.mariondelval.com

SEMAINE 2
  → recommence
```

## Limites du système

- Pas de **fusion** : c'est toujours un **remplacement total** du distant par le local. Si quelqu'un commente en ligne, il sera perdu à la prochaine sync.
- La taille du POST peut atteindre plusieurs Mo (toutes les images). Le `CURLOPT_TIMEOUT` est à 900s (15 min) pour permettre ça.
- Sur Hostinger shared, `upload_max_filesize` et `post_max_size` peuvent limiter. Si tu as beaucoup d'images, peut nécessiter de tuner `.htaccess`.

## Diagnostic

Pour vérifier l'état des clés actives sur le serveur, va sur `pages/sync_keys.php` :
- Liste des clés actives avec compte à rebours en temps réel
- Historique des 15 dernières clés (utilisées ou expirées)
- Bouton "Révoquer toutes les clés actives"

Pour debug l'endpoint, ajoute temporairement en haut de `api/sync_receive.php` :

```php
file_put_contents('/tmp/sync_debug.log', date('c').' '.print_r($_POST,true).PHP_EOL, FILE_APPEND);
```

Et regarde le log via FTP.
