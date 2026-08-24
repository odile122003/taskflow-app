<?php

namespace App\Actions\Tasks;

use App\DataTransferObjects\CreateTaskData;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;

/**
 * Un seul cas d'usage : créer une tâche dans un projet. Le contrôleur ne fait
 * plus que valider (StoreTaskRequest) et déléguer ici ; cette classe est
 * testable sans HTTP, sans policy, sans requête — juste Project + CreateTaskData.
 */
final readonly class CreateTaskAction
{
    public function handle(Project $project, CreateTaskData $data): Task
    {
        /** @var Task $task */
        $task = $project->tasks()->create([
            'title' => $data->title,
            // Explicite plutôt que de compter sur le DEFAULT 'todo' de la
            // colonne (Module 3) : sans ça, le modèle en mémoire garde
            // status = null juste après create() jusqu'au prochain rechargement
            // depuis la base — l'enum caché plante au premier ->value lu.
            'status' => TaskStatus::Todo->value,
            'priority' => $data->priority ?? 'normal',
            'due_date' => $data->dueDate,
            'assignee_id' => $data->assigneeId,
        ]);

        $task->tags()->sync($data->tagIds);

        return $task;
    }
}
