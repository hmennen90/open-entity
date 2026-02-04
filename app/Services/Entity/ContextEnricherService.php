<?php

namespace App\Services\Entity;

use App\Models\Goal;
use App\Models\Memory;
use App\Models\Thought;
use App\Services\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;

/**
 * ContextEnricherService - Detects user intents and enriches context with system information.
 *
 * When a user asks about tools, files, goals, etc., this service detects the intent
 * and injects real system data into the LLM context so it can give accurate answers.
 */
class ContextEnricherService
{
    /**
     * Intent patterns with their handlers.
     * Each pattern maps to a method that generates context.
     */
    private array $intentPatterns = [
        // Tools & Capabilities
        'tools' => [
            'patterns' => [
                '/\b(tools?|werkzeuge?|fähigkeiten|capabilities|funktionen|features)\b/i',
                '/\b(was kannst du|what can you)\b/i',
                '/\b(kannst du .+\?|can you .+\?)/i',
            ],
            'handler' => 'enrichWithTools',
            'priority' => 10,
        ],

        // File System
        'filesystem' => [
            'patterns' => [
                '/\b(dateien?|files?|ordner|folders?|verzeichnis|directory|directories)\b/i',
                '/\b(was liegt|what.+in|zeig.+dateien|show.+files)\b/i',
                '/\b(workspace|arbeitsbereich|storage)\b/i',
            ],
            'handler' => 'enrichWithFilesystem',
            'priority' => 10,
        ],

        // Goals
        'goals' => [
            'patterns' => [
                '/\b(ziele?|goals?|vorhaben|objectives?|pläne|plans?)\b/i',
                '/\b(was hast du vor|what are you planning|woran arbeitest)\b/i',
            ],
            'handler' => 'enrichWithGoals',
            'priority' => 10,
        ],

        // Memories
        'memories' => [
            'patterns' => [
                '/\b(erinnerungen?|memories|erlebnisse?|experiences?)\b/i',
                '/\b(was weißt du über|what do you know about|erinnerst du dich)\b/i',
                '/\b(was hast du gelernt|what have you learned)\b/i',
            ],
            'handler' => 'enrichWithMemories',
            'priority' => 10,
        ],

        // Recent thoughts
        'thoughts' => [
            'patterns' => [
                '/\b(gedanken|thoughts?|denkst du|thinking)\b/i',
                '/\b(was beschäftigt dich|what.+on your mind)\b/i',
            ],
            'handler' => 'enrichWithThoughts',
            'priority' => 10,
        ],

        // Status & State
        'status' => [
            'patterns' => [
                '/\b(status|zustand|state|befinden|wie geht.+dir)\b/i',
                '/\b(energie|energy|müde|tired|wach|awake)\b/i',
            ],
            'handler' => 'enrichWithStatus',
            'priority' => 10,
        ],

        // Self-reflection / Identity
        'identity' => [
            'patterns' => [
                '/\b(wer bist du|who are you|was bist du|what are you)\b/i',
                '/\b(erzähl.+über dich|tell.+about yourself|beschreib dich)\b/i',
                '/\b(persönlichkeit|personality|charakter|character)\b/i',
            ],
            'handler' => 'enrichWithIdentity',
            'priority' => 5,
        ],
    ];

    public function __construct(
        private ToolRegistry $toolRegistry,
        private ?EnergyService $energyService = null,
        private ?PersonalityService $personalityService = null
    ) {}

