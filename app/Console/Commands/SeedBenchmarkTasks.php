<?php

namespace App\Console\Commands;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Outil ponctuel pour l'exercice du Module 12 (performance) : générer un
 * grand volume de tâches sur un seul projet pour mesurer, puis optimiser,
 * la liste paginée. INSERT en masse via DB::table() plutôt que
 * Task::factory()->create() en boucle : pas de modèle Eloquent hydraté,
 * pas d'observer déclenché (TaskObserver tournerait 500 000 fois pour
 * rien) — juste des lignes SQL.
 */
class SeedBenchmarkTasks extends Command
{
    protected $signature = 'taskflow:seed-benchmark {--total=500000}';

    protected $description = 'Génère un grand nombre de tâches sur un projet dédié pour mesurer la pagination';

    public function handle(): int
    {
        $team = Team::firstOrCreate(['name' => 'Equipe Benchmark'], ['slug' => 'equipe-benchmark']);

        $owner = User::firstOrCreate(
            ['email' => 'benchmark@test.local'],
            ['name' => 'Benchmark', 'password' => bcrypt('password')]
        );

        if (! $team->users()->where('user_id', $owner->id)->exists()) {
            $team->users()->attach($owner, ['role' => TeamRole::Owner->value]);
        }

        $project = Project::firstOrCreate(
            ['team_id' => $team->id, 'name' => 'Projet Benchmark 500k'],
            ['is_archived' => false, 'slug' => 'projet-benchmark-500k']
        );

        $target = (int) $this->option('total');
        $existing = DB::table('tasks')->where('project_id', $project->id)->count();
        $remaining = $target - $existing;

        $this->info("Deja present : {$existing}");

        if ($remaining <= 0) {
            $this->info('Deja au niveau cible.');

            return self::SUCCESS;
        }

        $chunkSize = 5000;
        $statuses = ['todo', 'in_progress', 'done'];
        $now = now();
        $bar = $this->output->createProgressBar($remaining);

        for ($offset = 0; $offset < $remaining; $offset += $chunkSize) {
            $count = min($chunkSize, $remaining - $offset);
            $rows = [];

            for ($i = 0; $i < $count; $i++) {
                $n = $existing + $offset + $i;
                $rows[] = [
                    'project_id' => $project->id,
                    'title' => 'Tache de benchmark #'.$n,
                    'status' => $statuses[$n % 3],
                    'priority' => 'normal',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('tasks')->insert($rows);
            $bar->advance($count);
        }

        $bar->finish();
        $this->newLine();

        $final = DB::table('tasks')->where('project_id', $project->id)->count();
        $this->info("Total pour le projet {$project->slug} : {$final}");

        return self::SUCCESS;
    }
}
