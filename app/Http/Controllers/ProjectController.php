<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Support\CurrentTeam;
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
        return view('projects.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectRequest $request)
    {
        // Pas d'authentification avant le Module 6 : le nouveau projet est
        // rattaché provisoirement à l'équipe courante du contexte (vide pour
        // l'instant, voir CurrentTeam) ou, à défaut, à la première équipe
        // existante. Le Module 6 remplacera ceci par l'équipe réelle de
        // l'utilisateur connecté — team_id ne viendra jamais du formulaire.
        $teamId = app(CurrentTeam::class)->id() ?? Team::query()->value('id');

        $project = Project::create([...$request->validated(), 'team_id' => $teamId]);

        return redirect()->route('projects.show', $project)->with('success', 'Projet créé.');
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
        return view('projects.edit', ['project' => $project]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectRequest $request, Project $project)
    {
        $project->update($request->validated());

        return redirect()->route('projects.show', $project)->with('success', 'Projet mis à jour.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Projet supprimé.');
    }
}
