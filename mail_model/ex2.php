<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    

<?php
$to = 'luvumbu.n@gmail.com';
$subject = 'Sujet de l\'e-mail';
$message = '<p style="color:red">Contenu de l\'e-mail</p>';

$headers = 'From: contact@bokonzi.com';

// Utilisation de la fonction mail() pour envoyer l'e-mail
if (mail($to, $subject, $message, $headers)) {
    echo 'L\'e-mail a été envoyé avec succès.';
} else {
    echo 'Erreur lors de l\'envoi de l\'e-mail.';
}
?>
</body>
</html>