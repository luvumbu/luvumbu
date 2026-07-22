# Architecture technique

## Vision

Architecture **API REST découplée** : un backend PHP unique expose une API JSON consommée par plusieurs clients (front web, admin, extension Chrome, et demain apps mobiles).

```
   Front Web  ─────▶┌──────────────────────────────┐
   Admin      ─────▶│   API REST PHP 8.2  (JSON)   │───▶  MySQL
   Ext Chrome ─────▶│   Auth JWT · RBAC · RGPD     │───▶  /storage (fichiers)
   (Mobile+)  ─────▶└──────────────────────────────┘
```

## Couches du backend

Micro-framework maison (pas de framework lourd), fidèle au cahier des charges « PHP vanilla », déployable directement sur XAMPP.

| Couche | Fichiers | Rôle |
|--------|----------|------|
| **Bootstrap** | `public/index.php`, `src/Core/App.php` | Point d'entrée, chargement env/config, pipeline |
| **Routage** | `src/Core/Router.php`, `api/routes.php` | Routes REST, paramètres `{uuid}`, middlewares |
| **Requête/Réponse** | `src/Core/Request.php`, `Response.php` | Encapsulation HTTP, réponses JSON normalisées |
| **Middlewares** | `src/Middleware/*` | CORS, rate-limit, authentification, RBAC |
| **Contrôleurs** | `src/Controllers/*` | Logique HTTP par ressource |
| **Services** | `src/Services/*` | Métier : géocodage, médias, organisations |
| **Accès données** | `src/Core/Database.php` | PDO + transactions |
| **Sécurité** | `src/Core/Jwt.php`, `Crypto.php` | JWT, chiffrement AES-256-GCM |
| **Validation** | `src/Core/Validator.php` | Règles de validation chaînées |

## Cycle d'une requête

```
Requête HTTP
   │
   ▼
public/index.php ──▶ App::run()
   │
   ▼
Router::match()  ──▶ [handler, params, middlewares]
   │
   ▼
Pipeline middlewares  (cors → ratelimit → auth → role)
   │
   ▼
Contrôleur::méthode(Request) ──▶ Response JSON
```

## Format des réponses

```json
// Succès
{ "success": true, "data": { ... }, "meta": { ... } }

// Erreur
{ "success": false, "message": "…", "code": "VALIDATION_ERROR", "errors": { ... } }
```

## Sécurité

- **Authentification** : JWT access token (courte durée) + refresh token (révocable, stocké en base).
- **RBAC** : rôles `visitor < member < moderator < admin` (middleware `role:`).
- **Chiffrement** : données sensibles (ex. email d'une demande de droit de réponse) chiffrées AES-256-GCM au repos.
- **Pseudonymisation** : adresses IP hachées (HMAC) pour l'anti-abus sans stocker l'IP en clair.
- **Rate-limiting** : par IP et par endpoint (fenêtre glissante).
- **CORS** maîtrisé (origines déclarées + extensions `chrome-extension://`).
- **En-têtes** : `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`.
- **Fichiers** stockés hors racine web (`/storage`), servis via un contrôleur avec contrôle d'accès.

## Modèle de données (principales tables)

```
users · oauth_accounts · refresh_tokens
organizations                     (entité signalée : lieu / entreprise / marque)
category_groups → categories      (taxonomie hiérarchique)
motifs · discrimination_types     (référentiels)
reports                           (signalement)
  ├─ report_motifs (pivot)
  ├─ report_discrimination_types (pivot)
  └─ report_media (pièces jointes)
comments · votes · abuse_reports
subscriptions · notifications · push_subscriptions
moderation_actions (audit) · contestations (droit de réponse)
consents · rgpd_requests          (RGPD)
```

### L'entité `organizations`

Décision structurante : chaque signalement se rattache à une **entité réutilisable** (dédup par nom + ville/CP). Cela permet :
- la **carte agrégée** (un point = une entité),
- l'**indice de vigilance** 🟢🟡🔴 (calculé sur le nombre de témoignages publiés),
- le **droit de réponse** au niveau de l'établissement,
- les **notifications par marque**.

## Frontend

- **Multi-pages** HTML/CSS/JS vanilla. `index.html` à la racine, autres pages dans `public/pages/`.
- **`core.js`** : socle partagé (détection de base, appels API avec refresh auto, session, rendu de l'en-tête, bandeau cookies).
- **Design system** CSS avec variables, responsive mobile-first, thème clair/sombre automatique.
- **Leaflet** hébergé en local (`public/assets/vendor/leaflet`), sans CDN.
