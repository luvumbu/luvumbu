<?php
/** @var array $conv @var string $ip @var ?string $pseudo */
$pageTitle = Conversation::displayTitle($conv);
$title     = $pageTitle . ' — ' . $conv['code'];
$bodyClass = 'chat';
$bodyAttrs = 'data-code="' . e($conv['code']) . '"'
           . ' data-conv="' . (int) $conv['id'] . '"'
           . ' data-ip="' . e($ip) . '"'
           . ' data-base="' . e(base_url()) . '"';
?>
<script>
    // Applique immédiatement les couleurs personnelles (localStorage) pour éviter un flash.
    (function () {
        try {
            var t = JSON.parse(localStorage.getItem('df_user_theme') || '{}');
            for (var k in t) { document.documentElement.style.setProperty('--' + k, t[k]); }
        } catch (e) {}
    })();
</script>

<header class="chat-header">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <span class="code-badge">Code : <strong><?= e($conv['code']) ?></strong></span>
        <span class="access-badge"><?= $conv['is_open'] ? '🌐 Libre' : '🔒 Protégée' ?></span>
    </div>
    <div class="header-actions">
        <button id="color-toggle" type="button" class="btn small" title="Personnaliser mes couleurs">🎨</button>
        <a href="<?= e(base_url()) ?>" class="leave">Quitter</a>
    </div>
</header>

<!-- Panneau de couleurs personnelles (visibles uniquement par soi) -->
<div id="color-panel" class="color-panel" hidden>
    <div class="color-panel-head">
        <strong>🎨 Mes couleurs</strong>
        <span class="muted">visibles seulement par toi, sur cet appareil</span>
    </div>
    <div class="color-grid">
        <label>Fond<input type="color" data-var="bg"></label>
        <label>Cartes & bulles<input type="color" data-var="surface"></label>
        <label>Couleur principale<input type="color" data-var="accent"></label>
        <label>Texte<input type="color" data-var="text"></label>
        <label>Mes messages<input type="color" data-var="mine"></label>
    </div>
    <button id="color-reset" type="button" class="btn small">↩️ Réinitialiser</button>
</div>

<main id="messages" class="messages">
    <p class="loading">Chargement…</p>
</main>

<footer class="composer">
    <!-- Choix / changement de pseudo -->
    <div class="pseudo-row">
        <span class="muted">Pseudo :</span>
        <span id="current-pseudo" class="pseudo-name"><?= $pseudo ? e($pseudo) : 'non défini' ?></span>
        <button id="edit-pseudo" type="button" class="link-btn">modifier</button>
    </div>
    <form id="pseudo-form" class="pseudo-form" style="display:none">
        <input type="text" id="pseudo-input" maxlength="40" placeholder="Ton pseudo"
               value="<?= $pseudo ? e($pseudo) : '' ?>" autocomplete="off">
        <button type="submit" class="btn small">OK</button>
    </form>

    <!-- Envoi de message -->
    <form id="send-form" class="send-form">
        <input type="text" id="msg-input" placeholder="Écris ton message…"
               maxlength="2000" autocomplete="off" required>
        <button type="submit" class="btn">Envoyer</button>
    </form>
</footer>

<script src="<?= e(base_url('assets/app.js')) ?>"></script>
