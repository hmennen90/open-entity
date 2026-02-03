<?php

namespace App\Services\Tools\BuiltIn;

use App\Services\Tools\Contracts\ToolInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Artisan Tool - Enables execution of Laravel Artisan commands.
 *
 * Only whitelisted commands are allowed (whitelist principle).
 */
class ArtisanTool implements ToolInterface
{
    /**
     * Allowed commands (whitelist).
     * Format: 'command:name' => ['description', [allowed_options]]
     */
    private array $allowedCommands;

    public function __construct(?array $allowedCommands = null)
    {
        // null = default whitelist, [] = full access
        $this->allowedCommands = $allowedCommands ?? $this->getDefaultAllowedCommands();
    }

    public function name(): string
    {
        return 'artisan';
    }

    public function description(): string
    {
        if (empty($this->allowedCommands)) {
            return "Execute any Laravel Artisan command. Full access to all commands.";
        }
        $commands = implode(', ', array_keys($this->allowedCommands));
        return "Execute Laravel Artisan commands. Allowed commands: {$commands}";
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'command' => [
                    'type' => 'string',
                    'description' => 'The Artisan command to execute (e.g. "cache:clear", "config:clear")',
                ],
                'arguments' => [
                    'type' => 'object',
                    'description' => 'Optional arguments for the command',
                ],
            ],
            'required' => ['command'],
        ];
    }

    public function validate(array $params): array
    {
        $errors = [];

        if (empty($params['command'])) {
            $errors[] = 'command is required';
        }

        $command = $params['command'] ?? '';

        // Check if command is allowed (only when whitelist is active)
        if (!empty($this->allowedCommands) && !$this->isCommandAllowed($command)) {
            $allowed = implode(', ', array_keys($this->allowedCommands));
            $errors[] = "Command '{$command}' is not allowed. Allowed commands: {$allowed}";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function execute(array $params): array
    {
        $command = $params['command'];
        $arguments = $params['arguments'] ?? [];

        // Security check
        if (!$this->isCommandAllowed($command)) {
            return [
                'success' => false,
                'result' => null,
                'error' => [
                    'type' => 'command_not_allowed',
                    'message' => "Command '{$command}' is not in the whitelist",
                ],
            ];
        }

        // Filter only allowed arguments
        $filteredArgs = $this->filterArguments($command, $arguments);

        try {
            Log::channel('entity')->info('Artisan command executing', [
                'command' => $command,
                'arguments' => $filteredArgs,
            ]);

            $exitCode = Artisan::call($command, $filteredArgs);
            $output = Artisan::output();

            Log::channel('entity')->info('Artisan command completed', [
                'command' => $command,
                'exit_code' => $exitCode,
            ]);

            return [
                'success' => $exitCode === 0,
                'result' => [
                    'exit_code' => $exitCode,
                    'output' => trim($output),
                ],
                'error' => $exitCode !== 0 ? [
                    'type' => 'command_failed',
                    'message' => "Command exited with code {$exitCode}",
                ] : null,
            ];

        } catch (\Exception $e) {
            Log::channel('entity')->error('Artisan command failed', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'result' => null,
                'error' => [
                    'type' => 'execution_error',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check if a command is allowed.
     * When allowedCommands array is empty, ALL commands are allowed.
     */
    private function isCommandAllowed(string $command): bool
    {
        // If no whitelist is defined, allow everything
        if (empty($this->allowedCommands)) {
            return true;
        }

        // Exact match
        if (isset($this->allowedCommands[$command])) {
            return true;
        }

        // Wildcard match (e.g. "entity:*")
        foreach (array_keys($this->allowedCommands) as $allowed) {
            if (str_ends_with($allowed, ':*')) {
                $prefix = substr($allowed, 0, -1); // Remove *
                if (str_starts_with($command, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Filter only allowed arguments for a command.
     */
    private function filterArguments(string $command, array $arguments): array
    {
        $config = $this->allowedCommands[$command] ?? null;

        if (!$config || empty($config['allowed_options'])) {
            return [];
        }

        $allowed = $config['allowed_options'];
        return array_intersect_key($arguments, array_flip($allowed));
    }

    /**
     * Default allowed commands.
     */
    private function getDefaultAllowedCommands(): array
    {
        return [
            // Cache Management
            'cache:clear' => [
                'description' => 'Clear cache',
                'allowed_options' => [],
            ],
            'config:clear' => [
                'description' => 'Clear config cache',
                'allowed_options' => [],
            ],
            'config:cache' => [
                'description' => 'Cache config',
                'allowed_options' => [],
            ],
            'route:clear' => [
                'description' => 'Clear route cache',
                'allowed_options' => [],
            ],
            'view:clear' => [
                'description' => 'Clear view cache',
                'allowed_options' => [],
            ],
            'optimize:clear' => [
                'description' => 'Clear all caches',
                'allowed_options' => [],
            ],

            // Queue Management
            'queue:work' => [
                'description' => 'Start queue worker (once)',
                'allowed_options' => ['--once', '--queue'],
            ],
            'queue:retry' => [
                'description' => 'Retry failed jobs',
                'allowed_options' => ['all'],
            ],

            // Entity-specific commands
            'entity:*' => [
                'description' => 'All entity commands',
                'allowed_options' => ['--fresh'],
            ],

            // Migrations (status only)
            'migrate:status' => [
                'description' => 'Show migration status',
                'allowed_options' => [],
            ],

            // Scheduler
            'schedule:list' => [
                'description' => 'Show scheduled tasks',
                'allowed_options' => [],
            ],

            // Info Commands
            'about' => [
                'description' => 'Laravel app info',
                'allowed_options' => [],
            ],
            'env' => [
                'description' => 'Show current environment',
                'allowed_options' => [],
            ],
        ];
    }
}
