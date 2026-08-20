<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Scopes\TeamScope;
use Illuminate\Console\Command;

class CleanupArchivedProjects extends Command
{
    /**
     * @var string
     */
    protected $signature = 'taskflow:cleanup-archived
        {--days=90 : Nombre de jours d\'archivage avant suppression définitive}
        {--force : Supprimer sans demander de confirmation}';

    /**
     * @var string
     */
    protected $description = 'Supprime définitivement les projets archivés depuis plus de N jours (et leurs tâches)';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $projects = Project::withoutGlobalScope(TeamScope::class)
            ->where('is_archived', true)
            ->where('archived_at', '<=', now()->subDays($days))
            ->withCount('tasks')
            ->get();

        if ($projects->isEmpty()) {
            $this->info("Aucun projet archivé depuis plus de {$days} jour(s).");

            return self::SUCCESS;
        }

        $this->table(
            ['Projet', 'Équipe', 'Archivé le', 'Tâches'],
            $projects->map(fn (Project $project) => [
                $project->name,
                $project->team->name,
                $project->archived_at?->format('d/m/Y'),
                (string) $project->getAttribute('tasks_count'),
            ])
        );

        if (! $this->option('force') && ! $this->confirm('Supprimer définitivement ces projets et leurs tâches ?')) {
            $this->comment('Annulé.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($projects->count());
        $bar->start();

        foreach ($projects as $project) {
            // cascadeOnDelete() sur tasks.project_id (Module 3) : supprimer le
            // projet suffit, les tâches partent avec lui au niveau base.
            $project->delete();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$projects->count()} projet(s) supprimé(s) définitivement.");

        return self::SUCCESS;
    }
}
