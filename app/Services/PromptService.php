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
        $score = 0;
        $input = strtolower($userInput);
        $triggers = array_map('trim', explode(',', strtolower($triggerPhrase)));
        
        foreach ($triggers as $trigger) {
            if (str_contains($input, $trigger)) {
                $score += 10;
            }
            
            $triggerWords = explode(' ', $trigger);
            $inputWords = explode(' ', $input);
            
            foreach ($triggerWords as $triggerWord) {
                if (strlen($triggerWord) > 2) {
                    foreach ($inputWords as $inputWord) {
                        if ($triggerWord === $inputWord) {
                            $score += 3;
                        } elseif (str_contains($triggerWord, $inputWord) || str_contains($inputWord, $triggerWord)) {
                            $score += 1;
                        }
                    }
                }
            }
        }
        
        return $score;
    }
}
