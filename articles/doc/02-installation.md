# 02 — Installation

## A. Installation locale (XAMPP)

### Prérequis

- Windows / macOS / Linux
- XAMPP (Apache + MySQL + PHP 7.4+)
- Git
- Un éditeur de code (VS Code, etc.)

### Étapes

#### 1. Cloner le repo

```bash
cd C:\xampp\htdocs
git clone https://github.com/luvumbu/Blog.git Blog
```

Structure obtenue :

```
C:\xampp\htdocs\Blog\
├── index.html
├── blog/
├── doc/
└── .github/
```

#### 2. Démarrer XAMPP

Ouvre le panneau XAMPP Control → démarre **Apache** et **MySQL**.

Vérifie :
- http://localhost → page d'accueil XAMPP
- http://localhost/phpmyadmin → phpMyAdmin

#### 3. Créer la base de données locale

Dans phpMyAdmin :

1. Onglet **Bases de données**
2. Crée une base : `blog` (interclassement `utf8mb4_unicode_ci`)
3. **C'est tout** — l'installeur PHP créera les tables automatiquement

#### 4. Lancer l'installeur

Ouvre dans le navigateur :

```
http://localhost/Blog/blog/install.php
```

Remplis le formulaire :

| Champ | Valeur locale |
|---|---|
| Hôte MySQL | `localhost` |
| Nom de la base | `blog` |
| Utilisateur | `root` |
| Mot de passe | (vide par défaut sur XAMPP) |
| Email admin | ton.email@exemple.com |
| Mot de passe admin | un mot de passe robuste |

L'installeur :
1. Crée `blog/config/config.php` avec les credentials
2. Exécute `blog/sql/schema.sql` (crée toutes les tables)
3. Crée le premier utilisateur admin
4. Te redirige vers le site

#### 5. Accès local

- **Landing** : http://localhost/Blog/
- **Blog** : http://localhost/Blog/blog/
- **Admin** : http://localhost/Blog/blog/pages/admin.php
- **PWA mobile** : http://localhost/Blog/blog/mobile-app/

#### 6. Tester le mobile-app sur ton téléphone (même WiFi)

```bash
# Trouve l'IP de ton PC sur le WiFi
ipconfig    # Windows : cherche "Adresse IPv4"
# Exemple : 192.168.1.42
```

Sur ton téléphone (même WiFi) : `http://192.168.1.42/Blog/blog/mobile-app/`

⚠️ Le SW PWA peut refuser de s'installer hors HTTPS sur certains navigateurs. Pour tester complètement, déploie en prod.

---

## B. Déploiement sur Hostinger

### Prérequis

- Compte Hostinger (Premium ou Business)
- Domaine configuré (`mariondelval.com` dans cet exemple)
- Accès **hPanel**
- Repo GitHub fork ou clone

### Étape 1 — Créer la base de données

1. hPanel → **Bases de données** → **Bases MySQL**
2. Crée une nouvelle base. Note bien :
   - Nom de la base : `u489596434_blog` (avec préfixe Hostinger)
   - Utilisateur : `u489596434_admin`
   - Mot de passe : (génère un fort, copie-le)
   - Hôte : généralement `localhost`

### Étape 2 — Créer le sous-domaine `blog.<ton-domaine>`

1. hPanel → **Domaines** → **Sous-domaines**
2. Nouveau sous-domaine :
   - Nom : `blog`
   - Domaine : `mariondelval.com`
   - Document root : `public_html/blog` (important !)

Attends quelques minutes que la propagation DNS prenne (souvent < 10 min).

### Étape 3 — Récupérer les infos FTP

1. hPanel → **Fichiers** → **Comptes FTP**
2. Note :
   - **Hostname FTP** : ex. `ftp.tondomaine.com` ou `82.180.x.x`
   - **Username** : ex. `u489596434.tondomaine`
   - **Password** : celui que tu utilises dans FileZilla

### Étape 4 — Configurer GitHub Actions

1. Va sur **github.com/<ton-user>/Blog** → **Settings** → **Secrets and variables** → **Actions**
2. Clique **New repository secret** pour chaque :

| Secret | Valeur |
|---|---|
| `FTP_HOST` | ton hostname FTP |
| `FTP_USER` | ton user FTP |
| `FTP_PASSWORD` | ton mot de passe FTP |
| `FTP_REMOTE_DIR` | `/public_html/` |
| `FTP_PROTOCOL` | `ftps` (recommandé) ou `ftp` |
| `FTP_PORT` | `21` (uniquement si différent) |

### Étape 5 — Push initial

Depuis ton local :

```bash
git push origin master
```

→ Le workflow GitHub Actions se lance automatiquement (visible sur `github.com/<ton-user>/Blog/actions`)

Il déploie :
1. `./blog/` → `/public_html/blog/`
2. `./index.html` → `/public_html/index.html`

Durée : ~30s à 2 min selon la taille.

### Étape 6 — Installer le blog côté serveur

⚠️ Le `config/config.php` du repo n'est **pas** déployé (exclu du workflow car en `.gitignore`). Il faut donc lancer l'installeur côté serveur **une seule fois** :

1. Ouvre `https://blog.mariondelval.com/install.php`
2. Remplis le formulaire avec les infos de la base Hostinger (étape 1)
3. Crée le compte admin (peut être différent du local)

L'installeur crée `/public_html/blog/config/config.php` directement sur Hostinger.

⚠️ **Important** : `install.php` doit être supprimé / désactivé après installation. Tu peux soit :
- Le supprimer via FTP/hPanel
- Renommer `install.php` → `install.php.disabled`
- Ajouter une condition au début pour qu'il refuse de tourner si `config.php` existe (déjà fait dans le code)

### Étape 7 — Vérifier

- `https://mariondelval.com` → landing visible
- `https://blog.mariondelval.com` → accueil du blog visible
- Crée un article test depuis l'admin → vérifie qu'il apparaît
- `https://blog.mariondelval.com/mobile-app/` → tu peux installer la PWA

---

## C. Mise à jour après le déploiement initial

Une fois tout en place, ton workflow devient :

```bash
# 1. Tu codes en local
# 2. Tu testes sur http://localhost/Blog/blog/
# 3. Quand c'est bon :
git add .
git commit -m "Description"
git push
# 4. GitHub Actions déploie automatiquement (~1 min)
# 5. Vérifie sur https://blog.mariondelval.com
```

Pour pousser le contenu (articles, images, settings) plutôt que le code, voir [06-sync.md](06-sync.md).

---

## D. Problèmes d'installation fréquents

### Le sous-domaine n'est pas accessible

- Vérifie le Document root dans hPanel : il doit être `public_html/blog`
- Attends 10 min de propagation DNS
- Teste depuis un autre réseau (pas de cache DNS local)

### "Blog non installé" sur les endpoints API

Le serveur cherche `blog/config/config.php`. Si absent :
- Va sur `https://blog.mariondelval.com/install.php`
- Ou crée manuellement le fichier en FTP avec les bons credentials

### GitHub Actions échoue avec "EHOSTUNREACH"

- Mauvais `FTP_HOST` dans les secrets
- Hostinger peut bloquer certaines régions GitHub → essaie de changer `FTP_PROTOCOL` de `ftps` à `ftp`

### Erreur PDO "Access denied"

- Vérifie que dans hPanel l'utilisateur MySQL a bien tous les privilèges sur la base
- Tape le mot de passe directement (ne le copie-colle pas, parfois des caractères invisibles se glissent)
