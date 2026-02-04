<?php

namespace App\Console\Commands;

use App\Services\Entity\MemoryConsolidationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EntityConsolidateCommand extends Command
{
    protected $signature = 'entity:consolidate
                            {--date= : Specific date to consolidate (Y-m-d format)}
                            {--period=daily : Period type (daily, weekly, monthly)}
                            {--archive : Also archive old memories}
                            {--stats : Show consolidation statistics}';

    protected $description = 'Consolidate memories (like sleep for the brain)';

    public function handle(MemoryConsolidationService $consolidationService): int
    {
        // If stats requested, show them and exit
        if ($this->option('stats')) {
            return $this->showStats($consolidationService);
        }

        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $period = $this->option('period');

        $this->info("Starting memory consolidation...");
        $this->newLine();

        // Determine period bounds based on type
        [$start, $end] = match ($period) {
            'weekly' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            'monthly' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            default => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
        };

        $this->line("Period: {$start->format('Y-m-d')} to {$end->format('Y-m-d')} ({$period})");

        try {
            $summary = $consolidationService->consolidatePeriod($start, $end, $period);

            if ($summary) {
                $this->info("Consolidation complete!");
                $this->newLine();

                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Summary ID', $summary->id],
                        ['Period', $summary->period_description],
                        ['Memories Processed', $summary->source_memory_count],
                        ['Themes', implode(', ', $summary->themes ?? [])],
                        ['Avg. Emotional Valence', number_format($summary->average_emotional_valence, 2)],
                        ['Entities Mentioned', implode(', ', $summary->entities_mentioned ?? [])],
                    ]
                );

                $this->newLine();
                $this->line("Summary:");
                $this->line(str_repeat('-', 60));
                $this->line($summary->summary);

                if ($summary->key_insights) {
                    $this->newLine();
                    $this->line("Key Insights:");
                    $this->line(str_repeat('-', 60));
                    $this->line($summary->key_insights);
                }
            } else {
                $this->warn("No memories to consolidate for this period.");
            }

            // Archive old memories if requested
            if ($this->option('archive')) {
                $this->newLine();
                $archiveDays = config('entity.memory.consolidation.archive_after_days', 30);
                $this->line("Archiving memories older than {$archiveDays} days...");

                $archived = $consolidationService->archiveOldMemories($archiveDays);
                $this->info("Archived {$archived} old memories.");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Consolidation failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function showStats(MemoryConsolidationService $consolidationService): int
    {
        $stats = $consolidationService->getStats();

        $this->info("Memory Consolidation Statistics");
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Memories', $stats['total_memories']],
                ['Consolidated', $stats['consolidated']],
                ['Pending', $stats['pending']],
                ['Daily Summaries', $stats['summaries']['daily']],
                ['Weekly Summaries', $stats['summaries']['weekly']],
                ['Monthly Summaries', $stats['summaries']['monthly']],
                ['Last Consolidation', $stats['last_consolidation'] ?? 'Never'],
            ]
        );

        // Calculate consolidation rate
        if ($stats['total_memories'] > 0) {
            $rate = ($stats['consolidated'] / $stats['total_memories']) * 100;
            $this->newLine();
            $this->line(sprintf("Consolidation Rate: %.1f%%", $rate));
        }

        return self::SUCCESS;
    }
}
