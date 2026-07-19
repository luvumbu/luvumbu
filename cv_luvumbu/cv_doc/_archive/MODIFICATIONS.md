# Modifications — CV Luvumbu

Récapitulatif complet des fonctionnalités ajoutées au système de connexion,
avec la liste des fichiers à uploader sur Hostinger.

---

## 1. Connexion par les identifiants de la base — « solution ultime »

**But :** pouvoir toujours se connecter en tant qu'administrateur avec
l'**identifiant + mot de passe de la base de données**, même si le mot de passe
enregistré dans l'application est désynchronisé.

**Fonctionnement :** sur la page de connexion, si la vérification normale échoue,
l'application tente d'ouvrir une **vraie connexion MySQL** avec l'identifiant et
le mot de passe saisis. Si MySQL accepte, la personne détient les identifiants de
la base → elle est connectée, et le compte est resynchronisé automatiquement.

**Pour s'en servir :**
- Identifiant : l'utilisateur de la base (ex. `u489596434_luvumbu`)
- Mot de passe : le mot de passe **de la base de données** (hPanel → *Bases de
  données MySQL*), **pas** le mot de passe du compte hPanel.
- Oublié ? Réinitialise-le dans hPanel, puis connecte-toi avec le nouveau : la
  resynchronisation est automatique.

**Fichiers :** `includes/account.php` (fonction `db_login_fallback`), `login.php`.

---

## 2. Mot de passe oublié (par e-mail)

**But :** recevoir un lien de réinitialisation par e-mail.

**Fonctionnement :**
- `forgot_password.php` : saisie de l'identifiant ou de l'e-mail → un lien
  **valable 1 heure, à usage unique** est envoyé par e-mail. Message toujours
  générique (ne révèle pas si un compte existe).
- `reset_password.php?token=…` : valide le lien et permet de choisir un nouveau
  mot de passe.
- Lien **« Mot de passe oublié ? »** ajouté sur la page de connexion.

**Pré-requis :** renseigner l'**adresse e-mail** du compte dans
*Paramètres → Mon compte* (sinon aucun e-mail ne peut être envoyé).

**Fichiers :** `includes/password_reset.php`, `forgot_password.php`,
`reset_password.php`, table `password_resets` (créée automatiquement).

---

## 3. Connexion avec un e-mail

**But :** se connecter avec son adresse e-mail au lieu de l'identifiant.

**Fonctionnement :** dans *Paramètres → Mon compte*, on enregistre une adresse
e-mail. Ensuite, la page de connexion accepte **l'identifiant OU l'e-mail** + le
mot de passe. Ce même e-mail sert aussi au mot de passe oublié et à la connexion
Google.

**Fichiers :** `includes/account.php` (`update_account_email`,
`find_user_by_login`), `parametres.php`.

---

## 4. Connexion avec Google

**But :** se connecter en un clic avec un compte Google.

**Fonctionnement :**
- Bouton **« Se connecter avec Google »** toujours visible sur la page de
  connexion.
- Les identifiants Google (ID client + secret) sont **déjà intégrés dans le
  code** : la connexion fonctionne « clés en main », sans configuration.
- **Liste blanche** : seules les adresses Google figurant dans la liste
  « Comptes Google autorisés » peuvent se connecter (sécurité anti-usurpation).
- L'admin **ajoute / retire** ces adresses à tout moment dans Paramètres.

**Console Google (déjà configurée une fois) :**
- Origines JavaScript : `https://luvumbu.com` et `http://localhost`
- URI de redirection :
  `https://luvumbu.com/cv_luvumbu/google_callback.php` et
  `http://localhost/cv_luvumbu/google_callback.php`

**Fichiers :** `includes/google_auth.php`, `includes/settings.php`,
`google_login.php`, `google_callback.php`, `login.php`, `parametres.php`,
table `settings` (créée automatiquement).

---

## 5. Gestion des comptes Google autorisés (ajouter / retirer)

**But :** contrôler précisément qui peut se connecter via Google.

