<?php

namespace Tests\Feature\Api;

use App\Services\Entity\EntityService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EntityApiTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // Events faken um Broadcast-Fehler zu vermeiden
        Event::fake();

        $this->createTestPersonality();
    }

    /** @test */
    public function it_returns_entity_status(): void
    {
        Cache::put('entity:status', 'sleeping', 86400);

        $response = $this->getJson('/api/v1/entity/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'name',
                'uptime',
                'last_thought_at',
            ])
            ->assertJson([
                'status' => 'sleeping',
            ]);
    }

    /** @test */
    public function it_wakes_the_entity(): void
    {
        Cache::put('entity:status', 'sleeping', 86400);

        $response = $this->postJson('/api/v1/entity/wake');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'awake',
            ]);

        $this->assertEquals('awake', Cache::get('entity:status'));
    }

    /** @test */
    public function it_puts_entity_to_sleep(): void
    {
        Cache::put('entity:status', 'awake', 86400);

        $response = $this->postJson('/api/v1/entity/sleep');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'sleeping',
            ]);

        $this->assertEquals('sleeping', Cache::get('entity:status'));
    }

    /** @test */
    public function it_returns_entity_personality(): void
    {
        $response = $this->getJson('/api/v1/entity/personality');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'name',
                'traits',
            ]);
    }

    /** @test */
    public function it_returns_entity_mood(): void
    {
        $response = $this->getJson('/api/v1/entity/mood');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'state',
                'valence',
                'energy',
            ]);
    }

    /** @test */
    public function it_returns_available_tools(): void
    {
        $response = $this->getJson('/api/v1/entity/tools');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tools',
                'failed_tools',
            ]);
    }

    private function createTestPersonality(): void
    {
        $basePath = config('entity.storage_path') . '/mind';

        file_put_contents(
            $basePath . '/personality.json',
            json_encode([
                'name' => 'TestEntity',
                'traits' => ['curiosity' => 0.8],
                'values' => ['honesty'],
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
