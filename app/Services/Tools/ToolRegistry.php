<?php

namespace App\Services\Tools;

use App\Services\Tools\Contracts\ToolInterface;
use App\Services\Tools\BuiltIn\FileSystemTool;
use App\Services\Tools\BuiltIn\WebTool;
use App\Services\Tools\BuiltIn\SearchTool;
use App\Services\Tools\BuiltIn\DocumentationTool;
use App\Services\Tools\BuiltIn\ArtisanTool;
use App\Services\Tools\BuiltIn\BashTool;
use App\Services\Tools\BuiltIn\PersonalityTool;
use App\Services\Tools\BuiltIn\UpdateCheckTool;
use App\Services\Tools\BuiltIn\UserPreferencesTool;
use App\Services\Tools\BuiltIn\GoalTool;
use App\Events\ToolCreated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * Registry for all available tools.
 *
 * Manages both built-in and custom tools
 * created by the entity itself.
 */
class ToolRegistry
{
    /** @var Collection<string, ToolInterface> */
    private Collection $tools;

    /** @var Collection<string, array> */
    private Collection $failedTools;

    private ToolSandbox $sandbox;
    private string $customToolsPath;

    public function __construct(ToolSandbox $sandbox)
    {
        $this->sandbox = $sandbox;
        $this->tools = collect();
        $this->failedTools = collect();
        $this->customToolsPath = config('entity.storage_path') . '/tools';

        $this->ensureCustomToolsDirectory();
        $this->registerBuiltInTools();
        $this->loadCustomTools();
    }

