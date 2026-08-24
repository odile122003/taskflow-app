<?php

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use Laravel\Sanctum\Sanctum;

/**
 * cursorPaginate() (Module 12) remplace paginate() : mesuré à 42 ms contre
 * 2,3 s sur un projet de 500 000 tâches (voir CONCEPTS.md pour le détail,
 * y compris le piège du filesort). Ces tests vérifient le comportement —
 * curseur plutôt qu'offset, tri stable par id — pas la performance à
 * cette échelle, jamais reproduite dans la suite de tests.
 */
it('paginates by cursor, without a total count or a last page', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);
    Sanctum::actingAs($user, ['projects:read', 'tasks:read']);
    Task::factory()->for($project)->count(20)->create();

    $response = $this->getJson("/api/v1/projects/{$project->slug}/tasks");

    $response->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('links.prev', null)
        ->assertJsonStructure(['links' => ['next']])
        ->assertJsonMissingPath('meta.total')
        ->assertJsonMissingPath('meta.last_page');
});

it('never repeats or skips a task when following the cursor to the next page', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);
    Sanctum::actingAs($user, ['projects:read', 'tasks:read']);
    Task::factory()->for($project)->count(20)->create();

    $first = $this->getJson("/api/v1/projects/{$project->slug}/tasks")->json();
    $second = $this->getJson($first['links']['next'])->json();

    $firstIds = collect($first['data'])->pluck('id');
    $secondIds = collect($second['data'])->pluck('id');

    expect($firstIds->intersect($secondIds))->toBeEmpty()
        ->and($firstIds->count() + $secondIds->count())->toBe(20);
});

it('defaults to newest task first, ordered by id', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team, TeamRole::Owner);
    Sanctum::actingAs($user, ['projects:read', 'tasks:read']);
    Task::factory()->for($project)->create(['title' => 'Ancienne tache']);
    $newest = Task::factory()->for($project)->create(['title' => 'Tache la plus recente']);

    $response = $this->getJson("/api/v1/projects/{$project->slug}/tasks");

    $response->assertJsonPath('data.0.id', $newest->id);
});
