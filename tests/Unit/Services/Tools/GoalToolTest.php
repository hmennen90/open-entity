<?php

namespace Tests\Unit\Services\Tools;

use App\Models\Goal;
use App\Services\Tools\BuiltIn\GoalTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoalToolTest extends TestCase
{
    use RefreshDatabase;

    private GoalTool $goalTool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->goalTool = new GoalTool();
    }

    #[Test]
    public function it_has_correct_name(): void
    {
        $this->assertEquals('goal', $this->goalTool->name());
    }

    #[Test]
    public function it_has_description(): void
    {
        $description = $this->goalTool->description();
        $this->assertStringContainsString('goal', strtolower($description));
        $this->assertStringContainsString('duplicate', strtolower($description));
    }

    #[Test]
    public function it_defines_correct_parameters(): void
    {
        $params = $this->goalTool->parameters();

        $this->assertEquals('object', $params['type']);
        $this->assertArrayHasKey('action', $params['properties']);
        $this->assertContains('create', $params['properties']['action']['enum']);
        $this->assertContains('update_progress', $params['properties']['action']['enum']);
        $this->assertContains('find_similar', $params['properties']['action']['enum']);
        $this->assertContains('complete', $params['properties']['action']['enum']);
        $this->assertContains('abandon', $params['properties']['action']['enum']);
        $this->assertContains('list', $params['properties']['action']['enum']);
        $this->assertEquals(['action'], $params['required']);
    }

    #[Test]
    public function it_creates_a_goal(): void
    {
        $result = $this->goalTool->execute([
            'action' => 'create',
            'title' => 'Learn PHP',
            'description' => 'Master PHP development',
            'motivation' => 'To build better applications',
            'type' => 'learning',
            'priority' => 0.8,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Learn PHP', $result['goal']['title']);
        $this->assertEquals(0, $result['goal']['progress']);
        $this->assertEquals('active', $result['goal']['status']);

        $this->assertDatabaseHas('goals', [
            'title' => 'Learn PHP',
            'type' => 'learning',
        ]);
    }

    #[Test]
    public function it_requires_title_for_create(): void
    {
        $result = $this->goalTool->execute([
            'action' => 'create',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Title is required', $result['error']);
    }

    #[Test]
    public function it_detects_similar_goals_on_create(): void
    {
        // Create first goal
        Goal::factory()->create([
            'title' => 'Learn PHP Programming',
            'status' => 'active',
            'progress' => 50,
        ]);

        // Try to create similar goal
        $result = $this->goalTool->execute([
            'action' => 'create',
            'title' => 'Learn PHP',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('similar_goals_exist', $result['reason']);
        $this->assertNotEmpty($result['similar_goals']);
        $this->assertArrayHasKey('suggestions', $result);
    }

    #[Test]
    public function it_creates_goal_with_force_create_even_if_similar_exists(): void
    {
        // Create first goal
        Goal::factory()->create([
            'title' => 'Learn PHP Programming',
            'status' => 'active',
            'progress' => 50,
        ]);

        // Force create similar goal
        $result = $this->goalTool->execute([
            'action' => 'create',
            'title' => 'Learn PHP',
            'force_create' => true,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Learn PHP', $result['goal']['title']);
    }

    #[Test]
    public function it_updates_goal_progress(): void
    {
        $goal = Goal::factory()->create([
            'title' => 'Test Goal',
            'status' => 'active',
            'progress' => 0,
        ]);

        $result = $this->goalTool->execute([
            'action' => 'update_progress',
            'goal_id' => $goal->id,
            'progress' => 50,
            'progress_note' => 'Halfway there!',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(50, $result['goal']['progress']);
        $this->assertStringContainsString('50%', $result['message']);

        $goal->refresh();
        $this->assertEquals(50, $goal->progress);
    }

    #[Test]
    public function it_auto_completes_goal_at_100_progress(): void
    {
        $goal = Goal::factory()->create([
            'title' => 'Test Goal',
            'status' => 'active',
            'progress' => 90,
        ]);

        $result = $this->goalTool->execute([
            'action' => 'update_progress',
            'goal_id' => $goal->id,
            'progress' => 100,
            'progress_note' => 'Done!',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('completed', $result['goal']['status']);
        $this->assertStringContainsString('completed', strtolower($result['message']));

        $goal->refresh();
        $this->assertEquals('completed', $goal->status);
        $this->assertNotNull($goal->completed_at);
    }

    #[Test]
    public function it_finds_similar_goals(): void
    {
        Goal::factory()->create(['title' => 'Learn PHP', 'status' => 'active', 'progress' => 50]);
        Goal::factory()->create(['title' => 'Learn JavaScript', 'status' => 'active', 'progress' => 30]);
        Goal::factory()->create(['title' => 'Master PHP', 'status' => 'active', 'progress' => 20]);

        $result = $this->goalTool->execute([
            'action' => 'find_similar',
            'title' => 'Learn PHP Programming',
        ]);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(1, $result['count']);
    }

    #[Test]
    public function it_completes_a_goal(): void
    {
        $goal = Goal::factory()->create([
            'title' => 'Test Goal',
            'status' => 'active',
        ]);

        $result = $this->goalTool->execute([
            'action' => 'complete',
            'goal_id' => $goal->id,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('completed', $result['goal']['status']);
        $this->assertEquals(100, $result['goal']['progress']);

        $goal->refresh();
        $this->assertEquals('completed', $goal->status);
        $this->assertNotNull($goal->completed_at);
    }

    #[Test]
    public function it_abandons_a_goal_with_reason(): void
    {
        $goal = Goal::factory()->create([
            'title' => 'Test Goal',
            'status' => 'active',
        ]);

        $result = $this->goalTool->execute([
            'action' => 'abandon',
            'goal_id' => $goal->id,
            'reason' => 'No longer interested',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('abandoned', $result['goal']['status']);
        $this->assertEquals('No longer interested', $result['goal']['abandoned_reason']);
    }

    #[Test]
    public function it_lists_goals(): void
    {
        Goal::factory()->count(3)->create(['status' => 'active', 'progress' => 50]);
        Goal::factory()->count(2)->create(['status' => 'completed', 'progress' => 100]);

        $result = $this->goalTool->execute([
            'action' => 'list',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['count']);
    }

    #[Test]
    public function it_lists_goals_filtered_by_status(): void
    {
        Goal::factory()->count(3)->create(['status' => 'active', 'progress' => 50]);
        Goal::factory()->count(2)->create(['status' => 'completed', 'progress' => 100]);

        $result = $this->goalTool->execute([
            'action' => 'list',
            'status' => 'active',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['count']);
    }

    #[Test]
    public function it_gets_a_specific_goal(): void
    {
        $goal = Goal::factory()->create(['title' => 'Specific Goal']);

        $result = $this->goalTool->execute([
            'action' => 'get',
            'goal_id' => $goal->id,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Specific Goal', $result['goal']['title']);
    }

    #[Test]
    public function it_returns_error_for_invalid_action(): void
    {
        $result = $this->goalTool->execute([
            'action' => 'invalid_action',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid action', $result['error']);
    }

    #[Test]
    public function it_returns_error_for_nonexistent_goal(): void
    {
        $result = $this->goalTool->execute([
            'action' => 'update_progress',
            'goal_id' => 99999,
            'progress' => 50,
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['error']);
    }

    #[Test]
    public function it_only_checks_active_and_paused_goals_for_similarity(): void
    {
        // Create a completed goal
        Goal::factory()->create([
            'title' => 'Learn PHP Programming',
            'status' => 'completed',
        ]);

        // Should be able to create similar goal since the existing one is completed
        $result = $this->goalTool->execute([
            'action' => 'create',
            'title' => 'Learn PHP',
        ]);

        $this->assertTrue($result['success']);
    }

    #[Test]
    public function it_clamps_priority_between_0_and_1(): void
    {
        $result = $this->goalTool->execute([
            'action' => 'create',
            'title' => 'High Priority Goal',
            'priority' => 1.5, // Should be clamped to 1.0
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(1.0, $result['goal']['priority']);

        $result = $this->goalTool->execute([
            'action' => 'create',
            'title' => 'Low Priority Goal',
            'priority' => -0.5, // Should be clamped to 0.0
            'force_create' => true,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(0.0, $result['goal']['priority']);
    }

    #[Test]
    public function it_clamps_progress_between_0_and_100(): void
    {
        $goal = Goal::factory()->create();

        $result = $this->goalTool->execute([
            'action' => 'update_progress',
            'goal_id' => $goal->id,
            'progress' => 150, // Should be clamped to 100
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(100, $result['goal']['progress']);
    }

    #[Test]
    public function it_adds_progress_notes_on_create(): void
    {
        $result = $this->goalTool->execute([
            'action' => 'create',
            'title' => 'New Goal',
        ]);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['goal']['progress_notes']);
        $this->assertEquals('Goal created', $result['goal']['progress_notes'][0]['note']);
    }
}
