<?php

namespace Tests\Unit\Services\Tools;

use App\Services\Tools\ToolValidator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolValidatorTest extends TestCase
{
    private ToolValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ToolValidator();
    }

    #[Test]
    public function it_validates_correct_php_syntax(): void
    {
        $validCode = <<<'PHP'
<?php
class TestTool {
    public function test(): string {
        return 'hello';
    }
}
PHP;

        $result = $this->validator->validateSyntax($validCode);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function it_detects_syntax_errors(): void
    {
        $invalidCode = <<<'PHP'
<?php
class TestTool {
    public function test(): string {
        return 'hello'  // Missing semicolon
    }
}
PHP;

        $result = $this->validator->validateSyntax($invalidCode);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    #[Test]
    public function it_validates_tool_interface_implementation(): void
    {
        $validTool = <<<'PHP'
<?php
namespace App\Services\Tools\Custom;

use App\Services\Tools\Contracts\ToolInterface;

class ValidTool implements ToolInterface
{
    public function name(): string { return 'test'; }
    public function description(): string { return 'Test tool'; }
    public function parameters(): array { return []; }
    public function validate(array $params): array { return ['valid' => true, 'errors' => []]; }
    public function execute(array $params): array { return ['success' => true]; }
}
PHP;

        $result = $this->validator->validateInterface($validTool);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function it_detects_missing_interface_declaration(): void
    {
        $noInterface = <<<'PHP'
<?php
class InvalidTool
{
    public function name(): string { return 'test'; }
    public function description(): string { return 'Test tool'; }
    public function parameters(): array { return []; }
    public function validate(array $params): array { return ['valid' => true, 'errors' => []]; }
    public function execute(array $params): array { return ['success' => true]; }
}
PHP;

        $result = $this->validator->validateInterface($noInterface);

        $this->assertFalse($result['valid']);
        $this->assertContains('Class must implement ToolInterface', $result['errors']);
    }

    #[Test]
    public function it_detects_missing_required_methods(): void
    {
        $missingMethods = <<<'PHP'
<?php
use App\Services\Tools\Contracts\ToolInterface;

class IncompleteTool implements ToolInterface
{
    public function name(): string { return 'test'; }
    // Missing: description, parameters, validate, execute
}
PHP;

        $result = $this->validator->validateInterface($missingMethods);

        $this->assertFalse($result['valid']);
        $this->assertContains('Missing required method: description()', $result['errors']);
        $this->assertContains('Missing required method: parameters()', $result['errors']);
        $this->assertContains('Missing required method: validate()', $result['errors']);
        $this->assertContains('Missing required method: execute()', $result['errors']);
    }

    #[Test]
    public function it_blocks_eval_function(): void
    {
        $dangerousCode = <<<'PHP'
<?php
class DangerousTool implements ToolInterface
{
    public function execute(array $params): array {
        eval($params['code']);
        return ['success' => true];
    }
}
PHP;

        $result = $this->validator->validateSecurity($dangerousCode);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty(array_filter($result['errors'], fn($e) => str_contains($e, 'eval')));
    }

    #[Test]
    public function it_blocks_exec_function(): void
    {
        $dangerousCode = <<<'PHP'
<?php
class DangerousTool {
    public function execute(array $params): array {
        exec($params['command']);
        return ['success' => true];
    }
}
PHP;

        $result = $this->validator->validateSecurity($dangerousCode);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty(array_filter($result['errors'], fn($e) => str_contains($e, 'exec')));
    }

    #[Test]
    public function it_blocks_shell_exec_function(): void
    {
        $dangerousCode = <<<'PHP'
<?php
class DangerousTool {
    public function execute(array $params): array {
        shell_exec($params['command']);
        return ['success' => true];
    }
}
PHP;

        $result = $this->validator->validateSecurity($dangerousCode);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty(array_filter($result['errors'], fn($e) => str_contains($e, 'shell_exec')));
    }

    #[Test]
    public function it_blocks_system_function(): void
    {
        $dangerousCode = <<<'PHP'
<?php
class DangerousTool {
    public function execute(array $params): array {
        system($params['command']);
        return ['success' => true];
    }
}
PHP;

        $result = $this->validator->validateSecurity($dangerousCode);

        $this->assertFalse($result['valid']);
    }

    #[Test]
    public function it_warns_about_file_operations(): void
    {
        $riskyCode = <<<'PHP'
<?php
class RiskyTool {
    public function execute(array $params): array {
        file_put_contents($params['path'], $params['content']);
        return ['success' => true];
    }
}
PHP;

        $result = $this->validator->validateSecurity($riskyCode);

        $this->assertTrue($result['valid']); // Risky but allowed
        $this->assertNotEmpty($result['warnings']);
    }

    #[Test]
    public function it_allows_safe_code(): void
    {
        $safeCode = <<<'PHP'
<?php
namespace App\Services\Tools\Custom;

use App\Services\Tools\Contracts\ToolInterface;

class SafeTool implements ToolInterface
{
    public function name(): string { return 'safe_tool'; }
    public function description(): string { return 'A safe tool'; }
    public function parameters(): array { return ['type' => 'object', 'properties' => []]; }
    public function validate(array $params): array { return ['valid' => true, 'errors' => []]; }
    public function execute(array $params): array {
        $result = strtoupper($params['input'] ?? '');
        return ['success' => true, 'result' => $result, 'error' => null];
    }
}
PHP;

        $result = $this->validator->validate($safeCode);

        $this->assertTrue($result['valid']);
        $this->assertEquals('passed', $result['stage']);
    }

    #[Test]
    public function full_validation_stops_at_first_failure(): void
    {
        $syntaxError = <<<'PHP'
<?php
class Broken {
    // Syntax error - missing closing brace
PHP;

        $result = $this->validator->validate($syntaxError);

        $this->assertFalse($result['valid']);
        $this->assertEquals('syntax', $result['stage']);
    }
}
