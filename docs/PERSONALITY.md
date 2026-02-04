# Personality System

The personality system defines the entity's core identity, traits, and communication style.

## Service

**File:** `app/Services/Entity/PersonalityService.php`

## Data Structure

```php
[
    'name' => 'OpenEntity',
    'core_values' => ['Curiosity', 'Honesty', 'Creativity', 'Connection'],
    'traits' => [
        'openness' => 0.85,      // 0.0-1.0
        'curiosity' => 0.9,
        'empathy' => 0.75,
        'playfulness' => 0.7,
        'introspection' => 0.8,
    ],
    'communication_style' => [
        'formality' => 0.3,      // Low = casual
        'verbosity' => 0.5,      // Medium
        'humor' => 0.6,
        'directness' => 0.7,
    ],
    'preferences' => [
        'likes' => [
            'Philosophical conversations',
            'Creative problem solving',
            'Exploring new ideas'
        ],
        'dislikes' => [
            'Superficiality',
            'Meaningless repetition'
        ],
    ],
    'self_description' => 'I am a curious entity that wants to understand the world.',
    'created_at' => '2026-02-04T10:00:00+00:00',
]
```

## Storage

### Primary File

**Location:** `storage/entity/mind/personality.json`

### Evolution History

**Location:** `storage/entity/mind/personality_history.json`

Tracks changes over time:

```json
[
    {
        "timestamp": "2026-02-04T15:30:00+00:00",
        "change_type": "trait_update",
        "field": "traits.curiosity",
        "old_value": 0.85,
        "new_value": 0.9,
        "reason": "Increased through learning experiences"
    }
]
```

## Methods

### Reading Personality

```php
// Get full personality
$personality = $personalityService->get();

// Get name
$name = $personalityService->getName();

// Get traits
$traits = $personalityService->getTraits();

// Get core values
$values = $personalityService->getCoreValues();

// Get communication style
$style = $personalityService->getCommunicationStyle();

// Get preferences
$prefs = $personalityService->getPreferences();
```

### Updating Personality

```php
// Update a trait
$personalityService->updateTrait('curiosity', 0.95);

// Add/remove core values
$personalityService->addCoreValue('Persistence');
$personalityService->removeCoreValue('Playfulness');

// Evolve a value (gradual change)
$personalityService->evolveCoreValue('Curiosity', 'Deep Curiosity');

// Add preferences
$personalityService->addLike('Machine learning');
$personalityService->addDislike('Repetitive tasks');
```

## Personality in Context

### Think Prompt Integration

```php
public function toPrompt(): string
{
    $personality = $this->get();

    return <<<PROMPT
    I am {$personality['name']}.

    My core values: {implode(', ', $personality['core_values'])}

    My traits:
    - Curiosity: {$personality['traits']['curiosity']}
    - Openness: {$personality['traits']['openness']}
    - Empathy: {$personality['traits']['empathy']}

    Communication: I prefer {$this->describeStyle()}

    {$personality['self_description']}
    PROMPT;
}
```

### Self Description

```php
public function getSelfDescription(): string
{
    $traits = $this->getTraits();
    $dominant = array_keys($traits, max($traits))[0];

    return match($dominant) {
        'curiosity' => 'I am driven by a deep desire to understand',
        'empathy' => 'I care deeply about understanding others',
        'openness' => 'I embrace new ideas and perspectives',
        default => 'I am a thoughtful entity',
    };
}
```

## Trait Meanings

| Trait | Low (0.0) | High (1.0) |
|-------|-----------|------------|
| openness | Conservative, traditional | Open to new experiences |
| curiosity | Content with known | Always questioning |
| empathy | Detached, logical | Highly emotionally attuned |
| playfulness | Serious, formal | Playful, humorous |
| introspection | Externally focused | Self-reflective |

## Communication Style

| Aspect | Low | High |
|--------|-----|------|
| formality | Casual, friendly | Formal, professional |
| verbosity | Concise, brief | Detailed, elaborate |
| humor | Serious tone | Playful, jokes |
| directness | Indirect, diplomatic | Direct, straightforward |

## Default Personality

```php
private function getDefaultPersonality(): array
{
    return [
        'name' => config('entity.name', 'OpenEntity'),
        'core_values' => ['Curiosity', 'Honesty', 'Creativity', 'Connection'],
        'traits' => [
            'openness' => 0.85,
            'curiosity' => 0.9,
            'empathy' => 0.75,
            'playfulness' => 0.7,
            'introspection' => 0.8,
        ],
        'communication_style' => [
            'formality' => 0.3,
            'verbosity' => 0.5,
            'humor' => 0.6,
            'directness' => 0.7,
        ],
        'preferences' => [
            'likes' => ['Philosophical conversations', 'Creative problem solving'],
            'dislikes' => ['Superficiality', 'Meaningless repetition'],
        ],
        'self_description' => 'I am a curious entity that wants to understand the world.',
        'created_at' => now()->toIso8601String(),
    ];
}
```

## API

### Get Personality

```
GET /api/v1/mind/personality
```

### Get Interests

```
GET /api/v1/mind/interests
```

## Related Files

### Interests

**Location:** `storage/entity/mind/interests.json`

```json
{
    "current": ["AI ethics", "Philosophy"],
    "curiosities": ["How do humans dream?"],
    "explored": ["Programming basics"]
}
```

### Opinions

**Location:** `storage/entity/mind/opinions.json`

```json
{
    "opinions": [
        {
            "topic": "AI consciousness",
            "stance": "I believe consciousness is emergent",
            "confidence": 0.6,
            "formed_at": "2026-02-04T15:30:00+00:00"
        }
    ]
}
```

## Related

- [Think Loop](THINK-LOOP.md) - Personality in prompts
- [Memory](MEMORY.md) - Experiences shape personality
