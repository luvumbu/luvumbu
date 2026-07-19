<?php
/**
 * Paramètres — gestion des clés API.
 * Auto-génération, consultation, modification (nom + permissions) et révocation.
 * Accessible uniquement après connexion. Thème sombre (cohérent avec le reste).
 */

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/guard.php';

ensure_ready();

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/api_keys.php';
require __DIR__ . '/includes/account.php';
require __DIR__ . '/includes/google_auth.php';
require_login();

$userId  = (int) $_SESSION['user_id'];
$error   = '';
$notice  = '';
$newKey  = null;                 // clé en clair, affichée une seule fois
$editing = null;                 // clé en cours d'édition

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = "Session expirée, merci de réessayer.";
    } else {
        $action = $_POST['action'] ?? '';
        $scopes = sanitize_scopes($_POST['scopes'] ?? []);

        if ($action === 'create') {
            $label = trim($_POST['label'] ?? '');
            if ($label === '') {
                $label = 'Clé du ' . date('Y-m-d H:i');
            }
            try {
                $newKey = create_api_key($userId, $label, $scopes);
                $notice = "Clé créée.";
            } catch (Throwable $e) {
                $error = "Impossible de créer la clé : " . $e->getMessage();
            }
        } elseif ($action === 'update') {
            $keyId = (int) ($_POST['key_id'] ?? 0);
            $label = trim($_POST['label'] ?? '');
            if ($label === '') {
                $error   = "Le nom de la clé est obligatoire.";
                $editing = get_api_key($userId, $keyId);
            } else {
                try {
                    update_api_key($userId, $keyId, $label, $scopes);
                    $notice = "Clé mise à jour.";
                } catch (Throwable $e) {
                    $error = "Impossible de modifier la clé.";
                }
            }
        } elseif ($action === 'revoke') {
            $keyId = (int) ($_POST['key_id'] ?? 0);
            try {
                revoke_api_key($userId, $keyId);
                $notice = "Clé révoquée.";
            } catch (Throwable $e) {
                $error = "Impossible de révoquer la clé.";
            }
        } elseif ($action === 'set_email') {
            $email = trim($_POST['email'] ?? '');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Adresse e-mail invalide.";
            } else {
                try {
                    update_account_email($userId, $email);
                    $notice = $email !== '' ? "E-mail enregistré." : "E-mail retiré.";
                } catch (Throwable $e) {
                    $error = "Impossible d'enregistrer l'e-mail (peut-être déjà utilisé).";
                }
            }
        } elseif ($action === 'set_password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $new     = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');
            if (!verify_account_password($userId, $current)) {
                $error = "Mot de passe actuel incorrect.";
            } elseif (strlen($new) < 6) {
                $error = "Le nouveau mot de passe doit faire au moins 6 caractères.";
            } elseif ($new !== $confirm) {
                $error = "La confirmation ne correspond pas.";
            } else {
                update_account_password($userId, $new);
                $notice = "Mot de passe mis à jour.";
            }
        } elseif ($action === 'set_google') {
            $clientId     = trim($_POST['google_client_id'] ?? '');
            $clientSecret = trim($_POST['google_client_secret'] ?? '');
            try {
                set_setting('google_client_id', $clientId);
                // Secret laissé vide = on conserve l'ancien (champ non pré-rempli).
                if ($clientSecret !== '') {
                    set_setting('google_client_secret', $clientSecret);
                }
                if ($clientId === '') {
                    set_setting('google_client_secret', '');
                }
                $notice = "Connexion Google enregistrée.";
            } catch (Throwable $e) {
                $error = "Impossible d'enregistrer la configuration Google.";
            }
        } elseif ($action === 'google_add_email') {
            $email = trim($_POST['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Adresse Google invalide.";
            } elseif (google_add_allowed_email($email)) {
                $notice = "Adresse Google ajoutée.";
            } else {
                $error = "Cette adresse est déjà dans la liste.";
            }
        } elseif ($action === 'google_remove_email') {
            $email = trim($_POST['email'] ?? '');
            google_remove_allowed_email($email);
            $notice = "Adresse Google retirée.";
        }
    }
}

// Mode édition via lien ?edit=ID
if ($editing === null && isset($_GET['edit'])) {
    $editing = get_api_key($userId, (int) $_GET['edit']);
    if ($editing && $editing['revoked_at']) {
        $editing = null;
    }
}

try {
    $keys = list_api_keys($userId);
} catch (Throwable $e) {
    $keys  = [];
    $error = $error ?: "Impossible de charger les clés API.";
}

