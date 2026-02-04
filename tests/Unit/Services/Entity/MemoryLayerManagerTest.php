<?php

namespace Tests\Unit\Services\Entity;

use App\Models\Memory;
use App\Models\MemorySummary;
use App\Services\Entity\MemoryLayerManager;
use App\Services\Entity\MemoryService;
use App\Services\Entity\PersonalityService;
use App\Services\Entity\SemanticMemoryService;
use App\Services\Entity\WorkingMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MemoryLayerManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_build_think_context_includes_all_layers(): void
    {
        $personalityService = Mockery::mock(PersonalityService::class);
        $personalityService->shouldReceive('toPrompt')
            ->once()
            ->andReturn('I am Nova, a curious entity.');

        $workingMemoryService = Mockery::mock(WorkingMemoryService::class);
        $workingMemoryService->shouldReceive('toPromptContext')
            ->once()
            ->andReturn('Current focus: Testing');

        $semanticMemoryService = Mockery::mock(SemanticMemoryService::class);
        $semanticMemoryService->shouldReceive('search')
            ->andReturn(collect());

        $memoryService = new MemoryService();

        $manager = new MemoryLayerManager(
            $personalityService,
            $semanticMemoryService,
            $memoryService,
            $workingMemoryService
        );

        $context = $manager->buildThinkContext('Test situation', 'en');

        $this->assertStringContainsString('I am Nova', $context);
        $this->assertStringContainsString('Current focus: Testing', $context);
    }

    public function test_get_by_layer_returns_correct_memories(): void
    {
        Memory::factory()->create(['layer' => 'episodic', 'is_consolidated' => false]);
        Memory::factory()->create(['layer' => 'semantic', 'is_consolidated' => false]);
        Memory::factory()->create(['layer' => 'episodic', 'is_consolidated' => true]);

        $personalityService = Mockery::mock(PersonalityService::class);
        $semanticMemoryService = Mockery::mock(SemanticMemoryService::class);
        $memoryService = new MemoryService();
        $workingMemoryService = Mockery::mock(WorkingMemoryService::class);

        $manager = new MemoryLayerManager(
            $personalityService,
            $semanticMemoryService,
            $memoryService,
            $workingMemoryService
        );

        $episodic = $manager->getByLayer('episodic');
        $semantic = $manager->getByLayer('semantic');

        $this->assertCount(1, $episodic);
        $this->assertCount(1, $semantic);
    }

    public function test_route_to_layer_routes_experience_to_episodic(): void
    {
        $personalityService = Mockery::mock(PersonalityService::class);
        $memoryService = Mockery::mock(MemoryService::class);
        $workingMemoryService = Mockery::mock(WorkingMemoryService::class);

        $semanticMemoryService = Mockery::mock(SemanticMemoryService::class);
        $semanticMemoryService->shouldReceive('createWithEmbedding')
            ->once()
            ->withArgs(function ($data) {
                return $data['layer'] === 'episodic';
            })
            ->andReturn(Memory::factory()->make(['layer' => 'episodic']));

        $manager = new MemoryLayerManager(
            $personalityService,
            $semanticMemoryService,
            $memoryService,
            $workingMemoryService
        );

        $memory = $manager->routeToLayer([
            'type' => 'experience',
            'content' => 'Test experience',
        ]);

        $this->assertEquals('episodic', $memory->layer);
    }

    public function test_route_to_layer_routes_learned_to_semantic(): void
    {
        $personalityService = Mockery::mock(PersonalityService::class);
        $memoryService = Mockery::mock(MemoryService::class);
        $workingMemoryService = Mockery::mock(WorkingMemoryService::class);

        $semanticMemoryService = Mockery::mock(SemanticMemoryService::class);
        $semanticMemoryService->shouldReceive('createWithEmbedding')
            ->once()
            ->withArgs(function ($data) {
                return $data['layer'] === 'semantic';
            })
            ->andReturn(Memory::factory()->make(['layer' => 'semantic']));

        $manager = new MemoryLayerManager(
            $personalityService,
            $semanticMemoryService,
            $memoryService,
            $workingMemoryService
        );

        $memory = $manager->routeToLayer([
            'type' => 'learned',
            'content' => 'PHP is a server-side language',
        ]);

        $this->assertEquals('semantic', $memory->layer);
    }

    public function test_get_core_identity_returns_personality_data(): void
    {
        $personalityData = [
            'name' => 'Nova',
            'core_values' => ['curiosity', 'honesty'],
            'traits' => ['openness' => 0.9],
        ];

        $personalityService = Mockery::mock(PersonalityService::class);
        $personalityService->shouldReceive('get')
            ->once()
            ->andReturn($personalityData);
        $personalityService->shouldReceive('getName')
            ->once()
            ->andReturn('Nova');
        $personalityService->shouldReceive('getCoreValues')
            ->once()
            ->andReturn(['curiosity', 'honesty']);
        $personalityService->shouldReceive('getTraits')
            ->once()
            ->andReturn(['openness' => 0.9]);

        $semanticMemoryService = Mockery::mock(SemanticMemoryService::class);
        $memoryService = new MemoryService();
        $workingMemoryService = Mockery::mock(WorkingMemoryService::class);

        $manager = new MemoryLayerManager(
            $personalityService,
            $semanticMemoryService,
            $memoryService,
            $workingMemoryService
        );

        $identity = $manager->getCoreIdentity('en');

        $this->assertArrayHasKey('personality', $identity);
        $this->assertArrayHasKey('name', $identity);
        $this->assertEquals('Nova', $identity['name']);
    }

    public function test_build_think_context_includes_memory_summaries(): void
    {
        // Create a memory summary
        MemorySummary::create([
            'period_start' => now()->subDays(1),
            'period_end' => now()->subDays(1),
            'period_type' => 'daily',
            'summary' => 'Yesterday I learned about embeddings',
            'source_memory_count' => 5,
        ]);

        $personalityService = Mockery::mock(PersonalityService::class);
        $personalityService->shouldReceive('toPrompt')
            ->andReturn('I am Nova.');

        $workingMemoryService = Mockery::mock(WorkingMemoryService::class);
        $workingMemoryService->shouldReceive('toPromptContext')
            ->andReturn('');

        $semanticMemoryService = Mockery::mock(SemanticMemoryService::class);
        $semanticMemoryService->shouldReceive('search')
            ->andReturn(collect());

        $memoryService = new MemoryService();

        $manager = new MemoryLayerManager(
            $personalityService,
            $semanticMemoryService,
            $memoryService,
            $workingMemoryService
        );

        $context = $manager->buildThinkContext('', 'en');

        $this->assertStringContainsString('Learned knowledge', $context);
        $this->assertStringContainsString('Summaries', $context);
        $this->assertStringContainsString('learned about embeddings', $context);
    }
}
