# Memory System

The memory system stores and retrieves experiences, knowledge, and relationships.

## Model

**File:** `app/Models/Memory.php`

### Schema

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| type | string | Category of memory |
| content | text | Memory content |
| importance | float | 0.0-1.0 significance |
| context | json | Additional metadata |
| related_entity | string | Person/entity name |
| layer | string | Memory layer |
| is_consolidated | boolean | If processed |
| thought_id | bigint | Related thought (FK) |
| embedding | vector | For semantic search |
| recalled_at | timestamp | Last recall time |
| recall_count | int | Times recalled |
| created_at | timestamp | When created |

### Memory Types

| Type | Description | Example |
|------|-------------|---------|
| `experience` | Something done | "I used WebTool to fetch data" |
| `conversation` | Chat exchange | "User asked about AI ethics" |
| `learned` | Knowledge acquired | "PHP supports type hints" |
| `social` | Relationship info | "User prefers formal language" |
| `decision` | Choice made | "I decided to learn Python" |
| `achievement` | Goal completed | "Completed my first goal" |

### Memory Layers

| Layer | Description |
|-------|-------------|
| `episodic` | Personal experiences (autobiographical) |
| `semantic` | Facts and knowledge |
| `procedural` | How to do things |

## Services

### MemoryService

**File:** `app/Services/Entity/MemoryService.php`

```php
// Create memory
$memory = $memoryService->create([
    'type' => 'experience',
    'content' => 'I learned something new',
    'importance' => 0.6,
    'context' => ['source' => 'conversation'],
]);

// Get by importance
$important = $memoryService->getMostImportant(10);

// Get recent
$recent = $memoryService->getRecent(20);

// Search
$results = $memoryService->search('Python programming');

// Get by type
$experiences = $memoryService->getByType('experience');

// Get related to person
$aboutUser = $memoryService->getRelatedTo('Hendrik');

// Recall (strengthens memory)
$memoryService->recall($memory);

// Decay old memories
$memoryService->decay();
```

### SemanticMemoryService

**File:** `app/Services/Entity/SemanticMemoryService.php`

Uses embeddings for similarity search:

```php
// Search with semantic similarity
$results = $semanticMemory->search(
    query: "What do I know about programming?",
    limit: 10,
    threshold: 0.5
);

// Create with embedding
$memory = $semanticMemory->createWithEmbedding([
    'type' => 'learned',
    'content' => 'Laravel is a PHP framework',
    'layer' => 'semantic',
]);
```

### MemoryLayerManager

**File:** `app/Services/Entity/MemoryLayerManager.php`

Routes memories to appropriate layers:

```php
$manager = app(MemoryLayerManager::class);

// Auto-route based on type
$memory = $manager->routeToLayer([
    'type' => 'experience',  // → episodic
    'content' => '...',
]);

// Get by layer
$episodic = $manager->getByLayer('episodic');
```

## Storage

### Database

Primary storage in `memories` table with optional vector embeddings.

### Filesystem

Memories are also saved as JSON files:

**Location:** `storage/entity/memory/[type]/`

**Format:** `YYYY-MM-DD_HH-ii-ss_ID.json`

```json
{
    "id": 123,
    "type": "experience",
    "content": "I used WebTool to fetch weather data",
    "importance": 0.6,
    "context": {
        "tool": "WebTool",
        "autonomous": true
    },
    "created_at": "2026-02-04T15:30:00+00:00"
}
```

### Directory Structure

```
storage/entity/memory/
├── experience/
│   └── 2026-02-04_15-30-00_123.json
├── conversation/
├── learned/
├── decision/
├── social/
└── achievement/
```

## Memory Decay

Old, unimportant memories fade over time:

```php
// In MemoryService::decay()
Memory::where('importance', '<', 0.3)
    ->where('created_at', '<', now()->subDays(30))
    ->where('recall_count', 0)
    ->delete();
```

## Recall Strengthening

When a memory is recalled, it becomes stronger:

```php
public function recall(Memory $memory): void
{
    $memory->increment('recall_count');
    $memory->recalled_at = now();
    $memory->importance = min(1.0, $memory->importance + 0.05);
    $memory->save();
}
```

## Memory in Think Context

Memories are included in the think prompt:

```php
public function toPromptContext(int $limit = 5): string
{
    $memories = $this->getMostImportant($limit);

    return $memories->map(function ($m) {
        $age = $m->created_at->diffForHumans();
        return "- [{$m->type}] {$m->content} ({$age})";
    })->join("\n");
}
```

## API

### Get Memories

```
GET /api/v1/memory?type=experience&limit=20
```

### Search Memories

```
GET /api/v1/memory/search?q=programming
```

### Get by Type

```
GET /api/v1/memory/experiences
GET /api/v1/memory/learned
GET /api/v1/memory/conversations
```

## Memory Summaries

Long-term summaries for context compression:

**Table:** `memory_summaries`

```php
MemorySummary::create([
    'period_start' => now()->subDays(1),
    'period_end' => now(),
    'period_type' => 'daily',
    'summary' => 'Yesterday I learned about embeddings...',
    'source_memory_count' => 15,
]);
```

## Embeddings

For semantic search, memories can have vector embeddings:

**Driver:** `OllamaEmbeddingDriver`

```php
// Generate embedding
$embedding = $embeddingService->generate($memory->content);

// Store with memory
$memory->embedding = $embedding;
$memory->save();

// Search by similarity
$similar = Memory::whereNotNull('embedding')
    ->orderByRaw('embedding <-> ?', [$queryEmbedding])
    ->limit(10)
    ->get();
```

## Related

- [Think Loop](THINK-LOOP.md) - Memory in observations
- [Thoughts](THOUGHTS.md) - Thoughts create memories
- [Personality](PERSONALITY.md) - Core identity vs memories
