<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SettingsController - Manages user settings via API.
 *
 * Settings are stored in storage/entity/user/preferences.json
 */
class SettingsController extends Controller
{
    private string $preferencesPath;

    public function __construct()
    {
        $this->preferencesPath = config('entity.storage_path') . '/user/preferences.json';
    }

    /**
     * Get all user preferences.
     */
    public function getPreferences(): JsonResponse
    {
        $preferences = $this->loadPreferences();

        return response()->json([
            'preferences' => $preferences,
        ]);
    }

    /**
     * Get the current language preference.
     */
    public function getLanguage(): JsonResponse
    {
        $preferences = $this->loadPreferences();

        return response()->json([
            'language' => $preferences['language'] ?? config('entity.language', 'de'),
        ]);
    }

    /**
     * Set the language preference.
     */
    public function setLanguage(Request $request): JsonResponse
    {
        $request->validate([
            'language' => 'required|string|in:de,en',
        ]);

        $language = $request->input('language');
        $preferences = $this->loadPreferences();
        $preferences['language'] = $language;
        $preferences['updated_at'] = now()->toIso8601String();

        $this->savePreferences($preferences);

        return response()->json([
            'success' => true,
            'language' => $language,
        ]);
    }

    /**
     * Update a preference field.
     */
    public function updatePreference(Request $request): JsonResponse
    {
        $request->validate([
            'field' => 'required|string|max:50',
            'value' => 'required|string|max:1000',
        ]);

        $field = strtolower($request->input('field'));
        $value = $request->input('value');

        // Validate language field
        if ($field === 'language' && !in_array($value, ['de', 'en'])) {
            return response()->json([
                'success' => false,
                'error' => 'Language must be "de" or "en"',
            ], 422);
        }

        $preferences = $this->loadPreferences();
        $preferences[$field] = $value;
        $preferences['updated_at'] = now()->toIso8601String();

        $this->savePreferences($preferences);

        return response()->json([
            'success' => true,
            'field' => $field,
            'value' => $value,
        ]);
    }

    /**
     * Load preferences from JSON file.
     */
    private function loadPreferences(): array
    {
        if (!file_exists($this->preferencesPath)) {
            return [
                'created_at' => now()->toIso8601String(),
            ];
        }

        try {
            $content = file_get_contents($this->preferencesPath);
            if ($content === false) {
                return ['created_at' => now()->toIso8601String()];
            }
            $preferences = json_decode($content, true);
            return is_array($preferences) ? $preferences : [];
        } catch (\Exception $e) {
            return ['created_at' => now()->toIso8601String()];
        }
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

        $json = json_encode($preferences, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode preferences as JSON');
        }

        $result = file_put_contents($this->preferencesPath, $json, LOCK_EX);
        if ($result === false) {
            throw new \RuntimeException('Failed to write preferences file');
        }
    }
}
