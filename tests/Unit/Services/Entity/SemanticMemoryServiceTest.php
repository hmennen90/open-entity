<?php

namespace Tests\Unit\Services\Entity;

use App\Models\Memory;
use App\Services\Embedding\EmbeddingService;
use App\Services\Entity\MemoryService;
use App\Services\Entity\SemanticMemoryService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Mockery;
use Tests\TestCase;

class SemanticMemoryServiceTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_search_with_embeddings_returns_similar_memories(): void
    {
        // Create memories with embeddings
        $memory1 = Memory::factory()->create([
            'content' => 'I love programming in PHP',
            'embedding' => pack('f*', 0.9, 0.1, 0.0),
            'embedding_dimensions' => 3,
            'embedded_at' => now(),
        ]);

        $memory2 = Memory::factory()->create([
            'content' => 'I enjoy cooking Italian food',
            'embedding' => pack('f*', 0.0, 0.1, 0.9),
            'embedding_dimensions' => 3,
            'embedded_at' => now(),
        ]);

        $queryEmbedding = [0.85, 0.15, 0.0]; // Similar to programming memory

        $embeddingService = Mockery::mock(EmbeddingService::class);
        $embeddingService->shouldReceive('embed')
            ->with('programming')
            ->once()
            ->andReturn($queryEmbedding);

        $embeddingService->shouldReceive('findSimilar')
            ->once()
            ->andReturnUsing(function ($query, $candidates, $limit, $threshold) use ($memory1) {
                return collect([$memory1]);
            });

        $memoryService = new MemoryService();
        $service = new SemanticMemoryService($embeddingService, $memoryService);

        $results = $service->search('programming', 10, 0.5);

        $this->assertCount(1, $results);
        $this->assertEquals($memory1->id, $results->first()->id);
    }

    public function test_search_falls_back_to_keyword_when_no_embeddings(): void
    {
        // Create memory without embedding
        $memory = Memory::factory()->create([
            'content' => 'I love programming in PHP',
            'embedding' => null,
            'embedded_at' => null,
        ]);

        // The search method always generates query embedding first
        $embeddingService = Mockery::mock(EmbeddingService::class);
        $embeddingService->shouldReceive('embed')
            ->once()
            ->andReturn([0.1, 0.2, 0.3]);

        $memoryService = new MemoryService();
        $service = new SemanticMemoryService($embeddingService, $memoryService);

        $results = $service->search('PHP', 10, 0.5);

        $this->assertCount(1, $results);
        $this->assertEquals($memory->id, $results->first()->id);
    }

    public function test_generate_embedding_stores_embedding_on_memory(): void
    {
        $memory = Memory::factory()->create([
            'content' => 'Test memory content',
            'summary' => 'Test summary',
            'type' => 'experience',
            'embedding' => null,
        ]);

        $expectedEmbedding = array_fill(0, 768, 0.1);

        $embeddingService = Mockery::mock(EmbeddingService::class);
        $embeddingService->shouldReceive('embed')
            ->once()
            ->andReturn($expectedEmbedding);
        $embeddingService->shouldReceive('encodeToBinary')
            ->once()
            ->andReturn(pack('f*', ...$expectedEmbedding));
        $embeddingService->shouldReceive('getModelName')
            ->andReturn('nomic-embed-text');

        $memoryService = new MemoryService();
        $service = new SemanticMemoryService($embeddingService, $memoryService);

        $service->generateEmbedding($memory);

        $memory->refresh();

        $this->assertNotNull($memory->embedding);
        $this->assertNotNull($memory->embedded_at);
        $this->assertEquals(768, $memory->embedding_dimensions);
        $this->assertEquals('nomic-embed-text', $memory->embedding_model);
    }

    public function test_get_stats_returns_correct_counts(): void
    {
        // Create some memories with and without embeddings
        Memory::factory()->count(3)->create([
            'embedding' => pack('f*', 0.1, 0.2),
            'embedded_at' => now(),
        ]);

        Memory::factory()->count(2)->create([
            'embedding' => null,
            'embedded_at' => null,
        ]);

        $embeddingService = Mockery::mock(EmbeddingService::class);
        $embeddingService->shouldReceive('getModelName')
            ->andReturn('nomic-embed-text');
        $embeddingService->shouldReceive('getDimensions')
            ->andReturn(768);

        $memoryService = new MemoryService();
        $service = new SemanticMemoryService($embeddingService, $memoryService);

        $stats = $service->getStats();

        $this->assertEquals(5, $stats['total_memories']);
        $this->assertEquals(3, $stats['embedded']);
        $this->assertEquals(2, $stats['pending']);
        $this->assertEquals(60.0, $stats['coverage_percent']);
    }

    public function test_get_contextual_memories_respects_token_budget(): void
    {
        // Create memories with short content
        $memory1 = Memory::factory()->create([
            'content' => 'Short memory',
            'summary' => 'Short',
            'embedding' => pack('f*', 0.9, 0.1),
            'embedded_at' => now(),
        ]);

        // Create memory with very long content
        $memory2 = Memory::factory()->create([
            'content' => str_repeat('Long content ', 1000),
            'summary' => null,
            'embedding' => pack('f*', 0.85, 0.15),
            'embedded_at' => now(),
        ]);

        $embeddingService = Mockery::mock(EmbeddingService::class);
        $embeddingService->shouldReceive('embed')
            ->once()
            ->andReturn([0.9, 0.1]);

        $embeddingService->shouldReceive('findSimilar')
            ->once()
            ->andReturnUsing(function () use ($memory1, $memory2) {
                return collect([$memory1, $memory2]);
            });

        $memoryService = new MemoryService();
        $service = new SemanticMemoryService($embeddingService, $memoryService);

        // Very small token budget - should only fit short memory
        $results = $service->getContextualMemories('test context', 20);

        $this->assertCount(1, $results);
        $this->assertEquals($memory1->id, $results->first()->id);
    }

    public function test_get_by_layer_filters_correctly(): void
    {
        Memory::factory()->create([
            'layer' => 'episodic',
            'is_consolidated' => false,
        ]);

        Memory::factory()->create([
            'layer' => 'semantic',
            'is_consolidated' => false,
        ]);

        Memory::factory()->create([
            'layer' => 'episodic',
            'is_consolidated' => true, // Should be excluded
        ]);

        $embeddingService = Mockery::mock(EmbeddingService::class);
        $embeddingService->shouldReceive('isAvailable')
            ->andReturn(false);

        $memoryService = new MemoryService();
        $service = new SemanticMemoryService($embeddingService, $memoryService);

        $episodic = $service->getByLayer('episodic');
        $semantic = $service->getByLayer('semantic');

        $this->assertCount(1, $episodic); // Only unconsolidated
        $this->assertCount(1, $semantic);
    }
}
