# PhotoSync — Documentation d'utilisation

> Guide pas-à-pas pour **se servir** de PhotoSync au quotidien (Android, iPhone, navigateur).
> Pour l'installation serveur et le fonctionnement interne, voir **`DOCUMENTATION_TECHNIQUE.md`**.

PhotoSync sauvegarde les **photos, vidéos et musiques** de votre téléphone vers votre propre
serveur (`https://luvumbu.com`). Chaque personne a son **compte** et ne voit **que ses
propres fichiers**. On peut aussi envoyer/consulter (et y ajouter des **documents**) depuis un
**navigateur** (iPhone, PC).

---

## 1. Vue d'ensemble — qui fait quoi

| Vous voulez… | Sur Android | Sur iPhone / PC (navigateur) |
|---|---|---|
| Sauvegarder automatiquement | L'application PhotoSync | *(pas d'auto sur iPhone — voir envoi manuel)* |
| Envoyer manuellement | L'app (« Synchroniser maintenant ») | `…/web/upload_web.php` ou la galerie web |
| Voir / lire ses fichiers | Bouton « Voir mes photos » | `…/web/gallery.php` |
| Gérer / supprimer | Galerie de l'app | La galerie web (corbeille) |

> **Adresse du serveur** : `luvumbu.com`.

---

## 2. Première utilisation sur Android

### 2.1 Installer l'application
1. Copiez le fichier **`app-debug.apk`** (ou `PhotoSync.apk`) sur le téléphone.
2. Ouvrez-le. Android demande d'**autoriser l'installation depuis cette source** → acceptez.
3. Installez, puis ouvrez **PhotoSync**.

> C'est une application installée « hors store ». L'avertissement d'Android au premier
> lancement est normal.

### 2.2 Se connecter
1. À l'ouverture, l'écran de **connexion** s'affiche.
2. Indiquez votre **nom de domaine** : `luvumbu.com`
   *(s'il est dans un sous-dossier, cochez l'option et indiquez-le).*
3. Touchez **« Se connecter avec Google »** et choisissez votre compte Google.
   Votre compte PhotoSync est **créé automatiquement** la première fois.

