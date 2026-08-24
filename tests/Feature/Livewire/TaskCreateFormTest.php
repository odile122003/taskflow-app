<?php

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

it('creates a task and redirects to the project page', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);

    Livewire::actingAs($user)
        ->test('pages::projects.tasks.create', ['project' => $project])
        ->set('title', 'Ecrire les tests')
        ->set('priority', 'normal')
        ->call('handleCreate')
        ->assertRedirect(route('projects.show', $project));

    $this->assertDatabaseHas('tasks', [
        'project_id' => $project->id,
        'title' => 'Ecrire les tests',
        'status' => 'todo',
    ]);
});

it('requires a title', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);

    Livewire::actingAs($user)
        ->test('pages::projects.tasks.create', ['project' => $project])
        ->set('title', '')
        ->call('handleCreate')
        ->assertHasErrors(['title' => 'required']);
});

it('requires a due date when priority is high, in real time as the field is blurred', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);

    Livewire::actingAs($user)
        ->test('pages::projects.tasks.create', ['project' => $project])
        ->set('title', 'Tache urgente')
        ->set('priority', 'high')
        ->set('dueDate', '') // simule le blur sans date renseignée
        ->assertHasErrors(['dueDate' => 'required_if']);
});

it('refuses a guest role member', function () {
    $project = Project::factory()->create();
    $guest = memberOf($project->team, TeamRole::Guest);

    Livewire::actingAs($guest)
        ->test('pages::projects.tasks.create', ['project' => $project])
        ->assertForbidden();
});

it('refuses someone outside the team', function () {
    $project = Project::factory()->create();
    $outsider = User::factory()->create();

    Livewire::actingAs($outsider)
        ->test('pages::projects.tasks.create', ['project' => $project])
        ->assertForbidden();
});

it('rejects an assignee outside the project team', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $outsider = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::projects.tasks.create', ['project' => $project])
        ->set('title', 'Tache mal assignee')
        ->set('assigneeId', $outsider->id)
        ->call('handleCreate')
        ->assertHasErrors(['assigneeId']);
});
