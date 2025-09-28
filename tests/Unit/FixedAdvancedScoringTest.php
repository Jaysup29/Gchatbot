<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AdvancedPromptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixedAdvancedScoringTest extends TestCase
{
    private AdvancedPromptService $advancedService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->advancedService = new AdvancedPromptService();
        $this->createMinimalPromptsTable();
    }

    protected function tearDown(): void
    {
        try {
            Schema::dropIfExists('prompts');
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
        parent::tearDown();
    }

    private function createMinimalPromptsTable()
    {
        try {
            // Drop if exists
            Schema::dropIfExists('prompts');
            
            // Create minimal prompts table
            DB::statement('
                CREATE TABLE prompts (
                    id int AUTO_INCREMENT PRIMARY KEY,
                    trigger_phrase text,
                    prompt_content text,
                    priority int DEFAULT 5,
                    is_active int DEFAULT 1,
                    usage_count int DEFAULT 0
                )
            ');
        } catch (\Exception $e) {
            $this->markTestSkipped('Cannot create test table: ' . $e->getMessage());
        }
    }

    /** @test */
    public function debug_exact_phrase_matching()
    {
        $this->insertTestPrompt('refrigerator repair', 'Refrigerator repair instructions', 8);

        echo "\n=== DEBUGGING EXACT PHRASE MATCHING ===\n";
        
        // First test with analyzeMatch (no database needed)
        $analysis = $this->advancedService->analyzeMatch(
            'I need refrigerator repair help',
            'refrigerator repair'
        );
        
        echo "Analysis Results:\n";
        echo "- Input: 'I need refrigerator repair help'\n";
        echo "- Trigger: 'refrigerator repair'\n";
        echo "- Confidence: " . round($analysis['confidence'], 3) . "\n";
        echo "- Match Quality: " . $analysis['match_quality'] . "\n";
        echo "- Total Score: " . $analysis['total_score'] . "\n";
        echo "Score Breakdown:\n";
        foreach ($analysis['score_breakdown'] as $type => $score) {
            if ($score != 0) {
                echo "  - {$type}: {$score}\n";
            }
        }
        
        // Now test with database
        try {
            $result = $this->advancedService->findBestMatchingPrompt('I need refrigerator repair help');
            
            if ($result) {
                echo "\nDatabase Test Results:\n";
                echo "- Found match: YES\n";
                echo "- Confidence: " . round($result['confidence'], 3) . "\n";
                echo "- Match Quality: " . $result['match_quality'] . "\n";
                echo "- Content: " . substr($result['content'], 0, 50) . "...\n";
            } else {
                echo "\nDatabase Test Results:\n";
                echo "- Found match: NO\n";
            }
        } catch (\Exception $e) {
            echo "\nDatabase Error: " . $e->getMessage() . "\n";
        }

        $this->assertGreaterThan(0, $analysis['confidence']);
    }

    /** @test */
    public function debug_synonym_matching()
    {
        $this->insertTestPrompt('refrigerator repair', 'Refrigerator repair guide', 7);

        echo "\n=== DEBUGGING SYNONYM MATCHING ===\n";
        
        $analysis = $this->advancedService->analyzeMatch(
            'I need to fix my fridge',
            'refrigerator repair'
        );
        
        echo "Test: 'I need to fix my fridge' vs 'refrigerator repair'\n";
        echo "- Confidence: " . round($analysis['confidence'], 3) . "\n";
        echo "- Synonym Score: " . $analysis['score_breakdown']['synonym_matches'] . "\n";
        echo "- Input Keywords: " . implode(', ', $analysis['input_keywords']) . "\n";
        echo "- Trigger Keywords: " . implode(', ', $analysis['trigger_keywords']) . "\n";

        $this->assertGreaterThan(0, $analysis['confidence']);
    }

    /** @test */
    public function debug_fuzzy_matching()
    {
        echo "\n=== DEBUGGING FUZZY MATCHING ===\n";
        
        $analysis = $this->advancedService->analyzeMatch(
            'refridgerator temperatur problems',
            'refrigerator temperature'
        );
        
        echo "Test: 'refridgerator temperatur problems' vs 'refrigerator temperature'\n";
        echo "- Confidence: " . round($analysis['confidence'], 3) . "\n";
        echo "- Fuzzy Score: " . $analysis['score_breakdown']['fuzzy_matches'] . "\n";
        echo "- Exact Word Score: " . $analysis['score_breakdown']['exact_word_matches'] . "\n";
        echo "Score Breakdown:\n";
        foreach ($analysis['score_breakdown'] as $type => $score) {
            if ($score != 0) {
                echo "  - {$type}: {$score}\n";
            }
        }

        $this->assertGreaterThan(0, $analysis['confidence']);
    }

    /** @test */
    public function debug_multiple_trigger_phrases()
    {
        echo "\n=== DEBUGGING MULTIPLE TRIGGER PHRASES ===\n";
        
        $testCases = [
            'ice maker broken' => 'ice maker problems, ice machine issues, ice dispenser broken',
            'fridge warm today' => 'refrigerator not cooling, fridge warm, cooling problems',
            'cooling problems here' => 'refrigerator not cooling, fridge warm, cooling problems'
        ];

        foreach ($testCases as $input => $trigger) {
            $analysis = $this->advancedService->analyzeMatch($input, $trigger);
            
            echo "\nTest: '{$input}' vs '{$trigger}'\n";
            echo "- Confidence: " . round($analysis['confidence'], 3) . "\n";
            echo "- Exact Phrase Score: " . $analysis['score_breakdown']['exact_phrase_matches'] . "\n";
            echo "- Total Score: " . $analysis['total_score'] . "\n";
            
            if ($analysis['score_breakdown']['exact_phrase_matches'] == 0) {
                echo "- DEBUG: No exact phrase match found\n";
                echo "- Input processed: '{$analysis['processed_input']}'\n";
                
                // Check each trigger phrase individually
                $triggers = explode(',', $trigger);
                foreach ($triggers as $singleTrigger) {
                    $singleTrigger = trim($singleTrigger);
                    $contains = str_contains(strtolower($input), strtolower($singleTrigger));
                    echo "  - '{$singleTrigger}' in input: " . ($contains ? 'YES' : 'NO') . "\n";
                }
            }
        }

        $this->assertTrue(true); // Always pass - this is for debugging
    }

    /** @test */
    public function debug_confidence_thresholds()
    {
        echo "\n=== DEBUGGING CONFIDENCE THRESHOLDS ===\n";
        
        $testCases = [
            ['refrigerator repair needed urgently', 'refrigerator repair', 'Should be excellent'],
            ['fridge needs fixing soon', 'refrigerator repair', 'Should be good (synonyms)'],
            ['appliance maintenance required', 'refrigerator repair', 'Should be poor (generic)'],
            ['cooking dinner tonight', 'refrigerator repair', 'Should be very poor']
        ];

        foreach ($testCases as [$input, $trigger, $expectation]) {
            $analysis = $this->advancedService->analyzeMatch($input, $trigger);
            
            echo "\nTest: '{$input}'\n";
            echo "- Expected: {$expectation}\n";
            echo "- Confidence: " . round($analysis['confidence'], 3) . "\n";
            echo "- Match Quality: " . $analysis['match_quality'] . "\n";
            echo "- Total Score: " . $analysis['total_score'] . "\n";
            
            if ($analysis['confidence'] < 0.1) {
                echo "- Very low confidence - checking why:\n";
                echo "  - Max possible score: " . ($analysis['total_score'] / max(0.001, $analysis['confidence'])) . "\n";
                echo "  - Penalties: " . $analysis['score_breakdown']['penalties'] . "\n";
            }
        }

        $this->assertTrue(true);
    }

    private function insertTestPrompt(string $trigger, string $content, int $priority): void
    {
        try {
            DB::table('prompts')->insert([
                'trigger_phrase' => $trigger,
                'prompt_content' => $content,
                'priority' => $priority,
                'is_active' => 1,
                'usage_count' => 0
            ]);
        } catch (\Exception $e) {
            // If database insert fails, just continue with analyze-only tests
            echo "Database insert failed (continuing with analysis-only): " . $e->getMessage() . "\n";
        }
    }
}
