<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Entity Storage für Tests vorbereiten
        $this->prepareEntityStorage();
    }

    protected function prepareEntityStorage(): void
    {
        $basePath = storage_path('entity-test');

        $directories = [
            $basePath,
            $basePath . '/mind',
            $basePath . '/mind/reflections',
            $basePath . '/memory',
            $basePath . '/memory/conversations',
            $basePath . '/memory/learned',
            $basePath . '/social',
            $basePath . '/social/interactions',
            $basePath . '/goals',
            $basePath . '/tools',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Test-Konfiguration setzen
        config(['entity.storage_path' => $basePath]);
    }

    protected function tearDown(): void
    {
        // Test-Storage aufräumen
        $this->cleanupEntityStorage();

        parent::tearDown();
    }

    protected function cleanupEntityStorage(): void
    {
        $basePath = storage_path('entity-test');

        if (is_dir($basePath)) {
            $this->recursiveDelete($basePath);
        }
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }

        rmdir($dir);
    }
}
