<?php

namespace Tests\Unit\Services\Entity;

use App\Models\Memory;
use App\Models\MemorySummary;
use App\Services\Embedding\EmbeddingService;
use App\Services\Entity\MemoryConsolidationService;
use App\Services\LLM\LLMService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MemoryConsolidationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_consolidate_daily_creates_summary(): void
    {
        // Create memories from yesterday
        $yesterday = Carbon::yesterday();

        Memory::factory()->count(3)->create([
            'content' => 'Test memory content',
            'type' => 'experience',
            'is_consolidated' => false,
            'created_at' => $yesterday,
        ]);

        $llmService = Mockery::mock(LLMService::class);
        $llmService->shouldReceive('generate')
            ->times(3) // themes, summary, insights
            ->andReturn('["learning", "coding"]', 'Summary of the day', 'Key insight');

        $embeddingService = Mockery::mock(EmbeddingService::class);
        $embeddingService->shouldReceive('embed')
            ->once()
            ->andReturn(array_fill(0, 768, 0.1));
        $embeddingService->shouldReceive('encodeToBinary')
            ->once()
            ->andReturn(pack('f*', ...array_fill(0, 768, 0.1)));
        $embeddingService->shouldReceive('getModelName')
            ->once()
            ->andReturn('nomic-embed-text');

        $service = new MemoryConsolidationService($llmService, $embeddingService);

        $summary = $service->consolidateDaily();

        $this->assertNotNull($summary);
        $this->assertEquals('daily', $summary->period_type);
        $this->assertEquals(3, $summary->source_memory_count);

        // Verify memories are marked as consolidated
        $this->assertEquals(3, Memory::where('is_consolidated', true)->count());
    }

    public function test_consolidate_skips_when_summary_exists(): void
    {
        $yesterday = Carbon::yesterday();

        // Create existing summary
        MemorySummary::create([
            'period_start' => $yesterday->toDateString(),
            'period_end' => $yesterday->toDateString(),
            'period_type' => 'daily',
            'summary' => 'Existing summary',
            'source_memory_count' => 5,
        ]);

        $llmService = Mockery::mock(LLMService::class);
        $llmService->shouldNotReceive('generate');

        $embeddingService = Mockery::mock(EmbeddingService::class);

        $service = new MemoryConsolidationService($llmService, $embeddingService);

        $summary = $service->consolidateDaily();

        $this->assertNotNull($summary);
        $this->assertEquals('Existing summary', $summary->summary);
    }

    public function test_consolidate_returns_null_when_no_memories(): void
    {
        $llmService = Mockery::mock(LLMService::class);
        $llmService->shouldNotReceive('generate');

        $embeddingService = Mockery::mock(EmbeddingService::class);

        $service = new MemoryConsolidationService($llmService, $embeddingService);

        $summary = $service->consolidateDaily();

        $this->assertNull($summary);
    }

    public function test_extract_themes_returns_array(): void
    {
        $memories = collect([
            Memory::factory()->make(['content' => 'Learning PHP']),
            Memory::factory()->make(['content' => 'Building APIs']),
        ]);

        $llmService = Mockery::mock(LLMService::class);
        $llmService->shouldReceive('generate')
            ->once()
            ->andReturn('["programming", "learning", "PHP"]');

        $embeddingService = Mockery::mock(EmbeddingService::class);

        $service = new MemoryConsolidationService($llmService, $embeddingService);

        $themes = $service->extractThemes($memories);

        $this->assertIsArray($themes);
        $this->assertContains('programming', $themes);
    }

    public function test_extract_themes_handles_llm_failure(): void
    {
        $memories = collect([
            Memory::factory()->make(['type' => 'experience', 'content' => 'Test']),
            Memory::factory()->make(['type' => 'learned', 'content' => 'Test 2']),
        ]);

        $llmService = Mockery::mock(LLMService::class);
        $llmService->shouldReceive('generate')
            ->once()
            ->andThrow(new \Exception('LLM failed'));

        $embeddingService = Mockery::mock(EmbeddingService::class);

        $service = new MemoryConsolidationService($llmService, $embeddingService);

        $themes = $service->extractThemes($memories);

        // Should fallback to memory types
        $this->assertContains('experience', $themes);
        $this->assertContains('learned', $themes);
    }

    public function test_generate_summary_produces_text(): void
    {
        $memories = collect([
            Memory::factory()->make([
                'content' => 'Had a great coding session',
                'created_at' => now(),
            ]),
        ]);

        $llmService = Mockery::mock(LLMService::class);
        $llmService->shouldReceive('generate')
            ->once()
            ->andReturn('Today was a productive day focused on coding.');

        $embeddingService = Mockery::mock(EmbeddingService::class);

        $service = new MemoryConsolidationService($llmService, $embeddingService);

        $summary = $service->generateSummary($memories);

        $this->assertStringContainsString('productive', $summary);
    }

    public function test_archive_old_memories_groups_by_week(): void
    {
        // Create old, low-importance memories
        $oldDate = Carbon::now()->subDays(40);

        Memory::factory()->count(5)->create([
            'importance' => 0.3,
            'is_consolidated' => false,
            'created_at' => $oldDate,
        ]);

        $llmService = Mockery::mock(LLMService::class);
        $llmService->shouldReceive('generate')
            ->andReturn('["theme"]', 'Weekly summary', 'Insight');

        $embeddingService = Mockery::mock(EmbeddingService::class);
        $embeddingService->shouldReceive('embed')
            ->andReturn(array_fill(0, 768, 0.1));
        $embeddingService->shouldReceive('encodeToBinary')
            ->andReturn(pack('f*', ...array_fill(0, 768, 0.1)));
        $embeddingService->shouldReceive('getModelName')
            ->andReturn('nomic-embed-text');

        $service = new MemoryConsolidationService($llmService, $embeddingService);

        $archived = $service->archiveOldMemories(30);

        $this->assertEquals(5, $archived);

        // Should have created a weekly summary
        $this->assertEquals(1, MemorySummary::where('period_type', 'weekly')->count());
    }

    public function test_get_stats_returns_correct_data(): void
    {
        Memory::factory()->count(5)->create(['is_consolidated' => false]);
        Memory::factory()->count(3)->create(['is_consolidated' => true]);

        MemorySummary::create([
            'period_start' => now()->subDay(),
            'period_end' => now()->subDay(),
            'period_type' => 'daily',
            'summary' => 'Test',
            'source_memory_count' => 3,
        ]);

        $llmService = Mockery::mock(LLMService::class);
        $embeddingService = Mockery::mock(EmbeddingService::class);

        $service = new MemoryConsolidationService($llmService, $embeddingService);

        $stats = $service->getStats();

        $this->assertEquals(8, $stats['total_memories']);
        $this->assertEquals(3, $stats['consolidated']);
        $this->assertEquals(5, $stats['pending']);
        $this->assertEquals(1, $stats['summaries']['daily']);
    }

    public function test_consolidate_period_marks_memories_correctly(): void
    {
        $yesterday = Carbon::yesterday();

        $memories = Memory::factory()->count(2)->create([
            'is_consolidated' => false,
            'created_at' => $yesterday,
        ]);

        $llmService = Mockery::mock(LLMService::class);
        $llmService->shouldReceive('generate')
            ->andReturn('["theme"]', 'Summary', 'Insights');

        $embeddingService = Mockery::mock(EmbeddingService::class);
        $embeddingService->shouldReceive('embed')
            ->andReturn(array_fill(0, 768, 0.1));
        $embeddingService->shouldReceive('encodeToBinary')
            ->andReturn(pack('f*', ...array_fill(0, 768, 0.1)));
        $embeddingService->shouldReceive('getModelName')
            ->andReturn('nomic-embed-text');

        $service = new MemoryConsolidationService($llmService, $embeddingService);

        $summary = $service->consolidatePeriod($yesterday, $yesterday, 'daily');

        // Verify memories point to summary
        foreach ($memories as $memory) {
            $memory->refresh();
            $this->assertTrue($memory->is_consolidated);
            $this->assertEquals($summary->id, $memory->consolidated_into_id);
            $this->assertNotNull($memory->consolidated_at);
        }
    }
}
