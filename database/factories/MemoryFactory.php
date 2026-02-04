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
            'layer' => 'episodic',
            'content' => fake()->paragraph(),
            'importance' => fake()->randomFloat(2, 0.1, 1.0),
            'context' => [],
            'is_consolidated' => false,
        ];
    }

    public function experience(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'experience',
            'layer' => 'episodic',
        ]);
    }

    public function learned(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'learned',
            'layer' => 'semantic',
        ]);
    }

    public function important(): static
    {
        return $this->state(fn (array $attributes) => [
            'importance' => fake()->randomFloat(2, 0.8, 1.0),
        ]);
    }

    public function episodic(): static
    {
        return $this->state(fn (array $attributes) => [
            'layer' => 'episodic',
        ]);
    }

    public function semantic(): static
    {
        return $this->state(fn (array $attributes) => [
            'layer' => 'semantic',
        ]);
    }

    public function procedural(): static
    {
        return $this->state(fn (array $attributes) => [
            'layer' => 'procedural',
        ]);
    }

    public function withEmbedding(array $embedding = null): static
    {
        $embedding = $embedding ?? array_fill(0, 768, fake()->randomFloat(4, -1, 1));

        return $this->state(fn (array $attributes) => [
            'embedding' => pack('f*', ...$embedding),
            'embedding_dimensions' => count($embedding),
            'embedding_model' => 'nomic-embed-text',
            'embedded_at' => now(),
        ]);
    }

    public function consolidated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_consolidated' => true,
            'consolidated_at' => now(),
        ]);
    }
}
