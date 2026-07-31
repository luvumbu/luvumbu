# 🔑 Luvumbu ID — Connexion unique (SSO)

Service d'**identité partagée** : l'utilisateur se connecte **une fois** (via Google) et
il est reconnu par **toutes les applications** de l'écosystème.

- **Hub de connexion :** `https://luvumbu.com/sso/` — **la seule page de connexion du site**
- **Gestion des comptes :** `https://luvumbu.com/sso/accounts_admin.php` (réservée aux admins)
- **Démo protégée :** `https://luvumbu.com/sso/demo.php`
- Aucune base de données requise : l'identité tient dans un **JWT signé** (secret partagé),
  et les comptes dans un simple fichier PHP hors dépôt.

## Comment ça marche
```
App → redirige vers le hub (?app=NOM&return=URL)
Hub → l'utilisateur se connecte : Google, ou e-mail + mot de passe
Hub → vérifie le compte dans l'ANNUAIRE CENTRAL et son rôle sur l'app demandée
Hub → émet un JWT signé (avec les rôles) + pose un cookie partagé (LUVID)
Hub → REDISTRIBUE vers l'app avec ?sso=<jwt>
App → vérifie le JWT (secret partagé) → ouvre sa session locale
```
Le hub est donc un **aiguillage** : une identité valide ne suffit pas, il faut aussi un rôle
sur l'application demandée, sinon l'entrée est refusée **avant** d'arriver sur l'app.

## Annuaire central des comptes
Fichier `sso/accounts.local.php` (généré, **gitignoré**), un compte par e-mail :

| Champ | Rôle |
|-------|------|
| `email` | identifiant, et adresse Google le cas échéant |
| `name` | nom affiché |
| `pass_hash` | `password_hash()`, ou `null` pour « Google seulement » |
| `roles` | `['*' => 'admin', 'blog' => 'user', …]` — **absence = aucun accès** |
| `disabled` | coupe toutes les connexions du compte |

- La clé `'*'` vaut pour toutes les applications ; une clé précise (`'blog'`) l'emporte sur `'*'`.
- Rôles possibles : `admin`, `user`.
- **Amorçage automatique** : tant que le fichier n'existe pas, le compte déclaré dans
  `secret.local.php` (`local_user` + `password`) fait office d'administrateur `'*'`.
  Rien à migrer : l'accès existant continue de fonctionner et le fichier se crée
  à la première connexion.
- Une adresse **Google inconnue de l'annuaire est refusée** (sauf annuaire vide : la première
  adresse qui se présente l'adopte comme administrateur).
- Blocage 15 min après 5 échecs de mot de passe (par adresse + IP).

Tout se règle depuis **`sso/accounts_admin.php`** : créer un compte, cocher ses accès
application par application, désactiver, supprimer. Le dernier administrateur ne peut pas
être supprimé ni rétrogradé.
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
$user = luvumbu_user();      // tableau [email,name,sub,picture,roles] ou null
```
Rôles (lus dans le jeton, aucune requête) :
```php
luvumbu_role($user, 'blog');      // 'admin' | 'user' | 'none'
luvumbu_is_admin($user, 'blog');  // bool
luvumbu_require_admin('blog');    // connexion + rôle admin, sinon 403
```
Se déconnecter (global) :
```php
luvumbu_logout(true, 'https://mon-app/…');   // efface le cookie partagé
```

**L'URL du hub est trouvée toute seule** : tant que le dossier `sso/` est servi par le même
hôte que l'app, elle est déduite du chemin disque — donc `http://localhost/luvumbu/sso/` en
local et `https://luvumbu.com/sso/` en ligne, sans rien configurer.

Si l'app est sur un **autre serveur**, copie-y le dossier `sso/` (avec le **même**
`secret.local.php`) ou force l'URL du hub avant l'include :
```php
define('LUVUMBU_HUB', 'https://luvumbu.com/sso/');
require __DIR__ . '/sso/client.php';
```

## Mise en service — `sso/install.php`
Sur un serveur neuf, **ne pas créer `secret.local.php` à la main** : ouvrir
`https://luvumbu.com/sso/install.php`. La page génère la clé, écrit le fichier et crée le
premier compte administrateur. Protégée par la session `admin.php`, qui sait se rabattre sur
les identifiants MySQL tant que le SSO n'est pas actif.

> ⚠️ **Le secret et le premier compte ne se dissocient pas.** Dès que le secret existe,
> `admin.php` et `_gestion` cessent d'accepter leur formulaire de secours et exigent le hub :
> sans compte dans l'annuaire, plus personne n'entre. L'assistant impose donc un e-mail et un
> mot de passe (8 caractères minimum).

Voir aussi `GUIDE_DEPLOIEMENT.md` à la racine, et `etat.php` qui indique si le fichier est
présent sur le serveur.

