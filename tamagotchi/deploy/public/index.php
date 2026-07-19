<?php
/**
 * Point d'entrée du jeu.
 * Avant d'afficher le Tamagotchi, on vérifie que la base de données est configurée.
 *   - Base OK        → on sert le jeu (index.html).
 *   - Pas configurée → on redirige vers l'assistant d'installation (install.php).
 */

$configured = false;
$cfg = @include __DIR__ . '/../config/config.php';

if (is_array($cfg) && !empty($cfg['db']['name'])) {
    try {
        $db = $cfg['db'];
        new PDO(
            "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
            $db['user'],
            $db['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 4]
        );
        $configured = true;
    } catch (Throwable $e) {
        $configured = false;
    }
}

// Pas encore installé → on envoie vers la configuration.
if (!$configured && is_file(__DIR__ . '/install.php')) {
    header('Location: install.php');
    exit;
}

// Tout est prêt → on affiche le jeu.
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/index.html');
