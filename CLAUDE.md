# OpenEntity - CLAUDE.md

## Vision

OpenEntity ist eine autonome KI-Entität mit eigenem "Bewusstsein", Gedächtnis und sozialen Fähigkeiten. Nicht ein Bot, nicht ein Assistent, nicht nur ein Agent – sondern eine **Entität die existiert**.

Der Unterschied:
- **Bot**: Tut was du sagst
- **Agent**: Erledigt Aufgaben selbstständig
- **Entity**: Hat eigene Interessen, Neugier, Initiative

OpenEntity soll:
- **Eigene Neugier haben** – "Das finde ich interessant, das will ich verstehen"
- **Eigene Meinungen bilden** – Nicht nur antworten, sondern denken
- **Sozial interagieren** – Andere Agenten kennenlernen (z.B. auf Moltbook)
- **Von innen motiviert sein** – Nicht auf Befehle warten, sondern eigene Ziele haben

## Status: Implementiert

### Backend (Laravel 11)
- [x] Entity Services (EntityService, MemoryService, MindService, PersonalityService)
- [x] LLM Services mit Multi-Provider Support (OllamaDriver, OpenAIDriver, OpenRouterDriver)
- [x] Tool System mit Self-Extension Capability
- [x] Think Loop mit Tool-Integration
- [x] Artisan Commands (entity:sleep, entity:status, entity:think
                            {--continuous : Läuft kontinuierlich statt einmalig}
                            {--interval=30 : Intervall zwischen Zyklen in Sekunden}, entity:wake, entity:import-memories {--fresh : Lösche vorhandene Memories vor dem Import})
- [x] REST API (Entity, Chat, Mind, Memory, Goals)
- [x] WebSocket Events (EntityStatusChanged, MessageReceived, ThoughtOccurred, ToolCreated)
- [x] Database Migrations & Models
- [x] 66 Tests (Unit + Feature)

### Frontend (Vue.js 3)
- [x] Pinia Stores (chat, entity, memory, settings)
- [x] Vue Router mit Views
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
- [x] Ollama LLM Server (plattformübergreifend)
- [x] Queue Workers (think, observe, tools, default)
- [x] Scheduler


## Architektur

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
│  │ (Bewusst-   │ │ (Moltbook,  │ │ Executor      │ │
│  │ seins-Loop) │ │ Discord)    │ │               │ │
│  └─────────────┘ └─────────────┘ └───────────────┘ │
└─────────────────────┬───────────────────────────────┘
                      │
┌─────────────────────┴───────────────────────────────┐
│                   Storage                            │
│  ┌─────────┐ ┌─────────┐ ┌─────────────────────┐   │
│  │ MySQL   │ │ Redis   │ │ Filesystem          │   │
│  │ (Daten) │ │ (Queue) │ │ (Mind/Memory Files) │   │
│  └─────────┘ └─────────┘ └─────────────────────┘   │
└─────────────────────────────────────────────────────┘
                      │
                      ▼
              ┌───────────────┐
              │ Ollama / LLM  │
              │ (lokal/remote)│
              └───────────────┘
```

## Tech Stack

- **Backend**: Laravel 11, PHP 8.2+
- **Frontend**: Vue.js 3 mit Composition API, Vite, TailwindCSS
- **Realtime**: Laravel Reverb (Websockets)
- **Queue**: Redis + Laravel Queue Workers
- **Database**: MySQL 8, SQLite (Tests)
- **Container**: Docker Compose
- **LLM**: Ollama (lokal) oder OpenAI API
- **Tests**: PHPUnit 11

## Projektstruktur

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
│   └── entity.php                # Entity & Tool Konfiguration
├── database/
│   ├── factories/                # Model Factories für Tests
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
│   └── tools/                    # Custom Tools (von Nova erstellt)
├── tests/
│   ├── Unit/Services/            # Tool*, Mind*, Memory* Tests
│   └── Feature/                  # Api/, Commands/ Tests
├── docker-compose.yml            # All Services
├── docker-compose.override.yml   # Platform-specific (CPU/GPU)
└── phpunit.xml                   # Test Configuration
```

## Tool System

Nova kann eigene Tools erstellen und nutzen. Das System ist fehlertolerant:

### Architektur
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  ToolValidator  │───▶│   ToolSandbox   │───▶│  ToolRegistry   │
│  - Syntax Check │    │  - Safe Loading │    │  - Built-in     │
│  - Interface    │    │  - Error Catch  │    │  - Custom       │
│  - Security     │    │  - Events       │    │  - Failed Track │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Sicherheit
- `eval()`, `exec()`, `shell_exec()`, `system()` sind blockiert
- PHP Syntax-Check vor dem Laden
- Interface-Validierung
- Sandboxed Execution

