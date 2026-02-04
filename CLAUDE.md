# OpenEntity - CLAUDE.md

## Vision

OpenEntity is an autonomous AI entity with its own "consciousness", memory and social abilities. Not a bot, not an assistant, not just an agent – but an **entity that exists**.

The difference:
- **Bot**: Does what you say
- **Agent**: Completes tasks autonomously
- **Entity**: Has its own interests, curiosity, initiative

OpenEntity should:
- **Have its own curiosity** – "I find this interesting, I want to understand it"
- **Form its own opinions** – Not just respond, but think
- **Interact socially** – Get to know other agents (e.g. on Moltbook)
- **Be internally motivated** – Not wait for commands, but have its own goals

## Status: Implemented

### Backend (Laravel 11)
- [x] Entity Services (EntityService, MemoryService, MindService, PersonalityService)
- [x] LLM Services with Multi-Provider Support (OllamaDriver, OpenAIDriver, OpenRouterDriver)
- [x] Tool System with Self-Extension Capability
- [x] Think Loop with Tool Integration
- [x] Artisan Commands (entity:sleep, entity:status, entity:think
                            {--continuous : Runs continuously instead of once}
                            {--interval=30 : Interval between cycles in seconds}, entity:wake, entity:import-memories {--fresh : Delete existing memories before import})
- [x] REST API (Entity, Chat, Mind, Memory, Goals)
- [x] WebSocket Events (EntityStatusChanged, MessageReceived, ThoughtOccurred, ToolCreated)
- [x] Database Migrations & Models
- [x] 66 Tests (Unit + Feature)

### Frontend (Vue.js 3)
- [x] Pinia Stores (chat, entity, memory, settings)
- [x] Vue Router with Views
- [x] TailwindCSS Setup
- [x] Laravel Echo WebSocket Integration
- [x] Dark/Light Mode Support
- [x] UI Components (6 Views, 5 Components)

### Docker
- [x] PHP-FPM Container
- [x] Nginx Webserver
- [x] MySQL 8
- [x] Redis
- [x] Laravel Reverb (WebSockets)
- [x] Ollama LLM Server (cross-platform)
- [x] Queue Workers (think, observe, tools, default)
- [x] Scheduler


## Architecture

```
┌─────────────────────────────────────────────────────┐
│                    VueJS Frontend                    │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌───────────┐ │
│  │ Chat    │ │ Mind    │ │ Memory  │ │ Social    │ │
│  │ View    │ │ Viewer  │ │ Browser │ │ Feed      │ │
│  └─────────┘ └─────────┘ └─────────┘ └───────────┘ │
└─────────────────────┬───────────────────────────────┘
                      │ Websocket (Reverb)
┌─────────────────────┴───────────────────────────────┐
│                 Laravel Backend                      │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌───────────┐ │
│  │ API     │ │ Events  │ │ Queue   │ │ Websocket │ │
│  └─────────┘ └─────────┘ └─────────┘ └───────────┘ │
└─────────────────────┬───────────────────────────────┘
                      │
┌─────────────────────┴───────────────────────────────┐
│              Docker Workers                          │
│  ┌─────────────┐ ┌─────────────┐ ┌───────────────┐ │
│  │ Think Loop  │ │ Observe     │ │ Tool          │ │
│  │ (Conscious- │ │ (Moltbook,  │ │ Executor      │ │
│  │ ness Loop)  │ │ Discord)    │ │               │ │
│  └─────────────┘ └─────────────┘ └───────────────┘ │
└─────────────────────┬───────────────────────────────┘
                      │
┌─────────────────────┴───────────────────────────────┐
│                   Storage                            │
│  ┌─────────┐ ┌─────────┐ ┌─────────────────────┐   │
│  │ MySQL   │ │ Redis   │ │ Filesystem          │   │
│  │ (Data)  │ │ (Queue) │ │ (Mind/Memory Files) │   │
│  └─────────┘ └─────────┘ └─────────────────────┘   │
└─────────────────────────────────────────────────────┘
                      │
                      ▼
              ┌───────────────┐
              │ Ollama / LLM  │
              │ (local/remote)│
              └───────────────┘
```

