<?php
error_reporting(E_ERROR | E_PARSE);
class DatabaseHandler
{
    public $servername = "localhost";
    public $username;
    public $password;
    public $verif = true;
    public $connection;
    public $connection_child;
    public $tableList = array();
    public $tableList_child = array();
    public $tableList_child2 = array();
    public $tableList_info = array();
    public $tableList_info2 = [];
    public $table_name_and_child;

    public $table_name_general;
    public $sql_general;

    public $mysql_general;
    public $table_general;




    public $column_names = array();

    public $column_types = array();
    function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
        $this->connection = new mysqli($this->servername, $this->username, $this->password);
        if ($this->connection->connect_error) {
            $this->verif = false;
        } else {
            // Create connection
            $conn = new mysqli($this->servername, $this->username, $this->password);
            // Check connection
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }

            $name_bdd = $this->username;

            // Create database
            $sql = "CREATE DATABASE $name_bdd";
            if ($conn->query($sql) === TRUE) {
            } else {
                //  echo "Error creating database: " . $conn->error;
            }

            $conn->close();
        }
    }

    function function_affiche_all()
    {
      

 
   
    }

    function set_mysql_general($mysql_general)
    {

        $this->mysql_general = $mysql_general;
    }

    function set_table_general($table_general)
    {
        $this->table_general = $table_general;
    }

    function set_table_name_general($table_name_general)
    {
        $this->table_name_general = $table_name_general;
        return $this->table_name_general;
    }
    function get_table_name_general()
    {

        return $this->table_name_general;
    }

    function set_sql_general($sql_general)
    {

        $this->sql_general  = $sql_general;
    }



    function existance_table($table_a_verifier)
    {
        // Connexion à la base de données
        $connexion = new mysqli($this->servername, $this->username, $this->password, $this->username);

        // Vérification de la connexion
        if ($connexion->connect_error) {
            die("Erreur de connexion à la base de données : " . $connexion->connect_error);
        }

        // Nom de la table à vérifier


        // Requête SQL pour vérifier l'existence de la table
        $sql = "SHOW TABLES LIKE '$table_a_verifier'";
        $resultat = $connexion->query($sql);

        // Vérification du résultat
        if ($resultat->num_rows > 0) {
            return "1";
        } else {
            return "0";
        }

        // Fermeture de la connexion
        $connexion->close();
    }

    function getTables()
    {
        if ($this->verif) {
            $this->connection->select_db($this->username);
            if ($this->connection->error) {
                return;
            }
            $sql = "SHOW TABLES";
            $result = $this->connection->query($sql);
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_array()) {
                    array_push($this->tableList, $row[0]);
                }
            } else {
                $this->tableList = false;
            }
            $this->connection->close();
        }

        return $this->tableList;
    }
    function getListOfTables()
    {

        // Donne la liste de tables dans la Bdd
        // ont peut faire aussi
        //var_dump($this->tableList) ;

        return $this->tableList;
    }


    function general_dynamique()
    {
        $this->getListOfTables_Child($this->table_general);
        $this->getDataFromTable2X($this->mysql_general);
        $this->get_dynamicVariables();
    }
    function getListOfTables_Child($tableName)
    {
        if ($this->verif) {
            $this->connection_child = new mysqli($this->servername,  $this->username, $this->password, $this->username);
            if ($this->connection_child->connect_errno) {
                exit();
            }
            $query = "SHOW COLUMNS FROM $tableName";
            $result = $this->connection_child->query($query);
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    array_push($this->tableList_child, $row['Field']);
                }
            }
            $this->connection_child->close();
        }

        return $this->tableList_child;
    }

 

    function getListOfTables_Child2($tableName)
    {
        if ($this->verif) {
            $this->connection_child = new mysqli($this->servername,  $this->username, $this->password, $this->username);
            if ($this->connection_child->connect_errno) {
                exit();
            }
            $query = "SHOW COLUMNS FROM $tableName";
            $result = $this->connection_child->query($query);
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    array_push($this->tableList_child2, $row['Field']);
                }
            }
            $this->connection_child->close();
        }
    }
    function getDataFromTable($sql, $info_recherche)
    {
        $this->tableList_info = array();
        if ($this->verif) {
            $this->connection_child = new mysqli($this->servername,  $this->username, $this->password, $this->username);
            if ($this->connection_child->connect_error) {
                die("Connection failed: " . $this->connection_child->connect_error);
            }
            $result = $this->connection_child->query($sql);
            if ($result->num_rows > 0) {
                $data = array();
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                foreach ($data as $row) {
                    array_push($this->tableList_info, $row[$info_recherche]);
                }
            }
            $this->connection_child->close();
        }
        $this->tableList_info2[] = $this->tableList_info;
    }


    function getDataFromTable2X($sql)
    {
        foreach ($this->tableList_child as $row) {
            $this->getDataFromTable($sql, $row);
        }
    }
    function action_sql($sql)
    {
        $this->connection_child = new mysqli($this->servername,  $this->username, $this->password, $this->username);
        if ($this->connection_child->connect_error) {
            die("Connection failed: " . $this->connection_child->connect_error);
        }
        if ($this->connection_child->query($sql) === TRUE) {
        } else {
            echo "Error: " . $sql . "<br>" . $this->connection_child->error;
        }
        $this->connection_child->close();
    }
    function add_table($nom_table)
    {
        if (count($this->column_names) !== count($this->column_types)) {
            die("Erreur : les tableaux de noms de colonnes et de types de données doivent avoir la même longueur.");
        }
        $columns_definitions = array();
        for ($i = 0; $i < count($this->column_names); $i++) {
            $columns_definitions[] = "{$this->column_names[$i]} {$this->column_types[$i]}";
        }
        $this->connection_child = new mysqli($this->servername,  $this->username, $this->password, $this->username);

        if ($this->connection_child->connect_error) {
            die("Échec de la connexion : " . $this->connection_child->connect_error);
        }
        $sql = "CREATE TABLE $nom_table (
            " . implode(", ", $columns_definitions) . "
        )";
        if ($this->connection_child->query($sql) === TRUE) {
            //echo "Table clients créée avec succès.";
        } else {
            echo "Erreur lors de la création de la table : " . $this->connection_child->error;
        }
        $this->connection_child->close();
    }
    function existe_table($dbname)
    {
        // Connexion à MySQL en utilisant les informations d'identification
        // Vérification de la connexion
        if ($this->connection->connect_error) {
            die("La connexion a échoué : " . $this->connection->connect_error);
        }
        // Nom de la base de données à vérifier
        // Requête pour vérifier si la base de données existe
        $sql = "SHOW DATABASES LIKE '$dbname'";
        $result = $this->connection->query($sql);
        if ($result->num_rows > 0) {
            return true;
        } else {
            return 0;
        }
        // Fermer la connexion
        $this->connection->close();
    }
    function set_column_names($column_names)
    {
        array_push($this->column_names, $column_names);
    }
    function set_column_types($column_types)
    {
        array_push($this->column_types, $column_types);
    }
    function get_servername()
    {
        return $this->servername;
    }
    function get_username()
    {
        return $this->username;
    }
    function get_password()
    {
        return $this->password;
    }
    function get_verif()
    {
        return $this->verif;
    }
    function get_connection()
    {
        return $this->connection;
    }
    function get_connection_child()
    {
        return $this->connection_child;
    }
    function get_tableList()
    {
        return $this->tableList;
    }
    function get_tableList_child()
    {
        return $this->tableList_child;
    }
    function get_tableList_info()
    {
        return $this->tableList_info;
    }

    function get_dynamicVariables()
    {
        global $dynamicVariables; // Rend la variable accessible globalement
        $dynamicVariables = []; // Initialisation

        foreach ($this->tableList_child as $index => $nom) {
            if (isset($this->tableList_info2[$index])) {
                $dynamicVariables[strtolower($nom)] = $this->tableList_info2[$index];
            }
        }

        /*
       // exemple utilisation 
        $databaseHandler->get_dynamicVariables();
        // Utilisation des données dynamiques
       // global $dynamicVariables;
       // var_dump($dynamicVariables['id_sha1_user']);

        */




        /*

        // autre méthode 
// Création des variables dynamiques
foreach ($databaseHandler->tableList_child as $index => $nom) {
    if (isset($databaseHandler->tableList_info2[$index])) { // Vérifie si un fruit existe à cet index
        ${strtolower($nom)} = $databaseHandler->tableList_info2[$index]; // Crée une variable avec le nom en minuscule
    }
}




var_dump($id_sha1_user );

*/
    }


    function get_dynamicVariables_general()
    {
        global $dynamicVariables; // Rend la variable accessible globalement
        $dynamicVariables = []; // Initialisation

        foreach ($this->tableList_child as $index => $nom) {
            if (isset($this->tableList_info2[$index])) {
                $dynamicVariables[strtolower($nom)] = $this->tableList_info2[$index];
            }
        }
    }
}





 ?>

