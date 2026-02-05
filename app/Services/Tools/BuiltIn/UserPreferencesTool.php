<?php

namespace App\Services\Tools\BuiltIn;

use App\Services\Tools\Contracts\ToolInterface;

/**
 * User Preferences Tool - Allows the entity to read and update user preferences.
 *
 * Use this tool to remember user preferences like language, name, interests,
 * or any other information the user shares about themselves.
 * Preferences are stored in storage/entity/user/preferences.json
 */
class UserPreferencesTool implements ToolInterface
{
    private string $preferencesPath;

    public function __construct()
    {
        $this->preferencesPath = config('entity.storage_path') . '/user/preferences.json';
    }

    public function name(): string
    {
        return 'user_preferences';
    }

    public function description(): string
    {
        return 'Read and update user preferences. ' .
               'Use this to remember things the user tells you about themselves, ' .
               'like their preferred language, name, interests, or communication style. ' .
               'When a user says "Let\'s speak English" or "Nenn mich bitte du", update their preferences.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['read', 'update', 'delete'],
                    'description' => 'Action to perform: read current preferences, update them, or delete a field',
                ],
                'field' => [
                    'type' => 'string',
                    'description' => 'The preference field (e.g., "language", "name", "call_them", "notes", "interests")',
                ],
                'value' => [
                    'type' => 'string',
                    'description' => 'The new value for the field (for update action)',
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function validate(array $params): array
    {
        $errors = [];

        if (empty($params['action'])) {
            $errors[] = 'action is required';
        } elseif (!in_array($params['action'], ['read', 'update', 'delete'])) {
            $errors[] = 'action must be "read", "update", or "delete"';
        }

        if (in_array($params['action'] ?? '', ['update', 'delete'])) {
            if (empty($params['field'])) {
                $errors[] = 'field is required for update/delete action';
            }
        }

        if (($params['action'] ?? '') === 'update') {
            if (!isset($params['value'])) {
                $errors[] = 'value is required for update action';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function execute(array $params): array
    {
        $action = $params['action'];

        try {
            return match ($action) {
                'read' => $this->readPreferences(),
                'update' => $this->updatePreference($params['field'], $params['value']),
                'delete' => $this->deletePreference($params['field']),
            };
        } catch (\Exception $e) {
            return [
                'success' => false,
                'result' => null,
                'error' => [
                    'type' => 'preferences_error',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Read all user preferences.
     */
    private function readPreferences(): array
    {
        $preferences = $this->loadPreferences();

        return [
            'success' => true,
            'result' => [
                'preferences' => $preferences,
            ],
            'error' => null,
        ];
    }

    /**
     * Update a specific preference.
     */
    private function updatePreference(string $field, string $value): array
    {
        $preferences = $this->loadPreferences();

        // Normalize field name
        $field = strtolower(trim($field));

        // Handle special fields
        if ($field === 'language') {
            $value = strtolower(trim($value));
            if (!in_array($value, ['de', 'en'])) {
                return [
                    'success' => false,
                    'result' => null,
                    'error' => [
                        'type' => 'validation_error',
                        'message' => 'Language must be "de" or "en"',
                    ],
                ];
            }
        }

        $preferences[$field] = $value;
        $preferences['updated_at'] = now()->toIso8601String();

        $this->savePreferences($preferences);

        return [
            'success' => true,
            'result' => [
                'field' => $field,
                'value' => $value,
                'message' => "Updated {$field} to: {$value}",
            ],
            'error' => null,
        ];
    }

    /**
     * Delete a preference field.
     */
    private function deletePreference(string $field): array
    {
        $preferences = $this->loadPreferences();
        $field = strtolower(trim($field));

        if (!isset($preferences[$field])) {
            return [
                'success' => true,
                'result' => [
                    'message' => "Field '{$field}' was not set",
                ],
                'error' => null,
            ];
        }

        unset($preferences[$field]);
        $preferences['updated_at'] = now()->toIso8601String();

        $this->savePreferences($preferences);

        return [
            'success' => true,
            'result' => [
                'field' => $field,
                'message' => "Deleted field: {$field}",
            ],
            'error' => null,
        ];
    }

    /**
     * Load preferences from JSON file.
     */
    private function loadPreferences(): array
    {
        if (!file_exists($this->preferencesPath)) {
            return $this->getDefaultPreferences();
        }

        $content = file_get_contents($this->preferencesPath);
        $preferences = json_decode($content, true);

        return is_array($preferences) ? $preferences : $this->getDefaultPreferences();
    }

    /**
     * Save preferences to JSON file.
     */
    private function savePreferences(array $preferences): void
    {
        $dir = dirname($this->preferencesPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->preferencesPath,
            json_encode($preferences, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Get default preferences structure.
     */
    private function getDefaultPreferences(): array
    {
        return [
            'created_at' => now()->toIso8601String(),
        ];
    }
}
