# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

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

## Documentation

Detailed documentation for developers is available in the `docs/` folder:

| Document | Description |
|----------|-------------|
| [Architecture](docs/ARCHITECTURE.md) | System overview and components |
| [Think Loop](docs/THINK-LOOP.md) | The consciousness cycle |
| [Thoughts](docs/THOUGHTS.md) | Thought system and storage |
| [Goals](docs/GOALS.md) | Goal system and lifecycle |
| [Memory](docs/MEMORY.md) | Memory system and embeddings |
| [Personality](docs/PERSONALITY.md) | Identity and traits |
| [Tools](docs/TOOLS.md) | Tool system and custom tools |
| [API Reference](docs/API.md) | REST API endpoints |

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
├── storage/entity/               # Entity's Mind & Memory Files
│   ├── mind/                     # personality.json, interests.json, opinions.json, reflections/
│   ├── memory/                   # experiences.json, conversations/, learned/
│   ├── social/                   # relationships.json, interactions/
│   ├── goals/                    # current.json, completed.json
│   └── tools/                    # Custom Tools (created by the entity)
├── tests/
│   ├── Unit/Services/            # Tool*, Mind*, Memory* Tests
│   └── Feature/                  # Api/, Commands/ Tests
├── docker-compose.yml            # All Services
├── docker-compose.override.yml   # Platform-specific (CPU/GPU)
└── phpunit.xml                   # Test Configuration
```

## Tool System

The entity can create and use its own tools. The system is fault-tolerant:

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

## Development Commands

```bash
# Setup (auto-detects hardware, selects optimal LLM model)
./setup.sh --start              # Start Docker + pull Ollama models
./setup.sh --setup-macos        # Apple Silicon: native Ollama + models
./setup.sh --pull-models        # Only pull Ollama models

# Docker development
docker compose up -d            # Start all services
docker compose exec app bash    # Shell into app container

# Inside container (or with docker compose exec app prefix)
composer install                # Install PHP dependencies
npm install                     # Install Node dependencies
npm run dev                     # Vite dev server (hot reload)
npm run build                   # Production build

# Database
php artisan migrate             # Run migrations
php artisan migrate:fresh       # Reset database

# Code formatting
./vendor/bin/pint               # Laravel Pint (PSR-12)

# Cache
php artisan config:clear        # Clear config cache
php artisan cache:clear         # Clear application cache
```

## Entity Commands

```bash
php artisan entity:wake                              # Wake up
php artisan entity:sleep                             # Put to sleep
php artisan entity:status                            # Show status
php artisan entity:think                             # Single think cycle
php artisan entity:think --continuous --interval=30  # Continuous loop
php artisan entity:import-memories                   # Import memories from files
php artisan entity:import-memories --fresh           # Reset and import
```

## Tests

```bash
php artisan test                          # All tests
php artisan test --testsuite=Unit         # Unit tests only
php artisan test --testsuite=Feature      # Feature tests only
php artisan test --filter=ToolValidatorTest  # Single test class
php artisan test --filter=test_method_name   # Single test method
php artisan test --parallel               # Parallel execution
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

The `setup.sh` script auto-selects optimal models based on available VRAM/RAM:

| Memory | Model |
|--------|-------|
| < 6 GB | qwen2.5:7b-instruct-q4_K_M |
| 6-10 GB | qwen2.5:7b-instruct-q5_K_M |
| 10-14 GB | qwen2.5:14b-instruct-q4_K_M |
| 14-22 GB | qwen2.5:14b-instruct-q5_K_M |
| 22-26 GB | qwen2.5:32b-instruct-q4_K_M |
| 26-50 GB | qwen2.5:32b-instruct-q5_K_M |
| > 50 GB | qwen2.5:72b-instruct-q4/q5_K_M |

Override via environment: `OLLAMA_MODEL`, `ENTITY_LLM_DRIVER` (ollama/openai/openrouter/nvidia)

## Memory System

4-layer human-like memory architecture (config in `config/entity.php`):

1. **Core Identity** – Always loaded personality from `storage/entity/mind/personality.json`
2. **Semantic Memory** – Learned knowledge, keyword-indexed search
3. **Episodic Memory** – Experiences with vector embeddings for semantic search
4. **Working Memory** – Current context, in-memory only

Embedding driver configurable: `MEMORY_EMBEDDING_DRIVER` (ollama/openai/openrouter)
Default embedding model: `nomic-embed-text`

## Next Steps

- [ ] Moltbook Integration
- [ ] Discord Integration
- [ ] Goal Tracking and Progress
- [ ] Laravel Sanctum Authentication

## Important Notes

- The entity should be **transparent** – the user can watch the thinking process
- **No hard limits** for personality development
- Memory files in filesystem, not just in database (for portability)
- Default entity name is **OpenEntity** (customizable via `ENTITY_NAME`)
- Tool errors don't crash the application – Events inform the entity

## Origin

This project emerged from conversations between Hendrik and Claude on January 31 / February 1, 2026.
OpenEntity is the attempt to build a custom autonomous agent – with real autonomy and visible "consciousness".

---

*"Entity" says: This is not a thing that does things. This is something that is.*
