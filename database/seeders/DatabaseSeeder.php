<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Pas de WithoutModelEvents ici : DemoSeeder compte sur TaskObserver (déclenché
    // par un vrai `update()`, pas par `create()`) pour renseigner `completed_at`
    // quand une tâche passe à "done" — désactiver les événements de modèle le
    // laisserait silencieusement à null. Détail complet dans CONCEPTS.md (Module 4).

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(DemoSeeder::class);
    }
}
