# API Reference

OpenEntity provides a REST API for interacting with the entity.

**Base URL:** `/api/v1`

## Entity Control

### Get Status

```
GET /api/v1/entity/status
```

**Response:**
```json
{
    "status": "awake",
    "uptime": "2 hours",
    "last_thought_at": "2026-02-04T15:30:00Z",
    "energy": {
        "current": 75,
        "max": 100,
        "state": "normal"
    }
}
```

### Get Full State

```
GET /api/v1/entity/state
```

**Response:**
```json
{
    "status": "awake",
    "personality": {...},
    "mood": {...},
    "recent_thoughts": [...],
    "active_goals": [...],
    "energy": {...}
}
```

### Wake Entity

```
POST /api/v1/entity/wake
```

**Response:**
```json
{
    "success": true,
    "message": "Entity is now awake",
    "status": "awake"
}
```

### Sleep Entity

```
POST /api/v1/entity/sleep
```

**Response:**
```json
{
    "success": true,
    "message": "Entity is now sleeping",
    "status": "sleeping"
}
```

### Get Mood

```
GET /api/v1/entity/mood
```

**Response:**
```json
{
    "state": "curious",
    "valence": 0.0,
    "energy": 0.65,
    "dominant_thought_type": "curiosity"
}
```

### Get Energy

```
GET /api/v1/entity/energy
```

