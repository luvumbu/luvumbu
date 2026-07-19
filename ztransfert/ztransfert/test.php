<?php 

$test ="ABC.jpeg" ; 
 
$total = "" ; 
for($i=strlen($test)-1;$i>0;$i--){
  //  echo $test[$i].'<br/>' ; 
$total = $total.$test[$i] ; 
    if($test[$i]=="."){
        break ; 
    }

}


echo strrev($total) ; 

?>