<?php
// Connexion PDO partagée. Requiert config/config.php déjà chargé.

if (!isset($pdo)) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // Erreurs liées aux identifiants / nom de base → on supprime le config et on relance l'install.
        // Autres erreurs (serveur down, etc.) → on affiche juste l'erreur, sans rien supprimer.
        $invalidCredentials = (
            stripos($msg, 'Access denied') !== false ||
            stripos($msg, 'Unknown database') !== false ||
            strpos($msg, '[1045]') !== false ||
            strpos($msg, '[1049]') !== false
        );

        if ($invalidCredentials) {
            $configFile = __DIR__ . '/../config/config.php';
            if (file_exists($configFile)) {
                @unlink($configFile);
            }
            if (function_exists('flash_set')) {
                flash_set('error', 'Identifiants de base de données invalides — relance l\'installation.');
            }
            if (!headers_sent() && function_exists('base_url')) {
                header('Location: ' . base_url('install.php'));
                exit;
            }
            die('Identifiants de base de données invalides. <a href="install.php">Relance l\'installation</a>.');
        }

        die('Erreur de connexion à la base : ' . htmlspecialchars($msg));
    }
}
