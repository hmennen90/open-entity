<?php

namespace App\Console\Commands;

use App\Services\Entity\EntityService;
use App\Services\LLM\LLMService;
use Illuminate\Console\Command;

/**
 * Status Command - Zeigt den aktuellen Status der Entität.
 */
class EntityStatus extends Command
{
    protected $signature = 'entity:status';

    protected $description = 'Zeigt den aktuellen Status der Entität';

    public function __construct(
        private EntityService $entityService,
        private LLMService $llmService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = config('entity.name');
        $status = $this->entityService->getStatus();
        $uptime = $this->entityService->getUptime();
        $lastThought = $this->entityService->getLastThoughtAt();
        $mood = $this->entityService->getCurrentMood();
        $goals = $this->entityService->getActiveGoals();
        $thoughts = $this->entityService->getRecentThoughts(3);

        $this->newLine();
        $this->info("=== {$name} ===");
        $this->newLine();

        // Status
        $statusColor = $status === 'awake' ? 'green' : 'yellow';
        $this->line("Status: <fg={$statusColor}>{$status}</>");

        // Uptime
        if ($uptime) {
            $hours = floor($uptime / 3600);
            $minutes = floor(($uptime % 3600) / 60);
            $this->line("Uptime: {$hours}h {$minutes}m");
        }

        // Last Thought
        if ($lastThought) {
            $this->line("Last thought: {$lastThought}");
        }

        // LLM Status
        $llmAvailable = $this->llmService->isAvailable();
        $llmModel = $this->llmService->getModelName();
        $llmColor = $llmAvailable ? 'green' : 'red';
        $this->line("LLM: <fg={$llmColor}>{$llmModel}</> " . ($llmAvailable ? '(available)' : '(unavailable)'));

        $this->newLine();

        // Mood
        $this->info("--- Current Mood ---");
        $this->line("State: {$mood['state']}");
        $this->line("Energy: " . round($mood['energy'] * 100) . "%");

        $this->newLine();

        // Goals
        $this->info("--- Active Goals ---");
        if ($goals->isEmpty()) {
            $this->line("No active goals");
        } else {
            foreach ($goals->take(3) as $goal) {
                $progress = round($goal->progress * 100);
                $this->line("- {$goal->title} ({$progress}%)");
            }
        }

        $this->newLine();

        // Recent Thoughts
        $this->info("--- Recent Thoughts ---");
        if ($thoughts->isEmpty()) {
            $this->line("No thoughts yet");
        } else {
            foreach ($thoughts as $thought) {
                $time = $thought->created_at->diffForHumans();
                $this->line("- [{$thought->type}] {$thought->content}");
                $this->comment("  {$time}");
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
