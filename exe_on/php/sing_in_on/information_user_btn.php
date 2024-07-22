<?php 
session_start();
header("Access-Control-Allow-Origin: *"); 

require_once '../../../class/DatabaseHandler.php';
require_once '../../../class/config.php';

$time = time() ; 
$information_user_login = $_POST["information_user_login"] ; 
$information_user_password = $_POST["information_user_password"] ; 
$information_user_password_sha1 = sha1($information_user_password) ; 

$databaseHandler00 = new DatabaseHandler($username ,$password);
$databaseHandler00->getDataFromTable('SELECT * FROM `information_user` WHERE `information_user_login`="'.$information_user_login.'" AND `information_user_password` ="'.$information_user_password_sha1.'"',"information_user_id_sha1");

if(count($databaseHandler00->tableList_info)==0){

$databaseHandler01 = new DatabaseHandler($username ,$password);
$databaseHandler01->getDataFromTable('SELECT * FROM `information_user` WHERE `information_user_login`="'.$information_user_login.'" ',"information_user");

if(count($databaseHandler01->tableList_info)==0){

    $databaseHandler00 = new DatabaseHandler($username ,$password);
    $databaseHandler00->action_sql("INSERT INTO `information_user` (information_user_login,information_user_password,information_user_id_sha1) VALUES ('$information_user_login','$information_user_password_sha1','$time')") ;
    $_SESSION["information_user_login"] = $information_user_login ; 
    $_SESSION["information_user_password"] = $information_user_password ; 
    $_SESSION["information_user_id_sha1"] = $time;

 
 



 

 
// Fonction pour récupérer l'adresse IP de l'utilisateur
function getIPAddress() {
    // Si l'adresse IP est en tête HTTP_X_FORWARDED_FOR, alors c'est une adresse IP de proxy
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    // Sinon, l'adresse IP est dans la variable REMOTE_ADDR
    else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

// Appel de la fonction pour obtenir l'adresse IP
$ip_address = getIPAddress();

// Affichage de l'adresse IP
 
    $REMOTE_ADDR =$ip_address ; 
    
    $databaseHandler00->action_sql("INSERT INTO `liste_projet_admin` (liste_projet_admin_id_sha1_user,liste_projet_admin_id_sha1,liste_projet_admin_information_user_login,liste_projet_admin_ip) VALUES ('$time','$time','$information_user_login','$SERVER_NAME')") ;

}
else {
    unset($_SESSION["information_user_id_sha1"]);
}

 }
 else {

$_SESSION["information_user_login"] = $information_user_login ; 
$_SESSION["information_user_password"] = $information_user_password ; 
$_SESSION["information_user_id_sha1"] = $databaseHandler00->tableList_info[0];
}
 



 



 
$directory = '../../../src/img/';

if (!is_dir($directory)) {
    if (mkdir($directory, 0777, true)) {
        echo "Le dossier $directory a été créé avec succès.";
    } else {
        echo "Échec de la création du dossier $directory.";
    }
}  
 
 



$dossier = "../../../src/img/".$_SESSION["information_user_id_sha1"] ; 

 



if (!is_dir($dossier)) {
    if (mkdir($dossier, 0777, true)) {
        echo "Le dossier $dossier a été créé avec succès.";
    } else {
        echo "Échec de la création du dossier $dossier.";
    }
}  
?>