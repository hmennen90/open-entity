<?php

namespace App\Services\Tools\BuiltIn;

use App\Services\Tools\Contracts\ToolInterface;
use App\Services\Documentation\DocumentationService;
use Illuminate\Support\Facades\Artisan;

/**
 * Tool for analyzing and updating project documentation.
 *
 * Allows the entity to keep documentation (CLAUDE.md, README.md)
 * automatically up to date.
 */
class DocumentationTool implements ToolInterface
{
    private DocumentationService $docService;

    public function __construct()
    {
        $this->docService = app(DocumentationService::class);
    }

    public function name(): string
    {
        return 'documentation';
    }

    public function description(): string
    {
        return 'Analyze and update project documentation (CLAUDE.md, README.md). ' .
               'Use this tool to keep documentation in sync with the codebase. ' .
               'Actions: "analyze" (view codebase stats), "update_claude" (update CLAUDE.md), ' .
               '"update_readme" (update README.md), "update_all" (update everything).';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'description' => 'The action to perform',
                    'enum' => ['analyze', 'update_claude', 'update_readme', 'update_all'],
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function validate(array $params): array
    {
        $errors = [];

        if (!isset($params['action'])) {
            $errors[] = 'Parameter "action" is required';
        } elseif (!in_array($params['action'], ['analyze', 'update_claude', 'update_readme', 'update_all'])) {
            $errors[] = 'Invalid action. Must be one of: analyze, update_claude, update_readme, update_all';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function execute(array $params): array
    {
        $validation = $this->validate($params);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'result' => null,
                'error' => implode('; ', $validation['errors']),
            ];
        }

        $action = $params['action'];

        try {
            $result = match ($action) {
                'analyze' => $this->analyze(),
                'update_claude' => $this->updateClaude(),
                'update_readme' => $this->updateReadme(),
                'update_all' => $this->updateAll(),
            };

            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'result' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function analyze(): array
    {
        $analysis = $this->docService->analyzeCodebase();

        $summary = [
            'backend' => [
                'services' => count($analysis['backend']['services']),
                'models' => count($analysis['backend']['models']),
                'events' => count($analysis['backend']['events']),
                'has_entity_services' => $analysis['backend']['has_entity_services'],
                'has_llm_services' => $analysis['backend']['has_llm_services'],
                'has_tool_services' => $analysis['backend']['has_tool_services'],
            ],
            'frontend' => [
                'views' => count($analysis['frontend']['views']),
                'components' => count($analysis['frontend']['components']),
                'stores' => count($analysis['frontend']['stores']),
                'has_router' => $analysis['frontend']['has_router'],
                'has_websocket' => $analysis['frontend']['has_echo'],
            ],
            'docker' => [
                'services' => $analysis['docker']['services'],
                'has_compose' => $analysis['docker']['has_compose'],
            ],
            'tests' => [
                'unit_test_files' => count($analysis['tests']['unit_tests']),
                'feature_test_files' => count($analysis['tests']['feature_tests']),
                'estimated_test_count' => $analysis['tests']['estimated_test_count'],
            ],
            'api' => [
                'total_routes' => $analysis['api']['total_routes'],
            ],
            'commands' => [
                'total' => count($analysis['commands']['commands']),
                'entity_commands' => $analysis['commands']['entity_commands'],
            ],
        ];

        return [
            'success' => true,
            'result' => [
                'message' => 'Codebase analysis complete',
                'summary' => $summary,
                'timestamp' => $analysis['timestamp'],
            ],
            'error' => null,
        ];
    }

    private function updateClaude(): array
    {
        $exitCode = Artisan::call('docs:update', ['--claude' => true]);
        $output = trim(Artisan::output());

        if ($exitCode === 0) {
            return [
                'success' => true,
                'result' => [
                    'message' => 'CLAUDE.md has been updated with current implementation status',
                    'output' => $output,
                ],
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'result' => null,
            'error' => 'Failed to update CLAUDE.md: ' . $output,
        ];
    }

    private function updateReadme(): array
    {
        $exitCode = Artisan::call('docs:update', ['--readme' => true]);
        $output = trim(Artisan::output());

        if ($exitCode === 0) {
            return [
                'success' => true,
                'result' => [
                    'message' => 'README.md has been updated',
                    'output' => $output,
                ],
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'result' => null,
            'error' => 'Failed to update README.md: ' . $output,
        ];
    }

    private function updateAll(): array
    {
        $exitCode = Artisan::call('docs:update', ['--all' => true]);
        $output = trim(Artisan::output());

        if ($exitCode === 0) {
            return [
                'success' => true,
                'result' => [
                    'message' => 'All documentation files have been updated',
                    'output' => $output,
                ],
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'result' => null,
            'error' => 'Failed to update documentation: ' . $output,
        ];
    }
}
