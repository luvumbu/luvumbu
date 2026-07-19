# Base de données

## Modèle conceptuel

```
┌──────────────┐  1      N  ┌──────────────┐  1      N  ┌─────────────────┐
│    users     │ ─────────► │   mission    │ ─────────► │   candidature   │
│              │   publie   │              │   reçoit   │                 │
└──────────────┘            └──────────────┘            └─────────────────┘
       │                                                          ▲
       │                          1                              N│
       └──────────────────────────────────────────────────────────┘
                              postule
```

- Un **user** (recruteur) peut publier **N missions**
- Une **mission** peut recevoir **N candidatures**
- Un **user** (freelance) peut envoyer **N candidatures**
- Contrainte d'unicité : un même freelance ne peut postuler **qu'une fois** par mission

## Tables

### `users`

Stocke tous les comptes : freelances, recruteurs, administrateurs.

| Colonne          | Type                              | Description                                       |
|------------------|-----------------------------------|---------------------------------------------------|
| `user_id`        | INT AUTO_INCREMENT PK             | Identifiant unique                                |
| `user_name`      | VARCHAR(50) NOT NULL              | Nom complet ou raison sociale                     |
| `user_email`     | VARCHAR(100) NOT NULL UNIQUE      | Email (sert d'identifiant de connexion)           |
| `user_password`  | VARCHAR(255) NOT NULL             | Hash bcrypt (`password_hash` PHP)                 |
| `user_jesuis`    | ENUM('freelance','recruteur')     | Rôle métier de l'utilisateur                      |
| `user_telephone` | VARCHAR(20) NULL                  | Téléphone (optionnel)                             |
| `user_ville`     | VARCHAR(50) NULL                  | Ville (optionnel)                                 |
| `user_update`    | DATETIME DEFAULT CURRENT_TIMESTAMP| Date d'inscription                                |
| `user_is_admin`  | TINYINT(1) DEFAULT 0              | 1 = administrateur, 0 = utilisateur normal        |

**Notes** :
- L'unicité de `user_email` empêche les inscriptions en double
- `user_is_admin` est **indépendant** de `user_jesuis` (un admin peut être freelance ou recruteur)
- Les mots de passe ne sont **jamais** stockés en clair

### `mission`

Annonces publiées par les recruteurs.

| Colonne                   | Type                              | Description                                  |
|---------------------------|-----------------------------------|----------------------------------------------|
| `mission_id`              | INT AUTO_INCREMENT PK             | Identifiant unique                           |
| `mission_id_user`         | INT NOT NULL (FK → users)         | Recruteur propriétaire                       |
| `mission_titre_mission`   | VARCHAR(150) NOT NULL             | Titre du poste                               |
| `mission_description`     | TEXT NOT NULL                     | Description longue                           |
| `mission_technologie`     | VARCHAR(255) NULL                 | Technologies (CSV : "Java, Spring")          |
| `mission_profil`          | VARCHAR(100) NULL                 | Profil recherché                             |
| `mission_niveau_etudes`   | VARCHAR(100) NULL                 | Niveau d'études (Bac+3, Bac+5…)              |
| `mission_ville`           | VARCHAR(100) NOT NULL             | Localisation                                 |
| `mission_type_contrat`    | VARCHAR(50) NULL                  | CDI, CDD, Freelance, Stage, Alternance       |
| `mission_salaire`         | VARCHAR(50) NULL                  | Salaire (texte libre : "40-50K€", "500€/j")  |
| `mission_date_up`         | DATETIME DEFAULT CURRENT_TIMESTAMP| Date de publication                          |

**Index** : `idx_user` sur `mission_id_user` (pour `WHERE mission_id_user = ?` rapide).

### `candidature`

Postulations envoyées par les freelances aux annonces.

| Colonne                    | Type                                       | Description                          |
|----------------------------|--------------------------------------------|--------------------------------------|
| `candidature_id`           | INT AUTO_INCREMENT PK                      | Identifiant unique                   |
| `candidature_id_mission`   | INT NOT NULL (FK → mission)                | Annonce visée                        |
| `candidature_id_user`      | INT NOT NULL (FK → users)                  | Freelance postulant                  |
| `candidature_message`      | TEXT NOT NULL                              | Message de motivation                |
| `candidature_statut`       | ENUM('en_attente','acceptee','refusee')    | Suivi par le recruteur               |
| `candidature_date`         | DATETIME DEFAULT CURRENT_TIMESTAMP         | Date d'envoi                         |

**Index** :
- `unique_candidature` sur `(candidature_id_mission, candidature_id_user)` :
  empêche un freelance de postuler deux fois à la même annonce
- `idx_mission`, `idx_user` pour les jointures rapides

## Relations et intégrité

Les clés étrangères ne sont **pas** déclarées au niveau SQL (moteur InnoDB
mais pas de `FOREIGN KEY` explicite). L'intégrité est gérée applicativement :

- Quand un **utilisateur est supprimé** (via admin), le code supprime :
  1. Ses candidatures (`candidature_id_user`)
  2. Les candidatures sur ses annonces (jointure)
  3. Ses annonces (`mission_id_user`)
  4. Le user lui-même

- Quand une **annonce est supprimée**, ses candidatures sont supprimées en cascade applicative

**Évolution recommandée** : ajouter des contraintes `FOREIGN KEY ... ON DELETE CASCADE`
pour déléguer ce travail au SGBD.

## Requêtes types

### Liste des annonces (avec recherche)

```sql
SELECT m.*, u.user_name AS recruteur_nom
FROM mission m
LEFT JOIN users u ON u.user_id = m.mission_id_user
WHERE m.mission_titre_mission LIKE :q
   OR m.mission_description LIKE :q
   OR m.mission_technologie LIKE :q
ORDER BY m.mission_date_up DESC
```

### Annonces d'un recruteur + nombre de candidatures

```sql
SELECT m.*,
       (SELECT COUNT(*) FROM candidature c
        WHERE c.candidature_id_mission = m.mission_id) AS nb_candidatures
FROM mission m
WHERE m.mission_id_user = :uid
ORDER BY m.mission_date_up DESC
```

### Candidatures reçues par un recruteur

```sql
SELECT c.*, m.mission_titre_mission,
       u.user_name AS freelance_nom, u.user_email AS freelance_email
FROM candidature c
JOIN mission m ON m.mission_id = c.candidature_id_mission
JOIN users u ON u.user_id = c.candidature_id_user
WHERE m.mission_id_user = :uid
ORDER BY c.candidature_date DESC
```

### Statistiques globales (admin)

```sql
SELECT COUNT(*) FROM users;                              -- total comptes
SELECT COUNT(*) FROM users WHERE user_jesuis = 'recruteur';
SELECT COUNT(*) FROM users WHERE user_jesuis = 'freelance';
SELECT COUNT(*) FROM users WHERE user_is_admin = 1;
SELECT COUNT(*) FROM mission;
SELECT COUNT(*) FROM candidature;
```

## Évolutions possibles du schéma

| Besoin                              | Modification                                                       |
|-------------------------------------|--------------------------------------------------------------------|
| Upload de CV                        | Ajouter `user_cv_path VARCHAR(255)` + dossier `uploads/`           |
| Récupération mot de passe           | Table `password_reset(token, user_id, expires_at)`                 |
| Annonces favoris                    | Table `favoris(user_id, mission_id)`                               |
| Messagerie recruteur/freelance      | Table `messages(from_id, to_id, content, sent_at)`                 |
| Catégories de poste                 | Table `categorie` + FK depuis `mission`                            |
| Historique de candidature           | Table `candidature_log(candidature_id, statut, date, par_qui)`     |
| Vérification email                  | Colonne `user_email_verified TINYINT(1)` + table de tokens         |
