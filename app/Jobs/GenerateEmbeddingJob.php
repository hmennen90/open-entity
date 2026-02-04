<?php

namespace App\Jobs;

use App\Models\Memory;
use App\Services\Entity\SemanticMemoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to generate embedding for a memory in the background.
 */
class GenerateEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Memory $memory
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(SemanticMemoryService $semanticMemoryService): void
    {
        // Skip if already has embedding
        if ($this->memory->embedded_at) {
            Log::channel('entity')->debug('Memory already has embedding, skipping', [
                'memory_id' => $this->memory->id,
            ]);
            return;
        }

        try {
            $semanticMemoryService->generateEmbedding($this->memory);

            Log::channel('entity')->info('Generated embedding for memory via job', [
                'memory_id' => $this->memory->id,
            ]);

        } catch (\Exception $e) {
            Log::channel('entity')->error('Failed to generate embedding in job', [
                'memory_id' => $this->memory->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('entity')->error('Embedding generation job failed permanently', [
            'memory_id' => $this->memory->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