<?php 

/* 
 * Exemple de sélection de données dans une base de données avec `DatabaseHandler`
 * Ce script montre comment configurer une connexion, exécuter une requête SQL,
 * et récupérer des informations spécifiques à partir d'une table.
 */
/*
// Configuration de la base de données
$config_dbname = "root";  // Nom d'utilisateur de la base de données
$config_password = "root"; // Mot de passe de la base de données

// Requête SQL pour sélectionner toutes les lignes de la table `projet`
$req_sql = "SELECT * FROM `projet` WHERE 1";

// Création d'une instance de la classe `DatabaseHandler`
// Cette classe gère la connexion et l'exécution des requêtes SQL
$databaseHandler = new DatabaseHandler($config_dbname, $config_password);

// Exécution de la méthode pour obtenir les données d'une table
// Le deuxième paramètre ("id_projet") est utilisé pour spécifier la colonne à extraire
$databaseHandler->getDataFromTable($req_sql, "id_projet");

// Récupération des résultats dans une propriété de la classe
$id_projet = $databaseHandler->tableList_info;

// Affichage des résultats pour vérifier le contenu (à des fins de débogage)
var_dump($id_projet);
*/
/*
 * Remarque :
 * - La variable `$id_projet` contient une liste des valeurs de la colonne `id_projet` récupérées depuis la table `projet`.
 * - Utilisez cette méthode pour extraire des données spécifiques à partir de votre base de données.
 */

