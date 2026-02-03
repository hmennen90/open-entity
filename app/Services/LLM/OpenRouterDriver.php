<?php

namespace App\Services\LLM;

use App\Services\LLM\Contracts\LLMDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenRouter LLM Driver - Zugang zu vielen LLM-Modellen über eine einheitliche API.
 *
 * OpenRouter bietet Zugang zu Claude, GPT-4, Llama, Mixtral und vielen weiteren Modellen.
 * @see https://openrouter.ai/docs
 */
class OpenRouterDriver implements LLMDriverInterface
{
    private string $apiKey;
    private string $model;
    private int $timeout;
    private array $defaultOptions;
    private string $baseUrl = 'https://openrouter.ai/api/v1';
    private string $appName;
    private string $appUrl;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'anthropic/claude-3-haiku';
        $this->timeout = $config['timeout'] ?? 120;
        $this->appName = $config['app_name'] ?? config('app.name', 'OpenEntity');
        $this->appUrl = $config['app_url'] ?? config('app.url', 'http://localhost');
        $this->defaultOptions = $config['options'] ?? [
            'temperature' => 0.8,
            'max_tokens' => 4096,
        ];
    }

    /**
     * Generiere eine Antwort basierend auf einem Prompt.
     */
    public function generate(string $prompt, array $options = []): string
    {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt]
        ], $options);
    }

    /**
     * Generiere eine Antwort mit Chat-History.
     */
    public function chat(array $messages, array $options = []): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenRouter API key not configured');
        }

        $mergedOptions = array_merge($this->defaultOptions, $options);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => $this->appUrl,
                    'X-Title' => $this->appName,
                ])
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => $mergedOptions['temperature'] ?? 0.8,
                    'max_tokens' => $mergedOptions['max_tokens'] ?? 4096,
                ]);

            if ($response->failed()) {
                Log::channel('entity')->error('OpenRouter chat failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException("OpenRouter API error: {$response->status()}");
            }

            $choices = $response->json('choices', []);

            if (empty($choices)) {
                throw new \RuntimeException('No response from OpenRouter');
            }

            return trim($choices[0]['message']['content'] ?? '');

        } catch (\Exception $e) {
            Log::channel('entity')->error('OpenRouter chat exception', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Prüfe ob OpenRouter verfügbar ist.
     */
    public function isAvailable(): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                ])
                ->get("{$this->baseUrl}/models");

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
        if (empty($this->apiKey)) {
            return [];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                ])
                ->get("{$this->baseUrl}/models");

            if ($response->failed()) {
                return [];
            }

            return $response->json('data', []);

        } catch (\Exception $e) {
            return [];
        }
    }
}
