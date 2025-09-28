<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Prompt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\AdvancedPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdvancedScoringTest extends TestCase
{
    use RefreshDatabase;

    private AdvancedPromptService $advancedService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Properly handle error handlers
        while (set_error_handler(function() {})) {
            restore_error_handler();
        }
        while (set_exception_handler(function() {})) {
            restore_exception_handler();
        }
        
        $this->advancedService = new AdvancedPromptService();
        $this->createPromptsTable();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('prompts');
        parent::tearDown();
    }

    private function createPromptsTable()
    {
        DB::statement('
            CREATE TABLE prompts (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                trigger_phrase text NOT NULL,
                prompt_content text NOT NULL,
                prompt_type varchar(255) NOT NULL DEFAULT "response",
                priority int NOT NULL DEFAULT 5,
                is_active tinyint(1) NOT NULL DEFAULT 1,
                metadata json DEFAULT NULL,
                usage_count int NOT NULL DEFAULT 0,
                created_at timestamp NULL DEFAULT NULL,
                updated_at timestamp NULL DEFAULT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    /** @test */
    public function exact_phrase_matching_gets_highest_confidence()
    {
        $this->createTestPrompt('refrigerator repair', 'Refrigerator repair instructions', 8);

        $result = $this->advancedService->findBestMatchingPrompt('I need refrigerator repair help');

        $this->assertNotNull($result);
        $this->assertGreaterThanOrEqual(0.9, $result['confidence']); // 90%+ confidence
        $this->assertEquals('excellent', $result['match_quality']);
        $this->assertGreaterThan(0, $result['score_breakdown']['exact_phrase_matches']);
    }

    /** @test */
    public function synonym_matching_works_correctly()
    {
        $this->createTestPrompt('refrigerator repair', 'Refrigerator repair guide', 7);

        // Test synonyms: fridge = refrigerator, fix = repair
        $result = $this->advancedService->findBestMatchingPrompt('I need to fix my fridge');

        $this->assertNotNull($result);
        $this->assertGreaterThanOrEqual(0.6, $result['confidence']);
        $this->assertGreaterThan(0, $result['score_breakdown']['synonym_matches']);
    }

    /** @test */
    public function fuzzy_matching_handles_typos()
    {
        $this->createTestPrompt('refrigerator temperature', 'Temperature control guide', 6);

        // Test with typos: refridgerator, temperatur
        $result = $this->advancedService->findBestMatchingPrompt('refridgerator temperatur problems');

        $this->assertNotNull($result);
        $this->assertGreaterThanOrEqual(0.6, $result['confidence']);
        $this->assertGreaterThan(0, $result['score_breakdown']['fuzzy_matches']);
    }

    /** @test */
    public function stem_matching_handles_word_variations()
    {
        $this->createTestPrompt('cooling system', 'Cooling system maintenance', 7);

        // Test plural/singular and verb forms: cool, cooled, cooling
        $result1 = $this->advancedService->findBestMatchingPrompt('my cool system needs help');
        $result2 = $this->advancedService->findBestMatchingPrompt('cooled systems not working');

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);
        $this->assertGreaterThan(0, $result1['score_breakdown']['stem_matches']);
        $this->assertGreaterThan(0, $result2['score_breakdown']['stem_matches']);
    }

    /** @test */
    public function confidence_threshold_filters_poor_matches()
    {
        $this->createTestPrompt('ice maker repair', 'Ice maker repair steps', 5);

        // Completely unrelated query
        $result = $this->advancedService->findBestMatchingPrompt('how to cook pasta');

        $this->assertNull($result); // Should return null for poor matches
    }

    /** @test */
    public function priority_weighting_affects_final_scores()
    {
        // Create two similar prompts with different priorities
        $this->createTestPrompt('temperature control', 'Low priority temperature help', 3);
        $this->createTestPrompt('temperature control', 'High priority temperature help', 9);

        $result = $this->advancedService->findBestMatchingPrompt('temperature control issues');

        $this->assertNotNull($result);
        $this->assertStringContains('High priority', $result['content']);
    }

    /** @test */
    public function context_relevance_provides_bonus_scoring()
    {
        $this->createTestPrompt(
            'warranty information', 
            'Your refrigerator warranty covers parts and labor for manufacturing defects', 
            6
        );

        // Input that mentions context words from prompt content
        $result = $this->advancedService->findBestMatchingPrompt('warranty information about parts and labor');

        $this->assertNotNull($result);
        $this->assertGreaterThan(0, $result['score_breakdown']['context_bonus']);
    }

    /** @test */
    public function penalties_reduce_scores_for_irrelevant_content()
    {
        $this->createTestPrompt('ice maker', 'Ice maker troubleshooting', 5);

        // Input with many irrelevant words
        $result = $this->advancedService->findBestMatchingPrompt(
            'ice maker hello world lorem ipsum dolor sit amet consectetur'
        );

        if ($result) {
            $this->assertLessThan(0, $result['score_breakdown']['penalties']);
        }
    }

    /** @test */
    public function analyze_match_provides_detailed_breakdown()
    {
        $analysis = $this->advancedService->analyzeMatch(
            'My refrigerator is not cooling properly',
            'refrigerator cooling, cooling issues, not cooling'
        );

        $this->assertArrayHasKey('input_keywords', $analysis);
        $this->assertArrayHasKey('trigger_keywords', $analysis);
        $this->assertArrayHasKey('score_breakdown', $analysis);
        $this->assertArrayHasKey('confidence', $analysis);
        $this->assertArrayHasKey('match_quality', $analysis);
        
        $this->assertContains('refrigerator', $analysis['input_keywords']);
        $this->assertContains('cooling', $analysis['input_keywords']);
    }

    /** @test */
    public function multiple_trigger_phrases_are_evaluated_correctly()
    {
        $this->createTestPrompt(
            'refrigerator not cooling, fridge warm, cooling problems',
            'Cooling troubleshooting guide',
            8
        );

        $result1 = $this->advancedService->findBestMatchingPrompt('refrigerator not cooling');
        $result2 = $this->advancedService->findBestMatchingPrompt('fridge warm today');
        $result3 = $this->advancedService->findBestMatchingPrompt('cooling problems here');

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);
        $this->assertNotNull($result3);
        
        // All should have high confidence due to exact phrase matches
        $this->assertGreaterThanOrEqual(0.8, $result1['confidence']);
        $this->assertGreaterThanOrEqual(0.8, $result2['confidence']);
        $this->assertGreaterThanOrEqual(0.8, $result3['confidence']);
    }

    /** @test */
    public function empty_and_invalid_inputs_are_handled_gracefully()
    {
        $this->createTestPrompt('test prompt', 'Test content', 5);

        $result1 = $this->advancedService->findBestMatchingPrompt('');
        $result2 = $this->advancedService->findBestMatchingPrompt('   ');
        $result3 = $this->advancedService->findBestMatchingPrompt('a');

        $this->assertNull($result1);
        $this->assertNull($result2);
        $this->assertNull($result3); // Single character should be filtered out
    }

    /** @test */
    public function confidence_levels_are_properly_categorized()
    {
        $this->createTestPrompt('warranty info', 'Warranty information details', 5);

        // Test different confidence levels
        $excellent = $this->advancedService->findBestMatchingPrompt('warranty info help');
        $good = $this->advancedService->findBestMatchingPrompt('warranty details needed');
        
        if ($excellent) {
            $this->assertContains($excellent['match_quality'], ['excellent', 'very good', 'good']);
        }
        
        if ($good) {
            $this->assertContains($good['match_quality'], ['excellent', 'very good', 'good', 'acceptable']);
        }
    }

    /** @test */
    public function comprehensive_scoring_comparison_with_basic_algorithm()
    {
        // Create complex scenario
        $this->createTestPrompt(
            'ice maker not working, ice machine broken',
            'Ice maker troubleshooting: Check power, water line, and reset the unit',
            7
        );

        // Test various inputs to compare accuracy
        $testCases = [
            'ice maker not working' => 'excellent',
            'ice machine broken' => 'excellent', 
            'icemaker problems' => 'good', // fuzzy + stem matching
            'my ice thing is broke' => 'acceptable', // synonym matching
            'pasta recipe' => null // should return null
        ];

        foreach ($testCases as $input => $expectedQuality) {
            $result = $this->advancedService->findBestMatchingPrompt($input);
            
            if ($expectedQuality === null) {
                $this->assertNull($result, "Should return null for: {$input}");
            } else {
                $this->assertNotNull($result, "Should find match for: {$input}");
                $this->assertGreaterThanOrEqual(0.6, $result['confidence'], 
                    "Confidence too low for: {$input}");
            }
        }
    }

    private function createTestPrompt(string $trigger, string $content, int $priority): void
    {
        DB::table('prompts')->insert([
            'trigger_phrase' => $trigger,
            'prompt_content' => $content,
            'priority' => $priority,
            'is_active' => 1,
            'usage_count' => 0,
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}