**Où :** *Paramètres → Connexion avec Google → encadré « Comptes Google
autorisés »*.
- Chaque adresse a un bouton **« Retirer »** (avec confirmation).
- Un champ + bouton **« Ajouter »** pour autoriser une nouvelle adresse.
- Insensible à la casse, refuse les doublons et les adresses invalides.
- Modifiable à tout moment, effet immédiat.

**Comportement à la connexion :**
```
Clic « Se connecter avec Google » → choix du compte Google
   ├─ adresse dans la liste autorisée → connecté
   └─ adresse absente                 → refusé
```

**Fichiers :** `includes/google_auth.php`
(`google_allowed_emails`, `is_google_email_allowed`, `google_add_allowed_email`,
`google_remove_allowed_email`), `parametres.php`, `google_callback.php`.

---

## 6. Éditeur de CV WYSIWYG (l'aperçu = le rendu final)

**But :** créer et mettre en page ses CV directement dans l'application, avec un
**aperçu en direct strictement identique** à ce qui sera imprimé / exporté en PDF.
Moteur repris de l'ancien projet `cv_enligne` (désormais **supprimé**, plus aucune
dépendance) et relié à la base + à l'authentification.

**Fonctionnement :**
- Chaque CV possède un **profil riche** stocké en base (colonne `cvs.profile_json`,
  ajoutée automatiquement par migration au premier accès).
- *Mes CV* → **✏️ Éditer** ouvre l'éditeur visuel (`cv_builder.php?id=…`) :
  modèles, couleurs, photo, échelle de police, sections réordonnables par
  glisser-déposer, et un aperçu live à droite.
- **💾 Enregistrer** envoie le profil JSON à l'API (`api/cv_profile.php`,
  session + CSRF + vérification du propriétaire). Le nom complet du CV est
  resynchronisé à partir du prénom/nom.
- *Mes CV* → **👁️ Aperçu** (`cv_view.php?id=…`) affiche le CV exactement comme
  l'éditeur, avec **🖨 Imprimer / Enregistrer en PDF** (impression navigateur).

**Fichiers :**
- Nouveaux : `assets/js/cv-builder.js` (moteur de rendu + éditeur),
  `cv_builder.php` (page éditeur), `api/cv_profile.php` (lecture/écriture du profil).
- Modifiés : `includes/cv.php` (colonne `profile_json` + helpers
  `get_cv_profile`, `save_cv_profile`, `seed_profile_from_cv`),
  `cv_view.php` (rendu fidèle via le moteur partagé), `mes_cv.php` (boutons
  Éditer / Aperçu).

---

## 7. Maintien du CV sur une seule page (garanti)

**But :** garantir que le CV **ne dépasse jamais une page A4**.

