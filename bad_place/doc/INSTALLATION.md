# Installation & lancement

## Prérequis

- **XAMPP** (ou équivalent) avec :
  - PHP ≥ 8.1 (testé sur 8.2.12)
  - MySQL / MariaDB
  - Apache avec `mod_rewrite` activé
- Extensions PHP : `pdo_mysql`, `mbstring`, `openssl`, `gd`, `fileinfo`, `curl` (toutes présentes par défaut dans XAMPP)
- **Composer** (fourni en local via `composer.phar`)

## 1. Dépendances

```bash
php composer.phar install
```

Installe : `firebase/php-jwt` (^7.0), `phpmailer/phpmailer`, `vlucas/phpdotenv`, `ramsey/uuid`.

## 2. Configuration

```bash
cp config/.env.example config/.env
```

Générer les secrets et les reporter dans `config/.env` :

```bash
php -r "echo bin2hex(random_bytes(32));"   # -> JWT_SECRET
php -r "echo bin2hex(random_bytes(32));"   # -> APP_ENCRYPTION_KEY
```

Variables importantes :

| Variable | Rôle |
|----------|------|
| `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Connexion MySQL |
| `JWT_SECRET`, `APP_ENCRYPTION_KEY` | Sécurité (à générer) |
| `REPORTS_AUTO_PUBLISH` | `true` en dev (publication immédiate), `false` en prod (modération) |
| `APP_DEBUG` | `true` en dev, `false` en prod |
| `CORS_ALLOWED_ORIGINS` | Origines autorisées (ajouter l'ID de l'extension Chrome) |
| `GOOGLE_CLIENT_ID` | Connexion Google (optionnel) — voir [CONNEXION-GOOGLE.md](CONNEXION-GOOGLE.md) |
| `ZONE_MEDIUM_AT` / `ZONE_HIGH_AT` | Seuils de vigilance de zone (par ville) |

## 3. Base de données

Démarrer **MySQL** dans le panneau XAMPP, puis :

```bash
php database/migrate.php --seed          # crée la base, les tables, les référentiels + un admin
# ou repartir de zéro (destructif) :
php database/migrate.php --fresh --seed
```

Résultat attendu : 16 groupes, 102 catégories, 16 motifs, 13 types de discrimination + compte admin.

## 4. Lancement

### Via Apache (XAMPP) — recommandé

Démarrer **Apache** + **MySQL** dans XAMPP, puis ouvrir :

```
http://localhost/bad_place/
```

Le sous-dossier est **détecté automatiquement** (pas besoin de configurer `APP_BASE_PATH`).

### Via le serveur PHP intégré

```bash
php -S localhost:8000 -t public public/index.php
# http://localhost:8000/
```

## 5. Comptes de test

| Rôle | Identifiants |
|------|--------------|
| Administrateur | `admin@badplace.local` / `Admin1234!` |
| Membre (créé pendant les tests) | `test@example.com` / `MotDePasse123` |

> ⚠️ Changez ces identifiants avant toute mise en production.

## Commandes utiles

```bash
php database/migrate.php               # applique les migrations en attente
php database/migrate.php --seed        # + seeders
php database/migrate.php --fresh --seed # réinitialise tout
php composer.phar install              # dépendances
```
