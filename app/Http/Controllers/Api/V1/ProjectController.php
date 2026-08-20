<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Support\CurrentTeam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    /**
     * Filtres et tri écrits à la main, avant d'adopter spatie/laravel-query-builder
     * (voir ProjectController::index dans la doc du Module 9 pour la version
     * équivalente au paquet — ce contrôleur reste volontairement simple : un
     * seul champ filtrable, comparé à Task ci-après).
     *
     * ?filter[is_archived]=1&sort=-created_at
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $query = Project::query()->withCount('tasks');

        if ($request->filled('filter.is_archived')) {
            $query->where('is_archived', $request->boolean('filter.is_archived'));
        }

        $sort = (string) $request->input('sort', '-created_at');
        $column = ltrim($sort, '-');
        if (in_array($column, ['name', 'created_at'], true)) {
            $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc');
        }

        return ProjectResource::collection($query->paginate());
    }

    public function store(StoreProjectRequest $request, CurrentTeam $currentTeam): ProjectResource
    {
        $this->authorize('create', Project::class);

        $project = Project::create([...$request->validated(), 'team_id' => $currentTeam->id()]);

        return new ProjectResource($project);
    }

    public function show(Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        return new ProjectResource($project->loadCount('tasks'));
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return new ProjectResource($project);
    }

    public function destroy(Project $project): Response
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->noContent();
    }
}
