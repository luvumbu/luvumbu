<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/sync_dump.php';
require_admin();

$UPLOADS_DIR = __DIR__ . '/../uploads';

// ---- Statistiques locales ----
$stats = [];
foreach (SYNC_TABLES as $t) {
    $stats[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
}
$uploadsCount = 0;
$uploadsBytes = 0;
if (is_dir($UPLOADS_DIR)) {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($UPLOADS_DIR, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iter as $f) {
        if ($f->isFile() && $f->getFilename() !== '.gitkeep') {
            $uploadsCount++;
            $uploadsBytes += $f->getSize();
        }
    }
}
$uploadsMb = $uploadsBytes > 0 ? number_format($uploadsBytes / 1048576, 1) : '0';

// ---- Traitement POST (envoi synchrone) ----
$result = null;

// Enregistrement de la cible (URL + clé permanente) pour l'envoi rapide d'un article.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '') && ($_POST['action'] ?? '') === 'save_target') {
    set_setting('sync_remote_url', trim((string)($_POST['remote_url'] ?? '')));
    set_setting('sync_remote_key', trim((string)($_POST['token'] ?? '')));
    $result = ['ok' => true, 'message' => 'Cible enregistrée. Le bouton « 📤 Envoyer vers le serveur » de chaque article utilisera cette URL + clé.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '') && ($_POST['action'] ?? '') !== 'save_target') {
    $remoteUrl      = trim((string)($_POST['remote_url'] ?? ''));
    $token          = trim((string)($_POST['token']      ?? ''));
    $mode           = ($_POST['mode'] ?? 'miroir') === 'fusion' ? 'fusion' : 'miroir';
    $includeDb      = !empty($_POST['include_db']);
    $includeUploads = !empty($_POST['include_uploads']);
    $dryRun         = !empty($_POST['dry_run']);
    $insecureSsl    = !empty($_POST['insecure_ssl']);
    $confirm        = ($_POST['confirm'] ?? '') === 'yes';

    if (!$confirm) {
        $result = ['ok' => false, 'error' => 'Tu dois confirmer en cochant la case.'];
    } elseif (!filter_var($remoteUrl, FILTER_VALIDATE_URL)) {
        $result = ['ok' => false, 'error' => 'URL distante invalide.'];
    } elseif ($token === '') {
        $result = ['ok' => false, 'error' => 'Clé requise — génère-la sur le serveur distant.'];
    } elseif (!$includeDb && !$includeUploads) {
        $result = ['ok' => false, 'error' => 'Rien à envoyer : coche au moins BDD ou Images.'];
    } else {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $zipFile = tempnam(sys_get_temp_dir(), 'syncpay_');
        $log = [];
        $t0  = microtime(true);
        try {
            $log[] = 'Construction du payload (' . ($includeDb ? 'BDD' : '') . ($includeDb && $includeUploads ? ' + ' : '') . ($includeUploads ? 'images' : '') . ')…';
            $build    = sync_build_payload($pdo, $UPLOADS_DIR, $zipFile, $includeDb, $includeUploads);
            $zipBytes = @filesize($zipFile) ?: 0;
            $log[] = 'Payload prêt : ' . ($includeDb ? 'data.json inclus' : 'BDD exclue')
                . ' · ' . (int)$build['uploads'] . ' image(s) · ' . number_format($zipBytes / 1048576, 2) . ' Mo.';
            $log[] = 'Envoi vers ' . $remoteUrl . ' (mode ' . $mode
                . ($dryRun ? ', dry-run' : '') . ($insecureSsl ? ', SSL non vérifié' : '') . ')…';

            $ch = curl_init($remoteUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_TIMEOUT        => 300,
                CURLOPT_SSL_VERIFYPEER => $insecureSsl ? false : true,
                CURLOPT_SSL_VERIFYHOST => $insecureSsl ? 0 : 2,
                CURLOPT_POSTFIELDS     => [
                    'token'           => $token,
                    'mode'            => $mode,
                    'include_db'      => $includeDb      ? '1' : '0',
                    'include_uploads' => $includeUploads ? '1' : '0',
                    'dry_run'         => $dryRun         ? '1' : '0',
                    'payload'         => new CURLFile($zipFile, 'application/zip', 'payload.zip'),
                ],
            ]);
            $response  = curl_exec($ch);
            $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $totalTime = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            $curlErr   = curl_error($ch);
            curl_close($ch);

            $log[] = $curlErr
                ? 'Échec réseau : ' . $curlErr
                : 'Réponse reçue : HTTP ' . $httpCode . ' en ' . number_format($totalTime, 1) . ' s.';

            $base = [
                'mode'    => $mode,
                'http'    => $httpCode,
                'elapsed' => microtime(true) - $t0,
                'sent'    => ['db' => $includeDb, 'uploads_files' => (int)$build['uploads'], 'zip_bytes' => $zipBytes],
                'raw'     => (string)$response,
                'log'     => $log,
            ];

            if ($curlErr) {
                $hint = (stripos($curlErr, 'ssl') !== false || stripos($curlErr, 'certificate') !== false)
                    ? ' — astuce : en local, coche « Ignorer la vérification SSL ».' : '';
                $result = array_merge($base, ['ok' => false, 'dry_run' => $dryRun, 'error' => "Erreur réseau : $curlErr$hint"]);
            } else {
                $data = json_decode((string)$response, true);
                if ($httpCode === 200 && is_array($data) && !empty($data['ok'])) {
                    $result = array_merge($base, [
                        'ok'      => true,
                        'message' => $data['message'] ?? 'Synchronisation OK',
                        'summary' => $data['summary'] ?? null,
                        'dry_run' => !empty($data['dry_run']),
                    ]);
                } else {
                    $msg = is_array($data) && isset($data['error'])
                        ? $data['error'] : (substr((string)$response, 0, 300) ?: 'Erreur inconnue');
                    $result = array_merge($base, ['ok' => false, 'dry_run' => $dryRun, 'error' => "$msg (HTTP $httpCode)"]);
                }
            }
        } catch (Throwable $e) {
            $result = ['ok' => false, 'error' => $e->getMessage(), 'log' => $log];
        } finally {
            @unlink($zipFile);
        }
    }
}

