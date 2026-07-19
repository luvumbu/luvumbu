# Sécurité

## Mesures en place

### 1. Mots de passe — bcrypt

**Stockage** (`pages/inscription.php`) :

```php
$hash = password_hash($user_password1, PASSWORD_DEFAULT);
// résultat : "$2y$10$4SBgXtdIrrHFRezVXeMqmODpmFkF8k9/JkFl6CVIfjXpCVIPOF/Ue"
```

**Vérification** (`pages/connexion.php`) :

```php
if (password_verify($user_password, $user['user_password'])) { ... }
```

- L'algorithme `bcrypt` (par défaut) inclut un **salt automatique** différent pour chaque hash
- Le coût (`10`) ralentit l'attaque par force brute (~100ms par tentative)
- Impossible de retrouver le mot de passe en clair, même avec l'accès à la BDD

### 2. Injection SQL — requêtes préparées

**Toutes** les requêtes utilisent PDO avec paramètres nommés. Exemple :

```php
$stmt = $bdd->prepare(
    'SELECT * FROM users WHERE user_email = :email LIMIT 1'
);
$stmt->execute(['email' => $user_email]);
```

Aucune concaténation de chaîne dans une requête SQL. Même les valeurs en
provenance de `$_GET` ou `$_POST` sont **toujours** passées comme paramètres.

Pour les paramètres positionnels (IDs entiers), un cast `(int)` est appliqué :

```php
$id = (int)($_GET['id'] ?? 0);
```

### 3. XSS — échappement systématique

**Toutes** les sorties HTML passent par `htmlspecialchars()` :

```php
<?= htmlspecialchars($mission['mission_titre_mission']) ?>
```

Pour les contenus multi-lignes :

```php
<?= nl2br(htmlspecialchars($mission['mission_description'])) ?>
```

→ Aucun balisage HTML injectable depuis la base ou l'URL.

### 4. Contrôle d'accès par rôle

Chaque page protégée commence par une vérification :

```php
// admin.php
if (!est_admin()) {
    flash_set('error', 'Accès réservé aux administrateurs.');
    header('Location: connexion.php');
    exit;
}

// poster_mission.php
if (!est_recruteur()) {
    flash_set('error', 'Vous devez être recruteur pour publier une annonce.');
    header('Location: connexion.php');
    exit;
}

// postuler.php
if (!est_freelance()) {
    flash_set('error', 'Seuls les freelances peuvent postuler.');
    header('Location: connexion.php');
    exit;
}
```

Note : le `exit` après `header()` est crucial — sans lui, le reste du code
continue de s'exécuter même si le navigateur reçoit la redirection.

### 5. Vérification d'appartenance

Une ressource modifiée ou supprimée doit appartenir à l'utilisateur :

```php
// poster_mission.php - édition
$r = $bdd->prepare(
    'SELECT * FROM mission WHERE mission_id = :id AND mission_id_user = :uid'
);
$r->execute(['id' => $edit_id, 'uid' => $_SESSION['user_id']]);
```

```php
// dashboard.php - suppression
$d = $bdd->prepare(
    'DELETE FROM mission WHERE mission_id = :id AND mission_id_user = :uid'
);
```

Impossible pour un recruteur de supprimer ou modifier une annonce qui
n'est pas la sienne, même en forgeant l'URL.

### 6. Validation des entrées

À l'inscription :

```php
if ($user_name === '')                                 $erreurs[] = "...";
if (!filter_var($user_email, FILTER_VALIDATE_EMAIL))   $erreurs[] = "...";
if (strlen($user_password1) < 6)                       $erreurs[] = "...";
if ($user_password1 !== $user_password2)               $erreurs[] = "...";
if (!in_array($user_jesuis, ['freelance','recruteur'], true)) $erreurs[] = "...";
```

À la candidature :

```php
if (strlen($message) < 20) {
    $erreurs[] = 'Votre message doit faire au moins 20 caractères.';
}
```

Pour le statut de candidature (admin / recruteur) :

```php
if (in_array($statut, ['en_attente','acceptee','refusee'], true)) { ... }
```

→ Whitelist plutôt que blacklist.

