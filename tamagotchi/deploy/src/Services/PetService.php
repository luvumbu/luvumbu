<?php
namespace App\Services;

/**
 * Cœur du gameplay : toutes les RÈGLES du Tamagotchi vivent ici.
 * Aucune requête SQL ni HTTP ici — uniquement de la logique métier.
 * (Les données arrivent via un Repository, injecté plus tard.)
 *
 * NOTE : squelette de démonstration. Les méthodes renvoient l'état recalculé.
 */
class PetService
{
    private array $game;

    public function __construct()
    {
        $cfg = require __DIR__ . '/../../config/config.php';
        $this->game = $cfg['game'];
    }

    /**
     * Applique le temps écoulé depuis la dernière mise à jour.
     * C'est LA mécanique centrale : les jauges évoluent selon les heures passées.
     */
    public function applyTimePassed(array $pet): array
    {
        $last    = strtotime($pet['last_update']);
        $hours   = max(0, (time() - $last) / 3600);

        $pet['hunger']    = $this->clamp($pet['hunger']    + $this->game['hunger_rate']    * $hours);
        $pet['happiness'] = $this->clamp($pet['happiness'] - $this->game['happiness_rate'] * $hours);
        $pet['energy']    = $this->clamp($pet['energy']    - $this->game['energy_rate']     * $hours);

        // Santé : baisse doucement avec le temps, et PLUS VITE si la créature est
        // négligée (affamée ou épuisée). On la remonte en donnant des aliments sains.
        $healthLoss = ($this->game['health_rate'] ?? 3) * $hours;
        if ($pet['hunger'] >= 70) { $healthLoss += 3 * $hours; }   // a trop faim → tombe malade
        if ($pet['energy'] <= 15) { $healthLoss += 2 * $hours; }   // épuisée → tombe malade
        $pet['health']    = $this->clamp($pet['health'] - $healthLoss);

        $pet['age_hours'] = (int)($pet['age_hours'] + $hours);

        $pet = $this->checkEvolution($pet);
        $pet = $this->checkDeath($pet);

        $pet['last_update'] = date('Y-m-d H:i:s');
        return $pet;
    }

    public function feed(array $pet): array
    {
        $pet['hunger'] = $this->clamp($pet['hunger'] + $this->game['feed_hunger']);
        return $pet;
    }

    public function play(array $pet): array
    {
        if ($pet['is_sleeping']) {
            return $pet; // on ne joue pas avec une créature endormie
        }
        $pet['happiness'] = $this->clamp($pet['happiness'] + $this->game['play_happiness']);
        $pet['energy']    = $this->clamp($pet['energy']    + $this->game['play_energy']);
        return $pet;
    }

    public function sleep(array $pet): array
    {
        $pet['energy']      = $this->clamp($pet['energy'] + $this->game['sleep_energy']);
        $pet['is_sleeping'] = 1;
        return $pet;
    }

    private function checkEvolution(array $pet): array
    {
        $stages = ['egg', 'baby', 'child', 'teen', 'adult'];
        $current = array_search($pet['stage'], $stages);
        foreach ($this->game['evolve_stages'] as $i => $threshold) {
            if ($pet['age_hours'] >= $threshold && $current < $i + 1) {
                $pet['stage'] = $stages[$i + 1];
            }
        }
        return $pet;
    }

    private function checkDeath(array $pet): array
    {
        // Mort si la faim est au maximum OU si la santé tombe à zéro.
        if ($pet['hunger'] >= $this->game['max_stat'] || $pet['health'] <= 0) {
            $pet['is_alive'] = 0;
        }
        return $pet;
    }

    private function clamp(float $v): int
    {
        return (int) max(0, min($this->game['max_stat'], $v));
    }
}
