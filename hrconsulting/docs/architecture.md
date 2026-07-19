# Architecture technique

## Vue d'ensemble

HR Consulting est une application **PHP procédurale multi-pages**, sans
framework, utilisant PDO pour l'accès aux données et les sessions PHP natives
pour l'authentification.

```
┌────────────┐    HTTP     ┌─────────────┐     PDO      ┌──────────┐
│ Navigateur │ ◄────────►  │ Apache + PHP│ ◄─────────►  │  MySQL   │
└────────────┘             └─────────────┘              └──────────┘
                                  │
                                  ▼
                           Sessions PHP
                           (cookies HTTPOnly)
```

## Stack détaillée

| Élément              | Technologie / Version |
|----------------------|-----------------------|
| Serveur HTTP         | Apache 2.4 (XAMPP)    |
| Langage              | PHP 7.4+              |
| Accès BDD            | PDO MySQL (prepared statements)  |
| SGBD                 | MySQL 5.7 / MariaDB 10+ |
| Frontend             | HTML5, CSS3 (Flexbox + Grid) |
| JavaScript           | Minimal (≈ confirmations natives) |
| Sessions             | Cookies de session PHP |
| Hashing mot de passe | bcrypt via `password_hash()` |

## Structure des fichiers

### Racine — pages publiques

Chaque fichier `.php` à la racine est une **page complète** avec `<head>`,
`<body>`, header, contenu, footer.

| Fichier                | Rôle                                                    | Accès                  |
|------------------------|---------------------------------------------------------|------------------------|
| `index.php`            | Accueil avec hero, recherche, dernières annonces        | Public                 |
| `missions.php`         | Liste des annonces filtrable                            | Public                 |
| `mission.php`          | Détail d'une annonce (paramètre GET `id`)               | Public                 |
| `connexion.php`        | Formulaire de connexion                                 | Public                 |
| `inscription.php`      | Formulaire d'inscription                                | Public                 |
| `logout.php`           | Détruit la session, redirige vers `index.php`           | Connecté               |
| `poster_mission.php`   | Créer / éditer une annonce (param GET `edit`)           | Recruteur uniquement   |
| `postuler.php`         | Formulaire de candidature (param GET `id`)              | Freelance uniquement   |
| `dashboard.php`        | Espace personnel (annonces ou candidatures)             | Connecté               |
| `admin.php`            | Tableau de bord administrateur                          | Admin uniquement       |
| `promote_admin.php`    | Script ponctuel de promotion (à supprimer)              | Public (puis effacer)  |

### `bdd.php` — cœur technique

Inclus au début de chaque page via `require_once 'bdd.php'`. Fait :

1. `session_start()` — initialise les sessions
2. Connexion PDO selon l'environnement (local vs production)
3. Définit des helpers globaux :

```php
est_connecte()          // l'utilisateur est-il loggé ?
est_recruteur()         // est-ce un recruteur ?
est_freelance()         // est-ce un freelance ?
est_admin()             // est-ce un admin ?
utilisateur_actuel()    // retourne tableau [id, name, email, jesuis]
flash_set($type, $msg)  // mémorise un message à afficher après redirect
flash_get()             // récupère et vide les messages flash
```

### `pages/` — fragments inclus

Pas de pages autonomes, ces fichiers sont `include`'d depuis la racine :

| Fichier                    | Rôle                                                 |
|----------------------------|------------------------------------------------------|
| `pages/link.php`           | Balises `<link>` et `<meta charset>`                 |
| `pages/header.php`         | Topbar + nav principale + zone messages flash        |
| `pages/footer.php`         | Pied de page                                         |
| `pages/section.php`        | Contenu de l'accueil (hero, qui sommes-nous, derniers postes) |
| `pages/missions.php`       | Liste des annonces + barre de recherche              |
| `pages/connexion.php`      | Formulaire connexion + traitement POST               |
| `pages/inscription.php`    | Formulaire inscription + traitement POST             |
| `pages/javascript.js`      | Vide (legacy)                                        |

