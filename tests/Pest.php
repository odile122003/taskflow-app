<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| RefreshDatabase : chaque test tourne dans une transaction annulée à la
| fin (base SQLite en mémoire, voir phpunit.xml) — aucun test ne voit les
| données laissées par un autre.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Fonctions utilitaires
|--------------------------------------------------------------------------
|
| memberOf() : crée un utilisateur membre d'une équipe avec le rôle donné.
| Répété dans presque tous les tests Feature (policies, API, notifications…)
| — centralisé ici plutôt que copié-collé dans chaque fichier.
|
*/

function memberOf(Team $team, TeamRole $role = TeamRole::Owner): User
{
    $user = User::factory()->create();
    $team->users()->attach($user, ['role' => $role->value]);

    return $user;
}
