# Documentation — Plateforme de Signalements des Discriminations

Bienvenue dans la documentation du projet. Ce dossier `doc/` regroupe toute la documentation technique et fonctionnelle.

## 📚 Sommaire

| Document | Contenu |
|----------|---------|
| [INSTALLATION.md](INSTALLATION.md) | Prérequis, installation, lancement (XAMPP), commandes utiles |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Vision technique, couches, sécurité, base de données |
| [STRUCTURE.md](STRUCTURE.md) | Arborescence complète des fichiers et rôle de chacun |
| [API.md](API.md) | Référence de tous les endpoints de l'API REST |
| [CONNEXION-GOOGLE.md](CONNEXION-GOOGLE.md) | Configurer la connexion Google (OAuth) |
| [JURIDIQUE.md](JURIDIQUE.md) | Dispositif juridique (LCEN, RGPD, droit de réponse, modération) |
| [ROADMAP.md](ROADMAP.md) | Phases réalisées et fonctionnalités restantes |

## 🎯 En bref

Application web permettant de **signaler des situations de discrimination** dans des lieux, entreprises ou marques, de les **cartographier** et d'en tirer des **statistiques anonymisées**.

- **Backend** : PHP 8.2 (micro-framework maison) + API REST + MySQL
- **Frontend** : HTML5 / CSS3 / JavaScript (vanilla), Leaflet + OpenStreetMap
- **Sécurité** : JWT, RBAC, chiffrement AES des données sensibles, rate-limiting
- **Cadre** : compte requis pour signaler (anonymat public possible), modération, droit de réponse, RGPD

> ⚠️ Les contenus sont des **témoignages d'utilisateurs**, pas des faits juridiquement établis.

## 🚀 Démarrage rapide

```bash
php composer.phar install
cp config/.env.example config/.env       # puis générer les secrets
php database/migrate.php --seed
# Accès : http://localhost/bad_place/  (Apache XAMPP)
```

Détails dans [INSTALLATION.md](INSTALLATION.md).