$pageTitle = 'Envoyer vers le serveur';
include __DIR__ . '/../includes/header.php';

$defaultUrl = 'https://blog.mariondelval.com/api/sync_receive.php';
?>
<div class="auth-card auth-card-wide">
    <h1>📤 Envoyer vers le serveur</h1>
    <p class="muted">
        Pousse les données et/ou les images de cette instance locale vers une instance distante.
        Choisis le mode et le contenu avant d'envoyer.
    </p>

    <?php if ($result): ?>
        <p class="flash <?= $result['ok'] ? 'flash-success' : 'flash-error' ?>">
            <?= e($result['ok'] ? ($result['message'] ?? 'Terminé') : $result['error']) ?>
            <?= !empty($result['dry_run']) ? ' (dry-run : rien appliqué)' : '' ?>
        </p>

        <div class="section-block">
            <div class="section-head"><span class="ico">🧾</span><h3>Rapport de l'envoi</h3></div>

            <?php if (!empty($result['log'])): ?>
                <ol style="margin:0 0 4px 18px; padding:0; font-size:13.5px; line-height:1.7;">
                    <?php foreach ($result['log'] as $line): ?>
                        <li><?= e($line) ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <div class="mini-stats" style="margin-top:12px;">
                <?php if (isset($result['mode'])): ?>
                    <div class="mini-stat"><span class="v"><?= e($result['mode']) ?></span><span class="k">mode</span></div>
                <?php endif; ?>
                <div class="mini-stat"><span class="v"><?= !empty($result['dry_run']) ? 'oui' : 'non' ?></span><span class="k">dry-run</span></div>
                <?php if (isset($result['http'])): ?>
                    <div class="mini-stat"><span class="v"><?= (int)$result['http'] ?></span><span class="k">code HTTP</span></div>
                <?php endif; ?>
                <?php if (isset($result['elapsed'])): ?>
                    <div class="mini-stat"><span class="v"><?= number_format($result['elapsed'], 1) ?> s</span><span class="k">durée</span></div>
                <?php endif; ?>
                <?php if (isset($result['sent']['zip_bytes'])): ?>
                    <div class="mini-stat"><span class="v"><?= number_format($result['sent']['zip_bytes'] / 1048576, 2) ?> Mo</span><span class="k">taille envoyée</span></div>
                <?php endif; ?>
                <?php if (isset($result['sent']['uploads_files'])): ?>
                    <div class="mini-stat"><span class="v"><?= (int)$result['sent']['uploads_files'] ?></span><span class="k">images envoyées</span></div>
                <?php endif; ?>
                <?php if (isset($result['sent']['db'])): ?>
                    <div class="mini-stat"><span class="v"><?= $result['sent']['db'] ? 'oui' : 'non' ?></span><span class="k">BDD incluse</span></div>
                <?php endif; ?>
            </div>

            <?php if (!empty($result['summary'])): $s = $result['summary']; ?>
                <p class="muted" style="margin-top:14px;"><strong>Ce que le serveur a appliqué :</strong></p>
                <?php if (!empty($s['db'])): ?>
                    <p style="font-size:13px; margin:4px 0;">BDD — <?php
                        $parts = [];
                        foreach ($s['db'] as $t => $c) {
                            $parts[] = is_array($c) ? "$t : +{$c['added']} / {$c['skipped']} ignoré(s)" : "$t : $c";
                        }
                        echo e(implode(' · ', $parts));
                    ?></p>
                <?php endif; ?>
                <?php if (isset($s['uploads'])): ?>
                    <p style="font-size:13px; margin:4px 0;">Images — <?= is_array($s['uploads'])
                        ? '+' . (int)$s['uploads']['added'] . ' ajoutée(s), ' . (int)$s['uploads']['skipped'] . ' ignorée(s)'
                        : (int)$s['uploads'] . ' fichier(s)' ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($result['raw'])): ?>
                <details style="margin-top:12px;">
                    <summary class="muted" style="cursor:pointer;">Voir la réponse brute du serveur (JSON)</summary>
                    <pre style="background:#1e1e1e; color:#dcdcdc; padding:12px; border-radius:6px; overflow:auto; font-size:12px; margin-top:8px; white-space:pre-wrap; word-break:break-word;"><?= e(substr($result['raw'], 0, 4000)) ?></pre>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="section-block">
        <div class="section-head"><span class="ico">🎯</span><h3>Cible enregistrée (pour le bouton « Envoyer » des articles)</h3></div>
        <p class="muted" style="margin:0 0 10px;">
            Colle ici l'URL et une <strong>clé permanente</strong> générée sur le serveur.
            Une fois enregistrées, le bouton <strong>📤 Envoyer vers le serveur</strong> présent sur chaque
            article s'en sert automatiquement (plus besoin de recoller la clé à chaque fois).
        </p>
        <form method="post" class="form" style="gap:10px;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_target">
            <label>
                <span class="label-text">URL de l'endpoint distant</span>
                <input type="url" name="remote_url" value="<?= e(get_setting('sync_remote_url', $defaultUrl)) ?>" required>
            </label>
            <label>
                <span class="label-text">Clé permanente</span>
                <input type="text" name="token" value="<?= e(get_setting('sync_remote_key', '')) ?>"
                       placeholder="Colle ici la clé permanente du serveur" autocomplete="off" spellcheck="false">
            </label>
            <div>
                <button type="submit" class="btn-secondary">💾 Enregistrer la cible</button>
                <small class="muted" style="margin-left:8px;">
                    <?= get_setting('sync_remote_key', '') !== '' ? '✅ Une clé est déjà enregistrée.' : '⚠️ Aucune clé enregistrée pour l\'instant.' ?>
                </small>
            </div>
        </form>
    </div>

    <div class="section-block">
        <div class="section-head"><span class="ico">📊</span><h3>Local vs distant (comparer avant d'envoyer)</h3></div>
        <p class="muted" style="margin-bottom:8px;">Local :</p>
        <div class="mini-stats">
            <?php foreach ($stats as $t => $n): ?>
                <div class="mini-stat" data-local="<?= e($t) ?>"><span class="v"><?= $n ?></span><span class="k"><?= e($t) ?></span></div>
            <?php endforeach; ?>
            <div class="mini-stat"><span class="v"><?= $uploadsCount ?></span><span class="k">fichiers uploads</span></div>
            <div class="mini-stat"><span class="v"><?= $uploadsMb ?> Mo</span><span class="k">taille uploads</span></div>
        </div>
        <div style="margin-top:14px;">
            <button type="button" id="fetch-remote-btn" class="btn-secondary">📡 Récupérer les stats du serveur</button>
            <small class="muted" style="margin-left:8px;">Renseigne d'abord URL + clé (la clé n'est pas consommée).</small>
        </div>
        <div id="remote-stats" hidden style="margin-top:14px;">
            <p class="muted" style="margin-bottom:8px;">Distant :</p>
            <div class="mini-stats" id="remote-stats-grid"></div>
            <p id="diff-summary" class="muted" style="margin-top:10px; font-size:13px;"></p>
        </div>
        <p id="remote-stats-error" hidden style="margin-top:10px; color:#b91c1c;"></p>
    </div>

    <form method="post" class="form" id="sync-push-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="section-block">
            <div class="section-head"><span class="ico">🌐</span><h3>Destination</h3></div>
            <label>
                <span class="label-text">URL de l'endpoint distant</span>
                <input type="url" name="remote_url" value="<?= e($_POST['remote_url'] ?? $defaultUrl) ?>" required>
            </label>
            <label>
                <span class="label-text">Clé d'autorisation (générée sur le serveur)</span>
                <input type="text" name="token" placeholder="Colle ici la clé fournie par le serveur" required
                       autocomplete="off" spellcheck="false">
            </label>
        </div>

        <div class="section-block">
            <div class="section-head"><span class="ico">⚡</span><h3>Raccourci</h3></div>
            <p class="muted" style="margin:0 0 10px;">Pour pousser <strong>uniquement les articles (sans photos)</strong> :</p>
            <button type="button" class="btn-secondary" id="preset-articles-only">📝 Articles seulement (sans photos)</button>
            <small class="muted" style="display:block; margin-top:6px;">Coche BDD, décoche Images, et passe en mode Fusion (ajoute sans rien écraser).</small>
        </div>

        <div class="section-block">
            <div class="section-head"><span class="ico">⚙️</span><h3>Mode d'application côté serveur</h3></div>
            <label class="radio-label">
                <input type="radio" name="mode" value="miroir" <?= ($_POST['mode'] ?? 'miroir') === 'miroir' ? 'checked' : '' ?>>
                <strong>🪞 Miroir (remplacer)</strong> — le serveur supprime tout et prend exactement ce que tu envoies.
            </label>
            <label class="radio-label" style="margin-top:8px;">
                <input type="radio" name="mode" value="fusion" <?= ($_POST['mode'] ?? '') === 'fusion' ? 'checked' : '' ?>>
                <strong>➕ Fusion (ajouter)</strong> — le serveur garde son contenu et ajoute seulement les éléments qui n'existent pas chez lui (par ID).
            </label>
        </div>

        <div class="section-block">
            <div class="section-head"><span class="ico">📦</span><h3>Contenu à envoyer</h3></div>
            <label class="checkbox-label">
                <input type="checkbox" name="include_db" value="1" <?= !isset($_POST['confirm']) || !empty($_POST['include_db']) ? 'checked' : '' ?>>
                Base de données (settings, users, articles, commentaires, etc.)
            </label>
            <label class="checkbox-label" style="margin-top:6px;">
                <input type="checkbox" name="include_uploads" value="1" <?= !isset($_POST['confirm']) || !empty($_POST['include_uploads']) ? 'checked' : '' ?>>
                Images du dossier <code>uploads/</code> (<?= $uploadsCount ?> fichiers, <?= $uploadsMb ?> Mo)
            </label>
        </div>

        <div class="section-block">
            <div class="section-head"><span class="ico">🧪</span><h3>Options</h3></div>
            <label class="checkbox-label">
                <input type="checkbox" name="dry_run" value="1" <?= !empty($_POST['dry_run']) ? 'checked' : '' ?>>
                <strong>Dry-run</strong> — simulation : le serveur valide la clé et le payload mais n'applique rien.
            </label>
            <label class="checkbox-label" style="margin-top:14px;">
                <input type="checkbox" name="insecure_ssl" value="1" <?= !empty($_POST['insecure_ssl']) ? 'checked' : '' ?>>
                <strong>Ignorer la vérification SSL</strong> — dépannage local uniquement (XAMPP sans bundle de certificats).
            </label>
            <label class="checkbox-label" style="margin-top:14px;">
                <input type="checkbox" name="confirm" value="yes" required>
                J'ai bien compris ce qui va se passer côté serveur.
            </label>
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button type="submit" class="btn-primary">📤 Envoyer maintenant</button>
            <a href="<?= e(base_url('pages/admin.php')) ?>" class="btn-secondary">Annuler</a>
        </div>
    </form>
