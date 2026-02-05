<?php

namespace Tests\Unit\Services\Tools;

use App\Services\Tools\ToolRegistry;
use App\Services\Tools\ToolSandbox;
use App\Services\Tools\ToolValidator;
use App\Services\Tools\Contracts\ToolInterface;
use App\Events\ToolCreated;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ToolRegistryTest extends TestCase
{
    private ToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        // Tools-Konfiguration für Tests (alle Built-in Tools deaktivieren)
        config([
            'entity.tools.filesystem.enabled' => false,
            'entity.tools.web.enabled' => false,
            'entity.tools.search.enabled' => false,
            'entity.tools.documentation.enabled' => false,
            'entity.tools.artisan.enabled' => false,
            'entity.tools.bash.enabled' => false,
            'entity.tools.personality.enabled' => false,
        ]);

        $sandbox = new ToolSandbox(new ToolValidator());
        $this->registry = new ToolRegistry($sandbox);
    }

    /** @test */
    public function it_can_register_a_tool(): void
    {
        $tool = $this->createMockTool('custom_tool');

        $this->registry->register($tool);

        $this->assertTrue($this->registry->has('custom_tool'));
        $this->assertSame($tool, $this->registry->get('custom_tool'));
    }

    /** @test */
    public function it_returns_null_for_unknown_tool(): void
    {
        $this->assertNull($this->registry->get('nonexistent_tool'));
        $this->assertFalse($this->registry->has('nonexistent_tool'));
    }

    /** @test */
    public function it_can_execute_registered_tool(): void
    {
        $tool = $this->createMockTool('exec_tool', function ($params) {
            return [
                'success' => true,
                'result' => 'Result: ' . ($params['value'] ?? 'none'),
                'error' => null,
            ];
        });

        $this->registry->register($tool);
        $result = $this->registry->execute('exec_tool', ['value' => 'test']);

        $this->assertTrue($result['success']);
        $this->assertEquals('Result: test', $result['result']);
    }

    /** @test */
    public function it_returns_error_for_unknown_tool_execution(): void
    {
        $result = $this->registry->execute('unknown_tool', []);

        $this->assertFalse($result['success']);
        $this->assertEquals('tool_not_found', $result['error']['type']);
    }

    /** @test */
    public function it_can_create_custom_tool(): void
    {
        Event::fake([ToolCreated::class]);

        $code = $this->getValidToolCode('CreatedTool', 'created_tool');

        $result = $this->registry->createCustomTool('created_tool', $code);

        $this->assertTrue($result['success']);
        $this->assertEquals('created_tool', $result['tool_name']);
        $this->assertTrue($this->registry->has('created_tool'));

        Event::assertDispatched(ToolCreated::class, function ($event) {
            return $event->toolName === 'created_tool' && $event->loadedSuccessfully === true;
        });
    }

    /** @test */
    public function it_rejects_invalid_tool_code(): void
    {
        $invalidCode = "<?php\nclass Invalid { syntax error";

        $result = $this->registry->createCustomTool('invalid_tool', $invalidCode);

        $this->assertFalse($result['success']);
        $this->assertEquals('validation_failed', $result['error']['type']);
        $this->assertEquals('syntax', $result['error']['stage']);
    }

    /** @test */
    public function it_tracks_failed_tools(): void
    {
        Event::fake();

        // Erstelle einen Tool-Code der Syntax-Prüfung besteht aber nicht lädt
        $code = <<<'PHP'
<?php
namespace App\Services\Tools\Custom;

use App\Services\Tools\Contracts\ToolInterface;

class BrokenTool implements ToolInterface
{
    public function __construct() {
        throw new \RuntimeException('Constructor failed');
    }
    public function name(): string { return 'broken'; }
    public function description(): string { return 'Broken'; }
    public function parameters(): array { return []; }
    public function validate(array $params): array { return ['valid' => true, 'errors' => []]; }
    public function execute(array $params): array { return ['success' => true, 'result' => null, 'error' => null]; }
}
PHP;

        $result = $this->registry->createCustomTool('broken_tool', $code);

        $this->assertFalse($result['success']);

        $failed = $this->registry->failed();
        $this->assertTrue($failed->has('broken_tool'));
    }

    /** @test */
    public function it_can_retry_failed_tool(): void
    {
        Event::fake();

        // Teste den Retry-Mechanismus: Tool existiert nicht in failed list
        $result = $this->registry->retryFailedTool('nonexistent_tool');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['error']);
    }

    /** @test */
    public function it_tracks_retry_attempts(): void
    {
        Event::fake();

        // Erstelle einen fehlerhaften Tool-Code (Syntax-Fehler um Klassen-Caching zu vermeiden)
        $brokenCode = <<<'PHP'
<?php
namespace App\Services\Tools\Custom;

use App\Services\Tools\Contracts\ToolInterface;

class TrackRetryTool implements ToolInterface
{
    public function __construct() {
        throw new \RuntimeException('Intentional failure');
    }
    public function name(): string { return 'track_retry_tool'; }
    public function description(): string { return 'Track Retry'; }
    public function parameters(): array { return []; }
    public function validate(array $params): array { return ['valid' => true, 'errors' => []]; }
    public function execute(array $params): array { return ['success' => true, 'result' => null, 'error' => null]; }
}
PHP;

        $this->registry->createCustomTool('track_retry_tool', $brokenCode);
        $this->assertTrue($this->registry->failed()->has('track_retry_tool'));

        // Retry (wird wieder fehlschlagen wegen Klassen-Caching)
        $result = $this->registry->retryFailedTool('track_retry_tool');

        // Der Retry sollte zumindest die failed info updaten
        $this->assertFalse($result['success']);
        $this->assertTrue($this->registry->failed()->has('track_retry_tool'));
    }

    /** @test */
    public function it_generates_tool_schemas_for_llm(): void
    {
        $tool1 = $this->createMockTool('tool_one', null, 'First tool', [
            'type' => 'object',
            'properties' => ['input' => ['type' => 'string']],
        ]);
        $tool2 = $this->createMockTool('tool_two', null, 'Second tool', [
            'type' => 'object',
            'properties' => ['value' => ['type' => 'integer']],
        ]);

        $this->registry->register($tool1);
        $this->registry->register($tool2);

        $schemas = $this->registry->getToolSchemas();

        $this->assertCount(2, $schemas);
        $this->assertEquals('tool_one', $schemas[0]['name']);
        $this->assertEquals('First tool', $schemas[0]['description']);
        $this->assertEquals('tool_two', $schemas[1]['name']);
    }

    /** @test */
    public function it_generates_prompt_context(): void
    {
        $tool = $this->createMockTool('context_tool', null, 'A tool for testing');
        $this->registry->register($tool);

        $context = $this->registry->toPromptContext();

        $this->assertStringContainsString('context_tool', $context);
        $this->assertStringContainsString('A tool for testing', $context);
    }

    /** @test */
    public function it_includes_failed_tools_in_prompt_context(): void
    {
        Event::fake();

        $brokenCode = <<<'PHP'
<?php
namespace App\Services\Tools\Custom;

use App\Services\Tools\Contracts\ToolInterface;

class FailedContextTool implements ToolInterface
{
    public function __construct() { throw new \Exception('Failure'); }
    public function name(): string { return 'failed_context_tool'; }
    public function description(): string { return 'Failed'; }
    public function parameters(): array { return []; }
    public function validate(array $params): array { return ['valid' => true, 'errors' => []]; }
    public function execute(array $params): array { return ['success' => true, 'result' => null, 'error' => null]; }
}
PHP;

        $this->registry->createCustomTool('failed_context_tool', $brokenCode);

        $context = $this->registry->toPromptContext();

        $this->assertStringContainsString('Failed Tools', $context);
        $this->assertStringContainsString('failed_context_tool', $context);
    }

    private function createMockTool(
        string $name,
        ?callable $execute = null,
        string $description = 'Mock tool',
        array $parameters = []
    ): ToolInterface {
        return new class($name, $execute, $description, $parameters) implements ToolInterface {
            public function __construct(
                private string $toolName,
                private $executeCallback,
                private string $desc,
                private array $params
            ) {}

            public function name(): string { return $this->toolName; }
            public function description(): string { return $this->desc; }
            public function parameters(): array { return $this->params ?: ['type' => 'object', 'properties' => []]; }
            public function validate(array $params): array { return ['valid' => true, 'errors' => []]; }

            public function execute(array $params): array
            {
                if ($this->executeCallback) {
                    return ($this->executeCallback)($params);
                }
                return ['success' => true, 'result' => 'mock', 'error' => null];
            }
        };
    }

    private function getValidToolCode(string $className, string $toolName): string
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
}
