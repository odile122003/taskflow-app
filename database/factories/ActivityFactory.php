<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'causer_id' => User::factory(),
            'subject_type' => Task::class,
            'subject_id' => Task::factory(),
            'description' => 'a changé le statut de la tâche',
            'properties' => [],
        ];
    }
}
