<?php

namespace App\Console\Commands;

use App\Services\Entity\EntityService;
use Illuminate\Console\Command;

/**
 * Think Command - Executes a single think cycle.
 */
class EntityThink extends Command
{
    protected $signature = 'entity:think
                            {--continuous : Run continuously instead of once}
                            {--interval=30 : Interval between cycles in seconds}';

    protected $description = 'Execute the think loop of the entity';

    public function __construct(
        private EntityService $entityService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $continuous = $this->option('continuous');
        $interval = (int) $this->option('interval');

        if ($continuous) {
            return $this->runContinuously($interval);
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
     * Continuous think loop.
     */
    private function runContinuously(int $interval): int
    {
        $this->info("Starting continuous think loop (interval: {$interval}s)");
        $this->info('Press Ctrl+C to stop');

        // Wake the entity if sleeping
        if ($this->entityService->getStatus() !== 'awake') {
            $this->entityService->wake();
            $this->info('Entity woke up');
        }

        $cycleCount = 0;

        while (true) {
            $cycleCount++;
            $this->line("--- Think Cycle #{$cycleCount} ---");

            $thought = $this->entityService->think();

            if ($thought) {
                $this->info("[{$thought->type}] {$thought->content}");

                if ($thought->led_to_action) {
                    $this->comment("Action: {$thought->action_taken}");
                }
            } else {
                $this->warn('No thought generated');
            }

            $this->line("Next cycle in {$interval} seconds...\n");
            sleep($interval);
        }

        return self::SUCCESS;
    }
}
