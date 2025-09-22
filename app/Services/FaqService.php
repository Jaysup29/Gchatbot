<?php

namespace App\Services;

use App\Models\FAQ;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Log;

class FaqService
{
    private const MIN_QUESTION_COUNT = 10;
    private const MIN_QUESTION_LENGTH = 10;
    private const MIN_ANSWER_LENGTH = 20;

    /**
     * Check if user message matches existing FAQ
     */
    public function findMatchingFaq(string $userMessage): ?array
    {
        $keywords = $this->extractKeywords($userMessage);
        
        if (count($keywords) < 1) {
            return null;
        }

        $faq = FAQ::active()
            ->where(function($query) use ($keywords, $userMessage) {
                // Direct question matching
                $query->where('question', 'LIKE', '%' . substr($userMessage, 0, 40) . '%');
                
                // Keyword matching
                foreach ($keywords as $keyword) {
                    $query->orWhereJsonContains('keywords', $keyword);
                    $query->orWhere('question', 'LIKE', '%' . $keyword . '%');
                    $query->orWhere('answer', 'LIKE', '%' . $keyword . '%');
                }
            })
            ->orderBy('view_count', 'desc')
            ->first();

        if ($faq) {
            $faq->incrementView();
            return [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'source' => 'faq'
            ];
        }

        return null;
    }

    /**
     * Track question frequency and auto-create FAQ if threshold reached
     */
    public function trackAndCreateFaq(string $userMessage): ?FAQ
    {
        if (!$this->isValidQuestionForFaq($userMessage)) {
            return null;
        }

        $questionCount = $this->countSimilarQuestions($userMessage);
        
        if ($questionCount >= self::MIN_QUESTION_COUNT) {
            return $this->createAutoFaq($userMessage, $questionCount);
        }

        return null;
    }

    /**
     * Manually create FAQ (for admin use)
     */
    public function createManualFaq(string $question, string $answer, array $keywords = []): FAQ
    {
        return FAQ::create([
            'question' => $this->cleanQuestion($question),
            'answer' => $this->cleanAnswer($answer),
            'keywords' => empty($keywords) ? $this->extractKeywords($question) : $keywords,
            'is_active' => true,
            'view_count' => 0,
        ]);
    }

    /**
     * Get FAQ statistics
     */
    public function getStats(): array
    {
        return [
            'total_faqs' => FAQ::count(),
            'active_faqs' => FAQ::active()->count(),
            'popular_faqs' => FAQ::active()->orderBy('view_count', 'desc')->limit(5)->get(),
            'recent_faqs' => FAQ::active()->orderBy('created_at', 'desc')->limit(5)->get(),
            'auto_generated_count' => FAQ::whereNotNull('created_at')->count(),
        ];
    }

    /**
     * Search FAQs
     */
    public function searchFaqs(string $searchTerm, int $limit = 10): array
    {
        $faqs = FAQ::active()
            ->where(function($query) use ($searchTerm) {
                $query->where('question', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('answer', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhereRaw('MATCH(question, answer) AGAINST(? IN BOOLEAN MODE)', [$searchTerm]);
            })
            ->orderBy('view_count', 'desc')
            ->limit($limit)
            ->get();

        return $faqs->map(function($faq) {
            return $faq->toDisplayArray();
        })->toArray();
    }

    // Private helper methods

    private function isValidQuestionForFaq(string $question): bool
    {
        if (strlen(trim($question)) < self::MIN_QUESTION_LENGTH) {
            return false;
        }

        return !$this->isGenericQuestion($question);
    }

    private function isGenericQuestion(string $question): bool
    {
        $genericPatterns = [
            'hello', 'hi', 'hey', 'thanks', 'thank you', 'yes', 'no', 'ok', 'okay',
            'good morning', 'good afternoon', 'goodbye', 'bye'
        ];

        $question = strtolower(trim($question));
        
        foreach ($genericPatterns as $pattern) {
            if (str_contains($question, $pattern) && strlen($question) < 25) {
                return true;
            }
        }

        return false;
    }

    private function countSimilarQuestions(string $question): int
    {
        $keywords = $this->extractKeywords($question);
        
        if (count($keywords) < 2) {
            return 0;
        }

        $query = ChatMessage::where('sender_type', 'user');
        
        foreach ($keywords as $keyword) {
            $query->orWhere('message_content', 'LIKE', '%' . $keyword . '%');
        }

        return $query->distinct()->count();
    }

    private function createAutoFaq(string $question, int $askCount): ?FAQ
    {
        // Check if similar FAQ already exists
        $existingFaq = FAQ::where('question', 'LIKE', '%' . substr($question, 0, 30) . '%')
            ->first();

        if ($existingFaq) {
            $existingFaq->increment('view_count', $askCount);
            return $existingFaq;
        }

        $commonResponse = $this->getMostCommonResponse($question);

        if (!$commonResponse || strlen($commonResponse) < self::MIN_ANSWER_LENGTH) {
            return null;
        }

        $faq = FAQ::create([
            'question' => $this->cleanQuestion($question),
            'answer' => $this->cleanAnswer($commonResponse),
            'keywords' => $this->extractKeywords($question),
            'is_active' => true,
            'view_count' => $askCount,
        ]);

        Log::info('Auto-FAQ created', [
            'question' => $question,
            'ask_count' => $askCount,
            'faq_id' => $faq->id
        ]);

        return $faq;
    }

    private function getMostCommonResponse(string $question): ?string
    {
        $keywords = $this->extractKeywords($question);
        
        if (count($keywords) < 1) {
            return null;
        }

        $response = ChatMessage::where('sender_type', 'assistant')
            ->where('message_content', 'NOT LIKE', '%thinking%')
            ->where('message_content', 'NOT LIKE', '%technical difficulties%')
            ->where('message_content', 'NOT LIKE', '%apologize%')
            ->whereHas('session', function($query) use ($keywords) {
                $query->whereHas('messages', function($subQuery) use ($keywords) {
                    $subQuery->where('sender_type', 'user');
                    foreach ($keywords as $keyword) {
                        $subQuery->orWhere('message_content', 'LIKE', '%' . $keyword . '%');
                    }
                });
            })
            ->select('message_content')
            ->groupBy('message_content')
            ->orderByRaw('COUNT(*) DESC')
            ->first();

        return $response?->message_content;
    }

    private function extractKeywords(string $text): array
    {
        $words = str_word_count(strtolower($text), 1);
        $stopWords = [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'by', 'is', 'are', 'was', 'were', 'what', 'how', 'when',
            'where', 'why', 'can', 'could', 'would', 'should', 'do', 'does', 'my', 'your',
            'have', 'has', 'had', 'will', 'would', 'please', 'help', 'me', 'i'
        ];

        $keywords = array_diff($words, $stopWords);
        $keywords = array_filter($keywords, function($word) {
            return strlen($word) > 3;
        });

        return array_values(array_unique($keywords));
    }

    private function cleanQuestion(string $question): string
    {
        $cleaned = trim($question);
        $cleaned = ucfirst($cleaned);

        if (!str_ends_with($cleaned, '?') && !str_ends_with($cleaned, '.')) {
            $cleaned .= '?';
        }

        return $cleaned;
    }

    private function cleanAnswer(string $answer): string
    {
        $cleaned = trim($answer);
        $cleaned = preg_replace('/\n{3,}/', "\n\n", $cleaned);
        
        return $cleaned;
    }
}