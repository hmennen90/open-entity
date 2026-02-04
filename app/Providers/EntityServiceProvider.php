<?php

namespace App\Providers;

use App\Services\LLM\NVidiaApiDriver;
use Illuminate\Support\ServiceProvider;
use App\Services\Entity\ContextEnricherService;
use App\Services\Entity\EntityService;
use App\Services\Entity\EnergyService;
use App\Services\Entity\MindService;
use App\Services\Entity\MemoryService;
use App\Services\Entity\PersonalityService;
use App\Services\Entity\SemanticMemoryService;
use App\Services\Entity\WorkingMemoryService;
use App\Services\Entity\MemoryLayerManager;
use App\Services\Entity\MemoryConsolidationService;
use App\Services\Embedding\EmbeddingService;
use App\Services\Embedding\OllamaEmbeddingDriver;
use App\Services\Embedding\OpenAIEmbeddingDriver;
use App\Services\Embedding\OpenRouterEmbeddingDriver;
use App\Services\Embedding\Contracts\EmbeddingDriverInterface;
use App\Services\LLM\LLMService;
use App\Services\LLM\OllamaDriver;
use App\Services\LLM\OpenAIDriver;
use App\Services\LLM\OpenRouterDriver;
use App\Services\LLM\Contracts\LLMDriverInterface;
use App\Services\Tools\ToolRegistry;
use App\Services\Tools\ToolSandbox;
use App\Services\Tools\ToolValidator;

class EntityServiceProvider extends ServiceProvider
{
    /**
     * Register entity-related services.
     */
    public function register(): void
    {
        // LLM Driver registrieren
        $this->app->singleton(LLMDriverInterface::class, function ($app) {
            $driver = config('entity.llm.default');

            return match ($driver) {
                'ollama' => new OllamaDriver(config('entity.llm.drivers.ollama')),
                'openai' => new OpenAIDriver(config('entity.llm.drivers.openai')),
                'openrouter' => new OpenRouterDriver(config('entity.llm.drivers.openrouter')),
                'nvidia' => new NVidiaApiDriver(config('entity.llm.drivers.nvidia')),
                default => new OllamaDriver(config('entity.llm.drivers.ollama')),
            };
        });

        // LLM Service
        $this->app->singleton(LLMService::class, function ($app) {
            return new LLMService($app->make(LLMDriverInterface::class));
        });

        // Tool System
        $this->app->singleton(ToolValidator::class);

        $this->app->singleton(ToolSandbox::class, function ($app) {
            return new ToolSandbox($app->make(ToolValidator::class));
        });

        $this->app->singleton(ToolRegistry::class, function ($app) {
            return new ToolRegistry($app->make(ToolSandbox::class));
        });

        // Entity Services
        $this->app->singleton(PersonalityService::class);
        $this->app->singleton(MemoryService::class);
        $this->app->singleton(EnergyService::class);

        // Context Enricher (intent detection + system info injection)
        $this->app->singleton(ContextEnricherService::class, function ($app) {
            return new ContextEnricherService(
                $app->make(ToolRegistry::class),
                $app->make(EnergyService::class),
                $app->make(PersonalityService::class)
            );
        });

        // Embedding Driver
        $this->app->singleton(EmbeddingDriverInterface::class, function ($app) {
            $driver = config('entity.memory.embedding.driver', 'ollama');

            return match ($driver) {
                'ollama' => new OllamaEmbeddingDriver(
                    config('entity.memory.embedding.drivers.ollama', [])
                ),
                'openai' => new OpenAIEmbeddingDriver(
                    array_merge(
                        ['api_key' => config('entity.llm.drivers.openai.api_key')],
                        config('entity.memory.embedding.drivers.openai', [])
                    )
                ),
                'openrouter' => new OpenRouterEmbeddingDriver(
                    array_merge(
                        ['api_key' => config('entity.llm.drivers.openrouter.api_key')],
                        config('entity.memory.embedding.drivers.openrouter', [])
                    )
                ),
                default => new OllamaEmbeddingDriver(
                    config('entity.memory.embedding.drivers.ollama', [])
                ),
            };
        });

        // Embedding Service with optional fallback
        $this->app->singleton(EmbeddingService::class, function ($app) {
            $primaryDriver = $app->make(EmbeddingDriverInterface::class);

            // Create fallback driver if configured differently
            $fallbackDriver = null;
            $primaryDriverType = config('entity.memory.embedding.driver', 'ollama');

            // Fallback chain: Ollama -> OpenRouter -> OpenAI
            if ($primaryDriverType === 'ollama') {
                // If using Ollama, try OpenRouter as fallback
                if (config('entity.llm.drivers.openrouter.api_key')) {
                    $fallbackDriver = new OpenRouterEmbeddingDriver(
                        array_merge(
                            ['api_key' => config('entity.llm.drivers.openrouter.api_key')],
                            config('entity.memory.embedding.drivers.openrouter', [])
                        )
                    );
                }
            } elseif ($primaryDriverType === 'openrouter') {
                // If using OpenRouter, try Ollama as fallback (free, local)
                $fallbackDriver = new OllamaEmbeddingDriver(
                    config('entity.memory.embedding.drivers.ollama', [])
                );
            }

            return new EmbeddingService($primaryDriver, $fallbackDriver);
        });

        // Working Memory Service (Redis-based)
        $this->app->singleton(WorkingMemoryService::class);

        // Semantic Memory Service
        $this->app->singleton(SemanticMemoryService::class, function ($app) {
            return new SemanticMemoryService(
                $app->make(EmbeddingService::class),
                $app->make(MemoryService::class)
            );
        });

        // Memory Consolidation Service
        $this->app->singleton(MemoryConsolidationService::class, function ($app) {
            return new MemoryConsolidationService(
                $app->make(LLMService::class),
                $app->make(EmbeddingService::class)
            );
        });

        $this->app->singleton(MindService::class, function ($app) {
            return new MindService(
                $app->make(PersonalityService::class),
                $app->make(MemoryService::class)
            );
        });

        // Memory Layer Manager
        $this->app->singleton(MemoryLayerManager::class, function ($app) {
            return new MemoryLayerManager(
                $app->make(PersonalityService::class),
                $app->make(SemanticMemoryService::class),
                $app->make(MemoryService::class),
                $app->make(WorkingMemoryService::class)
            );
        });

        $this->app->singleton(EntityService::class, function ($app) {
            return new EntityService(
                $app->make(MindService::class),
                $app->make(MemoryService::class),
                $app->make(LLMService::class),
                $app->make(ToolRegistry::class),
                $app->make(MemoryLayerManager::class),
                $app->make(WorkingMemoryService::class),
                $app->make(EnergyService::class),
                $app->make(ContextEnricherService::class)
            );
        });
    }

    /**
     * Bootstrap entity services.
     */
    public function boot(): void
    {
        // Entity Storage-Verzeichnisse erstellen falls nicht vorhanden
        $this->ensureStorageDirectories();
    }

    /**
     * Stelle sicher dass die Entity Storage-Verzeichnisse existieren.
     */
    private function ensureStorageDirectories(): void
    {
        $basePath = config('entity.storage_path');

        $directories = [
            $basePath,
            $basePath . '/mind',
            $basePath . '/mind/reflections',
            $basePath . '/memory',
            $basePath . '/memory/conversations',
            $basePath . '/memory/learned',
            $basePath . '/social',
            $basePath . '/social/interactions',
            $basePath . '/goals',
            $basePath . '/tools',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
}
