<?php

namespace Database\Factories;

use App\Models\Goal;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoalFactory extends Factory
{
    protected $model = Goal::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'motivation' => fake()->sentence(),
            'type' => fake()->randomElement(['curiosity', 'social', 'learning', 'creative', 'self-improvement']),
            'status' => fake()->randomElement(['active', 'paused', 'completed', 'abandoned']),
            'progress' => fake()->numberBetween(0, 100),
            'priority' => fake()->randomFloat(2, 0, 1),
            'progress_notes' => [
                [
                    'date' => now()->toIso8601String(),
                    'note' => 'Goal created',
                ],
            ],
            'origin' => fake()->randomElement(['self', 'suggested', 'derived']),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'progress' => 100,
            'completed_at' => now(),
        ]);
    }

    public function learning(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'learning',
        ]);
    }

    public function curiosity(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'curiosity',
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 0.9,
        ]);
    }

    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 0.1,
        ]);
    }
}
