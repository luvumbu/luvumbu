# Fonctionnement global

## Parcours par rôle

### 🌐 Visiteur (non connecté)

```
Accueil (index.php)
   ├─► Voit hero + dernières annonces
   ├─► Peut chercher → missions.php?q=...
   ├─► Clique annonce → mission.php?id=X
   │      └─► Bouton "Connectez-vous pour postuler"
   ├─► Inscription → crée un compte
   └─► Connexion → revient en utilisateur authentifié
```

**Pages accessibles** : `index.php`, `missions.php`, `mission.php`,
`connexion.php`, `inscription.php`.

**Pages protégées** (redirigent vers connexion) :
`dashboard.php`, `poster_mission.php`, `postuler.php`, `admin.php`.

---

### 👨‍💻 Freelance

```
Inscription ou Connexion
        │
        ▼
   Accueil ou missions.php
        │
        ▼
   Cherche / parcourt les annonces
        │
        ▼
   mission.php?id=X (détail)
        │
        ├─ Si pas encore postulé :
        │     └─► Bouton "Postuler à cette offre"
        │            │
        │            ▼
        │       postuler.php?id=X
        │            │
        │            └─► Écrit message de motivation (20 chars min)
        │                   │
        │                   ▼
        │              INSERT candidature → flash success → mission.php
        │
        └─ Si déjà postulé :
              └─► Affiche "Vous avez déjà postulé à cette annonce"

Dashboard (dashboard.php) :
   └─► Liste de toutes ses candidatures avec statut (en attente / acceptée / refusée)
```

**Restrictions** :
- Ne peut pas publier d'annonce (bouton invisible dans la nav)
- Ne peut postuler qu'une seule fois par annonce (contrainte SQL UNIQUE)

---

### 🏢 Recruteur

```
Inscription (choisir "Recruteur") ou Connexion
        │
        ▼
   Bouton "Publier une annonce" apparaît dans la nav bleue
        │
        ▼
   poster_mission.php
        │
        └─► Formulaire : titre, description, ville, contrat, salaire, techno, profil, études
                │
                ▼
           INSERT mission → flash success → mission.php?id=N

Dashboard (dashboard.php) :
   ├─► Section "Mes annonces"
   │       ├─► Bouton "Modifier" → poster_mission.php?edit=X
   │       └─► Bouton "Supprimer" (avec confirmation JS)
   │
   └─► Section "Candidatures reçues"
           ├─► Affiche message + email du freelance
           └─► Dropdown : en attente / acceptée / refusée → UPDATE
```

**Workflow d'une annonce** :

```
Création          Modification          Réception          Décision         Suppression
──────────        ────────────          ─────────          ────────         ───────────
poster_mission    poster_mission?edit   candidatures       UPDATE statut    DELETE en cascade
INSERT mission    UPDATE mission        SELECT depuis      en_attente       (annonce +
                                        dashboard          → acceptee       candidatures)
                                                           ou refusee
```

---

### 🛠️ Administrateur

```
Connexion avec compte admin (badge "Admin" rouge dans la nav)
        │
        ▼
   admin.php
        │
        ├─► Statistiques globales (6 cartes bleues)
        │     users · recruteurs · freelances · admins · missions · candidatures
        │
        ├─► Table "Utilisateurs"
        │     ├─► Voir tous les comptes (avec date d'inscription)
        │     ├─► Bouton "Faire admin" / "Retirer admin" (toggle)
        │     └─► Bouton "Supprimer" → cascade applicative :
        │              suppr. candidatures user
        │            + suppr. candidatures sur ses missions
        │            + suppr. ses missions
        │            + suppr. le user
        │
        ├─► Table "Annonces"
        │     ├─► Voir toutes les annonces (avec nb candidatures)
        │     └─► Supprimer n'importe laquelle (+ ses candidatures)
        │
        └─► Table "Candidatures"
              └─► Supprimer n'importe quelle candidature
```

