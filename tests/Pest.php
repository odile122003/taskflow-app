<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
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

/**
 * Scout envoie les documents à Meilisearch en mode "fire-and-forget" —
 * addDocuments() renvoie dès que la tâche est mise en file côté moteur, pas
 * quand elle est indexée. Sans cette attente, un test qui crée une tâche
 * puis la recherche immédiatement est en course avec l'indexation réelle
 * (Module 12) : parfois vert, parfois rouge selon le timing. On attend
 * qu'il n'y ait plus aucune tâche Meilisearch en cours, pas un sleep() fixe.
 */
function waitForMeilisearch(): void
{
    $client = app(Client::class);
    $query = (new TasksQuery)->setStatuses(['enqueued', 'processing']);
    $deadline = microtime(true) + 5;

    do {
        if ($client->getTasks($query)->getResults() === []) {
            return;
        }

        usleep(50_000);
    } while (microtime(true) < $deadline);
}
