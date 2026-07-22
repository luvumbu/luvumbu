# Référence de l'API REST

Base : `/api/v1` — Toutes les réponses sont en JSON.

**Authentification** : header `Authorization: Bearer <access_token>` pour les endpoints protégés.

Légende : 🔓 public · 🔒 authentification requise · 🔓/🔒 optionnelle (comportement enrichi si connecté)

---

## Santé & référentiels

| Méthode | Endpoint | Accès | Description |
|---------|----------|-------|-------------|
| GET | `/health` | 🔓 | État du service + base de données |
| GET | `/meta/overview` | 🔓 | Compteurs (signalements, catégories, motifs, types) |
| GET | `/categories` | 🔓 | Groupes + sous-catégories |
| GET | `/motifs` | 🔓 | Liste des motifs de discrimination |
| GET | `/discrimination-types` | 🔓 | Liste des types de discrimination |

## Authentification

| Méthode | Endpoint | Accès | Description |
|---------|----------|-------|-------------|
| POST | `/auth/register` | 🔓 | Inscription. Corps : `display_name`, `email`, `password` |
| POST | `/auth/login` | 🔓 | Connexion. Corps : `email`, `password` |
| POST | `/auth/refresh` | 🔓 | Renouvelle la session. Corps : `refresh_token` |
| GET | `/auth/me` | 🔒 | Utilisateur courant |
| POST | `/auth/logout` | 🔒 | Déconnexion (révoque le refresh token) |

Réponse d'authentification : `{ user, access_token, refresh_token, token_type, expires_in }`.

## Signalements

| Méthode | Endpoint | Accès | Description |
|---------|----------|-------|-------------|
| GET | `/reports` | 🔓/🔒 | Liste des signalements publiés (filtres : `q`, `city`, `category_id`, `page`, `per_page`) |
| POST | `/reports` | 🔒 | Créer un signalement (`multipart/form-data`) |
| GET | `/reports/{uuid}` | 🔓/🔒 | Détail d'un signalement |

### Champs de création (`POST /reports`)

| Champ | Obligatoire | Note |
|-------|:---:|------|
| `org_name` | ✔ | Nom du lieu / entreprise / marque |
| `org_type` | | `place`, `company`, `brand`, `online_service`, `other` |
| `category_id` | ✔ | Sous-catégorie |
| `description` | ✔ | 20 caractères minimum |
| `motifs[]` | ✔ | Au moins un motif |
| `discrimination_types[]` | ✔ | Au moins un type |
| `address`, `city`, `postal_code` | | Géocodage automatique si pas de coordonnées |
| `latitude`, `longitude` | | Fournis par la géolocalisation ou l'autocomplétion |
| `incident_date`, `incident_time` | | |
| `is_anonymous` | | `1` = publication anonyme |
| `media[]` | | Photos, vidéos, documents |

## Carte

| Méthode | Endpoint | Accès | Description |
|---------|----------|-------|-------------|
| GET | `/map/points` | 🔓 | Points par entité (filtres : `bbox`, `category_id`, `city`, `level`). Inclut `report_uuid` (dernier témoignage) |
| GET | `/map/zones` | 🔓 | **Vigilance de zone** : agrégation par ville (total, nb de lieux, centre, niveau 🟢🟡🔴) |
| GET | `/map/heatmap` | 🔓 | Données de carte thermique `[[lat, lng, intensité], …]` |

## Adresses

| Méthode | Endpoint | Accès | Description |
|---------|----------|-------|-------------|
| GET | `/geo/search?q=…` | 🔒 | Autocomplétion d'adresses (proxy Nominatim) |

## Juridique

| Méthode | Endpoint | Accès | Description |
|---------|----------|-------|-------------|
| POST | `/contestations` | 🔓 | Droit de réponse (LCEN). Email chiffré au repos |
| POST | `/reports/{uuid}/abuse` | 🔓/🔒 | Signaler un contenu illicite (`reason`, `details`) |

## Pièces jointes

| Méthode | Endpoint | Accès | Description |
|---------|----------|-------|-------------|
| GET | `/media/{uuid}` | 🔓/🔒 | Sert un fichier (contrôle d'accès selon statut du signalement) |
| GET | `/media/{uuid}/thumb` | 🔓/🔒 | Vignette d'une image |

---

## Codes d'erreur

| Code HTTP | `code` | Signification |
|-----------|--------|---------------|
| 401 | `UNAUTHORIZED` | Jeton manquant ou invalide |
| 403 | `FORBIDDEN` | Privilèges insuffisants |
| 404 | `NOT_FOUND` | Ressource introuvable |
| 422 | `VALIDATION_ERROR` | Données invalides (détail dans `errors`) |
| 429 | `RATE_LIMITED` | Trop de requêtes |
| 503 | `DB_UNAVAILABLE` | Base de données indisponible |
