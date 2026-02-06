<?php

namespace Tests\Unit\Services\Tools;

use App\Services\Tools\BuiltIn\UpdateCheckTool;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateCheckToolTest extends TestCase
{
    private UpdateCheckTool $updateCheckTool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->updateCheckTool = new UpdateCheckTool();
    }

    #[Test]
    public function it_has_correct_name(): void
    {
        $this->assertEquals('update_check', $this->updateCheckTool->name());
    }

    #[Test]
    public function it_has_description(): void
    {
        $description = $this->updateCheckTool->description();
        $this->assertStringContainsString('version', strtolower($description));
        $this->assertStringContainsString('OpenEntity', $description);
    }

    #[Test]
    public function it_defines_optional_parameters(): void
    {
        $params = $this->updateCheckTool->parameters();

        $this->assertEquals('object', $params['type']);
        $this->assertArrayHasKey('include_changelog', $params['properties']);
        $this->assertArrayHasKey('include_prerelease', $params['properties']);
        $this->assertEmpty($params['required']);
    }

    #[Test]
    public function it_validates_without_required_params(): void
    {
        $result = $this->updateCheckTool->validate([]);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function it_validates_with_all_params(): void
    {
        $result = $this->updateCheckTool->validate([
            'include_changelog' => true,
            'include_prerelease' => false,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function it_executes_and_returns_version_info(): void
    {
        $result = $this->updateCheckTool->execute([]);

        // If network is unavailable, the tool returns failure - that's acceptable
        if (!$result['success']) {
            $this->assertArrayHasKey('error', $result);
            $this->markTestSkipped('GitHub API not reachable - skipping live test');
        }

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['result']);
        $this->assertArrayHasKey('current_version', $result['result']);
        $this->assertArrayHasKey('update_available', $result['result']);
        $this->assertArrayHasKey('message', $result['result']);
    }

    #[Test]
    public function it_returns_current_version(): void
    {
        $result = $this->updateCheckTool->execute(['include_changelog' => false]);

        // If network is unavailable, the tool returns failure - that's acceptable
        if (!$result['success']) {
            $this->assertArrayHasKey('error', $result);
            $this->markTestSkipped('GitHub API not reachable - skipping live test');
        }

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['result']['current_version']);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $result['result']['current_version']);
    }
}