## Configuration — `sso/secret.local.php` (généré par l'assistant, gitignoré)
```php
<?php return [
  'secret'           => '<longue chaîne aléatoire, ≥ 32 caractères>',   // OBLIGATOIRE, la MÊME partout
  'google_client_id' => '878381681024-…apps.googleusercontent.com',     // ID client Google
  'cookie_domain'    => '',   // '' = hôte courant ; '.luvumbu.com' pour partager entre sous-domaines
  'password'         => '',   // porte de secours sans Google ('' = désactivée)
  'local_user'       => ['email' => 'luvumbu.n@gmail.com', 'name' => 'Luvumbu'],
];
```
- Le **`secret`** doit être **identique** dans toutes les apps qui vérifient les jetons.
- Générer un secret : `php -r "echo bin2hex(random_bytes(32));"`
- L'**ID client Google** : sa console doit autoriser l'origine `https://luvumbu.com` (déjà le cas
  pour l'ID existant du projet). Le même ID peut servir toutes les apps.

## Sécurité
- JWT signé HS256 (falsification impossible sans le secret) ; cookie `LUVID` valable 7 jours.
- Le jeton qui transite par l'URL (`?sso=…`) n'est qu'un **jeton de transport, valable 2 min** :
  l'app le range aussitôt dans sa propre session et nettoie l'URL.
- Cookie `LUVID` : HttpOnly, SameSite=Lax, Secure en HTTPS.
- Redirections `return` validées contre une **liste blanche d'hôtes** (anti open-redirect) :
  luvumbu.com, bokonzi.com, localhost (modifiable dans `index.php`).
- Le token Google est vérifié côté serveur (`oauth2.googleapis.com/tokeninfo`), audience contrôlée.
- Ne jamais committer `secret.local.php`.

## Fichiers
| Fichier | Rôle |
|---------|------|
| `lib.php` | JWT (sign/verify), vérif Google, cookie partagé |
| `install.php` | Mise en service : écrit le secret + crée le premier admin |
| `accounts.php` | Annuaire central : comptes, mots de passe, rôles, anti-force brute |
| `index.php` | Hub : la page de connexion unique + l'aiguillage vers les apps |
| `accounts_admin.php` | Gestion des comptes et des accès (admins) |
| `client.php` | Helper à inclure dans les apps (`luvumbu_require_login`, `luvumbu_role`…) |
| `logout.php` | Déconnexion globale |
| `demo.php` | Page de démonstration protégée |
| `secret.local.php` | Secret + ID Google (gitignoré, à créer) |
| `accounts.local.php` | Annuaire des comptes (gitignoré, généré) |

## Déploiement par app (feuille de route)
1. ✅ Hub + client + démo.
2. ✅ Secret + ID client Google unique (`secret.local.php`).
3. ✅ **cv_luvumbu** — première app branchée, schéma de référence :
   - `sso_login.php` — traduit l'identité du hub en compte local (e-mail → compte) ;
   - `includes/auth.php` → `require_login()` part vers `sso_login.php?next=<page demandée>` ;
   - `logout.php` — déconnexion globale si la session venait du SSO.
4. ✅ **objectifs** (Élan).
5. ✅ **Annuaire central + rôles par application** (`accounts.php`, `accounts_admin.php`) :
   le hub sait désormais gérer plusieurs comptes, ce qui permet de brancher les apps
   multi-utilisateurs (blog, PhotoSync, DualCam) qui ont chacune leur table `users`.
6. ✅ **admin.php (racine) + `_gestion`** — app de validation :
   - plus aucun formulaire dans `admin.php` : il part au hub (`app=admin`) ;
   - le rôle `admin` sur l'app `admin` est exigé ; l'ancien formulaire MySQL ne
     reste qu'en **secours**, si `sso_ready()` est faux sur le serveur ;
   - `_gestion` continue de fonctionner sans modification (session `pf_admin` partagée) ;
   - la déconnexion devient globale quand la session venait du hub.
7. ⏳ À brancher : `articles/blog`, `dropbox/public_html` (PhotoSync),
   `DualCam` + `DualCam_app/server`, puis RPN, anniversaire, tamagotchi, marion.

### Recette de branchement d'une app
1. Choisir un **nom d'app** (`blog`, `photosync`, …) et l'ajouter à `LUVID_APPS`
   dans `accounts_admin.php` pour qu'il apparaisse dans les cases à cocher.
2. Dans l'app, remplacer la page de connexion par un `sso_login.php` qui fait :
   `luvumbu_require_login('<app>')` → e-mail → compte local → session locale.
3. Faire pointer le `require_login()` de l'app vers ce fichier.
4. Rendre la déconnexion globale quand la session vient du hub.
