<?php
       $liste_projet_admin_id_sha1 = $_SESSION['liste_projet_admin_insert'] ; 
      if(isset($_SESSION['liste_projet_admin_insert'])){
            $liste_projet_admin_id_sha1 = $_SESSION["liste_projet_admin_insert"] ;             
          }
          else {
            $liste_projet_admin_id_sha1 = $_SESSION["information_user_id_sha1"] ; 
          } 
$databaseHandler =  new DatabaseHandler($username, $password); 
$databaseHandler->getDataFromTable('SELECT * FROM `liste_projet_admin` WHERE `liste_projet_admin_sha1_parent` ="'.$liste_projet_admin_id_sha1.'"',"liste_projet_admin_id");
require 'model_new_data1.php' ; 
$info_sql = 'SELECT * FROM `liste_projet_admin` WHERE `liste_projet_admin_sha1_parent` ="'.$liste_projet_admin_id_sha1.'" ORDER BY  `liste_projet_admin_id_sha1` ASC ';
require 'model_new_data2.php' ; 
require 'model_new_data3.php' ; 


 
 ?>



