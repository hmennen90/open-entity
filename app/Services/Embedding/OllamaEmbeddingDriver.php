<?php

namespace App\Services\Embedding;

use App\Services\Embedding\Contracts\EmbeddingDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Ollama Embedding Driver - For local embedding generation.
 */
class OllamaEmbeddingDriver implements EmbeddingDriverInterface
{
    private string $baseUrl;
    private string $model;
    private int $timeout;
    private int $dimensions;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:11434', '/');
        $this->model = $config['model'] ?? 'nomic-embed-text';
        $this->timeout = $config['timeout'] ?? 60;
        $this->dimensions = $config['dimensions'] ?? 768;
    }

    /**
     * Generate an embedding vector for a single text.
     */
    public function embed(string $text): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/embeddings", [
                    'model' => $this->model,
                    'prompt' => $text,
                ]);

            if ($response->failed()) {
                Log::channel('entity')->error('Ollama embedding failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new RuntimeException("Ollama embedding API error: {$response->status()}");
            }

            $embedding = $response->json('embedding', []);

            if (empty($embedding)) {
                throw new RuntimeException('Ollama returned empty embedding');
            }

            return $embedding;

        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('entity')->error('Ollama embedding exception', [
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("Ollama embedding failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Generate embeddings for multiple texts in batch.
     */
    public function embedBatch(array $texts): array
    {
        $embeddings = [];

        foreach ($texts as $text) {
            $embeddings[] = $this->embed($text);
        }

        return $embeddings;
    }

    /**
     * Check if Ollama embedding service is available.
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/tags");

            if (! $response->successful()) {
                return false;
            }

            // Check if the embedding model is available
            $models = $response->json('models', []);

            foreach ($models as $model) {
                if (str_contains($model['name'] ?? '', $this->model)) {
                    return true;
                }
            }

            // Model not found, but service is up - might need to pull
            Log::channel('entity')->warning('Ollama embedding model not found', [
                'model' => $this->model,
            ]);

            return true; // Service is available, model might auto-pull

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the model name being used.
     */
    public function getModelName(): string
    {
        return $this->model;
    }

    /**
     * Get the dimensions of the embedding vectors.
     */
    public function getDimensions(): int
    {
        return $this->dimensions;
    }
}
