# Guide complet — Déploiement & configuration de luvumbu.com

Comment le site part en ligne, et **pourquoi une application fraîchement déployée est presque
toujours cassée la première fois**. En ligne : `https://luvumbu.com/etat.php`.

> Fichier de doc — placé à la racine du projet. **Ne pas y écrire de secret** (le dossier est servi sur le web).

---

## 1. La cause n°1 des pannes en production

Une application casse en ligne alors qu'elle tourne parfaitement en local. Le message est
presque toujours le même :

```
SQLSTATE[HY000] [1045] Access denied for user 'root'@'127.0.0.1' (using password: NO)
```

**Ce n'est pas une panne, c'est une configuration absente.**

Chaque application a un fichier qui porte ses identifiants de base de données. Ce fichier est
**exclu de Git exprès** — sinon le mot de passe se retrouverait sur GitHub, qui est public.
Conséquence directe et souvent oubliée :

> **Un `git push` n'emporte JAMAIS ces fichiers.**

Faute de configuration, l'application retombe sur ses valeurs de développement (`root`, sans mot
de passe), qui n'existent pas sur le serveur. D'où l'erreur.

Le piège n'est pas l'erreur elle-même : c'est qu'elle **peut rester invisible des semaines**,
jusqu'au jour où on rouvre par hasard la page concernée. Ce scénario s'est produit trois fois
en une seule session — PhotoSync, ATHLE_COMPETITION, DualCam.

---

## 2. Le réflexe : `etat.php`

**Après chaque mise en ligne, et devant toute erreur de base de données, ouvrir en premier :**

```
https://luvumbu.com/etat.php
```

La page recense les 12 applications et dit, pour chacune, si son fichier de configuration est
présent **sur le serveur** — avec un lien direct vers son assistant. Verdict global en tête :
« ✓ Rien ne manque » ou « ✗ 3 fichier(s) indispensable(s) manquant(s) ».

Elle est volontairement **incassable** : aucune connexion à une base, aucun chargement
d'application. Elle doit répondre même quand tout le reste est par terre. Réservée à la session
`pf_admin` — il faut donc passer par `admin.php` d'abord.

Quand une application est ajoutée au site, **l'inscrire dans le tableau `$APPS` en tête de
`etat.php`** : sans ça elle reste dans l'angle mort.

---

## 3. Les fichiers hors dépôt, application par application

| Application | Fichier attendu sur le serveur | Assistant web |
|---|---|---|
| Luvumbu ID (SSO) | `sso/secret.local.php` | `sso/install.php` |
| — annuaire des comptes | `sso/accounts.local.php` *(se crée seul)* | `sso/accounts_admin.php` |
| Gestionnaire de fichiers | `_gestion/apikey.local.php`, `_gestion/password.local.php` | — |
| PhotoSync | `dropbox/public_html/lib/db.config.php` | `dropbox/public_html/install.php` |
| DualCam | `DualCam/lib/db.config.php` ⚠️ *versionné — voir §7* | `DualCam/install.php` |
| Blog / articles | `articles/blog/config/config.php` | `articles/blog/install.php` |
| CV Luvumbu | `cv_luvumbu/config/config.php` | `cv_luvumbu/install.php` |
| Compétitions d'athlétisme | `ATHLE_COMPETITION/config/config.local.php` | `ATHLE_COMPETITION/install.php` |
| Athlétisme (app) | `athletisme_app/config/config.local.php` | `athletisme_app/install.php` |
| Tamagotchi | `tamagotchi/config/config.php` | `tamagotchi/public/install.php` |
| RPN | `rpn/config/config.php`, `rpn/config/db.php` | — |
| Bad Place | `bad_place/config/.env` | — |
| Anniversaire | `anniversaire/config.php` | — |

**Avec assistant** : tout se règle depuis le navigateur, aucun FTP.
**Sans assistant** : copier le fichier depuis la machine locale (gestionnaire Hostinger ou FTP).

Une application dont la base n'est pas joignable **renvoie automatiquement vers son assistant**
plutôt que d'afficher une erreur sans issue. C'est le comportement attendu de toute nouvelle app.

---

## 4. Comment le site part en ligne

### Le cas général : `git push`

Le serveur luvumbu.com **est lui-même un dépôt Git** et se met à jour depuis
`github.com/luvumbu/luvumbu`, branche `master`.

```bash
git push origin master
```

Compter quelques secondes à une minute avant que les fichiers soient servis.

### L'exception : ATHLE_COMPETITION

Cette app part par **FTPS**, via `.github/workflows/deploy-athle.yml`, déclenché par un push sur
`master` touchant `ATHLE_COMPETITION/**`. Deux conséquences :

- `dangerous-clean-slate` est **volontairement absent** : il effacerait `config/config.local.php`,
  qui n'existe que sur le serveur.
- La liste `exclude` laisse au sol `scraper/`, `data/`, `sql/dump.sql*` et la doc.
  **`sql/schema.sql` doit y monter** — l'assistant s'en sert pour créer les tables. Une exclusion
  trop large de `sql/` a déjà rendu la création des tables impossible en ligne.

### Fins de ligne

`core.autocrlf=true` sur la machine Windows : le local est en **CRLF**, le serveur Linux en
**LF**. Une comparaison brute montre donc de fausses différences (local plus gros d'environ
1 octet par ligne). **Toujours normaliser les fins de ligne avant de conclure à un vrai écart.**

---

## 5. Lire et écrire les fichiers du serveur sans FTP

Via l'API du gestionnaire de fichiers :

