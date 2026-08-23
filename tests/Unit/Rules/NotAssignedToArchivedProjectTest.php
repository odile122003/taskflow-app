<?php

use App\Models\Project;
use App\Rules\NotAssignedToArchivedProject;

it('fails when the project is archived', function () {
    $project = Project::factory()->archived()->create();
    $rule = new NotAssignedToArchivedProject($project);
    $failMessage = null;

    $rule->validate('assignee_id', 1, function (string $message) use (&$failMessage) {
        $failMessage = $message;
    });

    expect($failMessage)->toBe("Impossible d'assigner une tâche sur un projet archivé.");
});

it('passes silently when the project is active', function () {
    $project = Project::factory()->create(['is_archived' => false]);
    $rule = new NotAssignedToArchivedProject($project);
    $failCalled = false;

    $rule->validate('assignee_id', 1, function () use (&$failCalled) {
        $failCalled = true;
    });

    expect($failCalled)->toBeFalse();
});
