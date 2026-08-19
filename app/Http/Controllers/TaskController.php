<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\CreateTaskData;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TaskController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('project.active', only: ['store', 'update', 'destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Project $project)
    {
        $this->authorize('view', $project);

        return $project->tasks;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Project $project)
    {
        abort(501, 'Formulaire de création à implémenter au Module 13 (front interactif)');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskRequest $request, Project $project)
    {
        $this->authorize('create', [Task::class, $project]);

        $data = CreateTaskData::fromArray($request->validated());

        /** @var Task $task */
        $task = $project->tasks()->create([
            'title' => $data->title,
            'priority' => $data->priority ?? 'normal',
            'due_date' => $data->dueDate,
            'assignee_id' => $data->assigneeId,
        ]);

        $task->tags()->sync($data->tagIds);

        return response()->json($task->load('tags'), 201);
    }

    /**
     * Display the specified resource.
     *
     * Le contrôle « la tâche appartient-elle bien au projet de l'URL ? » n'est plus
     * fait ici à la main : `->scoped()` sur la route (routes/web.php) délègue cette
     * vérification à Laravel, qui l'applique via la relation `tasks()` de Project.
     */
    public function show(Project $project, Task $task)
    {
        $this->authorize('view', $task);

        return $task;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project, Task $task)
    {
        abort(501, 'Formulaire d\'édition à implémenter au Module 13 (front interactif)');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaskRequest $request, Project $project, Task $task)
    {
        $this->authorize('update', $task);

        $validated = $request->validated();

        $task->update($validated);

        if (array_key_exists('tags', $validated)) {
            $task->tags()->sync($validated['tags']);
        }

        return $task->fresh('tags');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project, Task $task)
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }
}
