<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AdvancedPromptService;

class SimpleAdvancedScoringTest extends TestCase
{
    private AdvancedPromptService $advancedService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->advancedService = new AdvancedPromptService();
    }

    /** @test */
    public function service_can_be_instantiated()
    {
        $this->assertInstanceOf(AdvancedPromptService::class, $this->advancedService);
    }

    /** @test */
    public function analyze_match_returns_proper_structure()
    {
        $result = $this->advancedService->analyzeMatch(
            'My refrigerator needs repair',
            'refrigerator repair, fridge fix'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('score_breakdown', $result);
        $this->assertArrayHasKey('match_quality', $result);
        $this->assertArrayHasKey('input_keywords', $result);
        $this->assertArrayHasKey('trigger_keywords', $result);
    }

    /** @test */
    public function exact_phrase_matching_works()
    {
        $result = $this->advancedService->analyzeMatch(
            'I need refrigerator repair help',
            'refrigerator repair'
        );

        $this->assertGreaterThan(0, $result['confidence']);
        $this->assertGreaterThan(0, $result['score_breakdown']['exact_phrase_matches']);
        $this->assertContains('refrigerator', $result['input_keywords']);
        $this->assertContains('repair', $result['input_keywords']);
    }

    /** @test */
    public function synonym_matching_works()
    {
        $result = $this->advancedService->analyzeMatch(
            'My fridge needs fixing',
            'refrigerator repair'
        );

        $this->assertGreaterThan(0, $result['confidence']);
        $this->assertGreaterThan(0, $result['score_breakdown']['synonym_matches']);
    }

    /** @test */
    public function fuzzy_matching_handles_typos()
    {
        $result = $this->advancedService->analyzeMatch(
            'refridgerator repair needed',
            'refrigerator repair'
        );

        $this->assertGreaterThan(0, $result['confidence']);
        $this->assertTrue(
            $result['score_breakdown']['fuzzy_matches'] > 0 || 
            $result['score_breakdown']['exact_phrase_matches'] > 0
        );
    }

    /** @test */
    public function low_relevance_input_gets_low_confidence()
    {
        $result = $this->advancedService->analyzeMatch(
            'cooking pasta for dinner tonight',
            'refrigerator repair'
        );

        $this->assertLessThan(0.3, $result['confidence']); // Should be very low confidence
        $this->assertEquals('poor', $result['match_quality']);
    }

    /** @test */
    public function empty_input_is_handled_gracefully()
    {
        $result = $this->advancedService->analyzeMatch('', 'refrigerator repair');

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['confidence']);
    }

    /** @test */
    public function confidence_levels_are_properly_categorized()
    {
        // Test different confidence levels
        $testCases = [
            ['refrigerator repair needed', 'refrigerator repair', 'excellent'],
            ['fridge needs repair', 'refrigerator repair', 'good'],
            ['appliance maintenance', 'refrigerator repair', 'poor']
        ];

        foreach ($testCases as [$input, $trigger, $expectedQuality]) {
            $result = $this->advancedService->analyzeMatch($input, $trigger);
            
            if ($expectedQuality === 'poor') {
                $this->assertContains($result['match_quality'], ['poor', 'acceptable']);
            } else {
                $this->assertContains($result['match_quality'], ['excellent', 'very good', 'good', 'acceptable']);
            }
        }
    }

    /** @test */
    public function multiple_trigger_phrases_are_processed()
    {
        $result = $this->advancedService->analyzeMatch(
            'ice maker broken',
            'ice maker problems, ice machine issues, ice dispenser broken'
        );

        $this->assertGreaterThan(0, $result['confidence']);
        $this->assertGreaterThan(0, $result['score_breakdown']['exact_phrase_matches']);
    }

    /** @test */
    public function stem_matching_works_for_word_variations()
    {
        $result = $this->advancedService->analyzeMatch(
            'repairing my refrigerator',
            'refrigerator repair'
        );

        $this->assertGreaterThan(0, $result['confidence']);
        $this->assertTrue(
            $result['score_breakdown']['stem_matches'] > 0 ||
            $result['score_breakdown']['exact_word_matches'] > 0
        );
    }
}
