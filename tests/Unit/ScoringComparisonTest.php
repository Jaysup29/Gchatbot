<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PromptService;
use App\Services\AdvancedPromptService;

class ScoringComparisonTest extends TestCase
{
    private PromptService $basicService;
    private AdvancedPromptService $advancedService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->basicService = new PromptService();
        $this->advancedService = new AdvancedPromptService();
    }

    /** @test */
    public function compare_basic_vs_advanced_scoring_accuracy()
    {
        // Test cases with expected accuracy improvements
        $testCases = [
            [
                'trigger' => 'refrigerator repair',
                'input' => 'My fridge needs fixing',
                'description' => 'Synonym matching test'
            ],
            [
                'trigger' => 'ice maker problems',
                'input' => 'icemaker not working',
                'description' => 'Spacing/fuzzy matching test'
            ],
            [
                'trigger' => 'temperature control',
                'input' => 'temperatur controls broken',
                'description' => 'Typo handling test'
            ],
            [
                'trigger' => 'cooling system',
                'input' => 'cooled systems failing',
                'description' => 'Stem matching test'
            ],
            [
                'trigger' => 'warranty information',
                'input' => 'warranty info needed',
                'description' => 'Abbreviation matching test'
            ]
        ];

        $improvements = [];
        
        foreach ($testCases as $case) {
            $basicAnalysis = $this->analyzeBasicScoring($case['input'], $case['trigger']);
            $advancedAnalysis = $this->advancedService->analyzeMatch($case['input'], $case['trigger']);
            
            $improvements[] = [
                'test_case' => $case['description'],
                'input' => $case['input'],
                'trigger' => $case['trigger'],
                'basic_score' => $basicAnalysis['score'],
                'advanced_confidence' => $advancedAnalysis['confidence'],
                'improvement' => $advancedAnalysis['confidence'] > ($basicAnalysis['score'] / 20), // Normalize basic score
                'advanced_breakdown' => $advancedAnalysis['score_breakdown']
            ];
        }

        // Verify that advanced algorithm shows improvements
        $improvedCases = array_filter($improvements, fn($case) => $case['improvement']);
        
        $this->assertGreaterThan(
            count($testCases) * 0.8, // At least 80% should show improvement
            count($improvedCases),
            'Advanced algorithm should improve accuracy in most test cases'
        );

        // Output comparison for analysis
        $this->outputComparisonResults($improvements);
    }

    /** @test */
    public function advanced_algorithm_handles_edge_cases_better()
    {
        $edgeCases = [
            'Empty input' => '',
            'Single character' => 'a',
            'Only stop words' => 'the and or but',
            'Numbers only' => '123 456',
            'Special characters' => '!@#$%^&*()',
            'Mixed case with typos' => 'ReFrIgErAtOr RePaIr',
            'Very long input' => str_repeat('refrigerator repair help needed ', 20),
            'Multiple topics' => 'refrigerator repair ice maker warranty temperature control'
        ];

        $trigger = 'refrigerator repair';
        $robustnessResults = [];

        foreach ($edgeCases as $description => $input) {
            try {
                $basicResult = $this->analyzeBasicScoring($input, $trigger);
                $advancedResult = $this->advancedService->analyzeMatch($input, $trigger);

                $robustnessResults[] = [
                    'case' => $description,
                    'input' => substr($input, 0, 50) . (strlen($input) > 50 ? '...' : ''),
                    'basic_handled' => $basicResult['score'] >= 0,
                    'advanced_handled' => $advancedResult['confidence'] >= 0,
                    'advanced_confidence' => $advancedResult['confidence'],
                    'basic_score' => $basicResult['score']
                ];
            } catch (\Exception $e) {
                $robustnessResults[] = [
                    'case' => $description,
                    'input' => substr($input, 0, 50) . (strlen($input) > 50 ? '...' : ''),
                    'basic_handled' => false,
                    'advanced_handled' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Advanced algorithm should handle all edge cases gracefully
        $advancedHandled = array_filter($robustnessResults, fn($r) => $r['advanced_handled'] ?? false);
        
        $this->assertGreaterThanOrEqual(
            count($edgeCases) * 0.9, // 90% of edge cases handled
            count($advancedHandled),
            'Advanced algorithm should handle edge cases robustly'
        );
    }

    /** @test */
    public function confidence_scoring_provides_better_accuracy_measurement()
    {
        $testInputs = [
            'refrigerator repair needed urgently' => 0.9, // Should be high confidence
            'fridge needs fixing soon' => 0.7, // Medium confidence (synonyms)
            'appliance maintenance required' => 0.4, // Low confidence (generic)
            'cooking dinner tonight' => 0.0 // No confidence (unrelated)
        ];

        $trigger = 'refrigerator repair';

        foreach ($testInputs as $input => $expectedMinConfidence) {
            $result = $this->advancedService->analyzeMatch($input, $trigger);
            
            if ($expectedMinConfidence > 0) {
                $this->assertGreaterThanOrEqual(
                    $expectedMinConfidence,
                    $result['confidence'],
                    "Confidence too low for: {$input}"
                );
            } else {
                $this->assertLessThan(
                    0.3,
                    $result['confidence'],
                    "Confidence too high for unrelated input: {$input}"
                );
            }
        }
    }

    /** @test */
    public function scoring_performance_comparison()
    {
        $iterations = 100;
        $testInput = 'My refrigerator is not cooling properly and needs repair';
        $trigger = 'refrigerator cooling, not cooling, repair needed';

        // Measure basic algorithm performance
        $basicStart = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->analyzeBasicScoring($testInput, $trigger);
        }
        $basicTime = microtime(true) - $basicStart;

        // Measure advanced algorithm performance
        $advancedStart = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->advancedService->analyzeMatch($testInput, $trigger);
        }
        $advancedTime = microtime(true) - $advancedStart;

        // Advanced algorithm should still be reasonably fast
        // Allow up to 5x slower for significantly better accuracy
        $this->assertLessThan(
            $basicTime * 5,
            $advancedTime,
            'Advanced algorithm should not be more than 5x slower than basic'
        );

        echo "\nPerformance Comparison:\n";
        echo "Basic Algorithm: " . round($basicTime * 1000, 2) . "ms for {$iterations} iterations\n";
        echo "Advanced Algorithm: " . round($advancedTime * 1000, 2) . "ms for {$iterations} iterations\n";
        echo "Performance Ratio: " . round($advancedTime / $basicTime, 2) . "x\n";
    }

    private function analyzeBasicScoring(string $input, string $trigger): array
    {
        // Simulate basic scoring algorithm analysis
        $reflection = new \ReflectionClass($this->basicService);
        $method = $reflection->getMethod('calculateMatchScore');
        $method->setAccessible(true);
        
        $score = $method->invoke($this->basicService, $input, $trigger);
        
        return [
            'score' => $score,
            'threshold_met' => $score >= 2,
            'confidence_estimate' => min(1.0, $score / 20) // Rough confidence estimate
        ];
    }

    private function outputComparisonResults(array $improvements): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "SCORING ALGORITHM COMPARISON RESULTS\n";
        echo str_repeat("=", 80) . "\n";

        foreach ($improvements as $case) {
            echo "\nTest: {$case['test_case']}\n";
            echo "Input: '{$case['input']}'\n";
            echo "Trigger: '{$case['trigger']}'\n";
            echo "Basic Score: {$case['basic_score']}\n";
            echo "Advanced Confidence: " . round($case['advanced_confidence'], 3) . "\n";
            echo "Improved: " . ($case['improvement'] ? '✅ YES' : '❌ NO') . "\n";
            
            if (!empty($case['advanced_breakdown'])) {
                echo "Advanced Breakdown:\n";
                foreach ($case['advanced_breakdown'] as $type => $score) {
                    if ($score > 0) {
                        echo "  - {$type}: {$score}\n";
                    }
                }
            }
            echo str_repeat("-", 50) . "\n";
        }

        $totalImproved = count(array_filter($improvements, fn($c) => $c['improvement']));
        $totalTests = count($improvements);
        $improvementRate = round(($totalImproved / $totalTests) * 100, 1);

        echo "\nSUMMARY:\n";
        echo "Total Tests: {$totalTests}\n";
        echo "Improved Cases: {$totalImproved}\n";
        echo "Improvement Rate: {$improvementRate}%\n";
        echo str_repeat("=", 80) . "\n";
    }
}