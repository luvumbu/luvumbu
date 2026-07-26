# Mettre l'application en ligne

L'application tourne en local sur XAMPP. La mettre en ligne demande **trois
choses distinctes**, et c'est la deuxième qu'on oublie&nbsp;:

1. envoyer les fichiers sur le serveur&nbsp;;
2. **recréer la base de données** — sans elle la page affiche « Base de données
   inaccessible » et rien d'autre&nbsp;;
3. écrire `config/config.local.php` sur le serveur avec les identifiants MySQL
   de l'hébergeur.

## Prérequis côté serveur

| | |
|---|---|
| PHP | **8.0 minimum** (le code utilise `str_starts_with`) |
| Extensions | `pdo_mysql`, `mbstring`, `json`, `curl` |
| Base | MySQL 5.7+ ou MariaDB 10.4+ |
| Apache | `mod_rewrite` non requis, mais `.htaccess` doit être pris en compte |

## 1. La base de données

Le dépôt ne contient pas les données — elles vivent dans MySQL. Un dump complet
est produit en local&nbsp;:

```bash
"C:/xampp/mysql/bin/mysqldump.exe" -u root --default-character-set=utf8mb4 \
  --single-transaction --no-tablespaces --skip-comments athle_competition \
  | sed -e 's/DEFINER=`[^`]*`@`[^`]*`//g' -e 's/SQL SECURITY DEFINER/SQL SECURITY INVOKER/g' \
  > sql/dump.sql && gzip -f sql/dump.sql
```

Le `sed` n'est pas cosmétique&nbsp;: `mysqldump` inscrit
`DEFINER=root@localhost` dans la vue `v_competitions_geo`, et l'import échoue
sur un hébergement mutualisé où vous n'êtes pas `root`.

Ensuite, dans **phpMyAdmin** de l'hébergeur&nbsp;: créez la base, puis
*Importer* → `sql/dump.sql.gz` (l'archive gzip est acceptée telle quelle).

Contenu attendu après import&nbsp;:

| Table | Lignes |
|---|---|
| `competitions` | 165 |
| `cities` | 69 |
| `places` | 44 543 |
| `disciplines` | 70 |
| `competition_disciplines` | 1 484 |

## 2. Les identifiants

Copiez `config/config.local.php.example` en **`config/config.local.php`**
directement sur le serveur, et renseignez la base. Ce fichier est ignoré par Git
— il ne remonte jamais dans le dépôt et le déploiement ne l'écrase pas.

## 3. Les fichiers

Deux voies, au choix.

**Automatique** — le dépôt contient `.github/workflows/deploy-athle.yml`&nbsp;:
chaque `git push` sur `master` touchant `ATHLE_COMPETITION/` envoie le dossier
par FTPS. Il faut d'abord déclarer les secrets dans
*GitHub → Settings → Secrets and variables → Actions*&nbsp;:
`FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`, `FTP_PORT`, `FTP_PROTOCOL`.

**Manuelle** — gestionnaire de fichiers de l'hébergeur ou FileZilla. Envoyez
tout **sauf** `scraper/`, `data/`, `sql/` et `node_modules/`.

## Ce qui ne marchera pas sur le serveur — et pourquoi

**Le scraper ne peut pas tourner en ligne.** `scrape.js` et `details.js` pilotent
un vrai Chrome pour franchir le challenge Cloudflare d'athletisme.app&nbsp;: un
hébergement mutualisé n'a ni Chrome, ni Playwright, ni le droit de lancer un
navigateur.

La mise à jour reste donc **locale**, en trois temps&nbsp;:

```bash
update.cmd                     # 1. rafraîchit la base locale
# 2. régénérer sql/dump.sql.gz (commande plus haut)
# 3. réimporter le dump dans phpMyAdmin
```

C'est le prix du contournement de Cloudflare&nbsp;: les données transitent par
votre machine. Pour automatiser, il faudrait un serveur où vous pouvez installer
Chrome (VPS), pas un mutualisé.

## Sécurité de l'exposition publique

En local, l'API sans authentification n'était pas un sujet. En ligne, trois
points ont été traités&nbsp;:

- **`.htaccess`** ferme `bin/`, `sql/`, `data/`, `scraper/`, `config/` et tout
  fichier `.json`, `.sql`, `.gz`, `.md`. Sans lui, `data/details.json` et le
  schéma de la base se téléchargeraient par simple URL.
- **Les scripts `bin/`** refusent déjà le contexte web (403 si `PHP_SAPI`
  n'est pas `cli`) — deuxième barrière indépendante de la première.
- **Les requêtes SQL** sont toutes préparées et paramétrées&nbsp;; l'API est en
  lecture seule, aucun `INSERT`/`UPDATE` n'est atteignable depuis le web.

Reste un point non traité, à votre appréciation&nbsp;: l'API est ouverte à tous
et sans limitation de débit. Pour un site personnel c'est sans conséquence&nbsp;;
si le trafic devenait notable, un cache HTTP sur `api/competitions.php`
suffirait.

## Vérifier que ça marche

1. `https://votre-domaine/ATHLE_COMPETITION/` — les compteurs en haut à droite
   doivent afficher **165 compétitions / 69 villes**.
2. Le menu **Épreuve / discipline** doit être peuplé (70 entrées groupées par
   famille). S'il est absent, l'index des disciplines est vide&nbsp;: le dump
   n'a pas été importé en entier.
3. `https://votre-domaine/ATHLE_COMPETITION/api/competitions.php?event=perche`
   doit renvoyer du JSON, 29 compétitions.
4. `https://votre-domaine/ATHLE_COMPETITION/data/details.json` doit renvoyer
   **404** — sinon le `.htaccess` n'est pas pris en compte par l'hébergeur.
