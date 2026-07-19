# PhotoSync — Sauvegarde de photos vers un serveur PHP (multi-comptes)

Application Android qui envoie automatiquement les photos du téléphone vers ton
hébergement PHP/MySQL, avec **comptes utilisateurs** (chaque compte a ses propres
photos), **galerie web protégée**, **corbeille (30 jours)** et **envoi manuel depuis
iPhone / navigateur** (sans App Store).

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

- **URL du serveur** : `https://luvumbu.com` (racine ; dans l'app : domaine `luvumbu.com`,
  sans cocher « sous-dossier »)
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
├── install.php        assistant : détecte l'état, formulaire de config, crée les tables
├── lib/               code commun (à NE PAS appeler directement par URL)
│   ├── config.php         réglages app (API_TOKEN, GALLERY_PASSWORD, limites) + lit db.config.php
│   ├── db.config.php      GÉNÉRÉ par l'assistant : identifiants MySQL (non versionné)
│   ├── bootstrap.php      charge config + les 4 classes (inclus par tous les endpoints)
│   ├── Db.php             connexion PDO partagée (singleton)
│   ├── Api.php            réponses JSON : Api::json(), Api::fail(), Api::header()
│   ├── Auth.php           comptes : schéma, jetons, identification, connexion web
│   └── Photos.php         corbeille, vignettes, dates FR
├── api/               endpoints appelés par l'app Android (+ JS de la page d'envoi)
│   ├── register.php       création de compte (protégée par le code d'invitation)
│   ├── login.php          connexion (renvoie le jeton du compte)
│   ├── upload.php         réception d'une photo (jeton OU session web)
│   ├── check.php          quelles empreintes SHA-256 existent déjà
│   ├── feed.php           liste JSON des photos du compte
│   ├── media.php          sert une image/miniature au propriétaire
│   └── setup.php          vérification de config au 1er lancement de l'app
├── web/               pages ouvertes dans le navigateur
│   ├── gallery.php        galerie : login, corbeille, multi-sélection, nb/page
│   ├── upload_web.php     envoi manuel (iPhone / navigateur)
│   └── list.php           ancienne URL → redirige vers gallery.php
├── sql/db.sql         schéma SQL de référence
└── uploads/           stockage des fichiers (accès HTTP direct interdit par .htaccess)
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

## Fonctionnalités ajoutées (récap)

### Application Android
- **Synchro automatique** (interrupteur) : envoi périodique en arrière-plan (~15 min).
- **Surveiller la galerie** (interrupteur séparé) : détection temps réel des ajouts (app
  ouverte) → alerte (toast + notification), envoi immédiat si la synchro auto est active.
- **Bouton « Arrêter la synchro »**.
- **Limite d'envoi par synchro** : champ « Max d'envois par synchro » (0 = illimité).
- **Notification de fin de synchro** : « ✅ X sauvegardée(s) ».
- **Photos ET vidéos** sauvegardées (permission `READ_MEDIA_VIDEO`).
- **« Photos supprimées du téléphone »** : compare serveur ↔ galerie locale, propose de
  supprimer du serveur ce qui n'est plus sur le téléphone.
- **Inscription** : champ « Code d'inscription » = mot de passe de la base de données.
- **Icône** style afro (marron/orange/noir/jaune).

### Serveur (web/admin)
- **Inscription web** (`web/register.php`) — code = mot de passe BDD.
- **Panneau admin** (`web/admin.php`) — accès par le mot de passe BDD :
  - liste des inscrits ; clic sur un profil → ses photos ;
  - onglet « Toutes les photos » (galerie globale, l'admin voit tout) ;
  - suppression de photos (corbeille) ; **supprimer un compte** ; **changer son mot de passe**.
- **Maintenance** (`web/maintenance.php`) :
  - entrées en base sans fichier sur le disque → suppression ;
  - fichiers sur le disque sans entrée en base → suppression (espace perdu).
- **Auto-nettoyage** : toute photo dont le fichier a disparu est effacée automatiquement
  (feed app, galerie web, admin, media) → plus d'« image manquante ».
- **Taille de fichier** : limite supprimée (`MAX_BYTES = 0`) + `.user.ini` qui relève les
  limites PHP de l'hébergeur (gros volumes / vidéos).
- **Favicon** afro (`favicon.svg`).

> Secrets unifiés : inscription (app + web) et admin utilisent tous le **mot de passe de
> la base de données** (`DB_PASS`). Plus de code `123456789`.

## Géolocalisation (à choisir — non implémenté)
Trois approches possibles, à trancher avant de coder :
1. **Lieu de chaque photo (EXIF)** — lire les coordonnées GPS déjà contenues dans la photo,
   les stocker côté serveur (colonnes `lat`/`lng`), les afficher (coordonnées + lien carte).
   Aucune localisation en direct. Permission Android `ACCESS_MEDIA_LOCATION`.
2. **Position actuelle du téléphone** — capter la position GPS à l'envoi (même sans géotag).
   Permission `ACCESS_FINE_LOCATION`, consentement utilisateur.
3. **Les deux** — géotag de la photo si présent, sinon position actuelle en repli.

## Pistes d'évolution
- Pagination « charger plus » automatique au défilement dans l'app.
- Rotation du jeton de compte / mot de passe oublié ; anti-bruteforce sur le login.
- Choix des albums/dossiers à sauvegarder, recherche par date.
- Quota par compte, statistiques d'espace, export ZIP d'un compte.
- Lecteur vidéo intégré, aperçu plein écran avec zoom/swipe.
- Sauvegarde automatique de la base, journal d'activité, HTTPS forcé.
