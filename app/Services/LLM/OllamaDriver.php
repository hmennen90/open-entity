<?php

namespace App\Services\LLM;

use App\Services\LLM\Contracts\LLMDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ollama LLM Driver - Für lokale LLM-Inferenz.
 */
class OllamaDriver implements LLMDriverInterface
{
    private string $baseUrl;
    private string $model;
    private int $timeout;
    private array $defaultOptions;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:11434', '/');
        $this->model = $config['model'] ?? 'qwen-coder:30b';
        $this->timeout = $config['timeout'] ?? 120;
        $this->defaultOptions = $config['options'] ?? [
            'temperature' => 0.8,
            'top_p' => 0.9,
            'num_ctx' => 8192,
        ];
    }

    /**
     * Generiere eine Antwort basierend auf einem Prompt.
     */
    public function generate(string $prompt, array $options = []): string
    {
        $mergedOptions = array_merge($this->defaultOptions, $options);

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/generate", [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => $mergedOptions,
                ]);

            if ($response->failed()) {
                Log::channel('entity')->error('Ollama generate failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException("Ollama API error: {$response->status()}");
            }

            return trim($response->json('response', ''));

        } catch (\Exception $e) {
            Log::channel('entity')->error('Ollama generate exception', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generiere eine Antwort mit Chat-History.
     */
    public function chat(array $messages, array $options = []): string
    {
        $mergedOptions = array_merge($this->defaultOptions, $options);

        // Konvertiere Messages ins Ollama-Format
        $ollamaMessages = array_map(function ($message) {
            return [
                'role' => $message['role'] ?? 'user',
                'content' => $message['content'] ?? '',
            ];
        }, $messages);

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/chat", [
                    'model' => $this->model,
                    'messages' => $ollamaMessages,
                    'stream' => false,
                    'options' => $mergedOptions,
                ]);

            if ($response->failed()) {
                Log::channel('entity')->error('Ollama chat failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException("Ollama API error: {$response->status()}");
            }

            return trim($response->json('message.content', ''));

        } catch (\Exception $e) {
            Log::channel('entity')->error('Ollama chat exception', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Prüfe ob Ollama verfügbar ist.
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/tags");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Hole den Namen des verwendeten Modells.
     */
    public function getModelName(): string
    {
        return $this->model;
    }

    /**
     * Liste verfügbare Modelle.
     */
    public function listModels(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/tags");

            if ($response->failed()) {
                return [];
            }

            return $response->json('models', []);

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Prüfe ob ein bestimmtes Modell verfügbar ist.
     */
    public function hasModel(string $modelName): bool
    {
        $models = $this->listModels();

        foreach ($models as $model) {
            if (str_contains($model['name'] ?? '', $modelName)) {
                return true;
            }
        }

        return false;
    }
}
