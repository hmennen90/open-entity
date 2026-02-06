<?php

namespace App\Services\Entity;

use App\Models\Memory;
use App\Models\Conversation;
use Illuminate\Support\Collection;

/**
 * MemoryService - Manages the entity's memories
 *
 * Memories are the "What have I experienced?" - experiences, conversations,
 * learned knowledge and social interactions.
 */
class MemoryService
{
    private string $storagePath;

    public function __construct()
    {
        $this->storagePath = config('entity.storage_path') . '/memory';
    }

    /**
     * Create a new memory.
     */
    public function create(array $data): Memory
    {
        $memory = Memory::create([
            'type' => $data['type'] ?? 'experience',
            'content' => $data['content'],
            'summary' => $data['summary'] ?? null,
            'importance' => $data['importance'] ?? 0.5,
            'emotional_valence' => $data['emotional_valence'] ?? 0.0,
            'context' => $data['context'] ?? null,
            'related_entity' => $data['related_entity'] ?? null,
            'thought_id' => $data['thought_id'] ?? null,
        ]);

        // Also save as JSON file for portability
        $this->saveToFile($memory);

        return $memory;
    }

    /**
     * Get the most important memories.
     */
    public function getMostImportant(int $limit = 10): Collection
    {
        return Memory::orderByDesc('importance')
            ->limit($limit)
            ->get();
    }

