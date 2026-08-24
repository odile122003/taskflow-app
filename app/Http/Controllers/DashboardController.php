<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentTeam;
use App\Support\TeamStatsCache;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(CurrentTeam $currentTeam)
    {
        $teamId = $currentTeam->id();

        // with('tasks.assignee') charge les tâches ET leur assigné en 2 requêtes
        // au lieu d'une par projet puis une par tâche (voir CONCEPTS.md, mesuré
        // à 42 requêtes sans eager loading contre 6 avec, sur ce même jeu de données).
        // Déjà filtré par équipe : Project applique le scope global TeamScope.
        $projects = Project::with('tasks.assignee')->get();

        // Activity n'a pas de team_id propre (elle décrit un changement sur une
        // tâche polymorphe) : sans ce filtre explicite, une équipe verrait
        // l'activité de toutes les autres équipes — fuite trouvée en testant
        // le tableau de bord avec une deuxième équipe réelle (Module 6).
        $activities = Activity::with('subject', 'causer')
            ->whereHasMorph('subject', [Task::class], function ($query) use ($teamId) {
                $query->whereHas('project', fn ($q) => $q->where('team_id', $teamId));
            })
            ->latest()
            ->limit(10)
            ->get();

        // Exercice module 4 : "les 5 utilisateurs ayant le plus de tâches terminées
        // ce mois", en une seule requête (withCount génère un sous-select, pas une
        // requête par utilisateur). Restreint aux membres de l'équipe courante pour
        // la même raison que ci-dessus.
        $topContributors = User::query()
            ->whereHas('teams', fn ($query) => $query->where('teams.id', $teamId))
            ->withCount(['assignedTasks as completed_this_month' => function ($query) {
                $query->where('status', TaskStatus::Done)
                    ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()]);
            }])
            ->orderByDesc('completed_this_month')
            ->limit(5)
            ->get();

        // Module 12 : remplace le flatMap+groupBy en mémoire (Module 4) par
        // un agrégat mis en cache. Ici le gain net sur *cette* page est modeste
        // ($projects->tasks est déjà chargé pour l'affichage par carte) — le
        // vrai bénéfice apparaît quand plusieurs endroits (ce tableau de bord,
        // GET /team/stats) redemandent le même agrégat sans reconstruire la
        // liste complète des tâches à chaque fois. Voir CONCEPTS.md.
        $statusCounts = TeamStatsCache::remember($currentTeam->get())['tasks_by_status'];

        return view('dashboard', compact('projects', 'activities', 'topContributors', 'statusCounts'));
    }
}