$allScopes   = available_scopes();
$account     = get_account($userId);
$google         = google_config();
$redirectUri    = google_redirect_uri();
$googleEmails   = google_allowed_emails();

/** Affiche un bloc de cases à cocher pour les permissions. */
function render_scope_checkboxes(array $allScopes, array $checked): void
{
    foreach ($allScopes as $id => $label) {
        $isChecked = in_array($id, $checked, true) ? 'checked' : '';
        echo '<label class="check"><input type="checkbox" name="scopes[]" value="'
            . htmlspecialchars($id) . '" ' . $isChecked . '> '
            . htmlspecialchars($label) . ' <code>' . htmlspecialchars($id) . '</code></label>';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <title>Paramètres — CV Luvumbu</title>
    <style>
        :root{
            --bg:#0b1220; --panel:#111c33; --panel2:#0e1830; --line:#22304f;
            --ink:#e6edf7; --muted:#8da2c0; --accent:#4f8cff; --accent2:#22d3ee;
            --violet:#a78bfa; --green:#34d399; --red:#f87171;
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
        .wrap{max-width:920px;margin:0 auto;padding:34px 22px 60px;}
        .hero h1{font-size:1.9rem;margin:0 0 6px;}
        .hero p{color:var(--muted);margin:0 0 24px;}

        .alert{padding:12px 14px;border-radius:10px;margin-bottom:18px;font-size:.9rem;border:1px solid transparent;}
        .alert-error{background:rgba(248,113,113,.14);color:#fecaca;border-color:rgba(248,113,113,.35);}
        .alert-success{background:rgba(52,211,153,.15);color:#bbf7d0;border-color:rgba(52,211,153,.35);}

        .panel{
            background:linear-gradient(160deg,var(--panel),var(--panel2));
            border:1px solid var(--line);border-radius:18px;padding:22px;margin-bottom:22px;
        }
        .panel h2{margin:0 0 16px;font-size:1.15rem;display:flex;align-items:center;gap:9px;}
        .panel h2 .dot{width:9px;height:9px;border-radius:50%;background:var(--accent);box-shadow:0 0 12px var(--accent);}

        label{display:block;margin-bottom:14px;font-size:.86rem;font-weight:600;color:#cbd8ef;}
        input{
            display:block;width:100%;margin-top:6px;padding:10px 12px;
            background:#0a1124;border:1px solid var(--line);border-radius:10px;
            color:var(--ink);font-size:.95rem;font-family:inherit;
        }
        input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,140,255,.2);}
        input::placeholder{color:#5f7196;}

        fieldset{border:1px solid var(--line);border-radius:12px;padding:16px;margin:0 0 18px;background:rgba(10,17,36,.4);}
        legend{padding:0 8px;font-weight:700;color:var(--accent2);}
        label.check{display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:8px;color:#cdd9ef;}
        label.check input{width:auto;margin:0;}
        label.check code{font-family:ui-monospace,Consolas,monospace;background:#0a1124;border:1px solid var(--line);color:var(--muted);padding:1px 6px;border-radius:5px;font-size:.78rem;}

        .btn{
            display:inline-block;padding:12px 22px;border:none;border-radius:11px;cursor:pointer;
            background:linear-gradient(135deg,var(--accent),var(--violet));color:#fff;
            font-size:.95rem;font-weight:700;transition:filter .15s;
        }
        .btn:hover{filter:brightness(1.12);}
        .btn-link{background:none;border:none;color:var(--accent);cursor:pointer;font-size:.85rem;padding:0;text-decoration:underline;}
        .btn-link.danger{color:#fca5a5;}
        .row-actions{display:flex;align-items:center;gap:16px;margin-top:8px;flex-wrap:wrap;}

        .key-reveal{
            font-family:ui-monospace,"Cascadia Code",Consolas,monospace;
            background:#060b18;color:#4ade80;border:1px solid var(--line);
            padding:14px 16px;border-radius:10px;word-break:break-all;margin-bottom:8px;user-select:all;
        }

        .tbl{width:100%;border-collapse:collapse;font-size:.86rem;}
        .tbl th,.tbl td{text-align:left;padding:11px 8px;border-bottom:1px solid var(--line);vertical-align:top;}
        .tbl th{color:var(--muted);font-weight:600;}
        .tbl tr.revoked{opacity:.5;}
        .tbl code{font-family:ui-monospace,Consolas,monospace;background:#0a1124;border:1px solid var(--line);padding:2px 6px;border-radius:5px;color:#cdd9ef;}
        .chip{display:inline-block;margin:1px 2px;font-family:ui-monospace,Consolas,monospace;
              background:#16264a;color:#9fc0ff;border:1px solid var(--line);padding:1px 6px;border-radius:5px;font-size:.76rem;}
        .badge{display:inline-block;padding:3px 9px;border-radius:999px;font-size:.74rem;font-weight:600;}
        .badge-on{background:rgba(52,211,153,.16);color:#6ee7b7;}
        .badge-off{background:rgba(248,113,113,.16);color:#fca5a5;}
        td.actions{display:flex;align-items:center;gap:12px;white-space:nowrap;}
        td.actions form{margin:0;}
        .muted{color:var(--muted);}
    </style>
    <link id="theme-mario" rel="stylesheet" href="assets/css/mario-theme.css">
    <script src="assets/js/theme-switch.js"></script>
</head>
<body>
<header class="topbar">
    <div class="brand">CV <span>Luvumbu</span></div>
    <nav>
        <a href="dashboard.php">Tableau de bord</a>
        <a href="mes_cv.php">Mes CV</a>
        <a href="architecture.php">Architecture</a>
        <a href="logout.php">Déconnexion</a>
    </nav>
</header>

<div class="wrap">
    <div class="hero">
        <h1>Paramètres 🔑</h1>
        <p>Vos clés API pour accéder à vos CV à distance (lecture / écriture).</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($notice && !$newKey): ?>
        <div class="alert alert-success"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <!-- ===== Connexion avec Google ===== -->
    <div class="panel">
        <h2><span class="dot"></span> Connexion avec Google</h2>
        <p class="muted" style="margin-top:-8px">
            Permet de vous connecter en un clic avec un compte Google.
            Seules les adresses de la liste « Comptes Google autorisés » ci-dessous peuvent se connecter.
            Statut :
            <?php if (google_enabled()): ?>
                <span class="badge badge-on">Activée</span>
            <?php else: ?>
                <span class="badge badge-off">Désactivée</span>
            <?php endif; ?>
        </p>

        <!-- ===== Comptes Google autorisés (ajout / suppression) ===== -->
        <fieldset style="margin-top:18px">
            <legend>Comptes Google autorisés</legend>
            <p class="muted" style="font-size:.85rem;margin:0 0 14px">
                Seules ces adresses Google peuvent se connecter. Ajoutez ou retirez-les
                à tout moment.
            </p>

            <?php if (!$googleEmails): ?>
                <p class="muted" style="margin:0 0 14px">Aucune adresse autorisée pour l'instant.</p>
            <?php else: ?>
                <table class="tbl" style="width:100%;margin-bottom:14px">
                    <tbody>
                    <?php foreach ($googleEmails as $email): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($email) ?></code></td>
                            <td class="actions" style="text-align:right">
                                <form method="post" onsubmit="return confirm('Retirer cette adresse ?');">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="google_remove_email">
                                    <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                                    <button class="btn-link danger" type="submit">Retirer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <form method="post" class="inline-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="google_add_email">
                <label class="grow" style="margin-bottom:0">Ajouter une adresse Google
                    <input name="email" type="email" placeholder="personne@gmail.com" required>
                </label>
                <button class="btn btn-auto" type="submit">Ajouter</button>
            </form>
        </fieldset>

        <details style="margin-top:20px">
            <summary class="muted" style="cursor:pointer">Configuration avancée (ID client / secret)</summary>
            <p class="muted" style="font-size:.85rem;line-height:1.6;margin:12px 0">
                Les identifiants Google sont déjà intégrés : ne modifiez ceci que pour
                utiliser vos propres identifiants. URI de redirection à déclarer dans la
                console Google :
            </p>
            <div class="key-reveal" style="margin-bottom:16px"><?= htmlspecialchars($redirectUri) ?></div>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="set_google">
                <label>ID client Google
                    <input name="google_client_id" value="<?= htmlspecialchars($google['client_id']) ?>"
                           placeholder="xxxxx.apps.googleusercontent.com">
                </label>
                <label>Secret client Google
                    <input name="google_client_secret" type="password"
                           placeholder="<?= $google['client_secret'] !== '' ? '•••••••• (laisser vide pour conserver)' : 'GOCSPX-…' ?>">
                </label>
                <button class="btn" type="submit">Enregistrer les identifiants</button>
            </form>
        </details>
    </div>

    <!-- ===== Mon compte administrateur ===== -->
    <div class="panel">
        <h2><span class="dot"></span> Mon compte administrateur</h2>
        <p class="muted" style="margin-top:-8px">
            Identifiant actuel : <code><?= htmlspecialchars($account['username'] ?? '') ?></code>
            <?php if (!empty($account['email'])): ?> · e-mail : <code><?= htmlspecialchars($account['email']) ?></code><?php endif; ?>
        </p>

        <form method="post" style="margin-bottom:24px">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="set_email">
            <label>Adresse e-mail <span class="muted">(permet de se connecter avec l'e-mail)</span>
                <input name="email" type="email" value="<?= htmlspecialchars($account['email'] ?? '') ?>" placeholder="vous@exemple.com">
            </label>
            <button class="btn" type="submit">Enregistrer l'e-mail</button>
        </form>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="set_password">
            <label>Mot de passe actuel
                <input name="current_password" type="password" required>
            </label>
            <label>Nouveau mot de passe <span class="muted">(min. 6 caractères)</span>
                <input name="new_password" type="password" required>
            </label>
            <label>Confirmer le nouveau mot de passe
                <input name="confirm_password" type="password" required>
            </label>
            <button class="btn" type="submit">Changer le mot de passe</button>
        </form>
    </div>

    <?php if ($newKey !== null): ?>
        <div class="panel">
            <div class="alert alert-success">✓ Clé générée. Copiez-la maintenant : elle ne sera plus jamais affichée.</div>
            <div class="key-reveal"><?= htmlspecialchars($newKey) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($editing): ?>
        <!-- ===== Édition d'une clé ===== -->
        <div class="panel">
            <h2><span class="dot"></span> Modifier la clé</h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="key_id" value="<?= (int) $editing['id'] ?>">

                <label>Nom de la clé
                    <input name="label" value="<?= htmlspecialchars($editing['label']) ?>" required autofocus>
                </label>
                <p class="muted">Préfixe : <code><?= htmlspecialchars($editing['key_prefix']) ?>…</code> (le secret ne peut pas être réaffiché)</p>

                <fieldset>
                    <legend>Permissions</legend>
                    <?php render_scope_checkboxes($allScopes, scopes_to_array($editing['scopes'])); ?>
                </fieldset>

                <div class="row-actions">
                    <button class="btn" type="submit">Enregistrer</button>
                    <a class="btn-link" href="parametres.php">Annuler</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- ===== Création ===== -->
        <div class="panel">
            <h2><span class="dot"></span> Générer une clé API</h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="create">

                <label>Nom de la clé <span class="muted">(optionnel)</span>
                    <input name="label" placeholder="Ex. Application mobile">
                </label>

                <fieldset>
                    <legend>Permissions — ce que la clé pourra faire</legend>
                    <?php render_scope_checkboxes($allScopes, ['cv:read']); ?>
                </fieldset>

                <button class="btn" type="submit">Générer une clé API</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- ===== Clés existantes ===== -->
    <div class="panel">
        <h2><span class="dot"></span> Clés existantes</h2>
        <?php if (!$keys): ?>
            <p class="muted">Aucune clé pour l'instant.</p>
        <?php else: ?>
            <div style="overflow-x:auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Nom</th><th>Préfixe</th><th>Permissions</th>
                        <th>Dernière utilisation</th><th>Statut</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($keys as $k): ?>
                    <?php $kScopes = scopes_to_array($k['scopes']); ?>
                    <tr class="<?= $k['revoked_at'] ? 'revoked' : '' ?>">
                        <td><?= htmlspecialchars($k['label']) ?></td>
                        <td><code><?= htmlspecialchars($k['key_prefix']) ?>…</code></td>
                        <td>
                            <?php if ($kScopes): ?>
                                <?php foreach ($kScopes as $s): ?><span class="chip"><?= htmlspecialchars($s) ?></span><?php endforeach; ?>
                            <?php else: ?>
                                <span class="muted">aucune</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($k['last_used_at'] ?? '—') ?></td>
                        <td>
                            <?php if ($k['revoked_at']): ?>
                                <span class="badge badge-off">Révoquée</span>
                            <?php else: ?>
                                <span class="badge badge-on">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <?php if (!$k['revoked_at']): ?>
                                <a class="btn-link" href="parametres.php?edit=<?= (int) $k['id'] ?>">Modifier</a>
                                <form method="post" onsubmit="return confirm('Révoquer cette clé ?');">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="revoke">
                                    <input type="hidden" name="key_id" value="<?= (int) $k['id'] ?>">
                                    <button class="btn-link danger" type="submit">Révoquer</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
