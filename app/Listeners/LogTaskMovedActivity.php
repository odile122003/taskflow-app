<?php

namespace App\Listeners;

use App\Events\TaskMoved;
use App\Models\Activity;
use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * ShouldQueue : écrire une ligne d'activité n'a aucune raison de retarder la
 * réponse HTTP qui a déplacé la tâche. Contrairement à TaskAssignedNotification
 * (Module 7, synchrone pour que le compteur de non-lus soit immédiat), rien
 * ici n'a besoin d'être visible instantanément par l'utilisateur qui agit.
 */
class LogTaskMovedActivity implements ShouldQueue
{
    public function handle(TaskMoved $event): void
    {
        Activity::create([
            'causer_id' => $event->task->assignee_id,
            'subject_type' => Task::class,
            'subject_id' => $event->task->id,
            'description' => sprintf(
                'a changé le statut de « %s » : %s → %s',
                $event->task->title,
                $event->from->label(),
                $event->to->label(),
            ),
            'properties' => [
                'from' => $event->from->value,
                'to' => $event->to->value,
            ],
        ]);
    }
}
