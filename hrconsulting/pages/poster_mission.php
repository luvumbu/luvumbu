<?php
require_once __DIR__ . '/../config/bdd.php';

if (!est_recruteur()) {
    flash_set('error', 'Vous devez être recruteur pour publier une annonce.');
    header('Location: ' . BASE_URL . 'pages/connexion.php');
    exit;
}

$erreurs = [];
$old = [
    'titre' => '', 'description' => '', 'techno' => '', 'profil' => '',
    'etudes' => '', 'ville' => '', 'contrat' => '', 'salaire' => ''
];
$edit_id = (int)($_GET['edit'] ?? 0);

if ($edit_id > 0) {
    $r = $bdd->prepare('SELECT * FROM mission WHERE mission_id = :id AND mission_id_user = :uid');
    $r->execute(['id' => $edit_id, 'uid' => $_SESSION['user_id']]);
    $m = $r->fetch();
    if (!$m) {
        flash_set('error', 'Annonce introuvable.');
        header('Location: ' . BASE_URL . 'pages/dashboard.php');
        exit;
    }
    $old = [
        'titre'       => $m['mission_titre_mission'],
        'description' => $m['mission_description'],
        'techno'      => $m['mission_technologie'],
        'profil'      => $m['mission_profil'],
        'etudes'      => $m['mission_niveau_etudes'],
        'ville'       => $m['mission_ville'],
        'contrat'     => $m['mission_type_contrat'],
        'salaire'     => $m['mission_salaire'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['titre']       = trim($_POST['titre'] ?? '');
    $old['description'] = trim($_POST['description'] ?? '');
    $old['techno']      = trim($_POST['techno'] ?? '');
    $old['profil']      = trim($_POST['profil'] ?? '');
    $old['etudes']      = trim($_POST['etudes'] ?? '');
    $old['ville']       = trim($_POST['ville'] ?? '');
    $old['contrat']     = trim($_POST['contrat'] ?? '');
    $old['salaire']     = trim($_POST['salaire'] ?? '');

    if ($old['titre'] === '')       $erreurs[] = "Le titre est obligatoire.";
    if ($old['description'] === '') $erreurs[] = "La description est obligatoire.";
    if ($old['ville'] === '')       $erreurs[] = "La ville est obligatoire.";

    if (empty($erreurs)) {
        if ($edit_id > 0) {
            $req = $bdd->prepare(
                'UPDATE mission SET
                    mission_titre_mission = :titre,
                    mission_description = :description,
                    mission_technologie = :techno,
                    mission_profil = :profil,
                    mission_niveau_etudes = :etudes,
                    mission_ville = :ville,
                    mission_type_contrat = :contrat,
                    mission_salaire = :salaire
                 WHERE mission_id = :id AND mission_id_user = :uid'
            );
            $req->execute([
                'titre'       => $old['titre'],
                'description' => $old['description'],
                'techno'      => $old['techno'],
                'profil'      => $old['profil'],
                'etudes'      => $old['etudes'],
                'ville'       => $old['ville'],
                'contrat'     => $old['contrat'],
                'salaire'     => $old['salaire'],
                'id'          => $edit_id,
                'uid'         => $_SESSION['user_id'],
            ]);
            flash_set('success', 'Annonce modifiée avec succès !');
            header('Location: ' . BASE_URL . 'pages/mission.php?id=' . $edit_id);
            exit;
        } else {
            $req = $bdd->prepare(
                'INSERT INTO mission(mission_id_user, mission_titre_mission, mission_description,
                    mission_technologie, mission_profil, mission_niveau_etudes,
                    mission_ville, mission_type_contrat, mission_salaire)
                 VALUES(:uid, :titre, :description, :techno, :profil, :etudes, :ville, :contrat, :salaire)'
            );
            $req->execute([
                'uid'         => $_SESSION['user_id'],
                'titre'       => $old['titre'],
                'description' => $old['description'],
                'techno'      => $old['techno'],
                'profil'      => $old['profil'],
                'etudes'      => $old['etudes'],
                'ville'       => $old['ville'],
                'contrat'     => $old['contrat'],
                'salaire'     => $old['salaire'],
            ]);
            $nouvelle_id = (int)$bdd->lastInsertId();
            flash_set('success', 'Votre annonce a été publiée !');
            header('Location: ' . BASE_URL . 'pages/mission.php?id=' . $nouvelle_id);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title><?= $edit_id ? 'Modifier' : 'Publier' ?> une annonce - HR Consulting</title>
    <?php include INCLUDES . '/link.php'; ?>
</head>
<body>
<header>
    <?php include INCLUDES . '/header.php'; ?>
</header>
<main class="page-main">
    <div class="form-page">
        <h2><?= $edit_id ? 'Modifier l\'annonce' : 'Publier une nouvelle annonce' ?></h2>

        <?php if (!empty($erreurs)): ?>
            <div class="alert alert-error">
                <?php foreach ($erreurs as $e): ?>
                    <div><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="big-form">
            <?= csrf_field() ?>
            <label>Titre du poste *
                <input type="text" name="titre" value="<?= htmlspecialchars($old['titre']) ?>" required>
            </label>
            <label>Description *
                <textarea name="description" rows="8" required><?= htmlspecialchars($old['description']) ?></textarea>
            </label>
            <label>Technologies (séparées par des virgules)
                <input type="text" name="techno" placeholder="Java, Spring, MySQL" value="<?= htmlspecialchars($old['techno']) ?>">
            </label>
            <label>Profil recherché
                <input type="text" name="profil" placeholder="Développeur backend, Chef de projet..." value="<?= htmlspecialchars($old['profil']) ?>">
            </label>
            <label>Niveau d'études
                <input type="text" name="etudes" placeholder="Bac+3, Bac+5..." value="<?= htmlspecialchars($old['etudes']) ?>">
            </label>
            <div class="row-2">
                <label>Ville *
                    <input type="text" name="ville" value="<?= htmlspecialchars($old['ville']) ?>" required>
                </label>
                <label>Type de contrat
                    <select name="contrat">
                        <option value="">-- Choisir --</option>
                        <?php foreach (['CDI','CDD','Freelance','Stage','Alternance'] as $c): ?>
                            <option value="<?= $c ?>" <?= $old['contrat']===$c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Salaire (optionnel)
                <input type="text" name="salaire" placeholder="40-50K€, 500€/jour..." value="<?= htmlspecialchars($old['salaire']) ?>">
            </label>
            <button type="submit" class="btn btn-success"><?= $edit_id ? 'Mettre à jour' : 'Publier' ?></button>
        </form>
    </div>
</main>
<footer>
    <?php include INCLUDES . '/footer.php'; ?>
</footer>
</body>
</html>
