<?php
// POST api/quiz.php — crée un questionnaire (QCM) et, en option, le rattache à un article.
// Protégé par clé API (mêmes clés que api/article.php : table api_tokens).
//
// Corps JSON attendu :
//   api_key*      (string)  — clé API
//   title*        (string)  — titre du questionnaire
//   description   (string)  — présentation (facultatif)
//   active        (0|1)     — 1 = publié (défaut)
//   author_name   (string)  — auteur affiché (défaut « Quiz »)
//   article_id    (int)     — rattache le quiz à cet article (facultatif)
//   questions*    (array)   — [{ body, explanation?, type?:'single'|'multiple',
//                               options:[{ label, correct:bool }, …] }, …]
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Méthode non autorisée', 405);
}

$user = api_require_user();
$body = read_json_body();

$title     = trim((string)($body['title'] ?? ''));
$questions = $body['questions'] ?? [];
if ($title === '' || mb_strlen($title) > 200 || !is_array($questions) || count($questions) === 0) {
    json_error('Champs requis : title (≤200) et questions[].', 422);
}

$description = trim((string)($body['description'] ?? ''));
$active      = !empty($body['active'] ?? 1) ? 1 : 0;
$author      = trim((string)($body['author_name'] ?? '')) ?: 'Quiz';
$articleId   = (int)($body['article_id'] ?? 0);

// ANTI-DOUBLON : un quiz du même titre créé via l'API → on le met à jour
// (on remplace ses questions) au lieu d'en créer un second.
$find = $pdo->prepare('SELECT id FROM quizzes WHERE title = ? ORDER BY id ASC LIMIT 1');
$find->execute([$title]);
$existingId = (int)($find->fetchColumn() ?: 0);
$createdNew = ($existingId === 0);

if ($createdNew) {
    $ins = $pdo->prepare(
        'INSERT INTO quizzes (title, description, active, author_id, author_name)
         VALUES (?, ?, ?, ?, ?)'
    );
    $ins->execute([$title, $description, $active, (int)$user['id'], $author]);
    $quizId = (int)$pdo->lastInsertId();
} else {
    $quizId = $existingId;
    $upd = $pdo->prepare('UPDATE quizzes SET description = ?, active = ?, updated_at = NOW() WHERE id = ?');
    $upd->execute([$description, $active, $quizId]);
    // On remplace l'ancien jeu de questions (les options tombent en cascade).
    $del = $pdo->prepare('DELETE FROM quiz_questions WHERE quiz_id = ?');
    $del->execute([$quizId]);
}

// (Re)crée questions + options. On ignore les questions invalides (< 2 options
// ou aucune bonne réponse). Plafond anti-abus : 200 questions, 20 options.
$qStmt = $pdo->prepare('INSERT INTO quiz_questions (quiz_id, body, explanation, type, position) VALUES (?, ?, ?, ?, ?)');
$oStmt = $pdo->prepare('INSERT INTO quiz_options (question_id, label, is_correct, position) VALUES (?, ?, ?, ?)');

$qpos = 0; $nQ = 0; $nO = 0;
foreach (array_slice($questions, 0, 200) as $q) {
    if (!is_array($q)) continue;
    $qbody = trim(strip_tags((string)($q['body'] ?? '')));
    if ($qbody === '') continue;
    $type  = (($q['type'] ?? 'single') === 'multiple') ? 'multiple' : 'single';
    $expl  = trim(strip_tags((string)($q['explanation'] ?? ''))) ?: null;

    $opts = is_array($q['options'] ?? null) ? array_slice($q['options'], 0, 20) : [];
    $clean = []; $hasCorrect = false;
    foreach ($opts as $o) {
        if (!is_array($o)) continue;
        $label = trim(strip_tags((string)($o['label'] ?? '')));
        if ($label === '') continue;
        $correct = !empty($o['correct']) ? 1 : 0;
        if ($correct) $hasCorrect = true;
        $clean[] = [$label, $correct];
    }
    if (count($clean) < 2 || !$hasCorrect) continue;

    $qStmt->execute([$quizId, mb_substr($qbody, 0, 500), $expl ? mb_substr($expl, 0, 500) : null, $type, $qpos++]);
    $qid = (int)$pdo->lastInsertId();
    $nQ++;
    $opos = 0;
    foreach ($clean as [$label, $correct]) {
        $oStmt->execute([$qid, mb_substr($label, 0, 300), $correct, $opos++]);
        $nO++;
    }
}

if ($nQ === 0) {
    if ($createdNew) {
        $pdo->prepare('DELETE FROM quizzes WHERE id = ?')->execute([$quizId]);
    }
    json_error('Aucune question valide (≥ 2 options et 1 bonne réponse cochée).', 422);
}

// Rattachement à un article existant (idempotent grâce à la clé unique).
$linked = null;
if ($articleId > 0) {
    $chk = $pdo->prepare('SELECT id FROM articles WHERE id = ?');
    $chk->execute([$articleId]);
    if ($chk->fetchColumn()) {
        $link = $pdo->prepare('INSERT IGNORE INTO article_quizzes (article_id, quiz_id, position) VALUES (?, ?, 0)');
        $link->execute([$articleId, $quizId]);
        $linked = $articleId;
    }
}

json_response([
    'ok'             => true,
    'quiz_id'        => $quizId,
    'created'        => $createdNew,
    'questions'      => $nQ,
    'options'        => $nO,
    'linked_article' => $linked,
    'active'         => $active,
    'url'            => base_url('pages/quiz.php?id=' . $quizId),
], $createdNew ? 201 : 200);
