<?php

use App\Enums\TeamRole;
use App\Mail\WeeklyReportMail;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Mail;

it('emails the owner and admin with real weekly stats, but not a plain member', function () {
    // freezeTime() : "cette semaine" doit désigner une plage fixe pendant tout
    // le test, sinon une exécution à cheval sur minuit dimanche/lundi rendrait
    // le test aléatoire.
    $this->freezeTime();
    Mail::fake();

    $project = Project::factory()->create();
    $owner = memberOf($project->team, TeamRole::Owner);
    $admin = memberOf($project->team, TeamRole::Admin);
    $member = memberOf($project->team, TeamRole::Member);

    Task::factory()->for($project)->create(['status' => 'done', 'completed_at' => now()->startOfWeek()->addDay()]);
    Task::factory()->for($project)->create(['status' => 'done', 'completed_at' => now()->subWeeks(2)]); // hors de la semaine

    $this->artisan('taskflow:send-weekly-report')->assertExitCode(0);

    Mail::assertQueued(WeeklyReportMail::class, fn (WeeklyReportMail $mail) => $mail->team->is($project->team)
        && $mail->stats['completed'] === 1);

    // Exactement 2 e-mails : Owner + Admin, jamais le simple Member.
    Mail::assertQueuedCount(2);
});
