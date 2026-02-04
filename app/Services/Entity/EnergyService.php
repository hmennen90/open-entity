<?php

namespace App\Services\Entity;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * EnergyService - Human-like energy management for the entity.
 *
 * Energy is influenced by:
 * - Time awake (fatigue over time)
 * - Actions/Tool usage (costs energy)
 * - Goal achievements (boosts energy)
 * - Sleep duration (recovery)
 * - Conversations (social energy exchange)
 * - Thought intensity (mental effort)
 */
class EnergyService
{
    private const CACHE_PREFIX = 'entity:energy:';
    private const ENERGY_KEY = self::CACHE_PREFIX . 'current';
    private const LAST_UPDATE_KEY = self::CACHE_PREFIX . 'last_update';
    private const SLEEP_START_KEY = self::CACHE_PREFIX . 'sleep_start';
    private const WAKE_TIME_KEY = self::CACHE_PREFIX . 'wake_time';
    private const ENERGY_LOG_KEY = self::CACHE_PREFIX . 'log';

    // Energy bounds
    private const MAX_ENERGY = 1.0;
    private const MIN_ENERGY = 0.0;
    private const DEFAULT_ENERGY = 0.7;

    // Fatigue rate: energy lost per hour while awake
    private const FATIGUE_RATE_PER_HOUR = 0.04; // ~24 hours to full depletion

    // Energy costs
    private const COST_TOOL_EXECUTION = 0.02;
    private const COST_THOUGHT_BASE = 0.005;
    private const COST_THOUGHT_INTENSITY_MULTIPLIER = 0.01; // Additional cost based on intensity
    private const COST_CONVERSATION_MESSAGE = 0.01;

    // Energy gains
    private const GAIN_GOAL_PROGRESS = 0.03; // Per 10% progress
    private const GAIN_GOAL_COMPLETED = 0.15;
    private const GAIN_POSITIVE_INTERACTION = 0.02;
    private const GAIN_MEMORY_RECALL = 0.005; // Nostalgic energy boost

    // Sleep recovery
    private const SLEEP_RECOVERY_PER_HOUR = 0.15;
    private const MIN_SLEEP_FOR_FULL_RECOVERY = 6; // hours
    private const WAKE_ENERGY_MINIMUM = 0.5;

    /**
     * Get current energy level (0.0 - 1.0).
     */
    public function getEnergy(): float
    {
        $this->applyFatigue();
        return (float) Cache::get(self::ENERGY_KEY, self::DEFAULT_ENERGY);
    }

    /**
     * Get energy as percentage (0 - 100).
     */
    public function getEnergyPercent(): int
    {
        return (int) round($this->getEnergy() * 100);
    }

    /**
     * Get energy state description.
     */
    public function getEnergyState(): array
    {
        $energy = $this->getEnergy();
        $hoursAwake = $this->getHoursAwake();

        $state = match (true) {
            $energy >= 0.9 => 'energized',
            $energy >= 0.7 => 'alert',
            $energy >= 0.5 => 'normal',
            $energy >= 0.3 => 'tired',
            $energy >= 0.15 => 'exhausted',
            default => 'depleted',
        };

        $needsSleep = $energy < 0.2 || $hoursAwake > 16;

        return [
            'level' => $energy,
            'percent' => $this->getEnergyPercent(),
            'state' => $state,
            'hours_awake' => round($hoursAwake, 1),
            'needs_sleep' => $needsSleep,
            'description' => $this->getStateDescription($state),
        ];
    }

    /**
     * Get human-readable state description.
     */
    private function getStateDescription(string $state): string
    {
        return match ($state) {
            'energized' => 'Feeling great! Ready for anything.',
            'alert' => 'Wide awake and focused.',
            'normal' => 'Doing fine, steady energy.',
            'tired' => 'Getting tired, could use a break.',
            'exhausted' => 'Very tired, should rest soon.',
            'depleted' => 'Completely drained, need sleep urgently.',
            default => 'Unknown state.',
        };
    }

