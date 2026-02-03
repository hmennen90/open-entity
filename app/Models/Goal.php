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
        'motivation', // Warum dieses Ziel?
        'type', // 'curiosity', 'social', 'learning', 'creative', 'self-improvement'
        'priority', // 0.0 - 1.0
        'status', // 'active', 'paused', 'completed', 'abandoned'
        'progress', // 0.0 - 1.0
        'progress_notes', // Notizen zum Fortschritt (JSON Array)
        'origin', // Wie entstand das Ziel? 'self', 'suggested', 'derived'
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
     * Scope für aktive Ziele.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope für abgeschlossene Ziele.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
