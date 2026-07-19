<?php
/**
 * Accueil : créer ou rejoindre une discussion.
 */
class HomeController extends Controller
{
    /** GET / : affiche le formulaire. */
    public function index(): void
    {
        $this->view('home', ['error' => '']);
    }

    /** POST / : traite la création ou l'accès. */
    public function store(): void
    {
        $action = $_POST['action'] ?? '';
        $error  = '';

        if ($action === 'create') {
            $title    = trim($_POST['title'] ?? '');
            $isOpen   = ($_POST['access'] ?? 'open') === 'open';
            $password = $_POST['password'] ?? '';

            if (!$isOpen && $password === '') {
                $error = 'Choisis un mot de passe pour une discussion protégée.';
            } else {
                $hash = (!$isOpen && $password !== '')
                    ? password_hash($password, PASSWORD_DEFAULT)
                    : null;
                $code = Conversation::create($title, $hash, $isOpen, client_ip());
                $_SESSION['access'][$code] = true; // le créateur est autorisé
                $this->redirect('chat?code=' . $code);
            }
        }

        if ($action === 'join') {
            $code     = normalize_code($_POST['code'] ?? '');
            $password = $_POST['password'] ?? '';
            $conv     = Conversation::findByCode($code);

            if (!$conv) {
                $error = 'Aucune discussion trouvée pour ce code.';
            } elseif (!$conv['is_open'] && !password_verify($password, (string) $conv['password_hash'])) {
                $error = 'Mot de passe incorrect.';
            } else {
                $_SESSION['access'][$code] = true;
                $this->redirect('chat?code=' . $code);
            }
        }

        $this->view('home', ['error' => $error]);
    }
}
