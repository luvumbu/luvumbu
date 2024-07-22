<?php 
session_start();
header("Access-Control-Allow-Origin: *"); 
 
require_once '../../../class/DatabaseHandler.php';
require_once '../../../class/config.php';
 

 $liste_projet_admin_id_sha1 = $_POST["liste_projet_admin_id_sha1"] ;
 

 

 
 
$databaseHandler01 = new DatabaseHandler($username ,$password);
 



$databaseHandler01->action_sql('UPDATE  `liste_projet_admin` SET `liste_projet_admin_ip` = "", `liste_projet_admin_ip`="" , `liste_projet_admin_id_sha1_user`="" , `liste_projet_admin_sha1_parent`="" , `liste_projet_admin_id_sha1`="" WHERE  `liste_projet_admin_id_sha1` = "'.$liste_projet_admin_id_sha1.'"') ;
 



 $_SESSION["liste_projet_admin_insert"] = $_SESSION["information_user_id_sha1"] ;
 
?>