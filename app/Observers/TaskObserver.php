<?php

namespace App\Observers;

use App\Enums\TaskStatus;
use App\Events\TaskMoved;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Support\TeamStatsCache;

class TaskObserver
{
    /**
     * Avant l'écriture en base : si le statut change, on met à jour completed_at
     * dans la même requête UPDATE (pas besoin d'un second aller-retour SQL).
     */
    public function updating(Task $task): void
    {
        if (! $task->isDirty('status')) {
            return;
        }

        $task->completed_at = $task->status === TaskStatus::Done ? now() : null;
    }

    /**
     * Après la création : si la tâche naît déjà assignée, on prévient
     * l'assigné. `created` (pas `creating`) car on notifie une fois la
     * tâche réellement en base, avec un id valide pour le lien de la notif.
     */
    public function created(Task $task): void
    {
        $this->notifyAssignee($task);
        $this->invalidateTeamStats($task);
    }

    /**
     * Après l'écriture : signale le changement de statut au reste de
     * l'application via un événement (journalisation + diffusion temps réel,
     * Module 8 — voir TaskMoved), et notifie le nouvel assigné en cas de
     * réassignation. Les deux sont indépendants — une même requête peut
     * changer l'un, l'autre, ou les deux à la fois.
     */
    public function updated(Task $task): void
    {
        if ($task->wasChanged('status')) {
            $original = $task->getOriginal('status');
            $from = $original instanceof TaskStatus ? $original : TaskStatus::from($original);

            event(new TaskMoved($task, $from, $task->status));

            // Seul le statut (et completed_at, dérivé de lui) entre dans
            // TeamStatsCache — un changement de titre ou d'assigné ne rend
            // pas le cache obsolète, inutile de le vider pour ça.
            $this->invalidateTeamStats($task);
        }

        if ($task->wasChanged('assignee_id')) {
            $this->notifyAssignee($task);
        }
    }

    /**
     * Une tâche supprimée change tasks_count et tasks_by_status.
     */
    public function deleted(Task $task): void
    {
        $this->invalidateTeamStats($task);
    }

    /**
     * User::find() plutôt que $task->assignee : la relation assignee() peut
     * déjà être en cache sur cette instance depuis un accès antérieur
     * (ex. created() la charge une première fois) — après une réassignation,
     * $task->assignee renverrait alors encore l'ancien assigné, pas le
     * nouveau. Bug réel trouvé en testant une réassignation avec
     * Notification::fake() : la notification partait vers la mauvaise
     * personne.
     */
    private function notifyAssignee(Task $task): void
    {
        if ($task->assignee_id === null) {
            return;
        }

        User::find($task->assignee_id)?->notify(new TaskAssignedNotification($task));
    }

    private function invalidateTeamStats(Task $task): void
    {
        TeamStatsCache::forget($task->project->team_id);
    }
}
