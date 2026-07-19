# Installation

## Pré-requis

- **XAMPP** (ou WAMP, MAMP) avec :
  - Apache ≥ 2.4
  - PHP ≥ 7.0 (PDO MySQL activé)
  - MySQL / MariaDB ≥ 5.6
- Un navigateur récent

## Étape 1 — Placer le projet

Copie tout le dossier `hrconsulting/` dans :
```
C:\xampp\htdocs\hrconsulting\
```

## Étape 2 — Lancer les services

Ouvre **XAMPP Control Panel** et démarre :
- ☑ **Apache**
- ☑ **MySQL**

## Étape 3 — Créer la base de données

Méthode A — **phpMyAdmin** (recommandé) :
1. Ouvre http://localhost/phpmyadmin
2. Onglet **Importer**
3. Sélectionne le fichier `install.sql` à la racine du projet
4. Clique **Exécuter**

Méthode B — **Ligne de commande** :
```powershell
Get-Content "C:\xampp\htdocs\hrconsulting\install.sql" | & "C:\xampp\mysql\bin\mysql.exe" -u root
```

Le script crée :
- la base `hrconsulting`
- les tables `users`, `mission`, `candidature`

## Étape 4 — Vérifier la configuration

Ouvre `bdd.php` à la racine du projet et vérifie :

```php
$bd_host     = "localhost";
$bd_name     = "hrconsulting";
$bd_user     = "root";
$bd_password = "";   // vide par défaut sous XAMPP
```

Si tu as défini un mot de passe MySQL, modifie `$bd_password`.

## Étape 5 — Premier accès

Ouvre http://localhost/hrconsulting/

Tu dois voir la page d'accueil avec :
- Une bannière bleue "Trouvez votre prochaine mission"
- Une barre de recherche
- Un message "Aucune annonce pour le moment" (la base est vide)

## Étape 6 — Créer un compte administrateur

1. Clique sur **Inscription** en haut à droite
2. Remplis le formulaire (peu importe le type : freelance ou recruteur)
3. Une fois inscrit, ouvre :
   ```
   http://localhost/hrconsulting/promote_admin.php
   ```
4. Entre l'email du compte que tu viens de créer
5. Clique **Promouvoir**
6. Déconnecte-toi puis reconnecte-toi
7. ⚠️ **Supprime le fichier `promote_admin.php`** pour la sécurité

Un bouton rouge **Admin** apparaît désormais dans la barre du haut.

## Configuration en production

Si tu déploies sur un vrai serveur, dans `bdd.php` la branche `else` est utilisée :

```php
$bd_host     = "localhost";
$bd_name     = "u481158665_hr";
$bd_user     = "u481158665_hr";
$bd_password = "v3p9r3e@59";
```

Adapte ces valeurs à ton hébergeur. **Recommandé** : déplacer les identifiants
dans un fichier `.env` ou hors du dossier web pour éviter qu'ils soient exposés.

## Résolution de problèmes

| Symptôme                                       | Cause probable                                       | Solution                                                  |
|------------------------------------------------|------------------------------------------------------|-----------------------------------------------------------|
| "Erreur de connexion à la base de données"     | MySQL non démarré, ou identifiants faux              | Démarrer MySQL dans XAMPP, vérifier `bdd.php`             |
| Page blanche                                   | Erreur PHP fatale silencieuse                        | Activer `display_errors` dans `php.ini`                   |
| "Vous devez être recruteur pour publier..."    | Le compte est freelance                              | Crée un compte recruteur via Inscription                  |
| Bouton Admin invisible alors que j'ai promu    | Session ancienne                                     | Déconnecte-toi puis reconnecte-toi                        |
| `promote_admin.php` ne trouve pas l'email      | Inscription pas faite ou faute de frappe email       | Vérifie dans phpMyAdmin → `users`                         |
