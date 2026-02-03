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
            'type' => fake()->randomElement(['short_term', 'long_term', 'ongoing']),
            'status' => fake()->randomElement(['active', 'paused', 'completed', 'abandoned']),
            'progress' => fake()->numberBetween(0, 100),
            'priority' => fake()->numberBetween(1, 5),
            'context' => [],
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
        ]);
    }

    public function shortTerm(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'short_term',
        ]);
    }

    public function longTerm(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'long_term',
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 1,
        ]);
    }
}