    /**
     * Apply fatigue based on time passed since last update.
     */
    private function applyFatigue(): void
    {
        $lastUpdate = Cache::get(self::LAST_UPDATE_KEY);

        if (!$lastUpdate) {
            Cache::put(self::LAST_UPDATE_KEY, now()->toIso8601String(), 86400 * 7);
            return;
        }

        $lastUpdateTime = Carbon::parse($lastUpdate);
        $hoursPassed = $lastUpdateTime->diffInMinutes(now()) / 60;

        if ($hoursPassed < 0.05) { // Less than 3 minutes
            return;
        }

        $fatigueCost = $hoursPassed * self::FATIGUE_RATE_PER_HOUR;
        $this->modifyEnergy(-$fatigueCost, 'fatigue', false);

        Cache::put(self::LAST_UPDATE_KEY, now()->toIso8601String(), 86400 * 7);
    }

    /**
     * Modify energy by a delta amount.
     */
    public function modifyEnergy(float $delta, string $reason = '', bool $log = true): float
    {
        $current = (float) Cache::get(self::ENERGY_KEY, self::DEFAULT_ENERGY);
        $new = max(self::MIN_ENERGY, min(self::MAX_ENERGY, $current + $delta));

        Cache::put(self::ENERGY_KEY, $new, 86400 * 7);

        if ($log && abs($delta) >= 0.01) {
            $this->logEnergyChange($delta, $reason, $current, $new);
        }

        return $new;
    }

    /**
     * Set energy to a specific value.
     */
    public function setEnergy(float $value, string $reason = ''): void
    {
        $old = $this->getEnergy();
        $new = max(self::MIN_ENERGY, min(self::MAX_ENERGY, $value));

        Cache::put(self::ENERGY_KEY, $new, 86400 * 7);
        Cache::put(self::LAST_UPDATE_KEY, now()->toIso8601String(), 86400 * 7);

        $this->logEnergyChange($new - $old, $reason, $old, $new);
    }

    /**
     * Record energy change in log.
     */
    private function logEnergyChange(float $delta, string $reason, float $old, float $new): void
    {
        $log = Cache::get(self::ENERGY_LOG_KEY, []);

        $log[] = [
            'time' => now()->toIso8601String(),
            'delta' => round($delta, 4),
            'reason' => $reason,
            'from' => round($old, 4),
            'to' => round($new, 4),
        ];

        // Keep only last 100 entries
        if (count($log) > 100) {
            $log = array_slice($log, -100);
        }

        Cache::put(self::ENERGY_LOG_KEY, $log, 86400 * 7);

        Log::channel('entity')->debug('Energy changed', [
            'delta' => round($delta, 4),
            'reason' => $reason,
            'new_level' => round($new, 4),
        ]);
    }

    /**
     * Get recent energy changes.
     */
    public function getEnergyLog(int $limit = 20): array
    {
        $log = Cache::get(self::ENERGY_LOG_KEY, []);
        return array_slice($log, -$limit);
    }

    // ========== Energy Cost Methods ==========

    /**
     * Cost energy for executing a tool.
     */
    public function costToolExecution(string $toolName): void
    {
        $this->modifyEnergy(-self::COST_TOOL_EXECUTION, "tool:{$toolName}");
    }

    /**
     * Cost energy for a thought based on its intensity.
     */
    public function costThought(float $intensity): void
    {
        $cost = self::COST_THOUGHT_BASE + ($intensity * self::COST_THOUGHT_INTENSITY_MULTIPLIER);
        $this->modifyEnergy(-$cost, 'thinking');
    }

    /**
     * Cost energy for conversation message.
     */
    public function costConversation(): void
    {
        $this->modifyEnergy(-self::COST_CONVERSATION_MESSAGE, 'conversation');
    }

    // ========== Energy Gain Methods ==========

    /**
     * Gain energy from goal progress.
     */
    public function gainGoalProgress(int $progressIncrement): void
    {
        $gain = ($progressIncrement / 10) * self::GAIN_GOAL_PROGRESS;
        $this->modifyEnergy($gain, 'goal_progress');
    }

