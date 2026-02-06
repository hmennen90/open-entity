<?php

namespace App\Services\Tools\BuiltIn;

use App\Services\Tools\Contracts\ToolInterface;

/**
 * FileSystem Tool - Enables file operations.
 *
 * Only within allowed paths (default: storage/entity).
 */
class FileSystemTool implements ToolInterface
{
    private array $allowedPaths;

    public function __construct(array $allowedPaths = [])
    {
        $this->allowedPaths = $allowedPaths ?: [
            storage_path('entity'),
        ];
    }

    public function name(): string
    {
        return 'filesystem';
    }

    public function description(): string
    {
        $paths = implode(', ', array_map(fn($p) => basename($p) ?: $p, $this->allowedPaths));
        return "Read and write files. Access to: {$paths}. " .
               'Operations: read, write, append, delete, list, exists.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'enum' => ['read', 'write', 'append', 'delete', 'list', 'exists'],
                    'description' => 'The operation to execute',
                ],
                'path' => [
                    'type' => 'string',
                    'description' => 'Relative path within storage/entity',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Content for write/append operations',
                ],
            ],
            'required' => ['operation', 'path'],
        ];
    }

    public function validate(array $params): array
    {
        $errors = [];

        if (empty($params['operation'])) {
            $errors[] = 'operation is required';
        }

        if (empty($params['path'])) {
            $errors[] = 'path is required';
        }

        if (in_array($params['operation'] ?? '', ['write', 'append']) && !isset($params['content'])) {
            $errors[] = 'content is required for write/append operations';
        }

        // Check for path traversal
        if (isset($params['path']) && str_contains($params['path'], '..')) {
            $errors[] = 'Path traversal not allowed';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function execute(array $params): array
    {
        $operation = $params['operation'];
        $relativePath = ltrim($params['path'], '/');
        $fullPath = $this->resolvePath($relativePath);

        // Security check
        if (!$this->isPathAllowed($fullPath)) {
            return [
                'success' => false,
                'result' => null,
                'error' => [
                    'type' => 'permission_denied',
                    'message' => 'Path is outside allowed directories',
                ],
            ];
        }

        return match($operation) {
            'read' => $this->read($fullPath),
            'write' => $this->write($fullPath, $params['content'] ?? ''),
            'append' => $this->append($fullPath, $params['content'] ?? ''),
            'delete' => $this->delete($fullPath),
            'list' => $this->list($fullPath),
            'exists' => $this->exists($fullPath),
            default => [
                'success' => false,
                'result' => null,
                'error' => ['type' => 'invalid_operation', 'message' => "Unknown operation: {$operation}"],
            ],
        };
    }

    private function read(string $path): array
    {
        if (!file_exists($path)) {
            return [
                'success' => false,
                'result' => null,
                'error' => ['type' => 'file_not_found', 'message' => 'File does not exist'],
            ];
        }

        if (!is_readable($path)) {
            return [
                'success' => false,
                'result' => null,
                'error' => ['type' => 'not_readable', 'message' => 'File is not readable'],
            ];
        }

        $maxSize = 1024 * 1024; // 1 MB limit
        $fileSize = filesize($path);
        if ($fileSize > $maxSize) {
            $content = file_get_contents($path, false, null, 0, $maxSize);
            return [
                'success' => true,
                'result' => $content . "\n\n[... truncated, total size: {$fileSize} bytes]",
                'error' => null,
            ];
        }

        return [
            'success' => true,
            'result' => file_get_contents($path),
            'error' => null,
        ];
    }

    private function write(string $path, string $content): array
    {
        // Ensure the directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $result = file_put_contents($path, $content);

        if ($result === false) {
            return [
                'success' => false,
                'result' => null,
                'error' => ['type' => 'write_failed', 'message' => 'Could not write to file'],
            ];
        }

        return [
            'success' => true,
            'result' => ['bytes_written' => $result],
            'error' => null,
        ];
    }

    private function append(string $path, string $content): array
    {
        $result = file_put_contents($path, $content, FILE_APPEND);

        if ($result === false) {
            return [
                'success' => false,
                'result' => null,
                'error' => ['type' => 'append_failed', 'message' => 'Could not append to file'],
            ];
        }

        return [
            'success' => true,
            'result' => ['bytes_written' => $result],
            'error' => null,
        ];
    }

    private function delete(string $path): array
    {
        if (!file_exists($path)) {
            return [
                'success' => false,
                'result' => null,
                'error' => ['type' => 'file_not_found', 'message' => 'File does not exist'],
            ];
        }

        if (is_dir($path)) {
            return [
                'success' => false,
                'result' => null,
                'error' => ['type' => 'is_directory', 'message' => 'Cannot delete directories'],
            ];
        }

        $result = unlink($path);

        return [
            'success' => $result,
            'result' => $result ? ['deleted' => true] : null,
            'error' => $result ? null : ['type' => 'delete_failed', 'message' => 'Could not delete file'],
        ];
    }

    private function list(string $path): array
    {
        if (!is_dir($path)) {
            return [
                'success' => false,
                'result' => null,
                'error' => ['type' => 'not_a_directory', 'message' => 'Path is not a directory'],
            ];
        }

        $files = scandir($path);
        $files = array_diff($files, ['.', '..']);

        $result = [];
        foreach ($files as $file) {
            $fullPath = "{$path}/{$file}";
            $result[] = [
                'name' => $file,
                'type' => is_dir($fullPath) ? 'directory' : 'file',
                'size' => is_file($fullPath) ? filesize($fullPath) : null,
            ];
        }

        return [
            'success' => true,
            'result' => $result,
            'error' => null,
        ];
    }

    private function exists(string $path): array
    {
        return [
            'success' => true,
            'result' => [
                'exists' => file_exists($path),
                'is_file' => is_file($path),
                'is_directory' => is_dir($path),
            ],
            'error' => null,
        ];
    }

    private function resolvePath(string $relativePath): string
    {
        // Default: storage/entity
        $basePath = $this->allowedPaths[0] ?? storage_path('entity');
        return rtrim($basePath, '/') . '/' . ltrim($relativePath, '/');
    }

    private function isPathAllowed(string $path): bool
    {
        // Resolve the directory part of the path
        $dir = dirname($path);
        $realDir = realpath($dir);

        // If the directory doesn't exist yet, walk up until we find one that does
        if ($realDir === false) {
            $checkDir = $dir;
            while ($checkDir !== '/' && $checkDir !== '.' && !is_dir($checkDir)) {
                $checkDir = dirname($checkDir);
            }
            $realDir = realpath($checkDir);
            if ($realDir === false) {
                return false;
            }
        }

        foreach ($this->allowedPaths as $allowed) {
            $allowedReal = realpath($allowed);
            if ($allowedReal === false) {
                continue;
            }
            if ($realDir === $allowedReal || str_starts_with($realDir . '/', $allowedReal . '/')) {
                return true;
            }
        }

        return false;
    }
}
