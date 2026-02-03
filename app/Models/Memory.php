<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Memory extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', // 'experience', 'conversation', 'learned', 'social'
        'content',
        'summary', // Kurze Zusammenfassung
        'importance', // 0.0 - 1.0, wie wichtig die Erinnerung ist
        'emotional_valence', // -1.0 bis 1.0 (negativ bis positiv)
        'context', // Zusätzlicher Kontext (JSON)
        'related_entity', // Name einer Person/Entität falls relevant
        'thought_id', // Verknüpfung zum auslösenden Gedanken
        'recalled_count', // Wie oft wurde diese Erinnerung abgerufen
        'last_recalled_at',
    ];

    protected $casts = [
        'context' => 'array',
        'importance' => 'float',
        'emotional_valence' => 'float',
        'recalled_count' => 'integer',
        'last_recalled_at' => 'datetime',
    ];

    /**
     * Der Gedanke der zu dieser Erinnerung führte.
     */
    public function thought(): BelongsTo
    {
        return $this->belongsTo(Thought::class);
    }
}
