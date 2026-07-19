<?php /** @var string $error */ $title = 'Direct File — Discussions en direct'; $bodyClass = 'home'; ?>
<main class="card">
    <h1>💬 Direct File</h1>
    <p class="subtitle">Discussions en direct, sans inscription.</p>

    <?php if ($error): ?>
        <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="tabs">
        <button class="tab active" data-tab="join">Rejoindre</button>
        <button class="tab" data-tab="create">Créer</button>
    </div>

    <!-- Rejoindre -->
    <form method="post" action="<?= e(base_url()) ?>" class="panel active" data-panel="join">
        <input type="hidden" name="action" value="join">
        <label>Code de la discussion
            <input type="text" name="code" placeholder="AB123" maxlength="8"
                   autocomplete="off" required style="text-transform:uppercase">
        </label>
        <label>Mot de passe <span class="muted">(si protégée)</span>
            <input type="password" name="password" placeholder="Laisser vide si libre">
        </label>
        <button type="submit" class="btn">Entrer</button>
    </form>

    <!-- Créer -->
    <form method="post" action="<?= e(base_url()) ?>" class="panel" data-panel="create">
        <input type="hidden" name="action" value="create">
        <label>Titre <span class="muted">(optionnel)</span>
            <input type="text" name="title" placeholder="Sujet de la discussion" maxlength="120">
        </label>

        <fieldset class="access">
            <legend>Accès</legend>
            <div class="access-toggle" role="group" aria-label="Type d'accès">
                <button type="button" class="toggle-btn active" data-access="open">🌐 Publique</button>
                <button type="button" class="toggle-btn" data-access="protected">🔒 Privée</button>
            </div>
            <input type="hidden" name="access" id="access-input" value="open">
        </fieldset>

        <label class="pwd-field" style="display:none">Mot de passe
            <input type="password" name="password" placeholder="Mot de passe d'accès">
        </label>

        <button type="submit" class="btn">Créer la discussion</button>
    </form>
</main>

<script>
    // Onglets
    document.querySelectorAll('.tab').forEach(function (t) {
        t.addEventListener('click', function () {
            document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
            document.querySelectorAll('.panel').forEach(x => x.classList.remove('active'));
            t.classList.add('active');
            document.querySelector('[data-panel="' + t.dataset.tab + '"]').classList.add('active');
        });
    });
    // Interrupteur Publique / Privée : met à jour le champ caché + le mot de passe
    document.querySelectorAll('.toggle-btn').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('.toggle-btn').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
            var val = b.dataset.access;
            document.getElementById('access-input').value = val;
            var pwd = document.querySelector('.pwd-field');
            pwd.style.display = (val === 'protected') ? 'block' : 'none';
            if (val !== 'protected') { pwd.querySelector('input').value = ''; }
        });
    });
</script>
