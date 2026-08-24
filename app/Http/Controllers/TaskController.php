<?php

namespace App\Http\Controllers;

use App\Actions\Tasks\CreateTaskAction;
use App\Actions\Tasks\MoveTaskAction;
use App\DataTransferObjects\CreateTaskData;
use App\Enums\TaskStatus;
use App\Http\Requests\MoveTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Queries\TaskQuery;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TaskController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('project.active', only: ['store', 'update', 'destroy', 'move']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Project $project, Request $request)
    {
        $this->authorize('view', $project);

        if ($search = $request->string('search')->trim()->value()) {
            // Remplace la version manuelle (LIKE '%...%') : tolère les fautes
            // de frappe, trie par pertinence — voir CONCEPTS.md pour la douleur
            // ressentie avant ce remplacement (typo introuvable, aucun tri).
            return Task::search($search)
                ->where('project_id', $project->id)
                ->query(fn ($query) => $query->with(['assignee', 'tags']))
                ->get();
        }

        return TaskQuery::for($project)->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskRequest $request, Project $project, CreateTaskAction $action)
    {
        $this->authorize('create', [Task::class, $project]);

        $data = CreateTaskData::fromArray($request->validated());

        $task = $action->handle($project, $data);

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
     * Déplace une tâche vers un nouveau statut (carte glissée sur le tableau
     * kanban, Module 13). Même autorisation que la modification générale :
     * déplacer est un cas particulier de modifier.
     */
    public function move(MoveTaskRequest $request, Project $project, Task $task, MoveTaskAction $action)
    {
        $this->authorize('update', $task);

        $status = TaskStatus::from($request->validated('status'));

        $task = $action->handle($task, $status);

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
