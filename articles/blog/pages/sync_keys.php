<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/sync_keys.php';
require_admin();

const REVEAL_MAX_ATTEMPTS = 5;
const REVEAL_LOCKOUT      = 900; // 15 min

$newKey      = null;
$error       = null;
$revealed    = null;  // token complet, uniquement apres verification du mot de passe
$revealForm  = null;  // prefixe de la cle dont on demande le mot de passe

function reveal_locked_until(): int {
    $fails = (int)($_SESSION['reveal_fails'] ?? 0);
    $last  = (int)($_SESSION['reveal_last_fail'] ?? 0);
    if ($fails < REVEAL_MAX_ATTEMPTS) return 0;
    $until = $last + REVEAL_LOCKOUT;
    if ($until <= time()) {
        unset($_SESSION['reveal_fails'], $_SESSION['reveal_last_fail']);
        return 0;
    }
    return $until;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'CSRF invalide.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'generate') {
            $ttl = (int)($_POST['ttl'] ?? 3600);
            if ($ttl < 0) $ttl = 0;
            if ($ttl !== 0 && $ttl < 60) $ttl = 60;
            // pas de cap superieur : si l'utilisateur choisit "permanente", on garde 0.
            $newKey = sync_key_generate($ttl);
        } elseif ($action === 'revoke_all') {
            // Marque toutes les cles actives comme utilisees
            $keys = sync_keys_active();
            foreach ($keys as $k) { sync_key_consume($k['token']); }
        } elseif ($action === 'ask_reveal') {
            $revealForm = (string)($_POST['prefix'] ?? '');
        } elseif ($action === 'reveal') {
            $prefix = (string)($_POST['prefix'] ?? '');
            $until  = reveal_locked_until();
            if ($until > 0) {
                $error = 'Trop de tentatives. Réessaie dans '
                       . ceil(($until - time()) / 60) . ' minute(s).';
            } elseif (!verify_current_password((string)($_POST['password'] ?? ''))) {
                $_SESSION['reveal_fails']     = (int)($_SESSION['reveal_fails'] ?? 0) + 1;
                $_SESSION['reveal_last_fail'] = time();
                $remaining = REVEAL_MAX_ATTEMPTS - (int)$_SESSION['reveal_fails'];
                $error = 'Mot de passe incorrect.'
                       . ($remaining > 0 ? " Il te reste $remaining tentative(s)." : ' Compte temporairement bloqué.');
                $revealForm = $prefix;
            } else {
                unset($_SESSION['reveal_fails'], $_SESSION['reveal_last_fail']);
                $key = sync_key_find_by_prefix($prefix);
                if (!$key) {
                    $error = 'Clé introuvable.';
                } else {
                    $revealed = $key;
                }
            }
        }
    }
}

$active  = sync_keys_active();
$history = sync_keys_history(15);

