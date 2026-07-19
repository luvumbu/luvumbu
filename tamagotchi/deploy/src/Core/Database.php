<?php
namespace App\Core;

use PDO;
use PDOException;

/**
 * Connexion unique à la base (singleton).
 * Usage : Database::pdo()->prepare(...)
 */
class Database
{
    private static ?PDO $instance = null;

    public static function pdo(): PDO
    {
        if (self::$instance === null) {
            $cfg = require __DIR__ . '/../../config/config.php';
            $db  = $cfg['db'];

            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";

            try {
                self::$instance = new PDO($dsn, $db['user'], $db['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);

                // Aligne l'horloge MySQL sur celle de PHP → les CURRENT_TIMESTAMP
                // et les date() PHP concordent (calcul du temps écoulé fiable).
                self::$instance->exec("SET time_zone = '" . date('P') . "'");
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'DB connection failed']);
                exit;
            }
        }
        return self::$instance;
    }
}
