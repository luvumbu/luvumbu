<?php
session_start();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Envoi réussi — ztransfert</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚀</text></svg>">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<h1>Envoi reussi</h1>

<script>
class Information {
	constructor(link) {
		this.link = link;
		this.identite = new FormData();
		this.req = new XMLHttpRequest();
		this.identite_tab = [
		];
	}
	info() {
		return this.identite_tab; 
	}
	add(info, text){
		this.identite_tab.push([info, text]); 
	}
	push(){
		for(var i = 0 ; i < this.identite_tab.length ; i++){
			console.log(this.identite_tab[i][1]);
			this.identite.append(this.identite_tab[i][0], this.identite_tab[i][1]);		 
		}		
		this.req.open("POST",this.link);
		this.req.send(this.identite);
		console.log(this.req);	 
	}
}




function send() {



    var ok = new Information("send_mail.php"); // création de la classe 
ok.add("nom_personne", document.getElementById("nom_personne").value); // ajout de l'information pour lenvoi 
ok.add("to", document.getElementById("to").value); // ajout d'une deuxieme information denvoi  
console.log(ok.info()); // demande l'information dans le tableau
ok.push(); // envoie l'information au code pkp 
 



const myTimeout = setTimeout(myGreeting, 250);

function myGreeting() {
 window.location.href = "merci.php";

}




}




</script>
<?php 


$SERVER_NAME =  $_SERVER['SERVER_NAME'];

$name = $_SESSION["name"] ; 


?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lien de Téléchargement</title>
    <style>
 body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background: linear-gradient(135deg,rgb(20, 26, 24),rgb(34, 59, 54));
    color: #FFFFFF;
    text-align: center;
    padding: 20px;
    height: 2222px;
    overflow: hidden; /* Hide scrollbars */
}

h2 {
    margin: 20px 0;
    font-size: 1.5rem;
    text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.1);
}

a {
    color: #FFFFFF;
    text-decoration: none;
    font-weight: bold;
    font-size: 1.2rem;
    background: rgba(255, 255, 255, 0.1); /* Blanc transparent */
    padding: 10px 15px;
    border-radius: 5px;
    display: inline-block;
    margin: 10px 0;
    transition: background 0.3s;
}

a:hover {
    background: rgba(255, 255, 255, 0.2); /* Blanc légèrement plus visible */
}

input[type="text"] {
    width: 80%;
    max-width: 400px;
    padding: 10px;
    margin: 10px 0;
    border: 2px solid #FFFFFF;
    border-radius: 5px;
    background: #1F1F1F; /* Fond sombre */
    color: #FFFFFF;
    font-size: 1rem;
    text-align: center;
    outline: none;
    transition: background 0.3s, border 0.3s;
}

input[type="text"]:focus {
    background: #292929; /* Fond un peu plus clair */
}

div[onclick="send()"] {
    display: inline-block;
    margin: 20px 0;
    padding: 10px 20px;
    background: #1F1F1F; /* Gris sombre */
    color: #FFFFFF;
    font-weight: bold;
    font-size: 1.2rem;
    border-radius: 5px;
    cursor: pointer;
    box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s, background 0.3s;
}

div[onclick="send()"]:hover {
    background: #292929;
    transform: translateY(-3px);
}

div[onclick="send()"]:active {
    transform: translateY(0);
}

.menu_p {
    float: left;
    padding: 10px;
    background-color: #1F1F1F; /* Gris sombre */
    color: white;
    border-radius: 5px;
}

    </style>
</head>
<body>
    <h2>Votre lien de téléchargement est</h2>
    <a href="all_doc.php?name=<?= rawurlencode($name) ?>"><?php echo htmlspecialchars($SERVER_NAME . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/all_doc.php?name=' . $name); ?></a>
    <h2>Envoyer lien par mail</h2>
    <input type="text" placeholder="Votre nom" id="nom_personne">
    <input type="text" placeholder="mail contact" id="to">
    <div onclick="send()">ENVOYER</div>
</body>
</html>

</body>
</html>
