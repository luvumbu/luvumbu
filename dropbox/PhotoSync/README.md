# PhotoSync — Sauvegarde de photos vers un serveur PHP (multi-comptes)

Application Android qui envoie automatiquement les **photos et vidéos** du téléphone vers ton
hébergement PHP/MySQL, avec **comptes utilisateurs** (chaque compte a ses propres médias),
**galerie web protégée**, **corbeille (30 jours)**, **lecture vidéo en streaming**, **suivi de
l'origine** (téléphone / ordinateur / web) et **envoi manuel depuis iPhone / navigateur**
(sans App Store).

```
PhotoSync/
├── public_html/   ← envoyer SON CONTENU à la racine du domaine (public_html/)
│   ├── index.php      ← accueil (redirige vers la galerie)
│   ├── install.php    ← assistant de configuration
│   ├── lib/           ← config + classes (Db, Api, Auth, Photos, bootstrap)
│   ├── api/           ← endpoints appelés par l'app (upload, feed, media, login…)
│   ├── web/           ← pages navigateur (gallery, upload_web)
│   ├── sql/           ← db.sql
│   └── uploads/       ← stockage des photos
├── android/       ← projet Android (Kotlin)
├── apk.apk        ← l'application compilée, prête à installer
└── .github/       ← compilation automatique de l'APK (optionnel, GitHub Actions)
```

- **URL du serveur (déploiement réel)** : `https://luvumbu.com/dropbox` — le contenu de
  `public_html/` est déposé dans le dossier **`dropbox/`** de l'hébergement (la racine
  `luvumbu.com` héberge un autre projet). Dans l'app : domaine `luvumbu.com`, sous-dossier `dropbox`.
- **Code d'invitation** (création de compte) = `API_TOKEN` dans `config.php` (= `123456789`).
  Doit rester identique à `INVITE_CODE` dans l'app (`SettingsStore.kt`).

---

## 🚀 Démarrage rapide (dans l'ordre)

1. **Base de données** : dans hPanel Hostinger → *Bases de données MySQL*, créer la base
   et l'utilisateur. Noter le nom de base, l'utilisateur et le mot de passe.
2. **Envoyer** le **contenu** du dossier `public_html/` (donc `index.php`, `install.php`,
   `lib/`, `api/`, `web/`, `sql/`, `uploads/`) directement dans le `public_html/` de
   l'hébergement, pour obtenir `public_html/api/`, `public_html/lib/`, etc. à la racine.
   ⚠️ N'envoie pas le *dossier* `public_html` lui-même : envoie ce qu'il y a **dedans**.
3. **Assistant d'installation** : ouvrir `https://luvumbu.com/install.php`.
   - Au 1ᵉʳ accès, un **formulaire** s'affiche (le serveur détecte qu'il n'est pas encore
     configuré) → saisir **nom de base / utilisateur / mot de passe** → *Enregistrer et vérifier*.
   - L'assistant écrit tout seul `db.config.php`, crée les tables et affiche un **rapport vert**.
   - **Supprimer `install.php`** ensuite. *(La config reste dans `db.config.php`.)*
4. **Android** : installer `PhotoSync.apk` → ouvrir l'app → indiquer le **nom de domaine**
   `luvumbu.com` (sans cocher « sous-dossier ») →
   **Créer un compte** → *Synchroniser maintenant*.
   *(Le 1ᵉʳ compte créé récupère les photos déjà présentes.)*
5. **iPhone / PC** : ouvrir `https://luvumbu.com/web/upload_web.php` dans le navigateur →
   se connecter → choisir des photos → *Envoyer*.
6. **Voir ses photos** (tout appareil) : `https://luvumbu.com/web/gallery.php`
   (ou simplement `https://luvumbu.com/`).

> 💡 Les tables sont **préfixées** (`photosync_users`, `photosync_photos`), donc PhotoSync
> peut cohabiter sans conflit avec un autre site qui partage la même base de données.
>
> En cas d'erreur, rouvrir `install.php` : il **détecte** l'état et propose à nouveau
> le formulaire (lien *« Modifier la configuration »* aussi disponible depuis le rapport).

---

## 1) Serveur (Hostinger — à la racine `public_html/`)