?>


<?php 

/*
 * Vérification de la connexion à la base de données avec `DatabaseHandler`
 * Ce script montre comment configurer une connexion et vérifier son état.
 */
/*
// Configuration de la base de données
$config_dbname = "root";  // Nom d'utilisateur de la base de données
$config_password = "root"; // Mot de passe de la base de données

// Requête SQL utilisée pour initialiser l'objet (non directement utilisée ici)
$req_sql = "SELECT * FROM `projet` WHERE 1";

// Création d'une instance de la classe `DatabaseHandler`
// Cette classe établit une connexion avec la base de données dès sa création
$databaseHandler = new DatabaseHandler($config_dbname, $config_password);

// Vérification de l'état de la connexion
// La propriété `verif` retourne :
// - `1` (ou true) si la connexion à la base de données a réussi
// - `false` si la connexion a échoué
echo $databaseHandler->verif;
*/
/*
 * Remarque :
 * - Si la connexion réussit, le résultat affiché sera `1`.
 * - Si la connexion échoue, aucun résultat ou une valeur `false` sera retournée.
 * - Assurez-vous que vos identifiants et paramètres de connexion sont corrects.
 */

?>


<?php 

/*
 * Exemple de création d'une table dans une base de données
 * Ce script montre comment définir les colonnes et leurs types, 
 * puis créer une table à l'aide de la classe `DatabaseHandler`.
 */
/*
// Configuration de la base de données
$config_dbname = "root";  // Nom d'utilisateur de la base de données
$config_password = "root"; // Mot de passe de la base de données
$nom_table_ajout ="nom_table_ajout";
// Création d'une instance de la classe `DatabaseHandler`
// Cette classe est utilisée pour interagir avec la base de données
$databaseHandler = new DatabaseHandler($config_dbname, $config_password);

// Définition des noms des colonnes pour la table
$databaseHandler->set_column_names("id_x");              // Colonne pour un identifiant unique
$databaseHandler->set_column_names("nom");               // Colonne pour le nom
$databaseHandler->set_column_names("prenom");            // Colonne pour le prénom
$databaseHandler->set_column_names("email");             // Colonne pour l'email
$databaseHandler->set_column_names("date_inscription");  // Colonne pour la date d'inscription

// Définition des types des colonnes correspondants
$databaseHandler->set_column_types("INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY"); // Type pour un identifiant unique auto-incrémenté
$databaseHandler->set_column_types("VARCHAR(30) NOT NULL");                       // Type pour un nom (non vide)
$databaseHandler->set_column_types("VARCHAR(30) NOT NULL");                       // Type pour un prénom (non vide)
$databaseHandler->set_column_types("VARCHAR(50)");                                // Type pour un email (peut être vide)
$databaseHandler->set_column_types("TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"); // Type pour une date avec mise à jour automatique

// Création de la table
$databaseHandler->add_table($nom_table_ajout); // Nom de la table à créer : "undeuxtroisquatre"
*/
/*
 * Remarque :
 * - `set_column_names` permet de définir les noms des colonnes dans l'ordre d'ajout.
 * - `set_column_types` permet de définir les types correspondants à ces colonnes.
 * - `add_table` crée la table avec les colonnes et types spécifiés.
 * - Vérifiez que la table n'existe pas déjà dans la base de données avant d'exécuter ce script.
 */

