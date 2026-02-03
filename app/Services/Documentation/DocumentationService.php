<?php

namespace App\Services\Documentation;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DocumentationService
{
    private string $basePath;
    private array $analysis = [];

    public function __construct()
    {
        $this->basePath = base_path();
    }

    /**
     * Analyze the codebase and return structured information.
     */
    public function analyzeCodebase(): array
    {
        $this->analysis = [
            'timestamp' => now()->toIso8601String(),
            'backend' => $this->analyzeBackend(),
            'frontend' => $this->analyzeFrontend(),
            'docker' => $this->analyzeDocker(),
            'tests' => $this->analyzeTests(),
            'api' => $this->analyzeApi(),
            'commands' => $this->analyzeCommands(),
        ];

        return $this->analysis;
    }

    /**
     * Analyze backend structure.
     */
    private function analyzeBackend(): array
    {
        $services = [];
        $servicePath = app_path('Services');

        if (File::isDirectory($servicePath)) {
            foreach (File::directories($servicePath) as $dir) {
                $name = basename($dir);
                $files = File::files($dir);
                $services[$name] = array_map(fn($f) => $f->getFilenameWithoutExtension(), $files);
            }
        }

        $models = [];
        $modelPath = app_path('Models');
        if (File::isDirectory($modelPath)) {
            $models = array_map(
                fn($f) => $f->getFilenameWithoutExtension(),
                File::files($modelPath)
            );
        }

        $events = [];
        $eventPath = app_path('Events');
        if (File::isDirectory($eventPath)) {
            $events = array_map(
                fn($f) => $f->getFilenameWithoutExtension(),
                File::files($eventPath)
            );
        }

        return [
            'services' => $services,
            'models' => $models,
            'events' => $events,
            'has_entity_services' => isset($services['Entity']) && count($services['Entity']) > 0,
            'has_llm_services' => isset($services['LLM']) && count($services['LLM']) > 0,
            'has_tool_services' => isset($services['Tools']) && count($services['Tools']) > 0,
        ];
    }

    /**
     * Analyze frontend structure.
     */
    private function analyzeFrontend(): array
    {
        $jsPath = resource_path('js');

        $views = [];
        $viewsPath = $jsPath . '/views';
        if (File::isDirectory($viewsPath)) {
            $views = array_map(
                fn($f) => str_replace('.vue', '', $f->getFilename()),
                File::files($viewsPath)
            );
        }

        $components = [];
        $componentsPath = $jsPath . '/components';
        if (File::isDirectory($componentsPath)) {
            $components = array_map(
                fn($f) => str_replace('.vue', '', $f->getFilename()),
                File::files($componentsPath)
            );
        }

        $stores = [];
        $storesPath = $jsPath . '/stores';
        if (File::isDirectory($storesPath)) {
            $stores = array_map(
                fn($f) => str_replace('.js', '', $f->getFilename()),
                File::files($storesPath)
            );
        }

        return [
            'views' => $views,
            'components' => $components,
            'stores' => $stores,
            'has_router' => File::exists($jsPath . '/router/index.js'),
            'has_echo' => File::exists($jsPath . '/echo.js'),
        ];
    }

    /**
     * Analyze Docker configuration.
     */
    private function analyzeDocker(): array
    {
        $composeFile = $this->basePath . '/docker-compose.yml';
        $services = [];

        if (File::exists($composeFile)) {
            $content = File::get($composeFile);
            // Simple regex to find service names
            preg_match_all('/^\s{2}(\w[\w-]*):\s*$/m', $content, $matches);
            $services = $matches[1] ?? [];
        }

        return [
            'has_compose' => File::exists($composeFile),
            'has_override' => File::exists($this->basePath . '/docker-compose.override.yml'),
            'services' => $services,
        ];
    }

    /**
     * Analyze test structure.
     */
    private function analyzeTests(): array
    {
        $testsPath = base_path('tests');

        $unitTests = [];
        $featureTests = [];

        // Unit tests
        $unitPath = $testsPath . '/Unit';
        if (File::isDirectory($unitPath)) {
            $unitTests = $this->findTestFiles($unitPath);
        }

        // Feature tests
        $featurePath = $testsPath . '/Feature';
        if (File::isDirectory($featurePath)) {
            $featureTests = $this->findTestFiles($featurePath);
        }

        // Count test methods
        $totalTests = $this->countTestMethods($testsPath);

        return [
            'unit_tests' => $unitTests,
            'feature_tests' => $featureTests,
            'total_test_files' => count($unitTests) + count($featureTests),
            'estimated_test_count' => $totalTests,
        ];
    }

