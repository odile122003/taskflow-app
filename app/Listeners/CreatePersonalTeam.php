<?php

namespace App\Listeners;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;

/**
 * Un compte sans équipe ne peut rien faire dans TaskFlow (projets et tâches
 * appartiennent toujours à une équipe). On crée donc une équipe personnelle
 * à l'inscription, avec l'utilisateur comme propriétaire (TeamRole::Owner).
 * Rejoindre une équipe existante se fera via une invitation (TeamMemberController).
 */
class CreatePersonalTeam
{
    public function handle(Registered $event): void
    {
        /** @var User $user */
        $user = $event->user;

        $team = Team::create([
            'name' => "Équipe de {$user->name}",
            'slug' => Str::slug($user->name).'-'.$user->id,
        ]);

        $team->users()->attach($user, ['role' => TeamRole::Owner->value]);
    }
}
