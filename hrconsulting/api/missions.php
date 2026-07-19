<?php
/* ========================================================
   API Missions — création / liste d'annonces à distance
   --------------------------------------------------------
   Authentification : clé secrète (config/bdd.php > API_KEY)
   à fournir dans l'en-tête HTTP :
       X-Api-Key: <clé>              (ou)
       Authorization: Bearer <clé>

   GET  /api/missions.php           -> liste les 50 dernières annonces
   POST /api/missions.php           -> crée une annonce (JSON ou form-encoded)

   Corps JSON attendu pour POST :
   {
     "recruteur_email": "recruteur@exemple.fr",   // ou "recruteur_id": 2
     "titre": "...",            (requis)
     "description": "...",      (requis)
     "ville": "...",            (requis)
     "techno": "...",  "profil": "...",  "etudes": "...",
     "contrat": "CDI|CDD|Freelance|Stage|Alternance",  "salaire": "..."
   }
   ======================================================== */

require_once __DIR__ . '/../config/bdd.php';

header('Content-Type: application/json; charset=utf-8');

function api_out($code, $data) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ===== Authentification par clé API (comparaison à temps constant) ===== */
$provided = '';
if (function_exists('getallheaders')) {
    $h = array_change_key_case(getallheaders(), CASE_LOWER);
    if (!empty($h['x-api-key'])) {
        $provided = $h['x-api-key'];
    } elseif (!empty($h['authorization']) && stripos($h['authorization'], 'Bearer ') === 0) {
        $provided = trim(substr($h['authorization'], 7));
    }
}
if ($provided === '' && !empty($_SERVER['HTTP_X_API_KEY'])) {
    $provided = $_SERVER['HTTP_X_API_KEY'];
}

if (!defined('API_KEY') || API_KEY === '' || !is_string($provided) || $provided === ''
    || !hash_equals(API_KEY, $provided)) {
    api_out(401, ['error' => 'Clé API absente ou invalide.']);
}

$method = $_SERVER['REQUEST_METHOD'];

/* ===== GET : lister les annonces ===== */
if ($method === 'GET') {
    $rows = $bdd->query(
        'SELECT m.mission_id, m.mission_titre_mission, m.mission_ville,
                m.mission_type_contrat, m.mission_date_up, u.user_name AS recruteur
         FROM mission m
         LEFT JOIN users u ON u.user_id = m.mission_id_user
         ORDER BY m.mission_date_up DESC
         LIMIT 50'
    )->fetchAll();
    api_out(200, ['count' => count($rows), 'missions' => $rows]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    api_out(405, ['error' => 'Méthode non autorisée. Utilise GET (lister) ou POST (créer).']);
}

/* ===== POST : créer une annonce ===== */
// Corps JSON ou form-encoded
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') !== false) {
    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) {
        api_out(400, ['error' => 'JSON invalide.']);
    }
} else {
    $in = $_POST;
}

$titre       = trim($in['titre'] ?? '');
$description = trim($in['description'] ?? '');
$ville       = trim($in['ville'] ?? '');
$techno      = trim($in['techno'] ?? '');
$profil      = trim($in['profil'] ?? '');
$etudes      = trim($in['etudes'] ?? '');
$contrat     = trim($in['contrat'] ?? '');
$salaire     = trim($in['salaire'] ?? '');
$rec_email   = trim($in['recruteur_email'] ?? '');
$rec_id      = (int)($in['recruteur_id'] ?? 0);

$erreurs = [];
if ($titre === '')       $erreurs[] = 'titre requis';
if ($description === '') $erreurs[] = 'description requise';
if ($ville === '')       $erreurs[] = 'ville requise';
if ($contrat !== '' && !in_array($contrat, ['CDI','CDD','Freelance','Stage','Alternance'], true)) {
    $erreurs[] = "contrat invalide (CDI, CDD, Freelance, Stage ou Alternance)";
}
if ($rec_email === '' && $rec_id <= 0) {
    $erreurs[] = 'recruteur_email ou recruteur_id requis';
}
if ($erreurs) {
    api_out(422, ['error' => 'Données invalides.', 'details' => $erreurs]);
}

// Résoudre et vérifier le compte propriétaire (doit être un recruteur)
if ($rec_email !== '') {
    $q = $bdd->prepare('SELECT user_id, user_jesuis FROM users WHERE user_email = :e LIMIT 1');
    $q->execute(['e' => $rec_email]);
} else {
    $q = $bdd->prepare('SELECT user_id, user_jesuis FROM users WHERE user_id = :i LIMIT 1');
    $q->execute(['i' => $rec_id]);
}
$rec = $q->fetch();
if (!$rec) {
    api_out(404, ['error' => 'Recruteur introuvable.']);
}
if ($rec['user_jesuis'] !== 'recruteur') {
    api_out(403, ['error' => "Ce compte n'est pas un recruteur ; il ne peut pas publier d'annonce."]);
}

$ins = $bdd->prepare(
    'INSERT INTO mission(mission_id_user, mission_titre_mission, mission_description,
        mission_technologie, mission_profil, mission_niveau_etudes,
        mission_ville, mission_type_contrat, mission_salaire)
     VALUES(:uid, :titre, :description, :techno, :profil, :etudes, :ville, :contrat, :salaire)'
);
$ins->execute([
    'uid'         => (int)$rec['user_id'],
    'titre'       => $titre,
    'description' => $description,
    'techno'      => $techno,
    'profil'      => $profil,
    'etudes'      => $etudes,
    'ville'       => $ville,
    'contrat'     => $contrat,
    'salaire'     => $salaire,
]);
$id = (int)$bdd->lastInsertId();

api_out(201, [
    'ok'         => true,
    'mission_id' => $id,
    'url'        => BASE_URL . 'pages/mission.php?id=' . $id,
    'message'    => 'Annonce créée avec succès.',
]);
