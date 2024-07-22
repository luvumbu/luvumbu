<div class="card">
      <?php








      $social_media_id = new DatabaseHandler($username, $password);
      $social_media_id_user_sha1 = new DatabaseHandler($username, $password);
      $social_media_id_sha1 = new DatabaseHandler($username, $password);
      $social_media_src = new DatabaseHandler($username, $password);


      $social_media_name = new DatabaseHandler($username, $password);
      $social_media_link = new DatabaseHandler($username, $password);






      $social_media_img = new DatabaseHandler($username, $password);
      $social_data = new DatabaseHandler($username, $password);


      //$info_sql = 'SELECT * FROM `social_media` WHERE `social_media_id` =1';




      $info_sql = 'SELECT * FROM `social_media` WHERE `social_media_id_user_sha1` =' . $data_root . '';


      $social_media_id->getDataFromTable($info_sql, "social_media_id");
      $social_media_id_user_sha1->getDataFromTable($info_sql, "social_media_id_user_sha1");
      $social_media_img->getDataFromTable($info_sql, "social_media_img");
      $social_data->getDataFromTable($info_sql, "social_data");

      $social_media_id_sha1->getDataFromTable($info_sql, "social_media_id_sha1");

      $social_media_src->getDataFromTable($info_sql, "social_media_src");

      $social_media_name->getDataFromTable($info_sql, "social_media_name");
      $social_media_link->getDataFromTable($info_sql, "social_media_link");





      /*
var_dump($social_media_id->tableList_info ) ; 
 var_dump($social_media_id_user_sha1->tableList_info)  ; 

 var_dump($social_media_img->tableList_info)  ; 
 var_dump($social_data->tableList_info)  ; 
*/


      //var_dump($social_media_id->tableList_info ) ; 

      $social_media_id_bool = false;

      if (isset($_SESSION["information_user_id_sha1"])) {

            $social_media_id_bool = true;
      }
      for ($a = 0; $a < count($social_media_id->tableList_info); $a++) {


            if ($social_media_src->tableList_info[$a] == "") {

                  $social_media_src_  = "https://img.icons8.com/office/40/picture.png";
            } else {

                  if(give_url()=="index.php"){

                              $social_media_src_ = str_replace("../", "", $social_media_src->tableList_info[$a]);
                  }
                  else {
                        $social_media_src_ = $social_media_src->tableList_info[$a] ; 
                  }
                  
            }

            if ($social_media_id_bool) {


                  if(give_url()=="index.php"){

 ?>
                  <input type="text" value="<?php echo $social_media_name->tableList_info[$a]; ?>" class="social_media_name" onkeyup="data_social_media_key_up(this)" placeholder="Name social media" title="<?php echo  $social_media_id_sha1->tableList_info[$a]; ?>" id="<?php echo "name_" . $social_media_id_sha1->tableList_info[$a]; ?>">
                  <input type="text" value="<?php echo $social_media_link->tableList_info[$a]; ?>" class="social_media_link" onkeyup="data_social_media_key_up(this)" placeholder="Link social media" title="<?php echo  $social_media_id_sha1->tableList_info[$a]; ?>" id="<?php echo "link_" . $social_media_id_sha1->tableList_info[$a]; ?>">
                  <img src="<?php echo $social_media_src_ ?>" alt="" srcset="" class="social_media">


               <?php     
               
            
            
            }
            else {
?>

<div class="display_flex_social_media">
                       <h4>
                               <a href="<?php echo $social_media_link->tableList_info[$a]; ?>">
                              
                              <?php echo $social_media_name->tableList_info[$a]; ?>
                        
                        </a>

                       </h4>
                       
                       <a href="<?php echo $social_media_link->tableList_info[$a]; ?>">
                        
                              <img src="<?php echo $social_media_src_ ?>" alt="" srcset="" class="social_media">
                              </a>
                       
                  </div>

 
<?php 
            }
     


             






                  ?>

            <?php

            } else {
            ?>



                  <div class="display_flex_social_media">
                       <h4>
                               <a href="<?php echo $social_media_link->tableList_info[$a]; ?>">
                              
                              <?php echo $social_media_name->tableList_info[$a]; ?>
                        
                        </a>

                       </h4>
                       
                       <a href="<?php echo $social_media_link->tableList_info[$a]; ?>">
                        
                              <img src="<?php echo $social_media_src_ ?>" alt="" srcset="" class="social_media">
                              </a>
                       
                  </div>
            <?php
            }
      }

      if ($social_media_id_bool) {

            ?>
            <br />
            <br />
            <br />
            <br />
            <br />

            <img width="50" onclick="data_social_media_click(this)" title="<?php echo $data_root ?>" height="50" src="https://img.icons8.com/cotton/50/plus--v3.png" alt="plus--v3" class="cursor_pointer" />

      <?php
      }
      ?>






</div>

<style>
      .space_top {
            margin-top: 50px;
      }

      .max_width {
            max-width: 50px;
      }

      .space_up {
            margin-top: 25px;
      }

      .social_media {
            width: 40px;
      }

      .social_media_src {
            margin-bottom: 12px;
            padding: 8px;
      }

      .social_media_link {
            margin-bottom: 12px;
            padding: 7px;
      }

      .data_social_media_img {
            width: 40px;
            margin-bottom: 100px;
      }
      .display_flex_social_media{
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            margin-bottom: 25px;
      }
</style>