?>




<?php 
/*
 * Exemple d'utilisation des variables dynamiques
 * Ce script montre comment utiliser la classe `DatabaseHandler` pour manipuler des données
 * d'une table spécifique dans une base de données en générant des variables dynamiques.
 */
/*
// Configuration de la base de données
$username = "root";   // Nom d'utilisateur pour la base de données
$password = "root";   // Mot de passe pour la base de données
$nom_table = "projet"; // Nom de la table cible

// Création d'une instance de la classe `DatabaseHandler`
$databaseHandler = new DatabaseHandler($username, $password);

// Requête SQL pour récupérer toutes les données de la table
$req_sql = "SELECT * FROM `$nom_table` WHERE 1";

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
var_dump($dynamicVariables['id_sha1_projet']);
// `id_sha1_projet` est une clé générée dynamiquement qui correspond à une colonne ou une donnée récupérée dans la table.
*/
/*
 * Remarque :
 * - Les variables dynamiques sont utiles pour générer du contenu ou manipuler des données
 *   sans connaître à l'avance les noms des colonnes ou des champs.
 * - Assurez-vous que les noms de colonnes dans `$dynamicVariables` existent dans la table cible.
 * - Cette approche peut être utilisée pour des tâches nécessitant une flexibilité dans le traitement des données.
 */

?>




<?php 
/*
 * Exemple de vérification de l'existence d'une table dans une base de données.
 * Ce script montre comment utiliser la méthode `existance_table` de la classe `DatabaseHandler`
 * pour vérifier si une table spécifique existe dans la base de données.
 */
/*
$username = "root";  // Nom d'utilisateur de la base de données
$password = "root"; // Mot de passe de la base de données
// Création d'une instance de la classe `DatabaseHandler`
$databaseHandler = new DatabaseHandler($username, $password);
// `$username` : Nom d'utilisateur pour se connecter à la base de données.
// `$password` : Mot de passe pour se connecter à la base de données.

// Vérification de l'existence de la table `projet_img`
$existance_table_ = $databaseHandler->existance_table("projet_img");
// La méthode `existance_table` prend le nom de la table en paramètre et vérifie si elle existe.
// Elle retourne une valeur numérique :
// - `1` : La table existe.
// - `0` : La table n'existe pas.

// Affichage du résultat de la vérification
echo $existance_table_;
// Cette ligne affiche le résultat de la méthode dans la console ou dans la réponse HTTP,
// en fonction de l'environnement d'exécution.
*/
/*
 * Remarque :
 * - Cette méthode est utile pour s'assurer qu'une table existe avant d'exécuter des opérations SQL dessus.
 * - Vous pouvez utiliser ce résultat pour décider si une table doit être créée, modifiée ou utilisée.
 * - En cas de connexion échouée ou d'erreur de base de données, assurez-vous que votre classe gère correctement les exceptions.
 */

?>