$pageTitle = 'Synchronisation — Clés serveur';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-card auth-card-wide">
    <h1>🔑 Clés de synchronisation (côté serveur)</h1>
    <p class="muted">
        Génère ici une clé d'autorisation à usage unique, valable une durée limitée,
        puis colle-la dans l'instance locale pour autoriser l'envoi des données.
    </p>

    <?php if ($error): ?>
        <p class="flash flash-error"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($newKey): ?>
        <?php $isPermanent = empty($newKey['expires_at']); ?>
        <div class="flash flash-success">
            <strong>Nouvelle clé générée</strong> —
            <?php if ($isPermanent): ?>
                <span class="pill pill-warn">PERMANENTE</span> sans expiration, réutilisable indéfiniment.
            <?php else: ?>
                valable jusqu'au <?= e(date('Y-m-d H:i:s', $newKey['expires_at'])) ?> (usage unique).
            <?php endif; ?>
            <div style="display:flex; gap:8px; margin-top:10px; align-items:flex-start;">
                <input type="text" id="new-key-value" readonly
                       value="<?= e($newKey['token']) ?>"
                       style="flex:1; padding:10px; font-family:monospace; font-size:13px; border:1px solid rgba(0,0,0,0.1); border-radius:6px; background:#fff;"
                       onclick="this.select()">
                <button type="button" class="copy-btn" id="copy-key-btn" data-target="new-key-value">📋 Copier</button>
            </div>
            <p style="margin-top:8px; font-size:13px;">Tu pourras la réafficher plus tard depuis le tableau ci-dessous, en confirmant ton mot de passe.</p>
        </div>
    <?php endif; ?>

    <?php if ($revealed): ?>
        <div class="flash flash-success">
            <strong>Clé révélée</strong> — créée le <?= e($revealed['created_at'] ?? '—') ?>.
            <div style="display:flex; gap:8px; margin-top:10px; align-items:flex-start;">
                <input type="text" id="revealed-key-value" readonly
                       value="<?= e($revealed['token']) ?>"
                       style="flex:1; padding:10px; font-family:monospace; font-size:13px; border:1px solid rgba(0,0,0,0.1); border-radius:6px; background:#fff;"
                       onclick="this.select()">
                <button type="button" class="copy-btn" data-target="revealed-key-value">📋 Copier</button>
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
            <select name="ttl">
                <option value="300">5 minutes</option>
                <option value="1800">30 minutes</option>
                <option value="3600" selected>1 heure</option>
                <option value="21600">6 heures</option>
                <option value="86400">24 heures</option>
                <option value="0">♾️ Sans expiration (permanente, réutilisable)</option>
            </select>
        </label>
        <p class="muted" style="font-size:13px; margin-top:-6px;">
            Les clés normales sont à usage unique. Une clé <strong>permanente</strong> peut être utilisée plusieurs fois et ne s'efface jamais — pratique pour automatiser, mais à révoquer manuellement si compromise.
        </p>
        <button type="submit" class="btn-primary">🔑 Générer une nouvelle clé</button>
    </form>

    <h2>Clés actives (<?= count($active) ?>)</h2>
    <?php if (empty($active)): ?>
        <p class="muted">Aucune clé active actuellement.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Clé (préfixe)</th><th>Créée le</th><th>Expire dans</th><th>Statut</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($active as $k): $perm = empty($k['expires_at']); ?>
                <tr>
                    <td><code><?= e(substr($k['token'], 0, 14)) ?>…</code></td>
                    <td><?= e($k['created_at']) ?></td>
                    <?php if ($perm): ?>
                        <td>♾️ jamais</td>
                        <td><span class="pill pill-warn">permanente</span></td>
                    <?php else: ?>
                        <td data-countdown="<?= (int)$k['expires_at'] ?>"><?= e(date('H:i:s', $k['expires_at'])) ?></td>
                        <td><span class="pill pill-ok">active</span></td>
                    <?php endif; ?>
                    <td>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="ask_reveal">
                            <input type="hidden" name="prefix" value="<?= e(substr($k['token'], 0, 14)) ?>">
                            <button type="submit" class="copy-btn">👁️ Révéler & copier</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <script>
        (function () {
            const fmt = (s) => {
                if (s <= 0) return 'expirée';
                const h = Math.floor(s / 3600);
                const m = Math.floor((s % 3600) / 60);
                const sec = s % 60;
                if (h > 0) return h + 'h ' + m + 'min';
                if (m > 0) return m + 'min ' + sec + 's';
                return sec + 's';
            };
            const cells = document.querySelectorAll('[data-countdown]');
            const tick = () => {
                const now = Math.floor(Date.now() / 1000);
                cells.forEach(c => {
                    const target = parseInt(c.dataset.countdown, 10);
                    const remaining = target - now;
                    c.textContent = fmt(remaining);
                    if (remaining <= 0) {
                        const row = c.closest('tr');
                        if (row) {
                            const pill = row.querySelector('.pill');
                            if (pill) { pill.className = 'pill pill-danger'; pill.textContent = 'expirée'; }
                        }
                    }
                });
            };
            tick();
            setInterval(tick, 1000);
        })();
        </script>
        <form method="post" style="margin-top:12px;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="revoke_all">
            <button type="submit" class="btn-danger" onclick="return confirm('Révoquer toutes les clés actives ?');">Révoquer toutes les clés actives</button>
        </form>
    <?php endif; ?>

    <h2 style="margin-top:32px;">Historique (15 dernières)</h2>
    <?php if (empty($history)): ?>
        <p class="muted">Aucun historique.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Clé</th><th>Créée le</th><th>Expire le</th><th>Utilisée le</th></tr></thead>
            <tbody>
            <?php foreach ($history as $k): ?>
                <tr>
                    <td><code><?= e(substr($k['token'], 0, 12)) ?>…</code></td>
                    <td><?= e($k['created_at'] ?? '—') ?></td>
                    <td><?= empty($k['expires_at']) ? '♾️ jamais' : e(date('Y-m-d H:i:s', $k['expires_at'])) ?></td>
                    <td><?= e($k['used_at'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
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
    btn.classList.add('copied');
    btn.textContent = 'Copié';
    setTimeout(() => {
        btn.classList.remove('copied');
        btn.textContent = label;
    }, 1800);
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
