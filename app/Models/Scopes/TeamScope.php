<?php

namespace App\Models\Scopes;

use App\Support\CurrentTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TeamScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $teamId = app(CurrentTeam::class)->id();

        if ($teamId !== null) {
            $builder->where($model->getTable().'.team_id', $teamId);
        }
    }
}
