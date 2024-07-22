<?php 
session_start();
header("Access-Control-Allow-Origin: *"); 
 
require_once '../../../class/DatabaseHandler.php';
require_once '../../../class/config.php';
  


$liste_projet_admin_id_sha1_user =  $_SESSION["information_user_id_sha1"] ; 

$liste_projet_admin_sha1_parent = $_POST["title"] ; 
$liste_projet_admin_id_sha1 = time() ; 
$liste_projet_admin_ip = $_SERVER['SERVER_NAME'] ; 
$databaseHandler00 = new DatabaseHandler($username ,$password);   
$databaseHandler00->action_sql("INSERT INTO `liste_projet_admin` (liste_projet_admin_id_sha1,liste_projet_admin_ip,liste_projet_admin_id_sha1_user,liste_projet_admin_sha1_parent) VALUES ('$liste_projet_admin_id_sha1','$liste_projet_admin_ip','$liste_projet_admin_id_sha1_user','$liste_projet_admin_sha1_parent')") ;






 /*
// Vérifier si la session spécifique 'user' existe
if (isset($_SESSION['liste_projet_admin_insert'])) {
    // Détruire la session spécifique 'liste_projet_admin_insert'
    unset($_SESSION['liste_projet_admin_insert']);

 
}  

*/






 
?>

 