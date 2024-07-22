<?php
     $headers ='From: "nom"<luvumbu.n@gmail.com>'."\n";
     $headers .='Reply-To: aluvumbu.n@gmail.com'."\n";
     $headers .='Content-Type: text/html; charset="iso-8859-1"'."\n";
     $headers .='Content-Transfer-Encoding: 8bit';

     $message ='<html><head><title>Un titre ici</title></head><body>Un message de test</body></html>';

     if(mail('adresse_du_destinataire@fai.fr', 'Sujet', $message, $headers))
     {
          echo 'Le message a été envoyé';
     }
     else
     {
          echo 'Le message n\'a pu être envoyé';
     }
?>
