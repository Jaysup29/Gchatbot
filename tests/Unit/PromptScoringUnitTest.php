<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PromptService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

class PromptScoringUnitTest extends TestCase
{
    private PromptService $promptService;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->promptService = new PromptService();
        $this->reflection = new ReflectionClass(PromptService::class);
    }

    /**
     * Helper method to access private calculateMatchScore method
     */
    private function calculateScore(string $userInput, string $triggerPhrase): int
    {
        $method = $this->reflection->getMethod('calculateMatchScore');
        $method->setAccessible(true);
        
        return $method->invoke($this->promptService, $userInput, $triggerPhrase);
    }

    #[Test]
    public function exact_phrase_match_gets_highest_score()
    {
        $score = $this->calculateScore(
            'refrigerator repair needed',
            'refrigerator repair'
        );

        // Should get 10 points for containing the exact phrase
        $this->assertGreaterThanOrEqual(10, $score);
    }

    #[Test]
    public function partial_phrase_match_gets_lower_score()
    {
        $exactScore = $this->calculateScore(
            'refrigerator repair needed',
            'refrigerator repair'
        );

        $partialScore = $this->calculateScore(
            'refrigerator problems',
            'refrigerator repair'
        );

        $this->assertGreaterThan($partialScore, $exactScore);
    }

    #[Test]
    public function word_exact_match_scores_correctly()
    {
        $score = $this->calculateScore(
            'ice maker broken',
            'ice maker problems'
        );

        // Should get points for exact word matches: 'ice' and 'maker'
        // Each exact word match = 3 points, so at least 6 points
        $this->assertGreaterThanOrEqual(6, $score);
    }

    #[Test]
    public function case_insensitive_matching_works()
    {
        $lowerCaseScore = $this->calculateScore(
            'refrigerator repair',
            'refrigerator repair'
        );

        $mixedCaseScore = $this->calculateScore(
            'REFRIGERATOR REPAIR',
            'Refrigerator Repair'
        );

        $this->assertEquals($lowerCaseScore, $mixedCaseScore);
    }

    #[Test]
    public function empty_input_returns_zero_score()
    {
        $this->assertEquals(0, $this->calculateScore('', 'refrigerator repair'));
        $this->assertEquals(0, $this->calculateScore('   ', 'refrigerator repair'));
    }

    #[Test]
    public function multiple_trigger_phrases_increase_score()
    {
        $singlePhraseScore = $this->calculateScore(
            'refrigerator not cooling properly',
            'refrigerator cooling'
        );

        $multiplePhraseScore = $this->calculateScore(
            'refrigerator not cooling properly',
            'refrigerator cooling, cooling issues, not cooling'
        );

        $this->assertGreaterThan($singlePhraseScore, $multiplePhraseScore);
    }

    #[Test]
    public function scoring_is_consistent_and_reproducible()
    {
        $input = 'refrigerator temperature control problems';
        $trigger = 'temperature control, refrigerator issues';

        $score1 = $this->calculateScore($input, $trigger);
        $score2 = $this->calculateScore($input, $trigger);
        $score3 = $this->calculateScore($input, $trigger);

        $this->assertEquals($score1, $score2);
        $this->assertEquals($score2, $score3);
    }
}
