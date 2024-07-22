<?php
error_reporting(E_ERROR | E_PARSE);
class DatabaseHandler {
    public $servername = "localhost";
    public $username;
    public $password;
    public $verif = true;
    public $connection;
    public $connection_child;
    public $tableList = array();
    public $tableList_child = array();
    public $tableList_info= array();
    public $column_names = array();
    public $column_types = array();
    function __construct($username, $password) {
        $this->username = $username;
        $this->password = $password;
        $this->connection = new mysqli($this->servername, $this->username, $this->password);
        if ($this->connection->connect_error) {
            $this->verif = false;
        }
        else {
            // Create connection
                $conn = new mysqli($this->servername, $this->username, $this->password);
                // Check connection
                if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
                }

                $name_bdd= $this->username ; 

                // Create database
                $sql = "CREATE DATABASE $name_bdd";
                if ($conn->query($sql) === TRUE) {
             
                } else {
           //     echo "Error creating database: " . $conn->error;
                }

                $conn->close();
        }
    }




    function existance_table($table_a_verifier){
        // Connexion à la base de données
$connexion = new mysqli($this->servername, $this->username, $this->password,$this->username);

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
    return 1;
} else {
   return 0;
}

// Fermeture de la connexion
$connexion->close();
    }
    function getTables($database) {
        if ($this->verif) {
            $this->connection->select_db($database);
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
                $this->tableList = false ; 
            }
            $this->connection->close();
        }
    }
    function getListOfTables() {
        return $this->tableList;
    }
    function getListOfTables_Child($tableName) {
        if ($this->verif) {
            $this->connection_child = new mysqli($this->servername,  $this->username,$this->password, $this->username);
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
    }   
    function getDataFromTable($sql,$info_recherche){
        if ($this->verif) {
            $this->connection_child = new mysqli($this->servername,  $this->username,$this->password, $this->username);
            if ($this->connection_child->connect_error) {
                die("Connection failed: " . $this->connection_child->connect_error);
            }
            $result = $this->connection_child->query($sql);    
            if ($result->num_rows > 0) {
                $data = array();
                while($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }    
                foreach ($data as $row) {
                    array_push($this->tableList_info,$row[$info_recherche]);     
                }
            }
            $this->connection_child->close();
        }
    }
    function action_sql($sql){
        $this->connection_child = new mysqli($this->servername,  $this->username,$this->password, $this->username);
        if ($this->connection_child->connect_error) {
            die("Connection failed: " . $this->connection_child->connect_error);
        }
        if ($this->connection_child->query($sql) === TRUE) {            
        } else {
            echo "Error: " . $sql . "<br>" . $this->connection_child->error;
        }
        $this->connection_child->close();
    }
    function add_table($nom_table){
        if (count($this->column_names) !== count($this->column_types)) {
            die("Erreur : les tableaux de noms de colonnes et de types de données doivent avoir la même longueur.");
        }
        $columns_definitions = array();
        for ($i = 0; $i < count($this->column_names); $i++) {
            $columns_definitions[] = "{$this->column_names[$i]} {$this->column_types[$i]}";
        }
        $this->connection_child = new mysqli($this->servername,  $this->username,$this->password, $this->username);

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
    function existe_table($dbname){     
// Connexion à MySQL en utilisant les informations d'identification
// Vérification de la connexion
if ($this->connection ->connect_error) {
    die("La connexion a échoué : " . $this->connection ->connect_error);
}
// Nom de la base de données à vérifier
// Requête pour vérifier si la base de données existe
$sql = "SHOW DATABASES LIKE '$dbname'";
$result = $this->connection ->query($sql);
if ($result->num_rows > 0) {
   return true ; 
} else {
    return 0 ; 
}
// Fermer la connexion
$this->connection ->close(); 
    }
    function set_column_names($column_names){
        array_push($this->column_names,$column_names);
    }
    function set_column_types($column_types){
        array_push($this->column_types,$column_types);
    }
    function get_servername() {
        return $this->servername;
    }
    function get_username() {
        return $this->username;
    }
    function get_password() {
        return $this->password;
    }
    function get_verif() {
        return $this->verif;
    }
    function get_connection() {
        return $this->connection;
    }
    function get_connection_child() {
        return $this->connection_child;
    }
    function get_tableList() {
        return $this->tableList;
    }
    function get_tableList_child() {
        return $this->tableList_child;
    }
    function get_tableList_info() {
        return $this->tableList_info;
    }
}
/*
$databaseHandler = new DatabaseHandler("root", "root");
$databaseHandler->getTables("root");

var_dump($databaseHandler->getListOfTables());

$databaseHandler->getListOfTables_Child("liste_log3");

var_dump($databaseHandler->tableList_child);

$databaseHandler->getDataFromTable("SELECT * FROM `information_user` WHERE 1 LIMIT 1","information_user_login");

var_dump($databaseHandler->tableList_info);

var_dump($databaseHandler->tableList);

var_dump($databaseHandler->get_tableList());

$column_names = array(
    "id",
    "nom",
    "prenom",
    "email",
    "date_inscription"
);

$column_types = array(
    "INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "VARCHAR(30) NOT NULL", 
    "VARCHAR(30) NOT NULL", 
    "VARCHAR(50)",
    "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
);

$databaseHandler->set_column_names("id_x");
$databaseHandler->set_column_names("nom");
$databaseHandler->set_column_names("prenom");
$databaseHandler->set_column_names("email");
$databaseHandler->set_column_names("date_inscription");
$databaseHandler->set_column_types("INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY");
$databaseHandler->set_column_types("VARCHAR(30) NOT NULL");
$databaseHandler->set_column_types("VARCHAR(30) NOT NULL");
$databaseHandler->set_column_types("VARCHAR(50)");
$databaseHandler->set_column_types("TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
$databaseHandler->add_table("xxxX");
*/
?>
