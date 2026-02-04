<?php

namespace App\Services\Entity;

use App\Models\Memory;
use App\Models\MemorySummary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * MemoryLayerManager - Orchestrates the 4-layer memory system.
 *
 * Layers (from bottom to top):
 * 1. Core Identity (always loaded) - personality, values, self-image
 * 2. Semantic Memory (index-based) - learned knowledge, general facts
 * 3. Episodic Memory (vector-based) - experiences, conversations, events
 * 4. Working Memory (in-memory) - current context, recent thoughts
 *
 * This manager builds the think context by assembling relevant memories
 * from each layer within a token budget.
 */
class MemoryLayerManager
{
    public function __construct(
        private PersonalityService $personalityService,
        private SemanticMemoryService $semanticMemoryService,
        private MemoryService $memoryService,
        private WorkingMemoryService $workingMemoryService
    ) {}

    /**
     * Build the complete context for the think loop.
     *
     * This replaces the old toThinkContext() approach with a layered,
     * token-budget-aware context building system.
     *
     * @param string $currentSituation Description of what's happening now
     * @param string $lang Language code ('en' or 'de')
     * @return string The assembled context for the LLM
     */
    public function buildThinkContext(string $currentSituation = '', string $lang = 'en'): string
    {
        $budget = config('entity.memory.context_budget', []);
        $totalBudget = $budget['total'] ?? 4000;

        $context = '';
        $usedTokens = 0;

        // Layer 1: Core Identity (always loaded)
        $coreIdentity = $this->getCoreIdentityContext($lang);
        $coreTokens = $this->estimateTokens($coreIdentity);
        $context .= $coreIdentity . "\n\n";
        $usedTokens += $coreTokens;

        Log::channel('entity')->debug('Memory layer: Core Identity', [
            'tokens' => $coreTokens,
        ]);

        // Layer 2: Working Memory (current context)
        $workingBudget = min($budget['working_memory'] ?? 1000, $totalBudget - $usedTokens);
        $workingContext = $this->getWorkingMemoryContext($workingBudget, $lang);
        $workingTokens = $this->estimateTokens($workingContext);
        if (!empty($workingContext)) {
            $context .= $workingContext . "\n\n";
            $usedTokens += $workingTokens;
        }

        Log::channel('entity')->debug('Memory layer: Working Memory', [
            'tokens' => $workingTokens,
            'budget' => $workingBudget,
        ]);

        // Layer 3: Episodic Memory (semantic search based on situation)
        $episodicBudget = min($budget['episodic'] ?? 1500, $totalBudget - $usedTokens);
        $episodicContext = $this->getEpisodicMemoryContext($currentSituation, $episodicBudget, $lang);
        $episodicTokens = $this->estimateTokens($episodicContext);
        if (!empty($episodicContext)) {
            $context .= $episodicContext . "\n\n";
            $usedTokens += $episodicTokens;
        }

        Log::channel('entity')->debug('Memory layer: Episodic Memory', [
            'tokens' => $episodicTokens,
            'budget' => $episodicBudget,
        ]);

        // Layer 4: Semantic Memory (learned knowledge)
        $semanticBudget = min($budget['semantic'] ?? 1000, $totalBudget - $usedTokens);
        $semanticContext = $this->getSemanticMemoryContext($currentSituation, $semanticBudget, $lang);
        $semanticTokens = $this->estimateTokens($semanticContext);
        if (!empty($semanticContext)) {
            $context .= $semanticContext . "\n\n";
            $usedTokens += $semanticTokens;
        }

        Log::channel('entity')->debug('Memory layer: Semantic Memory', [
            'tokens' => $semanticTokens,
            'budget' => $semanticBudget,
        ]);

        Log::channel('entity')->info('Built think context from memory layers', [
            'total_tokens' => $usedTokens,
            'budget' => $totalBudget,
        ]);

        return trim($context);
    }

