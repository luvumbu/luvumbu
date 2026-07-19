# Guide utilisateur — CV Luvumbu

> Documentation technique destinée à l'**utilisateur** de l'application.
> Elle explique, page par page, comment se connecter, créer un CV, le mettre
> en forme, le partager, suivre ses candidatures et piloter l'application par API.

**Version :** juin 2026
**Public visé :** administrateur / utilisateur final de l'application.
**Pré-requis :** application déjà installée (voir `GUIDE_INSTALLATION.md`).

---

## Sommaire

1. [Présentation](#1-présentation)
2. [Premiers pas & connexion](#2-premiers-pas--connexion)
3. [Le tableau de bord](#3-le-tableau-de-bord)
4. [Gérer ses CV (« Mes CV »)](#4-gérer-ses-cv--mes-cv-)
5. [L'éditeur de CV (WYSIWYG)](#5-léditeur-de-cv-wysiwyg)
6. [Aperçu, impression et export PDF](#6-aperçu-impression-et-export-pdf)
7. [Partage public + QR code](#7-partage-public--qr-code)
8. [La corbeille](#8-la-corbeille)
9. [Suivi des candidatures](#9-suivi-des-candidatures)
10. [Paramètres](#10-paramètres)
11. [Connexion avec Google](#11-connexion-avec-google)
12. [Mot de passe oublié](#12-mot-de-passe-oublié)
13. [Les clés API](#13-les-clés-api)
14. [La matrice de santé](#14-la-matrice-de-santé)
15. [Questions fréquentes (FAQ)](#15-questions-fréquentes-faq)

---

## 1. Présentation

**CV Luvumbu** est une application web (PHP / MySQL) qui permet de **créer, mettre
en forme, partager et suivre** des CV.

Principales fonctionnalités :

- **Éditeur visuel WYSIWYG** : l'aperçu à l'écran est strictement identique au CV
  imprimé / exporté en PDF.
- **Plusieurs modèles** de CV (dont le modèle « Tableautier / DC2Scale »).
- **Export PDF** via l'impression du navigateur.
- **Liens de partage publics** avec **QR code** (visibles sans compte).
- **Corbeille** (suppression douce, restauration possible).
- **Suivi de candidatures** (entreprise, date, statut, relances, infos libres).
- **API REST** pour piloter les CV à distance avec une clé.
- Connexion par **identifiants de base de données**, par **e-mail** ou via **Google**.

---

## 2. Premiers pas & connexion

### Accéder à l'application

Ouvrez votre navigateur à l'adresse de l'application, par exemple :

- En local (XAMPP) : `http://localhost/luvumbu/cv_luvumbu/`
- En ligne : `https://luvumbu.com/cv_luvumbu/`

Le point d'entrée (`index.php`) vous redirige automatiquement :

| État de l'application | Redirection |
|---|---|
| Pas encore installée | → assistant d'installation (`install.php`) |
| Installée, vous n'êtes pas connecté | → page de connexion (`login.php`) |
| Installée, vous êtes connecté | → tableau de bord (`dashboard.php`) |

### Se connecter

Sur la page **Connexion** (`login.php`), trois méthodes sont possibles :

1. **Identifiant + mot de passe de la base de données**
   *(la « solution ultime »)* — c'est la méthode garantie. Saisissez :
   - **Identifiant** : l'utilisateur de la base de données (ex. `root` en local,
     ou `u489596434_luvumbu` sur Hostinger).
   - **Mot de passe** : le mot de passe **de la base de données**
     (⚠️ pas le mot de passe du panneau d'hébergement hPanel).

   > Si la vérification habituelle échoue, l'application teste directement une
   > vraie connexion MySQL avec ce que vous saisissez. Si MySQL accepte, vous
   > êtes connecté et votre compte est resynchronisé automatiquement.

2. **Adresse e-mail + mot de passe**
   Si vous avez enregistré une adresse e-mail dans *Paramètres → Mon compte*,
   vous pouvez l'utiliser à la place de l'identifiant.

3. **Se connecter avec Google**
   Bouton toujours visible (voir §11). Seules les adresses Google **autorisées**
   peuvent se connecter.

Un lien **« Mot de passe oublié ? »** est également présent (voir §12).

---

## 3. Le tableau de bord

Le **tableau de bord** (`dashboard.php`) est la page d'accueil une fois connecté.
Il sert au **suivi des candidatures** (détaillé au §9) et affiche des
**statistiques rapides** :

- nombre total de candidatures ;
- candidatures en attente / réponses positives / réponses négatives ;
- relances programmées et **relances à faire** (date atteinte ou dépassée).

Depuis le menu, vous accédez aux autres pages : **Mes CV**, **Paramètres**,
**Architecture**, et **Déconnexion**.

---

## 4. Gérer ses CV (« Mes CV »)

La page **Mes CV** (`mes_cv.php`) liste tous vos CV actifs. Pour chaque CV :

- **✏️ Éditer** → ouvre l'éditeur visuel (voir §5).
- **👁️ Aperçu** → affiche le CV façon document A4, imprimable (voir §6).
- **🔗 Partager** → active/désactive un lien public + QR code (voir §7).
- **🗑️ Supprimer** → place le CV dans la corbeille (voir §8).

En haut de page :

- **➕ Nouveau CV** : crée un CV vierge, prêt à éditer.
- **🗑️ Corbeille** : accès aux CV supprimés (restauration / suppression définitive).
- **Aide contextuelle** : des pastilles **ⓘ** expliquent chaque élément ; un
  **Mode aide** affiche toutes les explications d'un coup.

---

## 5. L'éditeur de CV (WYSIWYG)

L'éditeur (`cv_builder.php`) est le cœur de l'application. **L'aperçu en direct,
à droite, est exactement ce qui sera imprimé / exporté.**

### Zone de gauche : les réglages

| Réglage | Description |
|---|---|
| **Modèle** | `dc2scale` (Tableautier), `moderne`, etc. Change toute la mise en page. |
| **Couleurs** | Couleur principale + secondaire, palettes prêtes à l'emploi, réglages avancés (texte, fond, bordures, badges). |
| **📷 Photo de profil** | Importer une photo, régler sa **taille** et sa **forme** (cercle, arrondi, carré, portrait, hexagone), la position. |
| **🖼️ Ajouter une image** | Pose une **image libre** sur la page (différente de la photo de profil). Déplaçable et redimensionnable. |
| **Titre / poste** (`headline`) | Le métier affiché sous le nom. |
| **Coordonnées** | Lieu, téléphone, e-mail, permis… Bouton **+** pour ajouter une info, **✕** pour la retirer. |
| **Profil / résumé** | Texte de présentation. |
| **Taille du texte** | Échelle de police globale. |

### Les sections

Les sections sont **réordonnables par glisser-déposer** et chacune accepte
l'ajout / la suppression d'éléments :

- **Expérience** (poste, entreprise, dates, puces de description) ;
- **Formation** ;
- **Compétences** ;
- **Logiciels** ;
- **Habilitations** ;
- **Langues** ;
- **Loisirs**.

### Options de mise en page

- **📐 Tout tenir sur une seule page** : un algorithme mesure la hauteur réelle et
  **compresse** automatiquement la mise en page jusqu'à ce que tout tienne sur une
  page A4. Si le contenu tient déjà, aucune réduction n'est appliquée.
- **Disposition libre (canvas)** : positionnez librement les blocs (déplacer ✥,
  redimensionner ↔ ↕ ⤡, supprimer ✕).
- **Aperçu visible / masqué** : masquer l'aperçu donne un formulaire plein écran.

### Enregistrer

- **💾 Enregistrer** envoie le profil complet (JSON) au serveur. Le nom du CV est
  resynchronisé à partir du prénom + nom.
- **🎓 Tutoriel** : visite guidée pas à pas de toutes les options (surbrillance + bulle).

---

## 6. Aperçu, impression et export PDF

La page **Aperçu** (`cv_view.php?id=…`) affiche le CV **exactement comme l'éditeur**,
au format document A4.

- **🖨 Imprimer / Enregistrer en PDF** : utilise l'impression du navigateur.
  Pour obtenir un PDF, choisissez « **Enregistrer au format PDF** » comme imprimante.

> 💡 Conseil : activez l'option **« Tout tenir sur une seule page »** dans l'éditeur
> avant d'imprimer pour garantir un CV sur une seule feuille A4.

---

## 7. Partage public + QR code

Chaque CV peut être partagé publiquement.

1. Depuis **Mes CV**, cliquez sur **🔗 Partager**.
2. L'application génère un **jeton de partage** unique et :
   - un **lien public** de la forme `cv_public.php?token=…` que n'importe qui peut
     ouvrir **sans compte** ;
   - un **QR code** pointant vers ce lien.
3. Le partage est **désactivable à tout moment** : le lien cesse alors de fonctionner.

> 🔒 La page publique est en `noindex` : elle n'est pas référencée par les moteurs
> de recherche. Le jeton est aléatoire (32 caractères) : un lien non communiqué
> reste introuvable.

---

## 8. La corbeille

Supprimer un CV ne l'efface pas immédiatement : il part dans la **corbeille**
(suppression douce). Depuis la corbeille (bouton **🗑️ Corbeille** sur Mes CV) :

- **♻️ Restaurer** : le CV revient dans la liste active ;
- **❌ Supprimer définitivement** : effacement irréversible.

---

## 9. Suivi des candidatures

Sur le **tableau de bord**, vous suivez vos envois de CV. Pour chaque candidature :

| Champ | Description |
|---|---|
| **Entreprise** | Nom de l'employeur. |
| **Date d'envoi** | Quand le CV a été envoyé. |
| **CV envoyé** | Le CV associé (facultatif). |
| **Statut** | En attente / Réponse positive / Réponse négative. |
| **Relance** | Active/désactive une relance, avec une **date** ; la candidature passe en « à faire » quand la date est atteinte. |
| **Infos supplémentaires** | Bouton **« + Ajouter une information »** : champs libres (libellé + valeur) — poste, salaire, contact, lien… Affichés sous forme de puces. |

Vous pouvez modifier le statut, programmer/annuler une relance et supprimer une
candidature à tout moment.

---

## 10. Paramètres

La page **Paramètres** (`parametres.php`) regroupe :

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

### Mon compte

- **Adresse e-mail** : permet de se connecter par e-mail, de recevoir le lien de
  réinitialisation de mot de passe, et sert pour la connexion Google.
- **Changer le mot de passe** : demande le mot de passe actuel puis le nouveau.

---

## 11. Connexion avec Google

La connexion Google fonctionne « clés en main » (les identifiants Google sont déjà
intégrés). La sécurité repose sur une **liste blanche** :

1. Allez dans *Paramètres → Connexion avec Google → Comptes Google autorisés*.
2. **Ajoutez** votre adresse Gmail (champ + bouton **Ajouter**).
3. Déconnectez-vous, puis testez **« Se connecter avec Google »**.

Comportement à la connexion :

```
Clic « Se connecter avec Google » → choix du compte Google
   ├─ adresse dans la liste autorisée → connecté
   └─ adresse absente                 → refusé
```

Vous pouvez **retirer** une adresse à tout moment (avec confirmation). Les doublons
et adresses invalides sont refusés ; effet immédiat.

---

## 12. Mot de passe oublié

1. Sur la page de connexion, cliquez sur **« Mot de passe oublié ? »**.
2. Saisissez votre **identifiant ou e-mail**.
3. Un **lien de réinitialisation**, valable **1 heure** et **à usage unique**,
   est envoyé par e-mail (le message reste générique pour ne pas révéler si un
   compte existe).
4. Ouvrez le lien (`reset_password.php?token=…`) et choisissez un nouveau mot de passe.

> ⚠️ Pré-requis : une **adresse e-mail** doit être enregistrée dans
> *Paramètres → Mon compte*, sinon aucun e-mail ne peut être envoyé.

> 💡 Astuce de secours : vous pouvez toujours vous connecter avec l'identifiant +
> mot de passe **de la base de données** (voir §2).

---

## 13. Les clés API

Les **clés API** permettent à un programme externe de piloter vos CV.

### Créer une clé

1. *Paramètres → Clés API*.
2. Donnez un **nom** (libellé) et cochez les **permissions** souhaitées.
3. La clé complète (format `cvk_…`) **n'est affichée qu'une seule fois** : copiez-la
   immédiatement. Seul son empreinte (SHA-256) est stockée — elle n'est jamais
   réaffichée.

### Permissions (scopes)

| Scope | Autorise |
|---|---|
| `cv:read` | Lire les CV |
| `cv:write` | Créer et modifier les CV |
| `cv:delete` | Supprimer les CV |
| `profile:read` | Lire le profil riche |
| `profile:write` | Modifier le profil riche |

### Utiliser la clé

Envoyez la clé dans l'en-tête `X-API-Key` (ou `Authorization: Bearer …`).

**Exemples :**

```bash
# Lister mes CV
curl -H "X-API-Key: cvk_xxxxxxxx" https://luvumbu.com/cv_luvumbu/api/cv.php

# Détail d'un CV + profil riche
curl -H "X-API-Key: cvk_xxxxxxxx" "https://luvumbu.com/cv_luvumbu/api/cv.php?id=12&profile=1"

# Créer un CV
curl -X POST -H "X-API-Key: cvk_xxxxxxxx" -H "Content-Type: application/json" \
  -d '{"full_name":"Jean Dupont","title":"Électricien"}' \
  https://luvumbu.com/cv_luvumbu/api/cv.php

# Activer le partage public et récupérer l'URL
curl -X PUT -H "X-API-Key: cvk_xxxxxxxx" -H "Content-Type: application/json" \
  -d '{"id":12,"share":true}' \
  https://luvumbu.com/cv_luvumbu/api/cv.php
```

La référence complète des routes API est détaillée dans `documentation.html` et
dans `GUIDE_INSTALLATION.md`.

### Révoquer une clé

Dans la liste des clés, cliquez sur **Révoquer** : la clé cesse immédiatement de
fonctionner (sans rien casser d'autre).

---

## 14. La matrice de santé

La page **Architecture** (`architecture.php`) propose une **matrice de santé en
temps réel** de l'application.

- Cliquez sur **« 🩺 Matrice de santé — Afficher »** : rien ne tourne tant que vous
  n'ouvrez pas la matrice.
- Une fois ouverte, elle se **rafraîchit toutes les 4 secondes** et montre, avec une
  pastille **🟢 OK / 🟡 avertissement / 🔴 erreur** :
  - la configuration ;
  - la connexion à la base ;
  - les tables (présence + nombre de lignes) ;
  - les modules métier et les endpoints API.
- **« Masquer »** arrête immédiatement le rafraîchissement. Le suivi se met aussi
  en pause quand l'onglet n'est plus visible.

---

## 15. Questions fréquentes (FAQ)

**Je n'arrive plus à me connecter.**
Utilisez l'identifiant + mot de passe **de la base de données** (pas hPanel).
En dernier recours, réinitialisez le mot de passe de la base dans votre panneau
d'hébergement puis reconnectez-vous : le compte se resynchronise tout seul.

**Mon CV déborde sur deux pages.**
Activez **« 📐 Tout tenir sur une seule page »** dans l'éditeur.

**Comment obtenir un PDF ?**
Ouvrez l'aperçu → **🖨 Imprimer** → choisissez « Enregistrer au format PDF ».

**Le lien de partage ne marche plus.**
Le partage a probablement été désactivé. Réactivez-le depuis **Mes CV → Partager**
(un nouveau jeton n'est créé que si aucun n'existait).

**La connexion Google est refusée.**
Votre adresse n'est pas dans la liste blanche : ajoutez-la dans
*Paramètres → Comptes Google autorisés*.

**Je n'ai pas reçu l'e-mail de réinitialisation.**
Vérifiez qu'une adresse e-mail est enregistrée dans *Paramètres → Mon compte* et
regardez vos spams. Sinon, connectez-vous avec les identifiants de la base.

---

*CV Luvumbu — Guide utilisateur. Voir aussi `GUIDE_INSTALLATION.md` (installation &
exploitation) et `documentation.html` (référence technique consultable au navigateur).*
