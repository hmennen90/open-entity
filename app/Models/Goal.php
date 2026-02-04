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
