<?php

namespace App\Console\Commands;

use App\Models\Goal;
use Illuminate\Console\Command;

/**
 * Cleanup command for goals - fixes invalid progress values and auto-completes.
 */
class EntityGoalsCleanup extends Command
{
    protected $signature = 'entity:goals-cleanup
                            {--dry-run : Show what would be changed without making changes}';

    protected $description = 'Fix goals with invalid progress values (>100%) and auto-complete them';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN - No changes will be made');
        }

        // Find goals with progress > 100
        $overProgressGoals = Goal::where('progress', '>', 100)->get();

        if ($overProgressGoals->isEmpty()) {
            $this->info('No goals with progress > 100% found.');
        } else {
            $this->warn("Found {$overProgressGoals->count()} goals with progress > 100%:");

            foreach ($overProgressGoals as $goal) {
                $this->line("  - [{$goal->id}] {$goal->title}: {$goal->progress}% ({$goal->status})");

                if (!$dryRun) {
                    $goal->progress = 100;
                    $goal->save(); // Model boot will auto-complete
                    $this->info("    -> Fixed: progress=100%, status={$goal->status}");
                }
            }
        }

        // Find active goals with exactly 100% that aren't completed
        $notCompletedGoals = Goal::where('progress', '>=', 100)
            ->where('status', 'active')
            ->get();

        if ($notCompletedGoals->isEmpty()) {
            $this->info('No active goals at 100% progress found.');
        } else {
            $this->warn("Found {$notCompletedGoals->count()} active goals at 100% progress:");

            foreach ($notCompletedGoals as $goal) {
                $this->line("  - [{$goal->id}] {$goal->title}: {$goal->progress}%");

                if (!$dryRun) {
                    $goal->status = 'completed';
                    $goal->completed_at = now();
                    $goal->save();
                    $this->info("    -> Marked as completed");
                }
            }
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run complete. Run without --dry-run to apply changes.' : 'Cleanup complete!');

        return self::SUCCESS;
    }
}
