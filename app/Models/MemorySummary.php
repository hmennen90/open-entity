<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MemorySummary - Consolidated memory summaries from daily/weekly/monthly consolidation.
 *
 * Like sleep for the brain, consolidation processes raw episodic memories
 * into distilled knowledge summaries.
 */
class MemorySummary extends Model
{
    /**
     * Fields hidden from JSON serialization.
     * The embedding is binary data and cannot be JSON encoded.
     */
    protected $hidden = [
        'embedding',
    ];

    protected $fillable = [
        'period_start',
        'period_end',
        'period_type',
        'summary',
        'key_insights',
        'average_emotional_valence',
        'themes',
        'entities_mentioned',
        'embedding',
        'embedding_dimensions',
        'embedding_model',
        'source_memory_count',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'themes' => 'array',
        'entities_mentioned' => 'array',
        'average_emotional_valence' => 'float',
        'source_memory_count' => 'integer',
        'embedding_dimensions' => 'integer',
    ];

    /**
     * Get memories that were consolidated into this summary.
     */
    public function sourceMemories(): HasMany
    {
        return $this->hasMany(Memory::class, 'consolidated_into_id');
    }

    /**
     * Scope to get summaries by period type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('period_type', $type);
    }

    /**
     * Scope to get summaries within a date range.
     */
    public function scopeWithinPeriod($query, $start, $end)
    {
        return $query->where('period_start', '>=', $start)
            ->where('period_end', '<=', $end);
    }

    /**
     * Check if this summary covers a specific date.
     */
    public function coversDate($date): bool
    {
        $date = \Carbon\Carbon::parse($date);
        return $date->between($this->period_start, $this->period_end);
    }

    /**
     * Get a short description of the period.
     */
    public function getPeriodDescriptionAttribute(): string
    {
        return match ($this->period_type) {
            'daily' => $this->period_start->format('M j, Y'),
            'weekly' => $this->period_start->format('M j') . ' - ' . $this->period_end->format('M j, Y'),
            'monthly' => $this->period_start->format('F Y'),
            default => $this->period_start->format('Y-m-d') . ' to ' . $this->period_end->format('Y-m-d'),
        };
    }
}
