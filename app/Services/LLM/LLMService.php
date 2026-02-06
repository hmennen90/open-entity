<?php

namespace App\Services\LLM;

use App\Exceptions\NoLlmConfigurationException;
use App\Models\LlmConfiguration;
use App\Services\LLM\Contracts\LLMDriverInterface;
use Illuminate\Support\Facades\Log;

/**
 * LLM Service - Manages LLM interactions with automatic fallback support.
 *
 * Loads configurations from database with priority-based fallback.
 * When one provider fails, automatically tries the next available one.
 */
class LLMService
{
    private ?LLMDriverInterface $driver = null;
    private ?LlmConfiguration $currentConfig = null;

    /**
     * Generate a response based on a prompt.
     */
    public function generate(string $prompt, array $options = []): string
    {
        return $this->executeWithFallback(function (LLMDriverInterface $driver) use ($prompt, $options) {
            Log::channel('entity')->debug('LLM generate request', [
                'model' => $driver->getModelName(),
                'prompt_length' => strlen($prompt),
            ]);

            $startTime = microtime(true);
            $response = $driver->generate($prompt, $options);
            $duration = round((microtime(true) - $startTime) * 1000);

            Log::channel('entity')->debug('LLM generate response', [
                'model' => $driver->getModelName(),
                'response_length' => strlen($response),
                'duration_ms' => $duration,
            ]);

            return $response;
        });
    }

    /**
     * Generate a response with chat history.
     */
    public function chat(array $messages, array $options = []): string
    {
        return $this->executeWithFallback(function (LLMDriverInterface $driver) use ($messages, $options) {
            Log::channel('entity')->debug('LLM chat request', [
                'model' => $driver->getModelName(),
                'message_count' => count($messages),
            ]);

            $startTime = microtime(true);
            $response = $driver->chat($messages, $options);
            $duration = round((microtime(true) - $startTime) * 1000);

            Log::channel('entity')->debug('LLM chat response', [
                'model' => $driver->getModelName(),
                'response_length' => strlen($response),
                'duration_ms' => $duration,
            ]);

            return $response;
        });
    }

    /**
     * Execute an LLM operation with automatic fallback to alternative configurations.
     *
     * @throws NoLlmConfigurationException when no configurations exist in the database
     */
    private function executeWithFallback(callable $operation): string
    {
        $configurations = $this->getAvailableConfigurations();

        if ($configurations->isEmpty()) {
            throw new NoLlmConfigurationException();
        }

        $lastException = null;

        foreach ($configurations as $config) {
            // Skip if circuit breaker is triggered
            if ($config->shouldSkip()) {
                Log::channel('entity')->debug('Skipping LLM config (circuit breaker)', [
                    'name' => $config->name,
                    'error_count' => $config->error_count,
                ]);
                continue;
            }

            try {
                $driver = $this->createDriverFromConfig($config);
                $this->driver = $driver;
                $this->currentConfig = $config;

                $result = $operation($driver);

                // Mark as successfully used
                $config->markUsed();

                return $result;

            } catch (\Exception $e) {
                $lastException = $e;

                // Mark the error
                $config->markError($e->getMessage());

                Log::channel('entity')->warning('LLM config failed, trying next', [
                    'name' => $config->name,
                    'driver' => $config->driver,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // All configurations failed
        throw $lastException ?? new \RuntimeException('All LLM configurations failed');
    }

    /**
     * Get available configurations ordered by priority.
     */
    private function getAvailableConfigurations()
    {
        return LlmConfiguration::active()
            ->byPriority()
            ->get();
    }

    /**
     * Create a driver instance from a database configuration.
     */
    private function createDriverFromConfig(LlmConfiguration $config): LLMDriverInterface
    {
        $driverConfig = $config->toDriverConfig();

        return match ($config->driver) {
            'ollama' => new OllamaDriver($driverConfig),
            'openai' => new OpenAIDriver($driverConfig),
            'openrouter' => new OpenRouterDriver($driverConfig),
            'nvidia' => new NVidiaApiDriver($driverConfig),
            default => throw new \RuntimeException("Unknown LLM driver: {$config->driver}"),
        };
    }

    /**
     * Check if any LLM is available.
     */
    public function isAvailable(): bool
    {
        $configurations = $this->getAvailableConfigurations();

        foreach ($configurations as $config) {
            if (!$config->shouldSkip()) {
                try {
                    $driver = $this->createDriverFromConfig($config);
                    if ($driver->isAvailable()) {
                        return true;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return false;
    }

    /**
     * Get the name of the currently used model.
     */
    public function getModelName(): string
    {
        if ($this->currentConfig) {
            return $this->currentConfig->model;
        }

        if ($this->driver) {
            return $this->driver->getModelName();
        }

        return 'unknown';
    }

    /**
     * Get the current driver.
     */
    public function getDriver(): ?LLMDriverInterface
    {
        return $this->driver;
    }

    /**
     * Get the current configuration (if using database configs).
     */
    public function getCurrentConfig(): ?LlmConfiguration
    {
        return $this->currentConfig;
    }

    /**
     * Generate with a system prompt.
     */
    public function generateWithSystem(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        return $this->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], $options);
    }

    /**
     * Summarize text.
     */
    public function summarize(string $text, int $maxLength = 200): string
    {
        $prompt = <<<PROMPT
Summarize the following text in a maximum of {$maxLength} characters.
Keep the most important information.

Text:
{$text}

Summary:
PROMPT;

        return $this->generate($prompt);
    }

    /**
     * Analyze sentiment of text.
     */
    public function analyzeSentiment(string $text): float
    {
        $prompt = <<<PROMPT
Analyze the sentiment of the following text on a scale from -1.0 (very negative) to 1.0 (very positive).
Reply ONLY with a number.

Text:
{$text}

Sentiment:
PROMPT;

        $response = $this->generate($prompt);

        // Try to extract a number
        preg_match('/-?\d+\.?\d*/', $response, $matches);

        if (!empty($matches)) {
            return max(-1.0, min(1.0, (float) $matches[0]));
        }

        return 0.0;
    }

    /**
     * List all available configurations with their status.
     */
    public function listConfigurations(): array
    {
        return LlmConfiguration::active()
            ->byPriority()
            ->get()
            ->map(function (LlmConfiguration $config) {
                return [
                    'id' => $config->id,
                    'name' => $config->name,
                    'driver' => $config->driver,
                    'model' => $config->model,
                    'is_default' => $config->is_default,
                    'priority' => $config->priority,
                    'status' => $config->status,
                    'last_used_at' => $config->last_used_at?->toIso8601String(),
                    'error_count' => $config->error_count,
                ];
            })
            ->toArray();
    }

    /**
     * Force use of a specific configuration by ID.
     */
    public function useConfiguration(int $configId): void
    {
        $config = LlmConfiguration::findOrFail($configId);
        $this->driver = $this->createDriverFromConfig($config);
        $this->currentConfig = $config;
    }

    /**
     * Reset circuit breaker for a configuration.
     */
    public function resetCircuitBreaker(int $configId): void
    {
        $config = LlmConfiguration::findOrFail($configId);
        $config->update([
            'error_count' => 0,
            'last_error' => null,
            'last_error_at' => null,
        ]);
    }
}
