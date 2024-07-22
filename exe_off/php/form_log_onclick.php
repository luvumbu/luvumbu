<?php
session_start();
header("Access-Control-Allow-Origin: *");  
require_once '../../class/DatabaseHandler.php';

$information_user_login =  $_POST["information_user_login"];
$information_user_password = $_POST["information_user_password"];
$information_user_password_sha1=sha1($_POST["information_user_password"]); 

$fichier_connexion = "../../class/config.php" ;
if (file_exists($fichier_connexion)) {
}
else { 
$username=  $_POST["information_user_login"]; 
$password=  $_POST["information_user_password"]; ; 
$dbname =   $_POST["information_user_login"];
include("add_table.php");
function creerFichier($nomFichier, $contenu) {
    // Écriture du contenu dans le fichier
    $resultat = file_put_contents($nomFichier, $contenu);

    if ($resultat !== false) {
        return true; // Le fichier a été créé avec succès
    } else {
        return false; // Une erreur s'est produite lors de la création du fichier
    }
} 
$filename = '../../class/config.php'; // Nom du fichier à créer
$content = '<?php' . PHP_EOL;
$content .= '$servername = \'localhost\'; // Nom du serveur' . PHP_EOL;
$content .= '$username = \'' . $username . '\'; // Nom d\'utilisateur de la base de données' . PHP_EOL;
$content .= '$password = \'' . $password . '\'; // Mot de passe de la base de données' . PHP_EOL;
$content .= '$dbname = \'' . $username . '\'; // Nom de la base de données' . PHP_EOL;
$content .= '?>';
if (creerFichier($filename, $content)) {
    echo "Le fichier $filename a été créé avec succès.";
} else {
    echo "Une erreur s'est produite lors de la création du fichier $filename.";
} 
$password_sha1 = sha1($password) ; 
$time = time() ; 
$databaseHandler00 = new DatabaseHandler($username ,$password);


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
 
$databaseHandler00->action_sql("INSERT INTO `information_user` (information_user_id_sha1,information_user_login,information_user_password,information_user_ip) VALUES ('$time','$username','$password_sha1','$REMOTE_ADDR')") ;
$databaseHandler00->action_sql("INSERT INTO `liste_projet_admin` (liste_projet_admin_id_sha1_user,liste_projet_admin_id_sha1,liste_projet_admin_information_user_login,liste_projet_admin_ip) VALUES ('$time','$time','$username','$REMOTE_ADDR')") ;



} 
 
?>