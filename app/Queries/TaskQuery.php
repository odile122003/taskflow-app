<?php

namespace App\Queries;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Point d'entrée unique pour interroger les tâches d'un projet. Avant cette
 * classe, `with(['assignee', 'tags'])` n'existait que dans le contrôleur API
 * (Api\V1\TaskController::index) ; le contrôleur web listait les tâches sans
 * ces relations. Les deux consommateurs partagent maintenant la même base de
 * requête — un futur troisième endroit qui liste des tâches ne peut plus
 * oublier ces relations et recréer un N+1.
 */
final class TaskQuery
{
    /**
     * @return HasMany<Task, Project>
     */
    public static function for(Project $project): HasMany
    {
        return $project->tasks()->with(['assignee', 'tags']);
    }
}
