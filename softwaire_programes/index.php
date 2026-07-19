<?php
/* ─────────────────────────────────────────────────────────────
   MES PROGRAMMES  —  portail cliquable
   ─────────────────────────────────────────────────────────────
   Pour AJOUTER un dossier : copie une ligne dans $programmes.
   Pour CHANGER l'icône     : mets le chemin de l'image dans 'icone'
                              (dépose le fichier dans assets/icons/).
   Formats d'image acceptés : PNG, SVG, JPG, WEBP…
   Si l'image est absente, la 1re lettre du nom s'affiche à la place.
   ───────────────────────────────────────────────────────────── */

$programmes = [
    [
        'nom'   => 'YouTube Downloader',
        'desc'  => 'Télécharger des vidéos YouTube — Node.js',
        'url'   => 'https://github.com/luvumbu/youtube_dowloader_node',
        'icone' => 'assets/icons/youtube.png',
    ],
    // Exemple pour ajouter un dossier — décommente et adapte :
    // [
    //     'nom'   => 'Mon autre app',
    //     'desc'  => 'Petite description',
    //     'url'   => 'https://…',
    //     'icone' => 'assets/icons/mon-logo.png',
    // ],
];

function initiale(string $nom): string {
    return mb_strtoupper(mb_substr(trim($nom), 0, 1) ?: '?');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Mes programmes</title>
    <style>
        :root {
            --accent: #7c5cff;
            --accent-2: #22d3ee;
            --yt: #ff0033;
            --bg-1: #07070f;
            --bg-2: #14122a;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 56px 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #f5f5fa;
            background: radial-gradient(1200px 600px at 20% -10%, #2a1f52 0%, transparent 60%),
                        radial-gradient(1000px 500px at 100% 110%, #0f3448 0%, transparent 55%),
                        linear-gradient(160deg, var(--bg-1), var(--bg-2));
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image: linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(circle at 50% 30%, #000 0%, transparent 78%);
            -webkit-mask-image: radial-gradient(circle at 50% 30%, #000 0%, transparent 78%);
        }
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .5;
            z-index: 0;
            animation: float 13s ease-in-out infinite;
        }
        .orb.a { width: 360px; height: 360px; background: var(--accent);   top: -120px; left: -90px; }
        .orb.b { width: 300px; height: 300px; background: var(--accent-2); bottom: -110px; right: -70px; animation-delay: -5s; }

        @keyframes float {
            0%,100% { transform: translateY(0) scale(1); }
            50%     { transform: translateY(36px) scale(1.1); }
        }

        /* ── En-tête ── */
        .head {
            position: relative;
            z-index: 1;
            text-align: center;
            margin-bottom: 48px;
            animation: rise .7s cubic-bezier(.16,1,.3,1) both;
        }
        .head h1 {
            font-size: clamp(1.9rem, 4vw, 2.6rem);
            font-weight: 800;
            letter-spacing: -.5px;
            background: linear-gradient(90deg, #fff, #cdbcff 60%, #9be8f5);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .head p {
            margin-top: 12px;
            color: rgba(245,245,250,.6);
            font-size: 1.05rem;
        }

        /* ── Grille de cartes ── */
        .grid {
            position: relative;
            z-index: 1;
            width: min(100%, 960px);
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 26px;
        }

        .card {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 18px;
            padding: 40px 30px 34px;
            border-radius: 24px;
            text-decoration: none;
            color: inherit;
            background: rgba(255,255,255,.05);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: 0 24px 60px rgba(0,0,0,.4);
            transition: transform .22s cubic-bezier(.16,1,.3,1), box-shadow .22s ease;
            animation: rise .7s cubic-bezier(.16,1,.3,1) both;
        }
        .card::before {
            content: "";
            position: absolute;
            inset: 0;
            padding: 1.5px;
            border-radius: inherit;
            background: linear-gradient(140deg, rgba(255,255,255,.35), rgba(255,255,255,.04) 40%);
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            mask-composite: exclude;
            transition: background .3s ease;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 34px 80px rgba(124,92,255,.35);
        }
        .card:hover::before {
            background: conic-gradient(from 0deg, var(--accent), var(--accent-2), var(--yt), var(--accent));
        }

        /* ── Icône / logo ── */
        .icone {
            width: 92px;
            height: 92px;
            border-radius: 22px;
            display: grid;
            place-items: center;
            background: linear-gradient(150deg, rgba(124,92,255,.25), rgba(34,211,238,.18));
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.12), 0 10px 26px rgba(0,0,0,.35);
            overflow: hidden;
        }
        .icone img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 16px;
        }
        .icone .fallback {
            display: none;
            font-size: 2.4rem;
            font-weight: 800;
            color: #fff;
        }
        .icone.no-img img { display: none; }
        .icone.no-img .fallback { display: block; }

        .card h2 {
            font-size: 1.25rem;
            font-weight: 700;
        }
        .card p {
            font-size: .95rem;
            line-height: 1.55;
            color: rgba(245,245,250,.62);
        }

        .go {
            margin-top: 6px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: .9rem;
            font-weight: 600;
            color: var(--accent-2);
            transition: gap .2s ease;
        }
        .card:hover .go { gap: 14px; }

        .vide {
            position: relative; z-index: 1;
            color: rgba(245,245,250,.5);
            text-align: center;
        }

        footer {
            position: relative;
            z-index: 1;
            margin-top: 54px;
            font-size: .82rem;
            color: rgba(245,245,250,.4);
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(26px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="orb a"></div>
    <div class="orb b"></div>

    <header class="head">
        <h1>Mes programmes</h1>
        <p>Choisissez un dossier à ouvrir</p>
    </header>

    <?php if (empty($programmes)): ?>
        <p class="vide">Aucun programme pour l'instant.</p>
    <?php else: ?>
    <main class="grid">
        <?php foreach ($programmes as $i => $p): ?>
        <a class="card" href="<?= htmlspecialchars($p['url']) ?>" style="animation-delay: <?= 0.06 * $i ?>s">
            <span class="icone">
                <img src="<?= htmlspecialchars($p['icone']) ?>"
                     alt="<?= htmlspecialchars($p['nom']) ?>"
                     onerror="this.parentElement.classList.add('no-img')">
                <span class="fallback"><?= htmlspecialchars(initiale($p['nom'])) ?></span>
            </span>
            <h2><?= htmlspecialchars($p['nom']) ?></h2>
            <?php if (!empty($p['desc'])): ?>
                <p><?= htmlspecialchars($p['desc']) ?></p>
            <?php endif; ?>
            <span class="go">
                Ouvrir
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14M13 6l6 6-6 6"/>
                </svg>
            </span>
        </a>
        <?php endforeach; ?>
    </main>
    <?php endif; ?>

    <footer>Portail Luvumbu</footer>
</body>
</html>