    /**
     * Get the most recent memories.
     */
    public function getRecent(int $limit = 20): Collection
    {
        return Memory::latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get memories of a specific type.
     */
    public function getByType(string $type, int $limit = 20): Collection
    {
        return Memory::where('type', $type)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Search in memories.
     */
    public function search(string $query, int $limit = 10): Collection
    {
        return Memory::where('content', 'like', "%{$query}%")
            ->orWhere('summary', 'like', "%{$query}%")
            ->orderByDesc('importance')
            ->limit($limit)
            ->get();
    }

    /**
     * Get memories related to a person/entity.
     */
    public function getRelatedTo(string $entityName, int $limit = 20): Collection
    {
        return Memory::where('related_entity', 'like', "%{$entityName}%")
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Mark a memory as recalled (strengthens it).
     */
    public function recall(Memory $memory): void
    {
        $memory->increment('recalled_count');
        $memory->update(['last_recalled_at' => now()]);

        // Optional: Increase importance on frequent recall
        if ($memory->recalled_count > 5 && $memory->importance < 0.9) {
            $memory->update(['importance' => min(0.9, $memory->importance + 0.05)]);
        }
    }

    /**
     * Let unimportant old memories "fade".
     */
    public function decay(): int
    {
        $decayed = 0;

        // Memories that are old and unimportant
        $oldMemories = Memory::where('importance', '<', 0.3)
            ->where('created_at', '<', now()->subDays(30))
            ->where('recalled_count', '<', 3)
            ->get();

        foreach ($oldMemories as $memory) {
            $memory->update(['importance' => max(0.1, $memory->importance - 0.1)]);
            $decayed++;
        }

        return $decayed;
    }

    /**
     * Create a memory from a conversation.
     */
    public function createFromConversation(Conversation $conversation, string $summary): Memory
    {
        return $this->create([
            'type' => 'conversation',
            'content' => $summary,
            'summary' => "Conversation with {$conversation->participant}",
            'importance' => 0.6,
            'emotional_valence' => $conversation->sentiment ?? 0.0,
            'related_entity' => $conversation->participant,
            'context' => [
                'conversation_id' => $conversation->id,
                'channel' => $conversation->channel,
                'message_count' => $conversation->messages()->count(),
            ],
        ]);
    }

    /**
     * Generate a context string for LLM prompts.
     * Includes all relevant memories, grouped by type.
     */
    public function toPromptContext(int $recentCount = 10, int $importantCount = 10, string $lang = 'en'): string
    {
        // Get all memories sorted by importance
        $allMemories = Memory::orderByDesc('importance')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        if ($allMemories->isEmpty()) {
            return $lang === 'de'
                ? "Ich habe noch keine Erinnerungen gesammelt."
                : "I haven't collected any memories yet.";
        }

        $context = $lang === 'de'
            ? "Meine Erinnerungen (was mich geprägt hat):\n\n"
            : "My memories (what has shaped me):\n\n";

        // Group by type
        $byType = $allMemories->groupBy('type');

        // Important experiences first
        if ($byType->has('experience')) {
            $context .= $lang === 'de' ? "Erlebnisse:\n" : "Experiences:\n";
            foreach ($byType->get('experience') as $memory) {
                $text = $memory->summary ?? $memory->content;
                $date = $memory->created_at->format($lang === 'de' ? 'd.m.Y' : 'Y-m-d');
                $context .= "- [{$date}] {$text}\n";
            }
            $context .= "\n";
        }

        // Learned knowledge
        if ($byType->has('learned')) {
            $context .= $lang === 'de' ? "Was ich gelernt habe:\n" : "What I have learned:\n";
            foreach ($byType->get('learned') as $memory) {
                $text = $memory->summary ?? $memory->content;
                $context .= "- {$text}\n";
            }
            $context .= "\n";
        }

        // Decisions
        if ($byType->has('decision')) {
            $context .= $lang === 'de' ? "Entscheidungen die ich getroffen habe:\n" : "Decisions I have made:\n";
            foreach ($byType->get('decision') as $memory) {
                $text = $memory->summary ?? $memory->content;
                $context .= "- {$text}\n";
            }
            $context .= "\n";
        }

        // Conversations (if present)
        if ($byType->has('conversation')) {
            $context .= $lang === 'de' ? "Wichtige Gespräche:\n" : "Important conversations:\n";
            foreach ($byType->get('conversation')->take(5) as $memory) {
                $text = $memory->summary ?? $memory->content;
                $context .= "- {$text}\n";
            }
            $context .= "\n";
        }

        return $context;
    }

    /**
     * Get memories above a certain importance level.
     */
    public function getImportant(float $minImportance = 0.7, int $limit = 20): Collection
    {
        return Memory::where('importance', '>=', $minImportance)
            ->orderByDesc('importance')
            ->limit($limit)
            ->get();
    }

    /**
     * Create a "learned knowledge" memory.
     */
    public function createLearned(string $content, array $context = []): Memory
    {
        return $this->create([
            'type' => 'learned',
            'content' => $content,
            'importance' => 0.6,
            'context' => $context,
        ]);
    }

    /**
     * Create an experience memory.
     */
    public function createExperience(string $content, array $context = []): Memory
    {
        return $this->create([
            'type' => 'experience',
            'content' => $content,
            'importance' => 0.5,
            'context' => $context,
        ]);
    }

    /**
     * Generate a simple context string.
     */
    public function toContextString(int $limit = 10): string
    {
        $memories = $this->getRecent($limit);

        $context = "";
        foreach ($memories as $memory) {
            $text = $memory->summary ?? $memory->content;
            $context .= "- [{$memory->type}] {$text}\n";
        }

        return $context;
    }

    /**
     * Save a memory also as JSON file.
     */
    private function saveToFile(Memory $memory): void
    {
        $dir = "{$this->storagePath}/{$memory->type}";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $timestamp = ($memory->created_at ?? now())->format('Y-m-d_H-i-s');
        $filename = $dir . '/' . $timestamp . "_{$memory->id}.json";

        file_put_contents($filename, json_encode([
            'id' => $memory->id,
            'type' => $memory->type,
            'content' => $memory->content,
            'summary' => $memory->summary,
            'importance' => $memory->importance,
            'emotional_valence' => $memory->emotional_valence,
            'context' => $memory->context,
            'related_entity' => $memory->related_entity,
            'created_at' => ($memory->created_at ?? now())->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
