<?php

namespace Tests\Unit\Services\Tools;

use App\Services\Tools\BuiltIn\SearchTool;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SearchToolTest extends TestCase
{
    private SearchTool $searchTool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->searchTool = new SearchTool();
    }

    #[Test]
    public function it_has_correct_name(): void
    {
        $this->assertEquals('search', $this->searchTool->name());
    }

    #[Test]
    public function it_has_description(): void
    {
        $description = $this->searchTool->description();
        $this->assertStringContainsString('search', strtolower($description));
        $this->assertStringContainsString('DuckDuckGo', $description);
    }

    #[Test]
    public function it_defines_required_parameters(): void
    {
        $params = $this->searchTool->parameters();

        $this->assertEquals('object', $params['type']);
        $this->assertArrayHasKey('query', $params['properties']);
        $this->assertContains('query', $params['required']);
    }

    #[Test]
    public function it_validates_empty_query(): void
    {
        $result = $this->searchTool->validate(['query' => '']);

        $this->assertFalse($result['valid']);
        $this->assertContains('query is required', $result['errors']);
    }

    #[Test]
    public function it_validates_short_query(): void
    {
        $result = $this->searchTool->validate(['query' => 'a']);

        $this->assertFalse($result['valid']);
        $this->assertContains('query must be at least 2 characters', $result['errors']);
    }

    #[Test]
    public function it_validates_long_query(): void
    {
        $result = $this->searchTool->validate(['query' => str_repeat('a', 501)]);

        $this->assertFalse($result['valid']);
        $this->assertContains('query must not exceed 500 characters', $result['errors']);
    }

    #[Test]
    public function it_validates_max_results_range(): void
    {
        $result = $this->searchTool->validate(['query' => 'test', 'max_results' => 15]);

        $this->assertFalse($result['valid']);
        $this->assertContains('max_results must be between 1 and 10', $result['errors']);
    }

    #[Test]
    public function it_defines_fetch_pages_parameter(): void
    {
        $params = $this->searchTool->parameters();

        $this->assertArrayHasKey('fetch_pages', $params['properties']);
        $this->assertEquals('boolean', $params['properties']['fetch_pages']['type']);
    }

    #[Test]
    public function it_validates_max_results_with_fetch_pages(): void
    {
        $result = $this->searchTool->validate([
            'query' => 'test',
            'max_results' => 8,
            'fetch_pages' => true,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains('max_results cannot exceed 5 when fetch_pages is enabled', $result['errors']);
    }

    #[Test]
    public function it_accepts_valid_fetch_pages_parameters(): void
    {
        $result = $this->searchTool->validate([
            'query' => 'test query',
            'max_results' => 3,
            'fetch_pages' => true,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function it_accepts_valid_parameters(): void
    {
        $result = $this->searchTool->validate([
            'query' => 'Laravel PHP framework',
            'max_results' => 5,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function it_executes_search_and_returns_results(): void
    {
        // Skip in CI to avoid rate limiting
        if (env('CI')) {
            $this->markTestSkipped('Skipping live search test in CI');
        }

        $result = $this->searchTool->execute([
            'query' => 'PHP programming language',
            'max_results' => 3,
        ]);

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['result']);
        $this->assertEquals('PHP programming language', $result['result']['query']);
        $this->assertIsArray($result['result']['results']);
        $this->assertArrayHasKey('pages_fetched', $result['result']);
        $this->assertFalse($result['result']['pages_fetched']);
    }
}
