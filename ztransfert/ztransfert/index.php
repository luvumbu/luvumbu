<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<link rel="icon" href="https://pbs.twimg.com/profile_images/1244325575659061249/YjvhVutG_400x400.jpg" type="image/gif" sizes="16x16">

<body>

 

  <div id="upload-progress"></div>
 


            <input type="file" name="" id="file-input" class="class1" onclick="name_file()" /><br />
            <input class="class1" type="submit" value="Envoyer" id="submit-button" class="class3" onclick="disip()" />
       
        <script>
            function disip() {
                document.getElementById("submit-button").style.display = "none";
            }

            function name_file() {
                const d = new Date();
                time = d.getTime();
                console.log(time);
                var ok = new Information("name.php"); // création de la classe 
                ok.add("name", time); // ajout de l'information pour lenvoi 
                console.log(ok.info()); // demande l'information dans le tableau
                ok.push(); // envoie l'information au code pkp 
            }
        </script>
      
        <script type="text/javascript" src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
        <script type="text/javascript" src="upload.js"></script>

        <a href="../" class="class4">
        <img width="50" height="50" src="https://img.icons8.com/ios/50/home--v1.png" alt="home--v1"/>
        </a>
    </div>



    <div id="bg_black"></div>
 


    <style>
   
.class1{
    display: none;
}
        .element_1 div {
            padding: 7px;
        }
 
      
    </style>


<style>
    .send-btn {
        background: var(--circle-bg);
        color: var(--text-color);
        font-family: Arial, sans-serif;
        font-size: 1.1rem;
        font-weight: bold;
        padding: 12px 28px;
        border-radius: 50px;
        border: 2px solid var(--selected-border);
        box-shadow: var(--glow);
        text-align: center;
        transition: all 0.25s ease-in-out;
        letter-spacing: 1px;
        user-select: none;
    }

    /* Curseur actif seulement si le bouton n'est pas disabled */
    .send-btn:hover:not([disabled]) {
        cursor: pointer;
        background: var(--highlight);
        color: #fff;
        border-color: var(--highlight);
        box-shadow: var(--pulse-glow);
        transform: translateY(-2px) scale(1.03);
    }

    .send-btn:active:not([disabled]) {
        transform: scale(0.97);
        box-shadow: var(--glow);
    }

    .send-btn[disabled] {
        opacity: 0.4;
        cursor: not-allowed;
        box-shadow: none;
        border-color: #333;
    }
</style>

 

    <link href="https://fonts.googleapis.com/css?family=Anton&display=swap" rel="stylesheet">
</body>

</html>

































<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Futuristic File Upload - No Top Margin</title>
<style>
  :root {
    --bg: #030b17;
    --circle-bg: #0a1a2f;
    --text-color: #cbdfff;
    --highlight: #2f80ed;
    --selected-border: #145ab3;
    --glow: 0 0 20px #2f80edcc;
    --pulse-glow: 0 0 25px #2f80edaa, 0 0 40px #2f80ed88;
  }

  html, body {
    margin: 0;
    padding: 0;
    height: 100vh;
    background-color: var(--bg);
    color: var(--text-color);
    font-family: 'Orbitron', sans-serif;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    user-select: none;
  }

  body {
    padding: 0 20px; /* horizontal padding only */
  }

h2 {
  font-weight: 700;
  font-size: clamp(1.2rem, 3vw, 1.8rem); /* plus petit que précédemment */
  margin-bottom: 20px;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  text-shadow: var(--glow);
  white-space: nowrap;
}


  input[type="file"] {
    display: none;
  }

  label.upload-btn {
    position: relative;
    display: inline-block;
    padding: 18px 50px;
    font-size: clamp(1rem, 2.5vw, 1.3rem);
    font-weight: 700;
    text-transform: uppercase;
    cursor: pointer;
    overflow: hidden;
    box-shadow:
      0 0 8px rgba(47, 128, 237, 0.6),
      inset 0 0 8px rgba(255, 255, 255, 0.1);
    border-radius: 50px;
    background: rgba(47, 128, 237, 0.15);
    color: var(--highlight);
    transition: 
      background-color 0.3s ease, 
      box-shadow 0.3s ease,
      color 0.3s ease;
    white-space: nowrap;
  }

  label.upload-btn:hover {
    background: rgba(47, 128, 237, 0.4);
    box-shadow:
      0 0 15px rgba(47, 128, 237, 0.9),
      inset 0 0 12px rgba(255, 255, 255, 0.2);
    color: var(--bg);
  }

  label.upload-btn::before {
    content: "";
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at center, var(--highlight), transparent 70%);
    opacity: 0;
    transition: opacity 0.5s ease;
    pointer-events: none;
    filter: blur(20px);
    z-index: 0;
    border-radius: 50px;
  }

  label.upload-btn:hover::before {
    opacity: 1;
  }

  label.upload-btn > span {
    position: relative;
    z-index: 1;
  }

  .file-name {
    margin-top: 15px;
    font-family: 'Courier New', Courier, monospace;
    font-size: clamp(0.9rem, 2vw, 1.1rem);
    color: var(--highlight);
    letter-spacing: 0.05em;
    min-height: 1.4em;
    user-select: text;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 90vw;
  }

  button.send-btn {
    margin-top: 30px;
    padding: 18px 60px;
    font-size: clamp(1rem, 2.5vw, 1.2rem);
    font-weight: 700;
    cursor: pointer;
    border: 1.5px solid transparent;
    border-image: linear-gradient(45deg, #2f80ed, #4facfe);
    border-image-slice: 1;
    border-radius: 50px;
    background: rgba(47, 128, 237, 0.15);
    color: var(--highlight);
    box-shadow:
      0 0 8px rgba(47, 128, 237, 0.6),
      inset 0 0 8px rgba(255, 255, 255, 0.1);
    transition: 
      background-color 0.3s ease, 
      box-shadow 0.3s ease,
      color 0.3s ease,
      opacity 0.3s ease;
    white-space: nowrap;
  }

  button.send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    box-shadow: none;
    background: transparent;
    border-image: none;
    border: 2px solid var(--highlight);
    color: var(--highlight);
  }

  button.send-btn:not(:disabled):hover {
    background: rgba(47, 128, 237, 0.4);
    box-shadow:
      0 0 15px rgba(47, 128, 237, 0.9),
      inset 0 0 12px rgba(255, 255, 255, 0.2);
    color: var(--bg);
  }

  label.upload-btn img {
    filter: drop-shadow(0 0 6px var(--highlight));
    transition: filter 0.3s ease;
    vertical-align: middle;
    animation: floatUpDown 3s ease-in-out infinite;
  }
  label.upload-btn img:hover {
    filter: drop-shadow(0 0 15px var(--highlight)) drop-shadow(0 0 10px var(--highlight));
  }

  @keyframes floatUpDown {
    0%, 100% {
      transform: translateY(0);
    }
    50% {
      transform: translateY(-15px);
    }
  }

  @media (max-width: 400px) {
    label.upload-btn {
      padding: 14px 30px;
    }
    button.send-btn {
      padding: 14px 40px;
    }
  }
</style>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&display=swap" rel="stylesheet" />
</head>
<body>

<h2>Step 1: Upload File</h2>
<input type="file" id="upload" />


<label for="file-input" class="upload-btn" aria-label="Select a file" title="Select a file">
  <img width="100" height="100" src="https://img.icons8.com/isometric-line/100/upload.png" alt="upload"/>
</label>

<div class="file-name" aria-live="polite"></div>

<h2>Step 2: Send File</h2>

<label for="submit-button">
    <div class="send-btn" >Send File</div>
</label>
</body>
</html>