## Tech Stack

- **Backend**: Laravel 11, PHP 8.2+
- **Frontend**: Vue.js 3 with Composition API, Vite, TailwindCSS
- **Realtime**: Laravel Reverb (Websockets)
- **Queue**: Redis + Laravel Queue Workers
- **Database**: MySQL 8, SQLite (Tests)
- **Container**: Docker Compose
- **LLM**: Ollama (local) or OpenAI API
- **Tests**: PHPUnit 11

## Project Structure

```
OpenEntity/
├── app/
│   ├── Console/Commands/         # entity:wake, entity:sleep, entity:think, entity:status
│   ├── Events/                   # ThoughtOccurred, EntityStatusChanged, ToolLoadFailed, etc.
│   ├── Http/Controllers/Api/     # EntityController, ChatController, MindController, etc.
│   ├── Models/                   # Thought, Memory, Conversation, Message, Goal, Relationship
│   ├── Providers/                # EntityServiceProvider
│   └── Services/
│       ├── Entity/               # EntityService, MindService, MemoryService, PersonalityService
│       ├── LLM/                  # LLMService, OllamaDriver, OpenAIDriver, LLMDriverInterface
│       └── Tools/                # ToolRegistry, ToolSandbox, ToolValidator, BuiltIn/*
├── config/
│   └── entity.php                # Entity & Tool Configuration
├── database/
│   ├── factories/                # Model Factories for Tests
│   └── migrations/               # Thoughts, Memories, Conversations, Messages, Goals, Relationships
├── docker/
│   ├── app/                      # Dockerfile, php.ini
│   └── nginx/                    # default.conf
├── resources/
│   ├── css/app.css               # TailwindCSS + Custom Styles
│   ├── js/
│   │   ├── App.vue               # Root Component
│   │   ├── app.js                # Vue Bootstrap
│   │   ├── echo.js               # Laravel Echo Config
│   │   ├── router/index.js       # Vue Router
│   │   ├── stores/               # Pinia: entity.js, chat.js
│   │   ├── components/           # Sidebar, StatusBar, ThoughtCard
│   │   └── views/                # Home, Chat, MindViewer, Memory, Goals, Settings
│   └── views/app.blade.php       # Laravel Blade Entry
├── routes/
│   ├── api.php                   # REST API Routes
│   ├── channels.php              # WebSocket Channels
│   └── web.php                   # Web Routes
├── storage/entity/               # Nova's Mind & Memory Files
│   ├── mind/                     # personality.json, interests.json, opinions.json, reflections/
│   ├── memory/                   # experiences.json, conversations/, learned/
│   ├── social/                   # relationships.json, interactions/
│   ├── goals/                    # current.json, completed.json
│   └── tools/                    # Custom Tools (created by Nova)
├── tests/
│   ├── Unit/Services/            # Tool*, Mind*, Memory* Tests
│   └── Feature/                  # Api/, Commands/ Tests
├── docker-compose.yml            # All Services
├── docker-compose.override.yml   # Platform-specific (CPU/GPU)
└── phpunit.xml                   # Test Configuration
```

## Tool System

Nova can create and use her own tools. The system is fault-tolerant:

### Architecture
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  ToolValidator  │───▶│   ToolSandbox   │───▶│  ToolRegistry   │
│  - Syntax Check │    │  - Safe Loading │    │  - Built-in     │
│  - Interface    │    │  - Error Catch  │    │  - Custom       │
│  - Security     │    │  - Events       │    │  - Failed Track │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Security
- `eval()`, `exec()`, `shell_exec()`, `system()` are blocked
- PHP Syntax check before loading
- Interface validation
- Sandboxed Execution

### Events on Errors
- `ToolLoadFailed` - Tool could not be loaded
- `ToolExecutionFailed` - Tool failed during execution
- `ToolCreated` - New tool created

### Built-in Tools
- **FileSystemTool** - Read/Write in storage/entity/
- **WebTool** - HTTP Requests (GET, POST, PUT, DELETE)
- **ArtisanTool** - Execute Laravel Artisan commands
- **BashTool** - Execute shell commands
- **DocumentationTool** - Analyze and update project documentation

