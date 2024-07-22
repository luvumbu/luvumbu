 
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
</html>


 
<div class="index_class_1">
  <h1>


  <div>
    <p class="index_class_2">
      <b id="titre_page1"></b>
    </p>
    <p>
      Avec <b id="titre_page2">Bokonzi</b>, partagez et restez en
      contact avec votre entourage.<!-- <a id="listt_projet" href="blog.php">Voir tous les projet</a> -->
      <div id="cookie_info_id"></div>
    </p>
  </div>
  </h1>
  <div class="index_class_3" id="form_1">
    <div>

    <p>Entrez votre adresse mail</p>
    <p id="info_err" class="display_none">Pas d'adresse mail indiqué</p>
      <input type="text" class="color_btn_1" id="information_user_login" placeholder="Adresse e-mail ou numéro de tél."> 
      <input type="password" class="color_btn_1" id="information_user_password" placeholder="Mot de passe">

      <br />
      <input type="button" value="Connexion" title="Connexion" id="btn_connexion" class="index_class_4 cursor_pointer"
        onclick="information_user_btn(this)">
   <!--   <input type="button" value="Inscription" title="Inscription" id="btn_inscription"
        class="index_class_5 cursor_pointer" onclick="information_user_btn(this)">

-->
<div style="color:blue;text-decoration:underline;cursor:pointer" id="form_forgot_password_id"  onclick="form_forgot_password_onclick()">Mot de passe oublié</div>

    </div>
    
  </div>
  
</div> 

</div>

<div class="alert alert-primary display_none" role="alert" id="information_user_btn_info"></div>
<div id="login_user_information" style="margin-bottom:150px"></div>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
  integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
 
 
 
 
<div id="information_user_id_sha1_info"> </div>

 

 <?php 
    //  include("blog.php");
 ?>

 
 
</body>
 
 <?php 


 
 

if( $_SERVER['SERVER_NAME']=="localhost"){
?>
<script>
  document.getElementById("form_forgot_password_id").className="display_none" ; 
 

  
</script>


<?php 
}
 
?>
<div class="display_none" id="SERVER_NAME"><?php echo $_SERVER['SERVER_NAME'] ?></div>
 
<script>
 // document.getElementById("titre_page1").innerHTML =document.getElementById("SERVER_NAME").innerHTML;
 // document.getElementById("titre_page2").innerHTML =document.getElementById("SERVER_NAME").innerHTML; 
</script>
<?php 
    require_once 'form_log_dbd_user_on_link.php' ; 
    require_once 'form_log_dbd_user_on_css.php' ; 
    require_once 'form_log_dbd_user_on_js.php' ; 
?>