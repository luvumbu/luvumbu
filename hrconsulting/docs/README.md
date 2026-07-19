# Documentation — HR Consulting

Plateforme web de mise en relation entre **freelances** et **recruteurs**,
inspirée d'Indeed. Permet la publication d'annonces, la candidature en ligne
et la gestion centralisée des comptes.

---

## Sommaire

1. [Installation](installation.md) — Mise en place de l'environnement local
2. [Architecture technique](architecture.md) — Stack, structure des fichiers, flux d'une requête
3. [Base de données](database.md) — Schéma SQL, tables, relations
4. [Fonctionnement global](fonctionnement.md) — Parcours utilisateur par rôle
5. [Sécurité](securite.md) — Mesures de protection

---

## Vue d'ensemble en une page

### Stack technique

| Couche             | Technologie                                                  |
|--------------------|--------------------------------------------------------------|
| Serveur web        | Apache (via XAMPP)                                           |
| Langage serveur    | PHP 7+ (procédural avec PDO)                                 |
| Base de données    | MySQL / MariaDB                                              |
| Frontend           | HTML5, CSS3 (responsive), JS minimal                         |
| Sessions           | Sessions PHP natives                                         |
| Sécurité mots de passe | `password_hash()` / `password_verify()` (bcrypt)         |

### Acteurs et rôles

| Rôle           | Peut faire                                                                   |
|----------------|------------------------------------------------------------------------------|
| **Visiteur**   | Consulter la liste des annonces, voir le détail d'une annonce                |
| **Freelance**  | + Postuler à une annonce, gérer ses candidatures depuis son dashboard        |
| **Recruteur**  | + Publier/modifier/supprimer ses annonces, accepter/refuser les candidatures |
| **Admin**      | + Gérer tous les utilisateurs, toutes les annonces, toutes les candidatures  |

### Fonctionnalités principales

- Inscription / connexion / déconnexion avec sessions
- Recherche d'annonces par mot-clé, ville, type de contrat
- Page de détail d'une annonce avec coordonnées du recruteur
- Création / modification / suppression d'annonces (recruteur)
- Candidature avec message de motivation (freelance)
- Workflow de candidature : en attente → acceptée / refusée
- Dashboard personnalisé selon le rôle
- Espace administrateur avec statistiques globales
- Messages flash de feedback
- Design responsive (mobile + desktop)

### Arborescence du projet

```
hrconsulting/
├── index.php              # Page d'accueil
├── connexion.php          # Page de connexion
├── inscription.php        # Page d'inscription
├── logout.php             # Déconnexion
├── missions.php           # Liste des annonces avec recherche
├── mission.php            # Détail d'une annonce
├── poster_mission.php     # Créer / modifier une annonce (recruteur)
├── postuler.php           # Postuler à une annonce (freelance)
├── dashboard.php          # Espace utilisateur
├── admin.php              # Espace administrateur
├── promote_admin.php      # Script de promotion (à supprimer après usage)
├── bdd.php                # Connexion BDD + helpers session
├── install.sql            # Schéma SQL à importer
├── styles.css             # Feuille de style complète
├── pages/
│   ├── header.php         # Barre de navigation
│   ├── footer.php         # Pied de page
│   ├── section.php        # Contenu accueil
│   ├── link.php           # Balises <link> CSS / meta
│   ├── missions.php       # Inclus dans missions.php (liste + recherche)
│   ├── connexion.php      # Formulaire connexion + traitement
│   ├── inscription.php    # Formulaire inscription + traitement
│   └── javascript.js      # JS minimal
├── images/                # Logo, photos
└── docs/                  # Cette documentation
```

### Démarrage rapide

1. Lance XAMPP (Apache + MySQL)
2. Importe `install.sql` dans phpMyAdmin
3. Ouvre http://localhost/hrconsulting/
4. Inscris-toi → promote_admin.php pour devenir admin → supprime ce fichier

Détails complets dans [installation.md](installation.md).
