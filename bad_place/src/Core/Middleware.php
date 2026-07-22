<?php

namespace App\Core;

/**
 * Contrat des middlewares : reçoivent la requête et le maillon suivant,
 * retournent une Response. Un middleware peut court-circuiter en retournant
 * directement une Response sans appeler $next.
 */
interface Middleware
{
    public function handle(Request $request, callable $next): Response;
}
