<?php
session_start();
header("Access-Control-Allow-Origin: *"); 
 
require_once '../../../class/DatabaseHandler.php';
require_once '../../../class/config.php';


$_SESSION["liste_projet_admin_id_sha1"] = $_POST["liste_projet_admin_id_sha1"] ; 


 
$_SESSION["id"] = $_POST["id"] ; 

 

?>