    /**
     * Register a tool.
     */
    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->name()] = $tool;

        Log::channel('entity')->info("Tool registered", [
            'name' => $tool->name(),
            'type' => 'manual',
        ]);
    }

    /**
     * Get a tool by name.
     */
    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Check if a tool exists.
     */
    public function has(string $name): bool
    {
        return $this->tools->has($name);
    }

    /**
     * Get all available tools.
     */
    public function all(): Collection
    {
        return $this->tools;
    }

    /**
     * Get all failed tools.
     */
    public function failed(): Collection
    {
        return $this->failedTools;
    }

    /**
     * Execute a tool.
     */
    public function execute(string $name, array $params = []): array
    {
        $tool = $this->get($name);

        if (!$tool) {
            return [
                'success' => false,
                'result' => null,
                'error' => [
                    'type' => 'tool_not_found',
                    'message' => "Tool '{$name}' not found",
                ],
            ];
        }

        return $this->sandbox->execute($tool, $params);
    }

    /**
     * Create a new custom tool.
     *
     * The entity can use this method to create its own tools.
     */
    public function createCustomTool(string $name, string $code): array
    {
        $filename = $this->sanitizeFilename($name) . '.php';
        $filePath = "{$this->customToolsPath}/{$filename}";

        // Validate first
        $validator = new ToolValidator();
        $validation = $validator->validate($code);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => [
                    'type' => 'validation_failed',
                    'stage' => $validation['stage'],
                    'errors' => $validation['errors'],
                ],
            ];
        }

        // Save the tool
        file_put_contents($filePath, $code);

        // Try to load it
        $loadResult = $this->sandbox->loadFromFile($filePath);

        if ($loadResult['success']) {
            $tool = $loadResult['tool'];
            $this->tools[$tool->name()] = $tool;

            // Remove from failed if previously failed
            $this->failedTools->forget($name);

            event(new ToolCreated(
                toolName: $tool->name(),
                filePath: $filePath,
                description: $tool->description(),
                loadedSuccessfully: true,
                warnings: $loadResult['warnings'] ?? []
            ));

            return [
                'success' => true,
                'tool_name' => $tool->name(),
                'file_path' => $filePath,
                'warnings' => $loadResult['warnings'] ?? [],
            ];
        }

        // Tool could not be loaded - track it as failed
        $this->failedTools[$name] = [
            'file_path' => $filePath,
            'error' => $loadResult['error'],
            'created_at' => now(),
        ];

        event(new ToolCreated(
            toolName: $name,
            filePath: $filePath,
            description: 'Unknown - failed to load',
            loadedSuccessfully: false
        ));

        return [
            'success' => false,
            'file_path' => $filePath,
            'error' => $loadResult['error'],
        ];
    }

    /**
     * Attempt to reload a failed tool.
     */
    public function retryFailedTool(string $name): array
    {
        $failed = $this->failedTools[$name] ?? null;

        if (!$failed) {
            return [
                'success' => false,
                'error' => "Tool '{$name}' not found in failed tools list",
            ];
        }

        $loadResult = $this->sandbox->loadFromFile($failed['file_path']);

        if ($loadResult['success']) {
            $tool = $loadResult['tool'];
            $this->tools[$tool->name()] = $tool;
            $this->failedTools->forget($name);

            return [
                'success' => true,
                'tool_name' => $tool->name(),
            ];
        }

        // Update the error
        $failedTool = $this->failedTools[$name];
        $failedTool['error'] = $loadResult['error'];
        $failedTool['last_retry_at'] = now();
        $this->failedTools[$name] = $failedTool;

        return [
            'success' => false,
            'error' => $loadResult['error'],
        ];
    }

    /**
     * Generate the tool schema for LLM function calling.
     */
    public function getToolSchemas(): array
    {
        return $this->tools->map(function (ToolInterface $tool) {
            return [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ];
        })->values()->toArray();
    }

    /**
     * Generate a summary of all tools for the LLM context.
     */
    public function toPromptContext(): string
    {
        $context = "Available Tools:\n\n";

        foreach ($this->tools as $tool) {
            $context .= "- **{$tool->name()}**: {$tool->description()}\n";
        }

        if ($this->failedTools->isNotEmpty()) {
            $context .= "\n\nFailed Tools (need repair):\n";
            foreach ($this->failedTools as $name => $info) {
                $context .= "- {$name}: " . ($info['error']['message'] ?? 'Unknown error') . "\n";
            }
        }

        return $context;
    }

    /**
     * Register the built-in tools.
     */
    private function registerBuiltInTools(): void
    {
        // FileSystem Tool
        if (config('entity.tools.filesystem.enabled', true)) {
            $this->register(new FileSystemTool(
                config('entity.tools.filesystem.allowed_paths', [])
            ));
        }

        // Web Tool
        if (config('entity.tools.web.enabled', true)) {
            $this->register(new WebTool(
                config('entity.tools.web.timeout', 30)
            ));
        }

        // Search Tool
        if (config('entity.tools.search.enabled', true)) {
            $this->register(new SearchTool(
                config('entity.tools.search.timeout', 15)
            ));
        }

        // Documentation Tool
        if (config('entity.tools.documentation.enabled', true)) {
            $this->register(new DocumentationTool());
        }

        // Artisan Tool - for Laravel commands
        if (config('entity.tools.artisan.enabled', true)) {
            $this->register(new ArtisanTool(
                config('entity.tools.artisan.allowed_commands', [])
            ));
        }

        // Bash Tool - full shell access
        if (config('entity.tools.bash.enabled', false)) {
            $this->register(new BashTool(
                config('entity.tools.bash.timeout', 60),
                base_path()
            ));
        }

        // Personality Tool - allows the entity to evolve its own personality
        if (config('entity.tools.personality.enabled', true)) {
            $this->register(new PersonalityTool());
        }

        // Update Check Tool - allows the entity to check for new versions
        if (config('entity.tools.update_check.enabled', true)) {
            $this->register(new UpdateCheckTool(
                config('entity.tools.update_check.timeout', 10)
            ));
        }

        // User Preferences Tool - allows the entity to remember user preferences
        if (config('entity.tools.user_preferences.enabled', true)) {
            $this->register(new UserPreferencesTool());
        }

        // Goal Tool - allows the entity to manage its own goals
        if (config('entity.tools.goal.enabled', true)) {
            $this->register(new GoalTool());
        }
    }

    /**
     * Load all custom tools from storage.
     */
    private function loadCustomTools(): void
    {
        if (!is_dir($this->customToolsPath)) {
            return;
        }

        $files = glob("{$this->customToolsPath}/*.php");

        foreach ($files as $file) {
            $result = $this->sandbox->loadFromFile($file);

            if ($result['success']) {
                $this->tools[$result['tool']->name()] = $result['tool'];
            } else {
                // Track failed tool
                $name = pathinfo($file, PATHINFO_FILENAME);
                $this->failedTools[$name] = [
                    'file_path' => $file,
                    'error' => $result['error'],
                    'loaded_at' => now(),
                ];
            }
        }
    }

    /**
     * Ensure the custom tools directory exists.
     */
    private function ensureCustomToolsDirectory(): void
    {
        if (!is_dir($this->customToolsPath)) {
            mkdir($this->customToolsPath, 0755, true);
        }
    }

    /**
     * Sanitize a filename.
     */
    private function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($name));
    }
}
