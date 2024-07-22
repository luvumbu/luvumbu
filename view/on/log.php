<?php
//echo $_SESSION["information_user_id_sha1"];
?>
<!DOCTYPE html>
<html>

<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    <?php
    require_once "log_css.php";
    require_once "exe_on/php/select/log_data.php";
    /*
exe_on/php/select/log_data:
    array_information_user_name_1
    array_information_user_img
    array_information_user_name_2
*/
    ?>
  </style>
</head>

<body>
 
  <div>
    <img onclick="destroy()" class="cursor_pointer" width="50" height="50" src="https://img.icons8.com/fluency-systems-regular/50/shutdown.png" alt="shutdown" />
    <img title="" onclick="liste_projet_admin_insert(this)" width="64" height="64" src="https://img.icons8.com/flat-round/64/plus.png" alt="plus" />

  </div>


  <div class="row row_element">
    <div class="leftcolumn" id="leftcolumn">
      <?php
 
      if (isset($_SESSION['liste_projet_admin_insert'])) {

        if($_SESSION['liste_projet_admin_insert']== $_SESSION["information_user_id_sha1"]){
         
        }
       
        $liste_projet_admin_id_sha1 = $_SESSION["liste_projet_admin_insert"];
       


      
      } else {

        $liste_projet_admin_id_sha1 = $_SESSION["information_user_id_sha1"];
       
 
      }
      $liste_projet_admin_insert_bool = "1";
      $plus_element__t = 0 ; 
          require_once "exe_on/php/select/data_parent.php";
          $plus_element__t = 1 ; 
       require_once "exe_on/php/select/data_children.php";
      ?>
    </div>
    <div class="rightcolumn" id="rightcolumn">
      <?php
      require_once "exe_on/php/select/data_user.php";
      require_once "exe_on/php/select/data_all.php";
      require_once "exe_on/php/select/data_social_media.php";
      ?>
    </div>
  </div>
  <div class="footer">
    <h2>Footer</h2>
  </div>
</body>

</html>

<style>
 
  .row_element{
    width: 98%;
    margin: auto;
  }
</style>