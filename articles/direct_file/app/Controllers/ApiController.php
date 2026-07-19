<?php
/**
 * API JSON : messages et pseudo.
 */
class ApiController extends Controller
{
    /**
     * Récupère la conversation demandée et vérifie l'accès.
     * Termine la requête en JSON si introuvable ou accès refusé.
     */
    private function requireConversation(): array
    {
        $code = normalize_code($_REQUEST['code'] ?? '');
        $conv = Conversation::findByCode($code);

        if (!$conv) {
            $this->json(['ok' => false, 'error' => 'Discussion introuvable.'], 404);
        }
        if (!$conv['is_open'] && empty($_SESSION['access'][$code])) {
            $this->json(['ok' => false, 'error' => 'Accès refusé.'], 403);
        }
        return $conv;
    }

    /** GET /api/messages?code=AB123&after=<id> */
    public function listMessages(): void
    {
        $conv   = $this->requireConversation();
        $ip     = client_ip();
        $after  = (int) ($_GET['after'] ?? 0);

        $out = [];
        foreach (Message::after((int) $conv['id'], $after) as $m) {
            $out[] = [
                'id'      => (int) $m['id'],
                'pseudo'  => $m['pseudo'],
                'content' => $m['content'],
                'time'    => date('H:i', strtotime($m['created_at'])),
                'mine'    => $m['ip'] === $ip,
            ];
        }
        $this->json(['ok' => true, 'messages' => $out]);
    }

    /** POST /api/messages {code, content} */
    public function sendMessage(): void
    {
        $conv    = $this->requireConversation();
        $ip      = client_ip();
        $content = trim($_POST['content'] ?? '');

        if ($content === '') {
            $this->json(['ok' => false, 'error' => 'Message vide.'], 422);
        }

        // Pseudo : celui enregistré, sinon dérivé de l'IP.
        $pseudo = Participant::pseudoFor((int) $conv['id'], $ip) ?? ('Invité-' . substr(md5($ip), 0, 4));
        $id     = Message::create((int) $conv['id'], $ip, $pseudo, $content);

        $this->json(['ok' => true, 'id' => $id]);
    }

    /** POST /api/pseudo {code, pseudo} */
    public function savePseudo(): void
    {
        $conv   = $this->requireConversation();
        $ip     = client_ip();
        $pseudo = trim($_POST['pseudo'] ?? '');

        if ($pseudo === '') {
            $this->json(['ok' => false, 'error' => 'Pseudo vide.'], 422);
        }
        $pseudo = mb_substr($pseudo, 0, 40);

        Participant::save((int) $conv['id'], $ip, $pseudo);
        $this->json(['ok' => true, 'pseudo' => $pseudo]);
    }
}
