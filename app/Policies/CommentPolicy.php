<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CommentPolicy
{
    public function before(User $user, string $ability, mixed $arg = null): ?bool
    {
        $team = match (true) {
            $arg instanceof Comment && $arg->commentable instanceof Task => $arg->commentable->projectForAuthorization->team,
            $arg instanceof Task => $arg->projectForAuthorization->team,
            default => null,
        };

        if ($team !== null && $user->roleIn($team) === TeamRole::Owner) {
            return true;
        }

        return null;
    }

    /**
     * @param  Task  $task  Passé explicitement par le contrôleur (comme
     *                      TaskPolicy::create) : un commentaire qui n'existe
     *                      pas encore n'a pas de tâche dont déduire l'équipe.
     */
    public function create(User $user, Task $task): Response
    {
        return in_array($user->roleIn($task->projectForAuthorization->team), [TeamRole::Owner, TeamRole::Admin, TeamRole::Member], true)
            ? Response::allow()
            : Response::deny('Les invités ne peuvent pas commenter.');
    }

    public function delete(User $user, Comment $comment): Response
    {
        if ($comment->user_id === $user->id) {
            return Response::allow();
        }

        if (! $comment->commentable instanceof Task) {
            return Response::deny('Type de commentaire non pris en charge.');
        }

        return in_array($user->roleIn($comment->commentable->projectForAuthorization->team), [TeamRole::Owner, TeamRole::Admin], true)
            ? Response::allow()
            : Response::deny('Vous ne pouvez supprimer que vos propres commentaires.');
    }
}
