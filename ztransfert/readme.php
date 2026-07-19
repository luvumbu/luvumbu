<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>README - Système d’Upload et Gestion du Nom</title>
    <style>
        :root {
            --bg: #030b17;
            --circle-bg: #0a1a2f;
            --text-color: #cbdfff;
            --highlight: #2f80ed;
            --selected-border: #145ab3;
            --glow: 0 0 12px #2f80ed66;
        }
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
            background-color: var(--bg);
            color: var(--text-color);
        }
        h1, h2 {
            color: var(--highlight);
            text-shadow: var(--glow);
        }
        code {
            background-color: var(--circle-bg);
            padding: 2px 4px;
            border-radius: 4px;
            color: var(--text-color);
        }
        pre {
            background: var(--circle-bg);
            color: var(--text-color);
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            box-shadow: var(--glow);
        }
        ul {
            margin-left: 20px;
        }
        a {
            color: var(--highlight);
            text-decoration: underline;
        }
        hr {
            border: 1px solid var(--selected-border);
            margin: 30px 0;
        }
    </style>
</head>
<body>

<h1>README - Système complet d’upload avec gestion du nom</h1>

<h2>📌 Description</h2>
<p>
Ce projet permet à un utilisateur de transférer un fichier lourd en plusieurs segments vers le serveur, 
tout en associant un <strong>nom</strong> saisi par l’utilisateur.  
Le flux se compose de trois parties :
</p>
<ol>
    <li>HTML + JavaScript pour la sélection du fichier, découpage et envoi en segments.</li>
    <li>PHP (<code>name.php</code>) pour récupérer et stocker le nom dans une session.</li>
    <li>PHP (<code>config.php</code>) pour créer la table <code>we_transfert</code> et insérer le fichier + nom.</li>
</ol>

<hr>

<h2>1️⃣ Partie HTML + JavaScript</h2>
<p>
La partie front-end se charge de :
</p>
<ul>
    <li>Permettre à l’utilisateur de choisir un fichier (<code>#file-input</code>).</li>
    <li>Envoyer le fichier en morceaux de 1 Mo à <code>upload.php</code>.</li>
    <li>Afficher la progression de l’upload dans <code>#upload-progress</code>.</li>
    <li>Rediriger vers <code>config.php</code> une fois l’upload terminé.</li>
</ul>

<h3>Récupération du nom côté client</h3>
<p>
Pour récupérer et envoyer le nom en même temps que le fichier, on peut utiliser la classe <code>Information</code> :
</p>
<pre>
// Exemple : envoyer le nom à name.php AVANT l'upload
var info = new Information("name.php");
info.add("name", document.querySelector("#name-input").value);
info.push();
</pre>
<p>
Ce code envoie le nom via POST vers <code>name.php</code> qui le stocke en session.
</p>

<hr>

<h2>2️⃣ Partie PHP - <code>name.php</code></h2>
<p>
Ce fichier est appelé depuis le front-end pour :
</p>
<ul>
    <li>Récupérer le nom envoyé en POST (<code>$_POST["name"]</code>).</li>
    <li>Le stocker dans <code>$_SESSION["name"]</code> pour pouvoir le réutiliser plus tard.</li>
    <li>Le renvoyer en réponse (utile pour vérifier côté client).</li>
</ul>

<pre>
&lt;?php
session_start();
header("Access-Control-Allow-Origin: *");

$name = $_POST["name"];
echo $name;

$_SESSION["name"] = $name;
?&gt;
</pre>

<p>
Ainsi, une fois que l’utilisateur a envoyé son nom, il est accessible dans toute la session.
</p>

<hr>

<h2>3️⃣ Partie PHP - <code>config.php</code></h2>
<p>
Ce fichier s’exécute après l’upload complet. Il :
</p>
<ul>
    <li>Récupère le nom de la session (<code>$_SESSION["name"]</code>).</li>
    <li>Récupère aussi <code>$_SESSION["file_path"]</code> et <code>$_SESSION["total"]</code> définis par <code>upload.php</code>.</li>
    <li>Crée la table <code>we_transfert</code> si elle n’existe pas, grâce à <code>DatabaseHandler</code>.</li>
    <li>Insère un nouvel enregistrement avec le chemin du fichier, le total et le nom.</li>
    <li>Redirige vers <code>envoi_ok.php</code> pour confirmation.</li>
</ul>

<pre>
&lt;?php
session_start();
echo $_SESSION["name"]; // Nom récupéré ici

require_once "DatabaseHandler.php";

$dbname = "u489596434_marion";
$username = "v3p9r3e@59A";
$table_name = "we_transfert";

$databaseHandler = new DatabaseHandler($dbname, $username);

$columns = [
    "id_transfert" => "INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY",
    "file_path"    => "LONGTEXT NOT NULL",
    "total"        => "LONGTEXT NOT NULL",
    "name"         => "LONGTEXT NOT NULL",
    "date_inscription_user" => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
];

foreach ($columns as $name => $type) {
    $databaseHandler->set_column_names($name);
    $databaseHandler->set_column_types($type);
}

$databaseHandler->add_table($table_name);

$file_path = $_SESSION['file_path'];
$total     = $_SESSION['total'];
$name      = $_SESSION['name'];

$databaseHandler->action_sql(
    "INSERT INTO `we_transfert` (`file_path`,`total`,`name`)
     VALUES ('$file_path','$total','$name')"
);

header("Location: envoi_ok.php");
exit;
?&gt;
</pre>

<hr>

<h2>📥 Récapitulatif du flux</h2>
<ol>
    <li>L’utilisateur saisit son nom et choisit un fichier.</li>
    <li>Le script JS envoie le nom à <code>name.php</code> qui le met dans la session.</li>
    <li>Le fichier est découpé et envoyé morceau par morceau à <code>upload.php</code>.</li>
    <li>Une fois l’upload terminé, JS redirige vers <code>config.php</code>.</li>
    <li><code>config.php</code> récupère le nom depuis la session et l’insère dans la base avec les infos du fichier.</li>
</ol>

<hr>

<h2>💡 Remarques importantes</h2>
<ul>
    <li>Pour éviter les failles XSS et injections SQL, toujours valider et échapper <code>$_POST</code> et les données de session avant utilisation.</li>
    <li>Les gros fichiers nécessitent un réglage adéquat de <code>upload_max_filesize</code> et <code>post_max_size</code> dans <code>php.ini</code>.</li>
    <li>Il est possible de fusionner le nom et le fichier dans un seul appel si on modifie <code>upload.php</code> pour recevoir aussi le nom.</li>
</ul>

</body>
</html>
