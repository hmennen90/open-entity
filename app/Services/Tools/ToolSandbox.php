<?php

namespace App\Services\Tools;

use App\Services\Tools\Contracts\ToolInterface;
use App\Events\ToolExecutionFailed;
use App\Events\ToolLoadFailed;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sandbox for safe tool execution.
 *
 * Catches errors and prevents faulty tools
 * from crashing the entire application.
 */
class ToolSandbox
{
    private ToolValidator $validator;

    public function __construct(ToolValidator $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Load a tool from a file safely.
     *
     * @return array ['success' => bool, 'tool' => ToolInterface|null, 'error' => array|null]
     */
    public function loadFromFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'tool' => null,
                'error' => [
                    'type' => 'file_not_found',
                    'message' => "Tool file not found: {$filePath}",
                ],
            ];
        }

        $code = file_get_contents($filePath);

        // Validate the code
        $validation = $this->validator->validate($code);
        if (!$validation['valid']) {
            $this->fireLoadFailedEvent($filePath, $validation);

            return [
                'success' => false,
                'tool' => null,
                'error' => [
                    'type' => 'validation_failed',
                    'stage' => $validation['stage'],
                    'errors' => $validation['errors'],
                ],
            ];
        }

        // Try to load the tool
        try {
            // Extract the class name from the code
            if (!preg_match('/class\s+(\w+)/', $code, $matches)) {
                throw new \RuntimeException('Could not extract class name from tool file');
            }

            $className = $matches[1];
            $namespace = $this->extractNamespace($code);
            $fullClassName = $namespace ? "{$namespace}\\{$className}" : $className;

            // Load the file (in an isolated scope)
            $this->safeRequire($filePath);

            // Check if the class now exists
            if (!class_exists($fullClassName)) {
                throw new \RuntimeException("Class {$fullClassName} not found after loading");
            }

            // Instantiate the tool
            $tool = new $fullClassName();

            // Check if it implements the interface
            if (!$tool instanceof ToolInterface) {
                throw new \RuntimeException("Tool does not implement ToolInterface");
            }

            Log::channel('entity')->info("Tool loaded successfully", [
                'file' => $filePath,
                'class' => $fullClassName,
                'name' => $tool->name(),
            ]);

            return [
                'success' => true,
                'tool' => $tool,
                'error' => null,
                'warnings' => $validation['warnings'] ?? [],
            ];

        } catch (Throwable $e) {
            $this->fireLoadFailedEvent($filePath, [
                'stage' => 'runtime',
                'errors' => [$e->getMessage()],
            ]);

            Log::channel('entity')->error("Tool load failed", [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'tool' => null,
                'error' => [
                    'type' => 'load_exception',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ];
        }
    }

    /**
     * Execute a tool safely.
     *
     * @return array ['success' => bool, 'result' => mixed, 'error' => array|null]
     */
    public function execute(ToolInterface $tool, array $params): array
    {
        try {
            // Validate parameters
            $validation = $tool->validate($params);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'result' => null,
                    'error' => [
                        'type' => 'validation_failed',
                        'errors' => $validation['errors'],
                    ],
                ];
            }

            // Execute the tool
            $result = $tool->execute($params);

            return $result;

        } catch (Throwable $e) {
            $this->fireExecutionFailedEvent($tool, $params, $e);

            Log::channel('entity')->error("Tool execution failed", [
                'tool' => $tool->name(),
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'result' => null,
                'error' => [
                    'type' => 'execution_exception',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ];
        }
    }

    /**
     * Require a file in an isolated scope.
     */
    private function safeRequire(string $filePath): void
    {
        (static function () use ($filePath) {
            require_once $filePath;
        })();
    }

    /**
     * Extract the namespace from PHP code.
     */
    private function extractNamespace(string $code): ?string
    {
        if (preg_match('/namespace\s+([^;]+);/', $code, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Fire event when tool loading fails.
     */
    private function fireLoadFailedEvent(string $filePath, array $validation): void
    {
        event(new ToolLoadFailed(
            filePath: $filePath,
            stage: $validation['stage'] ?? 'unknown',
            errors: $validation['errors'] ?? []
        ));
    }

    /**
     * Fire event when tool execution fails.
     */
    private function fireExecutionFailedEvent(ToolInterface $tool, array $params, Throwable $e): void
    {
        event(new ToolExecutionFailed(
            toolName: $tool->name(),
            params: $params,
            error: $e->getMessage(),
            file: $e->getFile(),
            line: $e->getLine()
        ));
    }
}
