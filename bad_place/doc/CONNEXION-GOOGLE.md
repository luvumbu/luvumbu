# Connexion Google (OAuth)

La plateforme permet la connexion via **Google Identity Services** (bouton « Se connecter avec Google »), en plus de l'email/mot de passe.

## Fonctionnement (résumé technique)

1. Le front affiche le bouton Google si `GOOGLE_CLIENT_ID` est configuré (`GET /api/v1/auth/providers`).
2. L'utilisateur se connecte ; Google renvoie un **ID token** (JWT) directement au navigateur.
3. Le front l'envoie à `POST /api/v1/auth/google`.
4. Le backend **vérifie la signature** de l'ID token via les clés publiques Google (cache 1 h), contrôle l'émetteur et l'audience (le `client_id`).
5. Il **retrouve ou crée** le compte (liaison par email si le compte existe déjà), enregistre le lien dans `oauth_accounts`, puis ouvre une session JWT classique.

> ℹ️ Ce mode **n'utilise pas** le *client secret* Google (`GOCSPX-…`). Seul l'**ID client** (`…apps.googleusercontent.com`) est nécessaire, et il est **public**.

## Configuration (une seule fois)

### 1. Créer l'ID client OAuth
Console : **https://console.cloud.google.com/apis/credentials**

- Créer un **projet** (ex. `Bokonzi`).
- Configurer l'**écran de consentement OAuth** (type *Externe*, nom de l'app, email de contact).
- **+ Créer des identifiants → ID client OAuth → Application Web**.

### 2. Origines JavaScript autorisées
Le **domaine seul**, sans chemin (`/bad_place`, `/public` interdits) :

```
http://localhost              # tests locaux (XAMPP)
https://bokonzi.com           # production
https://www.bokonzi.com       # production (www)
```

### 3. URI de redirection autorisés
**Laisser vide** — non utilisé par Google Identity Services.

### 4. Renseigner la clé
Copier l'**ID client** (`…apps.googleusercontent.com`) dans `config/.env` :

```
GOOGLE_CLIENT_ID=<VOTRE_ID_CLIENT>.apps.googleusercontent.com
```

Le bouton Google apparaît alors automatiquement sur les pages **connexion** et **inscription**.

## Points d'attention

| Sujet | Détail |
|-------|--------|
| **HTTPS** | Obligatoire en production (Google refuse le `http://` hors `localhost`). `bokonzi.com` doit avoir un certificat SSL. |
| **Même client pour local + prod** | Un seul ID client suffit : les origines `localhost` et `bokonzi.com` sont toutes deux déclarées. |
| **Mode « Test »** | Si l'écran de consentement est en *Test*, ajouter les adresses Gmail autorisées dans **Utilisateurs tests**, sinon « Accès bloqué ». |
| **Chemin vs origine** | L'appli peut vivre dans un sous-dossier (`/bad_place/`) ; l'origine Google reste le domaine seul. |
| **Secret inutile** | Ne pas renseigner de *client secret* : non requis ici. |

## Fichiers concernés

- Backend : `src/Controllers/AuthController.php` (`google()`, `providers()`, vérification JWK)
- Routes : `POST /auth/google`, `GET /auth/providers` (`api/routes.php`)
- Front : `public/assets/js/core.js` (`mountGoogle`), `public/pages/connexion.html`, `public/pages/inscription.html`
- Config : `config/.env` → `GOOGLE_CLIENT_ID`
- Table : `oauth_accounts` (liaison compte ↔ Google)
