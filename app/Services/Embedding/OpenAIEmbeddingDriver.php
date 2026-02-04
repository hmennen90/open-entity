<?php

namespace App\Services\Embedding;

use App\Services\Embedding\Contracts\EmbeddingDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * OpenAI Embedding Driver - For cloud-based embedding generation.
 */
class OpenAIEmbeddingDriver implements EmbeddingDriverInterface
{
    private string $apiKey;
    private string $model;
    private int $timeout;
    private int $dimensions;

    private const API_URL = 'https://api.openai.com/v1/embeddings';

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'text-embedding-3-small';
        $this->timeout = $config['timeout'] ?? 30;
        $this->dimensions = $config['dimensions'] ?? 1536;
    }

    /**
     * Generate an embedding vector for a single text.
     */
    public function embed(string $text): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('OpenAI API key not configured');
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post(self::API_URL, [
                    'model' => $this->model,
                    'input' => $text,
                ]);

            if ($response->failed()) {
                Log::channel('entity')->error('OpenAI embedding failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new RuntimeException("OpenAI embedding API error: {$response->status()}");
            }

            $data = $response->json();
            $embedding = $data['data'][0]['embedding'] ?? [];

            if (empty($embedding)) {
                throw new RuntimeException('OpenAI returned empty embedding');
            }

            return $embedding;

        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('entity')->error('OpenAI embedding exception', [
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("OpenAI embedding failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Generate embeddings for multiple texts in batch.
     */
    public function embedBatch(array $texts): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('OpenAI API key not configured');
        }

        if (empty($texts)) {
            return [];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post(self::API_URL, [
                    'model' => $this->model,
                    'input' => $texts,
                ]);

            if ($response->failed()) {
                Log::channel('entity')->error('OpenAI batch embedding failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new RuntimeException("OpenAI embedding API error: {$response->status()}");
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
            Log::channel('entity')->error('OpenAI batch embedding exception', [
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("OpenAI batch embedding failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Check if OpenAI embedding service is available.
     */
    public function isAvailable(): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        try {
            // Just verify we can reach OpenAI - don't waste tokens on a test embedding
            $response = Http::timeout(5)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                ])
                ->get('https://api.openai.com/v1/models');

            return $response->successful();

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