### Mise en place initiale
1. **Base MySQL** : dans hPanel → *Bases de données MySQL*, créer la base + l'utilisateur.
2. Envoyer le **contenu** du dossier `public_html/` (et non le dossier lui-même) par FTP /
   Gestionnaire de fichiers dans le `public_html/` de l'hébergement, pour obtenir
   `public_html/api/`, `public_html/lib/`, etc. à la racine.
3. Ouvrir `https://luvumbu.com/install.php` → l'**assistant** détecte que la
   base n'est pas configurée et affiche un **formulaire** : saisir nom de base / utilisateur /
   mot de passe (+ préfixe, par défaut `photosync_`) → *Enregistrer et vérifier*.
4. L'assistant écrit `db.config.php`, crée les tables `photosync_users` / `photosync_photos`,
   le dossier `uploads/`, et affiche un rapport. **Supprimer `install.php` ensuite.**

> Les identifiants MySQL ne sont **plus** à mettre à la main dans `config.php` : ils sont
> écrits automatiquement dans `db.config.php` par l'assistant. `config.php` ne garde que les
> réglages applicatifs (`API_TOKEN`, `GALLERY_PASSWORD`, limites) — éditables si besoin.

### Fichiers serveur (rangés par rôle, à la racine `public_html/`)

```
public_html/
├── index.php          accueil → redirige vers web/gallery.php
├── install.php        assistant : config BD + crée la base/les tables (à SUPPRIMER après)
├── favicon.svg        icône afro du site
├── .user.ini          limites PHP relevées (gros fichiers / vidéos)
├── lib/               code commun (à NE PAS appeler directement par URL)
│   ├── config.php         réglages (GALLERY_PASSWORD, limites) + lit db.config.php
│   ├── db.config.php      GÉNÉRÉ par l'assistant : identifiants MySQL (non versionné)
│   ├── bootstrap.php      charge config + les classes
│   ├── Db.php             connexion PDO partagée (singleton)
│   ├── Api.php            réponses JSON
│   ├── Auth.php           comptes : schéma (users/photos/albums), jetons, sessions
│   ├── Photos.php         corbeille, vignettes (micro/500), masquage, dates FR
│   └── Albums.php         albums partageables (création, partage, mot de passe)
├── api/               endpoints appelés par l'app Android (+ JS web)
│   ├── register.php       création de compte (code = mot de passe BDD)
│   ├── login.php          connexion (renvoie le jeton du compte)
│   ├── upload.php         réception d'une photo/vidéo
│   ├── feed.php           liste JSON des photos du compte (hors masquées)
│   ├── names.php          noms déjà présents (déduplication app) — optionnel
│   ├── check.php          empreintes SHA-256 déjà présentes — optionnel
│   ├── delete.php         mise à la corbeille depuis l'app
│   ├── media.php          sert image/miniature (propriétaire, admin, ou album partagé)
│   └── setup.php          (ancien) vérif config — plus appelé par l'app
├── web/               pages ouvertes dans le navigateur
│   ├── gallery.php        galerie : login, filtre date, corbeille, masquées, albums, ZIP
│   ├── register.php       inscription web
│   ├── admin.php          panneau admin (inscrits, photos, comptes, ZIP)
│   ├── maintenance.php    nettoyage BDD ↔ fichiers
│   ├── download.php       téléchargement ZIP (sélection / tout)
│   ├── share.php          page PUBLIQUE d'un album partagé
│   ├── upload_web.php     envoi manuel (iPhone / navigateur)
│   └── list.php           ancienne URL → redirige vers gallery.php
├── sql/db.sql         schéma SQL de référence
└── uploads/           stockage des fichiers (.thumbs/ vignettes, .corbeille/ corbeille)
```

> L'app appelle les endpoints sous `…/api/…` ; les pages web sont sous `…/web/…`.
> Seuls `index.php` et `install.php` sont au sommet de la racine.

### Sécurité
- Les photos dans `uploads/` ne sont **jamais** accessibles directement (`.htaccess` :
  `Require all denied`). Elles transitent par `media.php`, qui vérifie le compte.
- Mots de passe hachés (`password_hash`), comparaisons en temps constant.
- Chaque requête de données est filtrée par `user_id` → pas de mélange entre comptes.
- Dédoublonnage **par compte** : index unique `(user_id, sha256)`.

