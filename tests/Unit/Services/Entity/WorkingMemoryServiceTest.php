<?php

namespace Tests\Unit\Services\Entity;

use App\Models\Thought;
use App\Services\Entity\WorkingMemoryService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WorkingMemoryServiceTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear cache before each test
        Cache::flush();
    }

    public function test_add_stores_item_in_working_memory(): void
    {
        $service = new WorkingMemoryService();

        $service->add('Test item', 0.8, 'test_category');

        $items = $service->getItems();

        $this->assertCount(1, $items);
        $this->assertEquals('Test item', $items[0]['content']);
        $this->assertEquals(0.8, $items[0]['importance']);
        $this->assertEquals('test_category', $items[0]['category']);
    }

    public function test_add_keeps_most_recent_first(): void
    {
        $service = new WorkingMemoryService();

        $service->add('First item', 0.5);
        $service->add('Second item', 0.5);

        $items = $service->getItems();

        $this->assertEquals('Second item', $items[0]['content']);
        $this->assertEquals('First item', $items[1]['content']);
    }

    public function test_add_enforces_max_items_limit(): void
    {
        config(['entity.memory.layers.working.max_items' => 3]);

        $service = new WorkingMemoryService();

        for ($i = 1; $i <= 5; $i++) {
            $service->add("Item {$i}", 0.5);
        }

        $items = $service->getItems();

        // Should keep only 3 items
        $this->assertCount(3, $items);
    }

    public function test_add_prioritizes_important_items(): void
    {
        config(['entity.memory.layers.working.max_items' => 3]);

        $service = new WorkingMemoryService();

        $service->add('Low importance', 0.1);
        $service->add('High importance', 0.9);
        $service->add('Medium importance', 0.5);
        $service->add('Another low', 0.2);

        $items = $service->getItems();

        // Should keep high and medium importance items
        $importances = array_column($items, 'importance');
        $this->assertContains(0.9, $importances);
        $this->assertContains(0.5, $importances);
    }

    public function test_set_and_get_conversation_context(): void
    {
        $service = new WorkingMemoryService();

        $context = [
            'participant' => 'Test User',
            'recent_messages' => ['Hello', 'How are you?'],
        ];

        $service->setConversationContext(123, $context);

        $retrieved = $service->getConversationContext(123);

        $this->assertEquals($context, $retrieved);
    }

    public function test_clear_conversation_context(): void
    {
        $service = new WorkingMemoryService();

        $service->setConversationContext(123, ['test' => 'data']);
        $service->clearConversationContext(123);

        $this->assertNull($service->getConversationContext(123));
    }

    public function test_clear_removes_all_items(): void
    {
        $service = new WorkingMemoryService();

        $service->add('Item 1', 0.5);
        $service->add('Item 2', 0.5);

        $service->clear();

        $this->assertEmpty($service->getItems());
    }

    public function test_get_working_memory_includes_items_and_thoughts(): void
    {
        // Create recent thoughts
        Thought::factory()->create([
            'content' => 'Recent thought',
            'created_at' => now()->subMinutes(10),
        ]);

        $service = new WorkingMemoryService();
        $service->add('Working memory item', 0.5);

        $memory = $service->getWorkingMemory();

        $this->assertArrayHasKey('items', $memory);
        $this->assertArrayHasKey('recent_thoughts', $memory);
        $this->assertCount(1, $memory['items']);
    }

    public function test_get_recent_thoughts_filters_by_time(): void
    {
        // Create thoughts at different times
        Thought::factory()->create([
            'content' => 'Recent thought',
            'created_at' => now()->subMinutes(10),
        ]);

        Thought::factory()->create([
            'content' => 'Old thought',
            'created_at' => now()->subHours(2),
        ]);

        $service = new WorkingMemoryService();

        $recent = $service->getRecentThoughts(30);

        $this->assertCount(1, $recent);
        $this->assertEquals('Recent thought', $recent->first()->content);
    }

    public function test_to_prompt_context_formats_correctly(): void
    {
        Thought::factory()->create([
            'content' => 'A recent thought about testing',
            'created_at' => now()->subMinutes(5),
        ]);

        $service = new WorkingMemoryService();
        $service->add('Current focus item', 0.8, 'focus');

        $context = $service->toPromptContext('en');

        $this->assertStringContainsString('Current context', $context);
        $this->assertStringContainsString('Current focus item', $context);
        $this->assertStringContainsString('Recent thoughts', $context);
    }

    public function test_to_prompt_context_supports_german(): void
    {
        $service = new WorkingMemoryService();
        $service->add('Test item', 0.5);

        $context = $service->toPromptContext('de');

        $this->assertStringContainsString('Aktueller Kontext', $context);
    }

    public function test_get_current_focus_returns_most_important(): void
    {
        $service = new WorkingMemoryService();

        $service->add('Low importance', 0.2);
        $service->add('High importance', 0.9);
        $service->add('Medium importance', 0.5);

        $focus = $service->getCurrentFocus(2);

        $this->assertCount(2, $focus);
        $this->assertEquals(0.9, $focus[0]['importance']);
        $this->assertEquals(0.5, $focus[1]['importance']);
    }

    public function test_has_topic_returns_true_when_topic_exists(): void
    {
        $service = new WorkingMemoryService();

        $service->add('Thinking about Laravel development', 0.5);

        $this->assertTrue($service->hasTopic('Laravel'));
        $this->assertTrue($service->hasTopic('laravel')); // Case insensitive
        $this->assertFalse($service->hasTopic('Python'));
    }

    public function test_empty_working_memory_returns_empty_context(): void
    {
        // Ensure no recent thoughts
        Thought::query()->delete();

        $service = new WorkingMemoryService();

        $context = $service->toPromptContext('en');

        $this->assertEquals('', $context);
    }
}
