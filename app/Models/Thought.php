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
        'trigger', // Was den Gedanken ausgelöst hat
        'context', // Zusätzlicher Kontext
        'intensity', // 0.0 - 1.0, wie "stark" der Gedanke ist
        'led_to_action', // Ob der Gedanke zu einer Aktion führte
        'action_taken', // Welche Aktion
    ];

    protected $casts = [
        'context' => 'array',
        'intensity' => 'float',
        'led_to_action' => 'boolean',
    ];

    /**
     * Beziehung zu Memories die aus diesem Gedanken entstanden.
     */
    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }
}
