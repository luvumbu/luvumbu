# 05 — Déploiement (GitHub Actions → Hostinger FTP)

## Vue d'ensemble

Le déploiement est entièrement automatique : à chaque `git push` sur la branche `master`, GitHub Actions déclenche un workflow qui upload les fichiers via FTPS sur Hostinger.

```
Local (push)
    │
    ▼
GitHub Actions (Ubuntu runner)
    ├── Étape 1 : checkout du repo
    ├── Étape 2 : upload blog/ → /public_html/blog/   (FTPS)
    └── Étape 3 : upload index.html → /public_html/   (FTPS)
    │
    ▼
Hostinger (serveur web)
    │
    ▼
Visiteurs voient les changements (~1 min après push)
```

## Le workflow `.github/workflows/deploy.yml`

```yaml
name: Deploy to Hostinger via FTP

on:
  push:
    branches: [master]
  workflow_dispatch:

jobs:
  ftp-deploy:
    name: Sync files to Hostinger
    runs-on: ubuntu-latest
    steps:
      - name: Checkout repository
        uses: actions/checkout@v4

      - name: FTP Deploy blog (subdomain blog.mariondelval.com)
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          server:        ${{ secrets.FTP_HOST }}
          username:      ${{ secrets.FTP_USER }}
          password:      ${{ secrets.FTP_PASSWORD }}
          protocol:      ${{ secrets.FTP_PROTOCOL || 'ftps' }}
          port:          ${{ secrets.FTP_PORT || 21 }}
          server-dir:    /public_html/blog/
          local-dir:     ./blog/
          dangerous-clean-slate: false
          exclude: |
            **/.git*
            **/.git*/**
            **/node_modules/**
            config/config.php
            config/sync_keys.json
            uploads/**
            APK/**
            *.apk
            *.aab
            *.keystore
            signing-key-info.txt
            _diagnostic.php
            _fix_db.php
            mobile-app/generate-icons.ps1
            generate-site-icons.ps1
            **/.htaccess.local
            README.md

      - name: FTP Deploy landing page (mariondelval.com)
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          server:        ${{ secrets.FTP_HOST }}
          username:      ${{ secrets.FTP_USER }}
          password:      ${{ secrets.FTP_PASSWORD }}
          protocol:      ${{ secrets.FTP_PROTOCOL || 'ftps' }}
          port:          ${{ secrets.FTP_PORT || 21 }}
          server-dir:    /public_html/
          local-dir:     ./
          dangerous-clean-slate: false
          exclude: |
            **/.git*
            **/.git*/**
            **/.github/**
            blog/**
            README.md
            .gitignore
            *.ps1
            *.md
```

## Triggers

- `push` sur `master` → déclenchement automatique
- `workflow_dispatch` → déclenchement manuel via l'onglet **Actions** de GitHub

## Action utilisée

[SamKirkland/FTP-Deploy-Action](https://github.com/SamKirkland/FTP-Deploy-Action) v4.3.5 :
- Action GitHub officielle pour le déploiement FTP/FTPS
- Comparaison de hash : ne re-upload que les fichiers modifiés
- Support FTPS (TLS chiffré)
- Mode `dangerous-clean-slate: false` → ne supprime jamais de fichiers distants (sécurité)

## Étapes expliquées

### Étape 1 — Checkout

```yaml
- uses: actions/checkout@v4
```

Clone le repo dans le runner Ubuntu de GitHub Actions. À la fin, le filesystem temporaire contient tout le code à la même structure que sur `master`.

### Étape 2 — Déploiement du blog

| Paramètre | Valeur |
|---|---|
| `local-dir` | `./blog/` |
| `server-dir` | `/public_html/blog/` |

→ Tout le contenu du dossier `blog/` du repo est envoyé à `/public_html/blog/` sur Hostinger.

**Exclusions** (cf. liste `exclude`) :
- Fichiers Git (`.git`, `.gitignore`)
- Config locale (`config/config.php`) — la prod a son propre `config.php` créé par `install.php`
- Clés de sync (`config/sync_keys.json`) — fichier local de chaque instance
- Uploads (`uploads/**`) — les images sont gérées via le système de sync (pas le déploiement)
- Binaires APK et clé de signature — ne doivent jamais être en ligne
- Scripts de debug (`_diagnostic.php`, `_fix_db.php`)
- Scripts locaux (`generate-icons.ps1`)
- README (pas utile en prod)

### Étape 3 — Déploiement de la landing

| Paramètre | Valeur |
|---|---|
| `local-dir` | `./` (racine du repo) |
| `server-dir` | `/public_html/` |

→ La racine du repo est envoyée à `/public_html/`. **Mais** avec un `exclude` strict qui exclut tout sauf `index.html` :

- `blog/**` → déjà déployé à l'étape 2, on ne le réenvoie pas
- `.git*`, `.github/**` → métadonnées
- `*.md` → docs
- `.gitignore`, `*.ps1` → fichiers de repo

Résultat : seul `index.html` (la landing) est uploadé à `/public_html/`.

## Secrets requis

À configurer dans `github.com/<user>/Blog → Settings → Secrets and variables → Actions` :

| Secret | Obligatoire | Description |
|---|---|---|
| `FTP_HOST` | ✅ | Hostname ou IP du serveur FTP Hostinger |
| `FTP_USER` | ✅ | Nom d'utilisateur FTP (souvent `uXXXXX.<domaine>`) |
| `FTP_PASSWORD` | ✅ | Mot de passe FTP |
| `FTP_PROTOCOL` | ❌ | `ftps` (défaut) ou `ftp` |
| `FTP_PORT` | ❌ | `21` (défaut) |

Pour les trouver dans hPanel : **Fichiers → Comptes FTP**.

## Logs de déploiement

Après chaque push, va sur :

```
https://github.com/<user>/Blog/actions
```

Tu y vois la liste des runs avec leur statut (✅ vert, ❌ rouge, 🟡 en cours).

Clique sur un run pour voir les détails :
- Liste des fichiers uploadés
- Erreurs FTP éventuelles (timeout, auth fail, etc.)

## Pourquoi pas `dangerous-clean-slate: true` ?

Cette option supprimerait sur le serveur les fichiers qui n'existent plus dans le repo. Désactivée car :
- Risque de supprimer `config/config.php`, `uploads/`, etc. par erreur si on oublie une exclusion
- Sur Hostinger les opérations de delete FTP sont parfois lentes / capricieuses
- En cas de bug, plus difficile à diagnostiquer

**Conséquence** : si tu supprimes un fichier du repo, il reste sur le serveur. Pour le nettoyer, supprime-le manuellement via FileZilla ou hPanel.

## Bug courant : "Cannot connect to FTP server"

Causes habituelles :
1. **Mauvais `FTP_HOST`** : copie depuis hPanel exactement
2. **Hostinger bloque la région du runner** : essaie de passer `FTP_PROTOCOL` à `ftp` (au lieu de `ftps`)
3. **Mot de passe avec caractères spéciaux** : encoder en URL ou simplifier le mot de passe

## Déploiement manuel (sans Git push)

Va sur l'onglet **Actions** de GitHub → workflow "Deploy to Hostinger via FTP" → bouton **Run workflow** (en haut à droite) → sélectionne la branche → confirme.

Utile pour redéployer après avoir corrigé un secret sans nouveau commit.

## Synchronisation des données (≠ du code)

Le workflow ne déploie **que le code source**. Pour pousser :
- Les articles, commentaires, utilisateurs (BDD)
- Les images uploadées (`uploads/`)
- Les paramètres (table `settings`)

... il faut utiliser le système de synchronisation manuel. Voir [06-sync.md](06-sync.md).
