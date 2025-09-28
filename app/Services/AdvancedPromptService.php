<?php

namespace App\Services;

use App\Models\Prompt;

class AdvancedPromptService
{
    // Scoring weights for different match types
    private const EXACT_PHRASE_SCORE = 50;
    private const EXACT_WORD_SCORE = 15;
    private const STEM_MATCH_SCORE = 10;
    private const SYNONYM_SCORE = 8;
    private const PARTIAL_WORD_SCORE = 3;
    private const FUZZY_MATCH_SCORE = 2;
    
    // Penalties
    private const IRRELEVANT_WORD_PENALTY = -2;
    private const LENGTH_MISMATCH_PENALTY = -1;
    
    // Thresholds
    private const MIN_CONFIDENCE_THRESHOLD = 0.6; // 60% confidence
    private const HIGH_CONFIDENCE_THRESHOLD = 0.9; // 90% confidence
    
    // Common synonyms for appliance terms
    private array $synonymMap = [
        'refrigerator' => ['fridge', 'icebox', 'cooler'],
        'freezer' => ['icebox', 'frozen compartment'],
        'repair' => ['fix', 'service', 'maintenance', 'troubleshoot'],
        'broken' => ['damaged', 'not working', 'faulty', 'defective'],
        'temperature' => ['temp', 'heat', 'cold', 'cooling'],
        'ice maker' => ['ice machine', 'ice dispenser', 'ice generator'],
        'warranty' => ['guarantee', 'coverage', 'protection'],
        'energy' => ['power', 'electricity', 'consumption'],
        'noise' => ['sound', 'loud', 'noisy', 'vibration'],
        'clean' => ['wash', 'sanitize', 'maintenance'],
    ];

    // Stop words that should be ignored
    private array $stopWords = [
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
        'of', 'with', 'by', 'is', 'are', 'was', 'were', 'what', 'how', 'when',
        'where', 'why', 'can', 'could', 'would', 'should', 'do', 'does', 'my',
        'your', 'have', 'has', 'had', 'will', 'would', 'please', 'help', 'me', 'i'
    ];

    public function findBestMatchingPrompt(string $userMessage): ?array
    {
        $input = $this->preprocessInput($userMessage);
        if (empty($input)) {
            return null;
        }

        $prompts = Prompt::active()->orderBy('priority', 'desc')->get();
        $bestMatch = null;
        $highestScore = 0;
        $highestConfidence = 0;

        foreach ($prompts as $prompt) {
            $scoreData = $this->calculateAdvancedScore($input, $prompt->trigger_phrase, $prompt->prompt_content);
            
            // Combine raw score with confidence and priority
            $finalScore = $this->calculateFinalScore($scoreData, $prompt->priority);
            
            if ($scoreData['confidence'] >= self::MIN_CONFIDENCE_THRESHOLD && 
                $finalScore > $highestScore) {
                $highestScore = $finalScore;
                $highestConfidence = $scoreData['confidence'];
                $bestMatch = [
                    'prompt' => $prompt,
                    'score_data' => $scoreData,
                    'final_score' => $finalScore
                ];
            }
        }

        if ($bestMatch && $highestConfidence >= self::MIN_CONFIDENCE_THRESHOLD) {
            $bestMatch['prompt']->increment('usage_count');
            
            return [
                'content' => $bestMatch['prompt']->prompt_content,
                'id' => $bestMatch['prompt']->id,
                'confidence' => $highestConfidence,
                'score_breakdown' => $bestMatch['score_data'],
                'final_score' => $bestMatch['final_score'],
                'match_quality' => $this->getMatchQuality($highestConfidence)
            ];
        }

        return null;
    }