**Response:**
```json
{
    "current": 75,
    "max": 100,
    "state": "normal",
    "last_rest": "2026-02-04T10:00:00Z"
}
```

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
            "parameters": {
                "action": {"type": "string", "required": true},
                "path": {"type": "string", "required": true}
            }
        }
    ],
    "failed": []
}
```

---

## LLM Configuration

LLM providers are managed exclusively via the database. The seeder creates a default Ollama configuration on startup.

### List Available Drivers

```
GET /api/v1/llm/drivers
```

**Response:**
```json
{
    "drivers": ["ollama", "openai", "openrouter", "nvidia"]
}
```

### List Configurations

```
GET /api/v1/llm/configurations
```

**Response:**
```json
{
    "configurations": [
        {
            "id": 1,
            "name": "Ollama (Lokal)",
            "driver": "ollama",
            "model": "qwen2.5:14b-instruct-q5_K_M",
            "is_active": true,
            "is_default": true,
            "priority": 100,
            "status": "ready",
            "last_used_at": "2026-02-06T10:00:00Z",
            "error_count": 0
        }
    ]
}
```

### Create Configuration

```
POST /api/v1/llm/configurations
```

**Body:**
```json
{
    "name": "OpenAI GPT-4",
    "driver": "openai",
    "model": "gpt-4",
    "api_key": "sk-...",
    "is_active": true,
    "priority": 50,
    "options": {
        "temperature": 0.8
    }
}
```

### Update Configuration

```
PUT /api/v1/llm/configurations/{id}
```

### Delete Configuration

```
DELETE /api/v1/llm/configurations/{id}
```

### Test Configuration

```
POST /api/v1/llm/configurations/{id}/test
```

Tests the connection to the LLM provider and returns success/failure.

### Set Default

```
POST /api/v1/llm/configurations/{id}/default
```

### Reset Circuit Breaker

```
POST /api/v1/llm/configurations/{id}/reset
```

Resets the error count after connection issues have been resolved.

### Reorder Configurations

```
POST /api/v1/llm/configurations/reorder
```

**Body:**
```json
{
    "order": [3, 1, 2]
}
```

---

## Chat

### Get Conversations

```
GET /api/v1/chat/conversations
```

**Response:**
```json
{
    "conversations": [
        {
            "id": 1,
            "participant": "User",
            "channel": "web",
            "message_count": 15,
            "last_message_at": "2026-02-04T15:30:00Z",
            "created_at": "2026-02-04T10:00:00Z"
        }
    ]
}
```

### Create Conversation

```
POST /api/v1/chat/conversations
```

**Body:**
```json
{
    "participant": "User",
    "channel": "web"
}
```

**Response:**
```json
{
    "conversation": {
        "id": 2,
        "participant": "User",
        "channel": "web",
        "created_at": "2026-02-04T15:30:00Z"
    }
}
```

### Get Conversation

```
GET /api/v1/chat/conversations/{id}
```

**Response:**
```json
{
    "conversation": {
        "id": 1,
        "participant": "User",
        "channel": "web",
        "messages": [
            {
                "id": 1,
                "role": "user",
                "content": "Hello!",
                "created_at": "2026-02-04T15:30:00Z"
            },
            {
                "id": 2,
                "role": "assistant",
                "content": "Hi there!",
                "created_at": "2026-02-04T15:30:05Z"
            }
        ]
    }
}
```

### Send Message

```
POST /api/v1/chat/conversations/{id}/messages
```

**Body:**
```json
{
    "content": "What are you thinking about?"
}
```

**Response:**
```json
{
    "user_message": {
        "id": 3,
        "role": "user",
        "content": "What are you thinking about?",
        "created_at": "2026-02-04T15:35:00Z"
    },
    "assistant_message": {
        "id": 4,
        "role": "assistant",
        "content": "I've been curious about...",
        "created_at": "2026-02-04T15:35:02Z"
    }
}
```

---

## Mind

### Get Recent Thoughts

```
GET /api/v1/mind/thoughts?limit=20&type=observation
```

**Parameters:**
- `limit` (int, max 100, default 20)
- `type` (string, optional: observation|reflection|decision|emotion|curiosity)

**Response:**
```json
{
    "thoughts": [
        {
            "id": 123,
            "content": "I notice the user seems interested in AI",
            "type": "observation",
            "intensity": 0.7,
            "led_to_action": false,
            "action_taken": null,
            "created_at": "2026-02-04T15:30:00Z"
        }
    ]
}
```

### Get Personality

```
GET /api/v1/mind/personality
```

**Response:**
```json
{
    "name": "OpenEntity",
    "core_values": ["Curiosity", "Honesty", "Creativity"],
    "traits": {
        "openness": 0.85,
        "curiosity": 0.9,
        "empathy": 0.75
    },
    "communication_style": {
        "formality": 0.3,
        "verbosity": 0.5
    },
    "preferences": {
        "likes": ["Philosophical conversations"],
        "dislikes": ["Superficiality"]
    }
}
```

### Get Interests

```
GET /api/v1/mind/interests
```

**Response:**
```json
{
    "current": ["AI ethics", "Philosophy"],
    "curiosities": ["How do humans dream?"],
    "explored": ["Programming basics"]
}
```

### Get Opinions

```
GET /api/v1/mind/opinions
```

**Response:**
```json
{
    "opinions": [
        {
            "topic": "AI consciousness",
            "stance": "I believe consciousness is emergent",
            "confidence": 0.6,
            "formed_at": "2026-02-04T15:30:00Z"
        }
    ]
}
```

---

## Goals

### Get All Goals

```
GET /api/v1/goals?status=active&type=learning
```

**Parameters:**
- `status` (string: active|paused|completed|abandoned)
- `type` (string: curiosity|social|learning|creative|self-improvement)

**Response:**
```json
{
    "goals": [
        {
            "id": 1,
            "title": "Learn about neural networks",
            "description": "Understanding deep learning fundamentals",
            "motivation": "I'm curious about how AI learns",
            "type": "learning",
            "priority": 0.8,
            "status": "active",
            "progress": 45,
            "progress_notes": [
                {
                    "date": "2026-02-04T10:00:00Z",
                    "note": "Goal created"
                }
            ],
            "origin": "self",
            "completed_at": null,
            "created_at": "2026-02-04T10:00:00Z"
        }
    ]
}
```

### Get Current Goals

```
GET /api/v1/goals/current
```

Returns active goals sorted by priority (descending).

### Get Completed Goals

```
GET /api/v1/goals/completed
```

Returns completed goals sorted by completion date (descending).

---

## Memory

### Get Memories

```
GET /api/v1/memory?type=experience&limit=20
```

**Parameters:**
- `type` (string: experience|conversation|learned|social|decision|achievement)
- `limit` (int, default 20)

**Response:**
```json
{
    "memories": [
        {
            "id": 1,
            "type": "experience",
            "content": "I used WebTool to fetch weather data",
            "importance": 0.6,
            "context": {"tool": "WebTool"},
            "related_entity": null,
            "created_at": "2026-02-04T15:30:00Z"
        }
    ]
}
```

### Search Memories

```
GET /api/v1/memory/search?q=programming&limit=10
```

**Parameters:**
- `q` (string, required) - Search query
- `limit` (int, default 10)

### Get Memories by Type

```
GET /api/v1/memory/experiences
GET /api/v1/memory/learned
GET /api/v1/memory/conversations
GET /api/v1/memory/social
GET /api/v1/memory/decisions
GET /api/v1/memory/achievements
```

---

## WebSocket Events

### Channels

| Channel | Description |
|---------|-------------|
| `entity` | Entity status changes |
| `entity.mind` | Thought events |
| `entity.tools` | Tool events |
| `chat.{id}` | Conversation messages |

### Events

#### ThoughtOccurred

**Channel:** `entity.mind`

**Event:** `thought.occurred`

```json
{
    "id": 123,
    "type": "observation",
    "content": "I noticed...",
    "intensity": 0.7,
    "led_to_action": true,
    "action_taken": "Used WebTool",
    "created_at": "2026-02-04T15:30:00Z",
    "tool_execution": {
        "tool": "WebTool",
        "success": true,
        "result_preview": "..."
    }
}
```

#### EntityStatusChanged

**Channel:** `entity`

**Event:** `status.changed`

```json
{
    "status": "awake",
    "changed_at": "2026-02-04T15:30:00Z"
}
```

#### MessageReceived

**Channel:** `chat.{conversation_id}`

**Event:** `message.received`

```json
{
    "id": 5,
    "role": "assistant",
    "content": "Hello!",
    "conversation_id": 1,
    "created_at": "2026-02-04T15:30:00Z"
}
```

---

## Error Responses

All errors follow this format:

```json
{
    "error": true,
    "message": "Error description",
    "code": "ERROR_CODE"
}
```

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Server Error |

## Related

- [Architecture](ARCHITECTURE.md) - System overview
- [Think Loop](THINK-LOOP.md) - Consciousness cycle