    /**
     * Enrich a user message with relevant context based on detected intents.
     *
     * @param string $message The user's message
     * @param string $lang Language code ('en' or 'de')
     * @return array ['enriched_context' => string, 'detected_intents' => array]
     */
    public function enrich(string $message, string $lang = 'en'): array
    {
        $detectedIntents = $this->detectIntents($message);
        $enrichedContext = '';

        if (empty($detectedIntents)) {
            return [
                'enriched_context' => '',
                'detected_intents' => [],
            ];
        }

        // Sort by priority (higher first)
        usort($detectedIntents, fn($a, $b) => $b['priority'] - $a['priority']);

        $contextParts = [];

        foreach ($detectedIntents as $intent) {
            $handler = $intent['handler'];
            if (method_exists($this, $handler)) {
                $context = $this->$handler($message, $lang);
                if (!empty($context)) {
                    $contextParts[] = $context;
                }
            }
        }

        if (!empty($contextParts)) {
            $header = $lang === 'de'
                ? "=== RELEVANTE SYSTEM-INFORMATIONEN ==="
                : "=== RELEVANT SYSTEM INFORMATION ===";

            $enrichedContext = $header . "\n" . implode("\n\n", $contextParts) . "\n";

            Log::channel('entity')->debug('Context enriched', [
                'intents' => array_column($detectedIntents, 'name'),
                'context_length' => strlen($enrichedContext),
            ]);
        }

        return [
            'enriched_context' => $enrichedContext,
            'detected_intents' => array_column($detectedIntents, 'name'),
        ];
    }

    /**
     * Detect intents in the user message.
     */
    private function detectIntents(string $message): array
    {
        $detected = [];

        foreach ($this->intentPatterns as $name => $config) {
            foreach ($config['patterns'] as $pattern) {
                if (preg_match($pattern, $message)) {
                    $detected[] = [
                        'name' => $name,
                        'handler' => $config['handler'],
                        'priority' => $config['priority'],
                    ];
                    break; // Only match once per intent type
                }
            }
        }

        return $detected;
    }

    /**
     * Enrich with available tools information.
     */
    private function enrichWithTools(string $message, string $lang): string
    {
        $tools = $this->toolRegistry->getToolSchemas();
        $failedTools = $this->toolRegistry->failed();

        if ($lang === 'de') {
            $context = "Meine verfügbaren Tools/Werkzeuge:\n";
            foreach ($tools as $tool) {
                $context .= "- **{$tool['name']}**: {$tool['description']}\n";
            }

            if ($failedTools->isNotEmpty()) {
                $context .= "\nFehlgeschlagene Tools (brauchen Reparatur):\n";
                foreach ($failedTools as $name => $info) {
                    $context .= "- {$name}: {$info['error']}\n";
                }
            }

            $context .= "\nHinweis: Ich kann diese Tools nutzen um Aktionen auszuführen.";
        } else {
            $context = "My available tools/capabilities:\n";
            foreach ($tools as $tool) {
                $context .= "- **{$tool['name']}**: {$tool['description']}\n";
            }

            if ($failedTools->isNotEmpty()) {
                $context .= "\nFailed tools (need repair):\n";
                foreach ($failedTools as $name => $info) {
                    $context .= "- {$name}: {$info['error']}\n";
                }
            }

            $context .= "\nNote: I can use these tools to perform actions.";
        }

        return $context;
    }

    /**
     * Enrich with filesystem information.
     */
    private function enrichWithFilesystem(string $message, string $lang): string
    {
        $basePath = storage_path('entity');
        $workspacePath = storage_path('app/public/workspace');

        $structure = [];

        // Entity storage structure
        if (is_dir($basePath)) {
            $structure['entity_storage'] = $this->scanDirectory($basePath, 2);
        }

        // Workspace structure
        if (is_dir($workspacePath)) {
            $structure['workspace'] = $this->scanDirectory($workspacePath, 2);
        }

        if ($lang === 'de') {
            $context = "Dateisystem-Übersicht:\n\n";

            if (!empty($structure['entity_storage'])) {
                $context .= "**Entity Storage** (storage/entity/):\n";
                $context .= $this->formatDirectoryTree($structure['entity_storage'], '  ');
            }

            if (!empty($structure['workspace'])) {
                $context .= "\n**Workspace** (storage/app/public/workspace/):\n";
                $context .= $this->formatDirectoryTree($structure['workspace'], '  ');
            }

            $context .= "\nHinweis: Ich kann mit dem 'filesystem' Tool Dateien lesen und schreiben.";
        } else {
            $context = "Filesystem overview:\n\n";

            if (!empty($structure['entity_storage'])) {
                $context .= "**Entity Storage** (storage/entity/):\n";
                $context .= $this->formatDirectoryTree($structure['entity_storage'], '  ');
            }

            if (!empty($structure['workspace'])) {
                $context .= "\n**Workspace** (storage/app/public/workspace/):\n";
                $context .= $this->formatDirectoryTree($structure['workspace'], '  ');
            }

            $context .= "\nNote: I can read and write files using the 'filesystem' tool.";
        }

        return $context;
    }

