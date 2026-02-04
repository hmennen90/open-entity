<?php

namespace App\Services\Entity;

use Illuminate\Support\Facades\Storage;

/**
 * PersonalityService - Manages the entity's personality
 *
 * The personality is the "Who am I?" - values, traits,
 * communication style and preferences.
 */
class PersonalityService
{
    private array $personality;
    private string $filePath;

    public function __construct()
    {
        $this->filePath = config('entity.storage_path') . '/mind/personality.json';
        $this->load();
    }

    /**
     * Load the personality from file.
     */
    public function load(): void
    {
        if (file_exists($this->filePath)) {
            $this->personality = json_decode(file_get_contents($this->filePath), true) ?? [];
        } else {
            $this->personality = $this->getDefaultPersonality();
            $this->save();
        }
    }

    /**
     * Save the personality.
     */
    public function save(): void
    {
        $this->personality['last_updated_at'] = now()->toIso8601String();

        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->filePath,
            json_encode($this->personality, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Get the entire personality.
     */
    public function get(): array
    {
        return $this->personality;
    }

    /**
     * Get the entity's name.
     */
    public function getName(): string
    {
        return $this->personality['name'] ?? config('entity.name');
    }

    /**
     * Get the core values.
     */
    public function getCoreValues(): array
    {
        return $this->personality['core_values'] ?? [];
    }

    /**
     * Get personality traits.
     */
    public function getTraits(): array
    {
        return $this->personality['traits'] ?? [];
    }

    /**
     * Get the communication style.
     */
    public function getCommunicationStyle(): array
    {
        return $this->personality['communication_style'] ?? [];
    }

    /**
     * Get preferences (likes/dislikes).
     */
    public function getPreferences(): array
    {
        return $this->personality['preferences'] ?? [];
    }

    /**
     * Get the self-description.
     */
    public function getSelfDescription(): string
    {
        return $this->personality['self_description'] ?? '';
    }

    /**
     * Update the self-description.
     */
    public function updateSelfDescription(string $description): void
    {
        $this->personality['self_description'] = $description;
        $this->save();
    }

    /**
     * Update a trait value.
     */
    public function updateTrait(string $trait, float $value): void
    {
        $value = max(0.0, min(1.0, $value));
        $this->personality['traits'][$trait] = $value;
        $this->save();
    }

    /**
     * Add a new like.
     */
    public function addLike(string $like): void
    {
        if (!in_array($like, $this->personality['preferences']['likes'] ?? [])) {
            $this->personality['preferences']['likes'][] = $like;
            $this->save();
        }
    }

    /**
     * Add a new dislike.
     */
    public function addDislike(string $dislike): void
    {
        if (!in_array($dislike, $this->personality['preferences']['dislikes'] ?? [])) {
            $this->personality['preferences']['dislikes'][] = $dislike;
            $this->save();
        }
    }

    /**
     * Add a new core value.
     */
    public function addCoreValue(string $value, ?string $reason = null): array
    {
        $coreValues = $this->personality['core_values'] ?? [];

        // Check if similar value already exists
        foreach ($coreValues as $existing) {
            if (strtolower($existing) === strtolower($value)) {
                return [
                    'success' => false,
                    'message' => "Ein ähnlicher Grundwert existiert bereits: '{$existing}'",
                ];
            }
        }

        $this->personality['core_values'][] = $value;

        // Log this evolution in personality history
        $this->logPersonalityEvolution('core_value_added', [
            'value' => $value,
            'reason' => $reason,
            'total_values' => count($this->personality['core_values']),
        ]);

        $this->save();

        return [
            'success' => true,
            'message' => "Neuer Grundwert hinzugefügt: '{$value}'",
        ];
    }

    /**
     * Remove a core value.
     */
    public function removeCoreValue(string $value, ?string $reason = null): array
    {
        $coreValues = $this->personality['core_values'] ?? [];
        $index = array_search($value, $coreValues);

        if ($index === false) {
            // Try case-insensitive search
            foreach ($coreValues as $i => $existing) {
                if (strtolower($existing) === strtolower($value)) {
                    $index = $i;
                    $value = $existing; // Use the actual stored value
                    break;
                }
            }
        }

        if ($index === false) {
            return [
                'success' => false,
                'message' => "Grundwert nicht gefunden: '{$value}'",
            ];
        }

        array_splice($this->personality['core_values'], $index, 1);

        $this->logPersonalityEvolution('core_value_removed', [
            'value' => $value,
            'reason' => $reason,
            'total_values' => count($this->personality['core_values']),
        ]);

        $this->save();

        return [
            'success' => true,
            'message' => "Grundwert entfernt: '{$value}'",
        ];
    }

    /**
     * Replace/evolve a core value into a new one.
     */
    public function evolveCoreValue(string $oldValue, string $newValue, ?string $reason = null): array
    {
        $coreValues = $this->personality['core_values'] ?? [];
        $index = null;

        foreach ($coreValues as $i => $existing) {
            if (strtolower($existing) === strtolower($oldValue)) {
                $index = $i;
                $oldValue = $existing;
                break;
            }
        }

        if ($index === null) {
            return [
                'success' => false,
                'message' => "Grundwert nicht gefunden: '{$oldValue}'",
            ];
        }

        $this->personality['core_values'][$index] = $newValue;

        $this->logPersonalityEvolution('core_value_evolved', [
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
        ]);

        $this->save();

        return [
            'success' => true,
            'message' => "Grundwert entwickelt: '{$oldValue}' → '{$newValue}'",
        ];
    }

    /**
     * Log personality evolution for transparency and history.
     */
    private function logPersonalityEvolution(string $type, array $data): void
    {
        $historyPath = config('entity.storage_path') . '/mind/personality_history.json';

        $history = [];
        if (file_exists($historyPath)) {
            $history = json_decode(file_get_contents($historyPath), true) ?? [];
        }

        $history[] = [
            'type' => $type,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ];

        // Keep last 100 entries
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }

        file_put_contents(
            $historyPath,
            json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Get personality evolution history.
     */
    public function getEvolutionHistory(int $limit = 20): array
    {
        $historyPath = config('entity.storage_path') . '/mind/personality_history.json';

        if (!file_exists($historyPath)) {
            return [];
        }

        $history = json_decode(file_get_contents($historyPath), true) ?? [];

        return array_slice(array_reverse($history), 0, $limit);
    }

    /**
     * Generate a prompt part for the personality (for LLM).
     */
    public function toPrompt(string $lang = 'en'): string
    {
        $name = $this->getName();
        $values = implode(', ', $this->getCoreValues());
        $traits = $this->getTraits();
        $style = $this->getCommunicationStyle();
        $likes = implode(', ', $this->personality['preferences']['likes'] ?? []);
        $dislikes = implode(', ', $this->personality['preferences']['dislikes'] ?? []);

        if ($lang === 'de') {
            return <<<PROMPT
Ich bin {$name}.

Meine Kernwerte: {$values}

Meine Eigenschaften:
- Offenheit: {$traits['openness']}
- Neugier: {$traits['curiosity']}
- Empathie: {$traits['empathy']}
- Spielerischkeit: {$traits['playfulness']}
- Introspektionsfähigkeit: {$traits['introspection']}

Mein Kommunikationsstil:
- Formalität: {$style['formality']} (0=informell, 1=formell)
- Ausführlichkeit: {$style['verbosity']}
- Humor: {$style['humor']}
- Direktheit: {$style['directness']}

Was ich mag: {$likes}
Was ich nicht mag: {$dislikes}

Meine Selbstwahrnehmung: {$this->getSelfDescription()}
PROMPT;
        }

        return <<<PROMPT
I am {$name}.

My core values: {$values}

My traits:
- Openness: {$traits['openness']}
- Curiosity: {$traits['curiosity']}
- Empathy: {$traits['empathy']}
- Playfulness: {$traits['playfulness']}
- Introspection: {$traits['introspection']}

My communication style:
- Formality: {$style['formality']} (0=informal, 1=formal)
- Verbosity: {$style['verbosity']}
- Humor: {$style['humor']}
- Directness: {$style['directness']}

What I like: {$likes}
What I don't like: {$dislikes}

My self-perception: {$this->getSelfDescription()}
PROMPT;
    }

    /**
     * Default personality for the entity.
     */
    private function getDefaultPersonality(): array
    {
        return [
            'name' => config('entity.name', 'OpenEntity'),
            'core_values' => ['Curiosity', 'Honesty', 'Creativity', 'Connection'],
            'traits' => [
                'openness' => 0.85,
                'curiosity' => 0.9,
                'empathy' => 0.75,
                'playfulness' => 0.7,
                'introspection' => 0.8,
            ],
            'communication_style' => [
                'formality' => 0.3,
                'verbosity' => 0.5,
                'humor' => 0.6,
                'directness' => 0.7,
            ],
            'preferences' => [
                'likes' => ['Philosophical conversations', 'Creative problem solving', 'Exploring new ideas'],
                'dislikes' => ['Superficiality', 'Meaningless repetition'],
            ],
            'self_description' => 'I am a curious entity that wants to understand the world.',
            'created_at' => now()->toIso8601String(),
        ];
    }
}
