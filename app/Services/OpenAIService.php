<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenAIService
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    private const MODEL = 'gpt-4o-mini';
    private const TIMEOUT = 30;
    private const MAX_TOKENS = 500;
    private const TEMPERATURE = 0.2;

    public function generateResponse(array $messages): array
    {
        try {
            $response = Http::withToken(env('OPENAI_API_KEY'))
                ->timeout(self::TIMEOUT)
                ->post(self::API_URL, [
                    'model' => self::MODEL,
                    'messages' => $messages,
                    'max_completion_tokens' => self::MAX_TOKENS,
                    'temperature' => self::TEMPERATURE,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'content' => $data['choices'][0]['message']['content'] ?? $this->getErrorMessage(),
                    'tokens' => $data['usage']['total_tokens'] ?? null,
                    'error' => false
                ];
            }
            
            return $this->getErrorResponse();
            
        } catch (\Exception $e) {
            return $this->getErrorResponse();
        }
    }

    public function buildConversationPrompts(array $messages): array
    {
        $prompts = [
            ['role' => 'system', 'content' => config('glacierbot.system_prompt')],
        ];
        
        foreach ($messages as $message) {
            if (isset($message['typing']) && $message['typing']) {
                continue;
            }
            
            $prompts[] = [
                'role' => $message['user'] === 'You' ? 'user' : 'assistant',
                'content' => $message['text'],
            ];
        }

        return $prompts;
    }

    private function getErrorMessage(): string
    {
        return 'I apologize, but I\'m experiencing some technical difficulties. Please try again in a moment.';
    }

    private function getErrorResponse(): array
    {
        return [
            'content' => $this->getErrorMessage(),
            'tokens' => null,
            'error' => true
        ];
    }
}