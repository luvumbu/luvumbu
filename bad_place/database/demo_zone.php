<?php
// Données de démonstration : concentre des témoignages à Marseille pour illustrer une "zone sous vigilance".
require 'vendor/autoload.php';
use App\Core\Config; use App\Core\Database; use App\Services\OrganizationService; use Dotenv\Dotenv;
Dotenv::createImmutable('config')->safeLoad();
Config::load(require 'config/config.php');

$userId = (int) (Database::selectOne("SELECT id FROM users WHERE email='test@example.com'")['id'] ?? 1);

// Quelques lieux à Marseille (coords proches du centre) avec un nombre de témoignages
$places = [
    ['name'=>'Supermarché Canebière','cat'=>2,'lat'=>43.2965,'lng'=>5.3760,'n'=>5],
    ['name'=>'Boutique Vieux-Port','cat'=>3,'lat'=>43.2951,'lng'=>5.3745,'n'=>4],
    ['name'=>'Restaurant Le Panier','cat'=>11,'lat'=>43.2980,'lng'=>5.3690,'n'=>4],
    ['name'=>'Agence immobilière Prado','cat'=>44,'lat'=>43.2700,'lng'=>5.3900,'n'=>3],
];
$descrs = [
    "Refus d'accès sans explication alors que d'autres personnes entraient librement.",
    "Traitement clairement inégal par rapport aux autres clients présents ce jour-là.",
    "Remarques déplacées et service refusé sans aucune justification valable.",
    "Contrôle abusif et attitude méprisante du personnel envers moi.",
];

$created = 0;
foreach ($places as $p) {
    $orgId = OrganizationService::findOrCreate([
        'name'=>$p['name'],'type'=>'place','category_id'=>$p['cat'],
        'city'=>'Marseille','postal_code'=>'13001','department'=>'Bouches-du-Rhône','region'=>"Provence-Alpes-Côte d'Azur",
        'country'=>'France','latitude'=>$p['lat'],'longitude'=>$p['lng'],
    ]);
    for ($i=0; $i<$p['n']; $i++) {
        $uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $rid = Database::insert(
            "INSERT INTO reports (uuid,user_id,organization_id,category_id,description,incident_date,is_anonymous,reporter_display,status,language,published_at)
             VALUES (?,?,?,?,?,?,?,?, 'published','fr', NOW())",
            [$uuid,$userId,$orgId,$p['cat'],$descrs[$i%4], date('Y-m-d', strtotime('-'.rand(1,60).' days')), 1, null]
        );
        Database::execute('INSERT IGNORE INTO report_motifs (report_id,motif_id) VALUES (?,?)', [$rid, ($i%5)+1]);
        Database::execute('INSERT IGNORE INTO report_discrimination_types (report_id,type_id) VALUES (?,?)', [$rid, ($i%5)+1]);
        $created++;
    }
    OrganizationService::recomputeActivity($orgId);
}
echo "Créé $created témoignages de démo à Marseille.\n";
