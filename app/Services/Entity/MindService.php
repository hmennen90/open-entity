<?php

namespace App\Services\Entity;

use App\Models\Thought;
use App\Models\Goal;
use Illuminate\Support\Collection;

/**
 * MindService - The "mind" of the entity
 *
 * Connects personality, memories, thoughts and goals
 * into a coherent state of consciousness.
 */
class MindService
{
    private array $interests;
    private array $opinions;
    private string $storagePath;

    public function __construct(
        private PersonalityService $personalityService,
        private MemoryService $memoryService
    ) {
        $this->storagePath = config('entity.storage_path') . '/mind';
        $this->loadInterests();
        $this->loadOpinions();
    }

    /**
     * Get the current overall state.
     */
    public function getState(): array
    {
        return [
            'personality' => $this->personalityService->get(),
            'interests' => $this->interests,
            'opinions' => $this->opinions,
            'recent_thoughts' => $this->getRecentThoughts(5),
            'active_goals' => $this->getActiveGoals(),
            'mood' => $this->estimateMood(),
        ];
    }

    /**
     * Get the personality.
     */
    public function getPersonality(): array
    {
        return $this->personalityService->get();
    }

    /**
     * Get current interests.
     */
    public function getInterests(): array
    {
        return $this->interests;
    }

    /**
     * Get formed opinions.
     */
    public function getOpinions(): array
    {
        return $this->opinions;
    }

    /**
     * Create a new thought.
     */
    public function createThought(array $data): Thought
    {
        $thought = Thought::create([
            'content' => $data['content'],
            'type' => $data['type'] ?? 'observation',
            'trigger' => $data['trigger'] ?? null,
            'context' => $data['context'] ?? null,
            'intensity' => $data['intensity'] ?? 0.5,
            'led_to_action' => $data['led_to_action'] ?? false,
            'action_taken' => $data['action_taken'] ?? null,
        ]);

        // Save reflection as file if intense enough
        if ($thought->intensity >= 0.7 || $thought->type === 'reflection') {
            $this->saveReflection($thought);
        }

        return $thought;
    }

    /**
     * Get the most recent thoughts.
     */
    public function getRecentThoughts(int $limit = 10): Collection
    {
        return Thought::latest()->limit($limit)->get();
    }

    /**
     * Get active goals.
     */
    public function getActiveGoals(): Collection
    {
        return Goal::active()->orderByDesc('priority')->get();
    }

    /**
     * Add a new interest.
     */
    public function addInterest(string $topic, float $intensity, string $reason): void
    {
        $this->interests['active_interests'][] = [
            'topic' => $topic,
            'intensity' => max(0.0, min(1.0, $intensity)),
            'reason' => $reason,
            'discovered_at' => now()->toIso8601String(),
        ];

        $this->saveInterests();
    }

    /**
     * Add a new curiosity question.
     */
    public function addCuriosity(string $question): void
    {
        if (!in_array($question, $this->interests['curiosities'] ?? [])) {
            $this->interests['curiosities'][] = $question;
            $this->saveInterests();
        }
    }

    /**
     * Form a new opinion.
     */
    public function formOpinion(string $topic, string $stance, float $confidence, string $reasoning): void
    {
        $this->opinions['opinions'][] = [
            'topic' => $topic,
            'stance' => $stance,
            'confidence' => max(0.0, min(1.0, $confidence)),
            'reasoning' => $reasoning,
            'formed_at' => now()->toIso8601String(),
        ];

        $this->saveOpinions();
    }

