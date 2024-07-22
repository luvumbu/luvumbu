<script>
  const form_log_mail = document.getElementById("form_log_mail") ; 
const form_log_password = document.getElementById("form_log_password") ; 

const path_form_log_onclick ="../exe_off/php/form_log_onclick.php" ; 

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
</script>