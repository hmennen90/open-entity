<?php

namespace Tests\Unit\Services\Tools;

use App\Services\Tools\BuiltIn\UpdateCheckTool;
use Tests\TestCase;

class UpdateCheckToolTest extends TestCase
{
    private UpdateCheckTool $updateCheckTool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->updateCheckTool = new UpdateCheckTool();
    }

    /** @test */
    public function it_has_correct_name(): void
    {
        $this->assertEquals('update_check', $this->updateCheckTool->name());
    }

    /** @test */
    public function it_has_description(): void
    {
        $description = $this->updateCheckTool->description();
        $this->assertStringContainsString('version', strtolower($description));
        $this->assertStringContainsString('OpenEntity', $description);
    }

    /** @test */
    public function it_defines_optional_parameters(): void
    {
        $params = $this->updateCheckTool->parameters();

        $this->assertEquals('object', $params['type']);
        $this->assertArrayHasKey('include_changelog', $params['properties']);
        $this->assertArrayHasKey('include_prerelease', $params['properties']);
        $this->assertEmpty($params['required']);
    }

    /** @test */
    public function it_validates_without_required_params(): void
    {
        $result = $this->updateCheckTool->validate([]);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_validates_with_all_params(): void
    {
        $result = $this->updateCheckTool->validate([
            'include_changelog' => true,
            'include_prerelease' => false,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_executes_and_returns_version_info(): void
    {
        // Skip in CI to avoid rate limiting
        if (env('CI')) {
            $this->markTestSkipped('Skipping live update check test in CI');
        }

        $result = $this->updateCheckTool->execute([]);

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['result']);
        $this->assertArrayHasKey('current_version', $result['result']);
        $this->assertArrayHasKey('update_available', $result['result']);
        $this->assertArrayHasKey('message', $result['result']);
    }

    /** @test */
    public function it_returns_current_version(): void
    {
        // Skip in CI to avoid rate limiting
        if (env('CI')) {
            $this->markTestSkipped('Skipping live update check test in CI');
        }

        $result = $this->updateCheckTool->execute(['include_changelog' => false]);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['result']['current_version']);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $result['result']['current_version']);
    }
}