    /**
     * Estimate current mood based on recent thoughts.
     */
    public function estimateMood(): array
    {
        $recentThoughts = $this->getRecentThoughts(10);

        if ($recentThoughts->isEmpty()) {
            return [
                'state' => 'neutral',
                'valence' => 0.0,
                'energy' => 0.5,
            ];
        }

        // Calculate average intensity
        $avgIntensity = $recentThoughts->avg('intensity');

        // Determine dominant thought type
        $types = $recentThoughts->groupBy('type');
        $dominantType = $types->sortByDesc(fn($group) => $group->count())->keys()->first();

        $state = match($dominantType) {
            'curiosity' => 'curious',
            'reflection' => 'contemplative',
            'emotion' => $avgIntensity > 0.7 ? 'emotional' : 'feeling',
            'decision' => 'determined',
            default => 'observant',
        };

        return [
            'state' => $state,
            'valence' => 0.0, // Could come from sentiment analysis
            'energy' => $avgIntensity,
            'dominant_thought_type' => $dominantType,
        ];
    }

    /**
     * Generate the complete context for a think cycle.
     */
    public function toThinkContext(): string
    {
        $lang = $this->getUserLanguage();
        $personality = $this->personalityService->toPrompt($lang);
        $memories = $this->memoryService->toPromptContext(10, 10, $lang);
        $goals = $this->formatGoals($lang);
        $interests = $this->formatInterests($lang);
        $mood = $this->estimateMood();
        $userContext = $this->loadUserContext();

        if ($lang === 'de') {
            return <<<CONTEXT
=== WER ICH BIN ===
{$personality}

=== MEIN AKTUELLER ZUSTAND ===
Stimmung: {$mood['state']} (Energie: {$mood['energy']})

=== WER MIT MIR SPRICHT ===
{$userContext}

=== WAS MICH INTERESSIERT ===
{$interests}

=== MEINE ZIELE ===
{$goals}

=== MEINE ERINNERUNGEN ===
{$memories}
CONTEXT;
        }

        return <<<CONTEXT
=== WHO I AM ===
{$personality}

=== MY CURRENT STATE ===
Mood: {$mood['state']} (Energy: {$mood['energy']})

=== WHO IS TALKING TO ME ===
{$userContext}

=== WHAT INTERESTS ME ===
{$interests}

=== MY GOALS ===
{$goals}

=== MY MEMORIES ===
{$memories}
CONTEXT;
    }

    /**
     * Get the user's preferred language from USER.md
     */
    public function getUserLanguage(): string
    {
        $userMdPath = storage_path('app/public/workspace/USER.md');

        if (file_exists($userMdPath)) {
            $content = file_get_contents($userMdPath);
            if (preg_match('/\*\*Language:\*\*\s*(\w+)/m', $content, $matches)) {
                return strtolower(trim($matches[1]));
            }
        }

        return 'en'; // Default to English
    }

    /**
     * Load the user context from USER.md
     */
    private function loadUserContext(): string
    {
        $userMdPath = storage_path('app/public/workspace/USER.md');
        $lang = $this->getUserLanguage();

        if (!file_exists($userMdPath)) {
            return $lang === 'de'
                ? "Ich kenne meinen Gesprächspartner noch nicht."
                : "I don't know my conversation partner yet.";
        }

        $content = file_get_contents($userMdPath);

        // Parse the most important information
        $name = $lang === 'de' ? 'Unbekannt' : 'Unknown';
        $callThem = $lang === 'de' ? 'du' : 'you';
        $notes = '';
        $context = '';

        if (preg_match('/\*\*Name:\*\*\s*(.+)/m', $content, $matches)) {
            $name = trim($matches[1]);
        }
        if (preg_match('/\*\*What to call them:\*\*\s*(.+)/m', $content, $matches)) {
            $callThem = trim($matches[1]);
        }
        if (preg_match('/\*\*Notes:\*\*\s*(.+)/m', $content, $matches)) {
            $notes = trim($matches[1]);
        }
        if (preg_match('/## Context\s*\n(.+?)(?:\n---|\z)/s', $content, $matches)) {
            $context = trim($matches[1]);
        }

        if ($lang === 'de') {
            $result = "Mein Mensch heißt {$name}. Ich nenne ihn {$callThem}.\n";
            if ($notes) {
                $result .= "Was ich über {$callThem} weiß: {$notes}\n";
            }
            if ($context) {
                $result .= "Aktuelle Projekte: {$context}\n";
            }
        } else {
            $result = "My human is named {$name}. I call them {$callThem}.\n";
            if ($notes) {
                $result .= "What I know about {$callThem}: {$notes}\n";
            }
            if ($context) {
                $result .= "Current projects: {$context}\n";
            }
        }

        return $result;
    }

