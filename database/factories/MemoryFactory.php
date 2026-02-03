<?php

namespace Database\Factories;

use App\Models\Memory;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemoryFactory extends Factory
{
    protected $model = Memory::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['experience', 'learned', 'conversation', 'observation']),
            'content' => fake()->paragraph(),
            'importance' => fake()->randomFloat(2, 0.1, 1.0),
            'context' => [],
        ];
    }

    public function experience(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'experience',
        ]);
    }

    public function learned(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'learned',
        ]);
    }

    public function important(): static
    {
        return $this->state(fn (array $attributes) => [
            'importance' => fake()->randomFloat(2, 0.8, 1.0),
        ]);
    }
}
