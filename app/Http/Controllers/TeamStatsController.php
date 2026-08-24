<?php

namespace App\Http\Controllers;

use App\Support\CurrentTeam;
use App\Support\TeamStatsCache;
use Illuminate\Http\JsonResponse;

class TeamStatsController extends Controller
{
    /**
     * Toujours l'équipe courante (jamais un {team} depuis l'URL) : évite
     * d'avoir à écrire une policy pour empêcher de consulter les stats
     * d'une équipe dont on n'est pas membre — la question ne se pose pas.
     */
    public function __invoke(CurrentTeam $currentTeam): JsonResponse
    {
        return response()->json(TeamStatsCache::remember($currentTeam->get()));
    }
}