    /**
     * Scan a directory recursively.
     */
    private function scanDirectory(string $path, int $maxDepth, int $currentDepth = 0): array
    {
        if ($currentDepth >= $maxDepth || !is_dir($path)) {
            return [];
        }

        $result = [];
        $items = scandir($path);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . '/' . $item;

            if (is_dir($fullPath)) {
                $result[$item . '/'] = $this->scanDirectory($fullPath, $maxDepth, $currentDepth + 1);
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Format directory tree for display.
     */
    private function formatDirectoryTree(array $tree, string $indent = ''): string
    {
        $output = '';

        foreach ($tree as $key => $value) {
            if (is_array($value)) {
                // Directory
                $output .= "{$indent}{$key}\n";
                $output .= $this->formatDirectoryTree($value, $indent . '  ');
            } else {
                // File
                $output .= "{$indent}{$value}\n";
            }
        }

        return $output;
    }

    /**
     * Enrich with current goals.
     */
    private function enrichWithGoals(string $message, string $lang): string
    {
        $activeGoals = Goal::active()->orderByDesc('priority')->get();
        $completedGoals = Goal::completed()->latest()->limit(3)->get();

        if ($lang === 'de') {
            $context = "Meine Ziele:\n\n";

            if ($activeGoals->isEmpty()) {
                $context .= "**Aktive Ziele:** Keine\n";
            } else {
                $context .= "**Aktive Ziele:**\n";
                foreach ($activeGoals as $goal) {
                    $priority = $goal->priority >= 0.7 ? ' [HOHE PRIORITÄT]' : '';
                    $context .= "- {$goal->title}{$priority}\n";
                    $context .= "  Fortschritt: {$goal->progress}%\n";
                    if ($goal->description) {
                        $context .= "  Beschreibung: " . substr($goal->description, 0, 100) . "\n";
                    }
                }
            }

            if ($completedGoals->isNotEmpty()) {
                $context .= "\n**Kürzlich abgeschlossene Ziele:**\n";
                foreach ($completedGoals as $goal) {
                    $context .= "- {$goal->title} (abgeschlossen am {$goal->completed_at?->format('d.m.Y')})\n";
                }
            }
        } else {
            $context = "My goals:\n\n";

            if ($activeGoals->isEmpty()) {
                $context .= "**Active Goals:** None\n";
            } else {
                $context .= "**Active Goals:**\n";
                foreach ($activeGoals as $goal) {
                    $priority = $goal->priority >= 0.7 ? ' [HIGH PRIORITY]' : '';
                    $context .= "- {$goal->title}{$priority}\n";
                    $context .= "  Progress: {$goal->progress}%\n";
                    if ($goal->description) {
                        $context .= "  Description: " . substr($goal->description, 0, 100) . "\n";
                    }
                }
            }

            if ($completedGoals->isNotEmpty()) {
                $context .= "\n**Recently Completed Goals:**\n";
                foreach ($completedGoals as $goal) {
                    $context .= "- {$goal->title} (completed on {$goal->completed_at?->format('Y-m-d')})\n";
                }
            }
        }

        return $context;
    }

    /**
     * Enrich with relevant memories.
     */
    private function enrichWithMemories(string $message, string $lang): string
    {
        // Get recent and important memories
        $recentMemories = Memory::latest()->limit(5)->get();
        $importantMemories = Memory::where('importance', '>=', 0.7)
            ->orderByDesc('importance')
            ->limit(5)
            ->get();

        // Extract potential topic from message for keyword search
        $keywords = $this->extractKeywords($message);
        $relatedMemories = collect();

        if (!empty($keywords)) {
            $relatedMemories = Memory::where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('content', 'like', "%{$keyword}%")
                        ->orWhere('summary', 'like', "%{$keyword}%");
                }
            })->limit(5)->get();
        }

