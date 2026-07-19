<?php
// === Connexion à la base de données (singleton PDO) ===
// Lit les identifiants depuis config.php (constantes DB_*).

final class Db
{
    private static ?PDO $pdo = null;

    /** Connexion PDO partagée (créée une seule fois par requête HTTP). */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }
        return self::$pdo;
    }
}
