<div class="card text-center max_height" id="data_popular">
       

     

<?php
 
 
     $liste_projet_admin_id_ = new DatabaseHandler($username ,$password);   
      $liste_projet_admin_id_sha1_ = new DatabaseHandler($username ,$password);     
      $liste_projet_admin_id_sha1_user_ = new DatabaseHandler($username ,$password);     
      $liste_projet_admin_name1_ = new DatabaseHandler($username ,$password);     
      $liste_projet_admin_name2_ = new DatabaseHandler($username ,$password); 
      $liste_projet_admin_img_path_ =new DatabaseHandler($username ,$password); 

$bool__ =false ; 

      if(!isset($_SESSION["information_user_id_sha1"])){     
      $info_sql ='SELECT * FROM `liste_projet_admin` WHERE `liste_projet_admin_sha1_parent` =""  ' ; 
        
   }
   else {
    $info_sql ='SELECT * FROM `liste_projet_admin` WHERE `liste_projet_admin_id_sha1_user`="'.$information_user_id_sha1.'" AND `liste_projet_admin_sha1_parent` =""  ' ; 
    $bool__ = true ; 
   }
      
      $liste_projet_admin_id_->getDataFromTable($info_sql,"liste_projet_admin_id");
      $liste_projet_admin_id_sha1_->getDataFromTable($info_sql,"liste_projet_admin_id_sha1");
      $liste_projet_admin_id_sha1_user_->getDataFromTable($info_sql,"liste_projet_admin_id_sha1_user");
      $liste_projet_admin_name1_->getDataFromTable($info_sql,"liste_projet_admin_name1");
      $liste_projet_admin_name2_->getDataFromTable($info_sql,"liste_projet_admin_name2");
      $liste_projet_admin_img_path_->getDataFromTable($info_sql,"liste_projet_admin_img_path");
      


      for($a = 0 ; $a <count($liste_projet_admin_id_sha1_->tableList_info); $a ++) {
        $liste_projet_admin_id_sha1__=  $liste_projet_admin_id_sha1_->tableList_info[$a] ; 
        $liste_projet_admin_name1__=  $liste_projet_admin_name1_->tableList_info[$a] ;   
        $max_length = 10;        
        // Utilisation de substr pour obtenir les 5 premiers caractères
       $liste_projet_admin_name1__= substr($liste_projet_admin_name1__, 0, $max_length);         

       if(give_url()!="index.php"){
        $liste_projet_admin_img_path________ = $liste_projet_admin_img_path_->tableList_info[$a];


       }
       else{
       $liste_projet_admin_img_path________ = str_replace("../","",$liste_projet_admin_img_path_->tableList_info[$a]);

       }
     
        ?>
      <div class="fakeimg cursor_pointer data_all_colors" id="data_all_colors" >
      <div>
          <?php
          echo $a ;
          ?>
        </div>
        <div>
          <?php  echo $liste_projet_admin_name1__.'...' ?>
        </div>   

        <br/>
        <?php
        if($liste_projet_admin_id_sha1__!=$information_user_id_sha1){

 

          if($bool__){
            ?>

 
            <img class="img_colors" title="<?php echo $liste_projet_admin_id_sha1__ ?>" onclick="remove_block_(this)"  width="50" height="50" src="https://img.icons8.com/ios/50/delete-forever--v1.png" alt="delete-forever--v1"/>
            
              <div class="display_none" id="<?php echo "remove_".$liste_projet_admin_id_sha1__ ?>" title="<?php echo $liste_projet_admin_id_sha1__ ?>" onclick="remove_block(this)">
              
              <img class="img_colors" width="50" height="50" src="https://img.icons8.com/fluency/50/delete-forever.png" alt="delete-forever"/>
              </div>
            
            
            
            <?php 
          }

        }
        if($bool__){

?>

<div>
  <a href="<?php echo "blog.php/".$liste_projet_admin_id_sha1__  ?>">
    <img src="<?php echo $liste_projet_admin_img_path________ ?>" alt="" srcset="" class="data_all_src">
  </a>


        <img onclick="data_all_cookie(this)" title="<?php echo  $liste_projet_admin_id_sha1__ ?>" class="img_colors" width="50" height="50" src="https://img.icons8.com/ios/50/preview-pane.png" alt="preview-pane"/>
        </div>
<?php 

        }
        else {
 
     if(give_url()=="index.php"){

     }
     else {
      $liste_projet_admin_img_path________ = "../".$liste_projet_admin_img_path________ ; 

     }

          ?>
<img src="<?php echo $liste_projet_admin_img_path________ ?>" alt="" srcset="" class="data_all_src">

<a href="<?php echo  'blog.php/'.$liste_projet_admin_id_sha1__ ?>">


<?php

if($presentation2_index_php){
?>
  <img   title="<?php echo  $liste_projet_admin_id_sha1__ ?>" class="img_colors" width="50" height="50" src="https://img.icons8.com/ios/50/preview-pane.png" alt="preview-pane"/>
<?php 
}
else {
  ?>
<a href="<?php echo  $liste_projet_admin_id_sha1__ ?>" >
<img   title="<?php echo  $liste_projet_admin_id_sha1__ ?>" class="img_colors" width="50" height="50" src="https://img.icons8.com/ios/50/preview-pane.png" alt="preview-pane"/>

</a>

<?php 
}
?>

  </a>
     
          <?php 
        }

        ?>

      </div> 
        <?php
      }
      

 ?>
</div>
<style>
  .img_colors{ 
    background-color: white;
    padding: 10px;
  }
  .fakeimg{
    display: flex;
    justify-content: space-around;
    transition: 1s all ; 
    margin-bottom: 5px;
     
    color: white;

  }
 
  .max_height{
    max-height: 500px; 
    overflow-y: scroll; /* Hide vertical scrollbar */
  }
  .display_none{
    display: none;
  }
  .data_all_src{
    width: 50px;
    height: 50px;
  }
</style>