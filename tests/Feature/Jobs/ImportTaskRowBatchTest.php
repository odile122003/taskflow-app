<?php

use App\Jobs\ImportTaskRow;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;

function csvUploadFor(string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    file_put_contents($path, $content);

    return new UploadedFile($path, 'import.csv', 'text/csv', null, true);
}

it('dispatches one job per CSV row, without running them, when Bus is faked', function () {
    Bus::fake();

    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $csv = csvUploadFor("title,priority,due_date\nTâche un,normal,\nTâche deux,high,2026-09-01\n");

    $response = $this->actingAs($user)
        ->postJson("/projects/{$project->slug}/tasks/import", ['file' => $csv]);

    $response->assertAccepted()->assertJson(['total' => 2]);

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 2);
    expect($project->tasks()->count())->toBe(0); // Bus::fake() : rien n'a réellement tourné.
});

it('actually imports every valid row of the CSV', function () {
    // QUEUE_CONNECTION=sync (phpunit.xml) : chaque job du batch s'exécute
    // réellement, immédiatement, dans le même processus.
    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $csv = csvUploadFor("title,priority,due_date\nTâche une,normal,\nTâche deux,high,2026-09-01\n");

    $response = $this->actingAs($user)
        ->postJson("/projects/{$project->slug}/tasks/import", ['file' => $csv]);

    $response->assertAccepted();
    $batchId = $response->json('batch_id');

    $status = $this->actingAs($user)->getJson("/imports/{$batchId}")->json();

    expect($status['finished'])->toBeTrue()
        ->and($status['failed'])->toBe(0)
        ->and($project->tasks()->count())->toBe(2);
});

it('rejects a row with no title', function () {
    // Testé directement sur le job plutôt que via HTTP : avec le driver sync
    // (tests), une ValidationException levée dans un job d'un batch remonte
    // jusqu'à la réponse HTTP d'origine au lieu d'être comptée comme un échec
    // isolé du batch — comportement du driver sync, pas de Bus::batch()
    // lui-même (un vrai worker isolerait cet échec normalement).
    $project = Project::factory()->create();
    $job = new ImportTaskRow($project->id, ['title' => '', 'priority' => 'normal', 'due_date' => null]);

    expect(fn () => $job->handle())->toThrow(ValidationException::class);
    expect($project->tasks()->count())->toBe(0);
});

it('does not duplicate a task already imported successfully, if the same job runs again', function () {
    $project = Project::factory()->create();

    $job = new ImportTaskRow($project->id, ['title' => 'Idempotente', 'priority' => 'normal', 'due_date' => null]);
    $job->handle();
    $job->handle();

    expect($project->tasks()->where('title', 'Idempotente')->count())->toBe(1);
});