</div>

<div id="busy" class="busy-overlay" hidden>
    <div class="busy-card">
        <div class="spinner"></div>
        <h3>Envoi en cours…</h3>
        <p>Construction puis transfert vers le serveur.<br>La page se rechargera avec le rapport complet.</p>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('sync-push-form');
    const busy = document.getElementById('busy');
    if (!form || !busy) return;

    // Envoi synchrone simple : on affiche l'overlay, le formulaire se soumet
    // normalement et la page recharge avec le rapport rendu côté serveur.
    form.addEventListener('submit', () => {
        if (!form.checkValidity()) return;
        busy.hidden = false;
    });

    // Raccourci « Articles seulement (sans photos) »
    const preset = document.getElementById('preset-articles-only');
    if (preset) {
        preset.addEventListener('click', () => {
            const db = form.querySelector('input[name=include_db]');
            const up = form.querySelector('input[name=include_uploads]');
            const fusion = form.querySelector('input[name=mode][value=fusion]');
            if (db) db.checked = true;
            if (up) up.checked = false;
            if (fusion) fusion.checked = true;
            preset.textContent = '✓ Articles seulement (sans photos)';
            setTimeout(() => { preset.textContent = '📝 Articles seulement (sans photos)'; }, 1500);
        });
    }

    // ---- Stats serveur distant ----
    const btn        = document.getElementById('fetch-remote-btn');
    const remoteBox  = document.getElementById('remote-stats');
    const remoteGrid = document.getElementById('remote-stats-grid');
    const diffEl     = document.getElementById('diff-summary');
    const errEl      = document.getElementById('remote-stats-error');
    if (!btn) return;

    const localStats = <?= json_encode($stats, JSON_UNESCAPED_UNICODE) ?>;
    const localUploadsCount = <?= (int)$uploadsCount ?>;

    const fmtMb = (b) => b > 0 ? (b / 1048576).toFixed(1) + ' Mo' : '0';
    const diffSign = (l, r) => {
        if (r === null || r === undefined) return '?';
        const d = l - r;
        if (d === 0) return '=';
        return d > 0 ? '+' + d : String(d);
    };

    btn.addEventListener('click', async () => {
        const urlInput = form.querySelector('input[name=remote_url]');
        const tokenInput = form.querySelector('input[name=token]');
        if (!urlInput.value || !tokenInput.value) {
            errEl.hidden = false;
            errEl.textContent = 'Renseigne l\'URL et la clé avant de récupérer les stats.';
            remoteBox.hidden = true;
            return;
        }
        const statsUrl = urlInput.value.replace(/sync_receive\.php(\?.*)?$/, 'sync_stats.php');
        errEl.hidden = true;
        btn.disabled = true;
        btn.textContent = '⏳ Récupération…';
        try {
            const fd = new FormData();
            fd.append('token', tokenInput.value);
            const res = await fetch(statsUrl, { method: 'POST', body: fd });
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error(data.error || ('HTTP ' + res.status));

            remoteGrid.innerHTML = '';
            const tables = data.tables || {};
            let totalDiff = 0;
            Object.keys(localStats).forEach(t => {
                const local = localStats[t];
                const remote = tables[t];
                const d = diffSign(local, remote);
                if (typeof remote === 'number') totalDiff += (local - remote);
                const div = document.createElement('div');
                div.className = 'mini-stat';
                div.innerHTML = '<span class="v">' + (remote == null ? '?' : remote) + '</span>' +
                    '<span class="k">' + t + ' <small style="color:#16a34a;">(' + d + ')</small></span>';
                remoteGrid.appendChild(div);
            });
            const ru = data.uploads || { count: 0, bytes: 0 };
            const cellU = document.createElement('div');
            cellU.className = 'mini-stat';
            cellU.innerHTML = '<span class="v">' + ru.count + '</span><span class="k">fichiers uploads <small>(' + diffSign(localUploadsCount, ru.count) + ')</small></span>';
            remoteGrid.appendChild(cellU);
            const cellB = document.createElement('div');
            cellB.className = 'mini-stat';
            cellB.innerHTML = '<span class="v">' + fmtMb(ru.bytes) + '</span><span class="k">taille uploads</span>';
            remoteGrid.appendChild(cellB);

            diffEl.innerHTML = totalDiff === 0
                ? '<strong>Aucune différence détectée</strong> sur les tables.'
                : 'Différence totale (lignes BDD) : <strong>' + (totalDiff > 0 ? '+' : '') + totalDiff + '</strong> (local vs distant).';
            remoteBox.hidden = false;
        } catch (e) {
            errEl.hidden = false;
            errEl.textContent = 'Impossible de récupérer les stats : ' + e.message;
            remoteBox.hidden = true;
        } finally {
            btn.disabled = false;
            btn.textContent = '📡 Récupérer les stats du serveur';
        }
    });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
