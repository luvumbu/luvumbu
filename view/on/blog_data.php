<?php 
$databaseHandler = new DatabaseHandler($username, $password);
$databaseHandler->getDataFromTable("SELECT * FROM `liste_projet_admin` WHERE `liste_projet_admin_id_sha1`='123456789' ","liste_projet_admin_id");
 
?>