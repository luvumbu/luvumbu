 
 <script>

const information_user_login = document.getElementById("information_user_login") ; 
const information_user_password = document.getElementById("information_user_password") ; 
const form_1 = document.getElementById("form_1") ; 
const info_err = document.getElementById("info_err") ; 

  function information_user_btn(_this){

  var ok = new Information("exe_on/php/sing_in_on/information_user_btn.php"); // création de la classe 
  ok.add("information_user_login", information_user_login.value); // ajout de l'information pour lenvoi 
  ok.add("information_user_password", information_user_password.value); // ajout d'une deuxieme information denvoi  
  console.log(ok.info()); // demande l'information dans le tableau
  ok.push(); // envoie l'information au code pkp 



  const myTimeout = setTimeout(information_user_id_sha1_info, 250);
  const myTimeout2 = setTimeout(information_user_id_sha1_info, 500);


function information_user_id_sha1_info() {
  Ajax("information_user_id_sha1_info","view/data_user_srcon/information_user_id_sha1_info.php");



}


function information_user_id_sha1_info() {
   location.reload() ; 
}
   
  }


  function form_forgot_password_onclick(){

    //form_1.className="display_none"  ;


    Ajax("form_1","view/on/form2.php");
    
    if(information_user_login.value.length==0){
      info_err.className="info_err" ; 
      info_err.style="display:block" ; 

    }
    else {
      info_err.style="display:none" ; 

    }

  }



 

 </script>
 
<script>

function data_user() {
    console.log("12345787") ; 
  }


</script>