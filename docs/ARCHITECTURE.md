# OpenEntity Architecture

## Overview

OpenEntity is an autonomous AI entity with its own consciousness, memory, and social capabilities. This document describes the overall system architecture.

## System Components

```
┌─────────────────────────────────────────────────────────────┐
│                      FRONTEND (Vue.js)                       │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌───────┐ │
│  │  Chat   │ │  Mind   │ │ Memory  │ │  Goals  │ │Social │ │
│  │  View   │ │ Viewer  │ │ Browser │ │  View   │ │ Feed  │ │
│  └────┬────┘ └────┬────┘ └────┬────┘ └────┬────┘ └───┬───┘ │
└───────┼──────────┼──────────┼──────────┼──────────┼───────┘
        │          │          │          │          │
        └──────────┴──────────┴──────────┴──────────┘
                              │
                    WebSocket (Laravel Reverb)
                              │
┌─────────────────────────────┴───────────────────────────────┐
│                    BACKEND (Laravel 11)                      │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                    API Layer                          │  │
│  │  EntityController │ ChatController │ MindController   │  │
│  │  GoalController   │ MemoryController                  │  │
│  └──────────────────────────────────────────────────────┘  │
│                              │                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                  Service Layer                        │  │
│  │  EntityService    │ MindService    │ MemoryService    │  │
│  │  PersonalityService │ EnergyService │ LLMService      │  │
│  └──────────────────────────────────────────────────────┘  │
│                              │                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                   Tool System                         │  │
│  │  ToolRegistry │ ToolValidator │ ToolSandbox          │  │
│  │  Built-in: FileSystem, Web, Bash, Artisan, Docs      │  │
│  └──────────────────────────────────────────────────────┘  │
└──────────────────────────────┬──────────────────────────────┘
                               │
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
        ▼                      ▼                      ▼
┌───────────────┐    ┌─────────────────┐    ┌───────────────┐
│    MySQL      │    │     Redis       │    │  Filesystem   │
│   Database    │    │  Cache/Queue    │    │   Storage     │
│               │    │                 │    │               │
│ - thoughts    │    │ - entity:status │    │ - mind/       │
│ - memories    │    │ - queue jobs    │    │ - memory/     │
│ - goals       │    │ - cache         │    │ - tools/      │
│ - messages    │    │                 │    │               │
└───────────────┘    └─────────────────┘    └───────────────┘
                               │
                               ▼
                     ┌─────────────────┐
                     │   Ollama LLM    │
                     │   (Local AI)    │
                     └─────────────────┘
```

## Core Services

| Service | Responsibility | Documentation |
|---------|----------------|---------------|
| EntityService | Think loop, chat, status control | [THINK-LOOP.md](THINK-LOOP.md) |
| MindService | Thoughts, mood, interests | [THOUGHTS.md](THOUGHTS.md) |
| MemoryService | Memory CRUD, search, decay | [MEMORY.md](MEMORY.md) |
| PersonalityService | Identity, traits, values | [PERSONALITY.md](PERSONALITY.md) |
| ToolRegistry | Tool management, execution | [TOOLS.md](TOOLS.md) |
| LLMService | AI model communication (DB-based config) | - |
| EnergyService | Energy system (optional) | - |

## Data Flow

### Think Cycle
```
Observe → Think (LLM) → Create Thought → Execute Action → Broadcast
```

### Chat Flow
```
User Message → Context Building → LLM Response → Memory Creation → Reply
```

### Goal Flow
```
Thought → Goal Creation → Progress Updates → Completion → Achievement
```

## Queue Workers

| Worker | Queue | Purpose |
|--------|-------|---------|
| worker-think | think | Consciousness loop processing |
| worker-observe | observe | Social platform monitoring |
| worker-tools | tools | Tool execution |
| worker-default | default | General tasks |

## Events

| Event | Channel | Trigger |
|-------|---------|---------|
| ThoughtOccurred | entity.mind | New thought created |
| EntityStatusChanged | entity | Wake/sleep state change |
| MessageReceived | chat.{id} | New chat message |
| ToolCreated | entity.tools | New custom tool |
| ToolLoadFailed | entity.tools | Tool loading error |

## Configuration

Main configuration in `config/entity.php`:

```php
return [
    'name' => env('ENTITY_NAME', 'OpenEntity'),
    'storage_path' => storage_path('entity'),
    'think_interval' => env('ENTITY_THINK_INTERVAL', 30),
    'tools' => [
        'enabled' => true,
        'custom_path' => storage_path('entity/tools'),
    ],
];
```

### LLM Configuration

LLM providers are configured exclusively via the database (`llm_configurations` table), not via ENV variables. The `LlmConfigurationSeeder` creates a default Ollama configuration on first startup, and is run idempotently on every container start.

Manage configurations via the API:
```
GET    /api/v1/llm/configurations          # List all
POST   /api/v1/llm/configurations          # Create new
PUT    /api/v1/llm/configurations/{id}     # Update
DELETE /api/v1/llm/configurations/{id}     # Delete
POST   /api/v1/llm/configurations/{id}/test    # Test connection
POST   /api/v1/llm/configurations/{id}/default # Set as default
```

If no LLM configuration exists in the database, the think loop will log a warning and wait (without incrementing the failure counter) until a configuration is added.

## Related Documentation

- [Think Loop](THINK-LOOP.md) - The consciousness cycle
- [Thoughts](THOUGHTS.md) - Thought system
- [Goals](GOALS.md) - Goal system
- [Memory](MEMORY.md) - Memory system
- [Personality](PERSONALITY.md) - Personality system
- [Tools](TOOLS.md) - Tool system
- [API Reference](API.md) - REST API endpoints