---

## 2) Application Android

### Utilisation
1. Au **premier lancement** → écran **Connexion / Créer un compte**. Indique simplement ton
   **nom de domaine** (ex. `luvumbu.com`) : l'app construit l'adresse du serveur automatiquement.
   - Si le serveur n'est **pas à la racine**, coche **« 📁 Le serveur est dans un sous-dossier »**
     et saisis le dossier (ex. `apk`) → l'app utilisera `https://ton-domaine/apk`.
2. *Créer un compte* (identifiant + mot de passe) ou *Se connecter*.
   - Le **premier compte créé** récupère les photos déjà envoyées avant l'ère des comptes.
3. Écran principal : option *Wi-Fi uniquement*, *Activer la synchro*, *Synchroniser
   maintenant*, *Voir mes photos en ligne*, *Se déconnecter*.

### Synchronisation
- **Automatique** en arrière-plan via WorkManager (~toutes les 15 min, selon réseau/batterie).
- Déroulé visible en temps réel :
  1. 🔍 **Vérification** — calcule l'empreinte de chaque photo et demande au serveur
     (`check.php`) lesquelles existent déjà → évite de renvoyer après une réinstallation.
  2. 📤 **Envoi** — n'envoie que les nouvelles ; indique combien étaient déjà présentes.
- Repérage des photos déjà envoyées : base locale **Room** (id MediaStore) + filet de
  sécurité serveur (hash SHA-256). La base locale est vidée à la déconnexion.

### Conseil fiabilité
Réglages → Applications → **PhotoSync** → Batterie → **Sans restriction**, pour que la
synchro en arrière-plan ne soit pas coupée.

---

## 3) iPhone / navigateur (sans App Store)

iOS n'autorise pas l'upload automatique en arrière-plan ni l'installation libre d'app.
On utilise donc le **web** :

- 👀 **Voir ses photos** : `https://luvumbu.com/web/gallery.php` (connexion compte)
- 📤 **Envoyer des photos** : `https://luvumbu.com/web/upload_web.php`
  → se connecter, choisir des photos dans la pellicule, *Envoyer*.
- 💡 Safari → *Partager → Sur l'écran d'accueil* pour une icône type application.

Ces pages fonctionnent aussi sur Android/PC (envoi manuel de secours).

---

## 4) Galerie web (`gallery.php`)

- Connexion par **compte** (identifiant + mot de passe) — on ne voit que ses photos.
- **Date** de chaque photo affichée, miniatures, design sombre.
- **Nombre de photos par page réglable** (5 / 10 / 20 / 50 / 100), mémorisé.
- **Sélection multiple** (cases à cocher + *Tout sélectionner*).
- **Corbeille** : *Mettre à la corbeille* → conservation **30 jours** → suppression
  automatique. Restauration ou suppression définitive possibles (à l'unité ou en lot).

---

## 5) Compiler l'APK (rappel)

Dépendances déjà installées sur la machine de dev :
- JDK : `C:\Program Files\Android\Android Studio\jbr` (Java 21)
- SDK : `%LOCALAPPDATA%\Android\Sdk` (plateforme `android-35`, build-tools `35.0.0`)
- Gradle 8.9 : `%USERPROFILE%\.gradle-dist\gradle-8.9`
- Versions : AGP 8.7.2, Kotlin 2.0.20, compileSdk/targetSdk 35, minSdk 26.

```bash
# depuis android/, avec JAVA_HOME et ANDROID_HOME définis
gradle assembleDebug --no-daemon
# sortie : android/app/build/outputs/apk/debug/app-debug.apk  → copié en PhotoSync.apk
```

C'est un **APK de debug** (signé clé debug, installable directement). Pour le Play Store,
il faudrait un APK *release* signé.

L'alternative cloud (`.github/workflows/android.yml`) compile l'APK sur GitHub Actions
(artifact téléchargeable) sans rien installer localement.

---

## Fonctionnalités ajoutées (récap complet)

### 📱 Application Android (v2.4)
- **Version visible** dans l'app (sous l'identifiant : `· v2.4`) pour vérifier la mise à jour.
- **Synchro automatique** (interrupteur) : envoi périodique en arrière-plan (~15 min).
- **Surveiller la galerie** (interrupteur séparé) : détection temps réel des ajouts (app
  ouverte) → alerte (toast + notification) ; envoi immédiat si la synchro auto est active.
