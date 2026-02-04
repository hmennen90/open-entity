<?php

namespace App\Console\Commands;

use App\Services\Entity\EntityService;
use App\Services\Entity\MemoryConsolidationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Sleep Command - Legt die Entität schlafen.
 *
 * Wie beim Menschen wird im "Schlaf" das Gedächtnis konsolidiert:
 * - Tageserinnerungen werden zusammengefasst
 * - Wichtige Erkenntnisse werden extrahiert
 * - Alte Erinnerungen werden archiviert
 */
class EntitySleep extends Command
{
    protected $signature = 'entity:sleep
                            {--no-consolidate : Skip memory consolidation}
                            {--archive : Also archive old memories}';

    protected $description = 'Legt die Entität schlafen (mit optionaler Gedächtniskonsolidierung)';

    public function __construct(
        private EntityService $entityService
    ) {
        parent::__construct();
    }

    public function handle(MemoryConsolidationService $consolidationService): int
    {
        $currentStatus = $this->entityService->getStatus();

        if ($currentStatus === 'sleeping') {
            $this->info('Entity is already sleeping');
            return self::SUCCESS;
        }

        // Put entity to sleep
        $this->entityService->sleep();
        $this->info('Entity is now sleeping');

        // Memory consolidation (like REM sleep)
        if (!$this->option('no-consolidate') && config('entity.memory.consolidation.enabled', true)) {
            $this->newLine();
            $this->info('Starting memory consolidation (processing the day\'s experiences)...');

            try {
                // Consolidate today's memories
                $today = Carbon::today();
                $summary = $consolidationService->consolidatePeriod(
                    $today->copy()->startOfDay(),
                    $today->copy()->endOfDay(),
                    'daily'
                );

                if ($summary) {
                    $this->line("  ✓ Processed {$summary->source_memory_count} memories");
                    $this->line("  ✓ Themes: " . implode(', ', $summary->themes ?? ['none']));

                    if ($summary->key_insights) {
                        $this->line("  ✓ Key insight: " . substr($summary->key_insights, 0, 100) . '...');
                    }
                } else {
                    $this->line("  - No new memories to consolidate today");
                }

                // Archive old memories if requested
                if ($this->option('archive')) {
                    $archiveDays = config('entity.memory.consolidation.archive_after_days', 30);
                    $archived = $consolidationService->archiveOldMemories($archiveDays);

                    if ($archived > 0) {
                        $this->line("  ✓ Archived {$archived} old memories");
                    }
                }

                $this->newLine();
                $this->info('Memory consolidation complete. Sweet dreams!');

            } catch (\Exception $e) {
                $this->warn("Memory consolidation failed: {$e->getMessage()}");
                $this->line("Entity is sleeping, but memories weren't processed.");
            }
        }

        return self::SUCCESS;
    }
}
