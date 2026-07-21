# Descriptions détaillées des projets — Luvumbu

> Portfolio : https://luvumbu.com · Réalisation phare : https://bokonzi.com
> Chaque description couvre : à quoi ça sert · fonctionnalités clés · technologies · état.

---

## 🏟️ BOKONZI — Plateforme data (athlétisme)
**En ligne : https://bokonzi.com**

Écosystème complet de données sur l'athlétisme, conçu, développé et déployé de A à Z.
Une **seule source de données** (une API) alimente plusieurs surfaces : site web public,
application mobile et espace professionnel.

- **Recherche multi-critères** d'athlètes et **fiches auto-générées**.
- **Data-visualisation** : statistiques et indicateurs croisés.
- **Paiement en ligne** pour les services premium.
- **API REST** : 20+ endpoints JSON, cache fichier 24 h, CORS — le moteur de tout l'écosystème.
- **Application mobile Android** (Capacitor) branchée sur la même API : une source, deux surfaces.
- **Espace Pro (B2B)** : tableau de bord, effectifs, indicateurs, export CSV.

**Stack :** PHP · MySQL · JavaScript · API REST · Capacitor (Android) · paiement en ligne.
**État :** en production, écosystème complet et interconnecté.

---

## 📄 CV Luvumbu — Créateur et suivi de CV
**En ligne : https://luvumbu.com/cv_luvumbu/**

Application web pour **créer, mettre en forme, partager et suivre** des CV,
avec suivi des candidatures associées.

- **Éditeur visuel WYSIWYG** et plusieurs **modèles** de CV.
- **Rendu imprimable / export PDF**.
- **Liens de partage publics** avec **QR code**.
- **Suivi de candidatures**.
- **Connexion Google (OAuth 2.0)**, configurable depuis l'espace d'administration.
- Secrets stockés **hors du dépôt Git** (bonne hygiène de sécurité).

**Stack :** PHP · MySQL · JavaScript · OAuth Google · génération PDF · QR codes.
**État :** fonctionnel, connexion Google opérationnelle en production.

---

## 📸 PhotoSync — Sauvegarde photo (Android + serveur)
**Dossier : dropbox → public_html**

Sauvegarde automatique des médias d'un téléphone vers un serveur personnel.
Application mobile **et** serveur développés de bout en bout.

- **Envoi en arrière-plan** des photos/vidéos depuis Android.
- **Serveur PHP** qui reçoit, range et **sert** les médias (HTTP JSON + multipart).
- **Multi-utilisateurs** : chaque compte ne voit que ses propres photos (cloisonnement).
- Gestion des **transferts volumineux**.

**Stack :** Android · PHP · HTTP (JSON + multipart) · comptes utilisateurs.
**État :** chaîne mobile → serveur fonctionnelle, cloisonnée par compte.

---

## 📷 DualCam — Service photo à backend indépendant
**En ligne : https://luvumbu.com/DualCam/**

Service de capture / partage de photos doté d'un **backend totalement autonome**
(base de données, stockage et code dédiés — rien de partagé avec les autres apps).

- **Comptes utilisateurs** : inscription, connexion, **connexion Google**.
- **Envoi, flux (feed), partage** et suppression de photos.
- **Assistant d'installation web** (install.php) : écrit la config, crée les tables
  (préfixe `dualcam_`) et le dossier d'upload automatiquement.
- Architecture propre en librairie : `Api`, `Auth`, `Db`, `Photos`.

**Stack :** PHP · MySQL · API · OAuth Google · installateur web.
**État :** déployable de façon indépendante, installation guidée.

---

## 🧲 Anniversaire — Comptes à rebours partagés (PWA)
**En ligne : https://luvumbu.com/anniversaire**

Application de **comptes à rebours d'anniversaires** avec espaces personnels protégés,
partage entre utilisateurs et installation sur mobile.

- **Double authentification** : nom + mot de passe **OU** compte Google.
- **Partage d'espaces** entre utilisateurs (voir + modifier, invitations).
- **PWA installable** (Android / iOS), fonctionne hors ligne.
- **Assistant d'installation forcé** : si la base est injoignable, l'app guide
  l'admin pour saisir/tester les identifiants MySQL, puis crée les tables.
- **Personnalisation par espace** : thème clair/sombre, couleurs, coins, police.
- Sécurité durcie : cookies HttpOnly/SameSite/Secure, anti-XSS, pas de fuite d'erreurs.

**Stack :** PHP · MySQL · JavaScript · OAuth Google · PWA (manifest, service worker).
**État :** déployée, partageable et installable.

---

## 🌐 RPN — Plateforme communautaire (MVC)
**En ligne : https://bokonzi.com/rpn/**

Plateforme communautaire pour membres (articles, quiz), avec espace d'administration
complet et **ouverture aux services tiers via API**.

- **Architecture MVC** (code structuré et maintenable).
- **Connexion Google (OAuth)** pour les membres.
- **Espace administrateur** complet.
- **API JSON authentifiée par clé** : création d'articles et de quiz à distance,
  avec documentation dédiée (routes, sécurité, tests).
- **Sécurité anti-bruteforce** et **thèmes** personnalisables.

**Stack :** PHP · MySQL · JavaScript · MVC · OAuth Google · API JSON par clé.
**État :** en ligne, documentée, ouverte aux intégrations externes.

