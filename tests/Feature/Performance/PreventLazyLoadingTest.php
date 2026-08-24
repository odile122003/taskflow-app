<?php

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\LazyLoadingViolationException;

/**
 * Model::preventLazyLoading() (Module 12) ne protège que les collections
 * d'au moins 2 modèles — un ->first() isolé n'est jamais un vrai N+1, donc
 * Laravel ne le signale pas (voir CONCEPTS.md). Ce premier test prouve que
 * le mécanisme est bien actif dans l'app, avant de vérifier que les pages
 * réelles ne le déclenchent jamais.
 */
it('throws when a collection of 2+ tasks lazy-loads a relation', function () {
    $project = Project::factory()->create();
    Task::factory()->for($project)->count(3)->create();

    $tasks = $project->tasks;

    expect(fn () => $tasks->each(fn (Task $task) => $task->assignee))
        ->toThrow(LazyLoadingViolationException::class);
});

it('renders the dashboard with multiple tasks across multiple projects without any lazy loading', function () {
    $project = Project::factory()->create();
    $owner = memberOf($project->team, TeamRole::Owner);
    $otherProject = Project::factory()->create(['team_id' => $project->team_id]);

    Task::factory()->for($project)->count(3)->create(['assignee_id' => $owner->id]);
    Task::factory()->for($otherProject)->count(3)->create();

    $this->actingAs($owner)->get('/dashboard')->assertOk();
});

it('renders the kanban board with multiple tasks without any lazy loading', function () {
    $project = Project::factory()->create();
    $owner = memberOf($project->team, TeamRole::Owner);
    Task::factory()->for($project)->count(5)->create(['assignee_id' => $owner->id]);

    $this->actingAs($owner)->get("/projects/{$project->slug}/board")->assertOk();
});
