<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

// Plateformes connues + icône Font Awesome associée.
$available = [
    'facebook'  => 'fa-facebook-square',
    'twitter'   => 'fa-twitter-square',
    'linkedin'  => 'fa-linkedin-square',
    'instagram' => 'fa-instagram',
    'youtube'   => 'fa-youtube-play',
    'github'    => 'fa-github',
    'tiktok'    => 'fa-music',
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $errors[] = 'Jeton invalide, recharge la page.';
    } elseif (isset($_POST['add'])) {
        $platform = strtolower(trim($_POST['platform'] ?? ''));
        $url      = trim($_POST['url'] ?? '');
        if (!isset($available[$platform])) $errors[] = 'Plateforme inconnue.';
        if (!filter_var($url, FILTER_VALIDATE_URL)) $errors[] = 'URL invalide.';
        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO social_links (platform, url, icon) VALUES (?, ?, ?)
                                    ON DUPLICATE KEY UPDATE url = VALUES(url), icon = VALUES(icon)');
            $stmt->execute([$platform, $url, $available[$platform]]);
            flash_set('success', 'Réseau ajouté.');
            redirect(base_url('pages/social.php'));
        }
    } elseif (isset($_POST['delete'])) {
        $stmt = $pdo->prepare('DELETE FROM social_links WHERE id = ?');
        $stmt->execute([(int)$_POST['delete']]);
        flash_set('success', 'Réseau supprimé.');
        redirect(base_url('pages/social.php'));
    }
}

$current = $pdo->query('SELECT id, platform, url, icon FROM social_links ORDER BY platform')->fetchAll();
$usedPlatforms = array_column($current, 'platform');

$pageTitle = 'Réseaux sociaux';
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-card auth-card-wide">
    <h1>Réseaux sociaux</h1>
    <p class="muted">Ajoute uniquement les réseaux que tu veux afficher dans l'en-tête. Les autres n'apparaissent pas.</p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <h2>Réseaux affichés</h2>
    <?php if (empty($current)): ?>
        <p class="muted">Aucun réseau configuré pour l'instant.</p>
    <?php else: ?>
        <ul class="social-list">
            <?php foreach ($current as $row): ?>
                <li>
                    <i class="fa <?= e($row['icon']) ?>"></i>
                    <strong><?= e(ucfirst($row['platform'])) ?></strong>
                    <a href="<?= e($row['url']) ?>" target="_blank" rel="noopener"><?= e($row['url']) ?></a>
                    <form method="post" class="inline-form">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <button type="submit" name="delete" value="<?= (int)$row['id'] ?>" class="btn-danger">Supprimer</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>Ajouter un réseau</h2>
    <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>Plateforme
            <select name="platform" required>
                <?php foreach ($available as $key => $icon): ?>
                    <option value="<?= e($key) ?>" <?= in_array($key, $usedPlatforms, true) ? 'disabled' : '' ?>>
                        <?= e(ucfirst($key)) ?><?= in_array($key, $usedPlatforms, true) ? ' (déjà ajouté)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>URL
            <input type="url" name="url" required placeholder="https://...">
        </label>
        <button type="submit" name="add" value="1" class="btn-primary">Ajouter</button>
    </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
