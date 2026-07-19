<?php
/**
 * Configuration de la base (/setup) et installation des tables (/install).
 */
class SetupController extends Controller
{
    private function configFile(): string
    {
        return APP_ROOT . '/config/database.php';
    }

    /** GET /setup : formulaire de configuration. */
    public function index(): void
    {
        $c = Database::config();
        $this->view('setup', [
            'title'   => 'Configuration',
            'bodyClass' => 'home',
            'layout'  => false, // setup gère son propre <head> (pas d'accès BDD garanti)
            'values'  => $c,
            'message' => '',
            'success' => false,
        ]);
    }

    /** POST /setup : teste la connexion, écrit la config, crée les tables. */
    public function store(): void
    {
        $values = [
            'host'    => trim($_POST['host'] ?? '127.0.0.1'),
            'name'    => trim($_POST['name'] ?? 'direct_file'),
            'user'    => trim($_POST['user'] ?? 'root'),
            'pass'    => (string) ($_POST['pass'] ?? ''),
            'charset' => 'utf8mb4',
        ];
        $createDb = isset($_POST['create_db']);
        $message  = '';
        $success  = false;

        try {
            // 1) Connexion au serveur (sans choisir de base pour pouvoir la créer).
            $dsn = 'mysql:host=' . $values['host'] . ';charset=' . $values['charset'];
            $pdo = new PDO($dsn, $values['user'], $values['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // 2) Création de la base si demandé.
            if ($createDb) {
                $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $values['name'] . '`
                            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            }

            // 3) Vérifie que la base est accessible.
            $pdo->exec('USE `' . $values['name'] . '`');

            // 4) Écrit le fichier de configuration.
            $php = $this->renderConfig($values);
            if (@file_put_contents($this->configFile(), $php) === false) {
                throw new RuntimeException(
                    "Connexion réussie, mais impossible d'écrire config/database.php "
                    . "(droits insuffisants). Copie ce contenu dans le fichier à la main :\n\n" . $php
                );
            }

            // 5) Crée les tables (schéma centralisé).
            if ($createDb) {
                create_schema($pdo);
            }

            $success = true;
            $message = 'Connexion réussie et configuration enregistrée ✅';
        } catch (PDOException $e) {
            $code = $e->getCode();
            if ($code === 1045) {
                $message = "Mot de passe ou utilisateur incorrect. Vérifie et réessaie.";
            } elseif ($code === 1049) {
                $message = "La base '" . $values['name'] . "' n'existe pas. Coche « Créer la base » ci-dessous.";
            } elseif ($code === 2002) {
                $message = "Serveur MySQL injoignable. Démarre MySQL dans le panneau XAMPP.";
            } else {
                $message = $e->getMessage();
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
        }

        $this->view('setup', [
            'title'   => 'Configuration',
            'layout'  => false,
            'values'  => $values,
            'message' => $message,
            'success' => $success,
        ]);
    }

    /** GET /install : crée la base et les tables à partir de la config existante. */
    public function install(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $c = Database::config();
        try {
            $dsn  = 'mysql:host=' . $c['host'] . ';charset=' . $c['charset'];
            $root = new PDO($dsn, $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $root->exec('CREATE DATABASE IF NOT EXISTS `' . $c['name'] . '`
                         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $root->exec('USE `' . $c['name'] . '`');
            create_schema($root);

            echo '<h1 style="font-family:sans-serif;color:#16a34a">✅ Installation réussie</h1>';
            echo '<p style="font-family:sans-serif">Base <b>' . e($c['name']) . '</b> et tables créées.</p>';
            echo '<p style="font-family:sans-serif"><a href="' . e(base_url()) . '">→ Aller à l\'application</a></p>';
        } catch (Throwable $ex) {
            echo '<h1 style="font-family:sans-serif;color:#dc2626">❌ Erreur</h1>';
            echo '<pre style="font-family:monospace">' . e($ex->getMessage()) . '</pre>';
        }
    }

    /** Sérialise les identifiants en PHP exécutable. */
    private function renderConfig(array $values): string
    {
        return "<?php\n"
             . "/**\n * Identifiants de la base — généré par /setup.\n */\n"
             . "return [\n"
             . "    'host'    => " . var_export($values['host'], true) . ",\n"
             . "    'name'    => " . var_export($values['name'], true) . ",\n"
             . "    'user'    => " . var_export($values['user'], true) . ",\n"
             . "    'pass'    => " . var_export($values['pass'], true) . ",\n"
             . "    'charset' => " . var_export($values['charset'], true) . ",\n"
             . "];\n";
    }
}
