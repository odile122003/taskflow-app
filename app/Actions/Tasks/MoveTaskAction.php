<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;

/**
 * Cas d'usage étroit : changer uniquement le statut d'une tâche (le
 * déplacement d'une carte sur le futur tableau kanban, Module 13). Toute la
 * logique métier qui en découle — completed_at, événement TaskMoved — vit
 * déjà dans TaskObserver et se déclenche normalement puisqu'on passe par
 * Task::update(), donc rien à dupliquer ici : cette classe ne fait
 * qu'exposer un point d'entrée nommé, plus restreint qu'un update() générique
 * qui accepterait n'importe quel champ.
 */
final readonly class MoveTaskAction
{
    public function handle(Task $task, TaskStatus $status): Task
    {
        $task->update(['status' => $status->value]);

        return $task;
    }
}
