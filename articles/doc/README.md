# Documentation technique — Mon Blog

Documentation complète du projet : architecture, installation, fonctionnement interne, déploiement, sécurité.

## Table des matières

| Fichier | Contenu |
|---|---|
| [01-architecture.md](01-architecture.md) | Vue d'ensemble : composants, flux de données, structure du repo, mapping local ↔ serveur |
| [02-installation.md](02-installation.md) | Installation locale (XAMPP) + déploiement sur Hostinger (FTP, hPanel, base de données) |
| [03-database.md](03-database.md) | Schéma MySQL complet : tables, colonnes, types, foreign keys, contraintes |
| [04-api.md](04-api.md) | Référence API JSON : endpoints, paramètres, formats de réponse, exemples cURL |
| [05-deployment.md](05-deployment.md) | Pipeline GitHub Actions : workflow YAML expliqué ligne par ligne, secrets, exclusions |
| [06-sync.md](06-sync.md) | Système de synchronisation local → serveur : clés à usage unique, dump SQL, zip uploads |
| [07-mobile-app.md](07-mobile-app.md) | Architecture de la PWA mobile : manifest, service worker, système de mise à jour, icônes |
| [08-admin.md](08-admin.md) | Panel d'administration : pages, permissions, personnalisation landing, import/export JSON |
| [09-security.md](09-security.md) | Modèle de sécurité : auth, CSRF, tokens, sync keys, validations upload, hashing |
| [10-troubleshooting.md](10-troubleshooting.md) | Problèmes fréquents et solutions : déploiement, sync, cache, icônes PWA, BDD |
| [11-nouveautes.md](11-nouveautes.md) | Récap par thèmes des dernières nouveautés : visibilité, vues par IP, actions app mobile, qualité photo + visionneuse, sync upsert + envoi par article, service worker |
| [12-questionnaires.md](12-questionnaires.md) | Quiz : les 3 réglages admin (affichage, effet de transition, résultat en direct ou à la fin), connexion obligatoire pour voir son score, code, serveur, vérifications, limites |

## Conventions de cette documentation

- Les blocs `bash` sont des commandes shell (Git Bash sur Windows, ou Bash/Zsh sur macOS/Linux)
- Les blocs `php` montrent du code PHP du projet
- Les blocs `sql` sont des requêtes MySQL
- Les blocs `yaml` sont du YAML (GitHub Actions)
- Les chemins commencent par `Blog/` = racine du repo Git (= `C:\xampp\htdocs\Blog\` sur le poste de dev)
- Les chemins commençant par `/public_html/` désignent la racine serveur Hostinger

## Stack technique résumé

| Couche | Techno |
|---|---|
| Serveur web | Apache (XAMPP local / Hostinger prod) |
| Backend | PHP 7+ (compatible 8.x) sans framework, PDO MySQL |
| Base de données | MySQL 5.7+ / MariaDB 10.3+ |
| Frontend web | HTML server-rendered + CSS vanilla, pas de framework JS |
| Frontend mobile | PWA HTML + JS vanilla, Service Worker minimal |
| Auth web | Sessions PHP + cookies + bcrypt |
| Auth API | Tokens opaques 30 jours (table `api_tokens`) |
| Déploiement | GitHub Actions → FTPS Hostinger |
| Sync | PHP → cURL multipart HTTPS, clés one-shot fichier JSON |

## Démarrage rapide

1. Lis [01-architecture.md](01-architecture.md) pour comprendre où vit quoi
2. Suis [02-installation.md](02-installation.md) pour faire tourner le projet en local
3. Consulte [05-deployment.md](05-deployment.md) pour mettre en ligne
4. Quand tu auras besoin de pousser ton local vers la prod : [06-sync.md](06-sync.md)