    /**
     * Find test files recursively.
     */
    private function findTestFiles(string $path): array
    {
        $files = [];

        foreach (File::allFiles($path) as $file) {
            if (Str::endsWith($file->getFilename(), 'Test.php')) {
                $files[] = str_replace(
                    [base_path('tests/'), '.php'],
                    ['', ''],
                    $file->getPathname()
                );
            }
        }

        return $files;
    }

    /**
     * Count test methods in all test files.
     */
    private function countTestMethods(string $path): int
    {
        $count = 0;

        foreach (File::allFiles($path) as $file) {
            if (Str::endsWith($file->getFilename(), 'Test.php')) {
                $content = File::get($file->getPathname());
                // Count /** @test */ annotations and test* method names
                $count += preg_match_all('/@test|function\s+test\w+/', $content);
            }
        }

        return $count;
    }

    /**
     * Analyze API routes.
     */
    private function analyzeApi(): array
    {
        $apiRoutes = [];
        $routeFile = base_path('routes/api.php');

        if (File::exists($routeFile)) {
            $content = File::get($routeFile);

            // Extract routes with regex
            preg_match_all(
                "/Route::(get|post|put|patch|delete)\s*\(\s*['\"]([^'\"]+)['\"]/",
                $content,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $apiRoutes[] = [
                    'method' => strtoupper($match[1]),
                    'path' => '/api' . $match[2],
                ];
            }
        }

        return [
            'routes' => $apiRoutes,
            'total_routes' => count($apiRoutes),
        ];
    }

    /**
     * Analyze Artisan commands.
     */
    private function analyzeCommands(): array
    {
        $commands = [];
        $commandPath = app_path('Console/Commands');

        if (File::isDirectory($commandPath)) {
            foreach (File::files($commandPath) as $file) {
                $content = File::get($file->getPathname());

                // Extract command signature
                if (preg_match("/protected\s+\\\$signature\s*=\s*['\"]([^'\"]+)['\"]/", $content, $match)) {
                    $commands[] = $match[1];
                }
            }
        }

        return [
            'commands' => $commands,
            'entity_commands' => array_filter($commands, fn($c) => Str::startsWith($c, 'entity:')),
        ];
    }

