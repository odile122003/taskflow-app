<?php

use App\Models\Project;
use App\Models\Task;

/**
 * Recherche via Scout + Meilisearch. La version manuelle (LIKE '%...%',
 * documentée dans CONCEPTS.md) ne trouvait rien sur une simple faute de
 * frappe et ne triait jamais par pertinence — les deux points que ces
 * tests vérifient maintenant pour de vrai. waitForMeilisearch() (tests/Pest.php)
 * attend la fin réelle de l'indexation avant chaque recherche.
 *
 * SCOUT_DRIVER=collection est la valeur par défaut des tests (phpunit.xml) -
 * tout le reste de la suite n'a pas besoin d'un vrai Meilisearch pour
 * simplement créer une tâche. Ce fichier restaure le vrai moteur car c'est
 * justement son comportement (tolérance aux fautes) qui est testé ici ; le
 * driver "collection" ne le reproduirait pas.
 */
beforeEach(fn () => config(['scout.driver' => 'meilisearch']));

it('finds a task by an exact substring of its title', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);
    Task::factory()->for($project)->create(['title' => 'Corriger le bug de pagination']);
    Task::factory()->for($project)->create(['title' => 'Ecrire la documentation']);
    waitForMeilisearch();

    $response = $this->actingAs($user)->getJson("/projects/{$project->slug}/tasks?search=pagination");

    $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'Corriger le bug de pagination');
});

it('finds a task despite a one-letter typo, unlike the LIKE version it replaced', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);
    Task::factory()->for($project)->create(['title' => 'Corriger le bug de pagination']);
    waitForMeilisearch();

    // "paginaton" au lieu de "pagination" : la version manuelle (LIKE)
    // ne trouvait rien sur ce même cas — voir CONCEPTS.md.
    $response = $this->actingAs($user)->getJson("/projects/{$project->slug}/tasks?search=paginaton");

    $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'Corriger le bug de pagination');
});

it('never returns a task from another project', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    $userA = memberOf($projectA->team);
    Task::factory()->for($projectA)->create(['title' => 'Tache confidentielle A']);
    Task::factory()->for($projectB)->create(['title' => 'Tache confidentielle B']);
    waitForMeilisearch();

    $response = $this->actingAs($userA)->getJson("/projects/{$projectA->slug}/tasks?search=confidentielle");

    $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'Tache confidentielle A');
});