**Fonctionnement :** case **« 📐 Tout tenir sur une seule page »** dans l'éditeur.
À l'affichage et à l'impression, un algorithme mesure la hauteur réelle puis
**compresse itérativement** la mise en page (`zoom`) jusqu'à ce que tout tienne —
**sans plancher bloquant** (l'ancienne limite à 60 % pouvait encore déborder).
Si le contenu tient déjà, aucune réduction n'est appliquée.

**Fichier :** `assets/js/cv-builder.js` (fonction `buildCvHtml`, bloc `singlePage`).

---

## 8. Modèle « Tableautier (DC2Scale) » + champs étendus

**But :** un modèle de CV fidèle au CV PDF d'origine
(`CV-Ndenga-Luvumbu-Electricien-Tableautier-DC2Scale`) : colonne bleu nuit + or,
photo, coordonnées, habilitations, compétences, logiciels, profil, expérience,
formation.

**Fonctionnement :**
- Nouveau modèle **« Tableautier (DC2Scale) »** dans le sélecteur de modèles.
- Profil étendu : `headline` (titre/poste), `summary` (résumé), `contact`
  (ville / téléphone / e-mail / permis), et **3 nouvelles sections** éditables —
  *Habilitations*, *Compétences*, *Logiciels* — placées en colonne latérale.
- L'éditeur reçoit les champs correspondants (Titre, Coordonnées, Profil/résumé) ;
  les nouvelles sections se modifient comme les autres (ajout/suppression/ordre).

**Fichiers :** `assets/js/cv-builder.js` (template `dc2scale`, `SECTION_DEFS`,
`SIDEBAR_TYPES`, modèle de profil), `cv_builder.php` (champs d'édition).

---

## 9. Matrice de santé en temps réel (architecture.php)

**But :** voir l'**état vivant de l'application** sous forme de matrice — configuration,
connexion à la base, tables (présence + nombre de lignes), modules métier et endpoints
API — chacun avec une pastille **🟢 OK / 🟡 avertissement / 🔴 erreur**.

**Fonctionnement :**
- **Affichage à la demande uniquement** : un bouton **« 🩺 Matrice de santé — Afficher »**
  sur `architecture.php`. Rien ne tourne tant qu'on ne l'ouvre pas.
- À l'ouverture, la matrice se remplit puis **se rafraîchit en temps réel** (toutes les
  4 s) en interrogeant `api/health.php`. « Masquer » arrête immédiatement le polling.
- Le polling se **suspend** quand l'onglet n'est plus visible et reprend au retour.
- **Emplacement défini d'avance** : la matrice occupe un bloc réservé juste sous les
  statistiques (hauteur minimale fixée) → aucun saut de mise en page à l'ouverture.

**Sécurité :** `api/health.php` est réservé aux utilisateurs **connectés** (session) ;
il répond `401` en JSON sinon (pas de redirection).

**Fichiers :**
- Nouveau : `api/health.php` (état JSON de l'application).
- Modifié : `architecture.php` (bouton, bloc matrice réservé, script de polling).

---

## 10. Élément « image » séparé de la photo de profil (éditeur de CV)

**But :** distinguer clairement **deux éléments d'image** dans l'éditeur :
- la **photo de profil** (l'avatar de l'en-tête) — bouton existant « 📷 Choisir une photo » ;
- une **image libre** posée sur la page — nouveau bouton « 🖼️ Ajouter une image ».

**Fonctionnement :**
- Le bouton **« 🖼️ Ajouter une image »** ouvre le sélecteur de fichier, redimensionne
  l'image puis la pose comme **bloc libre** sur le canvas (bascule automatiquement en
  *Disposition libre* si besoin).
- L'image se **déplace** (✥), se **redimensionne** (↔ largeur, ↕ hauteur, ⤡ les deux) et
  se **supprime** (✕) comme un bloc de texte — mais sans édition de texte ni alignement.
- Les images sont stockées dans `profile.canvasBlocks` avec `type:'image'` (et un
  `src` en dataURL) ; elles sont enregistrées/exportées avec le profil.

**Fichiers :**
- `cv_builder.php` : bouton « 🖼️ Ajouter une image » + input fichier.
- `assets/js/cv-builder.js` : rendu du bloc image (canvas), ajout via `processPhoto`,
  redimensionnement à hauteur fixe, garde-fou (pas d'édition texte sur une image).

---

## Organisation de la page Paramètres

```
1. Connexion avec Google
   ├─ Statut (Activée / Désactivée)
   ├─ Comptes Google autorisés  → liste + Ajouter / Retirer
   └─ Configuration avancée (ID client / secret)   (replié)
2. Mon compte administrateur
   ├─ Adresse e-mail
   └─ Changer le mot de passe
3. Clés API
```

---

## Fichiers à uploader sur Hostinger

**Nouveaux fichiers**
- `includes/settings.php`
- `includes/password_reset.php`
- `includes/google_auth.php`
- `forgot_password.php`
- `reset_password.php`
- `google_login.php`
- `google_callback.php`
- `assets/js/cv-builder.js`  *(éditeur/rendu CV — sections 6 à 8)*
- `cv_builder.php`            *(page éditeur de CV)*
- `api/cv_profile.php`        *(API du profil de CV)*
- `api/health.php`            *(état de santé — matrice temps réel)*

**Fichiers modifiés**
- `includes/db.php`
- `includes/account.php`
- `login.php`
- `parametres.php`
- `includes/cv.php`   *(colonne `profile_json` + helpers de profil)*
- `cv_view.php`       *(rendu fidèle via le moteur partagé)*
- `mes_cv.php`        *(boutons Éditer / Aperçu)*
- `architecture.php` *(matrice de santé temps réel, à la demande)*

> ⚠️ Ne **jamais** uploader `config/config.php` (généré par le serveur).
> Les nouvelles tables (`password_resets`, `settings`) se créent toutes seules au
> premier usage : aucune manipulation phpMyAdmin nécessaire.
> La colonne `cvs.profile_json` est ajoutée **automatiquement** au premier accès
> (aucune migration manuelle).
> ⚠️ Le secret Google est écrit dans `includes/google_auth.php` : ne pas publier
> ce fichier sur un dépôt public (GitHub, etc.).
> 🗑️ Le dossier `cv_enligne/` a été **supprimé** : ne rien réuploader pour lui.

---

## Mise en service rapide (sur le serveur)

1. Se connecter : `https://luvumbu.com/cv_luvumbu/login.php`
   (identifiant + mot de passe de la base de données).
2. Aller dans `https://luvumbu.com/cv_luvumbu/parametres.php`.
3. Panneau **Connexion avec Google** → **Ajouter** son adresse Gmail.
4. Se déconnecter, puis tester **« Se connecter avec Google »**.

---

## Mises à jour — 19/07/2026 (espace admin & installation)

### A. Installation — création automatique du dossier `config/`
**Problème :** `install.php` affichait « Impossible d'écrire le fichier de
configuration (droits du dossier config/) » car le dossier `config/` n'était pas
déployé (git ne versionne pas les dossiers vides).

**Correctif :**
- `install.php` **crée le dossier `config/`** s'il manque, puis écrit la config,
  avec un message d'erreur précis (dossier absent / non inscriptible / fichier
  verrouillé).
- Ajout de `config/.gitignore` : force git à versionner le dossier `config/`
  tout en continuant d'ignorer `config.php`.

**Fichiers :** `install.php`, `config/.gitignore` *(nouveau)*.

### B. Connexion Google — adoption à la première connexion
**But :** ne plus avoir à saisir manuellement l'adresse Gmail. La **première**
connexion Google est **adoptée** automatiquement par le compte administrateur
(si aucune liste blanche n'est définie et que le compte n'a pas encore d'e-mail).
Ensuite, seule cette adresse peut se connecter.

**Aussi :** possibilité de **dissocier** l'adresse Google depuis
*Paramètres → Mon compte* (le prochain login Google ré-adopte la nouvelle
adresse), et réglage réversible `google_allow_any_email` (mode « toutes adresses »,
**désactivé par défaut — à ne JAMAIS activer en production**).

**Fichiers :** `includes/google_auth.php` (`google_adopt_primary_email`,
`google_allow_any_email`), `google_callback.php`, `parametres.php`.

### C. Espace admin dédié + bouton révélant le formulaire
- Sur `login.php` : le formulaire admin est **masqué**, un bouton
  **« 🔒 Se connecter en tant qu'admin »** le fait apparaître au clic. Le champ
  ne demande que l'**Identifiant** (plus « ou e-mail »).
- `admin.php` *(nouveau)* : page de connexion **par identifiants uniquement**
  (ni e-mail, ni Google).
- `cv_public.php` : lien discret **« 🔒 Espace admin »** dans la barre d'outils.

**Fichiers :** `login.php`, `admin.php` *(nouveau)*, `cv_public.php`.

> ⚠️ Rappel production : **ne pas** activer `google_allow_any_email` en ligne
> (n'importe quel compte Google pourrait alors se connecter en admin).
