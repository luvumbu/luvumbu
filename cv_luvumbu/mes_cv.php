<?php
/**
 * Mes CV — consultation, affichage et création de CV depuis l'application.
 * Accessible uniquement après connexion. Thème sombre (cohérent avec Architecture).
 */

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/guard.php';

ensure_ready();

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/cv.php';
require_login();

$userId = (int) $_SESSION['user_id'];
$error  = '';
$notice = '';

// Traitement des formulaires.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = "Session expirée, merci de réessayer.";
    } else {
        $action = $_POST['action'] ?? 'create';

        // --- Mettre un CV à la corbeille ---
        if ($action === 'trash') {
            trash_cv($userId, (int) ($_POST['cv_id'] ?? 0));
            header('Location: mes_cv.php?msg=trashed');
            exit;

        // --- Restaurer un CV depuis la corbeille ---
        } elseif ($action === 'restore') {
            restore_cv($userId, (int) ($_POST['cv_id'] ?? 0));
            header('Location: mes_cv.php?view=trash&msg=restored');
            exit;

        // --- Supprimer définitivement un CV ---
        } elseif ($action === 'force_delete') {
            force_delete_cv($userId, (int) ($_POST['cv_id'] ?? 0));
            header('Location: mes_cv.php?view=trash&msg=deleted');
            exit;

        // --- Activer le partage public ---
        } elseif ($action === 'share_on') {
            $cid = (int) ($_POST['cv_id'] ?? 0);
            enable_cv_share($userId, $cid);
            header('Location: mes_cv.php?id=' . $cid . '&msg=share_on');
            exit;

        // --- Désactiver le partage public ---
        } elseif ($action === 'share_off') {
            $cid = (int) ($_POST['cv_id'] ?? 0);
            disable_cv_share($userId, $cid);
            header('Location: mes_cv.php?id=' . $cid . '&msg=share_off');
            exit;

        // --- Création d'un CV ---
        } elseif (trim($_POST['full_name'] ?? '') === '') {
            $error = "Le nom complet est obligatoire.";
        } else {
            try {
                $id = create_cv($userId, $_POST);
                header('Location: mes_cv.php?id=' . $id);
                exit;
            } catch (Throwable $e) {
                $error = "Création impossible : " . $e->getMessage();
            }
        }
    }
}

// Messages de confirmation (après redirection).
switch ($_GET['msg'] ?? '') {
    case 'trashed':  $notice = "CV déplacé dans la corbeille."; break;
    case 'restored': $notice = "CV restauré avec succès."; break;
    case 'deleted':  $notice = "CV supprimé définitivement."; break;
    case 'share_on':  $notice = "Partage public activé. Le lien est prêt à être envoyé."; break;
    case 'share_off': $notice = "Partage public désactivé. L'ancien lien ne fonctionne plus."; break;
}

// Affichage de la corbeille ?
$showTrash = (($_GET['view'] ?? '') === 'trash');

// Détail d'un CV ?
$current = null;
if (isset($_GET['id'])) {
    $current = get_cv($userId, (int) $_GET['id']);
    if (!$current) {
        $error = "CV introuvable.";
    }
}

$cvs     = list_cvs($userId);
$trashed = list_trashed_cvs($userId);

/** Affiche un texte multiligne en HTML sécurisé. */
function nl($text): string
{
    return nl2br(htmlspecialchars((string) $text));
}

/** Pastille d'aide ⓘ : au survol/clic, affiche une bulle explicative. */
function help(string $text): string
{
    return '<span class="help" tabindex="0" role="note" aria-label="Aide">i'
         . '<span class="help-pop">' . htmlspecialchars($text) . '</span></span>';
}

