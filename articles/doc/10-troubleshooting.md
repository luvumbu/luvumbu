# 10 — Dépannage (FAQ)

## Déploiement GitHub Actions

### ❌ "Cannot connect to FTP server" / EHOSTUNREACH

**Causes possibles** :
1. `FTP_HOST` mal renseigné dans les secrets GitHub
2. Hostinger bloque temporairement la région du runner
3. Le compte FTP a été désactivé / mot de passe changé

**Solutions** :
- Recopie exactement le hostname depuis hPanel → Fichiers → Comptes FTP
- Essaie de basculer `FTP_PROTOCOL` de `ftps` à `ftp`
- Teste les credentials avec FileZilla pour confirmer qu'ils marchent en dehors d'Actions

### ❌ "EAI_AGAIN" (DNS temporaire)

Réessaie le workflow (onglet Actions → bouton "Re-run all jobs").

### ❌ Le workflow termine en succès mais le site ne change pas

Causes possibles :
1. Le cache navigateur affiche encore l'ancienne version → Ctrl+F5
2. Le fichier déployé est dans le mauvais dossier serveur
3. Tu regardes le mauvais domaine (`mariondelval.com` vs `blog.mariondelval.com`)

**Vérification** : connecte-toi en FTP via FileZilla et regarde directement le timestamp du fichier. Doit correspondre à l'heure du push.

### ❌ Plusieurs déploiements simultanés

GitHub Actions peut paralléliser les pushes rapides. Pour éviter les conflits FTP, ajoute en haut du workflow :

```yaml
concurrency:
  group: deploy
  cancel-in-progress: false
```

→ Si un déploiement tourne, le suivant attend la fin avant de démarrer.

## Synchronisation

### ❌ "Cle invalide, expiree ou deja utilisee" (HTTP 403)

**Causes** :
1. La clé a déjà été utilisée → génère-en une nouvelle
2. La clé a expiré (durée TTL dépassée) → génère-en une nouvelle
3. Tu colles dans le mauvais champ (typo)
4. Tu cibles la mauvaise URL distante

**Solutions** :
- Va sur le serveur → `pages/sync_keys.php` → vérifie l'historique pour voir si la clé a `used_at` rempli
- Recopie la clé sans espace ni saut de ligne

### ❌ "There is no active transaction" sur l'import JSON

**Cause** : version obsolète du code. Corrigé dans le commit `8115f0f` — `TRUNCATE TABLE` provoquait un commit implicite MySQL qui invalidait le `rollBack()` PHP.

**Solution** : `git pull` la dernière version, redéploie.

### ❌ Le sync POST échoue avec timeout

**Causes** :
- Beaucoup d'images dans `uploads/`, le ZIP devient gros (>50 Mo)
- Connexion lente entre ton local et Hostinger

**Solutions** :
- Augmente `CURLOPT_TIMEOUT` dans `pages/sync_push.php` (déjà à 900s = 15 min)
- Sur Hostinger, vérifie `post_max_size` et `upload_max_filesize` dans `php.ini` (via hPanel). Doivent être ≥ taille du ZIP.

### ❌ "Body too large" / 413 du serveur

Hostinger limite la taille des uploads. Augmente via :

```apache
# blog/.htaccess (prod)
php_value upload_max_filesize 256M
php_value post_max_size 256M
php_value max_input_time 900
php_value max_execution_time 900
```

## App mobile PWA

### ❌ La nouvelle icône ne s'affiche pas après mise à jour

**Cause** : l'OS (Android/iOS/Windows) met en cache l'icône à l'installation. Le manifest peut changer, l'icône ne se rafraîchit pas automatiquement.

**Solutions** :
- **Sur l'appareil** : désinstaller la PWA, puis réinstaller depuis le navigateur
- **Patience** : Chrome détecte les changements de manifest toutes les ~24 h

### ❌ La bannière "Mise à jour disponible" ne s'affiche jamais

**Cause** : `APP_VERSION` côté client = `MOBILE_APP_VERSION` côté serveur. Il faut **les deux** différentes pour que la bannière apparaisse.

**Vérification** :
- Console JS de l'app : `console.log(APP_VERSION)` → doit être < à la valeur du serveur
- Côté serveur : `curl https://blog.mariondelval.com/api/version.php`

### ❌ L'app affiche `offline.html` même en ligne

**Cause** : le service worker n'a pas été désinscrit, ou le sw.js du serveur est encore une ancienne version qui interceptait les fetch.

**Solution** :
1. F12 → Application → Service Workers → Unregister
2. F12 → Application → Storage → Clear site data
3. Ctrl+Shift+R

### ❌ Le login API ne marche pas (toujours 401)

**Vérifications** :
- Le serveur a-t-il bien une BDD installée ? Curl `/api/login.php` doit donner un 400 sur un POST vide, pas un 503
- `Authorization: Bearer <token>` est-il bien envoyé ? F12 → Network → onglet Headers
- Le token est-il expiré ? La table `api_tokens` a un champ `expires_at`

## Landing page

### ❌ Le titre n'apparaît pas / la landing reste vide

**Cause habituelle** : l'API `blog/api/site_info.php` est inaccessible (404 ou 503).

