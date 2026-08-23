<?php

use App\Models\Project;
use App\Models\Task;

it('deletes a project archived more than the threshold, with its tasks, cascading', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();

    // travel() : on se place 120 jours dans le passé pour que l'archivage
    // (déclenché par le hook static::updating() de Project) enregistre un
    // archived_at daté d'il y a 120 jours — pas besoin de forcer la colonne
    // à la main, la vraie mécanique du modèle s'exécute, juste à une autre date.
    $this->travel(-120)->days();
    $project->update(['is_archived' => true]);
    $this->travelBack();

    $this->artisan('taskflow:cleanup-archived', ['--days' => 90, '--force' => true])
        ->assertExitCode(0);

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

it('leaves a project archived more recently than the threshold untouched', function () {
    $project = Project::factory()->create();

    $this->travel(-10)->days();
    $project->update(['is_archived' => true]);
    $this->travelBack();

    $this->artisan('taskflow:cleanup-archived', ['--days' => 90, '--force' => true])
        ->assertExitCode(0);

    $this->assertDatabaseHas('projects', ['id' => $project->id]);
});

it('cancels without --force when the confirmation is declined', function () {
    $project = Project::factory()->create();

    $this->travel(-120)->days();
    $project->update(['is_archived' => true]);
    $this->travelBack();

    $this->artisan('taskflow:cleanup-archived', ['--days' => 90])
        ->expectsConfirmation('Supprimer définitivement ces projets et leurs tâches ?', 'no')
        ->assertExitCode(0);

    $this->assertDatabaseHas('projects', ['id' => $project->id]);
});
