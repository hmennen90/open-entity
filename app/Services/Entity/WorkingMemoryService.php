<?php

namespace App\Services\Entity;

use App\Models\Thought;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * WorkingMemoryService - Manages the entity's immediate/working memory.
 *
 * Working memory is the "current context" - what's actively being thought about,
 * recent conversation context, and immediate situational awareness.
 * This is stored in Redis for fast access and automatic TTL expiration.
 */
class WorkingMemoryService
{
    private const CACHE_PREFIX = 'entity:working_memory:';
    private const ITEMS_KEY = 'items';
    private const CONVERSATION_KEY = 'conversation:';

    private int $maxItems;
    private int $ttlMinutes;

    public function __construct()
    {
        $config = config('entity.memory.layers.working', []);
        $this->maxItems = $config['max_items'] ?? 20;
        $this->ttlMinutes = $config['ttl_minutes'] ?? 60;
    }

    /**
     * Add an item to working memory.
     *
     * @param string $item The memory item to add
     * @param float $importance Importance weight (0.0 to 1.0)
     * @param string|null $category Optional category for grouping
     */
    public function add(string $item, float $importance = 0.5, ?string $category = null): void
    {
        $items = $this->getItems();

        $newItem = [
            'content' => $item,
            'importance' => $importance,
            'category' => $category,
            'added_at' => now()->toIso8601String(),
        ];

        // Add to front (most recent first)
        array_unshift($items, $newItem);

        // Enforce max items limit, keeping most important
        if (count($items) > $this->maxItems) {
            // Sort by importance, then by recency
            usort($items, function ($a, $b) {
                $importanceDiff = ($b['importance'] ?? 0.5) <=> ($a['importance'] ?? 0.5);
                if ($importanceDiff !== 0) {
                    return $importanceDiff;
                }
                return ($b['added_at'] ?? '') <=> ($a['added_at'] ?? '');
            });

            $items = array_slice($items, 0, $this->maxItems);
        }

        $this->saveItems($items);

        Log::channel('entity')->debug('Added item to working memory', [
            'item_preview' => substr($item, 0, 50),
            'importance' => $importance,
            'total_items' => count($items),
        ]);
    }

    /**
     * Set the current conversation context.
     *
     * @param int $conversationId The conversation ID
     * @param array $context Context data (messages, participant, etc.)
     */
    public function setConversationContext(int $conversationId, array $context): void
    {
        $key = self::CACHE_PREFIX . self::CONVERSATION_KEY . $conversationId;

        Cache::put($key, $context, now()->addMinutes($this->ttlMinutes));

        Log::channel('entity')->debug('Set conversation context', [
            'conversation_id' => $conversationId,
        ]);
    }

    /**
     * Get the current conversation context.
     *
     * @param int $conversationId The conversation ID
     * @return array|null
     */
    public function getConversationContext(int $conversationId): ?array
    {
        $key = self::CACHE_PREFIX . self::CONVERSATION_KEY . $conversationId;

        return Cache::get($key);
    }

    /**
     * Clear a specific conversation context.
     *
     * @param int $conversationId The conversation ID
     */
    public function clearConversationContext(int $conversationId): void
    {
        $key = self::CACHE_PREFIX . self::CONVERSATION_KEY . $conversationId;

        Cache::forget($key);
    }

    /**
     * Get all items in working memory.
     *
     * @return array
     */
    public function getItems(): array
    {
        $key = self::CACHE_PREFIX . self::ITEMS_KEY;

        return Cache::get($key, []);
    }

    /**
     * Get the complete working memory context.
     *
     * @return array
     */
    public function getWorkingMemory(): array
    {
        return [
            'items' => $this->getItems(),
            'recent_thoughts' => $this->getRecentThoughts()->toArray(),
        ];
    }

    /**
     * Get recent thoughts from the database.
     *
     * @param int $minutes How far back to look
     * @return Collection
     */
    public function getRecentThoughts(int $minutes = 30): Collection
    {
        return Thought::where('created_at', '>=', now()->subMinutes($minutes))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'content', 'type', 'intensity', 'created_at']);
    }

    /**
     * Clear all items from working memory.
     */
    public function clear(): void
    {
        $key = self::CACHE_PREFIX . self::ITEMS_KEY;

        Cache::forget($key);

        Log::channel('entity')->info('Cleared working memory');
    }

    /**
     * Generate a context string for LLM prompts.
     *
     * @param string $lang Language code ('en' or 'de')
     * @return string
     */
    public function toPromptContext(string $lang = 'en'): string
    {
        $items = $this->getItems();
        $thoughts = $this->getRecentThoughts();

        if (empty($items) && $thoughts->isEmpty()) {
            return '';
        }

        $context = $lang === 'de'
            ? "Aktueller Kontext (was ich gerade im Kopf habe):\n"
            : "Current context (what I'm currently thinking about):\n";

        // Add working memory items
        if (!empty($items)) {
            foreach ($items as $item) {
                $content = $item['content'];
                $category = $item['category'] ?? 'note';
                $context .= "- [{$category}] {$content}\n";
            }
        }

        // Add recent thoughts
        if ($thoughts->isNotEmpty()) {
            $context .= $lang === 'de' ? "\nLetzte Gedanken:\n" : "\nRecent thoughts:\n";
            foreach ($thoughts->take(5) as $thought) {
                $preview = substr($thought->content, 0, 100);
                if (strlen($thought->content) > 100) {
                    $preview .= '...';
                }
                $context .= "- {$preview}\n";
            }
        }

        return $context;
    }

    /**
     * Get the current focus (most important items).
     *
     * @param int $limit Maximum items to return
     * @return array
     */
    public function getCurrentFocus(int $limit = 5): array
    {
        $items = $this->getItems();

        // Sort by importance
        usort($items, fn ($a, $b) => ($b['importance'] ?? 0.5) <=> ($a['importance'] ?? 0.5));

        return array_slice($items, 0, $limit);
    }

    /**
     * Check if a topic is currently in working memory.
     *
     * @param string $topic The topic to check for
     * @return bool
     */
    public function hasTopic(string $topic): bool
    {
        $topic = strtolower($topic);
        $items = $this->getItems();

        foreach ($items as $item) {
            if (str_contains(strtolower($item['content']), $topic)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Save items to cache.
     */
    private function saveItems(array $items): void
    {
        $key = self::CACHE_PREFIX . self::ITEMS_KEY;

        Cache::put($key, $items, now()->addMinutes($this->ttlMinutes));
    }
}
