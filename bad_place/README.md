# Plateforme de Signalement des Discriminations

Application web (API REST PHP 8 + MySQL) permettant de signaler et cartographier des situations de discrimination.
Architecture découplée : un backend API unique servant le front web responsive, l'admin, l'extension Chrome et, à terme, les apps mobiles.

> ⚠️ Les signalements sont des **témoignages d'utilisateurs**, pas des faits juridiquement établis.

---

## Stack

- **Backend** : PHP 8.2, micro-framework maison (routeur + middlewares + MVC léger), Composer
- **Base de données** : MySQL 8 (InnoDB, utf8mb4)
- **Auth** : JWT (access + refresh), RBAC (visitor / member / moderator / admin)
- **Front** : HTML5 / CSS3 / JS vanilla (à venir), Leaflet + OSM, Chart.js
- **Sécurité** : CORS maîtrisé, rate-limiting, chiffrement AES-256-GCM des données sensibles (RGPD)

---

## Installation (XAMPP)

```bash
# 1. Dépendances
php composer.phar install

# 2. Configuration
cp config/.env.example config/.env
# générer les secrets :
php -r "echo bin2hex(random_bytes(32));"   # -> JWT_SECRET
php -r "echo bin2hex(random_bytes(32));"   # -> APP_ENCRYPTION_KEY

# 3. Base de données (démarrer MySQL dans XAMPP au préalable)
php database/migrate.php --seed            # crée la base + tables + référentiels
# ou pour repartir de zéro :
php database/migrate.php --fresh --seed
```

### Lancer en développement

```bash
# Serveur intégré PHP
php -S localhost:8000 -t public public/index.php
# -> http://localhost:8000/api/v1/health

# OU via Apache XAMPP : http://localhost/bad_place/api/v1/health
# (mettre APP_BASE_PATH=/bad_place dans config/.env)
```

### Compte administrateur par défaut

`admin@badplace.local` / `Admin1234!` — **à changer immédiatement**.

---

## Structure

```
public/        Racine web (front controller index.php, .htaccess, assets, app front)
api/           Définition des routes REST (routes.php)
src/
  Core/        Router, Request, Response, Database, Jwt, Crypto, Validator, App, Config
  Controllers/ Contrôleurs HTTP
  Middleware/  Cors, RateLimit, Authenticate, RequireRole
  Services/    Logique métier (géocodage, médias, stats, notifications…)
  Models/ Repositories/
admin/         Interface d'administration
storage/       Uploads (media, thumbnails), logs, cache
database/
  migrations/  Schéma SQL versionné
  seeds/       Référentiels (catégories, motifs, types) + admin
  migrate.php  Runner de migrations & seeds
extension-chrome/  Source de l'extension (Manifest V3)
config/        .env, config.php
```

---

## API (v1)

| Méthode | Endpoint            | Description                    | Statut   |
|---------|---------------------|--------------------------------|----------|
| GET     | `/api/v1/health`    | État du service + BDD          | ✅ actif |

Le reste de l'API (auth, signalements, carte, stats, modération…) est ajouté au fil des phases.

---

## Feuille de route

1. ✅ **Socle** — structure, DB + migrations/seeds, core (routeur, DB, JWT, middlewares), config
2. Signalements (CRUD, entités, médias, géocodage)
3. Consultation publique (liste, détail, recherche, filtres)
4. Carte interactive (Leaflet, niveaux 🟢🟠🔴, cluster, heatmap)
5. Social (commentaires, votes, signalement d'abus)
6. Comptes (email + Google/Apple, notifications, abonnements)
7. Administration / modération + audit + droit de réponse
8. Statistiques + exports CSV/PDF
9. RGPD (export/suppression, consentements, clauses)
10. Extension Chrome + page de téléchargement
11. Responsive / performance / sécurité finale

---

## Format des réponses API

```json
// Succès
{ "success": true, "data": { ... }, "meta": { ... } }

// Erreur
{ "success": false, "message": "…", "code": "VALIDATION_ERROR", "errors": { ... } }
```
