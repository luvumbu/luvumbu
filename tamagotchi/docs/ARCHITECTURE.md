# 🥚 Tamagotchi — Architecture

Projet : jeu de créature virtuelle. Stack **PHP (API REST) + JavaScript (front)** + **MySQL**.
Conçu pour être **complet et extensible** : multi-créatures, comptes joueurs, temps réel, évolutions.

---

## 1. Vue d'ensemble (couches)

```
Navigateur (front JS)  ──HTTP/JSON──►  API PHP  ──►  Services (logique métier)
                                                   └─►  Repositories  ──►  MySQL
```

Principe : **séparation stricte** front / back. Le front ne connaît que des URLs JSON.
Le back est découpé en couches, chacune avec une seule responsabilité.

---

## 2. Arborescence

```
tamagotchi/
├── public/                  ← SEUL dossier exposé par le serveur web
│   ├── index.html           ← page unique de l'app (SPA légère)
│   ├── assets/
│   │   ├── css/             ← styles
│   │   ├── js/              ← libs tierces éventuelles
│   │   ├── img/             ← sprites, animations de la créature
│   │   └── sounds/          ← effets sonores
│   └── js/                  ← code front que TU écris
│       ├── core/            ← état du jeu, boucle de temps, sauvegarde
│       ├── ui/              ← rendu écran, boutons, barres de stats
│       └── api/             ← appels fetch vers l'API PHP
│
├── api/                     ← points d'entrée HTTP (routeur)
│   └── index.php            ← front controller : reçoit toutes les requêtes /api/*
│
├── src/                     ← cœur PHP (namespace App\)
│   ├── Core/                ← Router, Request, Response, Database (PDO)
│   ├── Config/             ← chargement config
│   ├── Models/              ← entités : Pet, User, Action, Item...
│   ├── Repositories/        ← accès BDD (1 repo par table)
│   ├── Services/            ← règles du jeu (faim, évolution, mort...)
│   ├── Controllers/         ← reçoivent la requête, appellent un service, renvoient du JSON
│   ├── Middleware/          ← auth, CORS, validation
│   └── Helpers/             ← fonctions utilitaires
│
├── database/
│   ├── schema.sql           ← création des tables
│   ├── migrations/          ← évolutions du schéma dans le temps
│   └── seeds/               ← données de départ (espèces, items...)
│
├── config/
│   └── config.php           ← BDD, constantes du jeu (vitesse de faim...)
│
├── storage/
│   └── logs/                ← journaux d'erreurs
│
├── docs/                    ← cette doc
└── tests/                   ← tests
```

---

## 3. Le cycle d'une requête (exemple : "nourrir la créature")

1. **Front** (`public/js/api/`) : `POST /api/pets/42/feed`
2. **`api/index.php`** : le routeur mappe l'URL vers `PetController::feed()`
3. **Middleware** : vérifie que le joueur est connecté (auth)
4. **Controller** : lit l'id, appelle `PetService::feed(42)`
5. **Service** : applique les règles (faim -30, bonheur +5, refuse si la créature dort)
6. **Repository** : sauvegarde le nouvel état en BDD
7. **Response** : renvoie l'état à jour en JSON → le front rafraîchit l'écran

---

## 4. Le "temps qui passe" (mécanique clé d'un Tamagotchi)

Deux approches combinées :
- **Front (temps réel visuel)** : une boucle `setInterval` fait descendre les jauges à l'écran.
- **Back (source de vérité)** : à chaque requête, le service calcule la dégradation depuis
  `last_update` (ex : "3h sans manger → faim +45"). Ça évite la triche et gère la fermeture d'onglet.

Optionnel plus tard : un **cron** côté serveur qui fait vieillir toutes les créatures même hors ligne.

---

## 5. Modèle de données (tables prévues)

| Table        | Rôle                                                        |
|--------------|-------------------------------------------------------------|
| `users`      | comptes joueurs (login, mot de passe haché)                 |
| `pets`       | créatures : nom, espèce, stats, âge, stade, statut vivant   |
| `species`    | catalogue d'espèces + arbres d'évolution                    |
| `items`      | nourriture, jouets, soins (boutique)                        |
| `inventory`  | items possédés par joueur                                   |
| `actions_log`| historique des actions (utile pour stats & debug)           |

---

## 6. Conventions

- **PHP** : namespaces `App\`, autoloading PSR-4, une classe par fichier.
- **API** : réponses JSON toujours `{ "success": bool, "data": ..., "error": ... }`.
- **Front** : pas de logique métier critique (elle est côté serveur = anti-triche).
- **Config secrète** : jamais dans le code versionné (voir `config/config.php`).
