<?php 
session_start() ; 

if (isset($_SESSION['liste_projet_admin_insert'])) {
    // Détruire la session spécifique 'liste_projet_admin_insert'
    unset($_SESSION['liste_projet_admin_insert']);
}
?>