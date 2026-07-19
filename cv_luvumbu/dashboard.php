<?php
/**
 * Tableau de bord — suivi des candidatures (quel CV envoyé à quelle entreprise,
 * réponse reçue ou non, relance programmée). Thème sombre (cohérent avec Architecture).
 */

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/guard.php';

ensure_ready();

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/cv.php';
require __DIR__ . '/includes/applications.php';
require_login();

$userId = (int) $_SESSION['user_id'];
$error  = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = "Session expirée, merci de réessayer.";
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add') {
                $company = trim($_POST['company'] ?? '');
                $cvId    = ((int) ($_POST['cv_id'] ?? 0)) ?: null;
                $sentAt  = (string) ($_POST['sent_at'] ?? '');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sentAt)) {
                    $sentAt = date('Y-m-d');
                }
                if ($company === '') {
                    $error = "Le nom de l'entreprise est obligatoire.";
                } else {
                    // Informations supplémentaires libres (bouton « + »).
                    $extra   = [];
                    $eLabels = (array) ($_POST['extra_label'] ?? []);
                    $eValues = (array) ($_POST['extra_value'] ?? []);
                    foreach ($eLabels as $i => $lab) {
                        $extra[] = ['label' => (string) $lab, 'value' => (string) ($eValues[$i] ?? '')];
                    }
                    create_application($userId, $cvId, $company, $sentAt, trim($_POST['notes'] ?? ''), $extra);
                    $notice = "Candidature ajoutée.";
                }
            } elseif ($action === 'status') {
                update_application_status($userId, (int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''));
                $notice = "Statut mis à jour.";
            } elseif ($action === 'followup') {
                $id = (int) ($_POST['id'] ?? 0);
                if (isset($_POST['cancel'])) {
                    set_application_followup($userId, $id, false);
                    $notice = "Relance annulée.";
                } else {
                    set_application_followup($userId, $id, true, (string) ($_POST['followup_date'] ?? ''));
                    $notice = "Relance programmée.";
                }
            } elseif ($action === 'delete') {
                delete_application($userId, (int) ($_POST['id'] ?? 0));
                $notice = "Candidature supprimée.";
            }
        } catch (Throwable $e) {
            $error = "Opération impossible : " . $e->getMessage();
        }
    }
}

$cvs    = list_cvs($userId);
$apps   = list_applications($userId);
$stats  = application_stats($userId);
$labels = application_statuses();
$today  = date('Y-m-d');

