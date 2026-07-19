<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

// La revelation d'un token complet est protegee par une reconfirmation du mot de
// passe, avec blocage temporaire apres plusieurs echecs — meme logique que sync_keys.php.
const TOKEN_REVEAL_MAX_ATTEMPTS = 5;
const TOKEN_REVEAL_LOCKOUT      = 900; // 15 min

$newToken   = null;
$error      = null;
$revealed   = null;  // token complet, uniquement apres verification du mot de passe
$revealForm = null;  // prefixe du token dont on demande le mot de passe

function token_reveal_locked_until(): int {
    $fails = (int)($_SESSION['token_reveal_fails'] ?? 0);
    $last  = (int)($_SESSION['token_reveal_last_fail'] ?? 0);
    if ($fails < TOKEN_REVEAL_MAX_ATTEMPTS) return 0;
    $until = $last + TOKEN_REVEAL_LOCKOUT;
    if ($until <= time()) {
        unset($_SESSION['token_reveal_fails'], $_SESSION['token_reveal_last_fail']);
        return 0;
    }
    return $until;
}

function token_find_by_prefix(PDO $pdo, string $prefix): ?array {
    if (!preg_match('/^[0-9a-f]{4,64}$/', $prefix)) return null;
    $stmt = $pdo->prepare('SELECT * FROM api_tokens WHERE token LIKE ? LIMIT 2');
    $stmt->execute([$prefix . '%']);
    $rows = $stmt->fetchAll();
    return count($rows) === 1 ? $rows[0] : null; // jamais de prefixe ambigu
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'CSRF invalide.';
    } else {
        $action = $_POST['action'] ?? '';
        $me     = current_user();

        if ($action === 'generate') {
            $days = (int)($_POST['days'] ?? 30);
            if ($days < 1)    $days = 1;
            if ($days > 3650) $days = 3650;

            $newToken = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare(
                'INSERT INTO api_tokens (token, user_id, expires_at)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))'
            );
            $stmt->execute([$newToken, (int)$me['id'], $days]);

        } elseif ($action === 'ask_reveal') {
            $revealForm = (string)($_POST['prefix'] ?? '');

        } elseif ($action === 'reveal') {
            $prefix = (string)($_POST['prefix'] ?? '');
            $until  = token_reveal_locked_until();
            if ($until > 0) {
                $error = 'Trop de tentatives. Réessaie dans '
                       . ceil(($until - time()) / 60) . ' minute(s).';
            } elseif (!verify_current_password((string)($_POST['password'] ?? ''))) {
                $_SESSION['token_reveal_fails']     = (int)($_SESSION['token_reveal_fails'] ?? 0) + 1;
                $_SESSION['token_reveal_last_fail'] = time();
                $remaining = TOKEN_REVEAL_MAX_ATTEMPTS - (int)$_SESSION['token_reveal_fails'];
                $error = 'Mot de passe incorrect.'
                       . ($remaining > 0 ? " Il te reste $remaining tentative(s)." : ' Révélation temporairement bloquée.');
                $revealForm = $prefix;
            } else {
                unset($_SESSION['token_reveal_fails'], $_SESSION['token_reveal_last_fail']);
                $row = token_find_by_prefix($pdo, $prefix);
                if (!$row) {
                    $error = 'Clé introuvable.';
                } else {
                    $revealed = $row;
                }
            }

        } elseif ($action === 'revoke') {
            $stmt = $pdo->prepare('DELETE FROM api_tokens WHERE token = ?');
            $stmt->execute([(string)($_POST['token'] ?? '')]);

        } elseif ($action === 'revoke_all') {
            $stmt = $pdo->prepare('DELETE FROM api_tokens WHERE user_id = ?');
            $stmt->execute([(int)$me['id']]);
        }
    }
}