- URL : `https://luvumbu.com/_gestion/api.php`
- Authentification : en-tête `X-Api-Key: <clé>` (HTTPS obligatoire ; ni cookie ni CSRF en lecture)
- Clé dans `_gestion/apikey.local.php` — **la même doit être présente en ligne**, sinon 401
- Lecture : `list`, `read`, `download` · Écriture : `save`, `upload`, `mkdir`, `newfile`, `rename`, `delete`
- `tree` est cité dans l'en-tête mais **n'est pas implémenté** — parcourir dossier par dossier avec `list`

⚠️ **Pour écrire un fichier, toujours `--data-urlencode "content@C:/chemin/windows"`** : curl lit
alors le fichier lui-même. Passer le contenu par le shell (`content=$(cat …)`) le fait transiter
par argv de Git Bash, qui **convertit l'UTF-8 en CP1252** — `—` devient `0x97`, le PHP n'est plus
valide et la page sort vide. Le chemin doit être **natif Windows** : `curl.exe` ne comprend pas
`/c/xampp/…`. Contrôler après coup avec `action=download`.

---

## 6. Activer le SSO sur un serveur neuf

`sso/install.php` écrit `sso/secret.local.php` depuis le navigateur. Protégé par la session
`admin.php` — qui sait justement se rabattre sur les identifiants MySQL **tant que le SSO n'est
pas actif**.

> ⚠️ **Ne jamais dissocier le secret du premier compte.** Dès que le secret existe, `admin.php` et
> le gestionnaire cessent d'accepter leur formulaire de secours et exigent le hub. Sans compte
> dans l'annuaire, plus personne n'entre. L'assistant crée donc le compte administrateur **dans
> le même geste** : e-mail et mot de passe (8 caractères minimum) sont obligatoires.

Marche à suivre :

1. `https://luvumbu.com/admin.php` — se connecter avec les identifiants MySQL
2. `https://luvumbu.com/sso/install.php` — laisser le champ « clé » vide (elle est générée),
   renseigner e-mail + mot de passe, valider
3. `https://luvumbu.com/etat.php` — vérifier qu'il ne manque plus rien

Le champ « clé » ne se remplit à la main que pour reprendre **à l'identique** celle d'un autre
serveur (bokonzi.com) : sans clé commune, les jetons d'un hub ne sont pas reconnus par l'autre.

Le bouton Google n'apparaît que si l'ID client est renseigné, et la console Google doit autoriser
`https://luvumbu.com` comme origine JavaScript. La connexion par mot de passe, elle, fonctionne
sans rien de plus.

---

## 7. Sécurité — à traiter

| Quoi | Où | Action |
|---|---|---|
| Mot de passe MySQL de production **en clair sur GitHub public** | `DualCam/lib/db.config.php`, versionné depuis `7bc7471` | Changer le mot de passe chez Hostinger, sortir le fichier du dépôt. Le retrait seul ne suffit pas : il reste lisible dans l'historique Git. |
| `GALLERY_PASSWORD = 'luvumbu2026'` en clair | `DualCam_app/server/lib/config.php` | Sortir hors dépôt |
| Mot de passe MySQL de production en clair | `marion/admin.php` | Sortir hors dépôt et changer |

---

## 8. Pièges déjà rencontrés

**Une application déployée en double.** `DualCam_app/server/` est une copie à l'identique du
serveur déployé sous `/DualCam/`, embarquée avec les sources Android. Les deux étant en ligne, on
ouvre facilement la mauvaise : l'application Android, elle, vise `https://luvumbu.com/DualCam`
(codé en dur dans `DualCam_app/app/src/main/java/com/frontback/dualcam/net/SettingsStore.kt`,
constante `DEFAULT_URL`). La copie n'avait de surcroît pas ses identifiants de base
en ligne, donc elle ne pouvait rien afficher — ce qui se lisait comme « rien n'est envoyé au
serveur ». Ses pages `web/` renvoient désormais vers l'adresse officielle. **Son API n'est
volontairement pas redirigée** : OkHttp ne rejoue pas le corps d'un POST après une 302, cela
casserait les envois de tout appareil configuré sur cette URL.

**Un fichier nécessaire exclu du déploiement.** Voir `sql/schema.sql` au §4.

**Un assistant qui ne se verrouille pas.** Une fois l'application configurée, l'assistant doit
refuser de la rebrancher sur une autre base sans preuve d'identité — sinon n'importe quel
visiteur la détourne. Attention au cas du mot de passe vide : `hash_equals('', '')` **renvoie
vrai**, la preuve ne prouve alors rien. Dans ce cas, refuser toute reconfiguration par le web.

**L'app de transfert change de nom selon l'endroit.** Dossier local `ztransfert/` = déployé sous
`marion/` = servi à l'URL `/zt/`.

---

## 9. Ajouter une application au site — la liste de contrôle

1. Mettre les identifiants dans un fichier **hors dépôt**, et l'ajouter au `.gitignore`
2. Écrire un **`install.php`** qui teste la connexion, crée la base si possible, applique le
   schéma et écrit ce fichier
3. Faire **rediriger l'app vers son assistant** quand la base ne répond pas — jamais de message
   sans issue
4. **Verrouiller l'assistant** une fois l'app configurée (voir §8)
5. Inscrire l'app dans le tableau **`$APPS` de `etat.php`**
6. Si l'app doit être protégée : la brancher sur le SSO (`sso/README.md`) et déclarer son nom
   dans `LUVID_APPS` de `sso/accounts_admin.php`
7. Déployer, puis **ouvrir `etat.php`** pour confirmer