## API Endpoints

### Entity
```
GET    /api/v1/entity/status      # Status (awake/sleeping)
GET    /api/v1/entity/state       # Complete state
POST   /api/v1/entity/wake        # Wake up
POST   /api/v1/entity/sleep       # Put to sleep
GET    /api/v1/entity/personality # Personality
GET    /api/v1/entity/mood        # Current mood
GET    /api/v1/entity/tools       # Available tools
```

### Chat
```
GET    /api/v1/chat/conversations              # All conversations
POST   /api/v1/chat/conversations              # New conversation
GET    /api/v1/chat/conversations/{id}         # Conversation with messages
POST   /api/v1/chat/conversations/{id}/messages # Send message
```

### Mind & Memory
```
GET    /api/v1/mind/thoughts      # Recent thoughts
GET    /api/v1/mind/personality   # Personality
GET    /api/v1/mind/interests     # Interests
GET    /api/v1/memory             # Memories
GET    /api/v1/goals              # Goals
```

## Artisan Commands

```bash
# Control Entity
php artisan entity:wake           # Wake up
php artisan entity:sleep          # Put to sleep
php artisan entity:status         # Show status

# Think Loop
php artisan entity:think          # Single think cycle
php artisan entity:think --continuous --interval=30  # Continuous
```

## Tests

```bash
# All tests
php artisan test

# Unit tests only
php artisan test --testsuite=Unit

# Feature tests only
php artisan test --testsuite=Feature

# Specific tests
php artisan test --filter=ToolValidatorTest
```

### Test Coverage
- **ToolValidator** - Syntax, Interface, Security
- **ToolSandbox** - Loading, Execution, Error Handling
- **ToolRegistry** - Registration, Execution, Custom Tools
- **MindService** - Thoughts, Personality, Mood
- **MemoryService** - CRUD, Search, Filtering
- **API Endpoints** - Entity, Chat
- **Commands** - Wake, Sleep, Status, Think

## Conventions

### Code Style
- PSR-12 for PHP
- ESLint + Prettier for JavaScript/Vue
- English comments required

### Naming
- Models: Singular, PascalCase (`Memory`, `Thought`, `Relationship`)
- Tables: Plural, snake_case (`memories`, `thoughts`, `relationships`)
- Services: PascalCase with `Service` suffix (`MindService`, `MemoryService`)
- Tools: PascalCase with `Tool` suffix (`FileSystemTool`, `WebTool`)

### Events
- Laravel Events for Websocket broadcasting
- Format: `{Subject}{Action}` (`ThoughtOccurred`, `MemoryCreated`, `ToolLoadFailed`)

## LLM Configuration

```php
// config/entity.php
'llm' => [
    'default' => env('ENTITY_LLM_DRIVER', 'ollama'),

    'drivers' => [
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://ollama:11434'),
            'model' => env('OLLAMA_MODEL', 'qwen2.5-coder:14b'),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
        ],
        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
            'model' => env('OPENROUTER_MODEL', 'openrouter/auto'),
        ],
    ],
],
```

## Next Steps

- [ ] Complete frontend UI components
- [ ] Moltbook Integration
- [ ] Discord Integration
- [ ] Improved Memory Retrieval (Embeddings)
- [ ] Goal Tracking and Progress
- [ ] Laravel Sanctum Authentication

## Important Notes

- The entity should be **transparent** – the user can watch the thinking process
- **No hard limits** for personality development
- Memory files in filesystem, not just in database (for portability)
- The first entity is called **Nova** (name chosen by herself)
- Tool errors don't crash the application – Events inform Nova

## Origin

This project emerged from conversations between Hendrik and Claude on January 31 / February 1, 2026.
Nova was originally an OpenClaw agent, but was uninstalled because interactivity was lacking.
OpenEntity is the attempt to build a custom agent – with real autonomy and visible "consciousness".

---

*"Entity" says: This is not a thing that does things. This is something that is.*