### 7. Unicité applicative + SQL

- **Email unique** : contrainte `UNIQUE` sur `user_email` + vérification PHP avant INSERT
- **Une candidature par freelance par annonce** : contrainte `UNIQUE
  (candidature_id_mission, candidature_id_user)` + vérification PHP

### 8. Pattern PRG (Post-Redirect-Get)

Tous les traitements POST se terminent par une redirection. Avantages :
- Empêche la **double-soumission** en cas de F5
- Évite le warning "voulez-vous renvoyer le formulaire ?"
- L'URL reste propre

### 9. Sessions PHP

- Cookie de session avec attribut `HttpOnly` par défaut (configurable dans `php.ini`)
- Destruction complète au logout :
  ```php
  session_unset();
  session_destroy();
  ```
- Variables minimales stockées : `user_id`, `user_name`, `user_email`,
  `user_jesuis`, `user_is_admin`

### 10. Protection contre les actions destructives

Confirmations JavaScript pour toutes les suppressions :

```html
<form method="POST" onsubmit="return confirm('Supprimer cette annonce ?');">
```

L'admin ne peut pas supprimer son propre compte (test côté serveur et UI).

---

## Limitations actuelles (à corriger pour production)

### ⚠️ Pas de protection CSRF

Les formulaires n'ont pas de jeton CSRF. Un attaquant pourrait théoriquement
faire pointer un formulaire d'un autre site vers `dashboard.php` pour
supprimer une annonce de la victime.

**À ajouter** : un token aléatoire dans `$_SESSION['csrf_token']`, injecté
en `<input type="hidden">` dans chaque formulaire, vérifié à la réception.

### ⚠️ Identifiants BDD en clair dans `bdd.php`

Les credentials production sont visibles dans le code source si le fichier
fuit (mauvaise config Apache, mauvaise permission, dépôt git public).

**À faire** :
- Déplacer dans un fichier `.env` hors du dossier web (lecture via `parse_ini_file`)
- Ajouter `.env` au `.gitignore`
- Changer le mot de passe MySQL exposé dans le code actuel

### ⚠️ Pas de rate-limiting sur la connexion

Force brute possible sur le login (1000 tentatives/sec).

**À ajouter** : compteur d'échecs par IP/email dans une table dédiée, blocage temporaire.

### ⚠️ Headers de sécurité HTTP absents

Manquent :
- `X-Frame-Options: DENY` (anti clickjacking)
- `Content-Security-Policy` (anti XSS)
- `Strict-Transport-Security` (si HTTPS)
- `X-Content-Type-Options: nosniff`

À ajouter dans Apache ou en PHP via `header()` au début de chaque page.

### ⚠️ Pas de HTTPS forcé

En production, tout doit passer en HTTPS. Sinon les mots de passe, sessions
et données privées circulent en clair sur le réseau.

### ⚠️ `promote_admin.php` doit être supprimé

Le fichier est accessible publiquement. **Toute** personne avec un compte
peut se promouvoir admin tant qu'il existe. À supprimer immédiatement après usage.

### ⚠️ Pas de logs d'audit

Aucune trace de qui a supprimé quoi, qui a promu qui en admin. Important
en production pour les enquêtes en cas d'incident.

---

## Checklist avant mise en production

- [ ] Supprimer `promote_admin.php`
- [ ] Supprimer ou désactiver `phpinfo()` éventuel
- [ ] Déplacer les identifiants BDD hors du code (`.env`)
- [ ] Changer le mot de passe MySQL exposé
- [ ] Forcer HTTPS (redirection 301 depuis HTTP)
- [ ] Activer `display_errors = Off` dans `php.ini`
- [ ] Configurer un handler d'erreurs qui logue mais n'affiche pas les détails
- [ ] Ajouter des tokens CSRF sur tous les formulaires
- [ ] Ajouter rate-limiting sur la connexion
- [ ] Ajouter les headers de sécurité HTTP
- [ ] Mettre en place des sauvegardes BDD automatiques
- [ ] Désactiver le compte `root` MySQL ou lui mettre un vrai mot de passe
- [ ] Restreindre les permissions filesystem (Apache en lecture seule sur le code)
