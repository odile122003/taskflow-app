<?php

use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Support\TeamStatsCache;
use Illuminate\Support\Facades\DB;

it('computes projects, tasks, statuses and completions this month', function () {
    $project = Project::factory()->create();
    Project::factory()->create(['team_id' => $project->team_id]);

    $todo = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);
    Task::factory()->for($project)->create(['status' => TaskStatus::InProgress]);
    $todo->update(['status' => TaskStatus::Done]);

    $stats = TeamStatsCache::remember($project->team);

    expect($stats)->toBe([
        'projects_count' => 2,
        'tasks_count' => 2,
        'tasks_by_status' => ['todo' => 0, 'in_progress' => 1, 'done' => 1],
        'completed_this_month' => 1,
    ]);
});

it('serves the second call from cache, without hitting the database again', function () {
    $project = Project::factory()->create();
    Task::factory()->for($project)->create();

    TeamStatsCache::remember($project->team);

    DB::enableQueryLog();
    TeamStatsCache::remember($project->team);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
});

it('invalidates the cache when a task is created', function () {
    $project = Project::factory()->create();
    expect(TeamStatsCache::remember($project->team)['tasks_count'])->toBe(0);

    Task::factory()->for($project)->create();

    expect(TeamStatsCache::remember($project->team)['tasks_count'])->toBe(1);
});

it('invalidates the cache when a task status changes', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);
    expect(TeamStatsCache::remember($project->team)['tasks_by_status']['done'])->toBe(0);

    $task->update(['status' => TaskStatus::Done]);

    expect(TeamStatsCache::remember($project->team)['tasks_by_status']['done'])->toBe(1);
});

it('invalidates the cache when a task is deleted', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();
    expect(TeamStatsCache::remember($project->team)['tasks_count'])->toBe(1);

    $task->delete();

    expect(TeamStatsCache::remember($project->team)['tasks_count'])->toBe(0);
});

it('invalidates the cache when a project is created', function () {
    $project = Project::factory()->create();
    expect(TeamStatsCache::remember($project->team)['projects_count'])->toBe(1);

    Project::factory()->create(['team_id' => $project->team_id]);

    expect(TeamStatsCache::remember($project->team)['projects_count'])->toBe(2);
});

it('does not leak one team stats into another', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    Task::factory()->for($projectA)->count(3)->create();

    expect(TeamStatsCache::remember($projectA->team)['tasks_count'])->toBe(3)
        ->and(TeamStatsCache::remember($projectB->team)['tasks_count'])->toBe(0);
});

it('exposes the current team stats through GET /team/stats', function () {
    $project = Project::factory()->create();
    $owner = memberOf($project->team, TeamRole::Owner);
    Task::factory()->for($project)->count(2)->create();

    $this->actingAs($owner)
        ->getJson('/team/stats')
        ->assertOk()
        ->assertJson(['projects_count' => 1, 'tasks_count' => 2]);
});
