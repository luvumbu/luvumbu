<?php
 $presentation2_index_php=false ;  

 
/*
$liste_projet_admin_id_ = new DatabaseHandler($username, $password);
$liste_projet_admin_id_sha1_ = new DatabaseHandler($username, $password);
$liste_projet_admin_id_sha1_user_ = new DatabaseHandler($username, $password);
$liste_projet_admin_name1_ = new DatabaseHandler($username, $password);
$liste_projet_admin_name2_ = new DatabaseHandler($username, $password);
$liste_projet_admin_img_path_ = new DatabaseHandler($username, $password);

*/



// partie ok  


 
// partie ok  


?>
 
<body>

 

<div class="row">
  <div class="leftcolumn">
 <?php 
 
require 'model_new_data1.php' ; 





if(give_url()=="index.php"){
  $info_sql = 'SELECT * FROM `liste_projet_admin` WHERE  `liste_projet_admin_id` ="1" ORDER BY  `liste_projet_admin_id` ASC ';
  $presentation2_index_php = true ; 
}
else {
  $info_sql = 'SELECT * FROM `liste_projet_admin` WHERE  `liste_projet_admin_id_sha1` ="'.give_url().'" ORDER BY  `liste_projet_admin_id` ASC ';

}
require 'model_new_data2.php' ; 
require 'model_new_data3.php' ; 




$liste_projet_admin_id_sha1__ = $liste_projet_admin_id_sha1_->tableList_info[0] ; 
 
require 'model_new_data1.php' ; 
$info_sql = 'SELECT * FROM `liste_projet_admin` WHERE  `liste_projet_admin_sha1_parent` ="'.$liste_projet_admin_id_sha1__.'" ORDER BY  `liste_projet_admin_id` ASC ';
require 'model_new_data2.php' ; 
//require 'model_new_data3_1.php' ; 
require 'model_new_data3.php' ; 

 
 
 ?>
  </div>
  <div class="rightcolumn">
<?php  
 

    

 $liste_projet_admin_id_ = new DatabaseHandler($username, $password);
 $liste_projet_admin_id_sha1_ = new DatabaseHandler($username, $password);
 $liste_projet_admin_id_sha1_user_ = new DatabaseHandler($username, $password);
 $liste_projet_admin_name1_ = new DatabaseHandler($username, $password);
 $liste_projet_admin_name2_ = new DatabaseHandler($username, $password);
 $liste_projet_admin_img_path_ = new DatabaseHandler($username, $password);
 $information_user_reg_date_ = new DatabaseHandler($username, $password);

 $info_sql = 'SELECT * FROM `liste_projet_admin` WHERE  `liste_projet_admin_id_sha1` ="' .give_url(). '" ORDER BY  `liste_projet_admin_id_sha1` ASC ';

 
$liste_projet_admin_id_sha1_user_->getDataFromTable($info_sql, "liste_projet_admin_id_sha1_user");
 



$liste_projet_admin_id_sha1_user____ = $liste_projet_admin_id_sha1_user_->tableList_info[0] ; 


 
$information_user_name_1 = new DatabaseHandler($username ,$password);   
$information_user_name_2 = new DatabaseHandler($username ,$password);     
$information_user_img_path = new DatabaseHandler($username ,$password);     

$information_user_id_sha1_root = new DatabaseHandler($username ,$password);     









if(give_url()=="index.php"){
  $info_sql ='SELECT * FROM `information_user` WHERE 1 LIMIT 1 ' ; 
  
}
else {
  $info_sql ='SELECT * FROM `information_user` WHERE `information_user_id_sha1`="'.$liste_projet_admin_id_sha1_user____.'" ' ; 

}



$information_user_name_1->getDataFromTable($info_sql,"information_user_name_1");
$information_user_name_2->getDataFromTable($info_sql,"information_user_name_2");
$information_user_img_path->getDataFromTable($info_sql,"information_user_img_path");



$information_user_id_sha1_root->getDataFromTable($info_sql,"information_user_id_sha1");


 



$information_user_name_1___ = $information_user_name_1->tableList_info[0] ; 
$information_user_name_2___ = $information_user_name_2->tableList_info[0] ; 
$information_user_name_1___ = $information_user_name_1->tableList_info[0] ; 
$information_user_img_path___ =$information_user_img_path->tableList_info[0] ; 

 


 
 
      require 'data_user.php' ; 
      require 'data_all.php' ; 
      require 'data_social_media.php' ; 



      if(give_url()!="index.php"){

?>

<div class="card">

<a href="../index.php">
  <h3>
    <img width="50" height="50" src="https://img.icons8.com/ios/50/home--v1.png" alt="home--v1"/>
  </h3>
 </a>
</div>

<?php


      }

?>
 

  </div>
</div>

<div class="footer">
  <h2></h2>
</div>

</body>


 

<script>
const information_user_name_1_01 =  document.getElementById("information_user_name_1_01").innerText;
const information_user_name_2_01 =  document.getElementById("information_user_name_2_01").innerText;

 


 console.log(information_user_name_1_01) ; 
 console.log(information_user_name_2_01) ; 

const information_user_reg_date_ = document.getElementsByClassName("information_user_reg_date_2") ; 

 


for(var a = 0 ; a<information_user_reg_date_.length ; a ++) {
   

  information_user_reg_date_[a].innerText ="crée par : "+information_user_name_1_01  ; 
}
 
</script>