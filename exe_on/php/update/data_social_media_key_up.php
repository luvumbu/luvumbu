<?php 
session_start();
header("Access-Control-Allow-Origin: *");  
require_once '../../../class/DatabaseHandler.php';
require_once '../../../class/config.php';
 $information_user_id_sha1 = $_SESSION["information_user_id_sha1"] ;
$time = time() ; 
$social_media_name = $_POST["social_media_name"];   
$social_media_link = $_POST["social_media_link"] ; 
$social_media_id_sha1 = $_POST["social_media_id_sha1"] ; 
$databaseHandler01 = new DatabaseHandler($username ,$password);
$databaseHandler01->action_sql('UPDATE  `social_media` SET `social_media_name` = "'.$social_media_name.'",`social_media_link` = "'.$social_media_link.'" WHERE  `social_media_id_sha1` = "'.$social_media_id_sha1.'"') ;
?>