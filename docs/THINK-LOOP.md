# Think Loop - The Consciousness Cycle

The Think Loop is the core of OpenEntity's autonomous behavior. It continuously observes the world, generates thoughts, and takes actions.

## Overview

```
┌──────────────────────────────────────────────────────────────┐
│                       THINK CYCLE                            │
│                                                              │
│   ┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐ │
│   │ OBSERVE │───▶│  THINK  │───▶│   ACT   │───▶│BROADCAST│ │
│   └─────────┘    └─────────┘    └─────────┘    └─────────┘ │
│        │              │              │              │        │
│        ▼              ▼              ▼              ▼        │
│   Gather         Generate       Execute        WebSocket    │
│   Context        Thought        Tools          Event        │
└──────────────────────────────────────────────────────────────┘
```

## Implementation

**File:** `app/Services/Entity/EntityService.php`

### Main Method: `think()`

```php
public function think(): ?Thought
{
    // 1. Check if awake
    if ($this->getStatus() !== 'awake') {
        return null;
    }

    // 2. Build context
    $context = $this->buildThinkContext();

    // 3. Gather observations
    $observations = $this->observe();

    // 4. Generate thought via LLM
    $response = $this->llm->generate($prompt);

    // 5. Parse and create thought
    $thought = $this->mindService->createThought($parsed);

    // 6. Execute actions if requested
    if ($parsed['wants_action']) {
        $this->executeAction($parsed);
    }

    // 7. Broadcast to frontend
    event(new ThoughtOccurred($thought));

    return $thought;
}
```

## Observation Phase

The `observe()` method gathers contextual information:

| Source | Probability | Description |
|--------|-------------|-------------|
| Conversations | 100% | Recent messages (last hour) |
| Active Goals | 100% | All goals with progress |
| Goal Focus | 50% | Prompt to work on priority goal |
| Past Thoughts | 30% | Resurface previous thought |
| Memory Recall | 25% | Random important memory |
| Failed Tools | 100% | Tools needing attention |
| Energy State | 100% | Current energy level |

## Prompt Structure

The think prompt is built in the entity's language preference:

```
=== WHO I AM ===
[Personality context from PersonalityService]

=== MY CURRENT STATE ===
Mood: [state] (Energy: [level])

=== MY GOALS ===
[Active goals with progress and motivation]

=== MY MEMORIES ===
[Relevant memories]

=== MY CAPABILITIES (TOOLS) ===
[Available tools with descriptions]

=== WHAT'S HAPPENING ===
[Observations gathered above]

=== YOUR TASK ===
Generate a thought. Focus on making progress on your goals.
```

## Response Format

The LLM response is parsed from this format:

```
THOUGHT_TYPE: observation|reflection|decision|emotion|curiosity
INTENSITY: 0.0-1.0
THOUGHT: [The actual thought content]
WANTS_ACTION: yes|no
TOOL: [tool name or 'none']
TOOL_PARAMS: [JSON parameters]
ACTION: [Free action description]
NEW_GOAL: [Optional: Create a new goal]
GOAL_PROGRESS: [Optional: Title|+increment|Note]
```

## Action Execution

When `WANTS_ACTION: yes`:

1. **Tool Execution** (if TOOL specified)
   ```php
   $result = $this->toolRegistry->execute($toolName, $params);
   ```

2. **Goal Creation** (if NEW_GOAL specified)
   ```php
   $this->createGoalFromThought($thought, $goalTitle);
   ```

3. **Goal Progress** (if GOAL_PROGRESS specified)
   ```php
   $this->updateGoalProgress($title, $increment, $note);
   ```

## Running the Think Loop

### Single Cycle
```bash
php artisan entity:think
```

### Continuous Mode
```bash
php artisan entity:think --continuous --interval=30
```

### Via Queue Worker
```bash
# Docker container: worker-think
php artisan queue:work redis --queue=think
```

## Events Broadcast

| Event | Channel | Data |
|-------|---------|------|
| ThoughtOccurred | entity.mind | thought details, tool execution |
| EntityStatusChanged | entity | new status |
| EntityQuestionAsked | entity | question for user |

## Energy Integration

If EnergyService is available:

```php
// Cost energy for thinking
$this->energyService->costThought($thought->intensity);

// Gain energy on goal progress
$this->energyService->gainGoalProgress($increment);

// Gain energy on goal completion
$this->energyService->gainGoalCompleted($goalTitle);
```

## Configuration

```php
// config/entity.php
'think_interval' => env('ENTITY_THINK_INTERVAL', 30), // seconds
```

## Related

- [Thoughts](THOUGHTS.md) - Thought creation and storage
- [Goals](GOALS.md) - Goal system integration
- [Tools](TOOLS.md) - Tool execution
