<?php
/**
 * Spectateur : affiche une conversation en LECTURE SEULE, sans connexion admin.
 * Même apparence que la vue admin d'une conversation, à une URL publique.
 *
 *   /view?conv=2      (par identifiant, comme l'admin)
 *   /view?code=AB123  (par code, au choix)
 */
class ViewerController extends Controller
{
    public function show(): void
    {
        $conv = null;
        if (isset($_GET['conv'])) {
            $conv = Conversation::findById((int) $_GET['conv']);
        } elseif (isset($_GET['code'])) {
            $conv = Conversation::findByCode(normalize_code($_GET['code']));
        }

        $this->view('viewer', [
            'title'     => $conv ? Conversation::displayTitle($conv) . ' — lecture' : 'Lecture',
            'bodyClass' => 'admin',
            'conv'      => $conv,
            'messages'  => $conv ? Message::allFor((int) $conv['id']) : [],
        ]);
    }
}