---

## 🥚 Tamagotchi — Créature virtuelle (architecture en couches)
**Dossier : tamagotchi**

Jeu de créature virtuelle, doublé d'une **démonstration d'architecture back-end propre**.

- **API REST** en couches : Core, Models, Repositories, Services, Controllers.
- **Noyau PHP « fait maison »** : autoloader, routeur, connexion PDO, réponses JSON.
- Seul le dossier `public/` est exposé au navigateur (bonne pratique de sécurité).
- **Migrations** et **données de départ** (seed) versionnées.
- **Équilibrage du gameplay** externalisé en configuration (faim, santé, énergie…).
- Décliné en **application Android** (APK) et pensé pour le mobile.

**Stack :** PHP (API REST) · JavaScript · MySQL · Android · PDO · routeur maison.
**État :** base technique propre et documentée (docs/ARCHITECTURE.md), extensible.

---

## 🚀 ztransfert — Envoi de fichiers volumineux
**Dossier : ztransfert**

Service d'envoi de **fichiers volumineux** (type WeTransfer) : on dépose un fichier,
on reçoit un **lien de téléchargement par e-mail**. Jusqu'à plusieurs Go, sans inscription.

- **Upload de gros fichiers** côté navigateur.
- **Génération de liens** de téléchargement et **notification par e-mail**.
- **Espace d'administration** des envois.
- Gestion du **stockage** et du cycle de vie des fichiers.

**Stack :** PHP · JavaScript · MySQL · envoi d'e-mails · gestion de fichiers.
**État :** service de transfert fonctionnel, parcours d'envoi complet.

---

## ✍️ Articles / Blog « Marion Delval »
**Dossier : articles**

Espace éditorial et **plateforme de blog** avec contenus pédagogiques et sportifs.

- **Moteur de blog** : rédaction d'articles, API et export de contenu en JSON.
- **Application mobile** associée (dossier `mobile-app` + APK).
- Contenus dédiés : **programme d'entraînement 400 m haies** (parcours structuré en blocs)
  et **« maths concrets »** (pédagogie).
- Outils de maintenance : diagnostic, réparation de base, génération d'icônes.

**Stack :** HTML/CSS/JS · PHP · JSON · application Android.
**État :** site de contenu + moteur de blog avec API et app mobile.

---

## 🎨 Cours complet — HTML5 Canvas
**Dossier : Cours_complet_canvas**

Support de cours **interactif** qui enseigne l'**API Canvas de HTML5**, étape par étape.

- Progression pédagogique du plus simple au plus avancé.
- Démonstrations et exemples de dessin 2D en direct.

**Stack :** HTML5 Canvas · JavaScript · CSS.
**État :** support de cours en ligne, orienté vulgarisation technique.

---

## 🎵 Arduino Nano + DFPlayer Mini — Électronique
**Dossier : ELECTRONIQUE**

Projet **maker** : un **lecteur MP3** piloté par un Arduino Nano.

- Le **DFPlayer Mini** lit des MP3 sur carte micro-SD ; l'Arduino le pilote en **liaison série** (D10/D11).
- Page de documentation : **schéma de câblage** et **code Arduino (.ino)** commenté.

**Stack :** Arduino (C/C++) · électronique (DFPlayer Mini) · page HTML de documentation.
**État :** montage documenté (câblage + code), projet embarqué.

---

## 🗂️ Mes programmes (portail)
**Dossier : softwaire_programes**

**Portail cliquable** qui regroupe et présente des programmes/projets, facilement extensible
(il suffit d'ajouter une ligne de configuration pour référencer un nouveau dossier).

**Stack :** PHP · HTML/CSS.
**État :** page vitrine/portail, simple à mettre à jour.

---

## 🔴🟡 Puissance 4
**Dossier : puissance4**

Jeu de **Puissance 4** à 2 joueurs qui **sauvegarde toutes les parties**.

- Grille 7×6, détection des victoires (lignes, colonnes, diagonales) et des matchs nuls.
- **Sauvegarde serveur** de chaque résultat (écriture atomique avec verrou).
- **Classement** (victoires / parties / %) et **historique** des dernières parties.

**Stack :** PHP · JavaScript · stockage JSON.
**État :** jouable, résultats persistants.

---

## 🎮 Portfolio « Luvumbu Land » + back-office
**En ligne : https://luvumbu.com**

Portfolio original qui transforme les **dossiers-projets en monde de jeu rétro**
(façon Mario / Sonic), avec un **back-office** pour tout piloter sans toucher au code.

- **Carte de jeu** générée à partir de l'arborescence réelle des projets.
- **Espace admin** (admin.php) : apparence du site + **éditeur d'habillage par projet**
  (nom, icône/émoji, image, description, page d'entrée).
- **Gestionnaire de fichiers distant** (`_gestion/`) : API JSON sécurisée + interface
  pour gérer tous les fichiers du site à distance (auth, clé d'API, confinement, CSRF).
- Configuration en cascade (valeurs par défaut + surcharges JSON) et uploads sécurisés.

**Stack :** PHP · JavaScript · JSON · API · sécurité (auth, CSRF, confinement realpath).
**État :** en ligne, entièrement administrable via interface dédiée.
