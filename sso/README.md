# 🔑 Luvumbu ID — Connexion unique (SSO)

Service d'**identité partagée** : l'utilisateur se connecte **une fois** (via Google) et
il est reconnu par **toutes les applications** de l'écosystème.

- **Hub de connexion :** `https://luvumbu.com/sso/`
- **Démo protégée :** `https://luvumbu.com/sso/demo.php`
- Aucune base de données requise : l'identité tient dans un **JWT signé** (secret partagé).

## Comment ça marche
```
App → redirige vers le hub (?app=NOM&return=URL)
Hub → l'utilisateur se connecte avec Google (une seule fois)
Hub → émet un JWT signé + pose un cookie partagé (LUVID)
Hub → renvoie vers l'app avec ?sso=<jwt>
App → vérifie le JWT (secret partagé) → ouvre sa session locale
```
La fois suivante, le cookie partagé fait que le hub renvoie **instantanément** (pas de re-login) —
y compris sur un autre domaine (bokonzi.com) grâce à la redirection.

## Intégrer une application (2 lignes)
Dans n'importe quelle page PHP à protéger :
```php
require __DIR__ . '/chemin/vers/sso/client.php';   // ajuste le chemin
$user = luvumbu_require_login();                    // redirige vers le hub si non connecté

echo "Bonjour " . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['email']) . ")";
```
Variante sans forcer la connexion :
```php
$user = luvumbu_user();      // tableau [email,name,sub,picture] ou null
```
Se déconnecter (global) :
```php
luvumbu_logout(true, 'https://mon-app/…');   // efface le cookie partagé
```

Si l'app n'est **pas** sous `luvumbu.com`, définis l'URL du hub AVANT l'include, et copie le
dossier `sso/` (avec le **même** `secret.local.php`) sur ce serveur :
```php
define('LUVUMBU_HUB', 'https://luvumbu.com/sso/');
require __DIR__ . '/sso/client.php';
```

## Configuration — `sso/secret.local.php` (À CRÉER, gitignoré)
```php
<?php return [
  'secret'           => '<longue chaîne aléatoire, ≥ 32 caractères>',   // OBLIGATOIRE, la MÊME partout
  'google_client_id' => '878381681024-…apps.googleusercontent.com',     // ID client Google
  'cookie_domain'    => '',   // '' = hôte courant ; '.luvumbu.com' pour partager entre sous-domaines
];
```
- Le **`secret`** doit être **identique** dans toutes les apps qui vérifient les jetons.
- Générer un secret : `php -r "echo bin2hex(random_bytes(32));"`
- L'**ID client Google** : sa console doit autoriser l'origine `https://luvumbu.com` (déjà le cas
  pour l'ID existant du projet). Le même ID peut servir toutes les apps.

## Sécurité
- JWT signé HS256 (falsification impossible sans le secret) ; expiration 7 jours.
- Cookie `LUVID` : HttpOnly, SameSite=Lax, Secure en HTTPS.
- Redirections `return` validées contre une **liste blanche d'hôtes** (anti open-redirect) :
  luvumbu.com, bokonzi.com, localhost (modifiable dans `index.php`).
- Le token Google est vérifié côté serveur (`oauth2.googleapis.com/tokeninfo`), audience contrôlée.
- Ne jamais committer `secret.local.php`.

## Fichiers
| Fichier | Rôle |
|---------|------|
| `lib.php` | JWT (sign/verify), vérif Google, cookie partagé |
| `index.php` | Hub de connexion (bouton Google) |
| `client.php` | Helper à inclure dans les apps (`luvumbu_require_login`, `luvumbu_user`) |
| `logout.php` | Déconnexion globale |
| `demo.php` | Page de démonstration protégée |
| `secret.local.php` | Secret + ID Google (gitignoré, à créer) |

## Déploiement par app (feuille de route)
1. ✅ Hub + client + démo (fait).
2. Brancher les apps qui utilisent DÉJÀ Google (cv_luvumbu, anniversaire, RPN, DualCam) :
   remplacer leur login Google par `luvumbu_require_login()`.
3. Brancher les autres (BOKONZI, ztransfert…) au besoin.
