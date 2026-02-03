<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Entity\EntityService;
use App\Services\Entity\MindService;
use App\Services\Entity\MemoryService;
use App\Services\Entity\PersonalityService;
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

        $this->app->singleton(MindService::class, function ($app) {
            return new MindService(
                $app->make(PersonalityService::class),
                $app->make(MemoryService::class)
            );
        });

        $this->app->singleton(EntityService::class, function ($app) {
            return new EntityService(
                $app->make(MindService::class),
                $app->make(MemoryService::class),
                $app->make(LLMService::class),
                $app->make(ToolRegistry::class)
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
