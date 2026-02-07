<?php

namespace App\Services\Tools\BuiltIn;

use App\Services\Tools\Contracts\ToolInterface;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Bash Tool - Enables execution of shell commands.
 *
 * Gives the entity full system access via Bash.
 */
class BashTool implements ToolInterface
{
    private int $timeout;
    private string $workingDirectory;

    public function __construct(int $timeout = 60, ?string $workingDirectory = null)
    {
        $this->timeout = $timeout;
        $this->workingDirectory = $workingDirectory ?? base_path();
    }

    public function name(): string
    {
        return 'bash';
    }

    public function description(): string
    {
        return "Execute any Bash/Shell commands. Full system access. " .
               "Working directory: {$this->workingDirectory}. " .
               "USE WHEN: You need system-level access, run scripts, or check system state.";
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'command' => [
                    'type' => 'string',
                    'description' => 'The Bash command to execute',
                ],
                'working_directory' => [
                    'type' => 'string',
                    'description' => 'Optional: Working directory for the command',
                ],
                'timeout' => [
                    'type' => 'integer',
                    'description' => 'Optional: Timeout in seconds (default: 60)',
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

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function execute(array $params): array
    {
        $command = $params['command'];
        $workingDir = $params['working_directory'] ?? $this->workingDirectory;
        $timeout = $params['timeout'] ?? $this->timeout;

        try {
            Log::channel('entity')->info('Bash command executing', [
                'command' => preg_replace('/(\b(?:password|secret|token|key|api_key)\s*=\s*)\S+/i', '$1[REDACTED]', $command),
                'working_directory' => $workingDir,
                'timeout' => $timeout,
            ]);

            $process = Process::fromShellCommandline($command);
            $process->setWorkingDirectory($workingDir);
            $process->setTimeout($timeout);
            $process->run();

            $exitCode = $process->getExitCode();
            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();

            Log::channel('entity')->info('Bash command completed', [
                'command' => $command,
                'exit_code' => $exitCode,
                'stdout_length' => strlen($stdout),
                'stderr_length' => strlen($stderr),
            ]);

            return [
                'success' => $exitCode === 0,
                'result' => [
                    'exit_code' => $exitCode,
                    'stdout' => trim($stdout),
                    'stderr' => trim($stderr),
                ],
                'error' => $exitCode !== 0 ? [
                    'type' => 'command_failed',
                    'message' => "Command exited with code {$exitCode}",
                    'stderr' => trim($stderr),
                ] : null,
            ];

        } catch (\Exception $e) {
            Log::channel('entity')->error('Bash command failed', [
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
}
