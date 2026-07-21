<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="ztransfert — envoyez vos fichiers volumineux rapidement, gratuitement et en toute sécurité. Jusqu'à 5 Go, sans inscription." />
<title>ztransfert — Envoyez vos fichiers volumineux</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚀</text></svg>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

<style>
    :root {
        --bg: #030b17;
        --bg-2: #061223;
        --circle-bg: #0a1a2f;
        --card: rgba(16, 34, 58, 0.55);
        --card-border: rgba(47, 128, 237, 0.28);
        --text-color: #cbdfff;
        --muted: #8fa8cc;
        --highlight: #2f80ed;
        --accent: #38d6ff;
        --selected-border: #145ab3;
        --glow: 0 0 24px rgba(47, 128, 237, 0.35);
        --grad: linear-gradient(135deg, #2f80ed 0%, #38d6ff 100%);
        --radius: 18px;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    html { scroll-behavior: smooth; }

    body {
        font-family: 'Sora', system-ui, sans-serif;
        background:
            radial-gradient(1200px 600px at 80% -10%, rgba(56, 214, 255, 0.10), transparent 60%),
            radial-gradient(900px 500px at 0% 10%, rgba(47, 128, 237, 0.14), transparent 55%),
            var(--bg);
        color: var(--text-color);
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
        overflow-x: hidden;
    }

    a { text-decoration: none; color: inherit; }

    .wrap { max-width: 1140px; margin: 0 auto; padding: 0 22px; }

    /* ---------- Header ---------- */
    header {
        position: sticky;
        top: 0;
        z-index: 50;
        backdrop-filter: blur(14px);
        background: rgba(3, 11, 23, 0.72);
        border-bottom: 1px solid rgba(47, 128, 237, 0.18);
    }
    .nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 66px;
    }
    .brand {
        font-weight: 800;
        font-size: 1.25rem;
        letter-spacing: -0.5px;
        display: flex;
        align-items: center;
        gap: 9px;
    }
    .brand .dot { color: var(--accent); }
    .nav-links { display: flex; gap: 26px; align-items: center; }
    .nav-links a {
        color: var(--muted);
        font-size: 0.95rem;
        transition: color 0.2s;
    }
    .nav-links a:hover { color: var(--text-color); }
    .nav-cta {
        background: var(--grad);
        color: #fff !important;
        padding: 9px 18px;
        border-radius: 999px;
        font-weight: 600;
        box-shadow: var(--glow);
    }
    .nav-admin {
        padding: 8px 16px;
        border-radius: 999px;
        font-weight: 600;
        border: 1px solid var(--highlight);
        color: var(--highlight) !important;
    }
    .nav-admin:hover {
        background: rgba(47, 128, 237, 0.12);
        color: var(--text-color) !important;
    }

    /* ---------- Hero ---------- */
    .hero {
        display: grid;
        grid-template-columns: 1.05fr 0.95fr;
        gap: 48px;
        align-items: center;
        padding: 92px 0 70px;
    }
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.82rem;
        color: var(--accent);
        background: rgba(56, 214, 255, 0.08);
        border: 1px solid rgba(56, 214, 255, 0.25);
        padding: 6px 14px;
        border-radius: 999px;
        margin-bottom: 22px;
    }
    .badge .pulse {
        width: 8px; height: 8px; border-radius: 50%;
        background: var(--accent);
        box-shadow: 0 0 0 0 rgba(56, 214, 255, 0.6);
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%   { box-shadow: 0 0 0 0 rgba(56, 214, 255, 0.55); }
        70%  { box-shadow: 0 0 0 10px rgba(56, 214, 255, 0); }
        100% { box-shadow: 0 0 0 0 rgba(56, 214, 255, 0); }
    }
    .hero h1 {
        font-size: clamp(2.1rem, 5vw, 3.4rem);
        line-height: 1.1;
        font-weight: 800;
        letter-spacing: -1.2px;
        margin-bottom: 18px;
    }
    .hero h1 .grad {
        background: var(--grad);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .hero p.lead {
        font-size: 1.12rem;
        color: var(--muted);
        max-width: 520px;
        margin-bottom: 30px;
    }
    .cta-row { display: flex; gap: 14px; flex-wrap: wrap; align-items: center; }
    .btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 15px 30px;
        background: var(--grad);
        color: #fff;
        font-weight: 700;
        font-size: 1.05rem;
        border-radius: 999px;
        box-shadow: var(--glow);
        border: none;
        cursor: pointer;
        font-family: inherit;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .btn-primary:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 12px 30px rgba(47, 128, 237, 0.45); }
    .btn-primary:active { transform: translateY(0) scale(0.99); }
    .btn-ghost {
        padding: 15px 24px;
        border-radius: 999px;
        border: 1px solid var(--card-border);
        color: var(--text-color);
        font-weight: 600;
        transition: background 0.2s, border-color 0.2s;
    }
    .btn-ghost:hover { background: rgba(47, 128, 237, 0.10); border-color: var(--highlight); }

    .trust {
        display: flex;
        gap: 22px;
        flex-wrap: wrap;
        margin-top: 30px;
        color: var(--muted);
        font-size: 0.9rem;
    }
    .trust span { display: inline-flex; align-items: center; gap: 7px; }

    /* ---------- Upload card (fonctionnel) ---------- */
    .hero-visual { position: relative; display: flex; justify-content: center; }
    .upload-card {
        width: 100%;
        max-width: 400px;
        background: var(--card);
        border: 1px solid var(--card-border);
        border-radius: 22px;
        padding: 26px;
        box-shadow: 0 30px 60px rgba(0, 0, 0, 0.45), var(--glow);
        backdrop-filter: blur(8px);
    }
    .up-status {
        min-height: 1.2em;
        text-align: center;
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.85rem;
        color: var(--accent);
        margin-bottom: 14px;
    }
    .up-status:empty { display: none; }
    .up-drop {
        display: block;
        border: 2px dashed var(--selected-border);
        border-radius: 14px;
        padding: 34px 18px;
        text-align: center;
        margin-bottom: 16px;
        background: rgba(47, 128, 237, 0.05);
        cursor: pointer;
        transition: border-color 0.25s, background 0.25s;
    }
    .up-drop:hover { border-color: var(--highlight); background: rgba(47, 128, 237, 0.12); }
    .up-drop .icon { font-size: 2.4rem; display: block; margin-bottom: 10px; }
    .up-drop strong { display: block; margin-bottom: 4px; }
    .up-drop small { color: var(--muted); }
    #file-input { display: none; }
    .up-filename {
        min-height: 1.3em;
        text-align: center;
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.82rem;
        color: var(--accent);
        margin-bottom: 16px;
        word-break: break-all;
    }
    .up-send { width: 100%; }

    /* ---------- Sections ---------- */
    section.block { padding: 70px 0; }
    .section-head { text-align: center; max-width: 640px; margin: 0 auto 48px; }
    .section-head h2 {
        font-size: clamp(1.7rem, 4vw, 2.3rem);
        font-weight: 800;
        letter-spacing: -0.8px;
        margin-bottom: 12px;
    }
    .section-head p { color: var(--muted); }

    .features {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
    }
    .feature {
        background: var(--card);
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        padding: 26px 22px;
        transition: transform 0.28s ease, border-color 0.28s ease, box-shadow 0.28s ease;
    }
    .feature:hover { transform: translateY(-6px); border-color: var(--highlight); box-shadow: var(--glow); }
    .feature .ico {
        width: 46px; height: 46px; border-radius: 12px;
        display: grid; place-items: center; font-size: 1.35rem;
        background: rgba(47, 128, 237, 0.12);
        border: 1px solid var(--card-border);
        margin-bottom: 16px;
    }
    .feature h3 { font-size: 1.05rem; margin-bottom: 6px; }
    .feature p { color: var(--muted); font-size: 0.92rem; }

    /* ---------- Steps ---------- */
    .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
    .step {
        position: relative;
        background: var(--card);
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        padding: 28px 24px;
    }
    .step .num {
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--accent);
        background: rgba(56, 214, 255, 0.08);
        border: 1px solid rgba(56, 214, 255, 0.25);
        width: 38px; height: 38px; border-radius: 10px;
        display: grid; place-items: center;
        margin-bottom: 16px;
    }
    .step h3 { font-size: 1.05rem; margin-bottom: 6px; }
    .step p { color: var(--muted); font-size: 0.92rem; }

    /* ---------- Pricing ---------- */
    .pricing { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; align-items: stretch; }
    .plan {
        position: relative;
        background: var(--card);
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        padding: 30px 22px;
        display: flex;
        flex-direction: column;
        transition: transform 0.28s ease, box-shadow 0.28s ease;
    }
    .plan:hover { transform: translateY(-6px); box-shadow: var(--glow); }
    .plan.popular {
        border-color: var(--highlight);
        background: linear-gradient(180deg, rgba(47,128,237,0.14), var(--card));
        box-shadow: var(--glow);
    }
    .plan .tag {
        position: absolute; top: -13px; left: 50%; transform: translateX(-50%);
        background: var(--grad); color: #fff;
        font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px;
        padding: 5px 14px; border-radius: 999px;
        text-transform: uppercase; white-space: nowrap;
    }
    .plan h3 { font-size: 1.1rem; margin-bottom: 6px; }
    .plan .price { font-size: 1.9rem; font-weight: 800; letter-spacing: -1px; margin: 6px 0 4px; }
    .plan .price span { font-size: 0.9rem; font-weight: 400; color: var(--muted); }
    .plan ul { list-style: none; margin: 18px 0 24px; display: grid; gap: 10px; }
    .plan li { display: flex; align-items: center; gap: 9px; color: var(--text-color); font-size: 0.9rem; }
    .plan li .chk { color: var(--accent); font-weight: 700; }
    .plan .pick {
        margin-top: auto;
        text-align: center;
        padding: 12px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 0.95rem;
        border: 1px solid var(--card-border);
        transition: background 0.2s, transform 0.2s;
    }
    .plan .pick:hover { background: rgba(47,128,237,0.12); transform: translateY(-2px); }
    .plan.popular .pick { background: var(--grad); color: #fff; border: none; }

    /* ---------- CTA final ---------- */
    .final-cta {
        text-align: center;
        background: linear-gradient(135deg, rgba(47,128,237,0.16), rgba(56,214,255,0.10));
        border: 1px solid var(--card-border);
        border-radius: 26px;
        padding: 56px 26px;
        margin: 30px 0 10px;
    }
    .final-cta h2 { font-size: clamp(1.6rem, 4vw, 2.2rem); font-weight: 800; letter-spacing: -0.8px; margin-bottom: 12px; }
    .final-cta p { color: var(--muted); margin-bottom: 26px; }

    /* ---------- Footer ---------- */
    footer {
        border-top: 1px solid rgba(47, 128, 237, 0.18);
        padding: 30px 0;
        margin-top: 40px;
        color: var(--muted);
        font-size: 0.88rem;
        text-align: center;
    }

    /* ---------- Responsive ---------- */
    @media (max-width: 980px) {
        .pricing { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 900px) {
        .hero { grid-template-columns: 1fr; gap: 40px; padding: 60px 0 40px; }
        .hero-visual { order: -1; }
        .features { grid-template-columns: repeat(2, 1fr); }
        .steps { grid-template-columns: 1fr; }
        .nav-links a:not(.nav-cta):not(.nav-admin) { display: none; }
    }
    @media (max-width: 560px) {
        .features, .pricing { grid-template-columns: 1fr; }
        .cta-row { flex-direction: column; align-items: stretch; }
        .btn-primary, .btn-ghost { justify-content: center; }
    }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
    }
</style>
</head>
<body>

<header>
  <div class="wrap nav">
    <a href="#" class="brand">🚀 ztransfert<span class="dot">.</span></a>
    <nav class="nav-links">
      <a href="#features">Avantages</a>
      <a href="#how">Comment ça marche</a>
      <a href="#pricing">Offres</a>
      <a href="#upload" class="nav-cta">Envoyer un fichier</a>
      <a href="admin.php" class="nav-admin">🛠️ Admin</a>
    </nav>
  </div>
</header>

<main class="wrap">

  <!-- HERO + UPLOADER -->
  <section class="hero">
    <div>
      <span class="badge"><span class="pulse"></span> Sans inscription · jusqu'à 5 Go</span>
      <h1>Envoyez vos fichiers <span class="grad">volumineux</span> en un instant</h1>
      <p class="lead">Glissez, déposez, partagez. Rapide, sécurisé et gratuit — vos fichiers arrivent à destination sans effort ni compte à créer.</p>
      <div class="cta-row">
        <a href="#upload" class="btn-primary">🚀 Commencer un transfert</a>
        <a href="#how" class="btn-ghost">Comment ça marche</a>
      </div>
      <div class="trust">
        <span>🔒 Chiffré &amp; sécurisé</span>
        <span>⚡ Envoi ultra rapide</span>
        <span>📱 Compatible mobile</span>
      </div>
    </div>

    <div class="hero-visual">
      <div class="upload-card" id="upload">
        <div id="upload-progress" class="up-status"></div>

        <label for="file-input" class="up-drop">
          <span class="icon">📁</span>
          <strong>Déposez votre fichier ici</strong>
          <small>ou cliquez pour parcourir</small>
        </label>
        <input type="file" id="file-input" onchange="name_file(); show_name(this);" />

        <div class="up-filename" id="file-name"></div>

        <button type="button" id="submit-button" class="btn-primary up-send" onclick="disip()">🚀 Envoyer le fichier</button>
      </div>
    </div>
  </section>

  <!-- FEATURES -->
  <section class="block" id="features">
    <div class="section-head">
      <h2>Pourquoi choisir ztransfert ?</h2>
      <p>Tout ce qu'il faut pour transférer sereinement, rien de superflu.</p>
    </div>
    <div class="features">
      <div class="feature">
        <div class="ico">⚡</div>
        <h3>Ultra rapide</h3>
        <p>Envoi par segments optimisé, même pour les très gros fichiers.</p>
      </div>
      <div class="feature">
        <div class="ico">🔒</div>
        <h3>Sécurisé</h3>
        <p>Vos données transitent de façon protégée jusqu'au destinataire.</p>
      </div>
      <div class="feature">
        <div class="ico">💻</div>
        <h3>Simple</h3>
        <p>Une interface épurée : choisir, envoyer, partager le lien.</p>
      </div>
      <div class="feature">
        <div class="ico">📱</div>
        <h3>Multi-appareils</h3>
        <p>Fonctionne aussi bien sur ordinateur que sur mobile.</p>
      </div>
    </div>
  </section>

  <!-- HOW IT WORKS -->
  <section class="block" id="how">
    <div class="section-head">
      <h2>Comment ça marche</h2>
      <p>Trois étapes, quelques secondes, aucun compte requis.</p>
    </div>
    <div class="steps">
      <div class="step">
        <div class="num">01</div>
        <h3>Choisissez un fichier</h3>
        <p>Sélectionnez un document, une vidéo ou une photo depuis votre appareil.</p>
      </div>
      <div class="step">
        <div class="num">02</div>
        <h3>L'envoi se lance</h3>
        <p>Le fichier est découpé en segments et transféré avec une barre de progression.</p>
      </div>
      <div class="step">
        <div class="num">03</div>
        <h3>Partagez le lien</h3>
        <p>Récupérez le lien de téléchargement et envoyez-le à qui vous voulez.</p>
      </div>
    </div>
  </section>

  <!-- PRICING -->
  <section class="block" id="pricing">
    <div class="section-head">
      <h2>Nos offres</h2>
      <p>Commencez gratuitement, montez en puissance quand vous en avez besoin.</p>
    </div>
    <div class="pricing">
      <div class="plan">
        <h3>Gratuit</h3>
        <div class="price">0€ <span>/ mois</span></div>
        <ul>
          <li><span class="chk">✔</span> 5 Go par transfert</li>
          <li><span class="chk">✔</span> 7 jours de stockage</li>
          <li><span class="chk">✔</span> Transferts illimités</li>
        </ul>
        <a href="#upload" class="pick">Commencer</a>
      </div>
      <div class="plan">
        <h3>Essentiel</h3>
        <div class="price">1,99€ <span>/ mois</span></div>
        <ul>
          <li><span class="chk">✔</span> 20 Go par transfert</li>
          <li><span class="chk">✔</span> 14 jours de stockage</li>
          <li><span class="chk">✔</span> Sans publicité</li>
        </ul>
        <a href="https://buy.stripe.com/dRm9AU7JEeMt7FVcpYdnW0H" target="_blank" rel="noopener" class="pick">Choisir Essentiel</a>
      </div>
      <div class="plan popular">
        <span class="tag">Populaire</span>
        <h3>Pro</h3>
        <div class="price">6,99€ <span>/ mois</span></div>
        <ul>
          <li><span class="chk">✔</span> 50 Go par transfert</li>
          <li><span class="chk">✔</span> 30 jours de stockage</li>
          <li><span class="chk">✔</span> Support prioritaire</li>
        </ul>
        <a href="https://buy.stripe.com/cNi28s1lg33LbWb9dMdnW0J" target="_blank" rel="noopener" class="pick">Choisir Pro</a>
      </div>
      <div class="plan">
        <h3>Premium</h3>
        <div class="price">12,99€ <span>/ mois</span></div>
        <ul>
          <li><span class="chk">✔</span> 200 Go par transfert</li>
          <li><span class="chk">✔</span> 90 jours de stockage</li>
          <li><span class="chk">✔</span> Personnalisation &amp; branding</li>
        </ul>
        <a href="https://buy.stripe.com/fZufZifc65bTgcr75EdnW0K" target="_blank" rel="noopener" class="pick">Choisir Premium</a>
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
  <section class="final-cta">
    <h2>Prêt à envoyer votre premier fichier ?</h2>
    <p>Gratuit, sans inscription, en quelques secondes.</p>
    <a href="#upload" class="btn-primary">🚀 Commencer maintenant</a>
  </section>

</main>

<footer>
  © 2025 ztransfert — Tous droits réservés
</footer>

<!-- ---------- Logique d'upload (inchangée : jQuery + upload.js) ---------- -->
<script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
<script src="upload.js"></script>
<script>
    // Enregistre un identifiant (horodatage) en session avant l'envoi — cf. name.php
    function name_file() {
        var t = new Date().getTime();
        var info = new Information("name.php");
        info.add("name", t);
        info.push();
    }
    // Affiche le nom du fichier choisi
    function show_name(input) {
        var el = document.getElementById("file-name");
        el.textContent = (input.files && input.files[0]) ? input.files[0].name : "";
    }
    // Masque le bouton une fois l'envoi lancé
    function disip() {
        document.getElementById("submit-button").style.display = "none";
    }
</script>

</body>
</html>