**Vérifications** :
- Curl `https://mariondelval.com/blog/api/site_info.php` → doit renvoyer du JSON
- Si 503 "Blog non installé" → il faut faire `install.php` sur le sous-domaine
- Si 404 → le sous-domaine pointe au mauvais endroit (hPanel → Sous-domaines)

### ❌ Les couleurs ne s'appliquent pas

**Cause** : les CSS custom properties ne sont pas écrites au bon endroit.

Inspecte la page :
```javascript
getComputedStyle(document.documentElement).getPropertyValue('--bg-1')
```

Si ça retourne du vide, le JS n'a pas tourné (regarde la console pour des erreurs).

### ❌ Le bouton CTA pointe vers le mauvais endroit

Va dans `Admin → Apparence accueil → Lien du bouton principal`. Vérifie que c'est un chemin **relatif** (`blog/`) et non absolu (`/blog/`).

| Avec `blog/` | Avec `/blog/` |
|---|---|
| Marche partout (local + prod) | Marche seulement si servi à la racine |

## Base de données

### ❌ "SQLSTATE[HY000] [2002] Connection refused"

MySQL n'est pas accessible :
- Sur XAMPP : MySQL pas démarré → ouvre XAMPP Control → Start
- Sur Hostinger : credentials erronés dans `config.php` → vérifier hPanel

### ❌ "Table 'blog.users' doesn't exist"

Les tables n'ont pas été créées :
- Visite `/install.php` pour relancer l'installeur
- Ou exécute manuellement `blog/sql/schema.sql` dans phpMyAdmin

### ❌ Caractères français qui s'affichent en "Ã©"

Problème d'encodage. Vérifie :
- La base est en `utf8mb4_unicode_ci`
- La connexion PDO a bien `charset=utf8mb4`
- Les fichiers PHP sont sauvegardés en UTF-8 sans BOM

## XAMPP local

### ❌ Apache refuse de démarrer (port 80 occupé)

Cause : Skype, IIS, ou un autre service utilise le port 80.

Solutions :
- Couper l'autre service
- Changer le port d'Apache dans `xampp/apache/conf/httpd.conf` : `Listen 80` → `Listen 8080`. Puis accéder via `http://localhost:8080/Blog/`

### ❌ Erreur 500 sur le blog en local mais pas de message

Active l'affichage des erreurs PHP. Dans `xampp/php/php.ini` :

```ini
display_errors = On
error_reporting = E_ALL
```

Redémarre Apache. L'erreur exacte apparaîtra dans la page.

## Cache navigateur

### ❌ "Mes changements CSS ne s'appliquent pas"

Forcer le rafraîchissement complet :
- **Chrome/Edge/Firefox** : `Ctrl+Shift+R` (Windows) ou `Cmd+Shift+R` (Mac)
- **Outil de dev** : F12 → onglet Network → coche "Disable cache" (tant que F12 est ouvert)
- **Nuke total** : F12 → Application → Storage → Clear site data

### ❌ Le favicon reste l'ancien

Solutions :
- Ajoute `?v=N` à la fin du href dans `header.php` et incrémente
- Ou vide totalement le cache via F12 → Application → Storage → Clear site data
- Sur la PWA installée : désinstalle / réinstalle

## Git / GitHub

### ❌ "fatal: not a git repository"

Tu n'es pas dans le bon dossier. Vérifie avec `pwd` et `ls -la` (le dossier `.git` doit exister à la racine).

### ❌ "Push rejected" / branch protection

GitHub bloque le push direct sur `master`. Solutions :
- Désactiver la branch protection dans Settings → Branches
- Ou créer une PR depuis une branche feature

### ❌ Push après modification du sous-module / .git mal placé

C'est arrivé lors du restructure dans `blog/`. Si tu as déplacé `.git` par erreur :

```bash
cd C:\xampp\htdocs\Blog
mv blog/.git .git
mv blog/.github .github
mv blog/empty-root empty-root  # si applicable
git status   # vérifie que tout est OK
```

## Erreurs "weird" à diagnostiquer

### Page blanche, aucun message d'erreur

Cause habituelle : erreur fatale PHP en mode prod (display_errors off).

Solution rapide en local :
```php
// Tout en haut du script
ini_set('display_errors', '1');
error_reporting(E_ALL);
```

### "Headers already sent"

Cause : du `echo` ou du whitespace avant un `header()` ou `setcookie()`.

Solution : vérifie qu'aucun caractère n'est envoyé avant les headers. Souvent un BOM UTF-8 ou des espaces avant `<?php`.

## Quand vraiment rien ne marche

1. Récupère le **dernier export JSON** propre depuis l'admin
2. Sauvegarde le contenu de `blog/uploads/` en local (via FTP)
3. Fais un `git pull` propre
4. Réinstalle proprement (locale + serveur) avec `install.php`
5. Importe l'export JSON
6. Réuploade les images dans `uploads/`

Si même ça échoue : crée une issue GitHub avec :
- Le message d'erreur complet
- L'URL exacte qui pose problème
- Le navigateur utilisé
- Une capture d'écran si pertinent
