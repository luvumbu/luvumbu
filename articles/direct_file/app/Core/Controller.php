<?php
/**
 * Contrôleur de base : rendu de vue, sortie JSON, redirection.
 */
abstract class Controller
{
    /**
     * Rend une vue (app/Views/<name>.php) avec les variables fournies,
     * enveloppée dans le layout app/Views/layouts/main.php.
     * Mettre $data['layout'] = false pour rendre la vue seule.
     */
    protected function view(string $name, array $data = []): void
    {
        $viewFile = dirname(__DIR__) . '/Views/' . $name . '.php';

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if (($data['layout'] ?? true) === false) {
            echo $content;
            return;
        }
        require dirname(__DIR__) . '/Views/layouts/main.php';
    }

    /** Réponse JSON + arrêt. */
    protected function json($data, int $status = 200): void
    {
        json_out($data, $status);
    }

    /** Redirection vers une route interne + arrêt. */
    protected function redirect(string $path): void
    {
        header('Location: ' . base_url($path));
        exit;
    }
}
