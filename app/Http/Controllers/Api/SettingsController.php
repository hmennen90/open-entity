<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SettingsController - Manages user settings.
 */
class SettingsController extends Controller
{
    private string $userMdPath;

    public function __construct()
    {
        $this->userMdPath = storage_path('app/public/workspace/USER.md');
    }

    /**
     * Get the current language preference.
     */
    public function getLanguage(): JsonResponse
    {
        $language = $this->readLanguageFromUserMd();

        return response()->json([
            'language' => $language ?? 'de',
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
        $this->updateLanguageInUserMd($language);

        return response()->json([
            'success' => true,
            'language' => $language,
        ]);
    }

    /**
     * Read language preference from USER.md.
     */
    private function readLanguageFromUserMd(): ?string
    {
        if (!file_exists($this->userMdPath)) {
            return null;
        }

        $content = file_get_contents($this->userMdPath);

        if (preg_match('/\*\*Language:\*\*\s*(\w+)/i', $content, $matches)) {
            return strtolower(trim($matches[1]));
        }

        return null;
    }

    /**
     * Update or add language field in USER.md.
     */
    private function updateLanguageInUserMd(string $language): void
    {
        // Ensure directory exists
        $dir = dirname($this->userMdPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Read existing content or create default
        if (file_exists($this->userMdPath)) {
            $content = file_get_contents($this->userMdPath);

            // Check if Language field exists
            if (preg_match('/\*\*Language:\*\*\s*\w*/i', $content)) {
                // Update existing field
                $content = preg_replace(
                    '/(\*\*Language:\*\*\s*)\w*/i',
                    '$1' . $language,
                    $content
                );
            } else {
                // Add Language field after the header section
                $content = preg_replace(
                    '/(\*\*What to call them:\*\*[^\n]*\n)/i',
                    "$1**Language:** {$language}\n",
                    $content
                );

                // If that didn't work, append at the end
                if (!str_contains($content, '**Language:**')) {
                    $content .= "\n**Language:** {$language}\n";
                }
            }
        } else {
            // Create new USER.md with default structure
            $content = <<<MD
# User Profile

**What to call them:** User
**Language:** {$language}

## About
(Add information about the user here)

## Preferences
(Add user preferences here)
MD;
        }

        file_put_contents($this->userMdPath, $content);
    }
}
