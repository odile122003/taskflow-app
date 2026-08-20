<?php

namespace App\Observers;

use App\Enums\TaskStatus;
use App\Events\TaskMoved;
use App\Models\Task;
use App\Notifications\TaskAssignedNotification;

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
        }

        if ($task->wasChanged('assignee_id')) {
            $this->notifyAssignee($task);
        }
    }

    private function notifyAssignee(Task $task): void
    {
        $task->assignee?->notify(new TaskAssignedNotification($task));
    }
}
