<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Relationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', // Name der Person/Entität
        'type', // 'human', 'entity', 'group'
        'platform', // 'web', 'moltbook', 'discord'
        'familiarity', // 0.0 - 1.0, wie gut kennt Nova diese Person
        'affinity', // -1.0 bis 1.0, Sympathie
        'trust', // 0.0 - 1.0, Vertrauen
        'notes', // Notizen über die Person (JSON)
        'known_facts', // Bekannte Fakten über die Person (JSON Array)
        'last_interaction_at',
        'interaction_count',
    ];

    protected $casts = [
        'familiarity' => 'float',
        'affinity' => 'float',
        'trust' => 'float',
        'notes' => 'array',
        'known_facts' => 'array',
        'last_interaction_at' => 'datetime',
        'interaction_count' => 'integer',
    ];

    /**
     * Aktualisiere nach einer Interaktion.
     */
    public function recordInteraction(): void
    {
        $this->increment('interaction_count');
        $this->update(['last_interaction_at' => now()]);
    }
}
