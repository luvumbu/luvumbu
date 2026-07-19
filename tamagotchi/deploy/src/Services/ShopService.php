<?php
namespace App\Services;

/**
 * Logique de la boutique : acheter un aliment le fait manger immédiatement.
 * Contient aussi l'apprentissage implicite de l'ÉQUILIBRE alimentaire :
 * le jeu réagit si l'enfant abuse du sucre ou félicite un repas varié.
 */
class ShopService
{
    private const MAX = 100;

    /**
     * Applique l'achat d'un aliment à la créature.
     *
     * @param array $pet               état de la créature
     * @param array $item              l'aliment acheté
     * @param array $recentCategories  catégories des derniers repas (récent d'abord)
     * @return array { ok, pet, message }
     */
    public function buy(array $pet, array $item, array $recentCategories): array
    {
        // Assez de points ?
        if ($pet['points'] < (int) $item['price']) {
            return [
                'ok'      => false,
                'pet'     => $pet,
                'message' => "😢 Pas assez de points ! Il t'en faut {$item['price']} 💰.",
            ];
        }

        // Paiement
        $pet['points'] -= (int) $item['price'];

        // Effets de l'aliment
        $pet['hunger']    = $this->clamp($pet['hunger']    + (int) $item['d_hunger']);
        $pet['energy']    = $this->clamp($pet['energy']    + (int) $item['d_energy']);
        $pet['health']    = $this->clamp($pet['health']    + (int) $item['d_health']);
        $pet['happiness'] = $this->clamp($pet['happiness'] + (int) $item['d_happy']);

        // --- Équilibre alimentaire (le repas courant compte) ---
        $window = array_merge([$item['category']], $recentCategories);
        $window = array_slice($window, 0, 5);
        $sweets   = count(array_filter($window, fn ($c) => $c === 'sucre'));
        $distinct = count(array_unique($window));

        $message = "🍽️ {$pet['name']} a mangé un(e) {$item['name']} !";

        if ($sweets >= 3) {
            // Trop de sucre : petite pénalité santé + message pédagogique
            $pet['health'] = $this->clamp($pet['health'] - 5);
            $message = "🍰 J'aime le sucré… mais j'ai besoin d'autres aliments pour être fort !";
        } elseif ($distinct >= 3) {
            // Repas varié : bonus bonheur + encouragement
            $pet['happiness'] = $this->clamp($pet['happiness'] + 3);
            $message = "😋 Un repas bien varié, {$pet['name']} est en pleine forme !";
        }

        return ['ok' => true, 'pet' => $pet, 'message' => $message];
    }

    private function clamp(int $v): int
    {
        return max(0, min(self::MAX, $v));
    }
}