    /**
     * Get memories by their layer classification.
     *
     * @param string $layer The layer ('episodic', 'semantic', 'procedural')
     * @param int $limit Maximum memories to return
     * @return Collection
     */
    public function getByLayer(string $layer, int $limit = 10): Collection
    {
        return Memory::where('layer', $layer)
            ->where('is_consolidated', false)
            ->orderByDesc('importance')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Route new memory data to the appropriate layer.
     *
     * @param array $memoryData The memory data to route
     * @return Memory The created memory in its appropriate layer
     */
    public function routeToLayer(array $memoryData): Memory
    {
        $type = $memoryData['type'] ?? 'experience';

        // Determine layer based on memory type
        $layer = match ($type) {
            'learned', 'fact', 'knowledge' => 'semantic',
            'procedure', 'skill', 'how_to' => 'procedural',
            default => 'episodic', // experiences, conversations, social, decisions
        };

        $memoryData['layer'] = $layer;

        return $this->semanticMemoryService->createWithEmbedding($memoryData);
    }

    /**
     * Get the core identity context (always loaded).
     *
     * @param string $lang Language code
     * @return string
     */
    public function getCoreIdentity(string $lang = 'en'): array
    {
        return [
            'personality' => $this->personalityService->get(),
            'name' => $this->personalityService->getName(),
            'values' => $this->personalityService->getCoreValues(),
            'traits' => $this->personalityService->getTraits(),
        ];
    }

    /**
     * Generate core identity context string.
     */
    private function getCoreIdentityContext(string $lang): string
    {
        return $this->personalityService->toPrompt($lang);
    }

    /**
     * Generate working memory context string.
     */
    private function getWorkingMemoryContext(int $tokenBudget, string $lang): string
    {
        $context = $this->workingMemoryService->toPromptContext($lang);

        // Truncate if exceeds budget
        return $this->truncateToTokenBudget($context, $tokenBudget);
    }

    /**
     * Generate episodic memory context using semantic search.
     */
    private function getEpisodicMemoryContext(string $situation, int $tokenBudget, string $lang): string
    {
        if (empty($situation)) {
            // No specific situation, get most important recent episodic memories
            $memories = Memory::where('layer', 'episodic')
                ->where('is_consolidated', false)
                ->orderByDesc('importance')
                ->orderByDesc('created_at')
                ->limit(config('entity.memory.layers.episodic.max_in_context', 10))
                ->get();
        } else {
            // Semantic search for relevant memories
            $memories = $this->semanticMemoryService->search(
                $situation,
                config('entity.memory.layers.episodic.max_in_context', 10),
                config('entity.memory.layers.episodic.similarity_threshold', 0.7)
            );
        }

        if ($memories->isEmpty()) {
            return '';
        }

        $header = $lang === 'de'
            ? "Relevante Erinnerungen (Erlebnisse):\n"
            : "Relevant memories (experiences):\n";

        $context = $header;
        $usedTokens = $this->estimateTokens($header);

        foreach ($memories as $memory) {
            $line = $this->formatMemoryLine($memory, $lang);
            $lineTokens = $this->estimateTokens($line);

            if ($usedTokens + $lineTokens > $tokenBudget) {
                break;
            }

            $context .= $line;
            $usedTokens += $lineTokens;
        }

        return $context;
    }

    /**
     * Generate semantic memory context (learned knowledge).
     */
    private function getSemanticMemoryContext(string $situation, int $tokenBudget, string $lang): string
    {
        // Get semantic (learned) memories
        $memories = Memory::where('layer', 'semantic')
            ->where('is_consolidated', false)
            ->orderByDesc('importance')
            ->limit(config('entity.memory.layers.semantic.max_in_context', 5))
            ->get();

        // Also include recent memory summaries (consolidated knowledge)
        $summaries = MemorySummary::latest()
            ->limit(3)
            ->get();

        if ($memories->isEmpty() && $summaries->isEmpty()) {
            return '';
        }

        $header = $lang === 'de'
            ? "Gelerntes Wissen:\n"
            : "Learned knowledge:\n";

        $context = $header;
        $usedTokens = $this->estimateTokens($header);

        // Add semantic memories
        foreach ($memories as $memory) {
            $line = $this->formatMemoryLine($memory, $lang);
            $lineTokens = $this->estimateTokens($line);

            if ($usedTokens + $lineTokens > $tokenBudget) {
                break;
            }

            $context .= $line;
            $usedTokens += $lineTokens;
        }

        // Add summaries
        if ($summaries->isNotEmpty()) {
            $summaryHeader = $lang === 'de' ? "\nZusammenfassungen:\n" : "\nSummaries:\n";
            $context .= $summaryHeader;
            $usedTokens += $this->estimateTokens($summaryHeader);

            foreach ($summaries as $summary) {
                $line = "- [{$summary->period_type}] {$summary->summary}\n";
                $lineTokens = $this->estimateTokens($line);

                if ($usedTokens + $lineTokens > $tokenBudget) {
                    break;
                }

                $context .= $line;
                $usedTokens += $lineTokens;
            }
        }

        return $context;
    }

    /**
     * Format a memory for display in context.
     */
    private function formatMemoryLine(Memory $memory, string $lang): string
    {
        $text = $memory->summary ?? $memory->content;
        $date = $memory->created_at->format($lang === 'de' ? 'd.m.Y' : 'Y-m-d');

        // Add similarity score if available
        $similarity = '';
        if (isset($memory->similarity)) {
            $similarity = sprintf(' (%.0f%%)', $memory->similarity * 100);
        }

        return "- [{$date}] {$text}{$similarity}\n";
    }

    /**
     * Estimate token count for a string.
     *
     * Using rough estimate of ~4 characters per token.
     */
    private function estimateTokens(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Truncate text to fit within token budget.
     */
    private function truncateToTokenBudget(string $text, int $tokenBudget): string
    {
        $estimatedTokens = $this->estimateTokens($text);

        if ($estimatedTokens <= $tokenBudget) {
            return $text;
        }

        // Truncate to fit budget
        $maxChars = $tokenBudget * 4;
        return substr($text, 0, $maxChars) . '...';
    }
}
