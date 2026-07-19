# 🥚 Tamagotchi

Jeu de créature virtuelle — **PHP (API REST) + JavaScript + MySQL**.

## 🚀 Démarrage rapide

1. **Base de données** : ouvre phpMyAdmin (`http://localhost/phpmyadmin`) et importe
   `database/schema.sql` (ça crée la base `tamagotchi` et les tables).
2. **Config** : vérifie les identifiants BDD dans `config/config.php`
   (par défaut XAMPP : user `root`, sans mot de passe).
3. **Lancer** : démarre Apache + MySQL dans XAMPP, puis ouvre :
   - Le jeu : `http://localhost/tamagotchi/public/`
   - Test API : `http://localhost/tamagotchi/api/ping`

## 📁 Architecture

Voir **`docs/ARCHITECTURE.md`** pour le détail des couches et du cycle d'une requête.

Résumé :
- `public/` → front (HTML/CSS/JS), seul dossier visible du navigateur
- `api/` → point d'entrée des requêtes JSON
- `src/` → cœur PHP en couches (Core, Models, Repositories, Services, Controllers…)
- `database/` → schéma SQL, migrations, données de départ
- `config/` → réglages BDD + équilibrage du gameplay

## ✅ État actuel (squelette)

- [x] Structure de dossiers en couches
- [x] Noyau PHP : autoloader, routeur, connexion PDO, réponses JSON
- [x] Route de test `/api/ping`
- [x] Logique de gameplay de base (`PetService` : temps, faim, évolution, mort)
- [x] Front de démo (jauges + boutons + connexion API)
- [ ] Controllers + Repositories à câbler
- [ ] Authentification joueurs
- [ ] Sprites/animations de la créature

---

## 🧰 Outils qu'on pourrait ajouter (roadmap)

### Développement / qualité
| Outil | Pourquoi |
|-------|----------|
| **Composer** | gestion des dépendances PHP + autoloading standard (remplacerait notre autoloader maison) |
| **phpdotenv** | sortir les mots de passe BDD du code (fichier `.env`) |
| **PHPUnit** | tests automatisés de la logique de jeu (`PetService`) |
| **PHP_CodeSniffer / PHP-CS-Fixer** | style de code cohérent |
| **Git** | versionner le projet (ce dossier n'est pas encore un dépôt) |

### Fonctionnalités de jeu
| Outil / feature | Pourquoi |
|-----------------|----------|
| **JWT (firebase/php-jwt)** | authentification par token pour l'API |
| **Cron (Windows Task Scheduler)** | faire vieillir les créatures même hors ligne |
| **WebSocket (Ratchet) ou SSE** | temps réel : voir la créature bouger en direct |
| **Système de boutique + monnaie** | acheter nourriture/jouets (tables `items`/`inventory` déjà prévues) |
| **Mini-jeux** | gagner de la monnaie et du bonheur |
| **Notifications navigateur** | prévenir quand la créature a faim |

### Front / UX
| Outil | Pourquoi |
|-------|----------|
| **Vite + un framework (Vue/React)** | si le front grossit, remplacer le JS vanilla |
| **Sprites animés (aseprite / canvas)** | animations de la créature selon son humeur |
| **Howler.js** | gestion propre des sons |
| **PWA (manifest + service worker)** | installer le jeu comme une app mobile |

### Déploiement
| Outil | Pourquoi |
|-------|----------|
| **Docker** | environnement identique partout (PHP + MySQL) |
| **GitHub Actions** | lancer les tests automatiquement à chaque push |

> 💡 Conseil : ne pas tout ajouter d'un coup. Ordre recommandé →
> **Git → Composer + .env → Controllers/Repositories → Auth → Boutique → Cron → temps réel.**
