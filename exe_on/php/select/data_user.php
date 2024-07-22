<div class="card">
 <?php 


 
$liste_projet_admin_id_sha1_user_ = new DatabaseHandler($username, $password);

 if(give_url()!="index.php"){
  $info_sql = 'SELECT * FROM `liste_projet_admin`  WHERE `liste_projet_admin_id_sha1`="'.give_url().'" ORDER BY   `liste_projet_admin_id_sha1` ASC';

 }
 else {

  if(isset($_SESSION["information_user_id_sha1"])){
 
    $info_sql = 'SELECT * FROM `liste_projet_admin`  WHERE `liste_projet_admin_id_sha1`='.$_SESSION["information_user_id_sha1"].'';
  }
  else {
    $info_sql = 'SELECT * FROM `liste_projet_admin`  WHERE `liste_projet_admin_id`="1" ';

  }
  

 }

$liste_projet_admin_id_sha1_user_ ->getDataFromTable($info_sql, "liste_projet_admin_id_sha1_user");



 
 
 


$data_root = $liste_projet_admin_id_sha1_user_ ->tableList_info[0] ;  

  if(isset($_SESSION["information_user_id_sha1"])){
 
$information_user_id_sha1 = $_SESSION["information_user_id_sha1"] ; 
    
$info_sql = 'SELECT * FROM `information_user` WHERE   `information_user_id_sha1`  ="'.$information_user_id_sha1.'"';

 
 
 






  }
  else {

 
    $liste_projet_admin_id_sha1_user=$liste_projet_admin_id_sha1_user_->tableList_info[0] ; 
    $info_sql = 'SELECT * FROM `information_user` WHERE   `information_user_id_sha1`  ="'.$liste_projet_admin_id_sha1_user .'"';

 



 
  }

  $information_user_id_sha1_ = new DatabaseHandler($username, $password);
  $information_user_name_1_ = new DatabaseHandler($username, $password);
  $information_user_name_2_ = new DatabaseHandler($username, $password);
  $information_user_img_path_ = new DatabaseHandler($username, $password);
  
  $information_user_id_sha1_ ->getDataFromTable($info_sql, "information_user_id_sha1");
  $information_user_name_1_ ->getDataFromTable($info_sql, "information_user_name_1");
  $information_user_name_2_ ->getDataFromTable($info_sql, "information_user_name_2");
  $information_user_img_path_ ->getDataFromTable($info_sql, "information_user_img_path");
  


  $information_user_id_sha1____ =$information_user_id_sha1_->tableList_info[0] ; 
  $information_user_name_1____ =$information_user_name_1_ ->tableList_info[0] ; 
  
  $information_user_name_2____ =$information_user_name_2_ ->tableList_info[0] ; 
  $information_user_img_path____ =$information_user_img_path_->tableList_info[0] ; 

 
 
 


if(give_url()=="index.php"){



  $information_user_img_path____ = str_replace("../","",  $information_user_img_path____);


  if($_SESSION["information_user_id_sha1"]){
    ?>

    <h2>
    <input onkeyup="information_user_key_up()" id="information_user_name_1" type="text" value="<?php echo  $information_user_name_1____ ?>" placeholder="Votre nom" >       

    </h2>
    <img id="data_user_img" onclick="add_picture(this)" title="data_user"  src="<?php echo $information_user_img_path____ ?>" alt="" class="data_user_src">

    
    <h2>
    <input onkeyup="information_user_key_up()" id="information_user_name_2" type="text" value="<?php echo  $information_user_name_2____ ?>" placeholder="Votre nom" >       

    </h2>
    <?php
          }
          else {
?>

<h2 id="information_user_name_1_01"><?php echo  $information_user_name_1____ ?></h2>

<img src="<?php echo $information_user_img_path____ ?>" alt="" class="data_user_src">

<h2 id="information_user_name_2_01"><?php echo  $information_user_name_2____ ?></h2>
<?php 
          }
}
else {


  
  //    <h2>
    ?>

<h2 id="information_user_name_1_01"><?php echo  $information_user_name_1____ ?></h2>

<img src="<?php echo $information_user_img_path____ ?>" alt="" class="data_user_src">

<h2 id="information_user_name_2_01"><?php echo  $information_user_name_2____ ?></h2>

<?php 


}
?>


      <?php 


 

?>
    </div>

    <style>
      .data_user_src{
        max-width: 300px;
        margin: auto;
        margin-bottom: 75px;

        min-height: 200px;
        min-width: 200px;
        background-color: grey;
 width: 100%;
      }
      .card h2 {
        margin-bottom: 50px;
        margin-top: 50px;

      }
   
    </style>