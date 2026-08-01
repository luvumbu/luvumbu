# 📝 Modifications PhotoSync — récapitulatif complet

> Migration : **connexion par mot de passe → « Se connecter avec Google »**, déploiement
> dans le sous-dossier **`/dropbox/`**, **rôle administrateur par e-mail**, et **nouveau design**
> repris du projet `cv_luvumbu`.

---

## 1. Déploiement dans le sous-dossier `/dropbox/`

- **Site web** : aucune modification nécessaire — toutes les URLs sont **relatives**
  (`web/gallery.php`, `../api/media.php`, etc.), donc fonctionnent dans n'importe quel sous-dossier.
- **Application Android** (`SettingsStore.kt`) : valeurs par défaut mises à jour
  - `DEFAULT_SUBPATH = "dropbox"`
  - `DEFAULT_URL = "https://luvumbu.com/dropbox"`

En ligne, le contenu de `public_html/` est déposé **directement** dans `dropbox/`
(donc `luvumbu.com/dropbox/`). En local : `localhost/dropbox/public_html/`.

---

## 2. Connexion « Se connecter avec Google » (remplace mot de passe + code d'inscription)

Principe : on **garde le jeton interne** (`api_token`, invisible pour l'utilisateur) et on
change seulement **comment on l'obtient** → via Google. Tout le reste de l'API/app est inchangé.

### Serveur
| Fichier | Modification |
|---|---|
| `lib/config.php` | Ajout de la constante `GOOGLE_CLIENT_ID` |
| `lib/Auth.php` | `verifyGoogleIdToken()`, `httpsGet()`, `loginWithGoogle()`, `uniqueUsername()`, `tokenForUser()`, `googleButtonHtml()` ; `webSession()` passe par Google ; schéma : colonnes `google_sub`, `email`, `pass_hash` rendu facultatif |
| `api/google_login.php` | **Nouveau** point d'entrée pour l'app (vérifie le jeton Google → renvoie le jeton interne) |
| `web/gallery.php` | Page de connexion = bouton Google |
| `web/upload_web.php` | Page de connexion = bouton Google |
| `web/register.php` | Devenu inutile (compte créé automatiquement) → redirige vers `gallery.php` |

### Application Android
| Fichier | Modification |
|---|---|
| `app/build.gradle.kts` | Dépendances Credential Manager + Google ID |
| `res/values/strings.xml` | `google_web_client_id` (= identifiant Web) |
| `res/layout/activity_auth.xml` | Champs identifiant/mot de passe/code supprimés → bouton Google |
| `AuthActivity.kt` | Connexion via Credential Manager (jeton d'identité Google) |
| `ApiClient.kt` | `loginWithGoogle()` → appelle `api/google_login.php` |

---

## 3. Redirection automatique si la base n'est pas configurée

Au lieu d'afficher une erreur SQL brute, le site envoie vers l'assistant `install.php`.

| Fichier | Modification |
|---|---|
| `lib/Db.php` | Ajout de `isReady()` (base configurée ET joignable ?) |
| `index.php` | Base prête → `web/gallery.php` ; sinon → `install.php` |
| `lib/Auth.php` (`webSession`) | Redirige vers `../install.php` si base pas prête |
| `web/admin.php` | Idem |

---

## 4. Rôle administrateur (par e-mail)

| Fichier | Modification |
|---|---|
| `lib/config.php` | Constante `ADMIN_EMAILS` (e-mails admin, séparés par virgules) |
| `lib/Auth.php` | Colonne `is_admin` ; `isAdmin()`, `isAdminEmail()` ; 1er compte = admin ; promotion auto du plus ancien s'il n'y a aucun admin ; synchro admin selon l'e-mail à chaque connexion |
| `web/admin.php` | Accès = compte Google admin **OU** clé maître (mot de passe BDD) ; action `set_admin` (donner/retirer admin) ; suppression de l'ancien « changer mot de passe » ; colonne **Rôle** + bouton ⭐ ; affichage de l'e-mail de chaque compte ; garde-fou : impossible de retirer le **dernier** admin |
| `web/gallery.php` | Lien **🛠️ Admin** affiché **uniquement** aux admins |

**Règle clé** : tout compte dont l'e-mail Google est dans `ADMIN_EMAILS` devient/reste admin
automatiquement à chaque connexion.

---

## 5. Nouveau design (repris de `cv_luvumbu`)

Thème sombre : variables CSS (`--accent` bleu `#4f8cff`, cyan, violet), fonds en **dégradés
radiaux**, **topbar en verre dépoli**, panneaux/cartes en dégradé, boutons dégradés bleu→violet,
badges, cartes de stats avec halo lumineux.

| Fichier | Modification |
|---|---|
| `web/gallery.php` | Page de connexion + galerie au thème `cv_luvumbu` |
| `web/admin.php` | Connexion admin + panneau au thème `cv_luvumbu` |

*(Pages non encore harmonisées : `web/upload_web.php`, `web/maintenance.php`.)*

---

## 🔑 Valeurs de configuration

| Élément | Valeur |
|---|---|
| **ID client Web** (dans le code, serveur + app) | `878381681024-d7hb2ih3f92jkrlhp4agvb9brpdqv61l.apps.googleusercontent.com` |
| **ID client Android** (PAS dans le code — existe juste sur Google Cloud) | `878381681024-nli9fa0r3klosjs8penbsvmtkf1v049g.apps.googleusercontent.com` |
| **Nom du package** | `com.example.photosync` |
| **Empreinte SHA-1** (clé debug) | `DB:FE:55:A1:AD:1A:7B:E4:B0:DB:4B:11:A9:7A:19:6E:89:3F:1B:07` |
| **E-mail(s) admin** (`ADMIN_EMAILS`) | `luvumbu.n@gmail.com` |

---

## 📦 Fichier APK

Construit avec la connexion Google :
```
C:\xampp\htdocs\dropbox\PhotoSync-Google.apk
```
(Source du build : dossier `android/`. La copie `PhotoSync/android/` n'est plus à jour.)

---

## ⚙️ Configuration Google Cloud Console (rappel)

1. **Écran de consentement OAuth** (Externe) + l'e-mail admin ajouté en « Utilisateur test ».
2. **ID client OAuth « Application Web »** → origines JavaScript :
   `https://luvumbu.com` et `http://localhost`. URI de redirection : *(vide)*.
3. **ID client OAuth « Android »** → package `com.example.photosync` + SHA-1 ci-dessus.

> ℹ️ L'ID Android ne se met **nulle part dans le code** : sa seule présence dans le projet
> (même projet que le client Web) suffit à autoriser l'app. Le code utilise l'ID **Web**.

---

## 📤 Fichiers serveur à téléverser dans `/dropbox/` (Hostinger)

```
index.php
lib/config.php
lib/Auth.php
lib/Db.php
api/google_login.php   (nouveau)
web/gallery.php
web/upload_web.php
web/admin.php
web/register.php
```

⚠️ **NE PAS écraser / NE PAS supprimer** sur le serveur :
- `lib/db.config.php` (identifiants de la base, créés par `install.php`)
- `uploads/` (les photos)

🔐 **Après installation** : supprimer `install.php` du serveur (non protégé par mot de passe).

---

## 📲 6. PhotoSync installable sur l'écran d'accueil (PWA) — iPhone compris

Objectif : une **icône d'application** sur iPhone, Android et ordinateur, **sans passer
par l'App Store ni le Play Store**. Rien à télécharger : c'est le site lui-même qui
s'installe depuis le navigateur.

### Fichiers ajoutés

| Fichier | Rôle |
|---|---|
| `manifest.php` | Nom, icônes, couleurs, `display: standalone`, raccourcis (Envoyer / Albums / Corbeille). |
| `sw.js` | Service worker **minimal** (voir ci-dessous). |
| `offline.html` | Page « Pas de connexion » affichée hors réseau. |
| `lib/Pwa.php` | `Pwa::head($base)` : toutes les balises `<head>` + enregistrement du service worker. |
| `web/appli.php` | Mode d'emploi d'installation, détecte l'appareil, bouton *Installer* sur Chrome. |
| `assets/icon-*.png`, `assets/apple-touch-icon.png` | Icônes 192/512 (dont *maskable*), 180 pour iOS, 1024 de réserve. |

### Points d'attention

- **Le service worker ne met en cache que la coquille** (icônes, manifeste, page hors
  connexion). Ni les pages HTML ni `api/` ne sont mis en cache : la galerie dépend du
  compte connecté, un cache ferait réapparaître les fichiers d'un compte après
  déconnexion. Les photos non plus, pour ne pas remplir le stockage du téléphone.
- **Chemins relatifs partout** (`Pwa::head('..')` depuis `web/`, `'.'` depuis la racine) :
  fonctionne à l'identique en local et sous `/dropbox/`.
- **Aucune modification de configuration serveur.** Le manifeste est un **script PHP**
  (`manifest.php`) qui pose lui-même son en-tête `application/manifest+json` : un fichier
  `.webmanifest` statique serait servi avec un mauvais type MIME par Apache (extension
  inconnue) et Chrome refuserait l'installation. Ni `.htaccess` ni réglage d'hébergement
  à toucher.
- **HTTPS obligatoire** pour l'installation (ou `localhost` en développement).
- **iOS** : Safari n'utilise pas encore le manifeste pour « Sur l'écran d'accueil », d'où
  les balises `apple-touch-icon` / `apple-mobile-web-app-*`. L'app installée a **sa propre
  session** (cookies séparés de Safari) → une reconnexion Google à l'intérieur de l'app.
  Les liens `target="_blank"` sont neutralisés en mode installé, sinon iOS renvoie vers
  Safari et perd la session.

### Fichiers modifiés

`lib/bootstrap.php` (charge `Pwa.php`), `install.php`, `web/gallery.php` (+ lien **📲 Appli**),
`web/albums.php`, `web/upload_web.php`, `web/view.php`, `web/admin.php`,
`web/maintenance.php`, `web/share.php` — la ligne `<link rel="icon">` y est remplacée par
`<?= Pwa::head('..') ?>`.

---

## ✅ État final

- Site en ligne `/dropbox/` + base de données : **OK**
- Connexion Google (site + app) : **OK**
- Rôle admin par e-mail : **OK**
- Design `cv_luvumbu` : **OK** (galerie + admin)
- APK Google : **construit**
