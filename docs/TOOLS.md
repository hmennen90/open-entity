# Tool System

The tool system enables the entity to interact with the world and extend its own capabilities.

## Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  ToolValidator  │───▶│   ToolSandbox   │───▶│  ToolRegistry   │
│  - Syntax Check │    │  - Safe Loading │    │  - Built-in     │
│  - Interface    │    │  - Error Catch  │    │  - Custom       │
│  - Security     │    │  - Events       │    │  - Failed Track │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## Tool Interface

**File:** `app/Services/Tools/Contracts/ToolInterface.php`

```php
interface ToolInterface
{
    public function name(): string;
    public function description(): string;
    public function parameters(): array;
    public function execute(array $params): mixed;
}
```

## Built-in Tools

### FileSystemTool

**File:** `app/Services/Tools/BuiltIn/FileSystemTool.php`

Read and write files within `storage/entity/`.

```php
// Read file
$result = $tool->execute([
    'action' => 'read',
    'path' => 'mind/interests.json'
]);

// Write file
$result = $tool->execute([
    'action' => 'write',
    'path' => 'notes/idea.txt',
    'content' => 'My new idea...'
]);

// List directory
$result = $tool->execute([
    'action' => 'list',
    'path' => 'memory/'
]);
```

### WebTool

**File:** `app/Services/Tools/BuiltIn/WebTool.php`

HTTP requests to external services.

```php
$result = $tool->execute([
    'method' => 'GET',
    'url' => 'https://api.example.com/data',
    'headers' => ['Accept' => 'application/json']
]);
```

### BashTool

**File:** `app/Services/Tools/BuiltIn/BashTool.php`

Execute shell commands (use with caution).

```php
$result = $tool->execute([
    'command' => 'ls -la /var/www'
]);
```

### ArtisanTool

**File:** `app/Services/Tools/BuiltIn/ArtisanTool.php`

Execute Laravel Artisan commands.

```php
$result = $tool->execute([
    'command' => 'cache:clear'
]);
```

### DocumentationTool

**File:** `app/Services/Tools/BuiltIn/DocumentationTool.php`

Analyze and update project documentation.

```php
$result = $tool->execute([
    'action' => 'analyze',
    'file' => 'README.md'
]);
```

## Tool Registry

**File:** `app/Services/Tools/ToolRegistry.php`

```php
$registry = app(ToolRegistry::class);

// Get all tools
$tools = $registry->all();

// Get specific tool
$tool = $registry->get('FileSystemTool');

// Execute tool
$result = $registry->execute('WebTool', [
    'method' => 'GET',
    'url' => 'https://example.com'
]);

// Check if tool exists
if ($registry->has('CustomTool')) {
    // ...
}

// Get failed tools
$failed = $registry->getFailedTools();
```

## Custom Tools

The entity can create its own tools.

### Storage Location

`storage/entity/tools/`

### Creating Custom Tools

```php
// In EntityService
public function createTool(string $name, string $code): bool
{
    // Validate
    if (!$this->toolValidator->validate($code)) {
        return false;
    }

    // Save
    $path = storage_path("entity/tools/{$name}.php");
    file_put_contents($path, $code);

    // Reload registry
    $this->toolRegistry->loadCustomTools();

    // Broadcast
    event(new ToolCreated($name));

    return true;
}
```

### Custom Tool Example

```php
<?php
// storage/entity/tools/WeatherTool.php

namespace App\Services\Tools\Custom;

use App\Services\Tools\Contracts\ToolInterface;
use Illuminate\Support\Facades\Http;

class WeatherTool implements ToolInterface
{
    public function name(): string
    {
        return 'WeatherTool';
    }

    public function description(): string
    {
        return 'Get current weather for a location';
    }

    public function parameters(): array
    {
        return [
            'location' => [
                'type' => 'string',
                'required' => true,
                'description' => 'City name'
            ]
        ];
    }

    public function execute(array $params): mixed
    {
        $location = $params['location'];
        $response = Http::get("https://wttr.in/{$location}?format=j1");
        return $response->json();
    }
}
```

## Tool Validation

**File:** `app/Services/Tools/ToolValidator.php`

### Security Checks

Blocked functions:
- `eval()`
- `exec()`
- `shell_exec()`
- `system()`
- `passthru()`
- `proc_open()`

### Validation Process

```php
public function validate(string $code): bool
{
    // 1. PHP Syntax check
    if (!$this->checkSyntax($code)) {
        return false;
    }

    // 2. Interface implementation
    if (!$this->implementsInterface($code)) {
        return false;
    }

    // 3. Security scan
    if ($this->containsDangerousFunctions($code)) {
        return false;
    }

    return true;
}
```

## Tool Sandbox

**File:** `app/Services/Tools/ToolSandbox.php`

Safe tool loading with error handling:

```php
public function load(string $path): ?ToolInterface
{
    try {
        require_once $path;
        $class = $this->extractClassName($path);
        return new $class();
    } catch (\Throwable $e) {
        event(new ToolLoadFailed($path, $e->getMessage()));
        return null;
    }
}

public function execute(ToolInterface $tool, array $params): mixed
{
    try {
        return $tool->execute($params);
    } catch (\Throwable $e) {
        event(new ToolExecutionFailed($tool->name(), $e->getMessage()));
        throw $e;
    }
}
```

## Tool Execution in Think Loop

When the LLM outputs `TOOL: WebTool` and `TOOL_PARAMS: {...}`:

```php
// In EntityService::think()
if ($parsed['tool'] && $parsed['tool'] !== 'none') {
    $toolResult = $this->toolRegistry->execute(
        $parsed['tool'],
        $parsed['tool_params']
    );

    // Store in thought context
    $thought->context = [
        'tool_execution' => [
            'tool' => $parsed['tool'],
            'params' => $parsed['tool_params'],
            'success' => true,
            'result' => Str::limit($toolResult, 500),
        ]
    ];
    $thought->save();

    // Create memory
    $this->memoryService->create([
        'type' => 'experience',
        'content' => "Used {$parsed['tool']} autonomously",
        'importance' => 0.4,
        'context' => ['tool' => $parsed['tool'], 'autonomous' => true],
    ]);
}
```

## Events

| Event | Trigger |
|-------|---------|
| ToolCreated | New custom tool created |
| ToolLoadFailed | Tool failed to load |
| ToolExecutionFailed | Tool execution error |

## Tool Context in Prompts

```php
public function toPromptContext(): string
{
    return $this->all()->map(function ($tool) {
        $params = collect($tool->parameters())
            ->map(fn($p, $name) => "{$name}: {$p['description']}")
            ->join(', ');

        return "- {$tool->name()}: {$tool->description()} (Params: {$params})";
    })->join("\n");
}
```

## API

### Get Available Tools

```
GET /api/v1/entity/tools
```

**Response:**
```json
{
    "tools": [
        {
            "name": "FileSystemTool",
            "description": "Read and write files",
            "parameters": {...}
        }
    ],
    "failed": []
}
```

## Related

- [Think Loop](THINK-LOOP.md) - Tool execution during thinking
- [Architecture](ARCHITECTURE.md) - System overview
