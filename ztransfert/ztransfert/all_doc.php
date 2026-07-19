<?php 
require_once 'Give_url.php' ; 
require_once 'DatabaseHandler.php' ; 
 
// Création d'une instance de la classe, avec $_SERVER['PHP_SELF'] par défaut
$url = new Give_url();
// Afficher le nom du fichier actuel
 
// Utilisation de la méthode split_basename pour séparer par "_"
$url->split_basename('_');
 


$URL__ = $url->get_basename() ; 
?>

<?php 

/* 
 * Exemple de sélection de données dans une base de données avec `DatabaseHandler`
 * Ce script montre comment configurer une connexion, exécuter une requête SQL,
 * et récupérer des informations spécifiques à partir d'une table.
 */
 
// Configuration de la base de données


?>

 

<?php 
/*
 * Exemple d'utilisation des variables dynamiques
 * Ce script montre comment utiliser la classe `DatabaseHandler` pour manipuler des données
 * d'une table spécifique dans une base de données en générant des variables dynamiques.
 */
 
// Configuration de la base de données
 
$username = "u489596434_marion";  // Nom d'utilisateur de la base de données
$password = "v3p9r3e@59A"; // Mot de passe de la base de données
$nom_table = "we_transfert"; // Nom de la table cible

// Création d'une instance de la classe `DatabaseHandler`
$databaseHandler = new DatabaseHandler($username, $password);

// Requête SQL pour récupérer toutes les données de la table
$req_sql = "SELECT * FROM `$nom_table` WHERE `name`='$URL__' ";

// Récupération des informations des tables enfant liées
$databaseHandler->getListOfTables_Child($nom_table);
// La méthode `getListOfTables_Child` récupère les tables enfants associées à `$nom_table`.

// Récupération des données de la table via une méthode spécialisée
$databaseHandler->getDataFromTable2X($req_sql);
// La méthode `getDataFromTable2X` exécute la requête SQL et prépare les données à être utilisées dynamiquement.

// Génération de variables dynamiques à partir des données récupérées
$databaseHandler->get_dynamicVariables();
// La méthode `get_dynamicVariables` transforme les données récupérées en variables dynamiques disponibles dans le tableau `$dynamicVariables`.

// Exemple : affichage d'une variable dynamique spécifique

// `id_sha1_projet` est une clé générée dynamiquement qui correspond à une colonne ou une donnée récupérée dans la table.
 
/*
 * Remarque :
 * - Les variables dynamiques sont utiles pour générer du contenu ou manipuler des données
 *   sans connaître à l'avance les noms des colonnes ou des champs.
 * - Assurez-vous que les noms de colonnes dans `$dynamicVariables` existent dans la table cible.
 * - Cette approche peut être utilisée pour des tâches nécessitant une flexibilité dans le traitement des données.
 */


 if(count($dynamicVariables['name'])<1) {
    echo "Aucun fichier " ; 
 }
 else {
 
 


    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Téléchargement</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg,rgb(20, 26, 24),rgb(34, 59, 54));
            color: #fff;
            text-align: center;
            padding: 20px;
            background-repeat: no-repeat;
            overflow: hidden; /* Hide scrollbars */
        
            width: 100%;
            height: 2000px;
        }

        h1 {
            font-size: 2.5rem;
            margin-top: 50px;
            text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.3);
        }

        a {
            display: inline-block;
            margin: 30px auto;
            padding: 15px 30px;
            font-size: 1.2rem;
            font-weight: bold;
            text-decoration: none;
            color: #fff;
            background: #3498DB;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
            transition: background 0.3s, transform 0.3s;
        }

        a:hover {
            background: #2980B9;
            transform: translateY(-3px);
        }

        a:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <h1>Télécharger un fichier</h1>
    <a href="<?= '../'.$dynamicVariables['file_path'][0] ?>" download>Télécharger le fichier</a>
<br/> 

<br/> 

<a href="../">
    <div class="menu_p">MENU PRINCIPAL</div>
</a>
</body>
</html>


    <?php 
 }
?>

<style>
    .menu_p{
        width: 300px;
        text-align: center;
        margin: auto;
    
        color: white;
    }
</style>