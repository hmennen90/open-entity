<?php

namespace Tests\Unit\Services\Tools;

use App\Services\Tools\ToolSandbox;
use App\Services\Tools\ToolValidator;
use App\Services\Tools\Contracts\ToolInterface;
use App\Events\ToolLoadFailed;
use App\Events\ToolExecutionFailed;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolSandboxTest extends TestCase
{
    private ToolSandbox $sandbox;
    private string $toolsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = new ToolSandbox(new ToolValidator());
        $this->toolsPath = storage_path('entity-test/tools');
    }

    #[Test]
    public function it_loads_valid_tool_from_file(): void
    {
        $toolCode = $this->createValidToolCode('LoadableTool', 'loadable_tool');
        $filePath = $this->toolsPath . '/loadable_tool.php';
        file_put_contents($filePath, $toolCode);

        $result = $this->sandbox->loadFromFile($filePath);

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(ToolInterface::class, $result['tool']);
        $this->assertEquals('loadable_tool', $result['tool']->name());
        $this->assertNull($result['error']);
    }

    #[Test]
    public function it_returns_error_for_nonexistent_file(): void
    {
        $result = $this->sandbox->loadFromFile('/nonexistent/path/tool.php');

        $this->assertFalse($result['success']);
        $this->assertNull($result['tool']);
        $this->assertEquals('file_not_found', $result['error']['type']);
    }

    #[Test]
    public function it_fires_event_on_load_failure(): void
    {
        Event::fake([ToolLoadFailed::class]);

        $invalidCode = "<?php\nclass Broken { invalid syntax";
        $filePath = $this->toolsPath . '/broken_tool.php';
        file_put_contents($filePath, $invalidCode);

        $this->sandbox->loadFromFile($filePath);

        Event::assertDispatched(ToolLoadFailed::class, function ($event) {
            return $event->stage === 'syntax';
        });
    }

    #[Test]
    public function it_executes_tool_safely(): void
    {
        $tool = $this->createMockTool('test_tool', function (array $params) {
            return [
                'success' => true,
                'result' => 'Processed: ' . ($params['input'] ?? ''),
                'error' => null,
            ];
        });

        $result = $this->sandbox->execute($tool, ['input' => 'hello']);

        $this->assertTrue($result['success']);
        $this->assertEquals('Processed: hello', $result['result']);
    }

    #[Test]
    public function it_catches_tool_execution_exceptions(): void
    {
        Event::fake([ToolExecutionFailed::class]);

        $tool = $this->createMockTool('failing_tool', function (array $params) {
            throw new \RuntimeException('Something went wrong');
        });

        $result = $this->sandbox->execute($tool, []);

        $this->assertFalse($result['success']);
        $this->assertEquals('execution_exception', $result['error']['type']);
        $this->assertStringContains('Something went wrong', $result['error']['message']);

        Event::assertDispatched(ToolExecutionFailed::class);
    }

    #[Test]
    public function it_validates_parameters_before_execution(): void
    {
        $tool = $this->createMockTool('validated_tool', function (array $params) {
            return ['success' => true, 'result' => 'ok', 'error' => null];
        }, function (array $params) {
            if (empty($params['required_field'])) {
                return ['valid' => false, 'errors' => ['required_field is required']];
            }
            return ['valid' => true, 'errors' => []];
        });

        $result = $this->sandbox->execute($tool, []);

        $this->assertFalse($result['success']);
        $this->assertEquals('validation_failed', $result['error']['type']);
        $this->assertContains('required_field is required', $result['error']['errors']);
    }

    #[Test]
    public function it_rejects_tools_with_forbidden_functions(): void
    {
        Event::fake([ToolLoadFailed::class]);

        $dangerousCode = <<<'PHP'
<?php
namespace App\Services\Tools\Custom;

use App\Services\Tools\Contracts\ToolInterface;

class DangerousTool implements ToolInterface
{
    public function name(): string { return 'dangerous'; }
    public function description(): string { return 'Bad tool'; }
    public function parameters(): array { return []; }
    public function validate(array $params): array { return ['valid' => true, 'errors' => []]; }
    public function execute(array $params): array {
        eval($params['code']);
        return ['success' => true, 'result' => null, 'error' => null];
    }
}
PHP;
        $filePath = $this->toolsPath . '/dangerous_tool.php';
        file_put_contents($filePath, $dangerousCode);

        $result = $this->sandbox->loadFromFile($filePath);

        $this->assertFalse($result['success']);
        $this->assertEquals('validation_failed', $result['error']['type']);
        $this->assertEquals('security', $result['error']['stage']);

        Event::assertDispatched(ToolLoadFailed::class, function ($event) {
            return $event->stage === 'security';
        });
    }

    private function createValidToolCode(string $className, string $toolName): string
    {
        return <<<PHP
<?php
namespace App\Services\Tools\Custom;

use App\Services\Tools\Contracts\ToolInterface;

class {$className} implements ToolInterface
{
    public function name(): string { return '{$toolName}'; }
    public function description(): string { return 'Test tool'; }
    public function parameters(): array { return ['type' => 'object', 'properties' => []]; }
    public function validate(array \$params): array { return ['valid' => true, 'errors' => []]; }
    public function execute(array \$params): array {
        return ['success' => true, 'result' => 'executed', 'error' => null];
    }
}
PHP;
    }

    private function createMockTool(string $name, callable $execute, ?callable $validate = null): ToolInterface
    {
        return new class($name, $execute, $validate) implements ToolInterface {
            public function __construct(
                private string $toolName,
                private $executeCallback,
                private $validateCallback = null
            ) {}

            public function name(): string { return $this->toolName; }
            public function description(): string { return 'Mock tool'; }
            public function parameters(): array { return []; }

            public function validate(array $params): array
            {
                if ($this->validateCallback) {
                    return ($this->validateCallback)($params);
                }
                return ['valid' => true, 'errors' => []];
            }

            public function execute(array $params): array
            {
                return ($this->executeCallback)($params);
            }
        };
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