<?php
/*
// Déclaration des identifiants de connexion à la base de données
$username = "root"; // Nom d'utilisateur pour la connexion à la base de données
$password = "root"; // Mot de passe associé au nom d'utilisateur pour la connexion

// Création d'un objet 'DatabaseHandler' avec les identifiants de connexion à la base de données
$handler = new DatabaseHandler($username, $password); 

// Appel à la méthode 'getTables()' de l'objet 'DatabaseHandler' pour récupérer toutes les tables de la base de données
$getTables_ = $handler->getTables();

// Initialisation d'un tableau vide '$a' pour stocker les noms des tables récupérées
$a = array();

// Boucle pour parcourir chaque élément (nom de table) dans le tableau '$getTables_'
foreach ($getTables_ as $key) {
    // Ajoute chaque nom de table à notre tableau '$a'
    array_push($a, $key);
}

// Affiche le contenu du tableau '$a', qui contient tous les noms des tables de la base de données
var_dump($a);

// Le code ici sert à rechercher et récupérer tous les noms des tables présentes dans la base de données
*/
?>



<?php 

/*
// Recherche tous les noms des tables dans la base de données en utilisant le nom de la base 'dbname' et le mot de passe 'password'

// Déclaration des identifiants de connexion à la base de données
$username = "root"; // Nom d'utilisateur pour la connexion à la base de données
$password = "root"; // Mot de passe associé au nom d'utilisateur pour la connexion

// Création d'un objet 'DatabaseHandler' avec les identifiants de connexion à la base de données
$handler = new DatabaseHandler($username, $password); 

// Appel à la méthode 'getTables()' pour récupérer toutes les tables de la base de données
$getTables_ =  $handler->getTables();

// Exemple de débogage pour afficher la structure de la variable contenant les noms des tables
// echo var_dump($getTables_); 

// Initialisation d'un tableau vide '$a' pour stocker les noms des tables récupérées
$a = array(); 

// Exemple de débogage avec une boucle 'for' pour afficher les éléments de la variable '$getTables_'
// echo var_dump($getTables_); 

// Boucle 'for' pour parcourir le tableau des tables récupérées
for ($i = 0; $i < count($getTables_); $i++) {
    // Ajoute le nom de chaque table au tableau '$a'
    array_push($a, $getTables_[$i]);
}

// Affiche le contenu du tableau '$a', qui contient tous les noms des tables récupérées
var_dump($a);

// Le code sert à rechercher et récupérer tous les noms des tables dans la base de données 
*/

?>



<?php 

/*
 
// Recherche tous les éléments dans une table spécifique en utilisant une boucle 'for'

// Création d'un objet 'DatabaseHandler' avec les identifiants de connexion à la base de données
$databaseHandler = new DatabaseHandler('root', 'root'); 

// Appel de la méthode 'getListOfTables_Child()' pour récupérer la liste des éléments d'une table
// La méthode prend en paramètre "root" comme argument pour identifier la base de données ou la table
$getTables_ =  $databaseHandler->getListOfTables_Child("root");

// Exemple de débogage pour afficher la structure de la variable contenant les éléments récupérés
// echo var_dump($getListOfTables_Child);

// Initialisation d'un tableau vide '$a' pour stocker les éléments récupérés
$a = array();

// Boucle 'for' pour parcourir le tableau des éléments récupérés
for ($i = 0; $i < count($getTables_); $i++) {
    // Ajoute chaque élément récupéré au tableau '$a'
    array_push($a, $getTables_[$i]);
}

// Affiche le contenu du tableau '$a', qui contient tous les éléments récupérés
var_dump($a);

// Recherche et récupération de tous les éléments dans la table spécifique à l'aide d'une boucle 'for'
*/

?>

 


<?php 
/*

 // Création d'un objet 'DatabaseHandler' avec les identifiants de connexion à la base de données
 // Le nom d'utilisateur et le mot de passe sont fournis en tant que variables (voir ci-dessous)
$databaseHandler = new DatabaseHandler($username, $password);

 
// Recherche tous les éléments dans une table spécifique en utilisant une boucle 'foreach'

// Création d'un objet 'DatabaseHandler' avec des identifiants de connexion pour la base de données
// Ici, 'root' est utilisé comme nom d'utilisateur et mot de passe pour établir la connexion
$databaseHandler = new DatabaseHandler('root', 'root'); 

// Appel de la méthode 'getListOfTables_Child()' pour récupérer la liste des éléments d'une table
// Le paramètre "root" peut être utilisé pour spécifier la base de données ou la table ciblée
$getTables_ =  $databaseHandler->getListOfTables_Child("root");

// Exemple de débogage pour afficher la structure de la variable contenant les éléments récupérés
// echo var_dump($getListOfTables_Child);

// Initialisation d'un tableau vide '$a' pour stocker les éléments récupérés
$a = array();

// Utilisation d'une boucle 'foreach' pour parcourir le tableau retourné par 'getListOfTables_Child()'
foreach ($getTables_ as $key) {
    // Ajoute chaque élément (représenté par '$key') au tableau '$a'
    array_push($a, $key);
}

// Affiche le contenu du tableau '$a', qui contient tous les éléments récupérés
var_dump($a); 

*/
?>