    /**
     * Gain energy from completing a goal.
     */
    public function gainGoalCompleted(string $goalTitle): void
    {
        $this->modifyEnergy(self::GAIN_GOAL_COMPLETED, "goal_completed:{$goalTitle}");
    }

    /**
     * Gain energy from positive interaction.
     */
    public function gainPositiveInteraction(): void
    {
        $this->modifyEnergy(self::GAIN_POSITIVE_INTERACTION, 'positive_interaction');
    }

    /**
     * Gain small energy boost from recalling a memory.
     */
    public function gainMemoryRecall(): void
    {
        $this->modifyEnergy(self::GAIN_MEMORY_RECALL, 'memory_recall');
    }

    // ========== Sleep/Wake Methods ==========

    /**
     * Called when entity goes to sleep.
     */
    public function startSleep(): void
    {
        Cache::put(self::SLEEP_START_KEY, now()->toIso8601String(), 86400 * 7);
        Cache::forget(self::WAKE_TIME_KEY);

        Log::channel('entity')->info('Entity started sleeping', [
            'energy_at_sleep' => $this->getEnergy(),
        ]);
    }

    /**
     * Called when entity wakes up. Returns recovered energy.
     */
    public function wake(): float
    {
        $sleepStart = Cache::get(self::SLEEP_START_KEY);
        $currentEnergy = (float) Cache::get(self::ENERGY_KEY, self::DEFAULT_ENERGY);

        if ($sleepStart) {
            $sleepHours = Carbon::parse($sleepStart)->diffInMinutes(now()) / 60;
            $recovery = min(
                self::MAX_ENERGY - $currentEnergy,
                $sleepHours * self::SLEEP_RECOVERY_PER_HOUR
            );

            // Ensure minimum wake energy after sleep
            $newEnergy = max(
                self::WAKE_ENERGY_MINIMUM,
                $currentEnergy + $recovery
            );

            $this->setEnergy($newEnergy, "sleep_recovery:{$sleepHours}h");

            Log::channel('entity')->info('Entity woke up', [
                'sleep_duration_hours' => round($sleepHours, 2),
                'energy_recovered' => round($recovery, 4),
                'new_energy' => round($newEnergy, 4),
            ]);
        } else {
            // No recorded sleep, set to default
            $this->setEnergy(self::DEFAULT_ENERGY, 'wake_no_sleep_record');
        }

        Cache::put(self::WAKE_TIME_KEY, now()->toIso8601String(), 86400 * 7);
        Cache::forget(self::SLEEP_START_KEY);

        return $this->getEnergy();
    }

    /**
     * Get hours since entity woke up.
     */
    public function getHoursAwake(): float
    {
        $wakeTime = Cache::get(self::WAKE_TIME_KEY);

        if (!$wakeTime) {
            return 0;
        }

        return Carbon::parse($wakeTime)->diffInMinutes(now()) / 60;
    }

    /**
     * Get estimated hours until energy depleted.
     */
    public function getHoursUntilDepleted(): float
    {
        $current = $this->getEnergy();

        if ($current <= self::MIN_ENERGY) {
            return 0;
        }

        return $current / self::FATIGUE_RATE_PER_HOUR;
    }

    /**
     * Check if entity should consider sleeping.
     */
    public function shouldSleep(): bool
    {
        $energy = $this->getEnergy();
        $hoursAwake = $this->getHoursAwake();

        // Urgently needs sleep
        if ($energy < 0.15) {
            return true;
        }

        // Been awake too long
        if ($hoursAwake > 18 && $energy < 0.4) {
            return true;
        }

        return false;
    }

    /**
     * Reset energy system (for testing/debugging).
     */
    public function reset(): void
    {
        Cache::forget(self::ENERGY_KEY);
        Cache::forget(self::LAST_UPDATE_KEY);
        Cache::forget(self::SLEEP_START_KEY);
        Cache::forget(self::WAKE_TIME_KEY);
        Cache::forget(self::ENERGY_LOG_KEY);

        $this->setEnergy(self::DEFAULT_ENERGY, 'reset');
    }
}
