<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AdvancedPromptService;

class DebugAdvancedScoringTest extends TestCase
{
    private AdvancedPromptService $advancedService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->advancedService = new AdvancedPromptService();
    }

    /** @test */
    public function debug_confidence_levels()
    {
        // Debug the confidence categorization issue
        $testCases = [
            ['refrigerator repair needed', 'refrigerator repair'],
            ['fridge needs repair', 'refrigerator repair'],
            ['appliance maintenance', 'refrigerator repair']
        ];

        foreach ($testCases as [$input, $trigger]) {
            $result = $this->advancedService->analyzeMatch($input, $trigger);
            
            echo "\n=== DEBUG: {$input} ===\n";
            echo "Confidence: " . round($result['confidence'], 3) . "\n";
            echo "Match Quality: " . $result['match_quality'] . "\n";
            echo "Total Score: " . $result['total_score'] . "\n";
            echo "Score Breakdown:\n";
            foreach ($result['score_breakdown'] as $type => $score) {
                if ($score != 0) {
                    echo "  - {$type}: {$score}\n";
                }
            }
            echo "Input Keywords: " . implode(', ', $result['input_keywords']) . "\n";
            echo "Trigger Keywords: " . implode(', ', $result['trigger_keywords']) . "\n";
        }

        // This test always passes - it's just for debugging
        $this->assertTrue(true);
    }

    /** @test */
    public function debug_multiple_trigger_phrases()
    {
        $result = $this->advancedService->analyzeMatch(
            'ice maker broken',
            'ice maker problems, ice machine issues, ice dispenser broken'
        );

        echo "\n=== DEBUG: Multiple Trigger Phrases ===\n";
        echo "Input: 'ice maker broken'\n";
        echo "Trigger: 'ice maker problems, ice machine issues, ice dispenser broken'\n";
        echo "Confidence: " . round($result['confidence'], 3) . "\n";
        echo "Total Score: " . $result['total_score'] . "\n";
        echo "Score Breakdown:\n";
        foreach ($result['score_breakdown'] as $type => $score) {
            if ($score != 0) {
                echo "  - {$type}: {$score}\n";
            }
        }
        echo "Input Keywords: " . implode(', ', $result['input_keywords']) . "\n";
        echo "Trigger Keywords: " . implode(', ', $result['trigger_keywords']) . "\n";

        // This test always passes - it's just for debugging
        $this->assertTrue(true);
    }

    /** @test */
    public function debug_exact_phrase_detection()
    {
        // Test if exact phrase matching is working
        $testCases = [
            'ice maker broken' => 'ice maker problems',
            'ice maker broken' => 'ice dispenser broken', 
            'refrigerator repair' => 'refrigerator repair'
        ];

        foreach ($testCases as $input => $trigger) {
            $result = $this->advancedService->analyzeMatch($input, $trigger);
            
            echo "\n=== EXACT PHRASE TEST ===\n";
            echo "Input: '{$input}'\n";
            echo "Trigger: '{$trigger}'\n";
            echo "Should contain phrase: " . (str_contains(strtolower($input), strtolower($trigger)) ? 'YES' : 'NO') . "\n";
            echo "Exact phrase score: " . $result['score_breakdown']['exact_phrase_matches'] . "\n";
        }

        $this->assertTrue(true);
    }

    /** @test */
    public function debug_preprocessed_input()
    {
        $inputs = [
            'ice maker broken',
            'Ice Maker Problems!',
            'ice-maker issues?',
            'ICE MAKER not working'
        ];

        // Use reflection to access private preprocessInput method
        $reflection = new \ReflectionClass($this->advancedService);
        $method = $reflection->getMethod('preprocessInput');
        $method->setAccessible(true);

        foreach ($inputs as $input) {
            $processed = $method->invoke($this->advancedService, $input);
            echo "\nOriginal: '{$input}' → Processed: '{$processed}'\n";
        }

        $this->assertTrue(true);
    }
}
