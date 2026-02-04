<?php

namespace App\Services\Embedding;

use App\Services\Embedding\Contracts\EmbeddingDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * OpenRouter Embedding Driver - Uses OpenRouter's embedding models.
 *
 * OpenRouter supports several embedding models including:
 * - openai/text-embedding-3-small
 * - openai/text-embedding-ada-002
 */
class OpenRouterEmbeddingDriver implements EmbeddingDriverInterface
{
    private string $apiKey;
    private string $model;
    private int $timeout;
    private int $dimensions;

    private const API_URL = 'https://openrouter.ai/api/v1/embeddings';

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'openai/text-embedding-3-small';
        $this->timeout = $config['timeout'] ?? 30;
        $this->dimensions = $config['dimensions'] ?? 1536;
    }

    /**
     * Generate an embedding vector for a single text.
     */
    public function embed(string $text): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('OpenRouter API key not configured');
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('entity.llm.drivers.openrouter.app_url', 'http://localhost'),
                    'X-Title' => config('entity.llm.drivers.openrouter.app_name', 'OpenEntity'),
                ])
                ->post(self::API_URL, [
                    'model' => $this->model,
                    'input' => $text,
                ]);

            if ($response->failed()) {
                Log::channel('entity')->error('OpenRouter embedding failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new RuntimeException("OpenRouter embedding API error: {$response->status()}");
            }

            $data = $response->json();
            $embedding = $data['data'][0]['embedding'] ?? [];

            if (empty($embedding)) {
                throw new RuntimeException('OpenRouter returned empty embedding');
            }

            return $embedding;

        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('entity')->error('OpenRouter embedding exception', [
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("OpenRouter embedding failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Generate embeddings for multiple texts in batch.
     */
    public function embedBatch(array $texts): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('OpenRouter API key not configured');
        }

        if (empty($texts)) {
            return [];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('entity.llm.drivers.openrouter.app_url', 'http://localhost'),
                    'X-Title' => config('entity.llm.drivers.openrouter.app_name', 'OpenEntity'),
                ])
                ->post(self::API_URL, [
                    'model' => $this->model,
                    'input' => $texts,
                ]);

            if ($response->failed()) {
                Log::channel('entity')->error('OpenRouter batch embedding failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new RuntimeException("OpenRouter embedding API error: {$response->status()}");
            }

            $data = $response->json();
            $embeddings = [];

            // Sort by index to maintain order
            $items = $data['data'] ?? [];
            usort($items, fn ($a, $b) => ($a['index'] ?? 0) - ($b['index'] ?? 0));

            foreach ($items as $item) {
                $embeddings[] = $item['embedding'] ?? [];
            }

            return $embeddings;

        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('entity')->error('OpenRouter batch embedding exception', [
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("OpenRouter batch embedding failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Check if OpenRouter embedding service is available.
     */
    public function isAvailable(): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        // OpenRouter doesn't have a simple health check endpoint
        // Just verify we have an API key
        return true;
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
