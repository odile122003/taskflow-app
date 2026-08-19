<?php

namespace App\Http\Middleware;

use App\Support\CurrentTeam;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Peuple le contexte CurrentTeam avec l'équipe de l'utilisateur connecté, pour
 * toute la durée de la requête. Un utilisateur peut appartenir à plusieurs
 * équipes (table pivot team_user) : en attendant un vrai sélecteur d'équipe
 * dans l'interface, on retient la première. C'est cette valeur que lit le
 * scope global TeamScope pour filtrer projets et tâches par équipe.
 */
class SetCurrentTeam
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            app(CurrentTeam::class)->set($user->teams()->first());
        }

        return $next($request);
    }
}
