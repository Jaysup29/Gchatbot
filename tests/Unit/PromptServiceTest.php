<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Prompt;
use App\Services\PromptService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    public function it_scores_word_matches_appropriately()
    {
        $prompt = Prompt::factory()->create([
            'trigger_phrase' => 'ice maker problems',
            'prompt_content' => 'Ice maker troubleshooting',
            'priority' => 7,
            'is_active' => true
        ]);

        // Test partial word matches
        $result = $this->promptService->findMatchingPrompt('My ice machine has problems');
        $this->assertNotNull($result);

        // Test exact word matches (should score higher)
        $result = $this->promptService->findMatchingPrompt('ice maker problems occurring');
        $this->assertNotNull($result);
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
    public function it_returns_null_when_score_is_below_threshold()
    {
        Prompt::factory()->create([
            'trigger_phrase' => 'refrigerator',
            'prompt_content' => 'Basic refrigerator info',
            'priority' => 5,
            'is_active' => true
        ]);

        // Input that might have minimal match but below threshold
        $result = $this->promptService->findMatchingPrompt('ref');

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
    public function it_trims_whitespace_from_user_input()
    {
        $prompt = Prompt::factory()->create([
            'trigger_phrase' => 'filter replacement',
            'prompt_content' => 'Filter replacement guide',
            'priority' => 5,
            'is_active' => true
        ]);

        $result = $this->promptService->findMatchingPrompt('   filter replacement needed   ');

        $this->assertNotNull($result);
        $this->assertEquals($prompt->prompt_content, $result['content']);
    }

    /** @test */
    public function it_handles_empty_or_whitespace_only_input()
    {
        Prompt::factory()->create([
            'trigger_phrase' => 'help',
            'prompt_content' => 'Help information',
            'priority' => 5,
            'is_active' => true
        ]);

        $testCases = ['', '   ', "\t\n", null];

        foreach ($testCases as $input) {
            $result = $this->promptService->findMatchingPrompt($input ?? '');
            $this->assertNull($result, "Should return null for empty input: " . var_export($input, true));
        }
    }

    /** @test */
    public function it_prioritizes_higher_scoring_prompts_over_priority_when_scores_are_different()
    {
        // Lower priority but exact match
        $exactMatchPrompt = Prompt::factory()->create([
            'trigger_phrase' => 'water filter replacement guide',
            'prompt_content' => 'Exact match response',
            'priority' => 3,
            'is_active' => true
        ]);

        // Higher priority but partial match
        $partialMatchPrompt = Prompt::factory()->create([
            'trigger_phrase' => 'filter',
            'prompt_content' => 'Partial match response',
            'priority' => 10,
            'is_active' => true
        ]);

        $result = $this->promptService->findMatchingPrompt('I need water filter replacement guide');

        // Should return the exact match despite lower priority
        $this->assertNotNull($result);
        $this->assertEquals($exactMatchPrompt->prompt_content, $result['content']);
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

    /** @test */
    public function it_handles_special_characters_and_punctuation()
    {
        $prompt = Prompt::factory()->create([
            'trigger_phrase' => 'model number lookup',
            'prompt_content' => 'Model number information',
            'priority' => 5,
            'is_active' => true
        ]);

        $testCases = [
            'Where can I find my model number?',
            'Model number lookup please!',
            'Need help with model-number lookup',
            'model number; lookup required'
        ];

        foreach ($testCases as $userInput) {
            $result = $this->promptService->findMatchingPrompt($userInput);
            $this->assertNotNull($result, "Failed for input with special chars: {$userInput}");
        }
    }

    /** @test */
    public function it_calculates_correct_score_for_complex_matching_scenario()
    {
        $prompt = Prompt::factory()->create([
            'trigger_phrase' => 'refrigerator temperature control, thermostat adjustment',
            'prompt_content' => 'Temperature control guide',
            'priority' => 5,
            'is_active' => true
        ]);

        // This should match both trigger phrases partially
        $result = $this->promptService->findMatchingPrompt('How to adjust refrigerator temperature and thermostat settings?');

        $this->assertNotNull($result);
        $this->assertEquals($prompt->prompt_content, $result['content']);
        $this->assertEquals(1, $prompt->fresh()->usage_count);
    }
}