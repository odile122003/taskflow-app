<?php

use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

it('displays tasks grouped by status', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);
    Task::factory()->for($project)->create(['title' => 'Tache a faire', 'status' => TaskStatus::Todo]);
    Task::factory()->for($project)->create(['title' => 'Tache en cours', 'status' => TaskStatus::InProgress]);

    Livewire::actingAs($user)
        ->test('pages::projects.kanban-board', ['project' => $project])
        ->assertSee('Tache a faire')
        ->assertSee('Tache en cours');
});

it('moves a task to a new status and re-renders the board', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team, TeamRole::Member);
    $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);

    Livewire::actingAs($user)
        ->test('pages::projects.kanban-board', ['project' => $project])
        ->call('handleMove', $task->id, TaskStatus::Done->value)
        ->assertOk();

    expect($task->fresh()->status)->toBe(TaskStatus::Done);
});

it('refuses to view the board for someone outside the team', function () {
    $project = Project::factory()->create();
    $outsider = User::factory()->create();

    Livewire::actingAs($outsider)
        ->test('pages::projects.kanban-board', ['project' => $project])
        ->assertForbidden();
});

it('refuses a guest role member moving a task', function () {
    $project = Project::factory()->create();
    $guest = memberOf($project->team, TeamRole::Guest);
    $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);

    Livewire::actingAs($guest)
        ->test('pages::projects.kanban-board', ['project' => $project])
        ->call('handleMove', $task->id, TaskStatus::Done->value)
        ->assertForbidden();

    expect($task->fresh()->status)->toBe(TaskStatus::Todo);
});

it('cannot move a task belonging to a different project by forging the id', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $otherProject = Project::factory()->create();
    $foreignTask = Task::factory()->for($otherProject)->create(['status' => TaskStatus::Todo]);

    $component = Livewire::actingAs($user)->test('pages::projects.kanban-board', ['project' => $project]);

    expect(fn () => $component->call('handleMove', $foreignTask->id, TaskStatus::Done->value))
        ->toThrow(ModelNotFoundException::class);

    expect($foreignTask->fresh()->status)->toBe(TaskStatus::Todo);
});

it('shows a banner and refreshes when notified a task moved elsewhere', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $task = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);

    $component = Livewire::actingAs($user)->test('pages::projects.kanban-board', ['project' => $project]);

    // Simule l'arrivée de l'événement diffusé (TaskMoved -> .task.moved) sans
    // dépendre d'un vrai aller-retour WebSocket, hors périmètre d'un test Pest -
    // vérifié pour de vrai en navigateur (deux onglets, Reverb réel).
    $task->update(['status' => TaskStatus::Done]);
    $component->call('handleMovedElsewhere')
        ->assertSet('movedByOther', true)
        ->assertSee('déplacée par quelqu');
});