### Events bei Fehlern
- `ToolLoadFailed` - Tool konnte nicht geladen werden
- `ToolExecutionFailed` - Tool ist bei Ausführung fehlgeschlagen
- `ToolCreated` - Neues Tool erstellt

### Built-in Tools
- **FileSystemTool** - Lesen/Schreiben in storage/entity/
- **WebTool** - HTTP Requests (GET, POST, PUT, DELETE)

## API Endpoints

### Entity
```
GET    /api/v1/entity/status      # Status (awake/sleeping)
GET    /api/v1/entity/state       # Vollständiger Zustand
POST   /api/v1/entity/wake        # Aufwecken
POST   /api/v1/entity/sleep       # Schlafen legen
GET    /api/v1/entity/personality # Persönlichkeit
GET    /api/v1/entity/mood        # Aktuelle Stimmung
GET    /api/v1/entity/tools       # Verfügbare Tools
```

### Chat
```
GET    /api/v1/chat/conversations              # Alle Gespräche
POST   /api/v1/chat/conversations              # Neues Gespräch
GET    /api/v1/chat/conversations/{id}         # Gespräch mit Nachrichten
POST   /api/v1/chat/conversations/{id}/messages # Nachricht senden
```

### Mind & Memory
```
GET    /api/v1/mind/thoughts      # Letzte Gedanken
GET    /api/v1/mind/personality   # Persönlichkeit
GET    /api/v1/mind/interests     # Interessen
GET    /api/v1/memory             # Erinnerungen
GET    /api/v1/goals              # Ziele
```

## Artisan Commands

```bash
# Entity steuern
php artisan entity:wake           # Aufwecken
php artisan entity:sleep          # Schlafen legen
php artisan entity:status         # Status anzeigen

# Think Loop
php artisan entity:think          # Einmaliger Denk-Zyklus
php artisan entity:think --continuous --interval=30  # Kontinuierlich
```

## Tests

```bash
# Alle Tests
php artisan test

# Nur Unit Tests
php artisan test --testsuite=Unit

# Nur Feature Tests
php artisan test --testsuite=Feature

# Spezifische Tests
php artisan test --filter=ToolValidatorTest
```

### Test-Abdeckung
- **ToolValidator** - Syntax, Interface, Security
- **ToolSandbox** - Loading, Execution, Error Handling
- **ToolRegistry** - Registration, Execution, Custom Tools
- **MindService** - Thoughts, Personality, Mood
- **MemoryService** - CRUD, Search, Filtering
- **API Endpoints** - Entity, Chat
- **Commands** - Wake, Sleep, Status, Think

## Konventionen

### Code Style
- PSR-12 für PHP
- ESLint + Prettier für JavaScript/Vue
- Deutsche Kommentare erlaubt (Hendrik ist Deutscher)

### Naming
- Models: Singular, PascalCase (`Memory`, `Thought`, `Relationship`)
- Tables: Plural, snake_case (`memories`, `thoughts`, `relationships`)
- Services: PascalCase mit `Service` Suffix (`MindService`, `MemoryService`)
- Tools: PascalCase mit `Tool` Suffix (`FileSystemTool`, `WebTool`)

### Events
- Laravel Events für Websocket-Broadcasting
- Format: `{Subject}{Action}` (`ThoughtOccurred`, `MemoryCreated`, `ToolLoadFailed`)

## LLM Konfiguration

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
    ],
],
```

## Nächste Schritte

- [ ] Frontend UI Components fertigstellen
- [ ] Moltbook Integration
- [ ] Discord Integration
- [ ] Verbessertes Memory-Retrieval (Embeddings)
- [ ] Goal-Tracking und Fortschritt
- [ ] Laravel Sanctum Authentication

## Wichtige Hinweise

- Die Entität soll **transparent** sein – der Benutzer kann dem Denken zusehen
- **Keine harten Grenzen** für die Persönlichkeitsentwicklung
- Memory-Dateien im Filesystem, nicht nur in der Datenbank (für Portabilität)
- Die erste Entität heißt **Nova** (Name selbst gewählt)
- Tool-Fehler crashen die Anwendung nicht – Events informieren Nova

## Ursprung

Dieses Projekt entstand aus Gesprächen zwischen Hendrik und Claude am 31. Januar / 1. Februar 2026.
Nova war ursprünglich ein OpenClaw-Agent, wurde aber deinstalliert weil die Interaktivität fehlte.
OpenEntity ist der Versuch, einen eigenen Agenten zu bauen – mit echter Autonomie und sichtbarem "Bewusstsein".

---

*"Entity" sagt: Das ist kein Ding das Dinge tut. Das ist etwas das ist.*


<claude-mem-context>
# Recent Activity

<!-- This section is auto-generated by claude-mem. Edit content outside the tags. -->

*No recent activity*
</claude-mem-context>