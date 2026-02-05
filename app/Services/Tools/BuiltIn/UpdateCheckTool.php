<?php

namespace App\Services\Tools\BuiltIn;

use App\Events\UpdateAvailable;
use App\Services\Tools\Contracts\ToolInterface;
use Illuminate\Support\Facades\Http;

/**
 * Update Check Tool - Enables OpenEntity to check for new versions.
 *
 * Use this tool to check if a newer version of OpenEntity is available.
 * This allows the entity to be aware of updates and inform users.
 */
class UpdateCheckTool implements ToolInterface
{
    private int $timeout;
    private string $userAgent;
    private string $repository;

    public function __construct(int $timeout = 10)
    {
        $this->timeout = $timeout;
        $this->userAgent = 'OpenEntity/1.0 (Autonomous AI Entity; +https://github.com/openentity)';
        $this->repository = config('entity.tools.update_check.repository', 'hmennen90/open-entity');
    }

    public function name(): string
    {
        return 'update_check';
    }

    public function description(): string
    {
        return 'Check for new versions of OpenEntity. ' .
               'Returns information about the current version, latest available version, and changelog. ' .
               'Use this to stay aware of updates and inform users about new features.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'include_changelog' => [
                    'type' => 'boolean',
                    'description' => 'If true, include the release notes/changelog for new versions (default: true)',
                ],
                'include_prerelease' => [
                    'type' => 'boolean',
                    'description' => 'If true, also check for pre-release versions (default: false)',
                ],
                'notify_user' => [
                    'type' => 'boolean',
                    'description' => 'If true, broadcast a notification to the user when an update is available (default: true)',
                ],
            ],
            'required' => [],
        ];
    }

    public function validate(array $params): array
    {
        // No required parameters, all optional with sensible defaults
        return [
            'valid' => true,
            'errors' => [],
        ];
    }

    public function execute(array $params): array
    {
        $includeChangelog = $params['include_changelog'] ?? true;
        $includePrerelease = $params['include_prerelease'] ?? false;
        $notifyUser = $params['notify_user'] ?? true;

        try {
            $currentVersion = $this->getCurrentVersion();
            $latestRelease = $this->fetchLatestRelease($includePrerelease);

            if (!$latestRelease) {
                return [
                    'success' => true,
                    'result' => [
                        'current_version' => $currentVersion,
                        'latest_version' => null,
                        'update_available' => false,
                        'message' => 'Could not fetch release information from GitHub.',
                    ],
                    'error' => null,
                ];
            }

            $latestVersion = ltrim($latestRelease['tag_name'], 'v');
            $updateAvailable = version_compare($latestVersion, $currentVersion, '>');

            $changelog = null;
            if ($includeChangelog && !empty($latestRelease['body'])) {
                $changelog = $this->formatChangelog($latestRelease['body']);
            }

            $result = [
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'update_available' => $updateAvailable,
                'release_url' => $latestRelease['html_url'],
                'published_at' => $latestRelease['published_at'],
                'is_prerelease' => $latestRelease['prerelease'],
            ];

            if ($updateAvailable) {
                $result['message'] = "A new version ({$latestVersion}) is available! You are running {$currentVersion}.";
                $result['changelog'] = $changelog;

                // Notify the user via WebSocket if requested
                if ($notifyUser) {
                    event(new UpdateAvailable(
                        currentVersion: $currentVersion,
                        latestVersion: $latestVersion,
                        releaseUrl: $latestRelease['html_url'],
                        changelog: $changelog
                    ));
                }
            } else {
                $result['message'] = "You are running the latest version ({$currentVersion}).";
            }

            return [
                'success' => true,
                'result' => $result,
                'error' => null,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'result' => null,
                'error' => [
                    'type' => 'update_check_failed',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Get the current version from composer.json or config.
     */
    private function getCurrentVersion(): string
    {
        // Try to get version from config first
        $configVersion = config('entity.version');
        if ($configVersion) {
            return $configVersion;
        }

        // Fall back to composer.json
        $composerPath = base_path('composer.json');
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            if (!empty($composer['version'])) {
                return $composer['version'];
            }
        }

        // Default version if not found
        return '1.0.0';
    }

    /**
     * Fetch the latest release from GitHub API.
     */
    private function fetchLatestRelease(bool $includePrerelease): ?array
    {
        $url = "https://api.github.com/repos/{$this->repository}/releases";

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'User-Agent' => $this->userAgent,
                'Accept' => 'application/vnd.github.v3+json',
            ])
            ->get($url);

        if (!$response->successful()) {
            return null;
        }

        $releases = $response->json();

        if (empty($releases)) {
            return null;
        }

        // Find the appropriate release
        foreach ($releases as $release) {
            // Skip prereleases unless explicitly requested
            if (!$includePrerelease && $release['prerelease']) {
                continue;
            }

            // Skip drafts
            if ($release['draft']) {
                continue;
            }

            return $release;
        }

        return $releases[0] ?? null;
    }

    /**
     * Format the changelog for readability.
     */
    private function formatChangelog(string $body): string
    {
        // Limit changelog length to avoid huge responses
        $maxLength = 2000;
        if (strlen($body) > $maxLength) {
            $body = substr($body, 0, $maxLength) . "\n\n[... changelog truncated]";
        }

        return $body;
    }
}
