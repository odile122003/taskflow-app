<?php

use App\Contracts\AttachmentStorage;
use App\Models\Project;
use App\Models\Task;
use App\Services\Attachments\InMemoryAttachmentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

/**
 * Preuve que le contrôleur ne dépend que de l'interface, pas de la façade
 * Storage : on remplace l'implémentation entière dans le conteneur, aucun
 * appel à Storage::fake() ici. Le disque réel n'est jamais sollicité, faux
 * ou non — seul le tableau PHP d'InMemoryAttachmentStorage l'est.
 */
it('stores and downloads an attachment without ever touching a real disk', function () {
    Queue::fake();
    $this->app->singleton(AttachmentStorage::class, InMemoryAttachmentStorage::class);

    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $task = Task::factory()->for($project)->create();

    $response = $this->actingAs($user)->postJson(
        "/projects/{$project->slug}/tasks/{$task->id}/attachments",
        ['file' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf')]
    );

    $response->assertCreated();

    /** @var InMemoryAttachmentStorage $storage */
    $storage = $this->app->make(AttachmentStorage::class);
    expect($storage->has($response->json('path')))->toBeTrue();

    $this->actingAs($user)
        ->get("/projects/{$project->slug}/tasks/{$task->id}/attachments/{$response->json('id')}/download")
        ->assertOk();
});

it('removes the file from the in-memory store when the attachment is destroyed', function () {
    Queue::fake();
    $this->app->singleton(AttachmentStorage::class, InMemoryAttachmentStorage::class);

    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $task = Task::factory()->for($project)->create();

    $response = $this->actingAs($user)->postJson(
        "/projects/{$project->slug}/tasks/{$task->id}/attachments",
        ['file' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf')]
    );

    /** @var InMemoryAttachmentStorage $storage */
    $storage = $this->app->make(AttachmentStorage::class);
    $path = $response->json('path');
    expect($storage->has($path))->toBeTrue();

    $this->actingAs($user)
        ->deleteJson("/projects/{$project->slug}/tasks/{$task->id}/attachments/{$response->json('id')}")
        ->assertNoContent();

    expect($storage->has($path))->toBeFalse();
});
