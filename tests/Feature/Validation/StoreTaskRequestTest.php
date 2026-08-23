<?php

use App\Models\Project;
use App\Models\User;

it('rejects invalid priority values', function (string $priority) {
    $project = Project::factory()->create();
    $user = memberOf($project->team);

    $this->actingAs($user)
        ->postJson("/projects/{$project->slug}/tasks", [
            'title' => 'Une tâche',
            'priority' => $priority,
        ])
        ->assertJsonValidationErrors('priority');
})->with(['urgent', 'basse', 'HIGH', '', '1']);

it('accepts every valid priority value', function (string $priority) {
    $project = Project::factory()->create();
    $user = memberOf($project->team);

    $this->actingAs($user)
        ->postJson("/projects/{$project->slug}/tasks", [
            'title' => 'Une tâche',
            'priority' => $priority,
            // La priorité "high" exige une échéance (règle testée séparément
            // ci-dessous) : sans ça, ce cas du dataset échouerait en 422 pour
            // une raison hors sujet ici.
            'due_date' => now()->addWeek()->toDateString(),
        ])
        ->assertCreated();
})->with(['low', 'normal', 'high']);

it('requires a due date when the priority is high, but not otherwise', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);

    $this->actingAs($user)
        ->postJson("/projects/{$project->slug}/tasks", ['title' => 'Urgent', 'priority' => 'high'])
        ->assertJsonValidationErrors('due_date');

    $this->actingAs($user)
        ->postJson("/projects/{$project->slug}/tasks", ['title' => 'Pas urgent', 'priority' => 'normal'])
        ->assertCreated();
});

it('refuses to assign a task to someone from another team', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $outsider = User::factory()->create();

    $this->actingAs($user)
        ->postJson("/projects/{$project->slug}/tasks", [
            'title' => 'Une tâche',
            'assignee_id' => $outsider->id,
        ])
        ->assertJsonValidationErrors('assignee_id');
});
