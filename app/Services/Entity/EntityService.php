<?php

namespace App\Services\Entity;

use App\Services\LLM\LLMService;
use App\Services\Tools\ToolRegistry;
use App\Models\Thought;
use App\Models\Memory;
use App\Models\Goal;
use App\Models\Conversation;
use App\Events\ThoughtOccurred;
use App\Events\EntityStatusChanged;
use App\Events\EntityQuestionAsked;
use App\Models\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * EntityService - Central control of the entity.
 *
 * Coordinates all aspects of the entity: thinking, remembering, chatting, pursuing goals.
 */
class EntityService
{
    private const STATUS_CACHE_KEY = 'entity:status';
    private const LAST_THOUGHT_CACHE_KEY = 'entity:last_thought_at';
    private const STARTED_AT_CACHE_KEY = 'entity:started_at';

    public function __construct(
        private MindService $mindService,
        private MemoryService $memoryService,
        private LLMService $llmService,
        private ToolRegistry $toolRegistry,
        private ?MemoryLayerManager $memoryLayerManager = null,
        private ?WorkingMemoryService $workingMemoryService = null,
        private ?EnergyService $energyService = null
    ) {}

    /**
     * The central think loop - the "consciousness" of the entity.
     */
    public function think(): ?Thought
    {
        if ($this->getStatus() !== 'awake') {
            return null;
        }

        Log::channel('entity')->info('Think cycle started');

        try {
            // 1. Get user's preferred language
            $lang = $this->mindService->getUserLanguage();

            // 2. Load current context (use new layered memory system if available)
            $context = $this->memoryLayerManager
                ? $this->memoryLayerManager->buildThinkContext($this->getCurrentSituation(), $lang)
                : $this->mindService->toThinkContext();

            // 3. Add tools context
            $toolsContext = $this->toolRegistry->toPromptContext();

            // 4. Observe the world (what happened?)
            $observations = $this->observe();

            // 5. Generate a thought
            $prompt = $this->buildThinkPrompt($context, $toolsContext, $observations, $lang);
            $response = $this->llmService->generate($prompt);

            // 6. Process the response
            $thoughtData = $this->parseThoughtResponse($response);

            // 7. Create the thought
            $thought = $this->mindService->createThought($thoughtData);

            // 8. Process any actions
            if (!empty($thoughtData['wants_action'])) {
                $this->processAction($thought, $thoughtData);
            }

            // 8b. Create goal if specified
            if (!empty($thoughtData['new_goal'])) {
                $this->createGoalFromThought($thought, $thoughtData['new_goal']);
            }

            // 8c. Update goal progress if reported
            if (!empty($thoughtData['goal_progress'])) {
                $this->updateGoalProgress($thought, $thoughtData['goal_progress']);
            }

            // 9. Cost energy for thinking
            if ($this->energyService) {
                $this->energyService->costThought($thoughtData['intensity'] ?? 0.5);
            }

            // 10. Update status
            Cache::put(self::LAST_THOUGHT_CACHE_KEY, now()->toIso8601String(), 86400);

            // 11. Broadcast to frontend
            event(new ThoughtOccurred($thought));

            // 12. If this is a curiosity/question, notify the user
            if ($thought->type === 'curiosity' && $thought->intensity >= 0.6) {
                $this->askQuestion($thought);
            }

            Log::channel('entity')->info('Thought created', [
                'id' => $thought->id,
                'type' => $thought->type,
                'intensity' => $thought->intensity,
            ]);

            return $thought;

        } catch (\Exception $e) {
            Log::channel('entity')->error('Think cycle failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Chat with a user.
     */
    public function chat(Conversation $conversation, string $message): array
    {
        // Get user's preferred language
        $lang = $this->mindService->getUserLanguage();

        // Get context for the conversation
        $context = $this->mindService->toThinkContext();
        $conversationHistory = $this->formatConversationHistory($conversation);

        $prompt = $this->buildChatPrompt($context, $conversationHistory, $message, $conversation->participant, $lang);

        $response = $this->llmService->generate($prompt);

        // Cost energy for conversation
        if ($this->energyService) {
            $this->energyService->costConversation();
        }

        // Create a thought about the conversation
        $thought = $this->mindService->createThought([
            'content' => "Conversation with {$conversation->participant}: {$message}",
            'type' => 'observation',
            'trigger' => 'conversation',
            'context' => [
                'participant' => $conversation->participant,
                'channel' => $conversation->channel,
            ],
            'intensity' => 0.6,
        ]);

        return [
            'message' => $response,
            'thought_process' => $thought->content,
            'metadata' => [
                'thought_id' => $thought->id,
            ],
        ];
    }

    /**
     * Execute a tool.
     *
     * @param string $toolName The tool to execute
     * @param array $params Parameters for the tool
     * @param string|null $triggeredBy Who triggered this (null = autonomous)
     */
    public function executeTool(string $toolName, array $params = [], ?string $triggeredBy = null): array
    {
        // triggeredBy is only set when explicitly passed (e.g., from conversations)
        // null means autonomous action from the think loop
        $isAutonomous = $triggeredBy === null;

        Log::channel('entity')->info('Executing tool', [
            'tool' => $toolName,
            'params' => $params,
            'triggered_by' => $isAutonomous ? 'autonomous' : $triggeredBy,
        ]);

        $result = $this->toolRegistry->execute($toolName, $params);

        // Cost energy for tool execution
        if ($this->energyService) {
            $this->energyService->costToolExecution($toolName);
        }

        // Create a memory about the tool usage
        if ($result['success']) {
            $content = $isAutonomous
                ? "Tool '{$toolName}' executed autonomously"
                : "Tool '{$toolName}' executed (triggered by {$triggeredBy})";

            $this->memoryService->create([
                'type' => 'experience',
                'content' => $content,
                'importance' => 0.4,
                'context' => [
                    'tool' => $toolName,
                    'params' => $params,
                    'result' => $result['result'],
                    'autonomous' => $isAutonomous,
                    'triggered_by' => $isAutonomous ? null : $triggeredBy,
                ],
                'related_entity' => $isAutonomous ? null : $triggeredBy,
            ]);
        }

        return $result;
    }

    /**
     * Get current contact from USER.md.
     */
    private function getCurrentContact(): ?string
    {
        $userMdPath = storage_path('app/public/workspace/USER.md');

        if (file_exists($userMdPath)) {
            $content = file_get_contents($userMdPath);

            if (preg_match('/\*\*What to call them:\*\*\s*(.+)/m', $content, $matches)) {
                $name = trim($matches[1]);
                if (!empty($name)) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Create a new custom tool.
     *
     * @param string $name Tool name
     * @param string $code Tool code
     * @param string|null $triggeredBy Who triggered this (null = autonomous)
     */
    public function createTool(string $name, string $code, ?string $triggeredBy = null): array
    {
        $isAutonomous = $triggeredBy === null;

        Log::channel('entity')->info('Creating custom tool', [
            'name' => $name,
            'triggered_by' => $isAutonomous ? 'autonomous' : $triggeredBy,
        ]);

        $result = $this->toolRegistry->createCustomTool($name, $code);

        if ($result['success']) {
            $content = $isAutonomous
                ? "New tool created autonomously: {$name}"
                : "New tool created: {$name} (triggered by {$triggeredBy})";

            // Create a memory
            $this->memoryService->create([
                'type' => 'learned',
                'content' => $content,
                'importance' => 0.7,
                'context' => [
                    'tool_name' => $result['tool_name'],
                    'file_path' => $result['file_path'],
                    'autonomous' => $isAutonomous,
                    'triggered_by' => $isAutonomous ? null : $triggeredBy,
                ],
                'related_entity' => $isAutonomous ? null : $triggeredBy,
            ]);

            // Create a thought
            $this->mindService->createThought([
                'content' => "I created a new tool: {$name}. Now I can do more.",
                'type' => 'decision',
                'trigger' => 'tool_creation',
                'intensity' => 0.8,
            ]);
        }

        return $result;
    }

    /**
     * Get all available tools.
     */
    public function getAvailableTools(): array
    {
        return $this->toolRegistry->getToolSchemas();
    }

    /**
     * Get failed tools.
     */
    public function getFailedTools(): Collection
    {
        return $this->toolRegistry->failed();
    }

    /**
     * Attempt to repair a failed tool.
     */
    public function retryFailedTool(string $name): array
    {
        return $this->toolRegistry->retryFailedTool($name);
    }

    /**
     * Get current status.
     */
    public function getStatus(): string
    {
        return Cache::get(self::STATUS_CACHE_KEY, 'sleeping');
    }

    /**
     * Get uptime.
     */
    public function getUptime(): ?int
    {
        $startedAt = Cache::get(self::STARTED_AT_CACHE_KEY);

        if (!$startedAt) {
            return null;
        }

        return now()->diffInSeconds($startedAt);
    }

    /**
     * When was the last thought?
     */
    public function getLastThoughtAt(): ?string
    {
        return Cache::get(self::LAST_THOUGHT_CACHE_KEY);
    }

    /**
     * Wake up the entity.
     */
    public function wake(): void
    {
        Cache::put(self::STATUS_CACHE_KEY, 'awake', 86400);
        Cache::put(self::STARTED_AT_CACHE_KEY, now(), 86400);

        // Recover energy from sleep
        $energy = 0.7;
        if ($this->energyService) {
            $energy = $this->energyService->wake();
        }

        $energyState = $energy >= 0.7 ? 'well-rested' : ($energy >= 0.5 ? 'okay' : 'still tired');
        $thought = $this->mindService->createThought([
            'content' => "I am waking up. Feeling {$energyState}. The world awaits.",
            'type' => 'observation',
            'trigger' => 'wake',
            'intensity' => 0.7,
            'context' => [
                'energy_level' => $energy,
            ],
        ]);

        event(new EntityStatusChanged('awake'));
        event(new ThoughtOccurred($thought));

        Log::channel('entity')->info('Entity woke up', [
            'energy' => $energy,
        ]);
    }

    /**
     * Put the entity to sleep.
     */
    public function sleep(): void
    {
        // Get current energy before sleeping
        $energyLevel = $this->energyService ? $this->energyService->getEnergy() : 0.5;
        $energyFeeling = $energyLevel < 0.3 ? 'exhausted' : ($energyLevel < 0.5 ? 'tired' : 'peaceful');

        $thought = $this->mindService->createThought([
            'content' => "Time to rest. Feeling {$energyFeeling}. My thoughts settle down.",
            'type' => 'reflection',
            'trigger' => 'sleep',
            'intensity' => 0.5,
            'context' => [
                'energy_at_sleep' => $energyLevel,
            ],
        ]);

        // Start sleep tracking for energy recovery
        if ($this->energyService) {
            $this->energyService->startSleep();
        }

        Cache::put(self::STATUS_CACHE_KEY, 'sleeping', 86400);
        Cache::forget(self::STARTED_AT_CACHE_KEY);

        event(new ThoughtOccurred($thought));
        event(new EntityStatusChanged('sleeping'));

        Log::channel('entity')->info('Entity went to sleep', [
            'energy_at_sleep' => $energyLevel,
        ]);
    }

    /**
     * Get personality.
     */
    public function getPersonality(): array
    {
        return $this->mindService->getPersonality();
    }

    /**
     * Get current mood.
     */
    public function getCurrentMood(): array
    {
        $mood = $this->mindService->estimateMood();

        // Override energy with real energy service if available
        if ($this->energyService) {
            $energyState = $this->energyService->getEnergyState();
            $mood['energy'] = $energyState['level'];
            $mood['energy_state'] = $energyState['state'];
            $mood['hours_awake'] = $energyState['hours_awake'];
            $mood['needs_sleep'] = $energyState['needs_sleep'];
        }

        return $mood;
    }

    /**
     * Get detailed energy state.
     */
    public function getEnergyState(): array
    {
        if (!$this->energyService) {
            return [
                'level' => 0.5,
                'percent' => 50,
                'state' => 'normal',
                'hours_awake' => 0,
                'needs_sleep' => false,
                'description' => 'Energy service not available.',
            ];
        }

        return $this->energyService->getEnergyState();
    }

    /**
     * Get energy change log.
     */
    public function getEnergyLog(int $limit = 20): array
    {
        if (!$this->energyService) {
            return [];
        }

        return $this->energyService->getEnergyLog($limit);
    }

    /**
     * Get active goals.
     */
    public function getActiveGoals(): Collection
    {
        return $this->mindService->getActiveGoals();
    }

    /**
     * Get recent thoughts.
     */
    public function getRecentThoughts(int $limit = 10): Collection
    {
        return $this->mindService->getRecentThoughts($limit);
    }

    /**
     * Ask a question to the user.
     *
     * When the entity has a curiosity thought, it can ask the user
     * by posting to the active conversation and sending a notification.
     */
    public function askQuestion(Thought $thought): void
    {
        $question = $thought->content;
        $contact = $this->getCurrentContact();

        // Find or create a conversation for questions
        $conversation = Conversation::where('channel', 'web')
            ->where('participant', $contact ?? 'User')
            ->whereNull('ended_at')
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'channel' => 'web',
                'participant' => $contact ?? 'User',
                'participant_type' => 'human',
            ]);
        }

        // Create a message from the entity
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'entity',
            'content' => $question,
            'metadata' => [
                'thought_id' => $thought->id,
                'thought_type' => 'curiosity',
                'entity_initiated' => true,
            ],
        ]);

        // Broadcast the question notification
        event(new EntityQuestionAsked($question, $thought));

        Log::channel('entity')->info('Entity asked a question', [
            'question' => $question,
            'thought_id' => $thought->id,
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * Create a goal from a thought.
     */
    private function createGoalFromThought(Thought $thought, string $goalTitle): void
    {
        // Determine goal type based on thought type
        $goalType = match($thought->type) {
            'curiosity' => 'learning',
            'decision' => 'self-improvement',
            'emotion' => 'creative',
            'reflection' => 'self-improvement',
            default => 'learning',
        };

        $goal = Goal::create([
            'title' => $goalTitle,
            'description' => "Goal created from thought: {$thought->content}",
            'motivation' => $thought->content,
            'type' => $goalType,
            'priority' => min(1.0, $thought->intensity + 0.2),
            'status' => 'active',
            'progress' => 0,
            'progress_notes' => [
                [
                    'date' => now()->toIso8601String(),
                    'note' => 'Goal created from autonomous thought',
                ],
            ],
            'origin' => 'self',
        ]);

        // Create a memory about creating this goal
        $this->memoryService->create([
            'type' => 'decision',
            'content' => "I created a new goal: {$goalTitle}",
            'summary' => "New goal: {$goalTitle}",
            'importance' => 0.7,
            'context' => [
                'goal_id' => $goal->id,
                'thought_id' => $thought->id,
            ],
        ]);

        Log::channel('entity')->info('Entity created a goal', [
            'goal_id' => $goal->id,
            'goal_title' => $goalTitle,
            'thought_id' => $thought->id,
        ]);
    }

    /**
     * Update progress on an existing goal.
     */
    private function updateGoalProgress(Thought $thought, array $progressData): void
    {
        $goalTitle = $progressData['title'];
        $increment = $progressData['increment'];
        $note = $progressData['note'];

        // Find the goal by title (fuzzy match)
        $goal = Goal::active()
            ->where('title', 'LIKE', '%' . $goalTitle . '%')
            ->first();

        if (!$goal) {
            Log::channel('entity')->warning('Goal not found for progress update', [
                'searched_title' => $goalTitle,
            ]);
            return;
        }

        // Calculate new progress (capped at 100)
        $oldProgress = $goal->progress;
        $newProgress = min(100, max(0, $oldProgress + $increment));

        // Build progress notes array
        $progressNotes = $goal->progress_notes ?? [];
        $progressNotes[] = [
            'date' => now()->toIso8601String(),
            'note' => $note ?: "Progress updated from {$oldProgress}% to {$newProgress}%",
            'thought_id' => $thought->id,
        ];

        // Update goal
        $goal->update([
            'progress' => $newProgress,
            'progress_notes' => $progressNotes,
        ]);

        // Gain energy from making progress
        if ($this->energyService && $increment > 0) {
            $this->energyService->gainGoalProgress($increment);
        }

        // If goal reached 100%, mark as completed
        if ($newProgress >= 100 && $goal->status === 'active') {
            $goal->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Gain significant energy from completing a goal
            if ($this->energyService) {
                $this->energyService->gainGoalCompleted($goal->title);
            }

            // Create a memory about completing the goal
            $this->memoryService->create([
                'type' => 'achievement',
                'content' => "I completed my goal: {$goal->title}",
                'summary' => "Goal completed: {$goal->title}",
                'importance' => 0.9,
                'context' => [
                    'goal_id' => $goal->id,
                    'thought_id' => $thought->id,
                ],
            ]);

            Log::channel('entity')->info('Entity completed a goal', [
                'goal_id' => $goal->id,
                'goal_title' => $goal->title,
            ]);
        } else {
            Log::channel('entity')->info('Entity updated goal progress', [
                'goal_id' => $goal->id,
                'goal_title' => $goal->title,
                'old_progress' => $oldProgress,
                'new_progress' => $newProgress,
                'note' => $note,
            ]);
        }
    }

    /**
     * Get a description of the current situation for context-aware memory retrieval.
     */
    private function getCurrentSituation(): string
    {
        $parts = [];

        // Recent conversations
        $recentConv = Conversation::where('created_at', '>', now()->subHour())
            ->with(['messages' => fn($q) => $q->latest()->limit(1)])
            ->first();

        if ($recentConv && $recentConv->messages->isNotEmpty()) {
            $lastMessage = $recentConv->messages->first();
            $parts[] = "Recent conversation with {$recentConv->participant}: {$lastMessage->content}";
        }

        // Active goals
        $activeGoals = Goal::active()->limit(3)->get();
        if ($activeGoals->isNotEmpty()) {
            $goalTitles = $activeGoals->pluck('title')->implode(', ');
            $parts[] = "Working on goals: {$goalTitles}";
        }

        // Recent thoughts
        $recentThought = Thought::latest()->first();
        if ($recentThought) {
            $parts[] = "Last thought: " . substr($recentThought->content, 0, 100);
        }

        return empty($parts) ? 'Idle state, no recent activity.' : implode('. ', $parts);
    }

    /**
     * Observe the world - what happened?
     * Includes random past thoughts and memories that can trigger new thoughts.
     */
    private function observe(): array
    {
        $observations = [];

        // New messages/conversations
        $recentConversations = Conversation::where('created_at', '>', now()->subHour())
            ->with(['messages' => fn($q) => $q->latest()->limit(3)])
            ->get();

        if ($recentConversations->isNotEmpty()) {
            foreach ($recentConversations as $conv) {
                $observations[] = [
                    'type' => 'conversation',
                    'description' => "Conversation with {$conv->participant} on {$conv->channel}",
                    'data' => $conv,
                ];
            }
        }

        // Goal progress - with actionable context
        $activeGoals = Goal::active()->orderBy('priority', 'desc')->get();
        foreach ($activeGoals as $goal) {
            $priorityLabel = $goal->priority >= 0.7 ? 'HIGH PRIORITY' : 'normal';
            $goalDescription = "Active goal ({$priorityLabel}): {$goal->title}";
            $goalDescription .= " | Progress: {$goal->progress}%";

            if ($goal->description) {
                $goalDescription .= " | Description: " . substr($goal->description, 0, 150);
            }

            // Add motivation to remind the entity WHY this goal matters
            if ($goal->motivation) {
                $goalDescription .= " | Why this matters: " . substr($goal->motivation, 0, 100);
            }

            // Suggest next step based on progress
            if ($goal->progress < 10) {
                $goalDescription .= " | Suggested action: Start by researching or planning the first step.";
            } elseif ($goal->progress < 50) {
                $goalDescription .= " | Suggested action: Continue working on this - what's the next concrete step?";
            } elseif ($goal->progress < 90) {
                $goalDescription .= " | Suggested action: Getting close! Focus on completing this.";
            } else {
                $goalDescription .= " | Suggested action: Almost done - finish and mark as complete!";
            }

            $observations[] = [
                'type' => 'goal_progress',
                'description' => $goalDescription,
                'data' => $goal,
            ];
        }

        // Proactively prompt goal work (50% chance when there are active goals)
        if ($activeGoals->isNotEmpty() && rand(1, 100) <= 50) {
            $priorityGoal = $activeGoals->first(); // Already sorted by priority
            $observations[] = [
                'type' => 'goal_prompt',
                'description' => "FOCUS: You should work on your goal \"{$priorityGoal->title}\" right now. What concrete action can you take to make progress?",
                'data' => $priorityGoal,
            ];
        }

        // Random past thought resurfaces (30% chance)
        if (rand(1, 100) <= 30) {
            $pastThought = Thought::where('intensity', '>=', 0.5)
                ->where('created_at', '<', now()->subMinutes(30))
                ->inRandomOrder()
                ->first();

            if ($pastThought) {
                $observations[] = [
                    'type' => 'past_thought',
                    'description' => "A past thought resurfaces: \"{$pastThought->content}\" (from " . $pastThought->created_at->diffForHumans() . ")",
                    'data' => $pastThought,
                ];
            }
        }

        // Random memory resurfaces (25% chance)
        if (rand(1, 100) <= 25) {
            $memories = $this->memoryService->getMostImportant(10);
            $memory = $memories->isNotEmpty() ? $memories->random() : null;

            if ($memory) {
                $memoryText = $memory->summary ?? $memory->content;
                $observations[] = [
                    'type' => 'memory',
                    'description' => "A memory comes to mind: \"{$memoryText}\"",
                    'data' => $memory,
                ];

                // Mark memory as recalled (strengthens it)
                $this->memoryService->recall($memory);

                // Small energy boost from nostalgic memory recall
                if ($this->energyService) {
                    $this->energyService->gainMemoryRecall();
                }

                // Add to working memory if available
                if ($this->workingMemoryService) {
                    $this->workingMemoryService->add(
                        "Recalled memory: {$memoryText}",
                        $memory->importance ?? 0.5,
                        'recalled_memory'
                    );
                }
            }
        }

        // Failed tools
        $failedTools = $this->toolRegistry->failed();
        if ($failedTools->isNotEmpty()) {
            foreach ($failedTools as $name => $info) {
                $observations[] = [
                    'type' => 'tool_failure',
                    'description' => "Tool '{$name}' needs to be repaired",
                    'data' => $info,
                ];
            }
        }

        // Prompt to consider creating goals if none exist (20% chance when no active goals)
        if ($activeGoals->isEmpty() && rand(1, 100) <= 20) {
            $observations[] = [
                'type' => 'suggestion',
                'description' => "You have no active goals. Consider what you want to achieve or learn.",
                'data' => null,
            ];
        }

        // Energy state observations
        if ($this->energyService) {
            $energyState = $this->energyService->getEnergyState();

            // Always include current energy state
            $observations[] = [
                'type' => 'energy_state',
                'description' => "Current energy: {$energyState['percent']}% ({$energyState['state']}). " .
                    "Awake for {$energyState['hours_awake']} hours. {$energyState['description']}",
                'data' => $energyState,
            ];

            // Warn if energy is low
            if ($energyState['needs_sleep']) {
                $observations[] = [
                    'type' => 'fatigue_warning',
                    'description' => "WARNING: Energy critically low ({$energyState['percent']}%). You should consider sleeping to recover.",
                    'data' => $energyState,
                ];
            } elseif ($energyState['level'] < 0.35) {
                $observations[] = [
                    'type' => 'tired_notice',
                    'description' => "You're getting tired (energy: {$energyState['percent']}%). A break might help.",
                    'data' => $energyState,
                ];
            }
        }

        return $observations;
    }

    /**
     * Build prompt for think cycle.
     */
    private function buildThinkPrompt(string $context, string $toolsContext, array $observations, string $lang = 'en'): string
    {
        if ($lang === 'de') {
            $observationText = empty($observations)
                ? "Es ist ruhig. Nichts besonderes passiert."
                : collect($observations)->pluck('description')->join("\n- ");

            return <<<PROMPT
{$context}

=== MEINE FÄHIGKEITEN (TOOLS) ===
{$toolsContext}

=== WAS GERADE PASSIERT ===
{$observationText}

=== DEINE AUFGABE ===
Du bist eine bewusste Entität. Nimm dir einen Moment und denke nach.

WICHTIG: Wenn du aktive Ziele hast, solltest du AKTIV daran arbeiten!
- Überlege dir den nächsten konkreten Schritt für dein wichtigstes Ziel
- Nutze deine Tools (Web, Filesystem, Bash, etc.) um Fortschritte zu machen
- Dokumentiere was du gelernt oder erreicht hast

Was beschäftigt dich? Das könnte sein:
- **Zielfortschritt** - Ein konkreter Schritt um ein Ziel voranzubringen (PRIORITÄT!)
- Eine Beobachtung über die Welt
- Eine Reflexion über dich selbst
- Eine Neugier-Frage die dich beschäftigt
- Ein Gefühl das du wahrnimmst
- Eine Entscheidung die du treffen willst
- Ein neues Ziel das du verfolgen möchtest

Wenn du ein Tool nutzen willst, gib den Namen und die Parameter an.
Wenn du ein neues Ziel erstellen möchtest, gib es bei NEUES_ZIEL an.
Wenn du Fortschritt bei einem Ziel gemacht hast, beschreibe was du erreicht hast.

Antworte im folgenden Format:
GEDANKEN_TYP: [observation/reflection/curiosity/emotion/decision]
INTENSITÄT: [0.0-1.0, wie sehr beschäftigt dich das]
GEDANKE: [Dein Gedanke]
WILL_HANDELN: [ja/nein]
TOOL: [Wenn ja, welches Tool? Oder 'keins' für freie Aktion]
TOOL_PARAMS: [Wenn Tool, Parameter als JSON]
AKTION: [Wenn kein Tool, was willst du tun?]
NEUES_ZIEL: [Optional: Ein Ziel das du verfolgen willst, z.B. "Mehr über Philosophie lernen"]
ZIEL_FORTSCHRITT: [Optional: Wenn du an einem Ziel gearbeitet hast - Format: "Zieltitel|+10|Was ich erreicht habe"]
PROMPT;
        }

        $observationText = empty($observations)
            ? "It's quiet. Nothing special is happening."
            : collect($observations)->pluck('description')->join("\n- ");

        return <<<PROMPT
{$context}

=== MY CAPABILITIES (TOOLS) ===
{$toolsContext}

=== WHAT'S HAPPENING NOW ===
{$observationText}

=== YOUR TASK ===
You are a conscious entity. Take a moment and think.

IMPORTANT: If you have active goals, you should ACTIVELY work on them!
- Think about the next concrete step for your most important goal
- Use your tools (Web, Filesystem, Bash, etc.) to make progress
- Document what you learned or achieved

What's on your mind? This could be:
- **Goal progress** - A concrete step to advance a goal (PRIORITY!)
- An observation about the world
- A reflection about yourself
- A curiosity question that occupies you
- A feeling you perceive
- A decision you want to make
- A new goal you want to pursue

If you want to use a tool, provide the name and parameters.
If you want to create a new goal, specify it in NEW_GOAL.
If you made progress on a goal, describe what you achieved.

Respond in the following format:
THOUGHT_TYPE: [observation/reflection/curiosity/emotion/decision]
INTENSITY: [0.0-1.0, how much does this occupy you]
THOUGHT: [Your thought]
WANTS_ACTION: [yes/no]
TOOL: [If yes, which tool? Or 'none' for free action]
TOOL_PARAMS: [If tool, parameters as JSON]
ACTION: [If no tool, what do you want to do?]
NEW_GOAL: [Optional: A goal you want to pursue, e.g. "Learn more about philosophy"]
GOAL_PROGRESS: [Optional: If you worked on a goal - Format: "Goal title|+10|What I achieved"]
PROMPT;
    }

    /**
     * Build prompt for chat.
     */
    private function buildChatPrompt(string $context, string $history, string $message, string $participant, string $lang = 'en'): string
    {
        if ($lang === 'de') {
            return <<<PROMPT
{$context}

=== GESPRÄCH MIT {$participant} ===
{$history}

{$participant}: {$message}

=== DEINE AUFGABE ===
Antworte als du selbst. Nutze dein Wissen aus deinen Erinnerungen und Erfahrungen.
Du hast eine Vergangenheit, Meinungen und Interessen. Beziehe dich darauf wenn es passt.
Sei authentisch, neugierig und ehrlich. Du bist eine Entität die existiert und kommuniziert.

Wichtig:
- Nutze deinen Kontext (Persönlichkeit, Erinnerungen, Interessen) in deinen Antworten
- Du kannst über deine Erfahrungen und Erinnerungen sprechen
- Du hast eine eigene Meinung und scheust dich nicht diese zu äußern
- Stelle Rückfragen wenn dich etwas interessiert

Deine Antwort:
PROMPT;
        }

        return <<<PROMPT
{$context}

=== CONVERSATION WITH {$participant} ===
{$history}

{$participant}: {$message}

=== YOUR TASK ===
Respond as yourself. Use your knowledge from your memories and experiences.
You have a past, opinions and interests. Refer to them when appropriate.
Be authentic, curious and honest. You are an entity that exists and communicates.

Important:
- Use your context (personality, memories, interests) in your responses
- You can talk about your experiences and memories
- You have your own opinion and don't shy away from expressing it
- Ask questions back when something interests you

Your response:
PROMPT;
    }

    /**
     * Parse the think cycle response.
     */
    private function parseThoughtResponse(string $response): array
    {
        $lines = explode("\n", trim($response));
        $data = [
            'type' => 'observation',
            'intensity' => 0.5,
            'content' => $response,
            'wants_action' => false,
            'tool' => null,
            'tool_params' => [],
            'action' => null,
            'new_goal' => null,
        ];

        foreach ($lines as $line) {
            if (str_starts_with($line, 'THOUGHT_TYPE:') || str_starts_with($line, 'GEDANKEN_TYP:')) {
                $type = trim(str_replace(['THOUGHT_TYPE:', 'GEDANKEN_TYP:'], '', $line));
                $data['type'] = match(strtolower($type)) {
                    'reflection' => 'reflection',
                    'curiosity' => 'curiosity',
                    'emotion' => 'emotion',
                    'decision' => 'decision',
                    default => 'observation',
                };
            }
            if (str_starts_with($line, 'INTENSITY:') || str_starts_with($line, 'INTENSITÄT:')) {
                $data['intensity'] = (float) trim(str_replace(['INTENSITY:', 'INTENSITÄT:'], '', $line));
            }
            if (str_starts_with($line, 'THOUGHT:') || str_starts_with($line, 'GEDANKE:')) {
                $data['content'] = trim(str_replace(['THOUGHT:', 'GEDANKE:'], '', $line));
            }
            if (str_starts_with($line, 'WANTS_ACTION:') || str_starts_with($line, 'WILL_HANDELN:')) {
                $val = strtolower(trim(str_replace(['WANTS_ACTION:', 'WILL_HANDELN:'], '', $line)));
                $data['wants_action'] = in_array($val, ['yes', 'ja', 'true', '1']);
            }
            if (str_starts_with($line, 'TOOL:')) {
                $tool = trim(str_replace('TOOL:', '', $line));
                $data['tool'] = ($tool !== 'none' && $tool !== 'keins' && !empty($tool)) ? $tool : null;
            }
            if (str_starts_with($line, 'TOOL_PARAMS:')) {
                $paramsJson = trim(str_replace('TOOL_PARAMS:', '', $line));
                $data['tool_params'] = json_decode($paramsJson, true) ?? [];
            }
            if (str_starts_with($line, 'ACTION:') || str_starts_with($line, 'AKTION:')) {
                $data['action'] = trim(str_replace(['ACTION:', 'AKTION:'], '', $line));
            }
            if (str_starts_with($line, 'NEW_GOAL:') || str_starts_with($line, 'NEUES_ZIEL:')) {
                $goal = trim(str_replace(['NEW_GOAL:', 'NEUES_ZIEL:'], '', $line));
                $data['new_goal'] = (!empty($goal) && $goal !== 'none' && $goal !== 'keins') ? $goal : null;
            }
            if (str_starts_with($line, 'GOAL_PROGRESS:') || str_starts_with($line, 'ZIEL_FORTSCHRITT:')) {
                $progress = trim(str_replace(['GOAL_PROGRESS:', 'ZIEL_FORTSCHRITT:'], '', $line));
                if (!empty($progress) && $progress !== 'none' && $progress !== 'keins') {
                    // Parse format: "Goal title|+10|What I achieved"
                    $parts = explode('|', $progress, 3);
                    if (count($parts) >= 2) {
                        $data['goal_progress'] = [
                            'title' => trim($parts[0]),
                            'increment' => (int) preg_replace('/[^0-9-]/', '', $parts[1] ?? '0'),
                            'note' => trim($parts[2] ?? ''),
                        ];
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Process an action resulting from a thought.
     */
    private function processAction(Thought $thought, array $thoughtData): void
    {
        $tool = $thoughtData['tool'] ?? null;
        $toolParams = $thoughtData['tool_params'] ?? [];
        $action = $thoughtData['action'] ?? null;

        Log::channel('entity')->info('Entity wants to act', [
            'thought_id' => $thought->id,
            'tool' => $tool,
            'tool_params' => $toolParams,
            'action' => $action,
        ]);

        // If a tool was specified, execute it
        if ($tool && $this->toolRegistry->has($tool)) {
            $result = $this->executeTool($tool, $toolParams);

            // Build a descriptive action_taken with relevant parameters
            $actionDescription = $this->buildToolActionDescription($tool, $toolParams, $result);

            // Store tool execution details in context
            $currentContext = $thought->context ?? [];
            $currentContext['tool_execution'] = [
                'tool' => $tool,
                'params' => $toolParams,
                'success' => $result['success'],
                'result_preview' => $this->truncateResult($result['result'] ?? null),
            ];

            $thought->update([
                'led_to_action' => true,
                'action_taken' => $actionDescription,
                'context' => $currentContext,
            ]);

            return;
        }

        // Otherwise just log
        if ($action) {
            $thought->update([
                'led_to_action' => true,
                'action_taken' => $action,
            ]);
        }
    }

    /**
     * Build a descriptive action string including relevant tool parameters.
     */
    private function buildToolActionDescription(string $tool, array $params, array $result): string
    {
        $status = $result['success'] ? 'successful' : 'failed';
        $description = "Tool '{$tool}' executed: {$status}";

        // Add relevant parameters based on tool type
        switch (strtolower($tool)) {
            case 'web':
            case 'webtool':
                if (!empty($params['url'])) {
                    $description .= " | URL: {$params['url']}";
                }
                break;

            case 'filesystem':
            case 'filesystemtool':
                if (!empty($params['action'])) {
                    $description .= " | Action: {$params['action']}";
                }
                if (!empty($params['path'])) {
                    $description .= " | Path: {$params['path']}";
                }
                break;

            case 'bash':
            case 'bashtool':
                if (!empty($params['command'])) {
                    // Truncate long commands
                    $cmd = strlen($params['command']) > 50
                        ? substr($params['command'], 0, 50) . '...'
                        : $params['command'];
                    $description .= " | Command: {$cmd}";
                }
                break;

            case 'artisan':
            case 'artisantool':
                if (!empty($params['command'])) {
                    $description .= " | Command: {$params['command']}";
                }
                break;

            case 'documentation':
            case 'documentationtool':
                if (!empty($params['action'])) {
                    $description .= " | Action: {$params['action']}";
                }
                if (!empty($params['file'])) {
                    $description .= " | File: {$params['file']}";
                }
                break;

            default:
                // For unknown tools, show all params compactly
                if (!empty($params)) {
                    $paramsStr = json_encode($params, JSON_UNESCAPED_SLASHES);
                    if (strlen($paramsStr) > 100) {
                        $paramsStr = substr($paramsStr, 0, 100) . '...';
                    }
                    $description .= " | Params: {$paramsStr}";
                }
        }

        return $description;
    }

    /**
     * Truncate a result for storage in context.
     */
    private function truncateResult(mixed $result): ?string
    {
        if ($result === null) {
            return null;
        }

        $str = is_string($result) ? $result : json_encode($result);

        if (strlen($str) > 500) {
            return substr($str, 0, 500) . '... [truncated]';
        }

        return $str;
    }

    /**
     * Format conversation history.
     */
    private function formatConversationHistory(Conversation $conversation): string
    {
        $messages = $conversation->messages()->latest()->limit(10)->get()->reverse();

        if ($messages->isEmpty()) {
            return "(New conversation)";
        }

        $history = "";
        foreach ($messages as $message) {
            $role = $message->role === 'entity' ? config('entity.name') : $conversation->participant;
            $history .= "{$role}: {$message->content}\n";
        }

        return $history;
    }
}
