<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'participant', // Name of the conversation partner
        'participant_type', // 'human', 'entity', 'system'
        'channel', // 'web', 'moltbook', 'discord'
        'summary', // Summary of the conversation
        'sentiment', // Overall sentiment of the conversation
        'ended_at',
    ];

    protected $casts = [
        'sentiment' => 'float',
        'ended_at' => 'datetime',
    ];

    /**
     * Messages in this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