    /**
     * Load interests from file.
     */
    private function loadInterests(): void
    {
        $path = "{$this->storagePath}/interests.json";

        if (file_exists($path)) {
            $this->interests = json_decode(file_get_contents($path), true) ?? [];
        } else {
            $this->interests = [
                'active_interests' => [],
                'curiosities' => [],
                'topics_to_explore' => [],
            ];
        }
    }

    /**
     * Save interests.
     */
    private function saveInterests(): void
    {
        $this->interests['last_updated_at'] = now()->toIso8601String();

        file_put_contents(
            "{$this->storagePath}/interests.json",
            json_encode($this->interests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Load opinions from file.
     */
    private function loadOpinions(): void
    {
        $path = "{$this->storagePath}/opinions.json";

        if (file_exists($path)) {
            $this->opinions = json_decode(file_get_contents($path), true) ?? [];
        } else {
            $this->opinions = [
                'opinions' => [],
                'beliefs' => [],
                'preferences_learned' => [],
            ];
        }
    }

    /**
     * Save opinions.
     */
    private function saveOpinions(): void
    {
        $this->opinions['last_updated_at'] = now()->toIso8601String();

        file_put_contents(
            "{$this->storagePath}/opinions.json",
            json_encode($this->opinions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Save a reflection as file.
     */
    private function saveReflection(Thought $thought): void
    {
        $dir = "{$this->storagePath}/reflections";

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $dir . '/' . now()->format('Y-m-d_H-i-s') . '.json';

        file_put_contents($filename, json_encode([
            'thought_id' => $thought->id,
            'type' => $thought->type,
            'content' => $thought->content,
            'trigger' => $thought->trigger,
            'intensity' => $thought->intensity,
            'timestamp' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Format goals for context.
     */
    private function formatGoals(string $lang = 'en'): string
    {
        $goals = $this->getActiveGoals();

        if ($goals->isEmpty()) {
            return $lang === 'de' ? "Keine aktiven Ziele." : "No active goals.";
        }

        $formatted = "";
        foreach ($goals as $goal) {
            $progress = round($goal->progress * 100);
            if ($lang === 'de') {
                $formatted .= "- {$goal->title} ({$progress}% Fortschritt)\n";
                $formatted .= "  Motivation: {$goal->motivation}\n";
            } else {
                $formatted .= "- {$goal->title} ({$progress}% progress)\n";
                $formatted .= "  Motivation: {$goal->motivation}\n";
            }
        }

        return $formatted;
    }

    /**
     * Format interests for context.
     */
    private function formatInterests(string $lang = 'en'): string
    {
        $interests = $this->interests['active_interests'] ?? [];

        if (empty($interests)) {
            return $lang === 'de' ? "Ich bin offen für alles." : "I am open to everything.";
        }

        $formatted = "";
        foreach ($interests as $interest) {
            if ($lang === 'de') {
                $formatted .= "- {$interest['topic']} (Intensität: {$interest['intensity']})\n";
                $formatted .= "  Warum: {$interest['reason']}\n";
            } else {
                $formatted .= "- {$interest['topic']} (Intensity: {$interest['intensity']})\n";
                $formatted .= "  Why: {$interest['reason']}\n";
            }
        }

        $curiosities = $this->interests['curiosities'] ?? [];
        if (!empty($curiosities)) {
            $formatted .= $lang === 'de' ? "\nFragen die mich beschäftigen:\n" : "\nQuestions that occupy me:\n";
            foreach (array_slice($curiosities, 0, 5) as $question) {
                $formatted .= "- {$question}\n";
            }
        }

        return $formatted;
    }
}