    private function calculateAdvancedScore(string $userInput, string $triggerPhrase, string $promptContent): array
    {
        $triggerWords = $this->extractKeywords($triggerPhrase);
        $inputWords = $this->extractKeywords($userInput);
        $promptWords = $this->extractKeywords($promptContent);

        $scoreBreakdown = [
            'exact_phrase_matches' => 0,
            'exact_word_matches' => 0,
            'synonym_matches' => 0,
            'stem_matches' => 0,
            'partial_matches' => 0,
            'fuzzy_matches' => 0,
            'penalties' => 0,
            'context_bonus' => 0
        ];

        $totalScore = 0;
        $maxPossibleScore = 0;

        // 1. Exact phrase matching (highest priority)
        $exactPhraseScore = $this->calculateExactPhraseMatches($userInput, $triggerPhrase);
        $scoreBreakdown['exact_phrase_matches'] = $exactPhraseScore;
        $totalScore += $exactPhraseScore;
        $maxPossibleScore += count(explode(',', $triggerPhrase)) * self::EXACT_PHRASE_SCORE;

        // 2. Advanced word matching
        foreach ($triggerWords as $triggerWord) {
            $maxPossibleScore += self::EXACT_WORD_SCORE;
            $bestWordScore = 0;
            $matchType = '';

            foreach ($inputWords as $inputWord) {
                // Exact word match
                if ($triggerWord === $inputWord) {
                    $bestWordScore = max($bestWordScore, self::EXACT_WORD_SCORE);
                    $matchType = 'exact_word';
                }
                // Synonym match
                elseif ($this->areSynonyms($triggerWord, $inputWord)) {
                    $bestWordScore = max($bestWordScore, self::SYNONYM_SCORE);
                    $matchType = 'synonym';
                }
                // Stem match (plural/singular, verb forms)
                elseif ($this->isStemMatch($triggerWord, $inputWord)) {
                    $bestWordScore = max($bestWordScore, self::STEM_MATCH_SCORE);
                    $matchType = 'stem';
                }
                // Fuzzy match (typos, similar spelling)
                elseif ($this->isFuzzyMatch($triggerWord, $inputWord)) {
                    $bestWordScore = max($bestWordScore, self::FUZZY_MATCH_SCORE);
                    $matchType = 'fuzzy';
                }
                // Partial match (substring)
                elseif ($this->isPartialMatch($triggerWord, $inputWord)) {
                    $bestWordScore = max($bestWordScore, self::PARTIAL_WORD_SCORE);
                    $matchType = 'partial';
                }
            }

            $totalScore += $bestWordScore;
            if ($matchType) {
                $scoreBreakdown[$matchType . '_matches'] += $bestWordScore;
            }
        }

        // 3. Context bonus (if prompt content is relevant)
        $contextScore = $this->calculateContextRelevance($inputWords, $promptWords);
        $scoreBreakdown['context_bonus'] = $contextScore;
        $totalScore += $contextScore;

        // 4. Apply penalties
        $penalties = $this->calculatePenalties($inputWords, $triggerWords);
        $scoreBreakdown['penalties'] = $penalties;
        $totalScore += $penalties; // Note: penalties are negative

        // 5. Calculate confidence (0-1 scale)
        $confidence = $maxPossibleScore > 0 ? max(0, $totalScore) / $maxPossibleScore : 0;
        $confidence = min(1.0, $confidence); // Cap at 100%

        return [
            'total_score' => $totalScore,
            'max_possible_score' => $maxPossibleScore,
            'confidence' => $confidence,
            'breakdown' => $scoreBreakdown
        ];
    }

    private function calculateExactPhraseMatches(string $input, string $triggerPhrase): int
    {
        $score = 0;
        $inputLower = strtolower($input);
        $triggers = array_map('trim', explode(',', strtolower($triggerPhrase)));
        
        foreach ($triggers as $trigger) {
            if (!empty($trigger)) {
                // Check for exact phrase match
                if (str_contains($inputLower, $trigger)) {
                    $score += self::EXACT_PHRASE_SCORE;
                }
                // Also check for partial phrase matches (words in same order)
                else {
                    $triggerWords = explode(' ', $trigger);
                    $inputWords = explode(' ', $inputLower);
                    
                    // Check if all trigger words appear in input (not necessarily consecutive)
                    $allWordsFound = true;
                    foreach ($triggerWords as $triggerWord) {
                        if (strlen($triggerWord) > 2 && !in_array($triggerWord, $inputWords)) {
                            $allWordsFound = false;
                            break;
                        }
                    }
                    
                    if ($allWordsFound && count($triggerWords) > 1) {
                        $score += self::EXACT_PHRASE_SCORE * 0.7; // 70% of full phrase score
                    }
                }
            }
        }
        
        return $score;
    }

    private function areSynonyms(string $word1, string $word2): bool
    {
        foreach ($this->synonymMap as $base => $synonyms) {
            if (($word1 === $base && in_array($word2, $synonyms)) ||
                ($word2 === $base && in_array($word1, $synonyms)) ||
                (in_array($word1, $synonyms) && in_array($word2, $synonyms))) {
                return true;
            }
        }
        return false;
    }

    private function isStemMatch(string $word1, string $word2): bool
    {
        // Simple stemming - remove common suffixes
        $stems1 = $this->getStemVariations($word1);
        $stems2 = $this->getStemVariations($word2);
        
        return !empty(array_intersect($stems1, $stems2));
    }

