<?php

namespace App\Services\Tools\BuiltIn;

use App\Models\Goal;
use App\Services\Tools\Contracts\ToolInterface;

/**
 * Tool for managing goals with intelligent duplicate detection.
 *
 * Allows the entity to create, update, and manage goals autonomously
 * while preventing duplicate goals through similarity detection.
 */
class GoalTool implements ToolInterface
{
    /**
     * Get the similarity threshold from config.
     */
    private function getSimilarityThreshold(): int
    {
        return (int) config('entity.tools.goal.similarity_threshold', 60);
    }

    public function name(): string
    {
        return 'goal';
    }

    public function description(): string
    {
        return 'Manage goals: create new goals, update progress, add learnings, find similar goals, and complete/abandon goals. ' .
            'Automatically detects similar existing goals to prevent duplicates. ' .
            'Track learnings to remember what was learned from each goal.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['create', 'update_progress', 'add_learning', 'find_similar', 'complete', 'abandon', 'list', 'get'],
                    'description' => 'The action to perform',
                ],
                'goal_id' => [
                    'type' => 'integer',
                    'description' => 'ID of the goal (for update_progress, complete, abandon, get)',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Title of the goal (for create, find_similar)',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Description of the goal (for create)',
                ],
                'motivation' => [
                    'type' => 'string',
                    'description' => 'Why this goal matters (for create)',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['curiosity', 'social', 'learning', 'creative', 'self-improvement'],
                    'description' => 'Type of goal (for create)',
                ],
                'priority' => [
                    'type' => 'number',
                    'description' => 'Priority from 0.0 to 1.0 (for create)',
                ],
                'progress' => [
                    'type' => 'integer',
                    'description' => 'New progress percentage 0-100 (for update_progress)',
                ],
                'progress_note' => [
                    'type' => 'string',
                    'description' => 'Note explaining the progress update (for update_progress)',
                ],
                'learning' => [
                    'type' => 'string',
                    'description' => 'What was learned from working on this goal (for add_learning, update_progress, complete)',
                ],
                'reason' => [
                    'type' => 'string',
                    'description' => 'Reason for abandoning (for abandon)',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'paused', 'completed', 'abandoned'],
                    'description' => 'Filter by status (for list)',
                ],
                'force_create' => [
                    'type' => 'boolean',
                    'description' => 'Create goal even if similar ones exist (default: false)',
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function validate(array $params): array
    {
        $errors = [];

        if (! isset($params['action'])) {
            $errors[] = 'action is required';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function execute(array $params): array
    {
        $action = $params['action'] ?? null;

        return match ($action) {
            'create' => $this->createGoal($params),
            'update_progress' => $this->updateProgress($params),
            'add_learning' => $this->addLearning($params),
            'find_similar' => $this->findSimilar($params),
            'complete' => $this->completeGoal($params),
            'abandon' => $this->abandonGoal($params),
            'list' => $this->listGoals($params),
            'get' => $this->getGoal($params),
            default => [
                'success' => false,
                'error' => 'Invalid action. Use: create, update_progress, add_learning, find_similar, complete, abandon, list, get',
            ],
        };
    }

    /**
     * Create a new goal with duplicate detection.
     */
    private function createGoal(array $params): array
    {
        $title = $params['title'] ?? null;
        if (! $title) {
            return ['success' => false, 'error' => 'Title is required'];
        }

        $forceCreate = $params['force_create'] ?? false;

        // Check for similar goals unless force_create is set
        if (! $forceCreate) {
            $similarGoals = $this->findSimilarGoals($title);
            if (! empty($similarGoals)) {
                return [
                    'success' => false,
                    'reason' => 'similar_goals_exist',
                    'message' => 'Similar goals already exist. Consider updating them or use force_create=true.',
                    'similar_goals' => $similarGoals,
                    'suggestions' => [
                        'Update an existing goal with update_progress action',
                        'Set force_create=true to create anyway',
                        'Consider if this is really a new goal or part of an existing one',
                    ],
                ];
            }
        }

        $goal = Goal::create([
            'title' => $title,
            'description' => $params['description'] ?? '',
            'motivation' => $params['motivation'] ?? '',
            'type' => $params['type'] ?? 'learning',
            'priority' => min(1.0, max(0.0, $params['priority'] ?? 0.5)),
            'status' => 'active',
            'progress' => 0,
            'progress_notes' => [
                [
                    'date' => now()->toIso8601String(),
                    'note' => 'Goal created',
                ],
            ],
            'origin' => 'self',
        ]);

        return [
            'success' => true,
            'goal' => $this->formatGoal($goal),
            'message' => "Goal '{$title}' created successfully",
        ];
    }

    /**
     * Update progress on an existing goal.
     */
    private function updateProgress(array $params): array
    {
        $goalId = $params['goal_id'] ?? null;
        if (! $goalId) {
            return ['success' => false, 'error' => 'goal_id is required'];
        }

        $goal = Goal::find($goalId);
        if (! $goal) {
            return ['success' => false, 'error' => "Goal with ID {$goalId} not found"];
        }

        $newProgress = $params['progress'] ?? null;
        $progressNote = $params['progress_note'] ?? 'Progress updated';

        if ($newProgress !== null) {
            $newProgress = min(100, max(0, (int) $newProgress));
            $goal->progress = $newProgress;
        }

        // Add progress note
        $notes = $goal->progress_notes ?? [];
        $notes[] = [
            'date' => now()->toIso8601String(),
            'note' => $progressNote,
            'progress' => $goal->progress,
        ];

        // Auto-complete if progress reaches 100
        if ($goal->progress >= 100 && $goal->status === 'active') {
            $goal->status = 'completed';
            $goal->completed_at = now();
            $notes[] = [
                'date' => now()->toIso8601String(),
                'note' => 'Goal automatically completed (100% progress)',
            ];
        }

        $goal->progress_notes = $this->capProgressNotes($notes);

        // Add learning if provided
        if (!empty($params['learning'])) {
            $this->addLearningToGoal($goal, $params['learning']);
        }

        $goal->save();

        return [
            'success' => true,
            'goal' => $this->formatGoal($goal),
            'message' => $goal->status === 'completed'
                ? "Goal completed! Progress reached 100%"
                : "Progress updated to {$goal->progress}%",
        ];
    }

    /**
     * Add a learning to a goal.
     */
    private function addLearning(array $params): array
    {
        $goalId = $params['goal_id'] ?? null;
        if (!$goalId) {
            return ['success' => false, 'error' => 'goal_id is required'];
        }

        $learning = $params['learning'] ?? null;
        if (!$learning) {
            return ['success' => false, 'error' => 'learning is required'];
        }

        $goal = Goal::find($goalId);
        if (!$goal) {
            return ['success' => false, 'error' => "Goal with ID {$goalId} not found"];
        }

        $this->addLearningToGoal($goal, $learning);
        $goal->save();

        return [
            'success' => true,
            'goal' => $this->formatGoal($goal),
            'message' => "Learning added to goal '{$goal->title}'",
        ];
    }

    /**
     * Helper to add a learning to a goal.
     */
    private function addLearningToGoal(Goal $goal, string $learning): void
    {
        $learnings = $goal->learnings ?? [];
        $learnings[] = [
            'timestamp' => now()->toIso8601String(),
            'content' => $learning,
            'progress_at_time' => $goal->progress,
        ];

        // Cap learnings to prevent unbounded growth
        if (count($learnings) > 100) {
            $learnings = array_slice($learnings, -100);
        }

        $goal->learnings = $learnings;
    }

    /**
     * Cap progress notes array to prevent unbounded growth.
     */
    private function capProgressNotes(array $notes, int $maxNotes = 200): array
    {
        if (count($notes) > $maxNotes) {
            return array_slice($notes, -$maxNotes);
        }
        return $notes;
    }

    /**
     * Find goals similar to a given title.
     */
    private function findSimilar(array $params): array
    {
        $title = $params['title'] ?? null;
        if (! $title) {
            return ['success' => false, 'error' => 'Title is required'];
        }

        $similarGoals = $this->findSimilarGoals($title);

        return [
            'success' => true,
            'query' => $title,
            'similar_goals' => $similarGoals,
            'count' => count($similarGoals),
        ];
    }

    /**
     * Mark a goal as completed.
     */
    private function completeGoal(array $params): array
    {
        $goalId = $params['goal_id'] ?? null;
        if (! $goalId) {
            return ['success' => false, 'error' => 'goal_id is required'];
        }

        $goal = Goal::find($goalId);
        if (! $goal) {
            return ['success' => false, 'error' => "Goal with ID {$goalId} not found"];
        }

        $goal->status = 'completed';
        $goal->progress = 100;
        $goal->completed_at = now();

        $notes = $goal->progress_notes ?? [];
        $notes[] = [
            'date' => now()->toIso8601String(),
            'note' => $params['progress_note'] ?? 'Goal completed',
        ];
        $goal->progress_notes = $this->capProgressNotes($notes);

        // Add final learning if provided
        if (!empty($params['learning'])) {
            $this->addLearningToGoal($goal, $params['learning']);
        }

        $goal->save();

        return [
            'success' => true,
            'goal' => $this->formatGoal($goal),
            'message' => "Goal '{$goal->title}' marked as completed",
        ];
    }

    /**
     * Abandon a goal with reason.
     */
    private function abandonGoal(array $params): array
    {
        $goalId = $params['goal_id'] ?? null;
        if (! $goalId) {
            return ['success' => false, 'error' => 'goal_id is required'];
        }

        $goal = Goal::find($goalId);
        if (! $goal) {
            return ['success' => false, 'error' => "Goal with ID {$goalId} not found"];
        }

        $reason = $params['reason'] ?? 'No longer relevant';

        $goal->status = 'abandoned';
        $goal->abandoned_reason = $reason;

        $notes = $goal->progress_notes ?? [];
        $notes[] = [
            'date' => now()->toIso8601String(),
            'note' => "Goal abandoned: {$reason}",
        ];
        $goal->progress_notes = $this->capProgressNotes($notes);

        // Add learning even when abandoning (learned what didn't work)
        if (!empty($params['learning'])) {
            $this->addLearningToGoal($goal, $params['learning']);
        }

        $goal->save();

        return [
            'success' => true,
            'goal' => $this->formatGoal($goal),
            'message' => "Goal '{$goal->title}' abandoned",
        ];
    }

    /**
     * List goals with optional filters.
     */
    private function listGoals(array $params): array
    {
        $query = Goal::query();

        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (isset($params['type'])) {
            $query->where('type', $params['type']);
        }

        $goals = $query->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($goal) => $this->formatGoal($goal))
            ->toArray();

        return [
            'success' => true,
            'goals' => $goals,
            'count' => count($goals),
        ];
    }

    /**
     * Get a specific goal by ID.
     */
    private function getGoal(array $params): array
    {
        $goalId = $params['goal_id'] ?? null;
        if (! $goalId) {
            return ['success' => false, 'error' => 'goal_id is required'];
        }

        $goal = Goal::find($goalId);
        if (! $goal) {
            return ['success' => false, 'error' => "Goal with ID {$goalId} not found"];
        }

        return [
            'success' => true,
            'goal' => $this->formatGoal($goal),
        ];
    }

    /**
     * Find similar goals based on title similarity.
     */
    private function findSimilarGoals(string $title): array
    {
        $activeGoals = Goal::whereIn('status', ['active', 'paused'])->get();
        $similar = [];

        foreach ($activeGoals as $goal) {
            $similarity = $this->calculateSimilarity($title, $goal->title);

            if ($similarity >= $this->getSimilarityThreshold()) {
                $similar[] = [
                    'id' => $goal->id,
                    'title' => $goal->title,
                    'status' => $goal->status,
                    'progress' => $goal->progress,
                    'similarity' => $similarity,
                ];
            }
        }

        // Sort by similarity descending
        usort($similar, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        return $similar;
    }

    /**
     * Calculate similarity between two strings (0-100).
     */
    private function calculateSimilarity(string $str1, string $str2): int
    {
        // Normalize strings
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));

        // Exact match
        if ($str1 === $str2) {
            return 100;
        }

        // Use similar_text for percentage
        similar_text($str1, $str2, $percent);

        // Also check for keyword overlap
        $words1 = array_filter(explode(' ', preg_replace('/[^a-z0-9\s]/', '', $str1)));
        $words2 = array_filter(explode(' ', preg_replace('/[^a-z0-9\s]/', '', $str2)));

        if (! empty($words1) && ! empty($words2)) {
            $intersection = array_intersect($words1, $words2);
            $union = array_unique(array_merge($words1, $words2));
            $jaccardSimilarity = (count($intersection) / count($union)) * 100;

            // Combine both metrics
            $percent = ($percent + $jaccardSimilarity) / 2;
        }

        return (int) round($percent);
    }

    /**
     * Format a goal for output.
     */
    private function formatGoal(Goal $goal): array
    {
        return [
            'id' => $goal->id,
            'title' => $goal->title,
            'description' => $goal->description,
            'motivation' => $goal->motivation,
            'type' => $goal->type,
            'priority' => $goal->priority,
            'status' => $goal->status,
            'progress' => $goal->progress,
            'progress_notes' => $goal->progress_notes,
            'learnings' => $goal->learnings,
            'origin' => $goal->origin,
            'created_at' => $goal->created_at?->toIso8601String(),
            'completed_at' => $goal->completed_at?->toIso8601String(),
            'abandoned_reason' => $goal->abandoned_reason,
        ];
    }
}
