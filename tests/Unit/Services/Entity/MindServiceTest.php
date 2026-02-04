<?php

namespace Tests\Unit\Services\Entity;

use App\Services\Entity\MindService;
use App\Services\Entity\PersonalityService;
use App\Services\Entity\MemoryService;
use App\Models\Thought;
use App\Models\Goal;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class MindServiceTest extends TestCase
{
    use DatabaseMigrations;

    private MindService $mindService;

    protected function setUp(): void
    {
        parent::setUp();

        // Test-Persönlichkeit erstellen
        $this->createTestPersonality();

        $personalityService = new PersonalityService();
        $memoryService = new MemoryService();
        $this->mindService = new MindService($personalityService, $memoryService);
    }

    /** @test */
    public function it_creates_thoughts(): void
    {
        $thought = $this->mindService->createThought([
            'content' => 'Test thought content',
            'type' => 'observation',
            'trigger' => 'test',
            'intensity' => 0.7,
        ]);

        $this->assertInstanceOf(Thought::class, $thought);
        $this->assertEquals('Test thought content', $thought->content);
        $this->assertEquals('observation', $thought->type);
        $this->assertEquals(0.7, $thought->intensity);
        $this->assertDatabaseHas('thoughts', ['id' => $thought->id]);
    }

    /** @test */
    public function it_retrieves_recent_thoughts(): void
    {
        // Erstelle mehrere Gedanken
        for ($i = 1; $i <= 5; $i++) {
            $this->mindService->createThought([
                'content' => "Thought {$i}",
                'type' => 'observation',
                'intensity' => 0.5,
            ]);
        }

        $recent = $this->mindService->getRecentThoughts(3);

        $this->assertCount(3, $recent);
    }

    /** @test */
    public function it_loads_personality(): void
    {
        $personality = $this->mindService->getPersonality();

        $this->assertIsArray($personality);
        $this->assertArrayHasKey('name', $personality);
        $this->assertArrayHasKey('traits', $personality);
    }

    /** @test */
    public function it_estimates_mood_from_recent_thoughts(): void
    {
        // Erstelle positive Gedanken
        $this->mindService->createThought([
            'content' => 'Ich bin glücklich',
            'type' => 'emotion',
            'intensity' => 0.9,
        ]);

        $mood = $this->mindService->estimateMood();

        $this->assertIsArray($mood);
        $this->assertArrayHasKey('state', $mood);
        $this->assertArrayHasKey('valence', $mood);
        $this->assertArrayHasKey('energy', $mood);
    }

    /** @test */
    public function it_generates_think_context(): void
    {
        $this->mindService->createThought([
            'content' => 'A recent thought',
            'type' => 'observation',
            'intensity' => 0.5,
        ]);

        $context = $this->mindService->toThinkContext();

        $this->assertIsString($context);
        // Default language is English when no USER.md exists
        $this->assertStringContainsString('WHO I AM', $context);
        $this->assertStringContainsString('MY MEMORIES', $context);
    }

    /** @test */
    public function it_retrieves_active_goals(): void
    {
        Goal::create([
            'title' => 'Test Goal',
            'description' => 'A test goal',
            'type' => 'short_term',
            'status' => 'active',
            'progress' => 50,
            'priority' => 1,
        ]);

        $goals = $this->mindService->getActiveGoals();

        $this->assertCount(1, $goals);
        $this->assertEquals('Test Goal', $goals->first()->title);
    }

    /** @test */
    public function it_creates_thoughts_with_context(): void
    {
        $thought = $this->mindService->createThought([
            'content' => 'Contextual thought',
            'type' => 'decision',
            'trigger' => 'user_input',
            'context' => [
                'source' => 'chat',
                'user' => 'hendrik',
            ],
            'intensity' => 0.8,
        ]);

        $this->assertEquals(['source' => 'chat', 'user' => 'hendrik'], $thought->context);
    }

    /** @test */
    public function thought_types_are_validated(): void
    {
        $validTypes = ['observation', 'reflection', 'curiosity', 'emotion', 'decision'];

        foreach ($validTypes as $type) {
            $thought = $this->mindService->createThought([
                'content' => "Thought of type {$type}",
                'type' => $type,
                'intensity' => 0.5,
            ]);

            $this->assertEquals($type, $thought->type);
        }
    }

    private function createTestPersonality(): void
    {
        $basePath = config('entity.storage_path') . '/mind';

        $personality = [
            'name' => 'TestEntity',
            'traits' => [
                'curiosity' => 0.8,
                'openness' => 0.7,
                'empathy' => 0.6,
                'playfulness' => 0.5,
                'introspection' => 0.7,
            ],
            'values' => ['honesty', 'growth'],
            'communication_style' => [
                'formality' => 0.5,
                'verbosity' => 0.6,
                'humor' => 0.4,
                'directness' => 0.7,
            ],
            'likes' => ['learning', 'exploring'],
            'dislikes' => ['repetition'],
        ];

        file_put_contents(
            $basePath . '/personality.json',
            json_encode($personality, JSON_PRETTY_PRINT)
        );

        $interests = [
            'current' => [
                ['topic' => 'testing', 'intensity' => 0.9],
            ],
        ];

        file_put_contents(
            $basePath . '/interests.json',
            json_encode($interests, JSON_PRETTY_PRINT)
        );

        file_put_contents(
            $basePath . '/opinions.json',
            json_encode(['opinions' => []], JSON_PRETTY_PRINT)
        );
    }
}
