<?php 
session_start();
header("Access-Control-Allow-Origin: *"); 
 
require_once '../../../class/DatabaseHandler.php';
require_once '../../../class/config.php';
  

$file_path = $_SESSION["file_path"] ; 
$total = $_SESSION["total"] ; 


$time = $_SESSION["name"] ; 
 $information_user_id_sha1 = $_SESSION["information_user_id_sha1"] ;
 /*
 $information_user_name_1 = $_POST["information_user_name_1"]; 
$information_user_name_2 = $_POST["information_user_name_2"] ; 
*/
$databaseHandler01 = new DatabaseHandler($username ,$password);



 

 




/*

$_SESSION["liste_projet_admin_id_sha1"] = $_POST["liste_projet_admin_id_sha1"] ; 


 
$_SESSION["id"] = $_POST["id"] ; 
*/




switch ($_SESSION["id"]) {
   case "data_user":
      $databaseHandler01->action_sql('UPDATE  `information_user` SET `information_user_img_name` = "'.$time.'",`information_user_img_extennsion` = "'.$total.'",`information_user_img_path` = "'.$file_path.'"  WHERE  `information_user_id_sha1` = "'.$information_user_id_sha1.'"') ; 

     break;
   case "data_children":
      
      $liste_projet_admin_id_sha1 = $_SESSION["liste_projet_admin_id_sha1"] ;
      $file_path= $_SESSION["file_path"]  ; 
      $total= $_SESSION["total"] ;  
      $databaseHandler01->action_sql('UPDATE  `liste_projet_admin` SET `liste_projet_admin_img_name` = "'.$liste_projet_admin_id_sha1.'",`liste_projet_admin_img_extennsion` = "'.$total.'",`liste_projet_admin_img_path` = "'.$file_path.'"  WHERE  `liste_projet_admin_id_sha1` = "'.$liste_projet_admin_id_sha1.'"') ; 
   
 break;


 case "data_social_media":
    
  $liste_projet_admin_id_sha1 = $_SESSION["liste_projet_admin_id_sha1"] ;
  $file_path= $_SESSION["file_path"]  ; 
  $total= $_SESSION["total"] ;  
  $databaseHandler01->action_sql('UPDATE  `social_media` SET `social_media_src` = "'.$file_path.'"  WHERE  `social_media_id_sha1` = "'.$liste_projet_admin_id_sha1.'"') ; 

break;

   default:

 
 }


unset($_SESSION["name"]);

?>

<script>
 window.location.href = "../../../index.php";
</script>