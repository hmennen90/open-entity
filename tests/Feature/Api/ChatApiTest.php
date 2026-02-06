<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // Events faken um Broadcast-Fehler zu vermeiden
        Event::fake();

        $this->createTestPersonality();
    }

    #[Test]
    public function it_lists_conversations(): void
    {
        Conversation::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/chat/conversations');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_creates_a_conversation(): void
    {
        $response = $this->postJson('/api/v1/chat/conversations', [
            'participant' => 'TestUser',
            'channel' => 'web',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'participant',
                    'channel',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('conversations', [
            'participant' => 'TestUser',
            'channel' => 'web',
        ]);
    }

    #[Test]
    public function it_shows_a_conversation_with_messages(): void
    {
        $conversation = Conversation::factory()->create();
        Message::factory()->count(5)->create([
            'conversation_id' => $conversation->id,
        ]);

        $response = $this->getJson("/api/v1/chat/conversations/{$conversation->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'participant',
                    'messages',
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_for_unknown_conversation(): void
    {
        $response = $this->getJson('/api/v1/chat/conversations/999');

        $response->assertStatus(404);
    }

    #[Test]
    public function it_validates_conversation_creation(): void
    {
        $response = $this->postJson('/api/v1/chat/conversations', [
            // Missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['participant']);
    }

    private function createTestPersonality(): void
    {
        $basePath = config('entity.storage_path') . '/mind';

        file_put_contents(
            $basePath . '/personality.json',
            json_encode([
                'name' => 'TestEntity',
                'traits' => ['curiosity' => 0.8],
            ], JSON_PRETTY_PRINT)
        );

        file_put_contents(
            $basePath . '/interests.json',
            json_encode(['current' => []], JSON_PRETTY_PRINT)
        );

        file_put_contents(
            $basePath . '/opinions.json',
            json_encode(['opinions' => []], JSON_PRETTY_PRINT)
        );
    }
}
