<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'participant', // Name des Gesprächspartners
        'participant_type', // 'human', 'entity', 'system'
        'channel', // 'web', 'moltbook', 'discord'
        'summary', // Zusammenfassung des Gesprächs
        'sentiment', // Gesamtstimmung des Gesprächs
        'ended_at',
    ];

    protected $casts = [
        'sentiment' => 'float',
        'ended_at' => 'datetime',
    ];

    /**
     * Nachrichten in diesem Gespräch.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
