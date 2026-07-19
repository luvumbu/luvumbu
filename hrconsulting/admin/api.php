<?php
require_once __DIR__ . '/../config/bdd.php';

if (!est_admin()) {
    flash_set('error', 'Accès réservé aux administrateurs.');
    header('Location: ' . BASE_URL . 'pages/connexion.php');
    exit;
}

$cfg_file = dirname(__DIR__) . '/config/db.php';

// Régénération OU modification manuelle de la clé API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['regenerer'])) {
        if (api_key_save(bin2hex(random_bytes(24)))) {
            flash_set('success', 'Nouvelle clé API générée.');
        } else {
            flash_set('error', "Impossible d'écrire config/db.php (droits d'écriture insuffisants).");
        }
        header('Location: ' . BASE_URL . 'admin/api.php');
        exit;
    }
    if (isset($_POST['modifier_cle'])) {
        $nouvelle = trim($_POST['api_key'] ?? '');
        if (strlen($nouvelle) < 16) {
            flash_set('error', 'La clé doit contenir au moins 16 caractères.');
        } elseif (api_key_save($nouvelle)) {
            flash_set('success', 'Clé API enregistrée.');
        } else {
            flash_set('error', "Impossible d'écrire config/db.php (droits d'écriture insuffisants).");
        }
        header('Location: ' . BASE_URL . 'admin/api.php');
        exit;
    }
}

$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$url_abs  = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL . 'api/missions.php';
$persistee = is_file($cfg_file) && !empty(($__cfg['api_key'] ?? ''));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Clé API - Administration</title>
    <?php include INCLUDES . '/link.php'; ?>
</head>
<body>
<header>
    <?php include INCLUDES . '/header.php'; ?>
</header>
<main class="page-main">
    <div class="dashboard">
        <h2>Clé API</h2>
        <p class="user-role">Sert à créer/lister des annonces à distance (endpoint <code>api/missions.php</code>).</p>

        <section class="dash-section">
            <h3>Clé secrète</h3>
            <pre style="background:#0f1720;color:#7ee0a8;padding:14px;border-radius:8px;overflow:auto"><?= htmlspecialchars(API_KEY) ?></pre>
            <p style="font-size:13px;color:#6b7684">
                <?= $persistee
                    ? 'Enregistrée dans config/db.php (propre à ce serveur).'
                    : 'Valeur par défaut (non encore personnalisée). Clique sur « Régénérer » pour en créer une unique.' ?>
            </p>
            <form method="POST" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px">
                <?= csrf_field() ?>
                <input type="text" name="api_key" value="<?= htmlspecialchars(API_KEY) ?>"
                       style="flex:1;min-width:280px;padding:10px 12px;border:1px solid #d5dbe3;border-radius:7px;font-family:monospace;font-size:13px">
                <button type="submit" name="modifier_cle" class="btn btn-primary btn-sm">Enregistrer cette clé</button>
            </form>
            <form method="POST" onsubmit="return confirm('Régénérer une clé au hasard ? L\'ancienne cessera de fonctionner.');">
                <?= csrf_field() ?>
                <button type="submit" name="regenerer" class="btn btn-danger btn-sm">Régénérer (aléatoire)</button>
            </form>
        </section>

        <section class="dash-section">
            <h3>URL de l'API</h3>
            <pre style="background:#0f1720;color:#d6e2f0;padding:14px;border-radius:8px;overflow:auto"><?= htmlspecialchars($url_abs) ?></pre>
        </section>

        <section class="dash-section">
            <h3>Exemple : créer une annonce</h3>
            <pre style="background:#0f1720;color:#d6e2f0;padding:14px;border-radius:8px;overflow:auto;font-size:12.5px">curl -X POST "<?= htmlspecialchars($url_abs) ?>" \
  -H "X-Api-Key: <?= htmlspecialchars(API_KEY) ?>" \
  -H "Content-Type: application/json" \
  -d '{
    "recruteur_email": "recruteur@exemple.fr",
    "titre": "Titre du poste",
    "description": "Description de la mission (20 caractères min).",
    "ville": "Lille",
    "contrat": "CDI"
  }'</pre>
            <p style="font-size:13px;color:#6b7684">
                <code>GET</code> sur la même URL (avec l'en-tête <code>X-Api-Key</code>) liste les annonces.
            </p>
        </section>
    </div>
</main>
<footer>
    <?php include INCLUDES . '/footer.php'; ?>
</footer>
</body>
</html>
