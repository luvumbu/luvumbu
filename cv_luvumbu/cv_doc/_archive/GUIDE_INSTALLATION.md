# Guide d'installation & d'exploitation — CV Luvumbu

> Documentation **technique** destinée à l'installateur / administrateur système.
> Elle couvre les pré-requis, l'installation locale (XAMPP) et en production
> (Hostinger), la configuration, le schéma de base de données, l'API REST,
> la sécurité, la sauvegarde et le dépannage.

**Version :** juin 2026
**Pile technique :** PHP (PDO/MySQL), MySQL/MariaDB, aucun framework, aucune dépendance externe à installer.

---

## Sommaire

1. [Architecture technique](#1-architecture-technique)
2. [Pré-requis](#2-pré-requis)
3. [Arborescence des fichiers](#3-arborescence-des-fichiers)
4. [Installation locale (XAMPP)](#4-installation-locale-xampp)
5. [Installation en production (Hostinger)](#5-installation-en-production-hostinger)
6. [L'assistant d'installation pas à pas](#6-lassistant-dinstallation-pas-à-pas)
7. [Fichier de configuration](#7-fichier-de-configuration)
8. [Schéma de la base de données](#8-schéma-de-la-base-de-données)
9. [Migrations automatiques](#9-migrations-automatiques)
10. [Référence de l'API REST](#10-référence-de-lapi-rest)
11. [Connexion avec Google (OAuth)](#11-connexion-avec-google-oauth)
12. [Sécurité](#12-sécurité)
13. [Sauvegarde & restauration](#13-sauvegarde--restauration)
14. [Mise à jour / déploiement](#14-mise-à-jour--déploiement)
15. [Dépannage](#15-dépannage)

---

## 1. Architecture technique

CV Luvumbu est une application **PHP procédurale** (sans framework), organisée ainsi :

- **Pages** (`*.php` à la racine) : les écrans visibles (connexion, tableau de bord,
  éditeur, etc.).
- **Modules métier** (`includes/`) : logique réutilisable (base de données,
  authentification, CV, candidatures, clés API, réglages…).
- **API REST** (`api/`) : endpoints JSON authentifiés par clé API.
- **Assets** (`assets/`) : CSS, JavaScript (dont le moteur de rendu de CV
  `cv-builder.js`), images.
- **Configuration** (`config/config.php`) : généré par l'assistant d'installation,
  **jamais** versionné.

**Principe central :** le login du site = **identifiant + mot de passe de la base de
données**. Il n'y a pas de compte séparé à créer ; l'assistant d'installation crée
le compte administrateur en réutilisant les identifiants de la base.

---

## 2. Pré-requis

| Élément | Recommandation |
|---|---|
| **PHP** | 8.0 ou supérieur (le code utilise `str_contains`, `random_bytes`, `password_hash`). |
| **Extensions PHP** | `pdo_mysql` (obligatoire), `openssl` (pour `random_bytes`), `mbstring`, `json`. |
| **Base de données** | MySQL 5.7+ ou MariaDB 10.3+ (jeu de caractères `utf8mb4`). |
| **Serveur web** | Apache ou Nginx. En local : **XAMPP**. |
| **Envoi d'e-mails** | Fonction `mail()` PHP opérationnelle (pour « mot de passe oublié »). |
| **HTTPS** | Recommandé en production (obligatoire pour la connexion Google). |

---

## 3. Arborescence des fichiers

```
cv_luvumbu/
├── index.php               Point d'entrée (redirige selon l'état)
├── install.php             Assistant d'installation
├── login.php               Connexion
├── logout.php              Déconnexion
├── forgot_password.php     Demande de réinitialisation
├── reset_password.php      Réinitialisation via jeton
├── google_login.php        Démarre la connexion Google
├── google_callback.php     Retour OAuth Google
├── dashboard.php           Tableau de bord (candidatures)
├── mes_cv.php              Liste des CV, corbeille, partage
├── cv_builder.php          Éditeur de CV (WYSIWYG)
├── cv_view.php             Aperçu A4 / impression
├── cv_public.php           Vue publique d'un CV (lien partagé)
├── parametres.php          Compte, Google, clés API
├── architecture.php        Vue d'ensemble + matrice de santé
├── seed_online.php         (utilitaire d'amorçage)
│
├── config/
│   └── config.php          ⚙️ Généré à l'installation — NE PAS versionner
│
├── includes/               Modules métier
│   ├── db.php              Connexion PDO, config, santé base
│   ├── guard.php           Redirige vers l'install si base KO
│   ├── auth.php            Session, login/logout, CSRF
│   ├── account.php         Compte admin (e-mail, mot de passe, login BDD)
│   ├── password_reset.php  Jetons de réinitialisation
│   ├── google_auth.php     OAuth Google + liste blanche
│   ├── settings.php        Réglages clé/valeur (table settings)
│   ├── cv.php              CV : CRUD, profil riche, corbeille, partage
│   ├── applications.php    Candidatures : suivi, statuts, relances
│   └── api_keys.php        Clés API : génération, scopes, vérification
│
├── api/
│   ├── cv.php              API CV (clé API)
│   ├── cv_profile.php      API profil (session + CSRF, pour l'éditeur)
│   └── health.php          État de santé JSON (matrice temps réel)
│
├── assets/
│   ├── css/                Styles + thème
│   ├── js/                 cv-builder.js (moteur de rendu), theme-switch.js…
│   └── img/                Images, favicon
│
└── cv_doc/
    ├── documentation.html      Référence technique (au navigateur)
    ├── guide-complet.html      Guide complet (au navigateur)
    ├── GUIDE_UTILISATEUR.md     Guide utilisateur
    ├── GUIDE_INSTALLATION.md    Ce document
    ├── MODIFICATIONS.md         Journal des modifications
    └── CV-Ndenga-...DC2Scale.pdf  CV source (PDF)
```

---

## 4. Installation locale (XAMPP)

1. **Installer XAMPP** et démarrer **Apache** + **MySQL** depuis le panneau de contrôle.
2. **Copier le projet** dans `C:\xampp\htdocs\luvumbu\cv_luvumbu` (déjà le cas ici).
3. Ouvrir `http://localhost/luvumbu/cv_luvumbu/` dans le navigateur.
4. L'assistant d'installation s'affiche. Renseigner :
   - **Hôte** : `127.0.0.1` (ou `localhost`)
   - **Nom de la base** : `cv_luvumbu`
   - **Utilisateur** : `root`
   - **Mot de passe** : *(vide par défaut sous XAMPP)*
5. Cliquer sur **« Installer et continuer »**.
6. À la fin, vous êtes redirigé vers la connexion. Connectez-vous avec
   **`root`** + le mot de passe de la base (vide par défaut).

> L'assistant crée automatiquement la base, les tables et le compte administrateur.
> Aucune manipulation phpMyAdmin n'est nécessaire.

---

## 5. Installation en production (Hostinger)

1. **Créer la base MySQL** dans hPanel → *Bases de données MySQL*. Notez :
   - le **nom de la base** (ex. `u489596434_luvumbu`) ;
   - l'**utilisateur** (ex. `u489596434_luvumbu`) ;
   - le **mot de passe de la base** (⚠️ différent du mot de passe hPanel).
2. **Uploader les fichiers** du projet dans le dossier web
   (ex. `public_html/cv_luvumbu/`).

   > ⚠️ Ne **jamais** uploader `config/config.php` : il est généré par le serveur.
   > Vérifiez que le dossier `config/` est **inscriptible** par PHP.

3. Ouvrir `https://votre-domaine/cv_luvumbu/`. L'assistant s'affiche.
4. Renseigner l'**hôte** (souvent `localhost` chez Hostinger), le **nom de la base**,
   l'**utilisateur** et le **mot de passe de la base**.
5. Cliquer sur **« Installer et continuer »**, puis se connecter.

> Sur hébergement mutualisé, l'utilisateur n'a pas toujours le droit
> `CREATE DATABASE` : l'assistant ignore alors cet échec et utilise la base
> **déjà créée** dans hPanel. C'est pourquoi la base doit exister au préalable.

### Première mise en service

1. Se connecter : `https://votre-domaine/cv_luvumbu/login.php`.
2. Aller dans `parametres.php` → **Connexion avec Google** → **Ajouter** votre Gmail.
3. Se déconnecter, puis tester **« Se connecter avec Google »**.

---

## 6. L'assistant d'installation pas à pas

Le fichier `install.php` réalise, dans l'ordre :

1. **Connexion au serveur MySQL** avec les identifiants saisis. Il essaie l'hôte
   indiqué **puis l'autre variante** (`localhost` ↔ `127.0.0.1`), car un compte
   MySQL peut n'être autorisé que pour l'un des deux.
2. **Création de la base** si le droit existe (`CREATE DATABASE IF NOT EXISTS …`),
   sinon utilisation de la base existante.
3. **Création / réparation de la table `users`** : si elle est absente ou
   incompatible (ancienne tentative), toutes les tables résiduelles sont supprimées
   avant de recréer un schéma propre (évite l'erreur InnoDB errno 150 de clé
   étrangère orpheline). Une table `users` déjà correcte est conservée.
4. **Création de la table `api_keys`**.
5. **Création du compte administrateur** : `username` = utilisateur de la base,
   `password_hash` = hachage du mot de passe de la base
   (`INSERT … ON DUPLICATE KEY UPDATE`). Le compte est donc toujours aligné sur la
   configuration saisie.
6. **Écriture de `config/config.php`** (uniquement si tout a réussi).

Comportements particuliers :
- L'application **déjà installée et base OK** redirige directement vers `index.php`,
  sauf si on ajoute `?reconfigure=1`.
- L'application **installée mais base KO** ré-affiche l'assistant **sans détruire**
  la configuration existante (une panne passagère ne doit pas effacer des paramètres
  valides), en montrant l'erreur.
- Le mot de passe est **trimé** (espaces autour retirés) — cause fréquente
  d'« Access denied » lors d'un copier-coller depuis hPanel.

---

## 7. Fichier de configuration

`config/config.php` est un simple tableau PHP retourné :

```php
<?php
// Fichier généré par l'assistant d'installation. Ne pas committer en clair.
return array (
  'host'   => '127.0.0.1',
  'port'   => 3306,
  'dbname' => 'cv_luvumbu',
  'user'   => 'root',
  'pass'   => '',
);
```

- Le **port** est fixé à `3306` (MySQL/XAMPP standard) ; il n'est pas demandé.
- Ce fichier contient le **mot de passe de la base en clair** → il ne doit
  **jamais** être versionné ni rendu public (déjà listé dans `.gitignore`).
- Pour reconfigurer manuellement : modifiez ce fichier, ou ouvrez
  `install.php?reconfigure=1`.

---

## 8. Schéma de la base de données

Toutes les tables sont en `InnoDB`, `utf8mb4_unicode_ci`.

### `users` — comptes administrateurs

| Colonne | Type | Rôle |
|---|---|---|
| `id` | INT UNSIGNED PK AI | Identifiant. |
| `username` | VARCHAR(50) UNIQUE | Identifiant de connexion (= utilisateur BDD). |
| `email` | VARCHAR(150) UNIQUE NULL | E-mail (connexion, reset, Google). *(ajouté par migration)* |
| `password_hash` | VARCHAR(255) | Hachage du mot de passe (`password_hash`). |
| `must_change_password` | TINYINT(1) | Forçage du changement de mot de passe. *(migration)* |
| `created_at` | DATETIME | Date de création. |

### `cvs` — les CV

| Colonne | Type | Rôle |
|---|---|---|
| `id` | INT UNSIGNED PK AI | Identifiant. |
| `user_id` | INT UNSIGNED FK→users | Propriétaire (`ON DELETE CASCADE`). |
| `full_name` | VARCHAR(150) | Nom affiché (synchronisé depuis le profil). |
| `title`, `email`, `phone` | VARCHAR | Champs texte de base. |
| `summary`, `skills`, `experience`, `education` | TEXT | Champs texte historiques. |
| `profile_json` | LONGTEXT | **Profil riche** (source de vérité de l'éditeur). *(migration)* |
| `deleted_at` | DATETIME NULL | Corbeille (suppression douce). *(migration)* |
| `share_token` | VARCHAR(64) NULL | Jeton de partage public. *(migration)* |
| `created_at`, `updated_at` | DATETIME | Horodatage. |

### `applications` — candidatures

| Colonne | Type | Rôle |
|---|---|---|
| `id` | INT UNSIGNED PK AI | Identifiant. |
| `user_id` | INT UNSIGNED FK→users | Propriétaire (`ON DELETE CASCADE`). |
| `cv_id` | INT UNSIGNED FK→cvs NULL | CV envoyé (`ON DELETE SET NULL`). |
| `company` | VARCHAR(150) | Entreprise. |
| `sent_at` | DATE | Date d'envoi. |
| `status` | VARCHAR(20) | `en_attente` / `positive` / `negative`. |
| `followup` | TINYINT(1) | Relance activée. |
| `followup_date` | DATE NULL | Date de relance. *(migration)* |
| `notes` | TEXT | Notes libres. |
| `extra_json` | LONGTEXT NULL | Infos supplémentaires (liste `{label,value}`). *(migration)* |
| `created_at`, `updated_at` | DATETIME | Horodatage. |

### `api_keys` — clés API

| Colonne | Type | Rôle |
|---|---|---|
| `id` | INT UNSIGNED PK AI | Identifiant. |
| `user_id` | INT UNSIGNED FK→users | Propriétaire (`ON DELETE CASCADE`). |
| `label` | VARCHAR(100) | Nom de la clé. |
| `scopes` | VARCHAR(255) | Permissions (CSV). *(migration)* |
| `key_prefix` | VARCHAR(12) | Préfixe lisible (`cvk_…`). |
| `key_hash` | CHAR(64) UNIQUE | SHA-256 de la clé (jamais en clair). |
| `created_at`, `last_used_at`, `revoked_at` | DATETIME | Cycle de vie. |

### `password_resets` — jetons de réinitialisation
Créée automatiquement. Stocke les jetons (usage unique, expiration 1 h).

### `settings` — réglages clé/valeur
Créée automatiquement. Stocke notamment la config Google et la liste blanche.

---

## 9. Migrations automatiques

L'application **ne nécessite aucune migration manuelle**. À chaque accès, les
fonctions `ensure_*` :

- créent les tables manquantes (`CREATE TABLE IF NOT EXISTS`) ;
- ajoutent les colonnes ajoutées au fil des versions (`SHOW COLUMNS` /
  `information_schema` puis `ALTER TABLE`).

Colonnes ajoutées par migration : `users.email`, `users.must_change_password`,
`cvs.profile_json`, `cvs.deleted_at`, `cvs.share_token`,
`applications.followup_date`, `applications.extra_json`, `api_keys.scopes`.

> Conséquence : on peut déployer une nouvelle version par simple upload des fichiers,
> sans toucher à phpMyAdmin.

---

## 10. Référence de l'API REST

**Endpoint :** `api/cv.php`
**Authentification :** en-tête `X-API-Key: cvk_…` (ou `Authorization: Bearer cvk_…`).
**Format :** corps des requêtes en JSON ; réponses JSON.

| Méthode | Route | Permission | Description |
|---|---|---|---|
| `GET` | `/api/cv.php` | `cv:read` | Liste les CV. |
| `GET` | `/api/cv.php?id=N` | `cv:read` | Détail texte d'un CV. |
| `GET` | `/api/cv.php?id=N&profile=1` | `cv:read` | CV + profil riche. |
| `GET` | `/api/cv.php?whoami=1` | — | Renvoie `{ user_id, scopes }`. |
| `POST` | `/api/cv.php` | `cv:write` | Crée un CV (champs texte et/ou `profile`). |
| `PUT` | `/api/cv.php` | `cv:write` | Met à jour ; `{"share":true}` active le partage et renvoie `share_url`. |
| `DELETE` | `/api/cv.php?id=N[&force=1]` | `cv:write` ou `cv:delete` | Corbeille, ou suppression définitive avec `force=1`. |

**Codes de réponse :** `200/201` succès, `401` clé manquante/invalide,
`403` permission insuffisante, `404` CV introuvable, `405` méthode non autorisée,
`422` champ obligatoire manquant, `500` erreur serveur.

**Corps `profile`** (profil riche complet — remplace entièrement le profil existant) :
`firstName`, `lastName`, `headline`, `summary`,
`contact{location,phone,email,website,permis}`, `template`, `colors`, `photo`,
`sections[…]`. Fournir `full_name` est facultatif s'il existe un `profile` avec
`firstName`/`lastName` (il en est dérivé).

> Autres endpoints :
> - `api/cv_profile.php` : lecture/écriture du profil **par l'éditeur** (auth par
>   **session + CSRF**, vérification du propriétaire) — pas par clé API.
> - `api/health.php` : état de santé JSON, réservé aux utilisateurs **connectés**
>   (répond `401` JSON sinon). Alimente la matrice de santé d'`architecture.php`.

---

## 11. Connexion avec Google (OAuth)

- Les identifiants Google (ID client + secret) sont **intégrés dans le code**
  (`includes/google_auth.php`) : la connexion fonctionne sans configuration.
- La sécurité repose sur une **liste blanche** d'adresses (`settings`), gérée depuis
  *Paramètres*.

Console Google (déjà configurée) :
- **Origines JavaScript autorisées** : `https://luvumbu.com`, `http://localhost`.
- **URI de redirection** :
  `https://luvumbu.com/cv_luvumbu/google_callback.php`,
  `http://localhost/cv_luvumbu/google_callback.php`.

> ⚠️ Le secret Google est écrit dans `includes/google_auth.php` : **ne pas publier**
> ce fichier sur un dépôt public. Pour un autre domaine, ajoutez l'origine et l'URI
> de redirection correspondantes dans la console Google.

---

## 12. Sécurité

- **Mots de passe** hachés avec `password_hash` (`PASSWORD_DEFAULT` / bcrypt).
- **Sessions** : `session_regenerate_id(true)` à la connexion ; jeton **CSRF**
  (`auth.php`) sur les formulaires et l'API de profil.
- **Requêtes préparées** PDO partout (anti-injection SQL), `EMULATE_PREPARES=false`.
- **Clés API** : seul le **SHA-256** est stocké ; la clé en clair n'est affichée
  qu'une fois ; révocation immédiate possible.
- **Partage public** : jeton aléatoire de 32 caractères (`random_bytes(16)`) ;
  page publique en `noindex` ; désactivation immédiate.
- **Isolation** : toutes les requêtes filtrent par `user_id` (un utilisateur ne voit
  que ses propres données).
- **À ne jamais exposer** : `config/config.php` (mot de passe BDD en clair),
  `includes/google_auth.php` (secret Google).

---

## 13. Sauvegarde & restauration

**Sauvegarder :**
- la **base de données** (export `mysqldump` ou via phpMyAdmin / hPanel) — contient
  tous les CV, candidatures, clés et réglages ;
- le fichier **`config/config.php`** (paramètres de connexion).

```bash
mysqldump -u UTILISATEUR -p NOM_BASE > sauvegarde_cv_luvumbu.sql
```

**Restaurer :**
```bash
mysql -u UTILISATEUR -p NOM_BASE < sauvegarde_cv_luvumbu.sql
```
Puis vérifier que `config/config.php` pointe vers la bonne base.

---

## 14. Mise à jour / déploiement

1. Sauvegarder la base et `config/config.php` (voir §13).
2. **Uploader les fichiers modifiés/nouveaux** (ne **jamais** écraser
   `config/config.php`).
3. Recharger une page de l'application : les **migrations** ajoutent
   automatiquement les colonnes/tables manquantes (§9).
4. Vérifier l'état via la **matrice de santé** (`architecture.php`).

Fichiers à **ne jamais** uploader/écraser en production :
`config/config.php`. Fichier à **ne pas publier** publiquement :
`includes/google_auth.php`.

---

## 15. Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| `1045 Access denied` à l'installation | Mauvais utilisateur/mot de passe de la **base** (souvent confondu avec hPanel). | Vérifier/réinitialiser le mot de passe **de la base** dans hPanel, ressaisir sans espace. |
| `1049 Unknown database` | La base n'existe pas. | Créer la base dans hPanel, vérifier son nom exact. |
| `2002 / refused / getaddrinfo` | Serveur MySQL injoignable, mauvais hôte. | Essayer `localhost` au lieu de `127.0.0.1` (ou l'inverse). |
| « Impossible d'écrire le fichier de configuration » | Dossier `config/` non inscriptible. | Donner les droits d'écriture à `config/`. |
| Impossible de se connecter au site | Hash désynchronisé. | Se connecter avec l'identifiant + mot de passe **de la base** : la « solution ultime » (`db_login_fallback`) resynchronise le compte. |
| E-mail de réinitialisation non reçu | Pas d'e-mail enregistré ou `mail()` indisponible. | Renseigner l'e-mail dans *Paramètres → Mon compte* ; vérifier les spams / la config SMTP de l'hôte. |
| Connexion Google refusée | Adresse absente de la liste blanche, ou origine/URI non déclarée. | Ajouter l'adresse dans *Paramètres* ; vérifier la console Google. |
| La matrice de santé montre du 🔴 | Table absente, base KO… | Lire le détail de la pastille ; recharger pour déclencher les migrations. |

> Pour reconfigurer la connexion à la base sans tout réinstaller :
> ouvrir `install.php?reconfigure=1` (la config existante n'est pas détruite).

---

*CV Luvumbu — Guide d'installation & d'exploitation. Voir aussi
`GUIDE_UTILISATEUR.md` (utilisation) et `documentation.html` (référence au navigateur).*
