<?php
// Gestion d'authentification basée sur la session.

function current_user() {
    global $pdo;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cached = null;
    if ($cached !== null && $cached['id'] === (int)$_SESSION['user_id']) {
        return $cached;
    }
    $stmt = $pdo->prepare('SELECT id, nom, prenom, email, is_admin FROM users WHERE id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $cached = $stmt->fetch() ?: null;
    return $cached;
}

function is_logged_in() {
    return current_user() !== null;
}

function is_admin() {
    $u = current_user();
    return $u && (int)$u['is_admin'] === 1;
}

function require_login() {
    if (!is_logged_in()) {
        flash_set('error', 'Connecte-toi pour accéder à cette page.');
        redirect(base_url('pages/login.php'));
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        flash_set('error', 'Accès réservé à l\'administrateur.');
        redirect(base_url('index.php'));
    }
}

function register_user($nom, $prenom, $email, $password) {
    global $pdo;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (nom, prenom, email, password_hash) VALUES (?, ?, ?, ?)');
    $stmt->execute([$nom, $prenom, $email, $hash]);
    return (int)$pdo->lastInsertId();
}

function login_user($email, $password) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    $_SESSION['user_id'] = (int)$user['id'];
    return true;
}

function verify_current_password($password) {
    global $pdo;
    $u = current_user();
    if (!$u || $password === '') {
        return false;
    }
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([(int)$u['id']]);
    $row = $stmt->fetch();
    return $row && password_verify($password, $row['password_hash']);
}

function logout_user() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']);
    }
    session_destroy();
}