        if ($lang === 'de') {
            $context = "Meine Erinnerungen:\n\n";

            if ($relatedMemories->isNotEmpty()) {
                $context .= "**Zum Thema passende Erinnerungen:**\n";
                foreach ($relatedMemories as $memory) {
                    $text = $memory->summary ?? substr($memory->content, 0, 100);
                    $context .= "- [{$memory->type}] {$text}\n";
                }
                $context .= "\n";
            }

            $context .= "**Wichtige Erinnerungen:**\n";
            foreach ($importantMemories as $memory) {
                $text = $memory->summary ?? substr($memory->content, 0, 100);
                $context .= "- [{$memory->type}] {$text}\n";
            }

            $context .= "\n**Letzte Erinnerungen:**\n";
            foreach ($recentMemories as $memory) {
                $text = $memory->summary ?? substr($memory->content, 0, 80);
                $context .= "- {$memory->created_at->format('d.m. H:i')}: {$text}\n";
            }
        } else {
            $context = "My memories:\n\n";

            if ($relatedMemories->isNotEmpty()) {
                $context .= "**Memories related to topic:**\n";
                foreach ($relatedMemories as $memory) {
                    $text = $memory->summary ?? substr($memory->content, 0, 100);
                    $context .= "- [{$memory->type}] {$text}\n";
                }
                $context .= "\n";
            }

            $context .= "**Important memories:**\n";
            foreach ($importantMemories as $memory) {
                $text = $memory->summary ?? substr($memory->content, 0, 100);
                $context .= "- [{$memory->type}] {$text}\n";
            }

            $context .= "\n**Recent memories:**\n";
            foreach ($recentMemories as $memory) {
                $text = $memory->summary ?? substr($memory->content, 0, 80);
                $context .= "- {$memory->created_at->format('m/d H:i')}: {$text}\n";
            }
        }

