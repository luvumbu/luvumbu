<?php
/**
 * Connexion PDO partagée à la base de données.
 *
 * Les identifiants sont lus depuis config/database.php. La connexion est
 * instanciée à la première utilisation (singleton).
 */
class Database
{
    private static ?PDO $pdo = null;

    /** Identifiants chargés depuis config/database.php (avec repli par défaut). */
    public static function config(): array
    {
        $cfg = @include dirname(__DIR__, 2) . '/config/database.php';
        if (!is_array($cfg)) {
            $cfg = ['host' => '127.0.0.1', 'name' => 'direct_file', 'user' => 'root', 'pass' => '', 'charset' => 'utf8mb4'];
        }
        return $cfg;
    }

    /** Connexion PDO partagée. */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c   = self::config();
            $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
            try {
                self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                $code = $e->getCode();
                if ($code === 1045) {
                    $hint = "Identifiants MySQL incorrects. Ouvre config/database.php et mets ton mot de passe MySQL dans 'pass'.";
                } elseif ($code === 1049) {
                    $hint = "La base '{$c['name']}' n'existe pas. Ouvre /install pour la créer.";
                } elseif ($code === 2002) {
                    $hint = "Impossible de joindre le serveur MySQL. Démarre MySQL dans le panneau XAMPP.";
                } else {
                    $hint = $e->getMessage();
                }
                throw new RuntimeException($hint, 0, $e);
            }
        }
        return self::$pdo;
    }
}
