<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Prompt;
use App\Services\PromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class PromptServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private PromptService $promptService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->promptService = new PromptService();
    }

    /** @test */
    public function it_finds_exact_match_prompts_with_highest_priority()
    {
        // Create prompts with different priorities
        $highPriorityPrompt = Prompt::factory()->create([
            'trigger_phrase' => 'refrigerator repair',
            'prompt_content' => 'High priority repair response',
            'priority' => 10,
            'is_active' => true
        ]);

        $lowPriorityPrompt = Prompt::factory()->create([
            'trigger_phrase' => 'refrigerator repair',
            'prompt_content' => 'Low priority repair response',
            'priority' => 5,
            'is_active' => true
        ]);

        $result = $this->promptService->findMatchingPrompt('My refrigerator repair is needed');

        $this->assertNotNull($result);
        $this->assertEquals($highPriorityPrompt->prompt_content, $result['content']);
        $this->assertEquals($highPriorityPrompt->id, $result['id']);
        
        // Verify usage count was incremented
        $this->assertEquals(1, $highPriorityPrompt->fresh()->usage_count);
        $this->assertEquals(0, $lowPriorityPrompt->fresh()->usage_count);
    }

    /** @test */
    public function it_calculates_match_scores_correctly_for_exact_phrase_matches()
    {
        $prompt = Prompt::factory()->create([
            'trigger_phrase' => 'freezer temperature',
            'prompt_content' => 'Freezer temperature guidance',
            'priority' => 5,
            'is_active' => true
        ]);

        $result = $this->promptService->findMatchingPrompt('What is the ideal freezer temperature?');

        $this->assertNotNull($result);
        $this->assertEquals($prompt->prompt_content, $result['content']);
    }

    /** @test */
    public function it_handles_multiple_trigger_phrases_separated_by_commas()
    {
        $prompt = Prompt::factory()->create([
            'trigger_phrase' => 'refrigerator not cooling, fridge warm, cooling issues',
            'prompt_content' => 'Cooling troubleshooting guide',
            'priority' => 8,
            'is_active' => true
        ]);

        // Test each trigger phrase
        $testCases = [
            'My refrigerator not cooling properly',
            'The fridge warm today',
            'Having cooling issues with appliance'
        ];

        foreach ($testCases as $userInput) {
            $result = $this->promptService->findMatchingPrompt($userInput);
            $this->assertNotNull($result, "Failed for input: {$userInput}");
            $this->assertEquals($prompt->prompt_content, $result['content']);
        }
    }

    /** @test */
    public function it_ignores_inactive_prompts()
    {
        $activePrompt = Prompt::factory()->create([
            'trigger_phrase' => 'warranty information',
            'prompt_content' => 'Active warranty response',
            'priority' => 5,
            'is_active' => true
        ]);

        $inactivePrompt = Prompt::factory()->create([
            'trigger_phrase' => 'warranty information',
            'prompt_content' => 'Inactive warranty response',
            'priority' => 10, // Higher priority but inactive
            'is_active' => false
        ]);

        $result = $this->promptService->findMatchingPrompt('I need warranty information');

        $this->assertNotNull($result);
        $this->assertEquals($activePrompt->prompt_content, $result['content']);
        $this->assertEquals($activePrompt->id, $result['id']);
    }

    /** @test */
    public function it_returns_null_when_no_prompts_meet_minimum_score_threshold()
    {
        Prompt::factory()->create([
            'trigger_phrase' => 'very specific technical term',
            'prompt_content' => 'Technical response',
            'priority' => 5,
            'is_active' => true
        ]);

        // Test with completely unrelated input
        $result = $this->promptService->findMatchingPrompt('hello world');

        $this->assertNull($result);
    }

    /** @test */
    public function it_handles_case_insensitive_matching()
    {
        $prompt = Prompt::factory()->create([
            'trigger_phrase' => 'ENERGY EFFICIENCY',
            'prompt_content' => 'Energy efficiency information',
            'priority' => 5,
            'is_active' => true
        ]);

        $testCases = [
            'energy efficiency questions',
            'ENERGY EFFICIENCY help',
            'Energy Efficiency tips',
            'energy EFFICIENCY guide'
        ];

        foreach ($testCases as $userInput) {
            $result = $this->promptService->findMatchingPrompt($userInput);
            $this->assertNotNull($result, "Failed for case variation: {$userInput}");
            $this->assertEquals($prompt->prompt_content, $result['content']);
        }
    }

    /** @test */
    public function it_increments_usage_count_only_for_returned_prompts()
    {
        $matchedPrompt = Prompt::factory()->create([
            'trigger_phrase' => 'door seal replacement',
            'prompt_content' => 'Door seal guide',
            'priority' => 5,
            'is_active' => true,
            'usage_count' => 5
        ]);

        $unmatchedPrompt = Prompt::factory()->create([
            'trigger_phrase' => 'completely different topic',
            'prompt_content' => 'Different response',
            'priority' => 8,
            'is_active' => true,
            'usage_count' => 2
        ]);

        $result = $this->promptService->findMatchingPrompt('door seal replacement needed');

        $this->assertNotNull($result);
        
        // Check usage counts
        $this->assertEquals(6, $matchedPrompt->fresh()->usage_count);
        $this->assertEquals(2, $unmatchedPrompt->fresh()->usage_count);
    }
}
