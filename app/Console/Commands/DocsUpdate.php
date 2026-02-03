<?php

namespace App\Console\Commands;

use App\Services\Documentation\DocumentationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DocsUpdate extends Command
{
    protected $signature = 'docs:update
        {--analyze : Only analyze, don\'t update files}
        {--claude : Update CLAUDE.md}
        {--readme : Update README.md}
        {--all : Update all documentation files}';

    protected $description = 'Update documentation files based on current codebase state';

    public function __construct(
        private DocumentationService $docService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Analyzing codebase...');

        $analysis = $this->docService->analyzeCodebase();

        if ($this->option('analyze')) {
            $this->displayAnalysis($analysis);
            return Command::SUCCESS;
        }

        $updateAll = $this->option('all') || (!$this->option('claude') && !$this->option('readme'));

        if ($updateAll || $this->option('claude')) {
            $this->updateClaudeMd($analysis);
        }

        if ($updateAll || $this->option('readme')) {
            $this->updateReadme($analysis);
        }

        $this->newLine();
        $this->info('Documentation updated successfully!');

        return Command::SUCCESS;
    }

    private function displayAnalysis(array $analysis): void
    {
        $this->newLine();
        $this->info('=== Codebase Analysis ===');
        $this->newLine();

        // Backend
        $this->line('<fg=cyan>Backend Services:</>');
        foreach ($analysis['backend']['services'] as $category => $services) {
            $this->line("  {$category}: " . implode(', ', $services));
        }
        $this->newLine();

        // Models
        $this->line('<fg=cyan>Models:</>');
        $this->line('  ' . implode(', ', $analysis['backend']['models']));
        $this->newLine();

        // Events
        $this->line('<fg=cyan>Events:</>');
        $this->line('  ' . implode(', ', $analysis['backend']['events']));
        $this->newLine();

        // Frontend
        $this->line('<fg=cyan>Frontend Views:</>');
        $this->line('  ' . implode(', ', $analysis['frontend']['views']));
        $this->newLine();

        $this->line('<fg=cyan>Frontend Components:</>');
        $this->line('  ' . implode(', ', $analysis['frontend']['components']));
        $this->newLine();

        $this->line('<fg=cyan>Pinia Stores:</>');
        $this->line('  ' . implode(', ', $analysis['frontend']['stores']));
        $this->newLine();

        // Docker
        $this->line('<fg=cyan>Docker Services:</>');
        $this->line('  ' . implode(', ', $analysis['docker']['services']));
        $this->newLine();

        // Tests
        $this->line('<fg=cyan>Tests:</>');
        $this->line("  Unit Tests: " . count($analysis['tests']['unit_tests']));
        $this->line("  Feature Tests: " . count($analysis['tests']['feature_tests']));
        $this->line("  Estimated Test Methods: {$analysis['tests']['estimated_test_count']}");
        $this->newLine();

        // API
        $this->line('<fg=cyan>API Routes:</>');
        $this->line("  Total Routes: {$analysis['api']['total_routes']}");
        $this->newLine();

        // Commands
        $this->line('<fg=cyan>Artisan Commands:</>');
        $this->line('  ' . implode(', ', $analysis['commands']['commands']));
    }

    private function updateClaudeMd(array $analysis): void
    {
        $this->info('Updating CLAUDE.md...');

        $path = base_path('CLAUDE.md');

        if (!File::exists($path)) {
            $this->error('CLAUDE.md not found');
            return;
        }

        $content = File::get($path);

        // Generate new status section
        $newStatus = $this->docService->generateImplementationStatus();

        // Replace status section
        $pattern = '/## Status: Implementiert[\s\S]*?(?=\n## Architektur)/';

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $newStatus . "\n", $content);
            File::put($path, $content);
            $this->line('  <fg=green>✓</> Status section updated');
        } else {
            $this->line('  <fg=yellow>!</> Could not find status section to update');
        }
    }

    private function updateReadme(array $analysis): void
    {
        $this->info('Updating README.md...');

        $path = base_path('README.md');

        if (!File::exists($path)) {
            $this->error('README.md not found');
            return;
        }

        $content = File::get($path);

        // Update test count in README
        $testCount = $analysis['tests']['estimated_test_count'];
        $content = preg_replace(
            '/PHPUnit 11 \(\d+ Tests\)/',
            "PHPUnit 11 ({$testCount} Tests)",
            $content
        );

        // Update "Alle X Tests ausführen" comment
        $content = preg_replace(
            '/# Alle \d+ Tests ausführen/',
            "# Alle {$testCount} Tests ausführen",
            $content
        );

        File::put($path, $content);
        $this->line('  <fg=green>✓</> Test count updated');
    }
}