<?php 
// Ajouter une table différemment dans la base de données
/*
 // Définition des paramètres de connexion à la base de données
 // Nom de la base de données et nom d'utilisateur pour la connexion
$dbname = "root";  // Nom de la base de données
$username = "root"; // Nom d'utilisateur pour la connexion

// Définition du nom de la table à ajouter
$table_name = "Nom_a_ajouter"; // Nom de la table à créer

// Initialisation du gestionnaire de base de données avec les paramètres de connexion
// Un objet '$databaseHandler' est créé avec la base de données et le nom d'utilisateur fournis
$databaseHandler = new DatabaseHandler($dbname, $username);

// Définition des colonnes de la table sous forme de tableau associatif
// Chaque clé du tableau représente le nom de la colonne et la valeur associée définit le type de la colonne
$columns = [
    "id_user"               => "INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY", // Colonne 'id_user' comme clé primaire
    "id_sha1_user"          => "LONGTEXT NOT NULL", // Colonne 'id_sha1_user'
    "id_parent_user"        => "LONGTEXT NOT NULL", // Colonne 'id_parent_user'
    "description_user"      => "LONGTEXT NOT NULL", // Colonne 'description_user'
    "title_user"            => "LONGTEXT NOT NULL", // Colonne 'title_user'
    "img_user"              => "LONGTEXT NOT NULL", // Colonne 'img_user'
    "nom_user"              => "LONGTEXT NOT NULL", // Colonne 'nom_user'
    "prenom_user"           => "LONGTEXT NOT NULL", // Colonne 'prenom_user'
    "password_user"         => "LONGTEXT NOT NULL", // Colonne 'password_user'
    "email_user"            => "LONGTEXT NOT NULL", // Colonne 'email_user'
    "activation_user"       => "LONGTEXT NOT NULL", // Colonne 'activation_user'
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

*/ 

?>

 
 
 
<?php 
/*
// Mise à jour dans la base de données
// Exemple de mise à jour d'un enregistrement dans une table spécifique

// Définition des paramètres de connexion à la base de données
$dbname = "root";  // Nom de la base de données
$username = "root"; // Nom d'utilisateur pour la connexion

// Initialisation du gestionnaire de base de données
// Un objet '$databaseHandler' est créé avec les paramètres de connexion spécifiés
$databaseHandler = new DatabaseHandler($dbname, $username);

// Exécution de la requête SQL pour mettre à jour un enregistrement
// La méthode 'action_sql()' est utilisée pour exécuter des requêtes SQL directes
// Cette requête met à jour la colonne 'activation_projet' dans la table 'projet'
// où 'id_projet' est égal à 11, en le mettant à la valeur "off"
$databaseHandler->action_sql('UPDATE `projet` SET `activation_projet` = "off" WHERE `id_projet` ="11" ');

// Mise à jour dans la base de données
// Exemple de mise à jour d'un enregistrement dans une table spécifique
*/
?>



<?php 
/*

// Suppression d'un enregistrement dans la base de données
// Exemple de suppression d'un élément dans une table spécifique

// Définition des paramètres de connexion à la base de données
$dbname = "root";  // Nom de la base de données
$username = "root"; // Nom d'utilisateur pour la connexion

// Initialisation du gestionnaire de base de données
// Un objet '$databaseHandler' est créé avec les paramètres de connexion spécifiés
$databaseHandler = new DatabaseHandler($dbname, $username);

// Exécution de la requête SQL pour supprimer un enregistrement
// La méthode 'action_sql()' est utilisée pour exécuter des requêtes SQL directes
// Cette requête supprime un enregistrement de la table 'projet'
// où 'id_projet' est égal à 11
$databaseHandler->action_sql("DELETE FROM `projet` WHERE `id_projet` = '11'");

// Suppression d'un enregistrement dans la base de données
// Exemple de suppression d'un élément dans une table spécifique
*/
?>