/** Badge coloré pour un statut de réponse. */
function status_badge(string $status, array $labels): string
{
    $cls = ['en_attente' => 'badge-wait', 'positive' => 'badge-on', 'negative' => 'badge-off'][$status] ?? 'badge-wait';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($labels[$status] ?? $status) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <title>Tableau de bord — CV Luvumbu</title>
    <style>
        :root{
            --bg:#0b1220; --panel:#111c33; --panel2:#0e1830; --line:#22304f;
            --ink:#e6edf7; --muted:#8da2c0; --accent:#4f8cff; --accent2:#22d3ee;
            --violet:#a78bfa; --green:#34d399; --amber:#fbbf24; --red:#f87171;
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
        .wrap{max-width:1060px;margin:0 auto;padding:34px 22px 60px;}
        .hero h1{font-size:1.9rem;margin:0 0 6px;}
        .hero p{color:var(--muted);margin:0 0 24px;}

        .alert{padding:12px 14px;border-radius:10px;margin-bottom:18px;font-size:.9rem;border:1px solid transparent;}
        .alert-error{background:rgba(248,113,113,.14);color:#fecaca;border-color:rgba(248,113,113,.35);}
        .alert-success{background:rgba(52,211,153,.15);color:#bbf7d0;border-color:rgba(52,211,153,.35);}

        /* Stat cards */
        .statgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:26px;}
        .statcard{
            background:linear-gradient(160deg,var(--panel),var(--panel2));
            border:1px solid var(--line);border-radius:16px;padding:16px 18px;position:relative;overflow:hidden;
        }
        .statcard .ic{font-size:1.25rem;}
        .statcard .num{font-size:1.8rem;font-weight:800;margin-top:4px;}
        .statcard .lab{color:var(--muted);font-size:.8rem;}
        .statcard.ok .num{color:var(--green);} .statcard.no .num{color:var(--red);} .statcard.warn .num{color:var(--amber);}
        .statcard::after{content:"";position:absolute;right:-30px;top:-30px;width:90px;height:90px;
            background:radial-gradient(circle,rgba(79,140,255,.2),transparent 70%);}

        /* Panneaux */
        .panel{
            background:linear-gradient(160deg,var(--panel),var(--panel2));
            border:1px solid var(--line);border-radius:18px;padding:22px;margin-bottom:22px;
        }
        .panel h2{margin:0 0 16px;font-size:1.15rem;display:flex;align-items:center;gap:9px;}
        .panel h2 .dot{width:9px;height:9px;border-radius:50%;background:var(--accent);box-shadow:0 0 12px var(--accent);}

        /* Tableau */
        .tbl{width:100%;border-collapse:collapse;font-size:.87rem;}
        .tbl th,.tbl td{text-align:left;padding:11px 10px;border-bottom:1px solid var(--line);vertical-align:top;}
        .tbl th{color:var(--muted);font-weight:600;}
        .tbl tr.to-followup td{background:rgba(251,191,36,.07);}
        .tbl .company{font-weight:600;color:#dbe6f7;}

        .badge{display:inline-block;padding:3px 9px;border-radius:999px;font-size:.74rem;font-weight:600;}
        .badge-on{background:rgba(52,211,153,.16);color:#6ee7b7;}
        .badge-off{background:rgba(248,113,113,.16);color:#fca5a5;}
        .badge-wait{background:#1e2c4b;color:#aebfde;}
        .badge-warn{background:rgba(251,191,36,.16);color:#fcd34d;}

        .btn-link{background:none;border:none;color:var(--accent);cursor:pointer;font-size:.82rem;padding:0;text-decoration:underline;}
        .btn-link.danger{color:#fca5a5;}
        .followup-form{display:flex;align-items:center;gap:6px;margin:6px 0;}

        /* Champs */
        label{display:block;margin-bottom:14px;font-size:.86rem;font-weight:600;color:#cbd8ef;}
        input,textarea,select{
            display:block;width:100%;margin-top:6px;padding:10px 12px;
            background:#0a1124;border:1px solid var(--line);border-radius:10px;
            color:var(--ink);font-size:.95rem;font-family:inherit;resize:vertical;
        }
        input:focus,textarea:focus,select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,140,255,.2);}
        input::placeholder,textarea::placeholder{color:#5f7196;}
        .select-mini{display:inline-block;width:auto;margin:0;padding:5px 8px;font-size:.8rem;}
        input[type="date"].select-mini{padding:4px 8px;}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 18px;}
        .form-grid .full{grid-column:1/-1;}
        @media(max-width:560px){.form-grid{grid-template-columns:1fr;}}
        .btn{
            display:inline-block;padding:12px 22px;border:none;border-radius:11px;cursor:pointer;
            background:linear-gradient(135deg,var(--accent),var(--violet));color:#fff;
            font-size:.95rem;font-weight:700;transition:filter .15s;
        }
        .btn:hover{filter:brightness(1.12);}
        .empty{text-align:center;padding:26px;border:1px dashed var(--line);border-radius:14px;color:var(--muted);}
    </style>
    <link id="theme-mario" rel="stylesheet" href="assets/css/mario-theme.css">
    <script src="assets/js/theme-switch.js"></script>
</head>
<body>
<header class="topbar">
    <div class="brand">CV <span>Luvumbu</span></div>
    <nav>
        <a href="mes_cv.php">Mes CV</a>
        <a href="parametres.php">Paramètres</a>
        <a href="architecture.php">Architecture</a>
        <a href="logout.php">Déconnexion</a>
    </nav>
</header>

<div class="wrap">
    <div class="hero">
        <h1>Bienvenue, <?= htmlspecialchars($_SESSION['username']) ?> 👋</h1>
        <p>Suivi de vos candidatures : quel CV envoyé à quelle entreprise, réponse et relances.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($notice): ?>
        <div class="alert alert-success"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <!-- ===== Statistiques ===== -->
    <div class="statgrid">
        <div class="statcard"><div class="ic">📨</div><div class="num"><?= (int) $stats['total'] ?></div><div class="lab">Candidatures</div></div>
        <div class="statcard"><div class="ic">⏳</div><div class="num"><?= (int) $stats['en_attente'] ?></div><div class="lab">En attente</div></div>
        <div class="statcard ok"><div class="ic">✅</div><div class="num"><?= (int) $stats['positive'] ?></div><div class="lab">Réponses +</div></div>
        <div class="statcard no"><div class="ic">❌</div><div class="num"><?= (int) $stats['negative'] ?></div><div class="lab">Réponses −</div></div>
        <div class="statcard"><div class="ic">📅</div><div class="num"><?= (int) $stats['followup'] ?></div><div class="lab">Relances prévues</div></div>
        <div class="statcard warn"><div class="ic">⚠️</div><div class="num"><?= (int) $stats['due'] ?></div><div class="lab">À relancer</div></div>
    </div>

    <!-- ===== Liste des candidatures ===== -->
    <div class="panel">
        <h2><span class="dot"></span> Mes candidatures</h2>
        <?php if (!$apps): ?>
            <div class="empty">Aucune candidature enregistrée. Ajoutez-en une ci-dessous. 👇</div>
        <?php else: ?>
            <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>CV envoyé</th><th>Entreprise</th><th>Envoyé le</th>
                        <th>Réponse</th><th>Relance</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($apps as $a): ?>
                    <?php $due = $a['followup'] && !empty($a['followup_date']) && $a['followup_date'] <= $today; ?>
                    <tr class="<?= $due ? 'to-followup' : '' ?>">
                        <td>
                            <?php if ($a['cv_name']): ?>
                                <a href="mes_cv.php?id=<?= (int) $a['cv_id'] ?>" title="Voir ce CV"><?= htmlspecialchars($a['cv_name']) ?></a>
                            <?php else: ?>
                                <span class="muted" style="color:var(--muted)">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="company"><?= htmlspecialchars($a['company']) ?>
                            <?php $extra = application_extra($a); ?>
                            <?php if ($extra): ?>
                                <div class="app-extra">
                                    <?php foreach ($extra as $ex): ?>
                                        <span class="chip"><?php if (($ex['label'] ?? '') !== ''): ?><b><?= htmlspecialchars($ex['label']) ?> :</b> <?php endif; ?><?= htmlspecialchars($ex['value'] ?? '') ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($a['sent_at']) ?></td>

                        <td>
                            <div><?= status_badge($a['status'], $labels) ?></div>
                            <form method="post" style="margin-top:6px">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <select name="status" class="select-mini" onchange="this.form.submit()">
                                    <?php foreach ($labels as $key => $lab): ?>
                                        <option value="<?= $key ?>" <?= $a['status'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>

                        <td>
                            <?php if ($a['followup']): ?>
                                <span class="badge <?= $due ? 'badge-warn' : 'badge-wait' ?>">
                                    <?= $due ? '⚠ ' : '📅 ' ?>le <?= htmlspecialchars($a['followup_date'] ?? '') ?>
                                </span>
                                <form method="post" class="followup-form">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="followup">
                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <input type="date" name="followup_date" class="select-mini" value="<?= htmlspecialchars($a['followup_date'] ?? $today) ?>">
                                    <button class="btn-link" type="submit">Modifier</button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="followup">
                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <input type="hidden" name="cancel" value="1">
                                    <button class="btn-link danger" type="submit">Annuler</button>
                                </form>
                            <?php else: ?>
                                <form method="post" class="followup-form">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="followup">
                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <input type="date" name="followup_date" class="select-mini" value="<?= $today ?>">
                                    <button class="btn-link" type="submit">Programmer</button>
                                </form>
                            <?php endif; ?>
                        </td>

                        <td>
                            <form method="post" onsubmit="return confirm('Supprimer cette candidature ?');">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <button class="btn-link danger" type="submit">Suppr.</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== Ajout d'une candidature ===== -->
    <div class="panel">
        <h2><span class="dot"></span> Nouvelle candidature</h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <label>CV envoyé
                    <select name="cv_id">
                        <option value="">— Aucun CV précis —</option>
                        <?php foreach ($cvs as $cv): ?>
                            <option value="<?= (int) $cv['id'] ?>"><?= htmlspecialchars($cv['full_name']) ?><?= $cv['title'] ? ' — ' . htmlspecialchars($cv['title']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Entreprise
                    <input name="company" placeholder="Ex. Ubisoft" required>
                </label>
                <label>Date d'envoi
                    <input name="sent_at" type="date" value="<?= date('Y-m-d') ?>">
                </label>
                <label>Notes <span style="color:var(--muted)">(optionnel)</span>
                    <input name="notes" placeholder="Contact, poste, lien…">
                </label>
            </div>

            <!-- Informations supplémentaires (dynamiques) -->
            <div class="extra-block">
                <div class="extra-head">Informations supplémentaires <span style="color:var(--muted);font-weight:400">(optionnel)</span></div>
                <div id="extraList"></div>
                <button type="button" id="extraAdd" class="btn-add-extra">+ Ajouter une information</button>
            </div>

            <button class="btn" type="submit" style="margin-top:14px">Ajouter la candidature</button>
        </form>
    </div>
</div>

<style>
    .extra-block{margin-top:16px;}
    .extra-head{font-size:.86rem;font-weight:600;color:#cbd8ef;margin-bottom:10px;}
    .extra-row{display:flex;gap:8px;align-items:center;margin-bottom:8px;}
    .extra-row input{
        padding:10px 12px;border-radius:8px;border:1px solid var(--line);
        background:#1b2942;color:var(--ink);font:inherit;
    }
    .extra-row .ex-label{flex:0 0 34%;}
    .extra-row .ex-value{flex:1;}
    .extra-row .ex-del{
        flex:0 0 auto;padding:9px 12px;border-radius:8px;border:1px solid var(--line);
        background:transparent;color:#fca5a5;cursor:pointer;font:inherit;
    }
    .extra-row .ex-del:hover{background:rgba(248,113,113,.16);}
    .btn-add-extra{
        padding:8px 14px;border-radius:8px;border:1px dashed var(--line);
        background:transparent;color:#cbd8ef;cursor:pointer;font:inherit;font-weight:600;
    }
    .btn-add-extra:hover{border-color:var(--accent);color:#fff;}
    .app-extra{margin-top:5px;display:flex;flex-wrap:wrap;gap:5px;}
    .app-extra .chip{
        background:#16264a;border:1px solid var(--line);border-radius:999px;
        padding:2px 10px;font-size:.74rem;color:#bcd0ef;
    }
    .app-extra .chip b{color:var(--accent2);font-weight:700;}
</style>
<script>
    // Bouton « + » : ajoute des champs d'informations supplémentaires (libellé + valeur).
    (function () {
        var list = document.getElementById('extraList');
        var add  = document.getElementById('extraAdd');
        if (!list || !add) return;
        function addRow(label, value) {
            var row = document.createElement('div');
            row.className = 'extra-row';
            var l = document.createElement('input');
            l.className = 'ex-label'; l.name = 'extra_label[]';
            l.placeholder = 'Intitulé (ex. Poste, Contact, Salaire…)';
            l.value = label || '';
            var v = document.createElement('input');
            v.className = 'ex-value'; v.name = 'extra_value[]';
            v.placeholder = 'Valeur';
            v.value = value || '';
            var d = document.createElement('button');
            d.type = 'button'; d.className = 'ex-del'; d.title = 'Retirer'; d.textContent = '✕';
            d.addEventListener('click', function () { row.remove(); });
            row.append(l, v, d);
            list.appendChild(row);
            l.focus();
        }
        add.addEventListener('click', function () { addRow(); });
    })();
</script>
</body>
</html>
