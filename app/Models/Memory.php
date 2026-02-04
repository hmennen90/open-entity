<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Memory extends Model
{
    use HasFactory;

    /**
     * Fields hidden from JSON serialization.
     * The embedding is binary data and cannot be JSON encoded.
     */
    protected $hidden = [
        'embedding',
    ];

    protected $fillable = [
        'type', // 'experience', 'conversation', 'learned', 'social'
        'layer', // 'episodic', 'semantic', 'procedural'
        'content',
        'summary', // Short summary
        'importance', // 0.0 - 1.0, how important the memory is
        'emotional_valence', // -1.0 to 1.0 (negative to positive)
        'context', // Additional context (JSON)
        'related_entity', // Name of a person/entity if relevant
        'thought_id', // Link to the triggering thought
        'recalled_count', // How often was this memory recalled
        'last_recalled_at',
        // Embedding fields
        'embedding',
        'embedding_dimensions',
        'embedding_model',
        'embedded_at',
        // Consolidation fields
        'is_consolidated',
        'consolidated_into_id',
        'consolidated_at',
        'semantic_tags',
    ];

    protected $casts = [
        'context' => 'array',
        'semantic_tags' => 'array',
        'importance' => 'float',
        'emotional_valence' => 'float',
        'recalled_count' => 'integer',
        'last_recalled_at' => 'datetime',
        'embedded_at' => 'datetime',
        'consolidated_at' => 'datetime',
        'is_consolidated' => 'boolean',
        'embedding_dimensions' => 'integer',
    ];

    /**
     * The thought that led to this memory.
     */
    public function thought(): BelongsTo
    {
        return $this->belongsTo(Thought::class);
    }

    /**
     * The summary this memory was consolidated into.
     */
    public function consolidatedInto(): BelongsTo
    {
        return $this->belongsTo(MemorySummary::class, 'consolidated_into_id');
    }

    /**
     * Memories that were consolidated from this one (if this is a semantic memory).
     */
    public function consolidatedFrom(): HasMany
    {
        return $this->hasMany(Memory::class, 'consolidated_into_id');
    }

    /**
     * Scope for unconsolidated memories.
     */
    public function scopeUnconsolidated($query)
    {
        return $query->where('is_consolidated', false);
    }

    /**
     * Scope for memories with embeddings.
     */
    public function scopeWithEmbedding($query)
    {
        return $query->whereNotNull('embedding')->whereNotNull('embedded_at');
    }

    /**
     * Scope for memories by layer.
     */
    public function scopeOfLayer($query, string $layer)
    {
        return $query->where('layer', $layer);
    }

    /**
     * Check if this memory has an embedding.
     */
    public function hasEmbedding(): bool
    {
        return !empty($this->embedding) && !empty($this->embedded_at);
    }

    /**
     * Get text suitable for embedding generation.
     */
    public function getTextForEmbedding(): string
    {
        $text = $this->summary ?? $this->content;

        // Prepend type for better semantic context
        if ($this->type) {
            $text = "[{$this->type}] " . $text;
        }

        return $text;
    }
}
