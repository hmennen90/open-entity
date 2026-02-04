<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LlmConfiguration;
use App\Services\LLM\LLMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * API Controller for LLM Configuration Management.
 *
 * Provides CRUD operations for LLM provider configurations
 * and status/health checks for each configuration.
 */
class LlmConfigurationController extends Controller
{
    public function __construct(
        private LLMService $llmService
    ) {}

    /**
     * List all LLM configurations.
     */
    public function index(): JsonResponse
    {
        $configurations = LlmConfiguration::query()
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->get()
            ->map(fn ($config) => $this->formatConfig($config));

        return response()->json([
            'success' => true,
            'data' => $configurations,
        ]);
    }

    /**
     * Get a single LLM configuration.
     */
    public function show(LlmConfiguration $llmConfiguration): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->formatConfig($llmConfiguration),
        ]);
    }

    /**
     * Create a new LLM configuration.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'driver' => 'required|string|in:ollama,openai,openrouter,nvidia',
            'model' => 'required|string|max:255',
            'api_key' => 'nullable|string',
            'base_url' => 'nullable|url|max:255',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'priority' => 'integer|min:0|max:100',
            'options' => 'nullable|array',
            'options.temperature' => 'nullable|numeric|min:0|max:2',
            'options.max_tokens' => 'nullable|integer|min:1|max:100000',
            'options.top_p' => 'nullable|numeric|min:0|max:1',
        ]);

        // If this is set as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            LlmConfiguration::where('is_default', true)->update(['is_default' => false]);
        }

        $config = LlmConfiguration::create($validated);

        Log::channel('entity')->info('LLM configuration created', [
            'id' => $config->id,
            'name' => $config->name,
            'driver' => $config->driver,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'LLM configuration created successfully',
            'data' => $this->formatConfig($config),
        ], 201);
    }

    /**
     * Update an existing LLM configuration.
     */
    public function update(Request $request, LlmConfiguration $llmConfiguration): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'driver' => 'string|in:ollama,openai,openrouter,nvidia',
            'model' => 'string|max:255',
            'api_key' => 'nullable|string',
            'base_url' => 'nullable|url|max:255',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'priority' => 'integer|min:0|max:100',
            'options' => 'nullable|array',
            'options.temperature' => 'nullable|numeric|min:0|max:2',
            'options.max_tokens' => 'nullable|integer|min:1|max:100000',
            'options.top_p' => 'nullable|numeric|min:0|max:1',
        ]);

        // If this is set as default, unset other defaults
        if (($validated['is_default'] ?? false) && !$llmConfiguration->is_default) {
            LlmConfiguration::where('is_default', true)->update(['is_default' => false]);
        }

        $llmConfiguration->update($validated);

        Log::channel('entity')->info('LLM configuration updated', [
            'id' => $llmConfiguration->id,
            'name' => $llmConfiguration->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'LLM configuration updated successfully',
            'data' => $this->formatConfig($llmConfiguration->fresh()),
        ]);
    }

    /**
     * Delete an LLM configuration.
     */
    public function destroy(LlmConfiguration $llmConfiguration): JsonResponse
    {
        $name = $llmConfiguration->name;
        $llmConfiguration->delete();

        Log::channel('entity')->info('LLM configuration deleted', [
            'name' => $name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'LLM configuration deleted successfully',
        ]);
    }

    /**
     * Test an LLM configuration.
     */
    public function test(LlmConfiguration $llmConfiguration): JsonResponse
    {
        try {
            $this->llmService->useConfiguration($llmConfiguration->id);

            $startTime = microtime(true);
            $response = $this->llmService->generate('Say "Hello" in one word.');
            $duration = round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'success' => true,
                'message' => 'Configuration test successful',
                'data' => [
                    'response' => $response,
                    'duration_ms' => $duration,
                    'model' => $llmConfiguration->model,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration test failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset circuit breaker for a configuration.
     */
    public function resetCircuitBreaker(LlmConfiguration $llmConfiguration): JsonResponse
    {
        $this->llmService->resetCircuitBreaker($llmConfiguration->id);

        return response()->json([
            'success' => true,
            'message' => 'Circuit breaker reset successfully',
            'data' => $this->formatConfig($llmConfiguration->fresh()),
        ]);
    }

    /**
     * Set a configuration as default.
     */
    public function setDefault(LlmConfiguration $llmConfiguration): JsonResponse
    {
        // Unset other defaults
        LlmConfiguration::where('is_default', true)->update(['is_default' => false]);

        // Set this as default
        $llmConfiguration->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Default configuration updated',
            'data' => $this->formatConfig($llmConfiguration->fresh()),
        ]);
    }

    /**
     * Reorder configurations by priority.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|exists:llm_configurations,id',
            'order.*.priority' => 'required|integer|min:0|max:100',
        ]);

        foreach ($validated['order'] as $item) {
            LlmConfiguration::where('id', $item['id'])->update(['priority' => $item['priority']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Configuration order updated',
        ]);
    }

    /**
     * Get available drivers and their default configurations.
     */
    public function drivers(): JsonResponse
    {
        $drivers = [
            'ollama' => [
                'name' => 'Ollama',
                'description' => 'Local LLM inference with Ollama',
                'requires_api_key' => false,
                'requires_base_url' => true,
                'default_base_url' => 'http://localhost:11434',
                'popular_models' => [
                    'llama3.2:latest',
                    'qwen2.5-coder:7b',
                    'mistral:latest',
                    'phi3:latest',
                ],
            ],
            'openai' => [
                'name' => 'OpenAI',
                'description' => 'OpenAI GPT models',
                'requires_api_key' => true,
                'requires_base_url' => false,
                'popular_models' => [
                    'gpt-4o',
                    'gpt-4o-mini',
                    'gpt-4-turbo',
                    'gpt-3.5-turbo',
                ],
            ],
            'openrouter' => [
                'name' => 'OpenRouter',
                'description' => 'Access multiple LLM providers through one API',
                'requires_api_key' => true,
                'requires_base_url' => false,
                'popular_models' => [
                    'anthropic/claude-3.5-sonnet',
                    'anthropic/claude-3-haiku',
                    'openai/gpt-4o',
                    'meta-llama/llama-3.1-405b-instruct',
                ],
            ],
            'nvidia' => [
                'name' => 'NVIDIA NIM',
                'description' => 'NVIDIA AI cloud inference with thinking models',
                'requires_api_key' => true,
                'requires_base_url' => false,
                'popular_models' => [
                    'moonshotai/kimi-k2.5',
                    'deepseek-ai/deepseek-r1',
                    'meta/llama-3.1-8b-instruct',
                    'meta/llama-3.1-405b-instruct',
                ],
                'notes' => 'Thinking models (kimi-k2.5, deepseek-r1) take longer but provide better reasoning',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $drivers,
        ]);
    }

    /**
     * Format a configuration for API response.
     */
    private function formatConfig(LlmConfiguration $config): array
    {
        return [
            'id' => $config->id,
            'name' => $config->name,
            'driver' => $config->driver,
            'model' => $config->model,
            'base_url' => $config->base_url,
            'has_api_key' => !empty($config->getAttributes()['api_key']),
            'is_active' => $config->is_active,
            'is_default' => $config->is_default,
            'priority' => $config->priority,
            'options' => $config->options ?? [],
            'status' => $config->status,
            'last_error' => $config->last_error,
            'last_used_at' => $config->last_used_at?->toIso8601String(),
            'last_error_at' => $config->last_error_at?->toIso8601String(),
            'error_count' => $config->error_count,
            'created_at' => $config->created_at->toIso8601String(),
            'updated_at' => $config->updated_at->toIso8601String(),
        ];
    }
}
