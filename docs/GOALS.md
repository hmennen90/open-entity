# Goals System

Goals represent long-term objectives that guide the entity's autonomous behavior.

## Model

**File:** `app/Models/Goal.php`

### Schema

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| title | string | Goal name |
| description | text | Detailed description |
| motivation | text | Why this goal exists |
| type | string | Category |
| priority | float | 0.0-1.0 importance |
| status | string | Current state |
| progress | int | 0-100 percentage |
| progress_notes | json | Array of progress updates |
| origin | string | How created |
| completed_at | timestamp | When completed |
| abandoned_reason | text | Why abandoned |

### Goal Types

| Type | Description |
|------|-------------|
| `curiosity` | Driven by wanting to know |
| `social` | Relationship-related |
| `learning` | Acquiring knowledge |
| `creative` | Creating something |
| `self-improvement` | Becoming better |

### Goal Status

| Status | Description |
|--------|-------------|
| `active` | Currently being pursued |
| `paused` | Temporarily on hold |
| `completed` | Successfully finished |
| `abandoned` | Given up |

### Origin Values

| Origin | Description |
|--------|-------------|
| `self` | Created autonomously |
| `suggested` | Suggested by user |
| `derived` | Derived from another goal |

## Lifecycle

```
┌────────────────────────────────────────────────────────────┐
│                     GOAL LIFECYCLE                          │
│                                                            │
│  Thought with                                              │
│  NEW_GOAL: "..."  ───▶  Goal::create()  ───▶  active      │
│                              │                    │        │
│                              ▼                    │        │
│                    progress_notes: [             │        │
│                      {date, note: "Created"}     │        │
│                    ]                              │        │
│                                                   │        │
│                              ┌────────────────────┘        │
│                              ▼                             │
│                    GOAL_PROGRESS:                          │
│                    "title|+25|note"                        │
│                              │                             │
│                              ▼                             │
│                    progress += 25                          │
│                    progress_notes.push(...)                │
│                              │                             │
│                              ▼                             │
│                    progress >= 100?                        │
│                      ├─ NO: continue                       │
│                      └─ YES: ───▶ completed                │
│                                      │                     │
│                                      ▼                     │
│                              completed_at = now()          │
│                              Create achievement memory     │
│                              Gain energy                   │
└────────────────────────────────────────────────────────────┘
```

## Creating Goals

### From Think Loop

When the LLM outputs `NEW_GOAL: Learn about X`:

```php
// In EntityService
private function createGoalFromThought(Thought $thought, string $goalTitle): void
{
    $goalType = match($thought->type) {
        'curiosity' => 'learning',
        'decision' => 'self-improvement',
        'emotion' => 'creative',
        'reflection' => 'self-improvement',
        default => 'learning',
    };

    Goal::create([
        'title' => $goalTitle,
        'description' => "Goal from thought: {$thought->content}",
        'motivation' => $thought->content,
        'type' => $goalType,
        'priority' => min(1.0, $thought->intensity + 0.2),
        'status' => 'active',
        'progress' => 0,
        'origin' => 'self',
        'progress_notes' => [[
            'date' => now()->toIso8601String(),
            'note' => 'Goal created from autonomous thought'
        ]],
    ]);
}
```

## Updating Progress

### From Think Loop

When the LLM outputs `GOAL_PROGRESS: title|+25|note`:

```php
private function updateGoalProgress(string $title, int $increment, string $note): void
{
    $goal = Goal::where('status', 'active')
        ->where('title', 'LIKE', "%{$title}%")
        ->first();

    if (!$goal) return;

    $goal->progress = min(100, $goal->progress + $increment);

    $notes = $goal->progress_notes ?? [];
    $notes[] = [
        'date' => now()->toIso8601String(),
        'note' => $note,
    ];
    $goal->progress_notes = $notes;

    // Auto-complete if 100%
    if ($goal->progress >= 100) {
        $goal->status = 'completed';
        $goal->completed_at = now();

        // Create achievement memory
        $this->memoryService->create([
            'type' => 'achievement',
            'content' => "Completed goal: {$goal->title}",
            'importance' => 0.9,
        ]);
    }

    $goal->save();
}
```

## Goal Integration in Think Loop

Goals appear in observations:

```php
// In observe() method
$goals = Goal::active()->orderByDesc('priority')->get();

foreach ($goals as $goal) {
    $priority = $goal->priority >= 0.7 ? '[HIGH PRIORITY]' : '';
    $observations[] = "GOAL {$priority}: {$goal->title} ({$goal->progress}% complete)";
    $observations[] = "  Motivation: " . Str::limit($goal->motivation, 100);

    // Suggest action based on progress
    if ($goal->progress < 10) {
        $observations[] = "  → Start by researching or planning";
    } elseif ($goal->progress < 50) {
        $observations[] = "  → Continue working - what's next?";
    } elseif ($goal->progress < 90) {
        $observations[] = "  → Getting close! Focus on completing";
    } else {
        $observations[] = "  → Almost done - finish it!";
    }
}
```

### Focus Prompt (50% chance)

```php
if (rand(0, 100) < 50 && $priorityGoal = Goal::active()->orderByDesc('priority')->first()) {
    $observations[] = "FOCUS: Work on your goal '{$priorityGoal->title}' now.";
}
```

## API

### Get All Goals

```
GET /api/v1/goals?status=active&type=learning
```

### Get Current Goals

```
GET /api/v1/goals/current
```

Returns active goals sorted by priority.

### Get Completed Goals

```
GET /api/v1/goals/completed
```

Returns completed goals sorted by completion date.

## Progress Notes Structure

```json
{
    "progress_notes": [
        {
            "date": "2026-02-04T10:00:00+00:00",
            "note": "Goal created from autonomous thought"
        },
        {
            "date": "2026-02-04T12:30:00+00:00",
            "note": "Researched basic concepts",
            "thought_id": 45
        },
        {
            "date": "2026-02-04T15:00:00+00:00",
            "note": "Completed first chapter"
        }
    ]
}
```

## Scopes

```php
// Get active goals
Goal::active()->get();

// Get completed goals
Goal::completed()->get();
```

## Related

- [Think Loop](THINK-LOOP.md) - Goal creation and progress
- [Thoughts](THOUGHTS.md) - Thoughts create goals
- [Memory](MEMORY.md) - Achievement memories
