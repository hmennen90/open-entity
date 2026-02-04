<?php

namespace App\Services\Entity;

use App\Jobs\GenerateEmbeddingJob;
use App\Models\Memory;
use App\Services\Embedding\EmbeddingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * SemanticMemoryService - Provides semantic search capabilities for memories.
 *
 * Uses embeddings and vector similarity to find memories based on meaning,
 * not just keyword matching.
 */
class SemanticMemoryService
{
    public function __construct(
        private EmbeddingService $embeddingService,
        private MemoryService $memoryService
    ) {}

    /**
     * Search memories using semantic similarity.
     *
     * @param string $query Natural language query (e.g., "What did I learn about programming?")
     * @param int $limit Maximum results to return
     * @param float $threshold Minimum similarity threshold (0.0 to 1.0)
     * @return Collection Memories sorted by relevance
     */
    public function search(string $query, int $limit = 10, float $threshold = 0.5): Collection
    {
        try {
            // Generate embedding for the query
            $queryEmbedding = $this->embeddingService->embed($query);

            // Get all memories with embeddings
            $candidates = Memory::whereNotNull('embedding')
                ->whereNotNull('embedded_at')
                ->get();

            if ($candidates->isEmpty()) {
                // Fallback to keyword search if no embeddings exist
                Log::channel('entity')->info('No embedded memories found, falling back to keyword search');
                return $this->memoryService->search($query, $limit);
            }

            // Find similar memories using vector similarity
            return $this->embeddingService->findSimilar(
                $queryEmbedding,
                $candidates,
                $limit,
                $threshold
            );

        } catch (\Exception $e) {
            Log::channel('entity')->error('Semantic search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            // Fallback to keyword search
            return $this->memoryService->search($query, $limit);
        }
    }

    /**
     * Find memories related to a topic using semantic similarity.
     *
     * @param string $topic The topic to find related memories for
     * @param int $limit Maximum results to return
     * @return Collection Related memories sorted by relevance
     */
    public function findRelatedTo(string $topic, int $limit = 10): Collection
    {
        return $this->search($topic, $limit, 0.6);
    }

    /**
     * Get contextually relevant memories for a given situation.
     *
     * @param string $context Description of the current context/situation
     * @param int $maxTokens Approximate token budget for returned memories
     * @return Collection Memories that fit within the token budget
     */
    public function getContextualMemories(string $context, int $maxTokens = 2000): Collection
    {
        $threshold = config('entity.memory.layers.episodic.similarity_threshold', 0.7);

        $memories = $this->search($context, 20, $threshold);

        // Estimate tokens and filter to fit budget
        $totalTokens = 0;
        $avgTokensPerChar = 0.25; // Rough estimate: 4 chars per token

        return $memories->filter(function ($memory) use (&$totalTokens, $maxTokens, $avgTokensPerChar) {
            $content = $memory->summary ?? $memory->content;
            $estimatedTokens = (int) (strlen($content) * $avgTokensPerChar);

            if ($totalTokens + $estimatedTokens > $maxTokens) {
                return false;
            }

            $totalTokens += $estimatedTokens;
            return true;
        });
    }

    /**
     * Create a new memory with automatic embedding generation.
     *
     * @param array $data Memory data
     * @param bool $embedSync Generate embedding synchronously (default: async job)
     * @return Memory
     */
    public function createWithEmbedding(array $data, bool $embedSync = false): Memory
    {
        // Create the memory using existing service
        $memory = $this->memoryService->create($data);

        if ($embedSync) {
            // Generate embedding immediately
            $this->generateEmbedding($memory);
        } else {
            // Dispatch background job
            GenerateEmbeddingJob::dispatch($memory);
        }

        return $memory;
    }

    /**
     * Generate and store embedding for a memory.
     */
    public function generateEmbedding(Memory $memory): void
    {
        try {
            // Use summary if available, otherwise full content
            $textToEmbed = $memory->summary ?? $memory->content;

            // Add context for better semantic understanding
            if ($memory->type) {
                $textToEmbed = "[{$memory->type}] " . $textToEmbed;
            }

            $embedding = $this->embeddingService->embed($textToEmbed);

            $memory->update([
                'embedding' => $this->embeddingService->encodeToBinary($embedding),
                'embedding_dimensions' => count($embedding),
                'embedding_model' => $this->embeddingService->getModelName(),
                'embedded_at' => now(),
            ]);

            Log::channel('entity')->debug('Generated embedding for memory', [
                'memory_id' => $memory->id,
                'dimensions' => count($embedding),
                'model' => $this->embeddingService->getModelName(),
            ]);

        } catch (\Exception $e) {
            Log::channel('entity')->error('Failed to generate embedding for memory', [
                'memory_id' => $memory->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Backfill embeddings for existing memories that don't have them.
     *
     * @param int $batchSize Number of memories to process per batch
     * @return int Number of memories processed
     */
    public function backfillEmbeddings(int $batchSize = 100): int
    {
        $processed = 0;

        Memory::whereNull('embedding')
            ->orWhereNull('embedded_at')
            ->orderBy('importance', 'desc')
            ->orderBy('created_at', 'desc')
            ->chunk($batchSize, function ($memories) use (&$processed) {
                foreach ($memories as $memory) {
                    try {
                        $this->generateEmbedding($memory);
                        $processed++;

                        // Small delay to avoid overwhelming the embedding service
                        usleep(100000); // 100ms
                    } catch (\Exception $e) {
                        Log::channel('entity')->warning('Skipped memory during backfill', [
                            'memory_id' => $memory->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $processed;
    }

    /**
     * Get memories by layer with semantic scoring.
     *
     * @param string $layer The memory layer ('episodic', 'semantic', 'procedural')
     * @param string|null $contextQuery Optional context for semantic ordering
     * @param int $limit Maximum results
     * @return Collection
     */
    public function getByLayer(string $layer, ?string $contextQuery = null, int $limit = 10): Collection
    {
        $query = Memory::where('layer', $layer)
            ->where('is_consolidated', false);

        if ($contextQuery && $this->embeddingService->isAvailable()) {
            // Get all candidates and filter by semantic similarity
            $candidates = $query->whereNotNull('embedding')->get();

            if ($candidates->isNotEmpty()) {
                try {
                    $queryEmbedding = $this->embeddingService->embed($contextQuery);
                    return $this->embeddingService->findSimilar(
                        $queryEmbedding,
                        $candidates,
                        $limit,
                        0.5
                    );
                } catch (\Exception $e) {
                    Log::channel('entity')->warning('Semantic layer query failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Fallback to importance-based ordering
        return $query->orderByDesc('importance')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Find similar memories to a given memory.
     *
     * @param Memory $memory The reference memory
     * @param int $limit Maximum results
     * @return Collection Similar memories (excluding the reference)
     */
    public function findSimilarMemories(Memory $memory, int $limit = 5): Collection
    {
        if (!$memory->embedding) {
            // Generate embedding if not exists
            $this->generateEmbedding($memory);
            $memory->refresh();
        }

        $embedding = $this->embeddingService->decodeBinaryEmbedding($memory->embedding);

        $candidates = Memory::whereNotNull('embedding')
            ->where('id', '!=', $memory->id)
            ->get();

        return $this->embeddingService->findSimilar($embedding, $candidates, $limit, 0.6);
    }

    /**
     * Get embedding statistics for monitoring.
     */
    public function getStats(): array
    {
        $total = Memory::count();
        $embedded = Memory::whereNotNull('embedded_at')->count();
        $pending = $total - $embedded;

        return [
            'total_memories' => $total,
            'embedded' => $embedded,
            'pending' => $pending,
            'coverage_percent' => $total > 0 ? round(($embedded / $total) * 100, 2) : 0,
            'embedding_model' => $this->embeddingService->getModelName(),
            'embedding_dimensions' => $this->embeddingService->getDimensions(),
        ];
    }
}
