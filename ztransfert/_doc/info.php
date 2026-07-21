<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Explication simple pour l'utilisateur</title>
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
    background-color: var(--bg);
    color: var(--text-color);
    font-family: Arial, sans-serif;
    margin: 30px;
    line-height: 1.5;
  }
  h1 {
    color: var(--highlight);
    text-align: center;
    text-shadow: var(--glow);
  }
  h2 {
    color: var(--highlight);
    margin-top: 40px;
    text-shadow: var(--glow);
  }
  ol {
    background-color: var(--circle-bg);
    padding: 20px 30px;
    border-radius: 10px;
    box-shadow: var(--glow);
    max-width: 600px;
    margin: 20px auto;
  }
  ol li {
    margin-bottom: 15px;
  }
  p {
    max-width: 600px;
    margin: 20px auto;
  }
  strong {
    color: var(--highlight);
  }
</style>
</head>
<body>

<h1>Comment fonctionne le transfert de fichier avec ton nom ?</h1>

<ol>
  <li><strong>Tu choisis un fichier à envoyer</strong><br>
      Sélectionne un fichier sur ton ordinateur, comme une photo ou un document.</li>
  <li><strong>Tu entres ton nom</strong><br>
      Indique ton nom dans le champ prévu pour pouvoir associer ton fichier.</li>
  <li><strong>Le fichier est envoyé en plusieurs petits morceaux</strong><br>
      Le fichier est découpé en petits bouts pour éviter les erreurs pendant l’envoi.</li>
  <li><strong>Ton nom est enregistré sur le serveur</strong><br>
      Ton nom est gardé en mémoire pour associer ton fichier à toi.</li>
  <li><strong>Tu vois la progression de l’envoi</strong><br>
      Une barre ou un message te montre combien du fichier a déjà été envoyé.</li>
  <li><strong>Une fois l’envoi terminé, tu es redirigé vers une page de confirmation</strong><br>
      Tu arrives sur une page qui confirme que tout a bien été reçu.</li>
</ol>

<p><strong>Pourquoi c’est utile ?</strong></p>
<p>Le système rend l’envoi de fichiers fiable, simple et personnalisé avec ton nom, pour que tu sois sûr que tout fonctionne bien.</p>

</body>
</html>
