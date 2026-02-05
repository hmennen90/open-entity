<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EntityCommandsTest extends TestCase
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
    public function entity_wake_command_wakes_entity(): void
    {
        Cache::put('entity:status', 'sleeping', 86400);

        $this->artisan('entity:wake')
            ->expectsOutput('Entity is now awake')
            ->assertExitCode(0);

        $this->assertEquals('awake', Cache::get('entity:status'));
    }

    /** @test */
    public function entity_sleep_command_puts_entity_to_sleep(): void
    {
        Cache::put('entity:status', 'awake', 86400);

        $this->artisan('entity:sleep')
            ->expectsOutput('Entity is now sleeping')
            ->assertExitCode(0);

        $this->assertEquals('sleeping', Cache::get('entity:status'));
    }

    /** @test */
    public function entity_status_command_shows_status(): void
    {
        Cache::put('entity:status', 'awake', 86400);
        Cache::put('entity:started_at', now()->subMinutes(5), 86400);

        $this->artisan('entity:status')
            ->expectsOutputToContain('Status')
            ->assertExitCode(0);
    }

    /** @test */
    public function entity_think_command_dreams_when_sleeping(): void
    {
        Cache::put('entity:status', 'sleeping', 86400);

        $this->artisan('entity:think')
            ->expectsOutputToContain('sleeping')
            ->expectsOutputToContain('dream')
            ->assertExitCode(0);
    }

    /** @test */
    public function entity_think_command_runs_single_cycle(): void
    {
        Cache::put('entity:status', 'awake', 86400);

        // Dieser Test prüft nur dass der Command ohne Fehler startet
        $this->artisan('entity:think')
            ->expectsOutput('Starting single think cycle...')
            ->assertExitCode(0);
    }

    private function createTestPersonality(): void
    {
        $basePath = config('entity.storage_path') . '/mind';

        file_put_contents(
            $basePath . '/personality.json',
            json_encode([
                'name' => 'OpenEntity',
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
