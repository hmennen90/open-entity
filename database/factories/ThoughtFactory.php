<?php

namespace Database\Factories;

use App\Models\Thought;
use Illuminate\Database\Eloquent\Factories\Factory;

class ThoughtFactory extends Factory
{
    protected $model = Thought::class;

    public function definition(): array
    {
        return [
            'content' => fake()->sentence(),
            'type' => fake()->randomElement(['observation', 'reflection', 'curiosity', 'emotion', 'decision']),
            'trigger' => fake()->randomElement(['think_loop', 'conversation', 'observation', 'internal']),
            'intensity' => fake()->randomFloat(2, 0.1, 1.0),
            'context' => [],
            'led_to_action' => false,
            'action_taken' => null,
        ];
    }

    public function observation(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'observation',
        ]);
    }

    public function reflection(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'reflection',
        ]);
    }

    public function decision(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'decision',
        ]);
    }

    public function highIntensity(): static
    {
        return $this->state(fn (array $attributes) => [
            'intensity' => fake()->randomFloat(2, 0.8, 1.0),
        ]);
    }
}