- **Bouton « Arrêter la synchro »**.
- **Limite d'envoi par synchro** : champ « Max d'envois par synchro » (0 = illimité).
- **Envoyer tout type de fichier (photos + vidéos)** (interrupteur) : décoché = photos
  seulement (par défaut) ; coché = photos **et** vidéos. Permission `READ_MEDIA_VIDEO`.
- **📁 Choisir des fichiers à envoyer** : sélection manuelle dans le sélecteur du téléphone
  (galerie/fichiers), envoi direct au serveur (bilan « X ok · Y échec(s) »).
- **Galeries séparées** : deux boutons **« 📷 Voir les photos »** et **« 🎬 Voir les vidéos »**
  (chacun ouvre une galerie d'un seul type, sans mélange).
- **Lecture vidéo** : lecteur intégré (lecture/pause/avance) **+ bouton « Ouvrir en ligne »**
  (navigateur / autre app — VLC…) en secours.
- **Date affichée** sur chaque vignette (date de prise de vue, sinon d'envoi).
- **Origine de chaque média** : cadre + pastille de couleur — 📱 **téléphone** (bleu),
  💻 **ordinateur** (vert), 🌐 **web** (orange) ; **filtre d'affichage par origine**
  (Tout / Téléphone / Ordinateur / Web).
- **🔄 Actualiser** la galerie + **rechargement auto** au retour (voir les nouveaux éléments,
  dont ceux envoyés depuis le PC).
- **Envoi rapide** : plus de calcul d'empreinte ni de vérification serveur ; suivi local des
  photos déjà envoyées ; **envoi en streaming** (sans copie temporaire) et **6 en parallèle**.
- **Notification de fin de synchro** : « ✅ X sauvegardée(s) ».
- **📟 Moniteur temps réel** (style Matrix) : journal en direct des détections, envois,
  tentatives et erreurs.
- **État détaillé** : l'écran principal montre l'état de la synchro manuelle ET automatique,
  les nouvelles tentatives, les erreurs.
- **🧹 Photos supprimées du téléphone** : compare serveur ↔ galerie locale ; propose de
  supprimer du serveur ce qui n'est plus sur le téléphone.
- **🗑️ Suppressions sur le serveur** : alerte (notification) quand des photos envoyées ont
  été supprimées côté serveur ; propose de **les renvoyer** ou de **ne plus les signaler**.
- **Inscription** : champ « Code d'inscription » = **mot de passe de la base de données**.
- **Icône** style afro (marron/orange/noir/jaune).

### 🖥️ Serveur — galerie web (`web/gallery.php`)
- **Barre d'actions fixe** (sticky) : reste visible au défilement (Tout sélectionner,
  envoi, corbeille…).
- **Envoi direct depuis la galerie** : boutons **« 📁 Ordinateur »** et **« 📱 Application »**
  → choisir des fichiers et les envoyer sans changer de page (barre de progression).
- **Bouton « Afficher »** : filtre la galerie par **origine** (Tout / 📱 Téléphone /
  💻 Ordinateur / 🌐 Web) — filtre serveur, conservé pendant la pagination.
- **Cadre + pastille d'origine** sur chaque vignette (📱 bleu / 💻 vert / 🌐 orange).
- **Filtre par date** (calendrier : du / au) ; conservé pendant la pagination.
- **Nombre d'images par page libre** (champ 1–500, mémorisé).
- **Miniatures « micro »** (~200 px) en cache → grilles rapides, sans toucher aux originaux.
- **Album masqué** 🔒 : masquer des photos ; elles disparaissent de la galerie/app/admin et
  ne s'ouvrent que dans la vue « Masquées » protégée par mot de passe (mot de passe du compte
  **ou** mot de passe spécifique défini par l'utilisateur).
- **Albums (dossiers virtuels)** 📁 partageables par lien, **public ou protégé par mot de
  passe** ; page publique `web/share.php?a=<jeton>` sans connexion.
- **Téléchargement ZIP** : sélection, ou tout le compte (compressé puis téléchargé).
- **Corbeille** (30 j) : mise à la corbeille, restauration, suppression définitive.

### 🛠️ Serveur — admin (`web/admin.php`, accès par mot de passe BDD)
- Liste des **inscrits** ; clic sur un profil → ses photos.
- Onglet **« Toutes les photos »** (galerie globale, l'admin voit tout).
- **Suppression** de photos, **téléchargement ZIP** par profil.
- **Gérer les comptes** : supprimer un compte (+ ses photos), changer son mot de passe.
- **🔧 Maintenance** (`web/maintenance.php`) : entrées BDD sans fichier → suppression ;
  fichiers sans entrée BDD → suppression (espace perdu).

### ⚙️ Serveur — divers
- **Streaming vidéo** (`api/media.php`) : support des requêtes **HTTP Range** (réponses
  `206 Partial Content`) → les vidéos démarrent et se parcourent (avance/retour), y compris
  les MP4 de téléphone (index `moov` en fin de fichier).
- **Rangement par type** (`api/upload.php`) : photos dans `uploads/<compte>/photos/<AAAA>/<MM>/`,
  vidéos dans `uploads/<compte>/videos/<AAAA>/<MM>/`.
- **Origine enregistrée** (`api/upload.php`) : `phone` (app, en-tête `X-Auth-Token`),
  `computer` ou `web` (boutons web, champ `source`). Renvoyée par `api/feed.php`.
- **Type photo/vidéo fiable** (`api/feed.php`) : déterminé côté serveur (dossier `videos/`,
  extension, sinon vrai MIME) et renvoyé dans le champ `video`.
- **Envoi web tout-type** (`web/upload_web.php`) : accepte tout fichier + bouton explicite
  **« 📁 Choisir un fichier »** (explorateur du PC / sélecteur mobile).
- **Déconnexion fiable** (`lib/Auth.php`) : le cookie de session est explicitement effacé.
- **Inscription web** (`web/register.php`) — code = mot de passe BDD.
- **Auto-nettoyage** : toute photo dont le fichier a disparu est effacée automatiquement
  (feed app, galerie web, admin, media) → plus d'« image manquante ».
- **Installation auto** : `install.php` crée la base si elle n'existe pas (si l'hébergeur le permet).
- **Taille de fichier** : limite supprimée (`MAX_BYTES = 0`) + `.user.ini` qui relève les
  limites PHP (gros volumes / vidéos).
- **Favicon** afro (`favicon.svg`).

> **Secrets unifiés** : inscription (app + web) et admin utilisent le **mot de passe de la
> base de données** (`DB_PASS`). L'album masqué et chaque album partagé ont leur **propre**
> mot de passe (réglable). Plus de code `123456789`.

### Tables de base de données
`photosync_users` (+ `hidden_pass_hash`), `photosync_photos` (+ `user_id`, `deleted_at`,
`hidden`, **`source`** = `phone`/`computer`/`web`), `photosync_albums`,
`photosync_album_photos` — toutes créées/complétées automatiquement par l'assistant et
`Auth::ensureSchema()` (la colonne `source` est aussi auto-créée par `feed.php`/`upload.php`
si elle manque).

## Géolocalisation (à choisir — non implémenté)
Trois approches possibles, à trancher avant de coder :
1. **Lieu de chaque photo (EXIF)** — lire les coordonnées GPS de la photo, les stocker
   (`lat`/`lng`), les afficher (lien carte). Permission `ACCESS_MEDIA_LOCATION`.
2. **Position actuelle du téléphone** — capter le GPS à l'envoi. Permission `ACCESS_FINE_LOCATION`.
3. **Les deux** — géotag si présent, sinon position actuelle en repli.

## Pistes d'évolution
- Pagination « charger plus » automatique au défilement dans l'app.
- Reconnaissance d'image / tags (ML Kit on-device ou API cloud) + recherche.
- Anti-bruteforce sur le login, mot de passe oublié.
- Quota par compte, statistiques d'espace.
- Aperçu plein écran avec zoom/swipe (le lecteur vidéo intégré est fait).
- Pagination « charger plus » automatique au défilement de la galerie web.
- Sauvegarde automatique de la base, journal d'activité, HTTPS forcé.
- 503 corbeille : option « corbeille sans miniatures » si pic de ressources.
