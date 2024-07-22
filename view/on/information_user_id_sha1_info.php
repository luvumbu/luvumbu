<?php
session_start() ; 

 


if(isset($_SESSION["information_user_id_sha1"])){
      echo $_SESSION["information_user_id_sha1"] ; 
}
else{
    ?>
<div class="alert alert-danger" role="alert">
   ERROR
</div>
    <?php 
}
?>