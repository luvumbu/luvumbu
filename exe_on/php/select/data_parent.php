<?php

/*
$liste_projet_admin_id_ = new DatabaseHandler($username, $password);
$liste_projet_admin_id_sha1_ = new DatabaseHandler($username, $password);
$liste_projet_admin_id_sha1_user_ = new DatabaseHandler($username, $password);
$liste_projet_admin_name1_ = new DatabaseHandler($username, $password);
$liste_projet_admin_name2_ = new DatabaseHandler($username, $password);
$liste_projet_admin_img_path_ = new DatabaseHandler($username, $password);

*/

require 'model_new_data1.php' ; 
$info_sql = 'SELECT * FROM `liste_projet_admin` WHERE  `liste_projet_admin_id_sha1` ="' . $liste_projet_admin_id_sha1 . '" ORDER BY  `liste_projet_admin_id_sha1` ASC ';
require 'model_new_data2.php' ; 
require 'model_new_data3.php' ; 








?>