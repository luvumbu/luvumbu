<?php

session_start()  ; 
header("Access-Control-Allow-Origin: *");
$servername = "localhost";
$name =$_POST["name"] ;
 



echo $name ; 


$_SESSION["name"] =  $name ; 

 
?>