<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('projects.index', [
            'projects' => Project::query()->latest()->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Formulaire HTML : implémenté au Module 2 (Blade). Pas de vue pour l'instant.
        abort(501, 'Formulaire de création à implémenter au Module 2 (Blade)');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:projects,slug'],
        ]);

        $project = Project::create($data);

        return response()->json($project, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        return view('projects.show', [
            'project' => $project->load('tasks'),
        ]);
    }

    /**
     * Tableau kanban du projet (todo / in_progress / done).
     *
     * Validation module 4 : ≤ 5 requêtes SQL quel que soit le nombre de tâches.
     * Ici : 1 (résolution de {project} par le binding) + 1 (tasks) + 1 (assignees
     * via la relation chargée en aval) = 3, indépendamment du nombre de tâches.
     */
    public function board(Project $project)
    {
        /** @var Collection<int, Task> $projectTasks */
        $projectTasks = $project->tasks()->with('assignee')->get();

        $tasks = $projectTasks->groupBy(fn (Task $task) => $task->status->value);

        return view('projects.board', [
            'project' => $project,
            'tasksByStatus' => $tasks,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project)
    {
        abort(501, 'Formulaire d\'édition à implémenter au Module 2 (Blade)');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'is_archived' => ['sometimes', 'boolean'],
        ]);

        $project->update($data);

        return $project;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        $project->delete();

        return response()->noContent();
    }
}
