<?php
/* =========================================================
   Connexion ADMIN par identifiants MySQL (même logique que le
   projet "anniversaire") : pas de compte email. On saisit la base,
   l'utilisateur et le mot de passe MySQL ; si la connexion réussit,
   l'accès admin est accordé (et la base créée si elle n'existe pas).
   ========================================================= */
require_once __DIR__ . '/../config/bdd.php';

$erreurs = [];
$vals = [
    'name' => $_POST['adb_name'] ?? 'hrconsulting',
    'user' => $_POST['adb_user'] ?? 'root',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submitadmin'])) {
    csrf_check();
    $h = 'localhost';
    $n = trim($_POST['adb_name'] ?? '');
    $u = trim($_POST['adb_user'] ?? '');
    $p = (string)($_POST['adb_pass'] ?? '');
    if ($n === '') { $n = 'hrconsulting'; }

    try {
        // 1) On valide les identifiants au niveau du SERVEUR MySQL
        $srv = new PDO("mysql:host=$h;charset=utf8", $u, $p, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // 2) On crée la base si elle n'existe pas encore
        $safe = str_replace('`', '', $n);
        $srv->exec("CREATE DATABASE IF NOT EXISTS `$safe` CHARACTER SET utf8 COLLATE utf8_general_ci");

        // 3) Accès admin accordé : session "admin" synthétique (sans compte email).
        //    user_id = 0 → ne correspond à aucun utilisateur réel (id >= 1), donc
        //    l'admin peut gérer tous les comptes sans se bloquer lui-même.
        session_regenerate_id(true);
        $_SESSION['user_id']       = 0;
        $_SESSION['user_name']     = 'Administrateur';
        $_SESSION['user_email']    = '';
        $_SESSION['user_jesuis']   = 'recruteur';
        $_SESSION['user_is_admin'] = 1;

        header('Location: ' . BASE_URL . 'admin/admin.php');
        exit;
    } catch (Exception $e) {
        $erreurs[] = "Connexion refusée. Vérifie l'utilisateur et le mot de passe MySQL. (MySQL doit être démarré dans XAMPP.)";
    }
}
?>
<div class="auth-container">
    <form method="POST" class="auth-form">
        <?= csrf_field() ?>
        <h2>Connexion administrateur</h2>
        <p style="font-size:13px;color:#6b7684;margin-top:-4px">
            Connexion avec les identifiants <strong>MySQL</strong> (XAMPP par défaut :
            base <strong>hrconsulting</strong>, utilisateur <strong>root</strong>, mot de passe <strong>vide</strong>).
        </p>

        <?php if (!empty($erreurs)): ?>
            <div class="alert alert-error">
                <?php foreach ($erreurs as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <input type="text" name="adb_name" value="<?= htmlspecialchars($vals['name']) ?>" placeholder="Nom de la base (dbname)" required>
        <input type="text" name="adb_user" value="<?= htmlspecialchars($vals['user']) ?>" placeholder="Utilisateur (root)" required>
        <input type="password" name="adb_pass" id="admin-pwd" placeholder="Mot de passe (vide par défaut)">
        <label style="font-size:12px;font-weight:normal;color:#6b7684;display:flex;align-items:center;gap:6px;margin-top:-4px">
            <input type="checkbox" onclick="var p=document.getElementById('admin-pwd');p.type=this.checked?'text':'password';">
            Afficher le mot de passe
        </label>

        <button type="submit" name="submitadmin" class="btn btn-primary">Se connecter</button>
        <p class="form-foot">Connexion classique par email ? <a href="<?= BASE_URL ?>pages/connexion.php">Connexion utilisateur</a></p>
    </form>
</div>
