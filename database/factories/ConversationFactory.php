<?php

namespace Database\Factories;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'participant' => fake()->name(),
            'participant_type' => 'human',
            'channel' => fake()->randomElement(['web', 'discord', 'moltbook']),
        ];
    }
}
