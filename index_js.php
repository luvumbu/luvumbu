
<script>
      const information_user_name_1 = document.getElementById("information_user_name_1");
      const information_user_name_2 = document.getElementById("information_user_name_2");
      const form_log_mail = document.getElementById("form_log_mail") ; 
      const form_log_password = document.getElementById("form_log_password") ;
      const path_form_log_onclick ="exe_off/php/form_log_onclick.php" ; 



      function form_log_onclick(_this){
console.log(form_log_mail.value) ; 
console.log(form_log_password.value) ; 
 
var ok = new Information(path_form_log_onclick); // création de la classe 
  ok.add("information_user_login", form_log_mail.value); // ajout de l'information pour lenvoi 
  ok.add("information_user_password", form_log_password.value); // ajout d'une deuxieme information denvoi  
  console.log(ok.info()); // demande l'information dans le tableau
  ok.push(); // envoie l'information au code pkp 






  const myTimeout = setTimeout(xxx, 200);

  function xxx() {
    location.reload();
  }


}








    function destroy(){
        Ajax("destroy","class/destroy.php");
        const myTimeout2 = setTimeout(destroy, 50);
function destroy() {
  location.reload();
}
    }

 function information_user_key_up() {
 console.log(information_user_name_1.value) ; 
 console.log(information_user_name_2.value) ; 
  var ok = new Information("exe_on/php/update/information_user_key_up.php"); // création de la classe 
  ok.add("information_user_name_1", information_user_name_1.value); // ajout de l'information pour lenvoi 
  ok.add("information_user_name_2", information_user_name_2.value); // ajout d'une deuxieme information denvoi  
  console.log(ok.info()); // demande l'information dans le tableau
  ok.push(); // envoie l'information au code pkp 
}

