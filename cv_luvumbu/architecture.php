<?php
/**
 * Architecture du projet — vue d'ensemble visuelle et vivante :
 * statistiques en direct, arborescence des fichiers, modèle de données,
 * pages, API et fonctionnalités. Accessible après connexion.
 */

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/guard.php';

ensure_ready();

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/cv.php';
require __DIR__ . '/includes/applications.php';
require_login();

function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES); }

/** Compte sécurisé (retourne null si la table n'existe pas). */
function safe_count(string $sql): ?int
{
    try { return (int) db()->query($sql)->fetchColumn(); }
    catch (Throwable $e) { return null; }
}

/* ---- Statistiques en direct ---- */
try { ensure_applications_table(); } catch (Throwable $e) {} // garantit users/cvs/applications
$stats = [
    ['👤', 'Utilisateurs',  safe_count("SELECT COUNT(*) FROM users")],
    ['📄', 'CV',            safe_count("SELECT COUNT(*) FROM cvs")],
    ['📨', 'Candidatures',  safe_count("SELECT COUNT(*) FROM applications")],
    ['🔑', 'Clés API actives', safe_count("SELECT COUNT(*) FROM api_keys WHERE revoked_at IS NULL")],
];

/* ---- Modèle de données ---- */
$tables = [
    'users' => [
        'desc' => "Comptes de connexion. Le login = identifiants de la base de données.",
        'cols' => [
            ['id', 'INT UNSIGNED', 'PK'],
            ['username', 'VARCHAR(50)', 'unique'],
            ['password_hash', 'VARCHAR(255)', ''],
            ['created_at', 'DATETIME', ''],
        ],
    ],
    'cvs' => [
        'desc' => "Les CV créés (via l'app ou l'API). Liés à un utilisateur.",
        'cols' => [
            ['id', 'INT UNSIGNED', 'PK'],
            ['user_id', 'INT UNSIGNED', 'FK → users'],
            ['full_name / title / email / phone', 'VARCHAR', ''],
            ['summary / skills / experience / education', 'TEXT', ''],
            ['created_at / updated_at', 'DATETIME', ''],
        ],
    ],
    'applications' => [
        'desc' => "Candidatures : quel CV envoyé à quelle entreprise, réponse et relance programmée.",
        'cols' => [
            ['id', 'INT UNSIGNED', 'PK'],
            ['user_id', 'INT UNSIGNED', 'FK → users'],
            ['cv_id', 'INT UNSIGNED', 'FK → cvs (null possible)'],
            ['company', 'VARCHAR(150)', ''],
            ['sent_at', 'DATE', ''],
            ['status', 'VARCHAR(20)', 'en_attente / positive / negative'],
            ['followup', 'TINYINT(1)', 'relance ?'],
            ['followup_date', 'DATE', 'date de relance'],
        ],
    ],
    'api_keys' => [
        'desc' => "Clés API pour accéder aux CV à distance, avec permissions (scopes).",
        'cols' => [
            ['id', 'INT UNSIGNED', 'PK'],
            ['user_id', 'INT UNSIGNED', 'FK → users'],
            ['label', 'VARCHAR(100)', ''],
            ['scopes', 'VARCHAR(255)', 'cv:read, cv:write'],
            ['key_prefix / key_hash', 'VARCHAR/CHAR', ''],
            ['last_used_at / revoked_at', 'DATETIME', ''],
        ],
    ],
];

/* ---- Pages de l'application ---- */
$pages = [
    ['install.php', 'Assistant d\'installation', 'Configure la base, crée le schéma et le compte de connexion. Auto-réparation.'],
    ['index.php', 'Point d\'entrée', 'Redirige vers config / connexion / tableau de bord selon l\'état.'],
    ['login.php', 'Connexion', 'Authentification avec les identifiants de la base.'],
    ['dashboard.php', 'Tableau de bord', 'Suivi des candidatures : entreprise, réponse, relance programmée.'],
    ['mes_cv.php', 'Mes CV', 'Liste, consultation et création de CV.'],
    ['cv_view.php', 'Aperçu CV', 'Rendu façon document A4, imprimable / exportable en PDF (thèmes).'],
    ['parametres.php', 'Paramètres', 'Génération et gestion des clés API (permissions, révocation).'],
    ['logout.php', 'Déconnexion', 'Ferme la session.'],
];

