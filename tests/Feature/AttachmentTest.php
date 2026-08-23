<?php

use App\Jobs\GenerateThumbnail;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('stores an uploaded image and dispatches a thumbnail job', function () {
    Storage::fake();
    Queue::fake();

    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $task = Task::factory()->for($project)->create();

    $response = $this->actingAs($user)->postJson(
        "/projects/{$project->slug}/tasks/{$task->id}/attachments",
        ['file' => UploadedFile::fake()->image('photo.jpg')]
    );

    $response->assertCreated();
    $path = $response->json('path');

    Storage::assertExists($path);
    Queue::assertPushed(GenerateThumbnail::class, fn (GenerateThumbnail $job) => $job->attachmentId === $response->json('id'));
});

it('does not dispatch a thumbnail job for a non-image attachment', function () {
    Storage::fake();
    Queue::fake();

    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $task = Task::factory()->for($project)->create();

    $this->actingAs($user)->postJson(
        "/projects/{$project->slug}/tasks/{$task->id}/attachments",
        ['file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')]
    )->assertCreated();

    Queue::assertNotPushed(GenerateThumbnail::class);
});

it('rejects a disallowed file type', function () {
    Storage::fake();

    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $task = Task::factory()->for($project)->create();

    $this->actingAs($user)->postJson(
        "/projects/{$project->slug}/tasks/{$task->id}/attachments",
        ['file' => UploadedFile::fake()->create('malware.exe', 10)]
    )->assertJsonValidationErrors('file');
});

it('lets a team member download an attachment but not an outsider', function () {
    Storage::fake();

    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $outsider = User::factory()->create();
    $task = Task::factory()->for($project)->create();

    Storage::put('attachments/'.$task->id.'/report.pdf', 'contenu du fichier');
    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'attachable_type' => Task::class,
        'attachable_id' => $task->id,
        'path' => 'attachments/'.$task->id.'/report.pdf',
        'original_name' => 'report.pdf',
    ]);

    $this->actingAs($user)
        ->get("/projects/{$project->slug}/tasks/{$task->id}/attachments/{$attachment->id}/download")
        ->assertOk();

    $this->actingAs($outsider)
        ->get("/projects/{$project->slug}/tasks/{$task->id}/attachments/{$attachment->id}/download")
        ->assertForbidden();
});

it('deletes the file from storage when an attachment is destroyed', function () {
    Storage::fake();

    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $task = Task::factory()->for($project)->create();

    Storage::put('attachments/'.$task->id.'/report.pdf', 'contenu du fichier');
    $attachment = Attachment::factory()->create([
        'user_id' => $user->id,
        'attachable_type' => Task::class,
        'attachable_id' => $task->id,
        'path' => 'attachments/'.$task->id.'/report.pdf',
    ]);

    $this->actingAs($user)
        ->deleteJson("/projects/{$project->slug}/tasks/{$task->id}/attachments/{$attachment->id}")
        ->assertNoContent();

    Storage::assertMissing($attachment->path);
    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
});
