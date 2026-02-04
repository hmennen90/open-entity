<?php

namespace App\Services\Embedding;

use App\Services\Embedding\Contracts\EmbeddingDriverInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service for generating embeddings and performing vector operations.
 */
class EmbeddingService
{
    private EmbeddingDriverInterface $driver;
    private ?EmbeddingDriverInterface $fallbackDriver;

    public function __construct(
        EmbeddingDriverInterface $driver,
        ?EmbeddingDriverInterface $fallbackDriver = null
    ) {
        $this->driver = $driver;
        $this->fallbackDriver = $fallbackDriver;
    }

    /**
     * Generate an embedding vector for a single text.
     *
     * @param string $text The text to embed
     * @return array<float> The embedding vector
     */
    public function embed(string $text): array
    {
        try {
            return $this->driver->embed($text);
        } catch (\Exception $e) {
            if ($this->fallbackDriver && $this->fallbackDriver->isAvailable()) {
                Log::channel('entity')->warning('Falling back to secondary embedding driver', [
                    'error' => $e->getMessage(),
                ]);

                return $this->fallbackDriver->embed($text);
            }

            throw $e;
        }
    }

    /**
     * Generate embeddings for multiple texts in batch.
     *
     * @param array<string> $texts The texts to embed
     * @return array<array<float>> Array of embedding vectors
     */
    public function embedBatch(array $texts): array
    {
        try {
            return $this->driver->embedBatch($texts);
        } catch (\Exception $e) {
            if ($this->fallbackDriver && $this->fallbackDriver->isAvailable()) {
                Log::channel('entity')->warning('Falling back to secondary embedding driver for batch', [
                    'error' => $e->getMessage(),
                ]);

                return $this->fallbackDriver->embedBatch($texts);
            }

            throw $e;
        }
    }

    /**
     * Calculate cosine similarity between two vectors.
     *
     * @param array<float> $a First vector
     * @param array<float> $b Second vector
     * @return float Similarity score between -1 and 1
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new RuntimeException(
                'Vector dimension mismatch: ' . count($a) . ' vs ' . count($b)
            );
        }

        if (empty($a)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0, $len = count($a); $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Find the k most similar items from a collection of candidates.
     *
     * @param array<float> $queryEmbedding The query embedding
     * @param Collection $candidates Collection of items with 'embedding' field
     * @param int $k Number of results to return
     * @param float $threshold Minimum similarity threshold (default 0.0)
     * @return Collection Sorted by similarity (highest first)
     */
    public function findSimilar(
        array $queryEmbedding,
        Collection $candidates,
        int $k = 10,
        float $threshold = 0.0
    ): Collection {
        return $candidates
            ->map(function ($item) use ($queryEmbedding) {
                $embedding = $this->extractEmbedding($item);

                if (empty($embedding)) {
                    return null;
                }

                // Handle dimension mismatch gracefully
                if (count($embedding) !== count($queryEmbedding)) {
                    Log::channel('entity')->debug('Skipping item with mismatched embedding dimensions', [
                        'expected' => count($queryEmbedding),
                        'actual' => count($embedding),
                    ]);
                    return null;
                }

                $similarity = $this->cosineSimilarity($queryEmbedding, $embedding);

                if (is_object($item)) {
                    $item->similarity = $similarity;
                } elseif (is_array($item)) {
                    $item['similarity'] = $similarity;
                }

                return ['item' => $item, 'similarity' => $similarity];
            })
            ->filter()
            ->filter(fn ($result) => $result['similarity'] >= $threshold)
            ->sortByDesc('similarity')
            ->take($k)
            ->map(fn ($result) => $result['item'])
            ->values();
    }

    /**
     * Extract embedding from an item (handles both objects and arrays).
     *
     * @param mixed $item
     * @return array<float>|null
     */
    private function extractEmbedding($item): ?array
    {
        if (is_object($item)) {
            $embedding = $item->embedding ?? null;
        } elseif (is_array($item)) {
            $embedding = $item['embedding'] ?? null;
        } else {
            return null;
        }

        // Handle binary BLOB storage - decode if needed
        if (is_string($embedding)) {
            return $this->decodeBinaryEmbedding($embedding);
        }

        return $embedding;
    }

    /**
     * Encode an embedding array to binary format for BLOB storage.
     *
     * @param array<float> $embedding
     * @return string Binary representation
     */
    public function encodeToBinary(array $embedding): string
    {
        return pack('f*', ...$embedding);
    }

    /**
     * Decode a binary embedding back to array format.
     *
     * @param string $binary Binary representation
     * @return array<float>
     */
    public function decodeBinaryEmbedding(string $binary): array
    {
        if (empty($binary)) {
            return [];
        }

        $floats = unpack('f*', $binary);

        return $floats ? array_values($floats) : [];
    }

    /**
     * Get the current driver being used.
     */
    public function getDriver(): EmbeddingDriverInterface
    {
        return $this->driver;
    }

    /**
     * Get the model name of the current driver.
     */
    public function getModelName(): string
    {
        return $this->driver->getModelName();
    }

    /**
     * Get the embedding dimensions of the current driver.
     */
    public function getDimensions(): int
    {
        return $this->driver->getDimensions();
    }

    /**
     * Check if embedding service is available.
     */
    public function isAvailable(): bool
    {
        if ($this->driver->isAvailable()) {
            return true;
        }

        return $this->fallbackDriver?->isAvailable() ?? false;
    }
}
