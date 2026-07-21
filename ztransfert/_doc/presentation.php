<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ztransfert - Envoyez vos fichiers volumineux</title>
<style>
    :root {
        --bg: #030b17;
        --circle-bg: #0a1a2f;
        --text-color: #cbdfff;
        --highlight: #2f80ed;
        --selected-border: #145ab3;
        --glow: 0 0 12px #2f80ed66;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Poppins', sans-serif;
        background: var(--bg);
        color: var(--text-color);
        text-align: center;
    }

    header {
        padding: 20px;
        font-size: 1.5em;
        font-weight: bold;
        background: var(--circle-bg);
        border-bottom: 2px solid var(--highlight);
        box-shadow: var(--glow);
    }

    .hero {
        padding: 80px 20px;
    }
    .hero h1 {
        font-size: 2.5em;
        margin-bottom: 15px;
        color: var(--highlight);
        text-shadow: var(--glow);
    }
    .hero p {
        font-size: 1.2em;
        margin-bottom: 30px;
        color: var(--text-color);
    }

    .upload-box {
        background: var(--circle-bg);
        padding: 30px;
        border-radius: 15px;
        max-width: 400px;
        margin: auto;
        border: 2px dashed var(--selected-border);
        box-shadow: var(--glow);
    }
    .upload-box input {
        display: none;
    }
    .upload-label {
        display: block;
        background: var(--highlight);
        color: white;
        padding: 15px;
        border-radius: 10px;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s;
    }
    .upload-label:hover {
        background: var(--selected-border);
    }

    section {
        padding: 50px 20px;
    }
    .features {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 30px;
    }
    .feature {
        background: var(--circle-bg);
        padding: 20px;
        border-radius: 10px;
        width: 250px;
        border: 1px solid var(--highlight);
        box-shadow: var(--glow);
    }

    .pricing {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 20px;
    }
    .plan {
        background: var(--circle-bg);
        padding: 30px;
        border-radius: 15px;
        width: 250px;
        border: 1px solid var(--highlight);
        box-shadow: var(--glow);
    }
    .plan h3 {
        margin-bottom: 10px;
        color: var(--highlight);
    }
    .plan p {
        font-size: 1.2em;
        font-weight: bold;
    }
 
    /* ... ton CSS existant ... */

    .start-btn {
        display: inline-block;
        padding: 15px 35px;
        margin-top: 20px;
        background: var(--highlight);
        color: white;
        font-size: 1.2em;
        font-weight: bold;
        border-radius: 50px;
        border: none;
        cursor: pointer;
        box-shadow: var(--glow);
        text-decoration: none;
        transition: all 0.3s ease;
    }
    .start-btn:hover {
        background: var(--selected-border);
        transform: scale(1.05);
        box-shadow: 0 0 20px var(--highlight);
    }
    .start-btn:active {
        transform: scale(0.98);
    }
 

    footer {
        padding: 20px;
        background: var(--circle-bg);
        border-top: 2px solid var(--highlight);
    }

    @media(max-width: 768px) {
        .features, .pricing { flex-direction: column; align-items: center; }
    }
</style>
</head>
<body>

<header>🚀 ztransfert</header>

<div class="hero">
    <h1>Envoyez vos fichiers volumineux rapidement</h1>
    <p>Gratuit jusqu'à 5 Go – Sécurisé – Sans inscription</p>
<a href="#upload" class="start-btn">🚀 Commencer</a>

</div>

<section>
    <h2>Pourquoi choisir ztransfert ?</h2>
    <div class="features">
        <div class="feature">⚡ Ultra rapide</div>
        <div class="feature">🔒 Sécurisé</div>
        <div class="feature">💻 Simple d'utilisation</div>
        <div class="feature">📱 Compatible mobile</div>
    </div>
</section>

<section>
    <h2>Nos offres</h2>
    <div class="pricing">
        <div class="plan">
            <h3>Gratuit</h3>
            <p>0€ / mois</p>
            <ul style="list-style:none; padding-top:10px;">
                <li>✔ 5 Go / transfert</li>
                <li>✔ 7 jours de stockage</li>
                <li>✔ Illimité en nombre</li>
            </ul>
        </div>
        <div class="plan">
            <h3>Pro</h3>
            <p>5,99€ / mois</p>
            <ul style="list-style:none; padding-top:10px;">
                <li>✔ 50 Go / transfert</li>
                <li>✔ 30 jours de stockage</li>
                <li>✔ Sans publicité</li>
            </ul>
        </div>
        <div class="plan">
            <h3>Premium</h3>
            <p>14,99€ / mois</p>
            <ul style="list-style:none; padding-top:10px;">
                <li>✔ 200 Go / transfert</li>
                <li>✔ 90 jours de stockage</li>
                <li>✔ Personnalisation & Branding</li>
            </ul>
        </div>
    </div>
</section>

<footer>
    © 2025 ztransfert - Tous droits réservés
</footer>

</body>
</html>
