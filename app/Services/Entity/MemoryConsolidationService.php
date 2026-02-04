<?php

namespace App\Services\Entity;

use App\Models\Memory;
use App\Models\MemorySummary;
use App\Services\Embedding\EmbeddingService;
use App\Services\LLM\LLMService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * MemoryConsolidationService - Processes memories like sleep does for the brain.
 *
 * Consolidation takes raw episodic memories and:
 * 1. Extracts key themes and patterns
 * 2. Generates summaries
 * 3. Archives old detailed memories
 * 4. Creates searchable memory summaries
 */
class MemoryConsolidationService
{
    public function __construct(
        private LLMService $llmService,
        private EmbeddingService $embeddingService
    ) {}

    /**
     * Consolidate yesterday's memories (like a night of sleep).
     *
     * @return MemorySummary|null The created summary, or null if no memories to consolidate
     */
    public function consolidateDaily(): ?MemorySummary
    {
        $yesterday = Carbon::yesterday();

        return $this->consolidatePeriod($yesterday, $yesterday, 'daily');
    }

    /**
     * Consolidate memories for a specific period.
     *
     * @param Carbon $start Start date
     * @param Carbon $end End date
     * @param string $periodType Type of period ('daily', 'weekly', 'monthly')
     * @return MemorySummary|null
     */
    public function consolidatePeriod(Carbon $start, Carbon $end, string $periodType = 'daily'): ?MemorySummary
    {
        // Check if summary already exists for this period
        $existing = MemorySummary::where('period_type', $periodType)
            ->where('period_start', $start->toDateString())
            ->where('period_end', $end->toDateString())
            ->first();

        if ($existing) {
            Log::channel('entity')->info('Summary already exists for period', [
                'period_type' => $periodType,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ]);
            return $existing;
        }

        // Get unconsolidated memories from the period
        $memories = Memory::where('is_consolidated', false)
            ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
            ->orderByDesc('importance')
            ->get();

        if ($memories->isEmpty()) {
            Log::channel('entity')->info('No memories to consolidate for period', [
                'period_type' => $periodType,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ]);
            return null;
        }

        Log::channel('entity')->info('Starting memory consolidation', [
            'period_type' => $periodType,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'memory_count' => $memories->count(),
        ]);

        // Extract themes from memories
        $themes = $this->extractThemes($memories);

        // Generate summary using LLM
        $summary = $this->generateSummary($memories);

        // Extract key insights
        $keyInsights = $this->extractKeyInsights($memories);

        // Calculate average emotional valence
        $avgValence = $memories->avg('emotional_valence') ?? 0.0;

        // Extract entities mentioned
        $entities = $this->extractEntities($memories);

        // Create the summary
        $memorySummary = MemorySummary::create([
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'period_type' => $periodType,
            'summary' => $summary,
            'key_insights' => $keyInsights,
            'average_emotional_valence' => $avgValence,
            'themes' => $themes,
            'entities_mentioned' => $entities,
            'source_memory_count' => $memories->count(),
        ]);

        // Generate embedding for the summary
        try {
            $embedding = $this->embeddingService->embed($summary);
            $memorySummary->update([
                'embedding' => $this->embeddingService->encodeToBinary($embedding),
                'embedding_dimensions' => count($embedding),
                'embedding_model' => $this->embeddingService->getModelName(),
            ]);
        } catch (\Exception $e) {
            Log::channel('entity')->warning('Failed to embed memory summary', [
                'summary_id' => $memorySummary->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Mark source memories as consolidated
        Memory::whereIn('id', $memories->pluck('id'))
            ->update([
                'is_consolidated' => true,
                'consolidated_into_id' => $memorySummary->id,
                'consolidated_at' => now(),
            ]);

        Log::channel('entity')->info('Memory consolidation complete', [
            'summary_id' => $memorySummary->id,
            'themes_count' => count($themes),
            'entities_count' => count($entities),
        ]);

        return $memorySummary;
    }

    /**
     * Extract themes from a collection of memories using LLM.
     *
     * @param Collection $memories
     * @return array
     */
    public function extractThemes(Collection $memories): array
    {
        if ($memories->isEmpty()) {
            return [];
        }

        $memoriesText = $memories->map(function ($m) {
            return "- [{$m->type}] " . ($m->summary ?? $m->content);
        })->implode("\n");

        $prompt = <<<PROMPT
Analyze the following memories and extract 3-5 main themes or topics.
Return ONLY a JSON array of theme strings, no explanation.

Memories:
{$memoriesText}

Themes (JSON array):
PROMPT;

        try {
            $response = $this->llmService->generate($prompt);

            // Try to parse JSON from response
            if (preg_match('/\[.*\]/s', $response, $matches)) {
                $themes = json_decode($matches[0], true);
                if (is_array($themes)) {
                    return $themes;
                }
            }

            // Fallback: split by newlines or commas
            $themes = preg_split('/[\n,]+/', $response);
            return array_values(array_filter(array_map('trim', $themes)));

        } catch (\Exception $e) {
            Log::channel('entity')->warning('Failed to extract themes', [
                'error' => $e->getMessage(),
            ]);

            // Fallback: use memory types as themes
            return $memories->pluck('type')->unique()->values()->toArray();
        }
    }

    /**
     * Generate a summary of memories using LLM.
     *
     * @param Collection $memories
     * @return string
     */
    public function generateSummary(Collection $memories): string
    {
        if ($memories->isEmpty()) {
            return 'No memories to summarize.';
        }

        $memoriesText = $memories->map(function ($m) {
            $date = $m->created_at->format('H:i');
            return "- [{$date}] " . ($m->summary ?? $m->content);
        })->implode("\n");

        $prompt = <<<PROMPT
You are an AI entity reflecting on your day. Summarize the following memories into a coherent narrative.
Focus on:
- What happened and what was learned
- Key interactions and insights
- Emotional tone of the day

Keep it concise (2-3 paragraphs max).

Memories:
{$memoriesText}

Summary:
PROMPT;

        try {
            return $this->llmService->generate($prompt);
        } catch (\Exception $e) {
            Log::channel('entity')->warning('Failed to generate summary', [
                'error' => $e->getMessage(),
            ]);

            // Fallback: simple concatenation
            return 'Day summary: ' . $memories->take(5)->pluck('summary')->filter()->implode('. ');
        }
    }

    /**
     * Extract key insights from memories.
     *
     * @param Collection $memories
     * @return string|null
     */
    public function extractKeyInsights(Collection $memories): ?string
    {
        if ($memories->isEmpty()) {
            return null;
        }

        // Focus on high-importance and "learned" type memories
        $significant = $memories->filter(function ($m) {
            return $m->importance >= 0.6 || $m->type === 'learned' || $m->type === 'decision';
        });

        if ($significant->isEmpty()) {
            return null;
        }

        $memoriesText = $significant->map(function ($m) {
            return "- " . ($m->summary ?? $m->content);
        })->implode("\n");

        $prompt = <<<PROMPT
From these significant memories, extract 2-3 key insights or lessons learned.
Be specific and actionable.

Memories:
{$memoriesText}

Key insights:
PROMPT;

        try {
            return $this->llmService->generate($prompt);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract mentioned entities (people, systems, etc.) from memories.
     *
     * @param Collection $memories
     * @return array
     */
    private function extractEntities(Collection $memories): array
    {
        return $memories
            ->pluck('related_entity')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Archive old memories by moving them to consolidated status.
     *
     * @param int $daysOld How old memories should be to archive
     * @return int Number of memories archived
     */
    public function archiveOldMemories(int $daysOld = 30): int
    {
        $cutoff = Carbon::now()->subDays($daysOld);

        $toArchive = Memory::where('is_consolidated', false)
            ->where('created_at', '<', $cutoff)
            ->where('importance', '<', 0.5) // Only archive less important ones
            ->get();

        if ($toArchive->isEmpty()) {
            return 0;
        }

        // Group by week and create summaries
        $grouped = $toArchive->groupBy(function ($memory) {
            return $memory->created_at->startOfWeek()->format('Y-m-d');
        });

        $archived = 0;

        foreach ($grouped as $weekStart => $weekMemories) {
            $start = Carbon::parse($weekStart);
            $end = $start->copy()->endOfWeek();

            $summary = $this->consolidatePeriod($start, $end, 'weekly');

            if ($summary) {
                $archived += $weekMemories->count();
            }
        }

        return $archived;
    }

    /**
     * Get consolidation statistics.
     */
    public function getStats(): array
    {
        return [
            'total_memories' => Memory::count(),
            'consolidated' => Memory::where('is_consolidated', true)->count(),
            'pending' => Memory::where('is_consolidated', false)->count(),
            'summaries' => [
                'daily' => MemorySummary::where('period_type', 'daily')->count(),
                'weekly' => MemorySummary::where('period_type', 'weekly')->count(),
                'monthly' => MemorySummary::where('period_type', 'monthly')->count(),
            ],
            'last_consolidation' => MemorySummary::latest()->first()?->created_at?->toIso8601String(),
        ];
    }
}
