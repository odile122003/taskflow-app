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
     *
     * cursorPaginate() plutôt que paginate() (Module 12) : sur un projet de
     * 500 000 tâches, OFFSET devient lui-même le coût dominant en profondeur
     * de page — MySQL doit parcourir et jeter toutes les lignes avant l'offset
     * avant de renvoyer la page. Le curseur repart de la dernière valeur vue
     * (WHERE id < ...), coût constant quelle que soit la profondeur — mesuré
     * à ~3 ms aussi bien en première page qu'à 300 000 lignes de profondeur.
     * Contrepartie assumée : plus de "page 42 sur 1000" possible, seulement
     * précédent/suivant.
     *
     * defaultSort('-id') plutôt que '-created_at' : id croît dans l'ordre de
     * création, donc "plus récent d'abord" reste vrai en triant dessus — et
     * seul le tri par id évite un filesort ici. Piège réel mesuré : même
     * avec l'index (project_id, created_at) en place, MySQL choisissait
     * encore "Using filesort" pour ORDER BY created_at DESC, id DESC (2,2 s
     * sur 500 000 lignes) — le vrai tri PRIMARY KEY, lui, ne filesort jamais
     * (~3 ms). L'index sur created_at reste utile pour le tri explicite via
     * ?sort=created_at, plus rare, jamais le chemin par défaut. Voir
     * CONCEPTS.md pour la mesure complète, avant/après, à chaque étape.
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
            ->allowedSorts(['due_date', 'priority', 'created_at', 'id'])
            ->defaultSort('-id')
            ->cursorPaginate();

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
