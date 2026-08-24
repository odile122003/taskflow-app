<?php

use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Events\TaskMoved;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Event;

it('moves a task to a new status', function () {
    $project = Project::factory()->create();
    $member = memberOf($project->team, TeamRole::Member);
    $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);

    $this->actingAs($member)
        ->patchJson("/projects/{$project->slug}/tasks/{$task->id}/move", ['status' => 'in_progress'])
        ->assertOk()
        ->assertJsonPath('status', 'in_progress');

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);
});

it('rejects a status outside the enum', function () {
    $project = Project::factory()->create();
    $member = memberOf($project->team, TeamRole::Member);
    $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);

    $this->actingAs($member)
        ->patchJson("/projects/{$project->slug}/tasks/{$task->id}/move", ['status' => 'not_a_status'])
        ->assertInvalid('status');

    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

it('refuses a guest role member', function () {
    $project = Project::factory()->create();
    $guest = memberOf($project->team, TeamRole::Guest);
    $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);

    $this->actingAs($guest)
        ->patchJson("/projects/{$project->slug}/tasks/{$task->id}/move", ['status' => 'done'])
        ->assertForbidden();

    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

it('refuses to move a task on an archived project', function () {
    $project = Project::factory()->archived()->create();
    $member = memberOf($project->team, TeamRole::Member);
    $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);

    $this->actingAs($member)
        ->patchJson("/projects/{$project->slug}/tasks/{$task->id}/move", ['status' => 'done'])
        ->assertForbidden();

    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

it('still dispatches TaskMoved when moved through the dedicated endpoint', function () {
    Event::fake([TaskMoved::class]);

    $project = Project::factory()->create();
    $member = memberOf($project->team, TeamRole::Member);
    $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);

    $this->actingAs($member)
        ->patchJson("/projects/{$project->slug}/tasks/{$task->id}/move", ['status' => 'done'])
        ->assertOk();

    Event::assertDispatched(TaskMoved::class, fn (TaskMoved $event) => $event->to === TaskStatus::Done);
});
