<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'assignee_id' => null,
            'parent_id' => null,
            'title' => fake()->sentence(4),
            'status' => 'todo',
            'priority' => 'normal',
            'due_date' => fake()->optional()->dateTimeBetween('now', '+2 months'),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'in_progress']);
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'done']);
    }

    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => ['priority' => 'high']);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => ['assignee_id' => $user->id]);
    }
}