    private function getStemVariations(string $word): array
    {
        $variations = [$word];
        
        // Remove common suffixes
        $suffixes = ['s', 'es', 'ed', 'ing', 'er', 'est', 'ly', 'tion', 'sion'];
        
        foreach ($suffixes as $suffix) {
            if (str_ends_with($word, $suffix) && strlen($word) > strlen($suffix) + 2) {
                $variations[] = substr($word, 0, -strlen($suffix));
            }
        }
        
        return array_unique($variations);
    }

    private function isFuzzyMatch(string $word1, string $word2): bool
    {
        // Use Levenshtein distance for fuzzy matching
        $maxLength = max(strlen($word1), strlen($word2));
        $distance = levenshtein($word1, $word2);
        
        // Allow 1-2 character differences for words > 4 chars
        if ($maxLength > 4) {
            return $distance <= 2;
        } elseif ($maxLength > 2) {
            return $distance <= 1;
        }
        
        return false;
    }

    private function isPartialMatch(string $word1, string $word2): bool
    {
        $minLength = min(strlen($word1), strlen($word2));
        
        // For words > 4 chars, allow partial matching
        if ($minLength > 4) {
            return str_contains($word1, $word2) || str_contains($word2, $word1);
        }
        
        return false;
    }

    private function calculateContextRelevance(array $inputWords, array $promptWords): int
    {
        $relevantWords = array_intersect($inputWords, $promptWords);
        return count($relevantWords) * 2; // Small bonus for context relevance
    }

    private function calculatePenalties(array $inputWords, array $triggerWords): int
    {
        $penalty = 0;
        
        // Penalty for completely irrelevant words
        $irrelevantWords = array_diff($inputWords, $triggerWords);
        $penalty += count($irrelevantWords) * self::IRRELEVANT_WORD_PENALTY;
        
        // Penalty for significant length mismatch
        $lengthDiff = abs(count($inputWords) - count($triggerWords));
        if ($lengthDiff > 3) {
            $penalty += self::LENGTH_MISMATCH_PENALTY * ($lengthDiff - 3);
        }
        
        return $penalty;
    }

    private function calculateFinalScore(array $scoreData, int $priority): float
    {
        $baseScore = $scoreData['total_score'];
        $confidence = $scoreData['confidence'];
        
        // Apply priority weighting (1-10 scale)
        $priorityMultiplier = 1 + ($priority / 10);
        
        // Combine score, confidence, and priority
        return $baseScore * $confidence * $priorityMultiplier;
    }

    private function preprocessInput(string $input): string
    {
        $input = strtolower(trim($input));
        
        // Remove extra whitespace
        $input = preg_replace('/\s+/', ' ', $input);
        
        // Remove punctuation except apostrophes
        $input = preg_replace('/[^\w\s\']/', ' ', $input);
        
        return $input;
    }

    private function extractKeywords(string $text): array
    {
        $words = explode(' ', $this->preprocessInput($text));
        
        // Filter out stop words and short words
        $keywords = array_filter($words, function($word) {
            return strlen($word) > 2 && !in_array($word, $this->stopWords);
        });
        
        return array_values(array_unique($keywords));
    }

    private function getMatchQuality(float $confidence): string
    {
        if ($confidence >= self::HIGH_CONFIDENCE_THRESHOLD) {
            return 'excellent';
        } elseif ($confidence >= 0.8) {
            return 'very good';
        } elseif ($confidence >= 0.7) {
            return 'good';
        } elseif ($confidence >= self::MIN_CONFIDENCE_THRESHOLD) {
            return 'acceptable';
        } else {
            return 'poor';
        }
    }

    // Method to analyze and debug scoring for a specific input
    public function analyzeMatch(string $userInput, string $triggerPhrase): array
    {
        $input = $this->preprocessInput($userInput);
        $scoreData = $this->calculateAdvancedScore($input, $triggerPhrase, '');
        
        return [
            'user_input' => $userInput,
            'processed_input' => $input,
            'trigger_phrase' => $triggerPhrase,
            'input_keywords' => $this->extractKeywords($userInput),
            'trigger_keywords' => $this->extractKeywords($triggerPhrase),
            'score_breakdown' => $scoreData['breakdown'],
            'total_score' => $scoreData['total_score'],
            'confidence' => $scoreData['confidence'],
            'match_quality' => $this->getMatchQuality($scoreData['confidence'])
        ];
    }
}