### `styles.css`

Feuille de style unique avec :

- Reset basique (`box-sizing: border-box`)
- Variables visuelles (palette bleu Indeed-like : `#2557a7`)
- Composants : `.btn`, `.badge`, `.mission-card`, `.alert`, `.dash-table`
- Responsive via `@media (max-width: 700px)`

### `install.sql`

Script d'installation idempotent :
- `CREATE DATABASE IF NOT EXISTS`
- `DROP TABLE IF EXISTS` puis recréation
- Définition des tables et contraintes

## Cycle de vie d'une requête

Exemple : un visiteur clique sur "Voir une annonce" (`mission.php?id=3`)

```
1. Navigateur → GET /hrconsulting/mission.php?id=3
2. Apache → invoque PHP
3. mission.php :
   ├─ require_once 'bdd.php'
   │  ├─ session_start()
   │  └─ ouvre PDO
   ├─ valide $_GET['id'] (cast int)
   ├─ prepare/execute SELECT … WHERE mission_id = :id
   ├─ si non trouvé : flash_set('error') + redirect 302
   ├─ si freelance connecté : check candidature existante
   ├─ inclut pages/link.php
   ├─ inclut pages/header.php (affiche flash, nav dynamique)
   ├─ rend le HTML du détail
   └─ inclut pages/footer.php
4. PHP renvoie HTML → Apache → Navigateur
```

## Flux d'authentification

```
┌─────────────────┐                              ┌────────────────┐
│ Form connexion  │ ──── POST email + mdp ────► │ pages/connexion.php │
└─────────────────┘                              └────────────────┘
                                                          │
                                                          ▼
                                            SELECT * FROM users WHERE email = ?
                                                          │
                                                          ▼
                                            password_verify($input, $hash)
                                                  │             │
                                                OK│           KO│
                                                  ▼             ▼
                                      $_SESSION['user_id']    flash error
                                            = $user['user_id']
                                                  │
                                                  ▼
                                          header('Location: index.php')
```

À chaque requête suivante, le cookie de session est envoyé → PHP retrouve
`$_SESSION` → les helpers `est_connecte()` etc. fonctionnent.

## Pattern POST → Redirect → GET (PRG)

Tous les formulaires d'écriture (création annonce, candidature, suppression…)
suivent ce pattern :

1. POST traité (INSERT / UPDATE / DELETE)
2. `flash_set('success', '...')` mémorise un message
3. `header('Location: page.php'); exit;` redirige
4. La page cible affiche le flash via `flash_get()`

Avantage : pas de double-soumission en cas de F5, URL propre.

## Gestion des erreurs

- PDO configuré avec `PDO::ERRMODE_EXCEPTION` → les erreurs SQL deviennent des exceptions
- Catch global dans `bdd.php` au moment de la connexion uniquement (`die(...)`)
- Pas de try/catch verbeux dans le code applicatif (les erreurs SQL restent
  des bugs visibles pendant le dev)
- En production, ajouter un handler global et masquer les détails d'erreur

## Conventions de code

- **Snake_case** pour noms de variables et colonnes BDD (`user_id`, `mission_titre_mission`)
- **Préfixage des colonnes** par leur table (`user_*`, `mission_*`, `candidature_*`)
- **Toutes les sorties HTML** passent par `htmlspecialchars()` pour échapper
- **Toutes les requêtes** utilisent des paramètres nommés `:param` (jamais de concaténation)
- **Tous les casts entiers** explicites sur les IDs : `(int)$_GET['id']`
- **Indentation** : 4 espaces, accolades sur même ligne

## Limitations connues

- Pas de framework → routage manuel via noms de fichiers
- Pas de migrations → modifications de schéma à la main
- Pas de tests automatisés
- Pas de pagination (toutes les annonces chargées d'un coup)
- Pas d'upload de CV / fichiers (la candidature est texte seul)
- Pas de récupération de mot de passe oublié
- Pas de protection CSRF (à ajouter pour la production)
