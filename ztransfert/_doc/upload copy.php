<?php


session_start() ; 
function decode_chunk($data) {
    $data = explode(';base64,', $data);

    if (!is_array($data) || !isset($data[1])) {
        return false;
    }

    $data = base64_decode($data[1]);
    if (!$data) {
        return false;
    }

    return $data;
}

// $file_path: fichier cible: garde le même nom de fichier, dans le dossier uploads






if(strpos($element_recherche, ".")!=".jpg"){
$file_path = 'uploads/' .$_SESSION["name"].'.jpg';
 
}

elseif (strpos($element_recherche, ".jpeg")!="") {
$file_path = 'uploads/' .$_SESSION["name"].'.jpeg';
   
  } 
  
  elseif (strpos($element_recherche, ".png")!="") {
$file_path = 'uploads/' .$_SESSION["name"].'.png';
   
  } 
  elseif (strpos($element_recherche, ".gif")!="") {
$file_path = 'uploads/' .$_SESSION["name"].'.gif';
   
  } 
  elseif (strpos($element_recherche, ".tif")!="") {
$file_path = 'uploads/' .$_SESSION["name"].'.tif';
   
  } 
  elseif (strpos($element_recherche, ".pdf")!="") {
$file_path = 'uploads/' .$_SESSION["name"].'.pdf';
   
  } 
 
 /*

  else {
   
  }

  */


$file_data = decode_chunk($_POST['file_data']);

if (false === $file_data) {
    echo "error";
}

/* on ajoute le segment de données qu'on vient de recevoir 
 * au fichier qu'on est en train de ré-assembler: */
file_put_contents($file_path, $file_data, FILE_APPEND);

// nécessaire pour que JavaScript considère que la requête s'est bien passée:
echo json_encode([]); 
?>