$stmt = $pdo->query("
    SELECT t.token, t.created_at, t.expires_at, t.last_used_at,
           u.email, (t.expires_at > NOW()) AS actif
    FROM api_tokens t
    JOIN users u ON u.id = t.user_id
    ORDER BY t.created_at DESC
    LIMIT 20
");
$tokens = $stmt->fetchAll();

$pageTitle = 'Clés API';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-card auth-card-wide">
    <h1>🔐 Clés API</h1>
    <p class="muted">
        Une clé API permet de publier, modifier ou supprimer des articles à distance,
        sans passer par ce site. Traite-la comme un mot de passe.
    </p>

    <?php if ($error): ?>
        <p class="flash flash-error"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($newToken): ?>
        <div class="flash flash-success">
            <strong>Nouvelle clé générée.</strong>
            <div style="display:flex; gap:8px; margin-top:10px; align-items:flex-start;">
                <input type="text" id="new-token-value" readonly value="<?= e($newToken) ?>"
                       style="flex:1; padding:10px; font-family:monospace; font-size:13px; border:1px solid rgba(0,0,0,0.1); border-radius:6px; background:#fff;"
                       onclick="this.select()">
                <button type="button" class="copy-btn" data-target="new-token-value">📋 Copier</button>
            </div>
            <p style="margin-top:8px; font-size:13px;">Tu pourras la réafficher plus tard depuis le tableau ci-dessous, en confirmant ton mot de passe.</p>
        </div>
    <?php endif; ?>

    <?php if ($revealed): ?>
        <div class="flash flash-success">
            <strong>Clé révélée</strong> — créée le <?= e($revealed['created_at']) ?>.
            <div style="display:flex; gap:8px; margin-top:10px; align-items:flex-start;">
                <input type="text" id="revealed-token-value" readonly value="<?= e($revealed['token']) ?>"
                       style="flex:1; padding:10px; font-family:monospace; font-size:13px; border:1px solid rgba(0,0,0,0.1); border-radius:6px; background:#fff;"
                       onclick="this.select()">
                <button type="button" class="copy-btn" data-target="revealed-token-value">📋 Copier</button>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($revealForm !== null): ?>
        <div class="flash">
            <strong>Confirme ton mot de passe</strong> pour afficher la clé
            <code><?= e(substr($revealForm, 0, 14)) ?>…</code>
            <form method="post" style="display:flex; gap:8px; margin-top:10px; align-items:flex-start;">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="reveal">
                <input type="hidden" name="prefix" value="<?= e($revealForm) ?>">
                <input type="password" name="password" required autofocus
                       autocomplete="current-password" placeholder="Mot de passe administrateur"
                       style="flex:1; padding:10px; border:1px solid rgba(0,0,0,0.1); border-radius:6px;">
                <button type="submit" class="btn-primary">👁️ Afficher la clé</button>
            </form>
        </div>
    <?php endif; ?>

    <form method="post" class="form" style="margin-bottom:24px;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="generate">
        <label>
            <span class="label-text">Durée de validité</span>
            <select name="days">
                <option value="30" selected>30 jours</option>
                <option value="90">90 jours</option>
                <option value="365">1 an</option>
                <option value="3650">10 ans</option>
            </select>
        </label>
        <button type="submit" class="btn-primary">🔑 Générer une clé API</button>
    </form>

    <h2>Clés existantes (<?= count($tokens) ?>)</h2>
    <?php if (empty($tokens)): ?>
        <p class="muted">Aucune clé. Génère-en une ci-dessus.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Clé</th><th>Compte</th><th>Créée le</th><th>Expire le</th><th>Dernier usage</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tokens as $t): ?>
                <tr>
                    <td><code><?= e(substr($t['token'], 0, 14)) ?>…</code></td>
                    <td><?= e($t['email']) ?></td>
                    <td><?= e($t['created_at']) ?></td>
                    <td><?= e($t['expires_at']) ?></td>
                    <td><?= e($t['last_used_at'] ?: 'jamais') ?></td>
                    <td>
                        <?php if ((int)$t['actif'] === 1): ?>
                            <span class="pill pill-ok">active</span>
                        <?php else: ?>
                            <span class="pill pill-danger">expirée</span>
                        <?php endif; ?>
                    </td>
                    <td style="display:flex; gap:6px;">
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="ask_reveal">
                            <input type="hidden" name="prefix" value="<?= e(substr($t['token'], 0, 14)) ?>">
                            <button type="submit" class="copy-btn">👁️ Révéler</button>
                        </form>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="revoke">
                            <input type="hidden" name="token" value="<?= e($t['token']) ?>">
                            <button type="submit" class="btn-danger" onclick="return confirm('Révoquer cette clé ?');">Révoquer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" style="margin-top:12px;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="revoke_all">
            <button type="submit" class="btn-danger" onclick="return confirm('Révoquer toutes tes clés ?');">Révoquer toutes mes clés</button>
        </form>
    <?php endif; ?>

    <h2 style="margin-top:32px;">Comment s'en servir</h2>
    <p class="muted">Publier un article à distance, avec la clé passée en champ <code>api_key</code> :</p>
    <pre style="background:#2d2d2d; color:#dcdcdc; padding:12px; border-radius:6px; overflow-x:auto; font-size:13px;">curl -X POST <?= e(base_url('api/article.php')) ?> \
  -H "Content-Type: application/json" \
  -d '{"api_key":"TA_CLE","titre":"Mon titre","contenu":"Mon texte"}'</pre>
</div>
<script>
document.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('.copy-btn[data-target]');
    if (!btn) return;
    const target = document.getElementById(btn.dataset.target);
    if (!target) return;
    const label = btn.textContent;
    try {
        await navigator.clipboard.writeText(target.value);
    } catch (_) {
        target.select();
        document.execCommand('copy');
    }
    btn.textContent = 'Copié';
    setTimeout(() => { btn.textContent = label; }, 1800);
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
