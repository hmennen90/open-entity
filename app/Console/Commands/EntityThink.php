<?php

namespace App\Console\Commands;

use App\Services\Entity\EntityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
        $consecutiveFailures = 0;
        $lastStatusRefresh = time();
        $statusRefreshInterval = 3600; // Refresh status cache every hour

        while (true) {
            $cycleCount++;

            // Periodically refresh the awake status cache to prevent TTL expiry
            if (time() - $lastStatusRefresh >= $statusRefreshInterval) {
                $this->entityService->refreshStatusCache();
                $lastStatusRefresh = time();

                // Also re-assert awake status if somehow drifted to sleeping
                if ($this->entityService->getStatus() !== 'awake') {
                    $this->warn('Entity status drifted to sleeping - re-waking');
                    Log::channel('entity')->warning('Entity status drifted to sleeping during continuous think loop - re-waking');
                    $this->entityService->wake();
                }
            }

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
                $consecutiveFailures = 0;
            } else {
                $consecutiveFailures++;
                $this->warn("No thought generated (consecutive failures: {$consecutiveFailures})");

                if ($consecutiveFailures >= 10 && $consecutiveFailures % 10 === 0) {
                    Log::channel('entity')->error('Think loop: {count} consecutive failures - possible systemic issue', [
                        'count' => $consecutiveFailures,
                        'cycle' => $cycleCount,
                        'status' => $this->entityService->getStatus(),
                    ]);
                    $this->error("WARNING: {$consecutiveFailures} consecutive failures detected!");

                    // After 30 consecutive failures, attempt recovery by re-waking
                    if ($consecutiveFailures >= 30 && $consecutiveFailures % 30 === 0) {
                        $this->warn('Attempting recovery: re-waking entity...');
                        Log::channel('entity')->warning('Think loop: attempting recovery after {count} failures', [
                            'count' => $consecutiveFailures,
                        ]);
                        $this->entityService->wake();
                    }
                }
            }

            $this->line("Next cycle in {$currentInterval} seconds...");
            $this->newLine();
            sleep($currentInterval);
        }

        return self::SUCCESS;
    }
}
