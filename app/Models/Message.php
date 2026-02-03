<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role', // 'entity', 'human', 'system'
        'content',
        'metadata', // Zusätzliche Daten (z.B. Tool-Aufrufe)
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Das Gespräch zu dem diese Nachricht gehört.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
