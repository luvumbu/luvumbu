<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$fields = [
    'site_name'       => ['label' => 'Nom du site',                          'type' => 'text',     'max' => 100],
    'tagline'         => ['label' => 'Slogan (sous le nom)',                  'type' => 'text',     'max' => 100],
    'header_baseline' => ['label' => 'Phrase d\'accueil (barre du haut)',     'type' => 'text',     'max' => 200],
    'about_text'      => ['label' => 'Texte "À propos" (page d\'accueil)',    'type' => 'textarea', 'max' => 500],
    'quiz_mode'       => ['label' => 'Questionnaires : affichage',
                          'type'  => 'select', 'max' => 20, 'choices' => quiz_modes()],
    'quiz_effect'     => ['label' => 'Questionnaires : effet entre les questions',
                          'type'  => 'select', 'max' => 20, 'choices' => quiz_effects()],
    'quiz_reveal'     => ['label' => 'Questionnaires : annonce du résultat',
                          'type'  => 'select', 'max' => 20, 'choices' => quiz_reveals(),
                          'hint'  => 'Ces trois réglages s\'appliquent à tous les questionnaires du site. Seul l\'administrateur peut les modifier ; les visiteurs les subissent tels quels. Dans tous les cas, il faut être connecté pour voir son résultat.'],
];

$errors = [];
$current = get_all_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $errors[] = 'Jeton invalide, recharge la page.';
    }
    foreach ($fields as $key => $def) {
        $val = trim($_POST[$key] ?? '');
        if ($key === 'site_name' && $val === '') {
            $errors[] = 'Le nom du site est obligatoire.';
        }
        if (mb_strlen($val) > $def['max']) {
            $errors[] = $def['label'] . ' : trop long (max ' . $def['max'] . ').';
        }
        if ($def['type'] === 'select' && !isset($def['choices'][$val])) {
            $errors[] = $def['label'] . ' : choix invalide.';
            continue;
        }
        $current[$key] = $val;
    }

    if (empty($errors)) {
        foreach ($fields as $key => $_) {
            set_setting($key, $current[$key]);
        }
        flash_set('success', 'Paramètres enregistrés.');
        redirect(base_url('pages/settings.php'));
    }
}

$pageTitle = 'Paramètres du site';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-card auth-card-wide">
    <h1>Paramètres du site</h1>
    <p class="muted">Personnalise le nom, le slogan et les textes du site. Tout est appliqué immédiatement après enregistrement.</p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <?php foreach ($fields as $key => $def): ?>
            <label>
                <?= e($def['label']) ?>
                <?php if ($def['type'] === 'textarea'): ?>
                    <textarea name="<?= e($key) ?>" rows="3" maxlength="<?= (int)$def['max'] ?>"><?= e($current[$key]) ?></textarea>
                <?php elseif ($def['type'] === 'select'): ?>
                    <select name="<?= e($key) ?>">
                        <?php foreach ($def['choices'] as $val => $lab): ?>
                            <option value="<?= e($val) ?>" <?= $current[$key] === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="text" name="<?= e($key) ?>" value="<?= e($current[$key]) ?>" maxlength="<?= (int)$def['max'] ?>">
                <?php endif; ?>
            </label>
            <?php if (!empty($def['hint'])): ?>
                <p class="muted" style="margin:-6px 0 14px; font-size:13px;"><?= e($def['hint']) ?></p>
            <?php endif; ?>
        <?php endforeach; ?>
        <button type="submit" class="btn-primary">Enregistrer</button>
    </form>

    <h2 style="margin-top:32px;">🔐 Clés API</h2>
    <p class="muted">
        Publier ou modifier des articles à distance, sans passer par ce site.
        Tes clés existantes restent consultables : la page te les réaffiche en entier
        après confirmation de ton mot de passe.
    </p>
    <p>
        <a class="btn-primary" href="<?= e(base_url('pages/api_tokens.php')) ?>"
           style="display:inline-block; text-decoration:none;">Gérer mes clés API</a>
    </p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
