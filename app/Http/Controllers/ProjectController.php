<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Project::query()->latest()->get();
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
        return $project->load('tasks');
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
