<?php

namespace App\Services\LLM;

use App\Services\LLM\Contracts\LLMDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NVidia LLM Driver - For cloud-based LLM inference via NVIDIA NIM.
 *
 * Supports standard models and "thinking" models like moonshotai/kimi-k2.5
 * which require special chat_template_kwargs for reasoning.
 */
class NVidiaApiDriver implements LLMDriverInterface
{
    private string $apiKey;
    private string $model;
    private int $timeout;
    private array $defaultOptions;
    private string $baseUrl = 'https://integrate.api.nvidia.com/v1';

    /**
     * Models that support "thinking" mode with extended reasoning.
     */
    private array $thinkingModels = [
        'moonshotai/kimi-k2.5',
        'deepseek-ai/deepseek-r1',
    ];

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'moonshotai/kimi-k2.5';
        $this->timeout = $config['timeout'] ?? 120;
        $this->defaultOptions = $config['options'] ?? [
            'temperature' => 1.0,
            'max_tokens' => 4096,
            'top_p' => 1.0,
        ];
    }

    /**
     * Generate a response based on a prompt.
     */
    public function generate(string $prompt, array $options = []): string
    {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt]
        ], $options);
    }

    /**
     * Generate a response with chat history.
     * Supports both streaming and non-streaming modes, with special
     * handling for "thinking" models that require chat_template_kwargs.
     */
    public function chat(array $messages, array $options = []): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('NVidia API key not configured');
        }

        $mergedOptions = array_merge($this->defaultOptions, $options);
        $useStreaming = $mergedOptions['stream'] ?? true;

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $mergedOptions['temperature'] ?? 1.0,
            'max_tokens' => $mergedOptions['max_tokens'] ?? 4096,
            'top_p' => $mergedOptions['top_p'] ?? 1.0,
            'stream' => $useStreaming,
        ];

        // Add thinking mode for supported models
        if ($this->isThinkingModel()) {
            $payload['chat_template_kwargs'] = ['thinking' => true];
        }

        Log::channel('entity')->debug('NVidia API request', [
            'model' => $this->model,
            'streaming' => $useStreaming,
            'thinking_mode' => $this->isThinkingModel(),
        ]);

        try {
            if ($useStreaming) {
                return $this->streamingChat($payload);
            } else {
                return $this->nonStreamingChat($payload);
            }
        } catch (\Exception $e) {
            Log::channel('entity')->error('NVidia chat exception', [
                'error' => $e->getMessage(),
                'model' => $this->model,
            ]);

            throw $e;
        }
    }

    /**
     * Handle streaming response from NVIDIA API.
     */
    private function streamingChat(array $payload): string
    {
        $ch = curl_init();

        $fullResponse = '';
        $thinkingContent = '';

        curl_setopt_array($ch, [
            CURLOPT_URL => "{$this->baseUrl}/chat/completions",
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->apiKey}",
                "Content-Type: application/json",
                "Accept: text/event-stream",
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$fullResponse, &$thinkingContent) {
                static $buffer = '';
                $buffer .= $data;

                // Process complete SSE lines
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    if (str_starts_with($line, 'data: ')) {
                        $jsonData = substr($line, 6);

                        if ($jsonData === '[DONE]') {
                            continue;
                        }

                        $decoded = json_decode($jsonData, true);
                        if ($decoded && isset($decoded['choices'][0]['delta']['content'])) {
                            $fullResponse .= $decoded['choices'][0]['delta']['content'];
                        }
                        // Capture thinking content if present
                        if ($decoded && isset($decoded['choices'][0]['delta']['reasoning_content'])) {
                            $thinkingContent .= $decoded['choices'][0]['delta']['reasoning_content'];
                        }
                    }
                }

                return strlen($data);
            },
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException("NVidia API curl error: {$curlError}");
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException("NVidia API error: HTTP {$httpCode}");
        }

        Log::channel('entity')->debug('NVidia streaming complete', [
            'response_length' => strlen($fullResponse),
            'has_thinking' => !empty($thinkingContent),
        ]);

        return trim($fullResponse);
    }

    /**
     * Handle non-streaming response from NVIDIA API.
     */
    private function nonStreamingChat(array $payload): string
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post("{$this->baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            Log::channel('entity')->error('NVidia chat failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException("NVidia API error: {$response->status()} - {$response->body()}");
        }

        $choices = $response->json('choices', []);

        if (empty($choices)) {
            throw new \RuntimeException('No response from NVidia');
        }

        return trim($choices[0]['message']['content'] ?? '');
    }

    /**
     * Check if current model supports "thinking" mode.
     */
    private function isThinkingModel(): bool
    {
        return in_array($this->model, $this->thinkingModels, true);
    }

    /**
     * Check if NVIDIA API is available.
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
     * Get the name of the current model.
     */
    public function getModelName(): string
    {
        return $this->model;
    }

    /**
     * List available models.
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

    /**
     * Get list of models that support thinking mode.
     */
    public function getThinkingModels(): array
    {
        return $this->thinkingModels;
    }
}
