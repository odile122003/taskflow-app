<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
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
        return $project->tasks;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Project $project)
    {
        abort(501, 'Formulaire de création à implémenter au Module 2 (Blade)');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:50'],
        ]);

        $task = $project->tasks()->create($data);

        return response()->json($task, 201);
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
        return $task;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project, Task $task)
    {
        abort(501, 'Formulaire d\'édition à implémenter au Module 2 (Blade)');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project, Task $task)
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:50'],
        ]);

        $task->update($data);

        return $task;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project, Task $task)
    {
        $task->delete();

        return response()->noContent();
    }
}
