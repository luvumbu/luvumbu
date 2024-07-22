<?php

session_start();
header("Access-Control-Allow-Origin: *"); 
 
require_once '../../../class/DatabaseHandler.php';
require_once '../../../class/config.php';
$information_user_id_sha1 =$_SESSION["information_user_id_sha1"]  ;
$databaseHandler01 = new DatabaseHandler($username ,$password);
$databaseHandler01->action_sql('UPDATE  `information_user` SET `information_user_img_name` = "'.$time.'",`information_user_img_extennsion` = "'.$time.'",`information_user_img_path` = "'.$time.'"  WHERE  `information_user_id_sha1` = "'.$information_user_id_sha1.'"') ; 
 

?>