<?php
/**
 * Administration : connexion, tableau de bord, lecture des conversations,
 * réglage des couleurs du thème.
 */
class AdminController extends Controller
{
    /** GET /admin */
    public function index(): void
    {
        if (!$this->isAuthenticated()) {
            $this->renderLogin();
            return;
        }
        if (isset($_GET['conv'])) {
            $this->showConversation((int) $_GET['conv']);
            return;
        }
        $this->dashboard();
    }

    /** L'admin est autorisé uniquement si la session est active. */
    private function isAuthenticated(): bool
    {
        return !empty($_SESSION['is_admin']);
    }

    /** POST /admin : connexion, déconnexion, thème. */
    public function handle(): void
    {
        $isAdmin = $this->isAuthenticated();
        $action  = $_POST['action'] ?? '';

        // Connexion : les identifiants admin SONT ceux de la base de données,
        // tels que dans config/database.php. L'identifiant accepté est soit
        // l'utilisateur MySQL (ex: root), soit le nom de la base (ex: direct_file).
        if ($action === 'login') {
            $cfg   = Database::config();
            $id    = trim((string) ($_POST['dbname'] ?? ''));
            $okId  = $id === (string) ($cfg['user'] ?? '') || $id === (string) ($cfg['name'] ?? '');
            $okPwd = (string) ($_POST['password'] ?? '') === (string) ($cfg['pass'] ?? '');
            if ($okId && $okPwd) {
                $_SESSION['is_admin'] = true;
                $this->redirect('admin');
            }
            $this->renderLogin('Identifiants incorrects. Utilise l\'utilisateur MySQL (ex : root) ou le nom de la base, avec le mot de passe MySQL.');
            return;
        }

        // Déconnexion.
        if ($action === 'logout') {
            unset($_SESSION['is_admin']);
            $this->redirect('admin');
        }

        // Couleurs (réservé admin).
        if ($action === 'save_theme' && $isAdmin) {
            Setting::saveTheme($_POST);
            $this->redirect('admin?saved=1');
        }
        if ($action === 'reset_theme' && $isAdmin) {
            Setting::resetTheme();
            $this->redirect('admin?reset=1');
        }

        // Modifier l'accès d'une conversation (public ⇄ privé).
        if ($action === 'conv_access' && $isAdmin) {
            $convId = (int) ($_POST['conv_id'] ?? 0);
            $makePrivate = ($_POST['access'] ?? '') === 'private';

            if ($makePrivate) {
                $pwd = (string) ($_POST['password'] ?? '');
                if ($pwd === '') {
                    $this->redirect('admin?conv=' . $convId . '&err=pwd');
                }
                Conversation::setAccess($convId, false, password_hash($pwd, PASSWORD_DEFAULT));
            } else {
                Conversation::setAccess($convId, true, null);
            }
            $this->redirect('admin?conv=' . $convId . '&access=1');
        }

        // Supprimer une conversation (et tous ses messages).
        if ($action === 'delete_conv' && $isAdmin) {
            Conversation::delete((int) ($_POST['conv_id'] ?? 0));
            $this->redirect('admin?deleted=1');
        }

        $this->redirect('admin');
    }

    /** Vue de connexion (mot de passe = mot de passe de la base). */
    private function renderLogin(string $error = ''): void
    {
        $this->view('admin/login', [
            'title'     => 'Connexion',
            'bodyClass' => 'home',
            'error'     => $error,
        ]);
    }

    /** Tableau de bord : liste des conversations + couleurs. */
    private function dashboard(): void
    {
        $notice = '';
        if (isset($_GET['saved']))   $notice = 'Couleurs enregistrées ✅';
        if (isset($_GET['reset']))   $notice = 'Couleurs réinitialisées ↩️';
        if (isset($_GET['deleted'])) $notice = 'Conversation supprimée 🗑️';

        $this->view('admin/dashboard', [
            'title'     => 'Administration',
            'bodyClass' => 'admin',
            'convs'     => Conversation::allWithCounts(),
            'theme'     => Setting::theme(),
            'labels'    => Setting::THEME_LABELS,
            'notice'    => $notice,
        ]);
    }

    /** Vue détaillée d'une conversation (contenu intégral). */
    private function showConversation(int $convId): void
    {
        $conv = Conversation::findById($convId);

        $notice = '';
        $error  = '';
        if (isset($_GET['access'])) $notice = 'Accès de la discussion mis à jour ✅';
        if (($_GET['err'] ?? '') === 'pwd') $error = 'Mot de passe requis pour rendre la discussion privée.';

        $this->view('admin/conversation', [
            'title'     => 'Conversation',
            'bodyClass' => 'admin',
            'conv'      => $conv,
            'messages'  => $conv ? Message::allFor($convId) : [],
            'notice'    => $notice,
            'error'     => $error,
        ]);
    }
}
