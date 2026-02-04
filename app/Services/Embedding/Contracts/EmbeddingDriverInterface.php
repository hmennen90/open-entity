<?php

namespace App\Services\Embedding\Contracts;

/**
 * Interface for embedding generation drivers.
 */
interface EmbeddingDriverInterface
{
    /**
     * Generate an embedding vector for a single text.
     *
     * @param string $text The text to embed
     * @return array<float> The embedding vector
     */
    public function embed(string $text): array;

    /**
     * Generate embeddings for multiple texts in batch.
     *
     * @param array<string> $texts The texts to embed
     * @return array<array<float>> Array of embedding vectors
     */
    public function embedBatch(array $texts): array;

    /**
     * Check if the embedding driver is available.
     */
    public function isAvailable(): bool;

    /**
     * Get the model name being used.
     */
    public function getModelName(): string;

    /**
     * Get the dimensions of the embedding vectors.
     */
    public function getDimensions(): int;
}
