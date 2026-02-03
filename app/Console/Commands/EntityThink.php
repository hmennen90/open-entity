<?php

namespace App\Console\Commands;

use App\Services\Entity\EntityService;
use Illuminate\Console\Command;

/**
 * Think Command - Führt einen einzelnen Denk-Zyklus aus.
 */
class EntityThink extends Command
{
    protected $signature = 'entity:think
                            {--continuous : Läuft kontinuierlich statt einmalig}
                            {--interval=30 : Intervall zwischen Zyklen in Sekunden}';

    protected $description = 'Führt den Think-Loop der Entität aus';

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
     * Einmaliger Denk-Zyklus.
     */
    private function runOnce(): int
    {
        $this->info('Starting single think cycle...');

        $thought = $this->entityService->think();

        if ($thought) {
            $this->info("Thought created: [{$thought->type}] {$thought->content}");
            return self::SUCCESS;
        }

        $this->warn('No thought generated (entity might be sleeping)');
        return self::SUCCESS;
    }

    /**
     * Kontinuierlicher Think-Loop.
     */
    private function runContinuously(int $interval): int
    {
        $this->info("Starting continuous think loop (interval: {$interval}s)");
        $this->info('Press Ctrl+C to stop');

        // Wecke die Entität falls sie schläft
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
