<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke()
    {
        // with('tasks.assignee') charge les tâches ET leur assigné en 2 requêtes
        // au lieu d'une par projet puis une par tâche (voir CONCEPTS.md, mesuré
        // à 42 requêtes sans eager loading contre 6 avec, sur ce même jeu de données).
        $projects = Project::with('tasks.assignee')->get();
        $activities = Activity::with('subject', 'causer')->latest()->limit(10)->get();

        // Exercice module 4 : "les 5 utilisateurs ayant le plus de tâches terminées
        // ce mois", en une seule requête (withCount génère un sous-select, pas une
        // requête par utilisateur).
        $topContributors = User::query()
            ->withCount(['assignedTasks as completed_this_month' => function ($query) {
                $query->where('status', TaskStatus::Done)
                    ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()]);
            }])
            ->orderByDesc('completed_this_month')
            ->limit(5)
            ->get();

        // Exercice module 4 : pipeline de Collections plutôt qu'un foreach avec
        // accumulateur — flatMap aplatit les tâches de tous les projets déjà chargés
        // (aucune requête SQL supplémentaire, $projects est déjà en mémoire).
        /** @var Collection<int, Task> $allTasks */
        $allTasks = $projects->flatMap(fn (Project $project) => $project->tasks);

        $statusCounts = $allTasks
            ->groupBy(fn (Task $task) => $task->status->value)
            ->map(fn (Collection $tasks) => $tasks->count());

        return view('dashboard', compact('projects', 'activities', 'topContributors', 'statusCounts'));
    }
}
