<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'motivation', // Why this goal?
        'type', // 'curiosity', 'social', 'learning', 'creative', 'self-improvement'
        'priority', // 0.0 - 1.0
        'status', // 'active', 'paused', 'completed', 'abandoned'
        'progress', // 0 - 100 (percentage)
        'progress_notes', // Progress notes (JSON Array)
        'origin', // How did the goal originate? 'self', 'suggested', 'derived'
        'completed_at',
        'abandoned_reason',
    ];

    protected $casts = [
        'priority' => 'float',
        'progress' => 'float',
        'progress_notes' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-complete goals when progress reaches 100%
        static::saving(function (Goal $goal) {
            // Clamp progress between 0 and 100
            $goal->progress = min(100, max(0, $goal->progress));

            // Auto-complete when progress reaches 100
            if ($goal->progress >= 100 && $goal->status === 'active') {
                $goal->status = 'completed';
                $goal->completed_at = now();

                // Add auto-completion note
                $notes = $goal->progress_notes ?? [];
                $notes[] = [
                    'timestamp' => now()->toIso8601String(),
                    'note' => 'Goal automatically completed (100% progress)',
                ];
                $goal->progress_notes = $notes;
            }
        });
    }

    /**
     * Scope for active goals.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for completed goals.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
