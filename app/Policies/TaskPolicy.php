<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    /**
     * Owner : accès complet aux tâches de son équipe, quelle que soit
     * l'action — mais seulement si le jeton (le cas échéant) a l'ability
     * requise. Un jeton en lecture seule reste bloqué même pour un Owner.
     */
    public function before(User $user, string $ability, mixed $arg = null): Response|bool|null
    {
        $required = in_array($ability, ['view', 'viewAny'], true) ? 'tasks:read' : 'tasks:write';

        if (! $user->tokenCan($required)) {
            return Response::deny("Ce jeton n'a pas la permission « {$required} ».");
        }

        $team = match (true) {
            $arg instanceof Task => $arg->projectForAuthorization->team,
            $arg instanceof Project => $arg->team,
            default => null,
        };

        if ($team !== null && $user->roleIn($team) === TeamRole::Owner) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): Response
    {
        return $user->roleIn($task->projectForAuthorization->team) !== null
            ? Response::allow()
            : Response::deny("Vous n'êtes pas membre de l'équipe propriétaire de cette tâche.");
    }

    /**
     * @param  Project  $project  Passé explicitement par le contrôleur via
     *                            authorize('create', [Task::class, $project])
     *                            car une tâche qui n'existe pas encore n'a
     *                            pas de projet dont déduire l'équipe.
     */
    public function create(User $user, Project $project): Response
    {
        return in_array($user->roleIn($project->team), [TeamRole::Owner, TeamRole::Admin, TeamRole::Member], true)
            ? Response::allow()
            : Response::deny('Les invités ne peuvent pas créer de tâche.');
    }

    public function update(User $user, Task $task): Response
    {
        // Un invité (Guest) peut voir les tâches mais jamais les modifier.
        return in_array($user->roleIn($task->projectForAuthorization->team), [TeamRole::Owner, TeamRole::Admin, TeamRole::Member], true)
            ? Response::allow()
            : Response::deny('Les invités ne peuvent pas modifier de tâche.');
    }

    public function delete(User $user, Task $task): Response
    {
        return in_array($user->roleIn($task->projectForAuthorization->team), [TeamRole::Owner, TeamRole::Admin], true)
            ? Response::allow()
            : Response::deny('Seuls le ou la propriétaire et les administrateurs peuvent supprimer une tâche.');
    }
}
