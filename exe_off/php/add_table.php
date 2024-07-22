<?php

$databaseHandler = new DatabaseHandler($dbname, $password);
 

 

$databaseHandler->set_column_names("information_user_id");
$databaseHandler->set_column_names("information_user_id_sha1");
$databaseHandler->set_column_names("information_user_ip");
$databaseHandler->set_column_names("information_user_login");
$databaseHandler->set_column_names("information_user_name_1");

$databaseHandler->set_column_names("information_user_name_2");
$databaseHandler->set_column_names("information_user_name_3");
$databaseHandler->set_column_names("information_user_name_4");
$databaseHandler->set_column_names("information_user_adresse_1");
$databaseHandler->set_column_names("information_user_adresse_2");

$databaseHandler->set_column_names("information_user_adresse_3");
$databaseHandler->set_column_names("information_user_adresse_4");
$databaseHandler->set_column_names("information_user_password");
$databaseHandler->set_column_names("information_user_img_name");

$databaseHandler->set_column_names("information_user_img_extennsion");
$databaseHandler->set_column_names("information_user_img_path");

$databaseHandler->set_column_names("information_user_born");

$databaseHandler->set_column_names("information_user_number_1");
$databaseHandler->set_column_names("information_user_number_2");
$databaseHandler->set_column_names("information_user_number_3");
$databaseHandler->set_column_names("information_user_number_4");
$databaseHandler->set_column_names("information_user_activate");
/**/
$databaseHandler->set_column_names("information_user_reg_date");










$databaseHandler->set_column_types("INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY");

 
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL"); 
$databaseHandler->set_column_types("TEXT NOT NULL");

$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");

$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");

$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");

/*
*/
$databaseHandler->set_column_types("TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
$databaseHandler->add_table("information_user");







$databaseHandler2 = new DatabaseHandler($dbname, $password);

$databaseHandler2->set_column_names("liste_projet_admin_id");
$databaseHandler2->set_column_names("liste_projet_admin_ip");

$databaseHandler2->set_column_names("liste_projet_admin_id_sha1");
$databaseHandler2->set_column_names("liste_projet_admin_sha1_parent");
$databaseHandler2->set_column_names("liste_projet_admin_id_sha1_user");
$databaseHandler2->set_column_names("liste_projet_admin_information_user_login");




$databaseHandler2->set_column_names("liste_projet_admin_add_projet_value");



$databaseHandler2->set_column_names("liste_projet_admin_sha1");



$databaseHandler2->set_column_names("liste_projet_admin_name1");
$databaseHandler2->set_column_names("liste_projet_admin_name2");
$databaseHandler2->set_column_names("liste_projet_admin_name3");
$databaseHandler2->set_column_names("liste_projet_admin_name4");
$databaseHandler2->set_column_names("liste_projet_admin_name5");



$databaseHandler2->set_column_names("liste_projet_admin_img_name");
$databaseHandler2->set_column_names("liste_projet_admin_img_extennsion");
$databaseHandler2->set_column_names("liste_projet_admin_img_path");



$databaseHandler2->set_column_names("liste_projet_admin_title_name1");
$databaseHandler2->set_column_names("liste_projet_admin_title_name2");
$databaseHandler2->set_column_names("liste_projet_admin_title_name3");
$databaseHandler2->set_column_names("liste_projet_admin_title_name4");
$databaseHandler2->set_column_names("liste_projet_admin_title_name5");



$databaseHandler2->set_column_names("liste_projet_admin_title_src1");
$databaseHandler2->set_column_names("liste_projet_admin_title_src2");
$databaseHandler2->set_column_names("liste_projet_admin_title_src3");

$databaseHandler2->set_column_names("liste_projet_admin_visibility_src1");
$databaseHandler2->set_column_names("liste_projet_admin_visibility_src2");
$databaseHandler2->set_column_names("liste_projet_admin_visibility_src3");

$databaseHandler2->set_column_names("liste_projet_admin_visibility_name1");
$databaseHandler2->set_column_names("liste_projet_admin_visibility_name2");
$databaseHandler2->set_column_names("liste_projet_admin_visibility_name3");
$databaseHandler2->set_column_names("liste_projet_admin_visibility_name4");
$databaseHandler2->set_column_names("liste_projet_admin_visibility_name5");

$databaseHandler2->set_column_names("liste_projet_admin_link");




$databaseHandler2->set_column_names("information_user_reg_date");
 

 




$databaseHandler2->set_column_types("INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY");
$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");


$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");



$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");

$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");

$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");

$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");

$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");

$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");


$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");
$databaseHandler2->set_column_types("TEXT  NOT NULL");

$databaseHandler2->set_column_types("TEXT  NOT NULL");



 
 $databaseHandler2->set_column_types("TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

$databaseHandler2->add_table("liste_projet_admin");

















$databaseHandler = new DatabaseHandler($dbname, $password);
 

 

$databaseHandler->set_column_names("social_media_id");
$databaseHandler->set_column_names("social_media_id_sha1");

$databaseHandler->set_column_names("social_media_id_user_sha1");

$databaseHandler->set_column_names("social_media_name");
$databaseHandler->set_column_names("social_media_link");
$databaseHandler->set_column_names("social_media_src");
$databaseHandler->set_column_names("social_date");

$databaseHandler->set_column_types("INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY"); 
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");

$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TEXT NOT NULL");
$databaseHandler->set_column_types("TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
$databaseHandler->add_table("social_media");



?>