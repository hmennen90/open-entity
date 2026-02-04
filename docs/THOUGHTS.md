# Thoughts System

Thoughts are the conscious output of OpenEntity - what the entity is "thinking" at any moment.

## Model

**File:** `app/Models/Thought.php`

### Schema

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| content | text | The thought content |
| type | string | Category of thought |
| trigger | string | What triggered this thought |
| context | json | Additional context data |
| intensity | float | 0.0-1.0 strength |
| led_to_action | boolean | If action was taken |
| action_taken | text | Description of action |
| created_at | timestamp | When thought occurred |

### Thought Types

| Type | Description | Example |
|------|-------------|---------|
| `observation` | Noticing something | "I see a new message from User" |
| `reflection` | Internal analysis | "I've been curious about AI lately" |
| `decision` | Making a choice | "I'll learn more about this topic" |
| `emotion` | Feeling something | "I feel satisfied with my progress" |
| `curiosity` | Questions/wondering | "What would happen if...?" |

## Service

**File:** `app/Services/Entity/MindService.php`

### Creating Thoughts

```php
$thought = $mindService->createThought([
    'content' => 'This is interesting...',
    'type' => 'curiosity',
    'trigger' => 'conversation',
    'intensity' => 0.7,
    'led_to_action' => false,
]);
```

### Retrieving Thoughts

```php
// Get recent thoughts
$thoughts = $mindService->getRecentThoughts(10);

// Get thoughts by type
$reflections = Thought::where('type', 'reflection')->latest()->get();

// Get high-intensity thoughts
$intense = Thought::where('intensity', '>=', 0.7)->get();
```

## Storage

### Database
All thoughts are stored in the `thoughts` table.

### Filesystem (Reflections)
High-intensity thoughts (>= 0.7) are also saved as files:

**Location:** `storage/entity/mind/reflections/`

**Format:** `YYYY-MM-DD_HH-ii-ss.json`

```json
{
    "thought_id": 123,
    "type": "reflection",
    "content": "I realize that learning is my core drive...",
    "trigger": "goal_completion",
    "intensity": 0.85,
    "timestamp": "2026-02-04T15:30:00+00:00"
}
```

## Mood Estimation

Thoughts influence the entity's mood:

```php
$mood = $mindService->estimateMood();
// Returns:
// [
//     'state' => 'curious',      // curious|contemplative|emotional|determined|observant
//     'valence' => 0.0,          // Placeholder for sentiment
//     'energy' => 0.65,          // Average intensity
//     'dominant_thought_type' => 'curiosity'
// ]
```

## Events

### ThoughtOccurred

**File:** `app/Events/ThoughtOccurred.php`

Broadcast when a thought is created:

```php
event(new ThoughtOccurred($thought));
```

**Channel:** `entity.mind`

**Event Name:** `thought.occurred`

**Payload:**
```json
{
    "id": 123,
    "type": "observation",
    "content": "I noticed...",
    "intensity": 0.6,
    "led_to_action": true,
    "action_taken": "Used WebTool to fetch data",
    "created_at": "2026-02-04T15:30:00Z",
    "tool_execution": {
        "tool": "WebTool",
        "success": true,
        "result_preview": "..."
    }
}
```

## API

### Get Recent Thoughts

```
GET /api/v1/mind/thoughts?limit=20&type=observation
```

**Parameters:**
- `limit` (int, max 100, default 20)
- `type` (string, optional filter)

**Response:**
```json
{
    "thoughts": [
        {
            "id": 123,
            "content": "...",
            "type": "observation",
            "intensity": 0.7,
            "led_to_action": true,
            "action_taken": "...",
            "created_at": "2026-02-04T15:30:00Z"
        }
    ]
}
```

## Relationship with Memories

Thoughts can create memories:

```php
// In Thought model
public function memories()
{
    return $this->hasMany(Memory::class);
}
```

When a thought leads to an action:
```php
$memory = Memory::create([
    'type' => 'experience',
    'content' => "I used WebTool to fetch data",
    'thought_id' => $thought->id,
    'importance' => 0.4,
]);
```

## Related

- [Think Loop](THINK-LOOP.md) - How thoughts are generated
- [Memory](MEMORY.md) - Memory creation from thoughts
- [Goals](GOALS.md) - Thoughts can create goals
