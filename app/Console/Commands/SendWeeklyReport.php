<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Mail\WeeklyReportMail;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendWeeklyReport extends Command
{
    /**
     * @var string
     */
    protected $signature = 'taskflow:send-weekly-report';

    /**
     * @var string
     */
    protected $description = 'Envoie à chaque équipe son rapport hebdomadaire (tâches terminées, projets actifs, meilleur contributeur)';

    public function handle(): int
    {
        $teams = Team::all();

        $bar = $this->output->createProgressBar($teams->count());
        $bar->start();

        foreach ($teams as $team) {
            $this->sendToTeam($team);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Rapport envoyé à {$teams->count()} équipe(s).");

        return self::SUCCESS;
    }

    private function sendToTeam(Team $team): void
    {
        $stats = $this->statsFor($team);

        $recipients = $team->users()
            ->wherePivotIn('role', [TeamRole::Owner->value, TeamRole::Admin->value])
            ->get();

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(new WeeklyReportMail($team, $stats));
        }
    }

    /**
     * @return array{completed: int, active_projects: int, top_contributor: ?string}
     */
    private function statsFor(Team $team): array
    {
        $week = [now()->startOfWeek(), now()->endOfWeek()];

        $completed = $team->tasks()
            ->where('status', TaskStatus::Done)
            ->whereBetween('completed_at', $week)
            ->count();

        $activeProjects = $team->projects()->where('is_archived', false)->count();

        $topContributor = User::query()
            ->whereHas('teams', fn ($query) => $query->where('teams.id', $team->id))
            ->withCount(['assignedTasks as completed_this_week' => function ($query) use ($week) {
                $query->where('status', TaskStatus::Done)->whereBetween('completed_at', $week);
            }])
            ->orderByDesc('completed_this_week')
            ->first();

        /** @var int $topContributorCompletedCount */
        $topContributorCompletedCount = $topContributor?->getAttribute('completed_this_week') ?? 0;

        return [
            'completed' => $completed,
            'active_projects' => $activeProjects,
            'top_contributor' => $topContributorCompletedCount > 0
                ? $topContributor->name
                : null,
        ];
    }
}
