<?php

namespace App\Observers;

use App\Models\Project;
use App\Support\TeamStatsCache;

class ProjectObserver
{
    public function created(Project $project): void
    {
        TeamStatsCache::forget($project->team_id);
    }

    public function deleted(Project $project): void
    {
        TeamStatsCache::forget($project->team_id);
    }
}
