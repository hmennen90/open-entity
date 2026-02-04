<?php

namespace App\Console\Commands;

use App\Models\Memory;
use App\Services\Entity\SemanticMemoryService;
use Illuminate\Console\Command;

class EmbedBackfillCommand extends Command
{
    protected $signature = 'entity:embed-backfill
                            {--batch=50 : Number of memories to process per batch}
                            {--limit= : Maximum number of memories to process (default: all)}
                            {--dry-run : Show what would be processed without actually embedding}';

    protected $description = 'Generate embeddings for existing memories that do not have them';

    public function handle(SemanticMemoryService $semanticMemoryService): int
    {
        $batchSize = (int) $this->option('batch');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = $this->option('dry-run');

        // Get stats first
        $stats = $semanticMemoryService->getStats();

        $this->info('Memory Embedding Status:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Memories', $stats['total_memories']],
                ['Already Embedded', $stats['embedded']],
                ['Pending', $stats['pending']],
                ['Coverage', $stats['coverage_percent'] . '%'],
                ['Embedding Model', $stats['embedding_model']],
                ['Dimensions', $stats['embedding_dimensions']],
            ]
        );

        if ($stats['pending'] === 0) {
            $this->info('All memories already have embeddings.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $pending = Memory::whereNull('embedding')
                ->orWhereNull('embedded_at')
                ->orderByDesc('importance')
                ->limit($limit ?? $stats['pending'])
                ->get(['id', 'type', 'summary', 'importance', 'created_at']);

            $this->info("\nWould process {$pending->count()} memories:");
            foreach ($pending as $memory) {
                $summary = $memory->summary ?? '(no summary)';
                if (strlen($summary) > 60) {
                    $summary = substr($summary, 0, 57) . '...';
                }
                $this->line("  #{$memory->id} [{$memory->type}] {$summary}");
            }

            return self::SUCCESS;
        }

        $toProcess = $limit ?? $stats['pending'];
        $this->info("\nProcessing {$toProcess} memories in batches of {$batchSize}...");

        $processed = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($toProcess);
        $bar->start();

        Memory::whereNull('embedding')
            ->orWhereNull('embedded_at')
            ->orderByDesc('importance')
            ->orderByDesc('created_at')
            ->limit($toProcess)
            ->chunk($batchSize, function ($memories) use ($semanticMemoryService, &$processed, &$failed, $bar) {
                foreach ($memories as $memory) {
                    try {
                        $semanticMemoryService->generateEmbedding($memory);
                        $processed++;
                    } catch (\Exception $e) {
                        $failed++;
                        $this->newLine();
                        $this->warn("Failed to embed memory #{$memory->id}: {$e->getMessage()}");
                    }

                    $bar->advance();

                    // Small delay to avoid overwhelming the embedding service
                    usleep(100000); // 100ms
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info("Backfill complete: {$processed} embedded, {$failed} failed.");

        // Show updated stats
        $newStats = $semanticMemoryService->getStats();
        $this->info("New coverage: {$newStats['coverage_percent']}%");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
