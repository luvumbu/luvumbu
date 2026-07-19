<?php /** @var ?array $conv @var array $messages */ ?>
<div class="admin-wrap">
    <div class="admin-head">
        <h1>👁️ Lecture en direct</h1>
        <a class="back" href="<?= e(base_url()) ?>">← Accueil</a>
    </div>

    <?php if (!$conv): ?>
        <div class="section">Conversation introuvable.</div>
    <?php else: ?>
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
        <p class="muted">Lecture seule — actualisation automatique toutes les 5 secondes.</p>
    <?php endif; ?>
</div>

<script>
    // Reste sur la dernière ligne et rafraîchit pour suivre la conversation en direct.
    window.scrollTo(0, document.body.scrollHeight);
    setTimeout(function () { location.reload(); }, 5000);
</script>
