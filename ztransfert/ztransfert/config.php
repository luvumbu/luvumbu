<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <style>
        body{
            background-color: black;
            color: black;
        }
    </style>

<?php 


session_start() ; 


echo $_SESSION["name"]  ; 


require_once "DatabaseHandler.php" ; 

 
 
// Ajouter une table différemment dans la base de données
 
 // Définition des paramètres de connexion à la base de données
 // Nom de la base de données et nom d'utilisateur pour la connexion
$dbname = "u489596434_marion";  // Nom de la base de données
$username = "v3p9r3e@59A"; // Nom d'utilisateur pour la connexion

// Définition du nom de la table à ajouter
$table_name = "we_transfert"; // Nom de la table à créer

// Initialisation du gestionnaire de base de données avec les paramètres de connexion
// Un objet '$databaseHandler' est créé avec la base de données et le nom d'utilisateur fournis
$databaseHandler = new DatabaseHandler($dbname, $username);

// Définition des colonnes de la table sous forme de tableau associatif
// Chaque clé du tableau représente le nom de la colonne et la valeur associée définit le type de la colonne
$columns = [
    "id_transfert"               => "INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY", // Colonne 'id_user' comme clé primaire
    "file_path"          => "LONGTEXT NOT NULL", // Colonne 'id_sha1_user'
    "total"        => "LONGTEXT NOT NULL", // Colonne 'id_parent_user'
    "name"        => "LONGTEXT NOT NULL", // Colonne 'id_parent_user'
    "date_inscription_user" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP" // Colonne 'date_inscription_user' avec valeur par défaut et mise à jour automatique
];

// Itération sur le tableau des colonnes pour définir les noms et types des colonnes dans la base de données
// Pour chaque élément du tableau '$columns', on appelle la méthode 'set_column_names()' et 'set_column_types()' pour définir la colonne et son type
foreach ($columns as $name => $type) {
    // Définition du nom de la colonne
    $databaseHandler->set_column_names($name);
    // Définition du type de la colonne
    $databaseHandler->set_column_types($type);
}

// Appel de la méthode 'add_table()' pour ajouter la table à la base de données
// La table est créée avec le nom '$table_name' et les colonnes définies précédemment
$databaseHandler->add_table($table_name);
 
  

?>




<?php 
 

// Suppression d'un enregistrement dans la base de données
// Exemple de suppression d'un élément dans une table spécifique
 
// Initialisation du gestionnaire de base de données
// Un objet '$databaseHandler' est créé avec les paramètres de connexion spécifiés
$databaseHandler = new DatabaseHandler($dbname, $username);

// Exécution de la requête SQL pour supprimer un enregistrement
// La méthode 'action_sql()' est utilisée pour exécuter des requêtes SQL directes
// Cette requête supprime un enregistrement de la table 'projet'
// où 'id_projet' est égal à 11


$time = time() ; 

 
$file_path = $_SESSION['file_path']  ; 
$total =$_SESSION['total'] ; 
$name = $_SESSION["name"] ; 


$databaseHandler->action_sql("INSERT INTO `we_transfert` (`file_path`,`total`,`name`) VALUES ('$file_path','$total','$name')");

// Suppression d'un enregistrement dans la base de données
// Exemple de suppression d'un élément dans une table spécifique
 
?>

<meta http-equiv="refresh" content="0; URL=envoi_ok.php">




</body>
</html>