### 2.3 Autoriser l'accès aux fichiers
À la première synchro, l'app demande l'accès à vos **photos / vidéos** (et **audio** si vous
l'activez). Acceptez : sans autorisation, rien ne peut être sauvegardé.

---

## 3. Comprendre l'écran d'accueil (aspect & boutons)

L'interface est **épurée** : fond sombre, sections regroupées dans des **cartes** arrondies,
un seul accent de couleur (orange) pour les éléments importants.

### Section « Synchronisation » (interrupteurs)
- **Wi-Fi uniquement** — n'envoie que connecté en Wi-Fi (économise les données mobiles).
- **Synchro automatique** — sauvegarde en arrière-plan ~toutes les **15 min**, app fermée.
- **Surveiller la galerie** — app ouverte, détecte les nouveaux fichiers en temps réel.

> Un interrupteur **à droite allumé (orange)** = activé ; éteint (gris) = désactivé.

### Section « Types de fichiers » (cases à cocher) — *nouveau*
Choisissez **ce qui est envoyé** :
- ☑ **📷 Photos**  ·  ☑ **🎬 Vidéos**  ·  ☐ **🎵 Musique / audio**

- Pour **n'envoyer que les photos** → cochez **Photos** seule.
- Pour **tout envoyer** → cochez les trois.
- Pour **ne pas envoyer un type** → **décochez-le** (ex. décocher Vidéos).

Les choix sont enregistrés aussitôt. L'app ne demande que les autorisations des types cochés.

### Section « Options »
- **Max d'envois par synchro** — limite le nombre de fichiers par passage (`0` = illimité).

### Les boutons
- **Enregistrer et activer la synchro** (bouton plein, accentué) — enregistre les réglages et
  lance la synchro.
- **Synchroniser maintenant** (bouton tonal) — envoi immédiat.
- **Arrêter la synchro** (bouton contour) — stoppe l'envoi en cours **et** la synchro auto.
- **Voir mes photos en ligne** (bouton tonal) — ouvre votre galerie.
- **Photos supprimées du téléphone** (bouton contour) — outil de nettoyage (voir §6).
- **Se déconnecter** (bouton texte, discret, en bas).

### Section « Activité en temps réel »
Affiche l'**état courant** (préparation, vérification, envoi `X / total`, bilan) et le
**total déjà sauvegardé**.

---

## 4. Sauvegarder ses fichiers (Android)

### Ce qui se passe pendant une synchro
1. 🔍 **Vérification** — l'app demande au serveur ce qui est **déjà** sauvegardé, pour
   **ne pas renvoyer** ce qui existe déjà.
2. 📤 **Envoi** — seuls les nouveaux fichiers (des types cochés) partent ; la progression
   `X / total` s'affiche en direct.
3. ✅ **Bilan** — une notification indique « X sauvegardée(s) ».

> Après une réinstallation du téléphone, PhotoSync **ne renvoie pas** tout : le serveur
> reconnaît les fichiers déjà présents (par empreinte) et les ignore.

### Conseil fiabilité (important)
Pour que la sauvegarde en arrière-plan ne soit pas coupée par Android :
**Réglages → Applications → PhotoSync → Batterie → Sans restriction**.

---

## 5. La galerie de l'app

Touchez **« Voir mes photos en ligne »**. La galerie affiche une **grille de vignettes
arrondies** :

- **Onglets en haut** pour filtrer par type : **Tout · Photos · Vidéos · Musique · Documents · Autres**.
- Bouton **« ⇅ Trier »** : **Date** (récent/ancien), **Nom** (A→Z / Z→A), **Taille**
  (gros/petit), **Type de fichier**.
- **Aperçu léger** : les photos affichent une **miniature réduite** (chargement rapide) ;
  les autres types affichent une **icône** + le **nom** (vidéo 🎬, musique 🎵, document 📄…).
  Un petit **logo appareil photo** marque les photos.
- **Au toucher** : une photo s'ouvre en plein écran ; une vidéo/musique/document s'ouvre
  dans le **lecteur de votre téléphone**.

---

## 6. Sur iPhone et navigateur (sans App Store)

iOS n'autorise pas l'envoi automatique en arrière-plan. On passe par le **web** :

### Envoyer des fichiers
1. Ouvrez **`https://luvumbu.com/web/gallery.php`** (ou `upload_web.php`) et connectez-vous
   (compte Google).
2. Bouton **📁 Ordinateur** → choisir des fichiers (photos, vidéos, **documents**, musique…) ;
   ou **📱 Application**. L'envoi se fait directement.

> 💡 iPhone : Safari → **Partager → Sur l'écran d'accueil** crée une icône type application.

### Voir et **lire** ses fichiers en ligne
Sur **`gallery.php`** vous pouvez :
- **Filtrer par Type** (Photos / Vidéos / Musique / Documents / Autres) et par **Origine**
  (📱 téléphone / 💻 ordinateur / 🌐 web), et **Trier** (date, nom, taille, type).
- Régler le **nombre par page** (5 → 100), mémorisé.
- **Lire en ligne** : cliquer une **vidéo** (pastille ▶) ou une **musique** ouvre un
  **lecteur intégré** dans le navigateur (lecture directe, sans téléchargement) ; les
  **documents** se téléchargent.
- **Sélectionner plusieurs** fichiers (cases + « Tout sélectionner »).
- **Mettre à la corbeille** : conservé **30 jours**, puis supprimé. Possibilité de
  **restaurer** ou **supprimer définitivement** avant.

> Chaque carte indique le **type** (🖼️/🎬/🎵/📄) et la **taille** du fichier.

---

## 7. Nettoyer / réconcilier (Android)

L'écran **« Photos supprimées du téléphone »** compare le serveur et votre galerie locale,
et propose de **mettre à la corbeille du serveur** ce qui n'est **plus** sur le téléphone.

---

## 8. Questions fréquentes

**Comment se connecter ?**
Avec votre **compte Google** (bouton « Se connecter avec Google »). Le compte PhotoSync est
créé automatiquement au premier accès.

**Puis-je n'envoyer que les photos ?**
Oui : dans **Types de fichiers**, cochez **Photos** uniquement. Pour exclure un type,
décochez-le.

**Puis-je sauvegarder la musique ? les documents ?**
La **musique** se synchronise depuis l'app (cochez 🎵). Les **documents** s'ajoutent depuis
le **web** (bouton 📁 Ordinateur) — Android ne permet pas à l'app d'y accéder automatiquement.

**Je n'arrive pas à lire les vidéos en ligne.**
Cliquez la vidéo dans la galerie web : elle s'ouvre dans le **lecteur intégré** (`view.php`).
Si rien ne se lance, votre navigateur peut être ancien — utilisez le bouton **Télécharger**.

**Mes fichiers ne partent pas en arrière-plan.**
Vérifiez : synchro **activée**, **accès aux fichiers** accordé, au moins **un type coché**,
batterie **sans restriction**, et si « Wi-Fi uniquement » est coché, être en Wi-Fi.

**J'ai réinstallé le téléphone : tout va-t-il être renvoyé ?**
Non. Le serveur reconnaît ce qu'il a déjà et n'accepte pas les doublons.

**« URL introuvable (404) ».**
Domaine/sous-dossier mal saisi. Vérifiez `luvumbu.com` (ou cochez « sous-dossier »).

**« Jeton invalide / reconnecte-toi ».**
La session a expiré. Déconnectez-vous puis reconnectez-vous.

---

## 9. Récapitulatif des adresses

| Usage | Adresse |
|---|---|
| Accueil / galerie | `https://luvumbu.com/` |
| Galerie web (filtres, tri, lecture) | `https://luvumbu.com/web/gallery.php` |
| Lecteur d'un fichier | `https://luvumbu.com/web/view.php?id=…` |
| Envoi manuel (iPhone/PC) | `https://luvumbu.com/web/upload_web.php` |
| Administration | `https://luvumbu.com/web/admin.php` |
