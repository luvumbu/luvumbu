<?php
// === Lecture normalisée des entrées HTTP ===
// Regroupe le décodage du corps JSON et l'extraction d'identifiants,
// jusque-là recopiés dans plusieurs endpoints (check, delete, gallery, admin…).

final class Request
{
    /** Corps de la requête décodé en tableau JSON (lu une seule fois), ou null si absent/invalide. */
    public static function json(): ?array
    {
        static $read = false;
        static $data = null;
        if ($read) return $data;
        $read = true;

        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) return $data;
        $j = json_decode($raw, true);
        return $data = is_array($j) ? $j : null;
    }

    /**
     * Liste d'identifiants entiers positifs, lus depuis le corps JSON ({"<key>":[...]})
     * ou le formulaire (<key>[]=...), avec repli sur un identifiant unique POST['id'].
     * Les doublons sont retirés.
     */
    public static function ids(string $key = 'ids'): array
    {
        $src = null;
        $json = self::json();
        if (is_array($json) && isset($json[$key]) && is_array($json[$key])) {
            $src = $json[$key];
        } elseif (isset($_POST[$key]) && is_array($_POST[$key])) {
            $src = $_POST[$key];
        } elseif (isset($_POST['id'])) {
            $src = [$_POST['id']];
        }

        $ids = [];
        foreach ((array) $src as $v) {
            $i = (int) $v;
            if ($i > 0) $ids[] = $i;
        }
        return array_values(array_unique($ids));
    }
}