**Sécurités spécifiques admin** :
- Impossible de se supprimer soi-même (bouton remplacé par "(vous-même)")
- Impossible de retirer son propre statut admin (sinon plus aucun admin possible)
- Toutes les actions destructives ont un `confirm()` JavaScript

---

## Scénario complet de bout en bout

Voici un cas concret qui passe par tous les rôles.

### Acteurs

- **Sophie** — administratrice (premier compte créé)
- **TechCorp** — recruteur
- **Marie** — freelance

### Déroulé

1. **Sophie** installe le site et s'inscrit en tant que freelance (ou recruteur, peu importe).
2. Elle ouvre `promote_admin.php`, entre son email → devient admin.
3. Elle supprime `promote_admin.php` du serveur. Elle se déconnecte/reconnecte.
4. **TechCorp** visite le site, clique "Inscription", choisit "Recruteur".
5. TechCorp publie une annonce "Développeur PHP - Paris - CDI" via "Publier une annonce".
6. **Marie** visite le site, voit l'annonce sur l'accueil.
7. Elle clique dessus → veut postuler → on lui demande de se connecter.
8. Elle s'inscrit comme freelance, est redirigée, ouvre l'annonce, clique "Postuler".
9. Elle écrit son message de motivation → INSERT candidature → flash success.
10. **TechCorp** se reconnecte, ouvre son dashboard → voit la candidature de Marie.
11. Il lit le message, contacte Marie par email (lien `mailto:`), met le statut sur "Acceptée".
12. **Marie** voit dans son dashboard que sa candidature est passée à "Acceptée".
13. **Sophie** (admin) consulte `admin.php` pour vérifier les stats :
    `3 utilisateurs, 1 admin, 1 recruteur, 1 freelance, 1 annonce, 1 candidature`.

---

## Diagramme de navigation

```
                          ┌───────────────┐
                          │  index.php    │ ◄─── Accueil pour tous
                          └───────┬───────┘
                                  │
        ┌─────────────────────────┼──────────────────────────┐
        │                         │                          │
        ▼                         ▼                          ▼
┌───────────────┐         ┌───────────────┐         ┌──────────────────┐
│ inscription   │         │   missions    │         │    connexion     │
│    .php       │         │    .php       │         │      .php        │
└───────┬───────┘         └───────┬───────┘         └────────┬─────────┘
        │                         │                          │
        │                         ▼                          │
        │                ┌────────────────┐                  │
        │                │  mission.php   │                  │
        │                │     ?id=X      │                  │
        │                └────────┬───────┘                  │
        │                         │                          │
        └─────────────┬───────────┴──────────────────────────┘
                      │ (après auth)
                      ▼
              ┌───────────────┐
              │ dashboard.php │
              └───┬───────┬───┘
                  │       │
       Recruteur  │       │  Freelance
                  ▼       ▼
       ┌─────────────────┐  ┌─────────────────┐
       │ poster_mission  │  │   postuler.php  │
       │ .php (?edit=X)  │  │      ?id=X      │
       └─────────────────┘  └─────────────────┘

       Si admin :
                  ▼
            ┌─────────────┐
            │  admin.php  │
            └─────────────┘

       Toujours accessible si connecté :
                  ▼
            ┌─────────────┐
            │ logout.php  │
            └─────────────┘
```

## Messages flash

À chaque action importante, un message s'affiche en haut de la page après redirection :

| Type      | Couleur     | Exemples                                                     |
|-----------|-------------|--------------------------------------------------------------|
| `success` | Vert        | "Votre annonce a été publiée !", "Bon retour Marie !"        |
| `error`   | Rouge       | "Email ou mot de passe incorrect.", "Accès réservé..."       |
| `info`    | Bleu        | "Seuls les freelances peuvent postuler"                      |

Mécanisme : stockés dans `$_SESSION['flash']`, affichés et vidés au prochain render.
