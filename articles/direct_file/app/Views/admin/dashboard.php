<?php /** @var array $convs @var array $theme @var array $labels @var string $notice */ ?>
<div class="admin-wrap">
    <div class="admin-head">
        <h1>🛠️ Administration</h1>
        <form method="post" action="<?= e(base_url('admin')) ?>" style="margin:0">
            <input type="hidden" name="action" value="logout">
            <button class="btn small" type="submit">Se déconnecter</button>
        </form>
    </div>

    <?php if ($notice): ?>
        <div class="section" style="padding:14px 24px"><?= e($notice) ?></div>
    <?php endif; ?>

    <!-- Réglage des couleurs -->
    <div class="section">
        <h2>🎨 Couleurs du thème</h2>
        <form method="post" action="<?= e(base_url('admin')) ?>">
            <input type="hidden" name="action" value="save_theme">
            <div class="swatches">
                <?php foreach ($labels as $key => $label): ?>
                    <div class="swatch">
                        <label><?= e($label) ?></label>
                        <input type="color" name="<?= e($key) ?>" value="<?= e($theme[$key]) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="row-actions">
                <button class="btn" type="submit">Enregistrer les couleurs</button>
        </form>
            <form method="post" action="<?= e(base_url('admin')) ?>" style="margin:0">
                <input type="hidden" name="action" value="reset_theme">
                <button class="btn small" type="submit" style="background:var(--surface-2)">Réinitialiser</button>
            </form>
            </div>
    </div>

    <!-- Liste des conversations -->
    <div class="section">
        <h2>📋 Toutes les conversations (<?= count($convs) ?>)</h2>
        <?php if (!$convs): ?>
            <p class="muted">Aucune conversation pour le moment.</p>
        <?php else: ?>
            <table>
                <thead><tr>
                    <th>Code</th><th>Titre</th><th>Accès</th><th>Messages</th>
                    <th>Participants</th><th>Créée le</th><th></th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($convs as $c): ?>
                    <tr>
                        <td><strong style="letter-spacing:1px"><?= e($c['code']) ?></strong></td>
                        <td><?= e($c['title'] !== '' ? $c['title'] : '—') ?></td>
                        <td><?= $c['is_open'] ? '🌐 Libre' : '🔒 Protégée' ?></td>
                        <td><?= (int) $c['msg_count'] ?></td>
                        <td><?= (int) $c['part_count'] ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime($c['created_at']))) ?></td>
                        <td><a href="<?= e(base_url('admin?conv=' . (int) $c['id'])) ?>">Lire →</a></td>
                        <td>
                            <form method="post" action="<?= e(base_url('admin')) ?>" style="margin:0"
                                  onsubmit="return confirm('Supprimer la discussion <?= e($c['code']) ?> et tous ses messages ?');">
                                <input type="hidden" name="action" value="delete_conv">
                                <input type="hidden" name="conv_id" value="<?= (int) $c['id'] ?>">
                                <button type="submit" class="link-btn" style="color:#f87171">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <p><a class="back" href="<?= e(base_url()) ?>">← Retour à l'accueil</a></p>
</div>
