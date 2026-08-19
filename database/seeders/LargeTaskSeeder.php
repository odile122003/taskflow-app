<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Exercice module 3 : 50 000 tâches, insérées en masse (pas via Eloquent::create()
 * en boucle, beaucoup trop lent) pour observer l'effet d'un index composite sous
 * volumétrie réaliste. Ne fait pas partie du seed de démo courant (DatabaseSeeder) :
 * s'exécute à la demande via `php artisan db:seed --class=LargeTaskSeeder`.
 */
class LargeTaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::query()->first() ?? Project::factory()->create();

        $statuses = ['todo', 'in_progress', 'done'];
        $total = 50_000;
        $chunkSize = 1_000;
        $now = now();

        for ($inserted = 0; $inserted < $total; $inserted += $chunkSize) {
            $rows = [];

            for ($i = 0; $i < $chunkSize; $i++) {
                $rows[] = [
                    'project_id' => $project->id,
                    'title' => 'Tâche de charge #'.($inserted + $i + 1),
                    'status' => $statuses[array_rand($statuses)],
                    'priority' => 'normal',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('tasks')->insert($rows);
        }

        $this->command?->info("50 000 tâches insérées pour le projet #{$project->id}.");
    }
}
