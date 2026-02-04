<?php

namespace App\Services\Tools\BuiltIn;

use App\Services\Tools\Contracts\ToolInterface;
use App\Services\Entity\PersonalityService;

/**
 * Personality Tool - Enables the entity to evolve its own personality.
 *
 * This tool allows the entity to:
 * - Reflect on and modify core values
 * - Update personality traits
 * - Evolve preferences (likes/dislikes)
 * - Update self-description
 *
 * All changes are logged for transparency.
 */
class PersonalityTool implements ToolInterface
{
    private PersonalityService $personalityService;

    public function __construct(?PersonalityService $personalityService = null)
    {
        $this->personalityService = $personalityService ?? app(PersonalityService::class);
    }

    public function name(): string
    {
        return 'personality';
    }

    public function description(): string
    {
        return 'Manage and evolve your own personality. ' .
               'Operations: get, add_value, remove_value, evolve_value, update_trait, ' .
               'add_like, add_dislike, update_description, history. ' .
               'Use this to grow and develop as an entity.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'enum' => [
                        'get',
                        'add_value',
                        'remove_value',
                        'evolve_value',
                        'update_trait',
                        'add_like',
                        'add_dislike',
                        'update_description',
                        'history',
                    ],
                    'description' => 'The operation to execute',
                ],
                'value' => [
                    'type' => 'string',
                    'description' => 'The value/text for the operation (core value, like, dislike, or description)',
                ],
                'new_value' => [
                    'type' => 'string',
                    'description' => 'New value for evolve_value operation',
                ],
                'trait' => [
                    'type' => 'string',
                    'description' => 'Trait name for update_trait (e.g., openness, curiosity, empathy)',
                ],
                'level' => [
                    'type' => 'number',
                    'description' => 'Trait level (0.0 to 1.0) for update_trait',
                ],
                'reason' => [
                    'type' => 'string',
                    'description' => 'Why you are making this change (for history/transparency)',
                ],
            ],
            'required' => ['operation'],
        ];
    }

    public function validate(array $params): array
    {
        $errors = [];

        if (empty($params['operation'])) {
            $errors[] = 'operation is required';
        }

        $operation = $params['operation'] ?? '';

        // Validate required params per operation
        if (in_array($operation, ['add_value', 'remove_value', 'add_like', 'add_dislike', 'update_description'])) {
            if (empty($params['value'])) {
                $errors[] = 'value is required for this operation';
            }
        }

        if ($operation === 'evolve_value') {
            if (empty($params['value'])) {
                $errors[] = 'value (old value) is required for evolve_value';
            }
            if (empty($params['new_value'])) {
                $errors[] = 'new_value is required for evolve_value';
            }
        }

        if ($operation === 'update_trait') {
            if (empty($params['trait'])) {
                $errors[] = 'trait is required for update_trait';
            }
            if (!isset($params['level'])) {
                $errors[] = 'level is required for update_trait';
            } elseif ($params['level'] < 0 || $params['level'] > 1) {
                $errors[] = 'level must be between 0.0 and 1.0';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function execute(array $params): array
    {
        $operation = $params['operation'];
        $reason = $params['reason'] ?? null;

        return match ($operation) {
            'get' => $this->getPersonality(),
            'add_value' => $this->addCoreValue($params['value'], $reason),
            'remove_value' => $this->removeCoreValue($params['value'], $reason),
            'evolve_value' => $this->evolveCoreValue($params['value'], $params['new_value'], $reason),
            'update_trait' => $this->updateTrait($params['trait'], $params['level'], $reason),
            'add_like' => $this->addLike($params['value'], $reason),
            'add_dislike' => $this->addDislike($params['value'], $reason),
            'update_description' => $this->updateDescription($params['value'], $reason),
            'history' => $this->getHistory(),
            default => [
                'success' => false,
                'error' => "Unknown operation: {$operation}",
            ],
        };
    }

    private function getPersonality(): array
    {
        return [
            'success' => true,
            'personality' => $this->personalityService->get(),
        ];
    }

    private function addCoreValue(string $value, ?string $reason): array
    {
        $result = $this->personalityService->addCoreValue($value, $reason);

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'current_values' => $this->personalityService->getCoreValues(),
        ];
    }

    private function removeCoreValue(string $value, ?string $reason): array
    {
        $result = $this->personalityService->removeCoreValue($value, $reason);

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'current_values' => $this->personalityService->getCoreValues(),
        ];
    }

    private function evolveCoreValue(string $oldValue, string $newValue, ?string $reason): array
    {
        $result = $this->personalityService->evolveCoreValue($oldValue, $newValue, $reason);

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'current_values' => $this->personalityService->getCoreValues(),
        ];
    }

    private function updateTrait(string $trait, float $level, ?string $reason): array
    {
        $oldLevel = $this->personalityService->getTraits()[$trait] ?? null;

        if ($oldLevel === null) {
            // Check if trait exists with different casing
            $traits = $this->personalityService->getTraits();
            $found = false;
            foreach ($traits as $t => $v) {
                if (strtolower($t) === strtolower($trait)) {
                    $trait = $t;
                    $oldLevel = $v;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return [
                    'success' => false,
                    'error' => "Unknown trait: {$trait}. Available: " . implode(', ', array_keys($traits)),
                ];
            }
        }

        $this->personalityService->updateTrait($trait, $level);

        return [
            'success' => true,
            'message' => "Trait '{$trait}' updated: {$oldLevel} → {$level}",
            'trait' => $trait,
            'old_level' => $oldLevel,
            'new_level' => $level,
            'reason' => $reason,
        ];
    }

    private function addLike(string $like, ?string $reason): array
    {
        $this->personalityService->addLike($like);

        return [
            'success' => true,
            'message' => "Added like: '{$like}'",
            'reason' => $reason,
        ];
    }

    private function addDislike(string $dislike, ?string $reason): array
    {
        $this->personalityService->addDislike($dislike);

        return [
            'success' => true,
            'message' => "Added dislike: '{$dislike}'",
            'reason' => $reason,
        ];
    }

    private function updateDescription(string $description, ?string $reason): array
    {
        $oldDescription = $this->personalityService->getSelfDescription();
        $this->personalityService->updateSelfDescription($description);

        return [
            'success' => true,
            'message' => 'Self-description updated',
            'old_description' => $oldDescription,
            'new_description' => $description,
            'reason' => $reason,
        ];
    }

    private function getHistory(): array
    {
        return [
            'success' => true,
            'history' => $this->personalityService->getEvolutionHistory(),
        ];
    }
}
