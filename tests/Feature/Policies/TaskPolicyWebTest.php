<?php

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

test('a guest role member can view a task but cannot delete it', function () {
    $project = Project::factory()->create();
    $guest = memberOf($project->team, TeamRole::Guest);
    $task = Task::factory()->for($project)->create();

    $this->actingAs($guest)
        ->getJson("/projects/{$project->slug}/tasks/{$task->id}")
        ->assertOk();

    $this->actingAs($guest)
        ->deleteJson("/projects/{$project->slug}/tasks/{$task->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'deleted_at' => null]);
});

test('a guest role member cannot create or update a task', function () {
    $project = Project::factory()->create();
    $guest = memberOf($project->team, TeamRole::Guest);
    $task = Task::factory()->for($project)->create();

    $this->actingAs($guest)
        ->postJson("/projects/{$project->slug}/tasks", ['title' => 'Nouvelle tâche'])
        ->assertForbidden();

    $this->actingAs($guest)
        ->putJson("/projects/{$project->slug}/tasks/{$task->id}", ['title' => 'Modifiée'])
        ->assertForbidden();
});

test('someone outside the team cannot access a task by guessing its direct URL', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->getJson("/projects/{$project->slug}/tasks/{$task->id}")
        ->assertForbidden();

    $this->actingAs($outsider)
        ->deleteJson("/projects/{$project->slug}/tasks/{$task->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'deleted_at' => null]);
});

test('an owner or admin can delete a task, but a plain member cannot', function () {
    $project = Project::factory()->create();
    $admin = memberOf($project->team, TeamRole::Admin);
    $member = memberOf($project->team, TeamRole::Member);
    $task = Task::factory()->for($project)->create();

    $this->actingAs($member)->deleteJson("/projects/{$project->slug}/tasks/{$task->id}")->assertForbidden();
    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'deleted_at' => null]);

    $this->actingAs($admin)->deleteJson("/projects/{$project->slug}/tasks/{$task->id}")->assertNoContent();
});