    /**
     * Generate implementation status for CLAUDE.md.
     */
    public function generateImplementationStatus(): string
    {
        if (empty($this->analysis)) {
            $this->analyzeCodebase();
        }

        $backend = $this->analysis['backend'];
        $frontend = $this->analysis['frontend'];
        $docker = $this->analysis['docker'];
        $tests = $this->analysis['tests'];

        $status = "## Status: Implementiert\n\n";

        // Backend
        $status .= "### Backend (Laravel 11)\n";
        $status .= $this->checkbox($backend['has_entity_services']) . " Entity Services (";
        $status .= implode(', ', $backend['services']['Entity'] ?? []) . ")\n";
        $status .= $this->checkbox($backend['has_llm_services']) . " LLM Services mit Multi-Provider Support (";
        $status .= implode(', ', array_filter($backend['services']['LLM'] ?? [], fn($s) => Str::endsWith($s, 'Driver'))) . ")\n";
        $status .= $this->checkbox($backend['has_tool_services']) . " Tool System mit Self-Extension Capability\n";
        $status .= $this->checkbox(in_array('EntityThink', $this->getCommandClasses())) . " Think Loop mit Tool-Integration\n";
        $status .= $this->checkbox(count($this->analysis['commands']['entity_commands']) >= 4) . " Artisan Commands (" . implode(', ', $this->analysis['commands']['entity_commands']) . ")\n";
        $status .= $this->checkbox($this->analysis['api']['total_routes'] > 10) . " REST API (Entity, Chat, Mind, Memory, Goals)\n";
        $status .= $this->checkbox(count($backend['events']) > 0) . " WebSocket Events (" . implode(', ', array_slice($backend['events'], 0, 4)) . ")\n";
        $status .= $this->checkbox(count($backend['models']) > 3) . " Database Migrations & Models\n";
        $status .= $this->checkbox($tests['estimated_test_count'] > 50) . " {$tests['estimated_test_count']} Tests (Unit + Feature)\n\n";

        // Frontend
        $status .= "### Frontend (Vue.js 3)\n";
        $status .= $this->checkbox(count($frontend['stores']) > 0) . " Pinia Stores (" . implode(', ', $frontend['stores']) . ")\n";
        $status .= $this->checkbox($frontend['has_router']) . " Vue Router mit Views\n";
        $status .= $this->checkbox(File::exists(resource_path('css/app.css'))) . " TailwindCSS Setup\n";
        $status .= $this->checkbox($frontend['has_echo']) . " Laravel Echo WebSocket Integration\n";
        $status .= $this->checkbox(in_array('ThemeToggle', $frontend['components'])) . " Dark/Light Mode Support\n";
        $status .= $this->checkbox(count($frontend['views']) >= 6) . " UI Components (" . count($frontend['views']) . " Views, " . count($frontend['components']) . " Components)\n\n";

        // Docker
        $status .= "### Docker\n";
        foreach (['app', 'nginx', 'mysql', 'redis', 'reverb', 'ollama'] as $service) {
            $has = in_array($service, $docker['services']);
            $label = match($service) {
                'app' => 'PHP-FPM Container',
                'nginx' => 'Nginx Webserver',
                'mysql' => 'MySQL 8',
                'redis' => 'Redis',
                'reverb' => 'Laravel Reverb (WebSockets)',
                'ollama' => 'Ollama LLM Server (plattformübergreifend)',
                default => ucfirst($service),
            };
            $status .= $this->checkbox($has) . " {$label}\n";
        }
        $status .= $this->checkbox(count(array_filter($docker['services'], fn($s) => Str::startsWith($s, 'worker'))) > 0) . " Queue Workers (think, observe, tools, default)\n";
        $status .= $this->checkbox(in_array('scheduler', $docker['services'])) . " Scheduler\n";

        return $status;
    }

    /**
     * Get command class names.
     */
    private function getCommandClasses(): array
    {
        $commandPath = app_path('Console/Commands');
        $classes = [];

        if (File::isDirectory($commandPath)) {
            foreach (File::files($commandPath) as $file) {
                $classes[] = $file->getFilenameWithoutExtension();
            }
        }

        return $classes;
    }

    /**
     * Generate checkbox.
     */
    private function checkbox(bool $checked): string
    {
        return $checked ? '- [x]' : '- [ ]';
    }

    /**
     * Update CLAUDE.md with current status.
     */
    public function updateClaudeMd(): bool
    {
        $path = base_path('CLAUDE.md');

        if (!File::exists($path)) {
            return false;
        }

        $content = File::get($path);
        $newStatus = $this->generateImplementationStatus();

        // Replace status section
        $pattern = '/## Status: Implementiert[\s\S]*?(?=\n## (?!Status))/';

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $newStatus . "\n", $content);
        }

        File::put($path, $content);

        return true;
    }

    /**
     * Generate API documentation section.
     */
    public function generateApiDocumentation(): string
    {
        if (empty($this->analysis)) {
            $this->analyzeCodebase();
        }

        $doc = "## API Endpoints\n\n";

        // Group routes by prefix
        $groups = [];
        foreach ($this->analysis['api']['routes'] as $route) {
            $parts = explode('/', trim($route['path'], '/'));
            $prefix = $parts[2] ?? 'other'; // Skip 'api' and 'v1'
            $groups[$prefix][] = $route;
        }

        foreach ($groups as $group => $routes) {
            $doc .= "### " . ucfirst($group) . "\n```\n";
            foreach ($routes as $route) {
                $doc .= sprintf("%-6s %s\n", $route['method'], $route['path']);
            }
            $doc .= "```\n\n";
        }

        return $doc;
    }

    /**
     * Get analysis results.
     */
    public function getAnalysis(): array
    {
        if (empty($this->analysis)) {
            $this->analyzeCodebase();
        }

        return $this->analysis;
    }
}
