<?php

namespace App\Services\Tools;

use Illuminate\Support\Facades\Log;

/**
 * Validates PHP code before loading it as a tool.
 *
 * Prevents faulty tools from crashing the entire application.
 */
class ToolValidator
{
    /**
     * Check if the PHP code is syntactically correct.
     */
    public function validateSyntax(string $code): array
    {
        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'tool_validate_');
        file_put_contents($tempFile, $code);

        // PHP Syntax-Check
        $output = [];
        $returnCode = 0;
        exec("php -l " . escapeshellarg($tempFile) . " 2>&1", $output, $returnCode);

        unlink($tempFile);

        if ($returnCode !== 0) {
            return [
                'valid' => false,
                'errors' => $output,
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
        ];
    }

    /**
     * Check if the code implements the ToolInterface.
     */
    public function validateInterface(string $code): array
    {
        $errors = [];

        // Check if the class implements the interface
        if (!str_contains($code, 'implements ToolInterface') &&
            !str_contains($code, 'implements \App\Services\Tools\Contracts\ToolInterface')) {
            $errors[] = 'Class must implement ToolInterface';
        }

        // Check for required methods
        $requiredMethods = ['name', 'description', 'parameters', 'execute', 'validate'];
        foreach ($requiredMethods as $method) {
            if (!preg_match('/public\s+function\s+' . $method . '\s*\(/', $code)) {
                $errors[] = "Missing required method: {$method}()";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check for potentially dangerous operations.
     */
    public function validateSecurity(string $code): array
    {
        $errors = [];
        $warnings = [];

        // Dangerous functions that are not allowed
        $forbidden = [
            'eval' => 'eval() is forbidden',
            'exec' => 'Direct exec() is forbidden - use BashTool instead',
            'shell_exec' => 'shell_exec() is forbidden - use BashTool instead',
            'system' => 'system() is forbidden - use BashTool instead',
            'passthru' => 'passthru() is forbidden - use BashTool instead',
            'proc_open' => 'proc_open() is forbidden',
            'popen' => 'popen() is forbidden',
            'call_user_func' => 'call_user_func() is forbidden - potential security bypass',
            'call_user_func_array' => 'call_user_func_array() is forbidden - potential security bypass',
        ];

        // Strip comments before checking to avoid false negatives
        $codeWithoutComments = preg_replace('#//.*$|/\*.*?\*/#ms', '', $code);

        foreach ($forbidden as $func => $message) {
            // Match at start of line or after non-alphanumeric character
            if (preg_match('/(^|[^a-zA-Z_])' . preg_quote($func, '/') . '\s*\(/m', $codeWithoutComments)) {
                $errors[] = $message;
            }
        }

        // Check for variable function calls (e.g., $func(), ${...}())
        if (preg_match('/\$\{?[a-zA-Z_]\w*\}?\s*\(/', $codeWithoutComments)) {
            $errors[] = 'Variable function calls are forbidden for security reasons';
        }

        // Check for backtick operator (shell execution)
        if (preg_match('/`[^`]+`/', $codeWithoutComments)) {
            $errors[] = 'Backtick operator (shell execution) is forbidden';
        }

        // Warnings for risky operations
        $risky = [
            'unlink' => 'File deletion detected - ensure proper path validation',
            'rmdir' => 'Directory deletion detected - ensure proper path validation',
            'file_put_contents' => 'File writing detected - ensure proper path validation',
            'curl_exec' => 'External HTTP request detected',
        ];

        foreach ($risky as $func => $message) {
            if (preg_match('/(^|[^a-zA-Z_])' . preg_quote($func, '/') . '\s*\(/m', $codeWithoutComments)) {
                $warnings[] = $message;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Complete validation of tool code.
     */
    public function validate(string $code): array
    {
        $syntaxResult = $this->validateSyntax($code);
        if (!$syntaxResult['valid']) {
            return [
                'valid' => false,
                'stage' => 'syntax',
                'errors' => $syntaxResult['errors'],
                'warnings' => [],
            ];
        }

        $interfaceResult = $this->validateInterface($code);
        if (!$interfaceResult['valid']) {
            return [
                'valid' => false,
                'stage' => 'interface',
                'errors' => $interfaceResult['errors'],
                'warnings' => [],
            ];
        }

        $securityResult = $this->validateSecurity($code);
        if (!$securityResult['valid']) {
            return [
                'valid' => false,
                'stage' => 'security',
                'errors' => $securityResult['errors'],
                'warnings' => $securityResult['warnings'] ?? [],
            ];
        }

        return [
            'valid' => true,
            'stage' => 'passed',
            'errors' => [],
            'warnings' => $securityResult['warnings'] ?? [],
        ];
    }
}