/* ---- Modules (includes) ---- */
$modules = [
    ['db.php', 'Connexion PDO, config, santé de la base, réparations de schéma.'],
    ['guard.php', 'Garde-fou : redirige vers l\'installation si la base ne répond pas.'],
    ['auth.php', 'Session, connexion/déconnexion, jeton CSRF.'],
    ['cv.php', 'Création / liste / lecture des CV.'],
    ['applications.php', 'Candidatures : suivi, statuts, relances programmées.'],
    ['api_keys.php', 'Génération, vérification et permissions des clés API.'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <title>Architecture — CV Luvumbu</title>
    <style>
        :root{
            --bg:#0b1220; --panel:#111c33; --panel2:#0e1830; --line:#22304f;
            --ink:#e6edf7; --muted:#8da2c0; --accent:#4f8cff; --accent2:#22d3ee;
            --green:#34d399; --amber:#fbbf24; --pink:#f472b6; --violet:#a78bfa;
        }
        *{box-sizing:border-box;}
        body{
            margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
            background:
                radial-gradient(1200px 600px at 10% -10%, #1b2c52 0%, transparent 55%),
                radial-gradient(900px 500px at 110% 10%, #143042 0%, transparent 50%),
                var(--bg);
            color:var(--ink); min-height:100vh;
        }
        a{color:var(--accent);text-decoration:none;}
        a:hover{text-decoration:underline;}
        .topbar{
            display:flex;justify-content:space-between;align-items:center;
            padding:16px 28px;background:rgba(8,14,28,.7);backdrop-filter:blur(6px);
            border-bottom:1px solid var(--line);position:sticky;top:0;z-index:5;
        }
        .topbar .brand{font-weight:800;letter-spacing:.02em;}
        .topbar .brand span{color:var(--accent2);}
        .topbar nav a{color:#bcd0ef;margin-left:18px;font-size:.92rem;}
        .wrap{max-width:1120px;margin:0 auto;padding:34px 22px 60px;}
        .hero h1{font-size:2rem;margin:0 0 6px;}
        .hero p{color:var(--muted);margin:0 0 26px;max-width:680px;line-height:1.5;}
        .badge-stack{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:30px;}
        .tag{font-size:.78rem;padding:5px 11px;border-radius:999px;border:1px solid var(--line);
             background:var(--panel);color:#cdd9ef;}

        /* Stats */
        .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:34px;}
        .statcard{
            background:linear-gradient(160deg,var(--panel),var(--panel2));
            border:1px solid var(--line);border-radius:16px;padding:18px 20px;position:relative;overflow:hidden;
        }
        .statcard .ic{font-size:1.4rem;}
        .statcard .num{font-size:2rem;font-weight:800;margin-top:6px;}
        .statcard .lab{color:var(--muted);font-size:.85rem;}
        .statcard::after{content:"";position:absolute;right:-30px;top:-30px;width:90px;height:90px;
            background:radial-gradient(circle,rgba(79,140,255,.25),transparent 70%);}

        .grid{display:grid;grid-template-columns:1.05fr 1fr;gap:22px;}
        @media(max-width:880px){.grid{grid-template-columns:1fr;}}
        .panel{
            background:linear-gradient(160deg,var(--panel),var(--panel2));
            border:1px solid var(--line);border-radius:18px;padding:22px;margin-bottom:22px;
        }
        .panel h2{margin:0 0 16px;font-size:1.15rem;display:flex;align-items:center;gap:9px;}
        .panel h2 .dot{width:9px;height:9px;border-radius:50%;background:var(--accent);box-shadow:0 0 12px var(--accent);}

        /* Tables data model */
        .tbl{border:1px solid var(--line);border-radius:12px;overflow:hidden;margin-bottom:14px;}
        .tbl .head{background:#15233f;padding:10px 14px;display:flex;align-items:center;gap:10px;}
        .tbl .head .name{font-family:ui-monospace,Consolas,monospace;font-weight:700;color:var(--accent2);}
        .tbl .head .desc{color:var(--muted);font-size:.8rem;}
        .tbl .col{display:flex;gap:10px;padding:7px 14px;border-top:1px solid var(--line);font-size:.83rem;}
        .tbl .col .c1{font-family:ui-monospace,Consolas,monospace;color:#e6edf7;min-width:34%;}
        .tbl .col .c2{color:#7f93b6;min-width:26%;}
        .tbl .col .c3{color:var(--amber);font-size:.78rem;}
        .pill{display:inline-block;font-size:.7rem;padding:1px 7px;border-radius:6px;background:#1e2c4b;color:#9fb6df;}

        /* Listes pages / modules */
        .item{padding:11px 0;border-top:1px solid var(--line);}
        .item:first-child{border-top:none;}
        .item .t{font-family:ui-monospace,Consolas,monospace;color:#cfe0ff;font-weight:600;}
        .item .r{color:#9fb1d0;font-weight:400;}
        .item .d{color:var(--muted);font-size:.84rem;margin-top:3px;}

        /* API */
        .ep{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--line);
            border-radius:10px;margin-bottom:10px;font-size:.85rem;background:#0c1730;}
        .method{font-weight:800;font-family:ui-monospace,Consolas,monospace;padding:2px 8px;border-radius:6px;font-size:.78rem;}
        .m-get{background:rgba(52,211,153,.16);color:var(--green);}
        .m-post{background:rgba(251,191,36,.16);color:var(--amber);}
        .scope{margin-left:auto;}
        .flow{display:flex;flex-wrap:wrap;align-items:center;gap:8px;color:var(--muted);font-size:.86rem;margin-top:8px;}
        .flow .step{background:#13213d;border:1px solid var(--line);border-radius:8px;padding:6px 11px;color:#cdd9ef;}
        .flow .arw{color:var(--accent);}
        .feat{display:flex;gap:10px;align-items:flex-start;padding:8px 0;color:#cdd9ef;font-size:.9rem;}
        .feat .chk{color:var(--green);}

        /* === Matrice de santé (affichée à la demande) === */
        .health-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px;}
        .health-toggle{
            display:inline-flex;align-items:center;gap:9px;cursor:pointer;
            background:linear-gradient(135deg,#13233f,#0e1830);color:#dce8ff;
            border:1px solid var(--line);border-radius:11px;padding:11px 18px;
            font-weight:700;font-size:.92rem;font-family:inherit;
        }
        .health-toggle:hover{border-color:var(--accent);}
        .health-toggle .led{width:10px;height:10px;border-radius:50%;background:#3b4a6b;}
        .health-toggle.is-on .led{background:var(--green);box-shadow:0 0 10px var(--green);}
        .health-meta{color:var(--muted);font-size:.82rem;}
        /* Emplacement réservé à l'avance : hauteur min définie pour éviter tout saut de mise en page. */
        #healthMatrix{display:none;min-height:280px;}
        #healthMatrix.is-open{display:block;}
        .matrix-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px;}
        .mgroup{background:#0d1730;border:1px solid var(--line);border-radius:14px;padding:14px 16px;}
        .mgroup h3{margin:0 0 12px;font-size:.95rem;color:#cfe0ff;display:flex;align-items:center;gap:8px;}
        .mcell{display:flex;align-items:center;gap:9px;padding:6px 0;border-top:1px solid #1a2740;font-size:.84rem;}
        .mcell:first-of-type{border-top:none;}
        .mcell .led{width:9px;height:9px;border-radius:50%;flex:none;}
        .mcell .nm{font-family:ui-monospace,Consolas,monospace;color:#dce8ff;}
        .mcell .dt{color:var(--muted);font-size:.76rem;margin-left:auto;text-align:right;}
        .led-ok{background:var(--green);box-shadow:0 0 8px rgba(52,211,153,.7);}
        .led-warn{background:var(--amber);box-shadow:0 0 8px rgba(251,191,36,.7);}
        .led-error{background:#f87171;box-shadow:0 0 8px rgba(248,113,113,.7);}
        .led-pending{background:#3b4a6b;}
        @keyframes mpulse{0%,100%{opacity:1;}50%{opacity:.35;}}
        #healthMatrix.is-loading .led-pending{animation:mpulse 1s ease-in-out infinite;}
    </style>
    <link id="theme-mario" rel="stylesheet" href="assets/css/mario-theme.css">
    <script src="assets/js/theme-switch.js"></script>
</head>
<body>
<header class="topbar">
    <div class="brand">CV <span>Luvumbu</span> · Architecture</div>
    <nav>
        <a href="dashboard.php">Tableau de bord</a>
        <a href="mes_cv.php">Mes CV</a>
        <a href="parametres.php">Paramètres</a>
        <a href="logout.php">Déconnexion</a>
    </nav>
</header>

<div class="wrap">
    <div class="hero">
        <h1>Vue d'ensemble du projet 🧭</h1>
        <p>Application de gestion de CV avec suivi de candidatures et API. Voici la totalité
           du projet : sa structure, ses données, ses pages et son API — en direct.</p>
        <div class="badge-stack">
            <span class="tag">PHP 8</span>
            <span class="tag">MySQL / PDO</span>
            <span class="tag">Architecture MVC légère</span>
            <span class="tag">API REST + clés</span>
            <span class="tag">Auth + CSRF</span>
            <span class="tag">Hostinger</span>
        </div>
        <div style="margin-top:18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <a href="cv_doc/index.html" target="_blank" rel="noopener"
               style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,var(--accent),#b39bff);
                      color:#fff;text-decoration:none;font-weight:700;padding:11px 20px;border-radius:11px;">
                📘 Ouvrir la documentation HTML
            </a>
            <code style="background:#16264a;border:1px solid var(--line);border-radius:8px;padding:6px 12px;color:#bfe9f2;font-size:.85rem;">
                cv_doc/index.html
            </code>
        </div>
    </div>

    <!-- Stats en direct -->
    <div class="stats">
        <?php foreach ($stats as [$ic, $lab, $val]): ?>
            <div class="statcard">
                <div class="ic"><?= $ic ?></div>
                <div class="num"><?= $val === null ? '—' : (int) $val ?></div>
                <div class="lab"><?= e($lab) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Matrice de santé en temps réel — affichée uniquement à la demande.
         Emplacement réservé d'avance (juste après les stats) : pas de saut de page. -->
    <div class="health-bar">
        <button type="button" id="healthToggle" class="health-toggle" aria-expanded="false" aria-controls="healthMatrix">
            <span class="led"></span> 🩺 Matrice de santé — <span id="healthToggleLabel">Afficher</span>
        </button>
        <span class="health-meta" id="healthMeta">Affichage à la demande · se rafraîchit en temps réel une fois ouverte.</span>
    </div>
    <section class="panel" id="healthMatrix" aria-live="polite">
        <h2><span class="dot"></span> État de l'application en direct</h2>
        <div class="matrix-grid" id="matrixGrid">
            <p class="health-meta" style="margin:0">Chargement de l'état…</p>
        </div>
    </section>

    <div class="grid">
        <!-- Colonne gauche -->
        <div>
            <div class="panel">
                <h2><span class="dot"></span> Modules métier <code style="color:var(--muted);font-weight:400">(includes/)</code></h2>
                <?php foreach ($modules as [$f, $d]): ?>
                    <div class="item">
                        <div class="t"><?= e($f) ?></div>
                        <div class="d"><?= e($d) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="panel">
                <h2><span class="dot"></span> API REST <code style="color:var(--muted);font-weight:400">(api/cv.php)</code></h2>
                <div class="ep">
                    <span class="method m-get">GET</span> /api/cv.php
                    <span class="scope pill">cv:read</span>
                </div>
                <div class="ep">
                    <span class="method m-get">GET</span> /api/cv.php?id=N
                    <span class="scope pill">cv:read</span>
                </div>
                <div class="ep">
                    <span class="method m-post">POST</span> /api/cv.php
                    <span class="scope pill">cv:write</span>
                </div>
                <p class="d" style="color:var(--muted);font-size:.82rem;margin:6px 0 0">
                    Authentification par en-tête <code>X-API-Key: cvk_…</code>
                </p>
            </div>
        </div>

        <!-- Colonne droite -->
        <div>
            <div class="panel">
                <h2><span class="dot"></span> Modèle de données</h2>
                <?php foreach ($tables as $name => $t): ?>
                    <div class="tbl">
                        <div class="head">
                            <span class="name"><?= e($name) ?></span>
                            <span class="desc"><?= e($t['desc']) ?></span>
                        </div>
                        <?php foreach ($t['cols'] as [$c, $type, $note]): ?>
                            <div class="col">
                                <span class="c1"><?= e($c) ?></span>
                                <span class="c2"><?= e($type) ?></span>
                                <span class="c3"><?= e($note) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="panel">
                <h2><span class="dot"></span> Pages de l'application</h2>
                <?php $navigable = ['dashboard.php','mes_cv.php','parametres.php','architecture.php']; ?>
                <?php foreach ($pages as [$f, $title, $d]): ?>
                    <div class="item">
                        <div class="t">
                            <?php if (in_array($f, $navigable, true)): ?>
                                <a href="<?= e($f) ?>"><?= e($f) ?></a>
                            <?php else: ?>
                                <?= e($f) ?>
                            <?php endif; ?>
                            <span class="r">— <?= e($title) ?></span>
                        </div>
                        <div class="d"><?= e($d) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="panel">
                <h2><span class="dot"></span> Parcours utilisateur</h2>
                <div class="flow">
                    <span class="step">Installation</span><span class="arw">→</span>
                    <span class="step">Connexion</span><span class="arw">→</span>
                    <span class="step">Créer un CV</span><span class="arw">→</span>
                    <span class="step">Suivre les candidatures</span><span class="arw">→</span>
                    <span class="step">Relances</span>
                </div>
            </div>

            <div class="panel">
                <h2><span class="dot"></span> Fonctionnalités clés</h2>
                <div class="feat"><span class="chk">✔</span> Installation auto-réparante (schéma, clés étrangères)</div>
                <div class="feat"><span class="chk">✔</span> Connexion sécurisée (hash, sessions, CSRF)</div>
                <div class="feat"><span class="chk">✔</span> Création de CV + aperçu PDF imprimable</div>
                <div class="feat"><span class="chk">✔</span> Suivi des candidatures (entreprise, réponse)</div>
                <div class="feat"><span class="chk">✔</span> Relances programmées avec alerte d'échéance</div>
                <div class="feat"><span class="chk">✔</span> API REST avec clés et permissions</div>
            </div>
        </div>
    </div>
</div>

<script>
/* === Matrice de santé : affichage à la demande + rafraîchissement temps réel === */
(function () {
    var REFRESH_MS = 4000;               // intervalle de rafraîchissement une fois ouverte
    var toggle  = document.getElementById('healthToggle');
    var label   = document.getElementById('healthToggleLabel');
    var matrix  = document.getElementById('healthMatrix');
    var grid    = document.getElementById('matrixGrid');
    var meta    = document.getElementById('healthMeta');
    var timer   = null;
    var open    = false;

    function esc(s) {
        return String(s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    function render(data) {
        var html = '';
        (data.groups || []).forEach(function (g) {
            html += '<div class="mgroup"><h3>' + esc(g.title) + '</h3>';
            (g.items || []).forEach(function (it) {
                html += '<div class="mcell">' +
                    '<span class="led led-' + esc(it.state) + '"></span>' +
                    '<span class="nm">' + esc(it.name) + '</span>' +
                    '<span class="dt">' + esc(it.detail || '') + '</span>' +
                    '</div>';
            });
            html += '</div>';
        });
        grid.innerHTML = html || '<p class="health-meta" style="margin:0">Aucune donnée.</p>';

        var t = new Date();
        var hh = ('0' + t.getHours()).slice(-2), mm = ('0' + t.getMinutes()).slice(-2), ss = ('0' + t.getSeconds()).slice(-2);
        var word = data.state === 'error' ? '🔴 Anomalie détectée'
                 : data.state === 'warn'  ? '🟡 Avertissements'
                 : '🟢 Tout est opérationnel';
        meta.textContent = word + ' · dernière mesure ' + hh + ':' + mm + ':' + ss;
    }

    function refresh() {
        matrix.classList.add('is-loading');
        fetch('api/health.php', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) {
                if (r.status === 401) throw new Error('Session expirée — reconnecte-toi.');
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) { render(data); })
            .catch(function (err) {
                grid.innerHTML = '<p class="health-meta" style="margin:0;color:#f87171">Impossible de lire l\'état : ' + esc(err.message) + '</p>';
                meta.textContent = '🔴 Lecture impossible';
            })
            .finally(function () { matrix.classList.remove('is-loading'); });
    }

    function start() {
        open = true;
        matrix.classList.add('is-open');
        toggle.classList.add('is-on');
        toggle.setAttribute('aria-expanded', 'true');
        label.textContent = 'Masquer';
        grid.innerHTML = '<p class="health-meta" style="margin:0">Mesure en cours…</p>';
        refresh();
        timer = setInterval(refresh, REFRESH_MS);
    }

    function stop() {
        open = false;
        matrix.classList.remove('is-open');
        toggle.classList.remove('is-on');
        toggle.setAttribute('aria-expanded', 'false');
        label.textContent = 'Afficher';
        meta.textContent = 'Affichage à la demande · se rafraîchit en temps réel une fois ouverte.';
        if (timer) { clearInterval(timer); timer = null; }
    }

    toggle.addEventListener('click', function () { open ? stop() : start(); });

    // Économie : suspendre le polling quand l'onglet est masqué, reprendre au retour.
    document.addEventListener('visibilitychange', function () {
        if (!open) return;
        if (document.hidden) { if (timer) { clearInterval(timer); timer = null; } }
        else if (!timer) { refresh(); timer = setInterval(refresh, REFRESH_MS); }
    });
})();
</script>
</body>
</html>
