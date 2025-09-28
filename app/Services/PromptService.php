<?php

namespace App\Services;

use App\Models\Prompt;

class PromptService
{
    public function findMatchingPrompt(string $userMessage): ?array
    {
        $input = strtolower(trim($userMessage));
        $prompts = Prompt::active()->orderBy('priority', 'desc')->get();
        
        $bestMatch = null;
        $highestScore = 0;
        
        foreach ($prompts as $prompt) {
            $score = $this->calculateMatchScore($input, $prompt->trigger_phrase);
            
            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $prompt;
            }
        }
        
        if ($bestMatch && $highestScore >= 2) {
            $bestMatch->increment('usage_count');
            return [
                'content' => $bestMatch->prompt_content,
                'id' => $bestMatch->id
            ];
        }
        
        return null;
    }

    private function calculateMatchScore(string $userInput, string $triggerPhrase): int
    {
        // Handle empty or whitespace-only input
        $input = strtolower(trim($userInput));
        if (empty($input)) {
            return 0;
        }
        
        // Handle empty trigger phrase
        $triggerPhrase = trim($triggerPhrase);
        if (empty($triggerPhrase)) {
            return 0;
        }
        
        $score = 0;
        $triggers = array_map('trim', explode(',', strtolower($triggerPhrase)));
        
        foreach ($triggers as $trigger) {
            // Skip empty triggers
            if (empty($trigger)) {
                continue;
            }
            
            // Exact phrase match (10 points)
            if (str_contains($input, $trigger)) {
                $score += 10;
            }
            
            // Word-by-word matching
            $triggerWords = array_filter(explode(' ', $trigger), function($word) {
                return strlen(trim($word)) > 2; // Only process words longer than 2 characters
            });
            
            $inputWords = array_filter(explode(' ', $input), function($word) {
                return strlen(trim($word)) > 2; // Only process words longer than 2 characters
            });
            
            foreach ($triggerWords as $triggerWord) {
                $triggerWord = trim($triggerWord);
                if (empty($triggerWord)) continue;
                
                foreach ($inputWords as $inputWord) {
                    $inputWord = trim($inputWord);
                    if (empty($inputWord)) continue;
                    
                    if ($triggerWord === $inputWord) {
                        // Exact word match (3 points)
                        $score += 3;
                    } elseif (str_contains($triggerWord, $inputWord) || str_contains($inputWord, $triggerWord)) {
                        // Partial word match (1 point)
                        $score += 1;
                    }
                }
            }
        }
        
        return $score;
    }
}
