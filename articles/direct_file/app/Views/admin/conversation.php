<?php /** @var ?array $conv @var array $messages @var string $notice @var string $error */ ?>
<div class="admin-wrap">
    <div class="admin-head">
        <h1>💬 Conversation</h1>
        <a class="back" href="<?= e(base_url('admin')) ?>">← Toutes les conversations</a>
    </div>

    <?php if ($notice): ?><div class="section" style="padding:14px 24px"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="section alert" style="margin-bottom:24px"><?= e($error) ?></div><?php endif; ?>

    <?php if (!$conv): ?>
        <div class="section">Conversation introuvable.</div>
    <?php else: ?>
        <!-- Gestion de l'accès -->
        <div class="section">
            <h2>🔑 Accès</h2>
            <p class="muted">
                État actuel : <?= $conv['is_open']
                    ? '🌐 Libre (tout le monde peut entrer)'
                    : '🔒 Protégée par mot de passe' ?>
            </p>
            <?php if ($conv['is_open']): ?>
                <form method="post" action="<?= e(base_url('admin')) ?>" class="row-actions" style="flex-wrap:wrap">
                    <input type="hidden" name="action" value="conv_access">
                    <input type="hidden" name="conv_id" value="<?= (int) $conv['id'] ?>">
                    <input type="hidden" name="access" value="private">
                    <input type="password" name="password" placeholder="Nouveau mot de passe"
                           required style="width:auto;margin:0">
                    <button class="btn" type="submit">🔒 Rendre privée</button>
                </form>
            <?php else: ?>
                <div class="row-actions" style="flex-wrap:wrap">
                    <form method="post" action="<?= e(base_url('admin')) ?>" style="margin:0;display:flex;gap:10px">
                        <input type="hidden" name="action" value="conv_access">
                        <input type="hidden" name="conv_id" value="<?= (int) $conv['id'] ?>">
                        <input type="hidden" name="access" value="private">
                        <input type="password" name="password" placeholder="Changer le mot de passe"
                               required style="width:auto;margin:0">
                        <button class="btn small" type="submit">Changer le mot de passe</button>
                    </form>
                    <form method="post" action="<?= e(base_url('admin')) ?>" style="margin:0">
                        <input type="hidden" name="action" value="conv_access">
                        <input type="hidden" name="conv_id" value="<?= (int) $conv['id'] ?>">
                        <input type="hidden" name="access" value="public">
                        <button class="btn small" type="submit" style="background:var(--surface-2)">🌐 Rendre publique</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>
                <?= e(Conversation::displayTitle($conv)) ?>
                <span class="pill"><?= e($conv['code']) ?></span>
                <span class="pill"><?= $conv['is_open'] ? '🌐 Libre' : '🔒 Protégée' ?></span>
            </h2>

            <?php if (!$messages): ?>
                <p class="muted">Aucun message.</p>
            <?php else: ?>
                <div class="msg-list">
                    <?php foreach ($messages as $m): ?>
                        <div class="msg-item">
                            <div class="meta">
                                <strong><?= e($m['pseudo']) ?></strong> ·
                                <?= e(date('d/m/Y H:i', strtotime($m['created_at']))) ?>
                            </div>
                            <div class="text" style="white-space:pre-wrap"><?= e($m['content']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <p>
            <a class="back" href="<?= e(base_url('chat?code=' . $conv['code'])) ?>" target="_blank">
                Ouvrir dans le chat ↗
            </a>
        </p>

        <!-- Zone de suppression -->
        <div class="section" style="border:1px solid #7f1d1d">
            <h2 style="color:#f87171">🗑️ Supprimer</h2>
            <p class="muted">Cette action efface la discussion et <strong>tous ses messages</strong>. Irréversible.</p>
            <form method="post" action="<?= e(base_url('admin')) ?>" style="margin:0"
                  onsubmit="return confirm('Supprimer définitivement la discussion <?= e($conv['code']) ?> et tous ses messages ?');">
                <input type="hidden" name="action" value="delete_conv">
                <input type="hidden" name="conv_id" value="<?= (int) $conv['id'] ?>">
                <button type="submit" class="btn" style="background:#dc2626">Supprimer cette conversation</button>
            </form>
        </div>
    <?php endif; ?>
</div>
