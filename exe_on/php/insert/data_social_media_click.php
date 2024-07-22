<?php 
session_start();
header("Access-Control-Allow-Origin: *"); 
 
require_once '../../../class/DatabaseHandler.php';
require_once '../../../class/config.php';
  


$social_media_id_user_sha1 = $_SESSION["information_user_id_sha1"] ; 
$time = time() ; 
$databaseHandler00 = new DatabaseHandler($username ,$password);   
$databaseHandler00->action_sql("INSERT INTO `social_media` (social_media_id_user_sha1,social_media_id_sha1) VALUES ('$social_media_id_user_sha1','$time')") ;


 


 
 
?>

 