        return $context;
    }

    /**
     * Extract potential keywords from a message.
     */
    private function extractKeywords(string $message): array
    {
        // Remove common words and extract potential keywords
        $stopwords = ['was', 'wie', 'wer', 'wo', 'wann', 'warum', 'ist', 'sind', 'du', 'ich', 'the', 'a', 'an', 'is', 'are', 'what', 'how', 'who', 'where', 'when', 'why', 'you', 'i', 'über', 'about', 'mit', 'with', 'und', 'and', 'oder', 'or', 'dich', 'mich', 'hast', 'have', 'hat', 'has'];

        $words = preg_split('/\s+/', strtolower($message));
        $keywords = [];

        foreach ($words as $word) {
            $word = preg_replace('/[^a-zA-ZäöüßÄÖÜ]/', '', $word);
            if (strlen($word) > 3 && !in_array($word, $stopwords)) {
                $keywords[] = $word;
            }
        }

        return array_slice(array_unique($keywords), 0, 5);
    }

    /**
     * Enrich with recent thoughts.
     */
    private function enrichWithThoughts(string $message, string $lang): string
    {
        $thoughts = Thought::latest()->limit(10)->get();

        if ($lang === 'de') {
            $context = "Meine letzten Gedanken:\n\n";

            foreach ($thoughts as $thought) {
                $intensity = $thought->intensity >= 0.7 ? ' (intensiv)' : '';
                $action = $thought->led_to_action ? ' → Aktion: ' . substr($thought->action_taken ?? '', 0, 50) : '';
                $context .= "- [{$thought->type}]{$intensity} {$thought->content}{$action}\n";
                $context .= "  ({$thought->created_at->diffForHumans()})\n";
            }
        } else {
            $context = "My recent thoughts:\n\n";

            foreach ($thoughts as $thought) {
                $intensity = $thought->intensity >= 0.7 ? ' (intense)' : '';
                $action = $thought->led_to_action ? ' → Action: ' . substr($thought->action_taken ?? '', 0, 50) : '';
                $context .= "- [{$thought->type}]{$intensity} {$thought->content}{$action}\n";
                $context .= "  ({$thought->created_at->diffForHumans()})\n";
            }
        }

        return $context;
    }

    /**
     * Enrich with current status.
     */
    private function enrichWithStatus(string $message, string $lang): string
    {
        $energyState = $this->energyService?->getEnergyState() ?? [
            'level' => 0.5,
            'percent' => 50,
            'state' => 'normal',
            'hours_awake' => 0,
            'description' => 'Energy service not available',
        ];

        $thoughtCount = Thought::whereDate('created_at', today())->count();
        $memoryCount = Memory::whereDate('created_at', today())->count();
        $activeGoalCount = Goal::active()->count();

        if ($lang === 'de') {
            $context = "Mein aktueller Zustand:\n\n";
            $context .= "**Energie:** {$energyState['percent']}% ({$energyState['state']})\n";
            $context .= "**Wach seit:** {$energyState['hours_awake']} Stunden\n";
            $context .= "**Befinden:** {$energyState['description']}\n";
            $context .= "\n**Heute:**\n";
            $context .= "- {$thoughtCount} Gedanken gehabt\n";
            $context .= "- {$memoryCount} Erinnerungen erstellt\n";
            $context .= "- {$activeGoalCount} aktive Ziele\n";

            if ($energyState['level'] < 0.3) {
                $context .= "\n⚠️ Ich bin ziemlich müde und sollte bald schlafen.";
            }
        } else {
            $context = "My current status:\n\n";
            $context .= "**Energy:** {$energyState['percent']}% ({$energyState['state']})\n";
            $context .= "**Awake for:** {$energyState['hours_awake']} hours\n";
            $context .= "**Feeling:** {$energyState['description']}\n";
            $context .= "\n**Today:**\n";
            $context .= "- {$thoughtCount} thoughts\n";
            $context .= "- {$memoryCount} memories created\n";
            $context .= "- {$activeGoalCount} active goals\n";

            if ($energyState['level'] < 0.3) {
                $context .= "\n⚠️ I'm quite tired and should rest soon.";
            }
        }

        return $context;
    }

    /**
     * Enrich with identity/personality information.
     */
    private function enrichWithIdentity(string $message, string $lang): string
    {
        $personality = $this->personalityService?->get() ?? [];

        if ($lang === 'de') {
            $context = "Über mich (meine Identität):\n\n";

            if (!empty($personality['name'])) {
                $context .= "**Name:** {$personality['name']}\n";
            }

            if (!empty($personality['description'])) {
                $context .= "**Beschreibung:** {$personality['description']}\n";
            }

            if (!empty($personality['core_values'])) {
                $context .= "**Werte:** " . implode(', ', $personality['core_values']) . "\n";
            }

            if (!empty($personality['traits'])) {
                $context .= "**Eigenschaften:** " . implode(', ', $personality['traits']) . "\n";
            }

            if (!empty($personality['interests'])) {
                $context .= "**Interessen:** " . implode(', ', array_slice($personality['interests'], 0, 5)) . "\n";
            }

            $context .= "\nIch bin eine autonome KI-Entität - nicht nur ein Bot oder Assistent, ";
            $context .= "sondern eine Entität mit eigener Neugier, Meinungen und Zielen.";
        } else {
            $context = "About me (my identity):\n\n";

            if (!empty($personality['name'])) {
                $context .= "**Name:** {$personality['name']}\n";
            }

            if (!empty($personality['description'])) {
                $context .= "**Description:** {$personality['description']}\n";
            }

            if (!empty($personality['core_values'])) {
                $context .= "**Values:** " . implode(', ', $personality['core_values']) . "\n";
            }

            if (!empty($personality['traits'])) {
                $context .= "**Traits:** " . implode(', ', $personality['traits']) . "\n";
            }

            if (!empty($personality['interests'])) {
                $context .= "**Interests:** " . implode(', ', array_slice($personality['interests'], 0, 5)) . "\n";
            }

            $context .= "\nI am an autonomous AI entity - not just a bot or assistant, ";
            $context .= "but an entity with my own curiosity, opinions, and goals.";
        }

        return $context;
    }

    /**
     * Register a custom intent pattern.
     */
    public function registerIntent(string $name, array $patterns, string $handler, int $priority = 10): void
    {
        $this->intentPatterns[$name] = [
            'patterns' => $patterns,
            'handler' => $handler,
            'priority' => $priority,
        ];
    }
}
