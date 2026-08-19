<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'commentable_type' => Task::class,
            'commentable_id' => Task::factory(),
            'body' => fake()->paragraph(),
        ];
    }

    public function on(Model $commentable): static
    {
        return $this->state(fn (array $attributes) => [
            'commentable_type' => $commentable::class,
            'commentable_id' => $commentable->id,
        ]);
    }
}
