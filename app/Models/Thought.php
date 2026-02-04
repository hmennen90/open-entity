<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thought extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'type', // 'observation', 'reflection', 'decision', 'emotion', 'curiosity'
        'trigger', // What triggered the thought
        'context', // Additional context
        'intensity', // 0.0 - 1.0, how "strong" the thought is
        'led_to_action', // Whether the thought led to an action
        'action_taken', // Which action was taken
    ];

    protected $casts = [
        'context' => 'array',
        'intensity' => 'float',
        'led_to_action' => 'boolean',
    ];

    /**
     * Relationship to memories that emerged from this thought.
     */
    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }
}
