<?php

namespace App\Console\Commands;

use App\Services\Entity\EntityService;
use Illuminate\Console\Command;

/**
 * Think Command - Executes a single think cycle or continuous thinking.
 */
class EntityThink extends Command
{
    protected $signature = 'entity:think
                            {--continuous : Run continuously instead of once}
                            {--adaptive : Use dynamic interval based on activity (faster when idle)}
                            {--interval= : Fixed interval between cycles in seconds (overrides adaptive)}';

    protected $description = 'Execute the think loop of the entity';

    public function __construct(
        private EntityService $entityService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $continuous = $this->option('continuous');

        if ($continuous) {
            return $this->runContinuously();
        }

        return $this->runOnce();
    }

    /**
     * Single think cycle.
     */
    private function runOnce(): int
    {
        $status = $this->entityService->getStatus();
        $isDreaming = $status !== 'awake';

        if ($isDreaming) {
            $this->info('Entity is sleeping - starting dream cycle...');
        } else {
            $this->info('Starting single think cycle...');
        }

        $thought = $this->entityService->think();

        if ($thought) {
            $prefix = $isDreaming ? 'Dream' : 'Thought';
            $this->info("{$prefix} created: [{$thought->type}] {$thought->content}");
            return self::SUCCESS;
        }

        $this->warn('No thought generated');
        return self::SUCCESS;
    }

    /**
     * Continuous think loop with optional adaptive intervals.
     */
    private function runContinuously(): int
    {
        $useAdaptive = $this->option('adaptive');
        $fixedInterval = $this->option('interval');

        // Determine interval mode
        if ($fixedInterval !== null) {
            $interval = (int) $fixedInterval;
            $this->info("Starting continuous think loop (fixed interval: {$interval}s)");
        } elseif ($useAdaptive) {
            $this->info('Starting continuous think loop (adaptive interval)');
            $this->info('  - Idle: ' . config('entity.think.idle_interval', 5) . 's');
            $this->info('  - Active: ' . config('entity.think.active_interval', 60) . 's');
            $this->info('  - Activity timeout: ' . config('entity.think.activity_timeout', 120) . 's');
        } else {
            // Default to legacy interval
            $interval = config('entity.think_interval', 30);
            $this->info("Starting continuous think loop (interval: {$interval}s)");
        }

        $this->info('Press Ctrl+C to stop');
        $this->newLine();

        // Wake the entity if sleeping
        if ($this->entityService->getStatus() !== 'awake') {
            $this->entityService->wake();
            $this->info('Entity woke up');
        }

        $cycleCount = 0;

        while (true) {
            $cycleCount++;

            // Get current interval (dynamic if adaptive mode)
            if ($fixedInterval !== null) {
                $currentInterval = (int) $fixedInterval;
            } elseif ($useAdaptive) {
                $currentInterval = $this->entityService->getThinkInterval();
                $isIdle = $this->entityService->isIdle();
                $mode = $isIdle ? 'idle' : 'active';
            } else {
                $currentInterval = config('entity.think_interval', 30);
            }

            // Show cycle header with mode info
            if ($useAdaptive && $fixedInterval === null) {
                $this->line("--- Think Cycle #{$cycleCount} [{$mode}, {$currentInterval}s] ---");
            } else {
                $this->line("--- Think Cycle #{$cycleCount} ---");
            }

            $thought = $this->entityService->think();

            if ($thought) {
                $this->info("[{$thought->type}] {$thought->content}");

                if ($thought->led_to_action) {
                    $this->comment("Action: {$thought->action_taken}");
                }
            } else {
                $this->warn('No thought generated');
            }

            $this->line("Next cycle in {$currentInterval} seconds...");
            $this->newLine();
            sleep($currentInterval);
        }

        return self::SUCCESS;
    }
}