function liste_projet_admin_key_up(_this){
 

  const liste_projet_admin_name1 = document.getElementById("liste_projet_admin_name1_"+_this.title);
  const liste_projet_admin_name2 = document.getElementById("liste_projet_admin_name2_"+_this.title);


   

 
 
  var ok = new Information("exe_on/php/update/liste_projet_admin_key_up.php"); // création de la classe 
  
  ok.add("information_user_id_sha1", _this.title); // ajout de l'information pour lenvoi 
  
  ok.add("liste_projet_admin_name1", liste_projet_admin_name1.value); // ajout de l'information pour lenvoi 

  ok.add("liste_projet_admin_name2", liste_projet_admin_name2.value); // ajout d'une deuxieme information denvoi  
  console.log(ok.info()); // demande l'information dans le tableau
  ok.push(); // envoie l'information au code pkp 
 



}
 

 function add_picture(_this){
 
  var ok = new Information("exe_on/php/info/add_picture.php"); // création de la classe 
  ok.add("liste_projet_admin_id_sha1", _this.id); // ajout de l'information pour lenvoi 
  ok.add("id", _this.title); // ajout de l'information pour lenvoi 


  console.log(ok.info()); // demande l'information dans le tableau
  ok.push(); // envoie l'information au code pkp 
 window.location.href = "add_picture/index.php";
 }


 function data_parent_plus(_this){

  _this.style.display="none" ; 
  var ok = new Information("exe_on/php/insert/data_parent_plus.php"); // création de la classe 
  //ok.add("liste_projet_admin_id_sha1", _this.id); // ajout de l'information pour lenvoi 
  ok.add("id", _this.id); // ajout de l'information pour lenvoi 
  ok.add("title", _this.title); // ajout de l'information pour lenvoi 

 
 
  console.log(ok.info()); // demande l'information dans le tableau
  ok.push(); // envoie l'information au code pkp 

  const myTimeout = setTimeout(xw, 250);

function xw() {
  location.reload() ; 

}


 }

 function liste_projet_admin_insert(_this){
 
 _this.style.display="none" ; 

  var ok = new Information("exe_on/php/insert/liste_projet_admin_insert.php"); // création de la classe 
  //ok.add("liste_projet_admin_id_sha1", _this.id); // ajout de l'information pour lenvoi 
  
  
  
 ok.add("liste_projet_admin_insert", _this.title); // ajout de l'information pour lenvoi 

 
  console.log(ok.info()); // demande l'information dans le tableau
  ok.push(); // envoie l'information au code pkp 

  const myTimeout = setTimeout(xxx, 250);

function xxx() {
  location.reload() ; 
}

 }


 function liste_projet_admin_insert_destroy(){



 
  Ajax("destroy","class/liste_projet_admin_insert_destroy.php");


  const myTimeout = setTimeout(myGreeting, 250);

function myGreeting() {
  location.reload() ; 
}
 }

 function remove_block(_this) {
 



  var ok = new Information("exe_on/php/update/remove_block.php"); // création de la classe 
  ok.add("liste_projet_admin_id_sha1", _this.title); // ajout de l'information pour lenvoi 
  
  console.log(ok.info()); // demande l'information dans le tableau
  ok.push(); // envoie l'information au code pkp 

  const myTimeout = setTimeout(myGreeting, 250);

function myGreeting() {
  location.reload() ; 
}



 }

 function all_liste_projet_admin() {
 

  
  var ok = new Information("class/all_liste_projet_admin.php"); // création de la classe 
  //ok.add("liste_projet_admin_id_sha1", _this.title); // ajout de l'information pour lenvoi 
  
  console.log(ok.info()); // demande l'information dans le tableau
  ok.push(); // envoie l'information au code pkp 

 }


 function data_all_remove(_this){
  console.log(_this.title) ; 
 }

 function remove_block_(_this){
  console.log(_this.title) ; 
  document.getElementById("remove_"+_this.title).className="" ; 

_this.style.display="none" ; 

 }


 function data_all_cookie(_this) {

 
  var ok = new Information("class/data_all_cookie.php"); // création de la classe 
  ok.add("liste_projet_admin_id_sha1", _this.title); // ajout de l'information pour lenvoi 
  
  console.log(ok.info()); // demande l'information dans le tableau
  ok.push(); // envoie l'information au code pkp 


  const myTimeout = setTimeout(myGreeting, 250);

function myGreeting() {
  location.reload() ; 
}
 }

 function add_picture2(_this){
  console.log(_this.id) ;

  

  if(_this.className=="img_background_"){
    _this.className="img_background_2" ; 
  }
  else {
    _this.className="img_background_" ; 
  }
 }



 function data_social_media_click(_this) {

  var ok = new Information("exe_on/php/insert/data_social_media_click.php"); // création de la classe 
  //ok.add("liste_projet_admin_id_sha1", _this.id); // ajout de l'information pour lenvoi 
  ok.add("id", _this.id); // ajout de l'information pour lenvoi 
  ok.add("title", _this.title); // ajout de l'information pour lenvoi 
  ok.push(); // envoie l'information au code pkp 

  _this.style.display="none" ; 
  console.log(_this.title) ; 


  const myTimeout = setTimeout(myTimeout_a, 100);

function myTimeout_a() {
location.reload() ; 
}

 }


 function data_social_media_key_up(_this){
var social_media_name = document.getElementById("name_"+_this.title).value ; 
var social_media_link = document.getElementById("link_"+_this.title).value ; 

 
 

  var ok = new Information("exe_on/php/update/data_social_media_key_up.php"); // création de la classe 
  ok.add("social_media_name", social_media_name); // ajout de l'information pour lenvoi 
  ok.add("social_media_link", social_media_link); // ajout d'une deuxieme information denvoi  
  ok.add("social_media_id_sha1", _this.title); // ajout d'une deuxieme information denvoi  


 
  console.log(ok.info()); // demande l'information dans le tableau
  ok.push(); // envoie l'information au code pkp 
 }

</script>