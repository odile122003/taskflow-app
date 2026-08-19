<?php

namespace App\Observers;

use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Task;

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
     * Après l'écriture : journalise le changement de statut dans activities.
     */
    public function updated(Task $task): void
    {
        if (! $task->wasChanged('status')) {
            return;
        }

        $original = $task->getOriginal('status');
        $from = $original instanceof TaskStatus ? $original : TaskStatus::from($original);

        Activity::create([
            'causer_id' => $task->assignee_id,
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'description' => sprintf(
                'a changé le statut de « %s » : %s → %s',
                $task->title,
                $from->label(),
                $task->status->label(),
            ),
            'properties' => [
                'from' => $from->value,
                'to' => $task->status->value,
            ],
        ]);
    }
}
