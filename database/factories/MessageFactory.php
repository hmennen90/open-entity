<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role' => fake()->randomElement(['user', 'entity']),
            'content' => fake()->sentence(),
            'metadata' => [],
        ];
    }

    public function fromUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'user',
        ]);
    }

    public function fromEntity(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'entity',
        ]);
    }
}
