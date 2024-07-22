<?php
$to = 'luvumbu.n@gmail.com';
$subject = 'Sujet de l\'e-mail';
$messageContent = '<p style="font-family: Arial, sans-serif; color: #333; background-color: #f4f4f4;">Contenu de l\'e-mail avec une image :</p><img src="https://pbs.twimg.com/media/GItnosZWMAAf6Dj?format=jpg&name=medium" alt="Description de l\'image" style="display: block; margin: 0 auto;">';

$headers = 'From: contact@bokonzi.com' . "\r\n";
$headers .= 'MIME-Version: 1.0' . "\r\n";
$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";

// Utilisation de la fonction mail() pour envoyer l'e-mail
if (mail($to, $subject, $messageContent, $headers)) {
    echo 'L\'e-mail a été envoyé avec succès.';
} else {
    echo 'Erreur lors de l\'envoi de l\'e-mail.';
}
?>
