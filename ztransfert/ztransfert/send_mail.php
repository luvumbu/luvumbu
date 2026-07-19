<?php
session_start();
header("Access-Control-Allow-Origin: *");

$file_path     = $_SESSION['file_path'];
$total         = $_SESSION['total'];
$nom_personne  = $_POST['nom_personne'];
$to_form       = $_POST['to']; // Adresse saisie dans le formulaire
$name          = $_SESSION["name"];

function validateEmail($email) {
    return preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email);
}

$activation   = time();
$SERVER_NAME  = $_SERVER['SERVER_NAME'];

// Vérifie si l'email saisi est valide
if (validateEmail($to_form)) {

    // Ajoute l'adresse contact@bokonzi.com comme destinataire principal aussi
    $to = 'contact@bokonzi.com, ' . $to_form;

    $subject = 'Bienvenue sur ' . $SERVER_NAME;
    $link = "https://$SERVER_NAME/we_transfert/all_doc.php/$name";

    $message = '
    <html>
    <head>
      <meta charset="UTF-8">
      <title>Bienvenue sur ' . $SERVER_NAME . '</title>
      <style>
        body {
          font-family: Arial, sans-serif;
          background-color: #f4f4f4;
          margin: 0;
          padding: 0;
        }
        .container {
          max-width: 600px;
          margin: 20px auto;
          background-color: #ffffff;
          padding: 20px;
          border-radius: 8px;
          box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
          background-color: #000000;
          color: #ffffff;
          padding: 10px;
          text-align: center;
          border-radius: 8px 8px 0 0;
        }
        .content {
          font-size: 16px;
          color: #333333;
          line-height: 1.6;
        }
        .btn {
          display: inline-block;
          padding: 10px 20px;
          background-color: #000000;
          color: #ffffff;
          text-decoration: none;
          border-radius: 5px;
          margin-top: 20px;
        }
        .footer {
          text-align: center;
          font-size: 12px;
          color: #888888;
          margin-top: 30px;
        }
      </style>
    </head>
    <body>
      <div class="container">
        <div class="header">
          <h1>Bienvenue sur ' . $SERVER_NAME . '</h1>
        </div>
        <div class="content">
          <p>Bonjour,</p>
          <p>Vous avez reçu un document de <strong>' . htmlspecialchars($nom_personne) . '</strong>.</p>
          <p>Pour voir le fichier, cliquez ici :</p>
          <p><a class="btn" href="' . $link . '" target="_blank">Voir le document</a></p>
        </div>
        <div class="footer">
          <p>&copy; 2024 ' . $SERVER_NAME . ' - Tous droits réservés</p>
        </div>
      </div>
    </body>
    </html>';

    // En-têtes de l'e-mail
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Support <support@$SERVER_NAME>\r\n";
    $headers .= "Reply-To: support@$SERVER_NAME\r\n";

    // Envoi de l'e-mail
    if (mail($to, $subject, $message, $headers)) {
        echo 'E-mail envoyé avec succès.';
    } else {
        echo 'Échec de l\'envoi de l\'e-mail.';
    }

} else {
    $_SESSION["session_info"] = "Adresse mail incorrecte";
    $_SESSION["session_info_coumpt"] = 1;
}
?>
