<?php

use App\Models\Project;
use App\Models\Scopes\TeamScope;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Le scope global TeamScope filtrerait par l'équipe *courante* de $user — hors
// de propos ici, on vérifie l'appartenance au projet demandé, quel qu'il soit.
Broadcast::channel('projects.{projectId}', function (User $user, int $projectId) {
    $project = Project::withoutGlobalScope(TeamScope::class)->find($projectId);

    return $project !== null && $user->roleIn($project->team) !== null;
});
