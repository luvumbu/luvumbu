<?php
/**
 * Page de discussion (le chat lui-même).
 */
class ChatController extends Controller
{
    /** GET /chat?code=AB123 */
    public function show(): void
    {
        $code = normalize_code($_GET['code'] ?? '');
        $conv = Conversation::findByCode($code);

        // Inexistante, ou protégée sans accès validé → retour accueil.
        if (!$conv) {
            $this->redirect('');
        }
        if (!$conv['is_open'] && empty($_SESSION['access'][$code])) {
            $this->redirect('');
        }

        $ip     = client_ip();
        $pseudo = Participant::pseudoFor((int) $conv['id'], $ip);

        $this->view('chat', [
            'conv'   => $conv,
            'ip'     => $ip,
            'pseudo' => $pseudo,
        ]);
    }
}
