<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\PetRepository;
use App\Repositories\TopicProgressRepository;
use App\Services\LearningService;
use App\Services\ProgressService;

/**
 * Module « Apprendre ».
 * - fournit une question adaptée au niveau
 * - corrige la réponse et récompense la créature (points, connaissance, bonheur)
 * - suit la progression par thème (déblocage à la Duolingo)
 */
class LearningController
{
    private LearningService $learning;
    private PetRepository $pets;
    private TopicProgressRepository $topics;
    private ProgressService $progress;
    private array $cfg;

    public function __construct()
    {
        $this->learning = new LearningService();
        $this->pets     = new PetRepository();
        $this->topics   = new TopicProgressRepository();
        $this->progress = new ProgressService();
        $this->cfg      = (require __DIR__ . '/../../config/config.php')['learning'];
    }

    /** GET /learn/question?topic=colors&pet_id=1 */
    public function question(): void
    {
        $topic = (string) ($_GET['topic'] ?? 'eveil');
        $petId = (int) ($_GET['pet_id'] ?? 0);
        // Progression de la créature → calcul progressif (nombres qui grandissent).
        $progress = $petId > 0 ? $this->topics->forPet($petId) : [];
        Response::json($this->learning->question($topic, $progress));
    }

    /** POST /learn/answer  { pet_id, token, answer, topic } */
    public function answer(): void
    {
        $petId  = (int) Request::input('pet_id', 0);
        $token  = (string) Request::input('token', '');
        $given  = (string) Request::input('answer', '');
        $topic  = (string) Request::input('topic', '');

        $result = $this->learning->check($token, $given);
        if (!$result['valid']) {
            Response::error('Question invalide ou expirée.', 422);
        }

        $pet = $this->pets->find($petId);
        if ($pet === null) {
            Response::error('Créature introuvable.', 404);
        }

        $awarded = 0;
        if ($result['correct']) {
            $awarded = $this->cfg['points_per_correct'];
            $pet['points']    += $awarded;
            $pet['knowledge'] += $this->cfg['knowledge_per_correct'];
            $pet['happiness']  = min(100, $pet['happiness'] + $this->cfg['happiness_per_correct']);
            $pet = $this->pets->save($pet);

            // Progression : compte cette réussite sur le thème concret joué.
            if ($topic !== '') {
                $this->topics->increment($petId, $topic);
            }
        }

        Response::json([
            'correct'        => $result['correct'],
            'correct_answer' => $result['correctAnswer'],
            'points_awarded' => $awarded,
            'pet'            => $pet,
        ]);
    }

    /** GET /learn/progress?pet_id=1 — état de déblocage des thèmes. */
    public function progress(): void
    {
        $petId = (int) ($_GET['pet_id'] ?? 0);
        $data  = $this->progress->compute($this->topics->forPet($petId));
        Response::json($data);
    }

    /** POST /learn/bonus  { pet_id, count, correct } — récompense de fin de quiz. */
    public function bonus(): void
    {
        $petId   = (int) Request::input('pet_id', 0);
        $count   = (int) Request::input('count', 0);
        $correct = (int) Request::input('correct', 0);

        $table = $this->cfg['bonus'];
        if (!isset($table[$count])) {
            Response::error('Palier de quiz invalide.', 422);
        }

        $pet = $this->pets->find($petId);
        if ($pet === null) {
            Response::error('Créature introuvable.', 404);
        }

        // Le nombre de bonnes réponses ne peut pas dépasser le nombre de questions.
        $correct = max(0, min($count, $correct));
        $perfect = ($correct === $count);

        $bonus = $table[$count];
        if ($perfect) {
            $bonus = (int) round($bonus * $this->cfg['perfect_multiplier']);
        }

        $pet['points'] += $bonus;
        $pet = $this->pets->save($pet);

        Response::json([
            'bonus'   => $bonus,
            'perfect' => $perfect,
            'pet'     => $pet,
        ]);
    }
}