/** Initiales (max 2 lettres) pour l'avatar d'un CV. */
function cv_initials(string $name): string
{
    $i = '';
    foreach (preg_split('/\s+/', trim($name)) as $w) {
        if ($w !== '') $i .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    return mb_substr($i, 0, 2) ?: '?';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <title>Mes CV — CV Luvumbu</title>
    <style>
        :root{
            --bg:#1e2c49; --panel:#2a3c5f; --panel2:#243553; --line:#3c5078;
            --ink:#eef3fb; --muted:#aabbd6; --accent:#5b95ff; --accent2:#38d8ee;
            --violet:#b39bff; --green:#34d399; --red:#f87171;
        }
        *{box-sizing:border-box;}
        body{
            margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
            background:
                radial-gradient(1200px 600px at 10% -10%, #335089 0%, transparent 55%),
                radial-gradient(900px 500px at 110% 10%, #265a72 0%, transparent 50%),
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
        .wrap{max-width:1000px;margin:0 auto;padding:34px 22px 60px;}
        .hero h1{font-size:1.9rem;margin:0 0 6px;}
        .hero p{color:var(--muted);margin:0 0 24px;}
        .count-pill{background:#16264a;border:1px solid var(--line);color:#bcd0ef;
            border-radius:999px;padding:3px 11px;font-size:.8rem;font-weight:700;margin-left:8px;}

        .alert{padding:12px 14px;border-radius:10px;margin-bottom:18px;font-size:.9rem;
            background:rgba(248,113,113,.14);color:#fecaca;border:1px solid rgba(248,113,113,.35);}
        .alert.ok{background:rgba(52,211,153,.14);color:#bbf7d0;border:1px solid rgba(52,211,153,.4);}

        /* Grille de cartes CV */
        .cv-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:16px;}
        .cv-card{
            background:linear-gradient(160deg,var(--panel),var(--panel2));
            border:1px solid var(--line);border-radius:16px;padding:18px;
            transition:transform .15s, box-shadow .15s, border-color .15s;
        }
        .cv-card:hover{transform:translateY(-4px);box-shadow:0 16px 34px rgba(0,0,0,.45);border-color:#34508c;}
        .cv-card-top{display:flex;gap:12px;align-items:center;margin-bottom:12px;}
        .cv-avatar{
            width:48px;height:48px;flex:0 0 auto;border-radius:13px;
            display:flex;align-items:center;justify-content:center;
            background:linear-gradient(135deg,var(--accent),var(--violet));
            color:#fff;font-weight:800;font-size:1.05rem;
        }
        .cv-card-id h3{margin:0;font-size:1.05rem;line-height:1.2;}
        .cv-card-id .role{margin:2px 0 0;color:var(--accent2);font-weight:600;font-size:.85rem;}
        .cv-meta{color:var(--muted);font-size:.82rem;margin:0 0 14px;}
        .cv-actions{display:flex;gap:8px;}
        .btn-mini{
            flex:1;text-align:center;padding:9px 10px;border-radius:10px;font-size:.85rem;font-weight:600;
            background:var(--accent);color:#fff;text-decoration:none;transition:filter .15s;
        }
        .btn-mini:hover{filter:brightness(1.12);text-decoration:none;}
        .btn-mini.ghost{background:#16264a;color:#bcd0ef;border:1px solid var(--line);}
        .btn-mini.danger{background:rgba(248,113,113,.16);color:#fca5a5;border:1px solid rgba(248,113,113,.4);}
        .btn-mini.danger:hover{background:rgba(248,113,113,.28);}
        .btn-mini.success{background:rgba(52,211,153,.16);color:#86efac;border:1px solid rgba(52,211,153,.4);}
        .btn-mini.success:hover{background:rgba(52,211,153,.28);}
        .btn-mini.flat{flex:0 0 auto;border:none;cursor:pointer;font-family:inherit;}
        .cv-actions form{flex:1;display:flex;}
        .cv-actions form .btn-mini{flex:1;}
        /* Lien vers la corbeille */
        .trash-link{display:inline-flex;align-items:center;gap:7px;margin-left:auto;
            background:#16264a;border:1px solid var(--line);color:#bcd0ef;
            padding:8px 14px;border-radius:10px;font-size:.85rem;font-weight:600;}
        .trash-link:hover{text-decoration:none;border-color:#34508c;}
        .trash-link .badge{background:var(--red);color:#fff;border-radius:999px;
            padding:1px 8px;font-size:.72rem;font-weight:800;}
        .hero-head{display:flex;align-items:center;gap:14px;flex-wrap:wrap;}

        .empty{
            text-align:center;padding:34px 20px;border:1px dashed var(--line);
            border-radius:16px;color:var(--muted);background:rgba(17,28,51,.4);
        }

        /* Panneau / formulaire */
        .panel{
            background:linear-gradient(160deg,var(--panel),var(--panel2));
            border:1px solid var(--line);border-radius:18px;padding:24px;margin-top:26px;
        }
        .panel h2{margin:0 0 18px;font-size:1.15rem;display:flex;align-items:center;gap:9px;}
        .panel h2 .dot{width:9px;height:9px;border-radius:50%;background:var(--accent);box-shadow:0 0 12px var(--accent);}
        label{display:block;margin-bottom:14px;font-size:.86rem;font-weight:600;color:#cbd8ef;}
        input,textarea{
            display:block;width:100%;margin-top:6px;padding:11px 13px;
            background:#1b2942;border:1px solid var(--line);border-radius:10px;
            color:var(--ink);font-size:.95rem;font-family:inherit;resize:vertical;
        }
        input:focus,textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,140,255,.2);}
        input::placeholder,textarea::placeholder{color:#5f7196;}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 18px;}
        .form-grid .full{grid-column:1/-1;}
        @media(max-width:560px){.form-grid{grid-template-columns:1fr;}}
        .btn{
            display:inline-block;padding:12px 22px;border:none;border-radius:11px;cursor:pointer;
            background:linear-gradient(135deg,var(--accent),var(--violet));color:#fff;
            font-size:.95rem;font-weight:700;transition:filter .15s;
        }
        .btn:hover{filter:brightness(1.12);}

        /* Fiche d'un CV */
        .row-actions{display:flex;align-items:center;gap:16px;margin-bottom:18px;flex-wrap:wrap;}

        /* Panneau de partage public */
        .share-panel{
            background:linear-gradient(160deg,var(--panel),var(--panel2));
            border:1px solid var(--line);border-radius:16px;padding:20px;margin-bottom:18px;
        }
        .share-panel h2{margin:0 0 6px;font-size:1.05rem;display:flex;align-items:center;gap:8px;}
        .share-panel .sub{color:var(--muted);font-size:.86rem;margin:0 0 14px;}
        .share-on{display:flex;gap:18px;flex-wrap:wrap;align-items:center;}
        .share-qr{background:#fff;border-radius:12px;padding:8px;flex:0 0 auto;line-height:0;}
        .share-qr img{display:block;width:150px;height:150px;}
        .share-info{flex:1;min-width:240px;display:flex;flex-direction:column;gap:10px;}
        .share-url{display:flex;gap:8px;}
        .share-url input{
            flex:1;padding:10px 12px;background:#1b2942;border:1px solid var(--line);
            border-radius:10px;color:var(--ink);font-size:.85rem;
        }
        .share-buttons{display:flex;gap:8px;flex-wrap:wrap;}
        .sbtn{padding:9px 14px;border-radius:10px;font-size:.85rem;font-weight:600;border:none;cursor:pointer;
            text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
        .sbtn.copy{background:var(--accent);color:#fff;}
        .sbtn.open{background:#16264a;color:#bcd0ef;border:1px solid var(--line);}
        .sbtn.off{background:rgba(248,113,113,.16);color:#fca5a5;border:1px solid rgba(248,113,113,.4);}
        .sbtn.on{background:linear-gradient(135deg,var(--accent),var(--violet));color:#fff;}

        /* Aide contextuelle : pastilles ⓘ + bulles explicatives */
        .help{
            display:inline-flex;align-items:center;justify-content:center;
            width:17px;height:17px;border-radius:50%;background:#16264a;border:1px solid var(--line);
            color:var(--accent2);font-size:11px;font-weight:700;cursor:help;position:relative;
            vertical-align:middle;margin-left:6px;font-style:normal;flex:0 0 auto;
        }
        .help:hover,.help:focus{background:var(--accent);color:#fff;outline:none;}
        .help-pop{
            position:absolute;left:50%;top:150%;transform:translateX(-50%);
            width:250px;max-width:78vw;background:#0e1a33;border:1px solid var(--line);
            border-radius:10px;padding:11px 13px;font-size:.8rem;font-weight:400;line-height:1.55;
            color:#cdd9ef;text-align:left;box-shadow:0 14px 34px rgba(0,0,0,.55);z-index:40;display:none;
        }
        .help-pop::before{
            content:"";position:absolute;left:50%;top:-6px;transform:translateX(-50%);
            border:6px solid transparent;border-bottom-color:#0e1a33;
        }
        .help:hover .help-pop,.help:focus .help-pop,
        body.help-on .help-pop{display:block;}
        body.help-on .help{background:var(--accent);color:#fff;}
        /* Bandeau d'aide en haut */
        .help-bar{
            display:flex;align-items:center;gap:12px;flex-wrap:wrap;
            background:rgba(56,216,238,.08);border:1px solid rgba(56,216,238,.3);
            border-radius:12px;padding:12px 16px;margin-bottom:18px;color:#bfe9f2;font-size:.88rem;
        }
        .help-bar b{color:var(--accent2);}
        .help-toggle{
            margin-left:auto;cursor:pointer;border:1px solid var(--line);background:#16264a;
            color:#bcd0ef;border-radius:9px;padding:7px 13px;font-size:.82rem;font-weight:600;
        }
        .help-toggle.active{background:var(--accent);color:#fff;border-color:var(--accent);}
        .cv-sheet{
            background:linear-gradient(160deg,var(--panel),var(--panel2));
            border:1px solid var(--line);border-radius:18px;padding:28px;
        }
        .cv-sheet-head{display:flex;gap:16px;align-items:center;margin-bottom:20px;}
        .cv-sheet-head .cv-avatar{width:62px;height:62px;font-size:1.3rem;border-radius:16px;}
        .cv-name{margin:0;font-size:1.6rem;}
        .cv-title{margin:2px 0 0;color:var(--accent2);font-weight:600;}
        .cv-contact{margin:4px 0 0;color:var(--muted);font-size:.9rem;}
        .cv-sheet section{margin-bottom:18px;}
        .cv-sheet h3{
            margin:0 0 6px;font-size:.85rem;text-transform:uppercase;letter-spacing:.05em;
            color:var(--accent2);border-bottom:1px solid var(--line);padding-bottom:5px;
        }
        .cv-sheet p{margin:0;line-height:1.55;color:#cdd9ef;}
    </style>
    <link id="theme-mario" rel="stylesheet" href="assets/css/mario-theme.css">
    <script src="assets/js/theme-switch.js"></script>
</head>
<body>
<header class="topbar">
    <div class="brand">CV <span>Luvumbu</span></div>
    <nav>
        <a href="dashboard.php">Tableau de bord</a>
        <a href="parametres.php">Paramètres</a>
        <a href="architecture.php">Architecture</a>
        <a href="logout.php">Déconnexion</a>
    </nav>
</header>

<div class="wrap">
    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($notice): ?>
        <div class="alert ok"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <?php if ($current): ?>
        <!-- ===== Affichage d'un CV ===== -->
        <div class="row-actions">
            <a href="mes_cv.php">← Retour à la liste</a>
            <a class="btn-mini" style="flex:0 0 auto" href="cv_builder.php?id=<?= (int) $current['id'] ?>">✏️ Éditeur visuel</a>
            <a class="btn-mini ghost" style="flex:0 0 auto" href="cv_view.php?id=<?= (int) $current['id'] ?>">👁️ Aperçu façon CV (PDF)</a>
            <?= help("✏️ Éditeur visuel : modifie la mise en page, les couleurs et les sections. 👁️ Aperçu : voit le CV façon PDF et l'imprime/exporte. Plus bas, active le partage par lien public.") ?>
        </div>

        <!-- ===== Partage public ===== -->
        <?php
            $shareToken = $current['share_token'] ?? '';
            $shareUrl   = $shareToken ? cv_public_url($shareToken) : '';
            $qrSrc      = $shareUrl
                ? 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=4&data=' . urlencode($shareUrl)
                : '';
        ?>
        <div class="share-panel">
            <h2>🔗 Partage public<?= help("Active un lien public : n'importe qui possédant ce lien (ou le QR code) peut voir ce CV sans compte. Idéal pour postuler. Tu peux le désactiver à tout moment — le lien cesse alors de fonctionner.") ?></h2>
            <?php if ($shareToken): ?>
                <p class="sub">N'importe qui disposant de ce lien peut voir ce CV (sans compte). Tu peux le désactiver à tout moment.</p>
                <div class="share-on">
                    <div class="share-qr"><img src="<?= htmlspecialchars($qrSrc) ?>" alt="QR code du CV" loading="lazy"></div>
                    <div class="share-info">
                        <div class="share-url">
                            <input type="text" id="shareUrl" value="<?= htmlspecialchars($shareUrl) ?>" readonly onclick="this.select()">
                            <button type="button" class="sbtn copy" onclick="navigator.clipboard.writeText(document.getElementById('shareUrl').value).then(()=>{this.textContent='✓ Copié';setTimeout(()=>this.textContent='📋 Copier',1500);})">📋 Copier</button>
                        </div>
                        <div class="share-buttons">
                            <a class="sbtn open" href="<?= htmlspecialchars($shareUrl) ?>" target="_blank" rel="noopener">↗ Ouvrir le lien</a>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="share_off">
                                <input type="hidden" name="cv_id" value="<?= (int) $current['id'] ?>">
                                <button type="submit" class="sbtn off" onclick="return confirm('Désactiver le partage ? Le lien actuel cessera de fonctionner.');">🚫 Désactiver le partage</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="sub">Génère un lien public pour envoyer ce CV à n'importe quelle entreprise, sans qu'elle ait besoin d'un compte.</p>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="share_on">
                    <input type="hidden" name="cv_id" value="<?= (int) $current['id'] ?>">
                    <button type="submit" class="sbtn on">🔗 Activer le partage public</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="cv-sheet">
            <div class="cv-sheet-head">
                <span class="cv-avatar"><?= htmlspecialchars(cv_initials($current['full_name'])) ?></span>
                <div>
                    <h2 class="cv-name"><?= htmlspecialchars($current['full_name']) ?></h2>
                    <?php if ($current['title']): ?>
                        <p class="cv-title"><?= htmlspecialchars($current['title']) ?></p>
                    <?php endif; ?>
                    <p class="cv-contact">
                        <?= htmlspecialchars($current['email']) ?>
                        <?php if ($current['phone']): ?> · <?= htmlspecialchars($current['phone']) ?><?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if ($current['summary']): ?>
                <section><h3>Profil</h3><p><?= nl($current['summary']) ?></p></section>
            <?php endif; ?>
            <?php if ($current['experience']): ?>
                <section><h3>Expérience</h3><p><?= nl($current['experience']) ?></p></section>
            <?php endif; ?>
            <?php if ($current['education']): ?>
                <section><h3>Formation</h3><p><?= nl($current['education']) ?></p></section>
            <?php endif; ?>
            <?php if ($current['skills']): ?>
                <section><h3>Compétences</h3><p><?= nl($current['skills']) ?></p></section>
            <?php endif; ?>
        </div>
    <?php elseif ($showTrash): ?>
        <!-- ===== Corbeille ===== -->
        <div class="hero">
            <div class="hero-head">
                <h1>🗑️ Corbeille <span class="count-pill"><?= count($trashed) ?></span></h1>
                <a class="trash-link" href="mes_cv.php">← Retour à mes CV</a>
            </div>
            <p>CV supprimés. Restaurez-les ou supprimez-les définitivement.</p>
        </div>

        <?php if (!$trashed): ?>
            <div class="empty">La corbeille est vide. 🧹</div>
        <?php else: ?>
            <div class="cv-grid">
                <?php foreach ($trashed as $cv): ?>
                    <article class="cv-card">
                        <div class="cv-card-top">
                            <span class="cv-avatar"><?= htmlspecialchars(cv_initials($cv['full_name'])) ?></span>
                            <div class="cv-card-id">
                                <h3><?= htmlspecialchars($cv['full_name']) ?></h3>
                                <?php if ($cv['title']): ?><p class="role"><?= htmlspecialchars($cv['title']) ?></p><?php endif; ?>
                            </div>
                        </div>
                        <p class="cv-meta">🗓️ Supprimé le <?= htmlspecialchars(substr((string) $cv['deleted_at'], 0, 10)) ?></p>
                        <div class="cv-actions">
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="cv_id" value="<?= (int) $cv['id'] ?>">
                                <button type="submit" class="btn-mini success flat">♻️ Restaurer</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Supprimer définitivement ce CV ? Cette action est irréversible.');">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="force_delete">
                                <input type="hidden" name="cv_id" value="<?= (int) $cv['id'] ?>">
                                <button type="submit" class="btn-mini danger flat">🗑️ Supprimer</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- ===== Liste des CV ===== -->
        <div class="help-bar">
            <span>💡 <b>Besoin d'aide ?</b> Les pastilles <span class="help" tabindex="0" role="note">i<span class="help-pop">Voilà à quoi ressemble une pastille d'aide : survole-la ou clique dessus pour lire l'explication.</span></span> expliquent chaque élément. Active le « Mode aide » pour tout afficher d'un coup.</span>
            <button type="button" class="help-toggle" id="helpToggle">❓ Mode aide</button>
        </div>
        <div class="hero">
            <div class="hero-head">
                <h1>Mes CV <span class="count-pill"><?= count($cvs) ?></span><?= help("Le nombre de CV actifs que tu possèdes. Les CV supprimés vont dans la corbeille et ne sont plus comptés ici.") ?></h1>
                <?php if ($trashed): ?>
                    <a class="trash-link" href="mes_cv.php?view=trash">🗑️ Corbeille <span class="badge"><?= count($trashed) ?></span></a>
                    <?= help("La corbeille contient les CV supprimés. Tu peux les restaurer ou les effacer définitivement.") ?>
                <?php endif; ?>
            </div>
            <p>Consultez, ouvrez en aperçu PDF ou créez un nouveau CV. <?= help("Cette page liste tous tes CV sous forme de cartes. Chaque carte permet d'éditer, prévisualiser, partager ou supprimer un CV. Le formulaire en bas crée un nouveau CV.") ?></p>
        </div>

        <?php if (!$cvs): ?>
            <div class="empty">Aucun CV pour l'instant. Créez-en un ci-dessous. 👇</div>
        <?php else: ?>
            <div class="cv-grid">
                <?php foreach ($cvs as $cv): ?>
                    <article class="cv-card">
                        <div class="cv-card-top">
                            <span class="cv-avatar"><?= htmlspecialchars(cv_initials($cv['full_name'])) ?></span>
                            <div class="cv-card-id">
                                <h3><?= htmlspecialchars($cv['full_name']) ?></h3>
                                <?php if ($cv['title']): ?><p class="role"><?= htmlspecialchars($cv['title']) ?></p><?php endif; ?>
                            </div>
                        </div>
                        <p class="cv-meta">📅 Créé le <?= htmlspecialchars(substr((string) $cv['created_at'], 0, 10)) ?><?= help("✏️ Éditer : ouvre l'éditeur visuel (mise en page, couleurs, sections). 👁️ Aperçu : affiche le CV façon PDF. 🗑️ : envoie le CV à la corbeille. Ouvre un CV pour aussi le partager par lien.") ?></p>
                        <div class="cv-actions">
                            <a class="btn-mini" href="cv_builder.php?id=<?= (int) $cv['id'] ?>">✏️ Éditer</a>
                            <a class="btn-mini ghost" href="cv_view.php?id=<?= (int) $cv['id'] ?>">👁️ Aperçu</a>
                            <form method="post" style="flex:0 0 auto" onsubmit="return confirm('Déplacer ce CV dans la corbeille ?');">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="trash">
                                <input type="hidden" name="cv_id" value="<?= (int) $cv['id'] ?>">
                                <button type="submit" class="btn-mini danger flat" title="Supprimer" style="flex:0 0 auto">🗑️</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ===== Formulaire de création ===== -->
        <div class="panel">
            <h2><span class="dot"></span> Nouveau CV<?= help("Remplis au minimum le nom complet, puis « Créer le CV ». Tu pourras ensuite tout personnaliser (photo, modèle, couleurs, sections) dans l'éditeur visuel. Les autres champs sont facultatifs.") ?></h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="form-grid">
                    <label>Nom complet
                        <input name="full_name" required>
                    </label>
                    <label>Titre / poste
                        <input name="title" placeholder="Ex. Développeur Web Full-Stack">
                    </label>
                    <label>Email
                        <input name="email" type="email">
                    </label>
                    <label>Téléphone
                        <input name="phone">
                    </label>
                    <label class="full">Profil
                        <textarea name="summary" rows="3"></textarea>
                    </label>
                    <label class="full">Expérience
                        <textarea name="experience" rows="4"></textarea>
                    </label>
                    <label class="full">Formation
                        <textarea name="education" rows="2"></textarea>
                    </label>
                    <label class="full">Compétences
                        <textarea name="skills" rows="2" placeholder="PHP, MySQL, JavaScript…"></textarea>
                    </label>
                </div>
                <button class="btn" type="submit">Créer le CV</button>
            </form>
        </div>
    <?php endif; ?>
</div>
<script>
    // Bouton « Mode aide » : affiche/masque toutes les bulles explicatives d'un coup.
    (function () {
        var t = document.getElementById('helpToggle');
        if (!t) return;
        t.addEventListener('click', function () {
            var on = document.body.classList.toggle('help-on');
            t.classList.toggle('active', on);
            t.textContent = on ? '✓ Aide affichée' : '❓ Mode aide';
        });
    })();
</script>
</body>
</html>
