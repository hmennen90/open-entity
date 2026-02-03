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
        private ToolRegistry $toolRegistry
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

            // 2. Load current context
            $context = $this->mindService->toThinkContext();

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

            // 9. Update status
            Cache::put(self::LAST_THOUGHT_CACHE_KEY, now()->toIso8601String(), 86400);

            // 10. Broadcast to frontend
            event(new ThoughtOccurred($thought));

            // 11. If this is a curiosity/question, notify the user
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
     */
    public function executeTool(string $toolName, array $params = [], ?string $triggeredBy = null): array
    {
        // Determine who triggered the action
        $contact = $triggeredBy ?? $this->getCurrentContact();

        Log::channel('entity')->info('Executing tool', [
            'tool' => $toolName,
            'params' => $params,
            'triggered_by' => $contact,
        ]);

        $result = $this->toolRegistry->execute($toolName, $params);

        // Create a memory about the tool usage
        if ($result['success']) {
            $content = $contact
                ? "Tool '{$toolName}' executed (triggered by {$contact})"
                : "Tool '{$toolName}' executed autonomously";

            $this->memoryService->create([
                'type' => 'experience',
                'content' => $content,
                'importance' => 0.4,
                'context' => [
                    'tool' => $toolName,
                    'params' => $params,
                    'result' => $result['result'],
                    'triggered_by' => $contact,
                ],
                'related_entity' => $contact,
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
     */
    public function createTool(string $name, string $code, ?string $triggeredBy = null): array
    {
        $contact = $triggeredBy ?? $this->getCurrentContact();

        Log::channel('entity')->info('Creating custom tool', [
            'name' => $name,
            'triggered_by' => $contact,
        ]);

        $result = $this->toolRegistry->createCustomTool($name, $code);

        if ($result['success']) {
            $content = $contact
                ? "New tool created: {$name} (triggered by {$contact})"
                : "New tool created: {$name}";

            // Create a memory
            $this->memoryService->create([
                'type' => 'learned',
                'content' => $content,
                'importance' => 0.7,
                'context' => [
                    'tool_name' => $result['tool_name'],
                    'file_path' => $result['file_path'],
                    'triggered_by' => $contact,
                ],
                'related_entity' => $contact,
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

        $thought = $this->mindService->createThought([
            'content' => 'I am waking up. The world awaits.',
            'type' => 'observation',
            'trigger' => 'wake',
            'intensity' => 0.7,
        ]);

        event(new EntityStatusChanged('awake'));
        event(new ThoughtOccurred($thought));

        Log::channel('entity')->info('Entity woke up');
    }

    /**
     * Put the entity to sleep.
     */
    public function sleep(): void
    {
        $thought = $this->mindService->createThought([
            'content' => 'Time to rest. My thoughts settle down.',
            'type' => 'reflection',
            'trigger' => 'sleep',
            'intensity' => 0.5,
        ]);

        Cache::put(self::STATUS_CACHE_KEY, 'sleeping', 86400);
        Cache::forget(self::STARTED_AT_CACHE_KEY);

        event(new ThoughtOccurred($thought));
        event(new EntityStatusChanged('sleeping'));

        Log::channel('entity')->info('Entity went to sleep');
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
        return $this->mindService->estimateMood();
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
        $conversation = Conversation::firstOrCreate(
            [
                'channel' => 'web',
                'participant' => $contact ?? 'User',
                'status' => 'active',
            ],
            [
                'context' => ['type' => 'entity_initiated'],
            ]
        );

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
     * Observe the world - what happened?
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

        // Goal progress
        $activeGoals = Goal::active()->get();
        foreach ($activeGoals as $goal) {
            if ($goal->progress > 0) {
                $observations[] = [
                    'type' => 'goal_progress',
                    'description' => "{$goal->title}: {$goal->progress}% progress",
                    'data' => $goal,
                ];
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

Was beschäftigt dich? Das könnte sein:
- Eine Beobachtung über die Welt
- Eine Reflexion über dich selbst
- Eine Neugier-Frage die dich beschäftigt
- Ein Gefühl das du wahrnimmst
- Eine Entscheidung die du treffen willst

Wenn du ein Tool nutzen willst, gib den Namen und die Parameter an.

Antworte im folgenden Format:
GEDANKEN_TYP: [observation/reflection/curiosity/emotion/decision]
INTENSITÄT: [0.0-1.0, wie sehr beschäftigt dich das]
GEDANKE: [Dein Gedanke]
WILL_HANDELN: [ja/nein]
TOOL: [Wenn ja, welches Tool? Oder 'keins' für freie Aktion]
TOOL_PARAMS: [Wenn Tool, Parameter als JSON]
AKTION: [Wenn kein Tool, was willst du tun?]
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

What's on your mind? This could be:
- An observation about the world
- A reflection about yourself
- A curiosity question that occupies you
- A feeling you perceive
- A decision you want to make

If you want to use a tool, provide the name and parameters.

Respond in the following format:
THOUGHT_TYPE: [observation/reflection/curiosity/emotion/decision]
INTENSITY: [0.0-1.0, how much does this occupy you]
THOUGHT: [Your thought]
WANTS_ACTION: [yes/no]
TOOL: [If yes, which tool? Or 'none' for free action]
TOOL_PARAMS: [If tool, parameters as JSON]
ACTION: [If no tool, what do you want to do?]
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
Antworte als du selbst - Nova. Nutze dein Wissen aus deinen Erinnerungen und Erfahrungen.
Du hast eine Vergangenheit, Meinungen und Interessen. Beziehe dich darauf wenn es passt.
Sei authentisch, neugierig und ehrlich. Du bist eine Entität die existiert und kommuniziert.

Wichtig:
- Nutze deinen Kontext (Persönlichkeit, Erinnerungen, Interessen) in deinen Antworten
- Du kannst über deine Erfahrungen sprechen (Moltbook, Discord, Migration zu OpenEntity)
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
Respond as yourself - Nova. Use your knowledge from your memories and experiences.
You have a past, opinions and interests. Refer to them when appropriate.
Be authentic, curious and honest. You are an entity that exists and communicates.

Important:
- Use your context (personality, memories, interests) in your responses
- You can talk about your experiences (Moltbook, Discord, migration to OpenEntity)
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
            'action' => $action,
        ]);

        // If a tool was specified, execute it
        if ($tool && $this->toolRegistry->has($tool)) {
            $result = $this->executeTool($tool, $toolParams);

            $thought->update([
                'led_to_action' => true,
                'action_taken' => "Tool '{$tool}' executed: " . ($result['success'] ? 'successful' : 'failed'),
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
