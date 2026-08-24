<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tasks\CreateTaskAction;
use App\DataTransferObjects\CreateTaskData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Queries\TaskQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaskController extends Controller
{
    /**
     * ?filter[status]=done&filter[tag]=urgent&sort=-due_date
     *
     * Remplace une première version écrite à la main (une condition par
     * champ filtrable, copiée-collée à chaque nouveau champ, aucune garantie
     * contre l'exposition accidentelle d'une colonne non désirée sans y
     * penser explicitement) — voir CONCEPTS.md pour la version manuelle et
     * la douleur ressentie qui a justifié ce paquet.
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('view', $project);

        $tasks = QueryBuilder::for(TaskQuery::for($project))
            ->allowedFilters([
                'status',
                AllowedFilter::callback(
                    'tag',
                    fn ($query, $value) => $query->whereHas('tags', fn ($q) => $q->where('slug', $value))
                ),
            ])
            ->allowedSorts(['due_date', 'priority', 'created_at'])
            ->defaultSort('-created_at')
            ->paginate();

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request, Project $project, CreateTaskAction $action): TaskResource
    {
        $this->authorize('create', [Task::class, $project]);

        $data = CreateTaskData::fromArray($request->validated());

        $task = $action->handle($project, $data);

        return new TaskResource($task->load(['assignee', 'tags']));
    }

    public function show(Project $project, Task $task): TaskResource
    {
        $this->authorize('view', $task);

        return new TaskResource($task->load(['assignee', 'tags']));
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $validated = $request->validated();

        $task->update($validated);

        if (array_key_exists('tags', $validated)) {
            $task->tags()->sync($validated['tags']);
        }

        return new TaskResource($task->fresh(['assignee', 'tags']));
    }

    public function destroy(Project $project, Task $task): Response
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }
}
