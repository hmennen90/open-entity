<?php

namespace Tests\Unit\Services\Entity;

use App\Services\Entity\MemoryService;
use App\Models\Memory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private MemoryService $memoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->memoryService = new MemoryService();
    }

    /** @test */
    public function it_creates_memories(): void
    {
        $memory = $this->memoryService->create([
            'type' => 'experience',
            'content' => 'Learned something new',
            'importance' => 0.7,
        ]);

        $this->assertInstanceOf(Memory::class, $memory);
        $this->assertEquals('experience', $memory->type);
        $this->assertEquals('Learned something new', $memory->content);
        $this->assertEquals(0.7, $memory->importance);
        $this->assertDatabaseHas('memories', ['id' => $memory->id]);
    }

    /** @test */
    public function it_creates_memories_with_context(): void
    {
        $memory = $this->memoryService->create([
            'type' => 'conversation',
            'content' => 'Had a nice chat',
            'importance' => 0.5,
            'context' => [
                'participant' => 'hendrik',
                'channel' => 'chat',
            ],
        ]);

        $this->assertEquals([
            'participant' => 'hendrik',
            'channel' => 'chat',
        ], $memory->context);
    }

    /** @test */
    public function it_retrieves_recent_memories(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->memoryService->create([
                'type' => 'experience',
                'content' => "Memory {$i}",
                'importance' => 0.5,
            ]);
        }

        $recent = $this->memoryService->getRecent(5);

        $this->assertCount(5, $recent);
    }

    /** @test */
    public function it_filters_memories_by_type(): void
    {
        $this->memoryService->create([
            'type' => 'experience',
            'content' => 'An experience',
            'importance' => 0.5,
        ]);

        $this->memoryService->create([
            'type' => 'learned',
            'content' => 'Something learned',
            'importance' => 0.5,
        ]);

        $this->memoryService->create([
            'type' => 'conversation',
            'content' => 'A conversation',
            'importance' => 0.5,
        ]);

        $experiences = $this->memoryService->getByType('experience');
        $learned = $this->memoryService->getByType('learned');

        $this->assertCount(1, $experiences);
        $this->assertCount(1, $learned);
        $this->assertEquals('An experience', $experiences->first()->content);
    }

    /** @test */
    public function it_retrieves_important_memories(): void
    {
        $this->memoryService->create([
            'type' => 'experience',
            'content' => 'Minor memory',
            'importance' => 0.3,
        ]);

        $this->memoryService->create([
            'type' => 'experience',
            'content' => 'Important memory',
            'importance' => 0.9,
        ]);

        $this->memoryService->create([
            'type' => 'experience',
            'content' => 'Medium memory',
            'importance' => 0.6,
        ]);

        $important = $this->memoryService->getImportant(0.7);

        $this->assertCount(1, $important);
        $this->assertEquals('Important memory', $important->first()->content);
    }

    /** @test */
    public function it_searches_memories_by_content(): void
    {
        $this->memoryService->create([
            'type' => 'learned',
            'content' => 'PHP is a programming language',
            'importance' => 0.5,
        ]);

        $this->memoryService->create([
            'type' => 'learned',
            'content' => 'Laravel is a PHP framework',
            'importance' => 0.6,
        ]);

        $this->memoryService->create([
            'type' => 'experience',
            'content' => 'Had coffee today',
            'importance' => 0.2,
        ]);

        $results = $this->memoryService->search('PHP');

        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_creates_learned_memory(): void
    {
        $memory = $this->memoryService->createLearned(
            'Docker containers are isolated environments',
            ['source' => 'documentation']
        );

        $this->assertEquals('learned', $memory->type);
        $this->assertGreaterThanOrEqual(0.5, $memory->importance);
    }

    /** @test */
    public function it_creates_experience_memory(): void
    {
        $memory = $this->memoryService->createExperience(
            'Successfully deployed the application',
            ['action' => 'deploy']
        );

        $this->assertEquals('experience', $memory->type);
    }

    /** @test */
    public function memory_types_are_valid(): void
    {
        $validTypes = ['experience', 'learned', 'conversation', 'observation'];

        foreach ($validTypes as $type) {
            $memory = $this->memoryService->create([
                'type' => $type,
                'content' => "Memory of type {$type}",
                'importance' => 0.5,
            ]);

            $this->assertEquals($type, $memory->type);
        }
    }

    /** @test */
    public function it_handles_memory_decay(): void
    {
        // Erstelle alte Memory
        $oldMemory = Memory::create([
            'type' => 'experience',
            'content' => 'Old memory',
            'importance' => 0.5,
            'created_at' => now()->subDays(30),
        ]);

        // Erstelle neue Memory
        $newMemory = $this->memoryService->create([
            'type' => 'experience',
            'content' => 'New memory',
            'importance' => 0.5,
        ]);

        $recentFirst = $this->memoryService->getRecent(10);

        // Neueste zuerst
        $this->assertEquals($newMemory->id, $recentFirst->first()->id);
    }

    /** @test */
    public function it_formats_memories_for_context(): void
    {
        $this->memoryService->create([
            'type' => 'learned',
            'content' => 'Important lesson',
            'importance' => 0.8,
        ]);

        $context = $this->memoryService->toContextString(5);

        $this->assertIsString($context);
        $this->assertStringContainsString('Important lesson', $context);
    }
}
