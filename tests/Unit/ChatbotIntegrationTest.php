<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PromptService;
use App\Services\FaqService;
use App\Services\OpenAIService;
use App\Services\ChatSessionService;
use App\Models\Prompt;
use App\Models\FAQ;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class ChatbotIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private PromptService $promptService;
    private FaqService $faqService;
    private OpenAIService $openAiService;
    private ChatSessionService $sessionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->promptService = new PromptService();
        $this->faqService = new FaqService();
        $this->openAiService = new OpenAIService();
        $this->sessionService = new ChatSessionService();

        // Mock OpenAI API responses
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'AI generated response']]
                ],
                'usage' => ['total_tokens' => 100]
            ], 200)
        ]);
    }

    /** @test */
    public function complete_chatbot_workflow_with_prompt_response()
    {
        // 1. Create a session
        $session = $this->sessionService->initializeSession();
        $this->assertInstanceOf(ChatSession::class, $session);

        // 2. Create a matching prompt
        $prompt = Prompt::factory()->create([
            'trigger_phrase' => 'refrigerator not cooling',
            'prompt_content' => 'Check if the power is connected and temperature settings are correct.',
            'priority' => 10,
            'is_active' => true,
            'usage_count' => 0
        ]);

        // 3. User sends message
        $userMessage = 'My refrigerator not cooling properly';
        $this->sessionService->saveMessage($session->id, 'user', $userMessage);

        // 4. Check prompt matching
        $promptResponse = $this->promptService->findMatchingPrompt($userMessage);
        $this->assertNotNull($promptResponse);
        $this->assertEquals($prompt->prompt_content, $promptResponse['content']);

        // 5. Save assistant response
        $this->sessionService->saveMessage($session->id, 'assistant', $promptResponse['content'], [
            'source' => 'prompt',
            'prompt_id' => $promptResponse['id']
        ]);

        // 6. Verify the workflow
        $this->assertEquals(1, $prompt->fresh()->usage_count);
        $this->assertDatabaseHas('chat_messages', [
            'session_id' => $session->id,
            'sender_type' => 'user',
            'message_content' => $userMessage
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'session_id' => $session->id,
            'sender_type' => 'assistant',
            'message_content' => $prompt->prompt_content
        ]);
    }

    /** @test */
    public function complete_chatbot_workflow_with_faq_fallback()
    {
        // 1. Create session
        $session = $this->sessionService->initializeSession();

        // 2. Create FAQ but no matching prompt
        $faq = FAQ::factory()->create([
            'question' => 'How do I clean my refrigerator?',
            'answer' => 'Use warm soapy water and avoid harsh chemicals.',
            'is_active' => true
        ]);

        // 3. User message that doesn't match any prompts
        $userMessage = 'How do I clean my refrigerator?';
        $this->sessionService->saveMessage($session->id, 'user', $userMessage);

        // 4. Check prompt matching (should fail)
        $promptResponse = $this->promptService->findMatchingPrompt($userMessage);
        $this->assertNull($promptResponse);

        // 5. Check FAQ matching (should succeed)
        $faqResponse = $this->faqService->findMatchingFaq($userMessage);
        $this->assertNotNull($faqResponse);
        $this->assertEquals($faq->answer, $faqResponse['answer']);

        // 6. Save FAQ response
        $this->sessionService->saveMessage($session->id, 'assistant', $faqResponse['answer'], [
            'source' => 'faq',
            'faq_id' => $faqResponse['id']
        ]);

        // 7. Verify workflow
        $this->assertDatabaseHas('chat_messages', [
            'session_id' => $session->id,
            'sender_type' => 'assistant',
            'message_content' => $faq->answer,
            'metadata' => json_encode(['source' => 'faq', 'faq_id' => $faq->id])
        ]);
    }

    /** @test */
    public function complete_chatbot_workflow_with_ai_fallback()
    {
        // 1. Create session
        $session = $this->sessionService->initializeSession();

        // 2. User message that doesn't match prompts or FAQs
        $userMessage = 'What is the meaning of life?';
        $this->sessionService->saveMessage($session->id, 'user', $userMessage);

        // 3. Check prompt and FAQ (both should fail)
        $promptResponse = $this->promptService->findMatchingPrompt($userMessage);
        $faqResponse = $this->faqService->findMatchingFaq($userMessage);
        
        $this->assertNull($promptResponse);
        $this->assertNull($faqResponse);

        // 4. Build conversation context and call AI
        $messages = [
            ['user' => 'You', 'text' => $userMessage, 'time' => '10:00']
        ];
        $prompts = $this->openAiService->buildConversationPrompts($messages);
        $aiResponse = $this->openAiService->generateResponse($prompts);

        // 5. Save AI response
        $this->sessionService->saveMessage($session->id, 'assistant', $aiResponse['content'], [
            'source' => 'openai',
            'model' => 'gpt-4o-mini',
            'tokens' => $aiResponse['tokens']
        ]);

        // 6. Verify workflow
        $this->assertDatabaseHas('chat_messages', [
            'session_id' => $session->id,
            'sender_type' => 'assistant',
            'message_content' => 'AI generated response'
        ]);
    }

    /** @test */
    public function chatbot_prioritizes_responses_correctly()
    {
        $session = $this->sessionService->initializeSession();
        
        // Create both prompt and FAQ that could match
        $prompt = Prompt::factory()->create([
            'trigger_phrase' => 'temperature control',
            'prompt_content' => 'Prompt: Temperature control guidance',
            'priority' => 8,
            'is_active' => true
        ]);

        $faq = FAQ::factory()->create([
            'question' => 'How do I control temperature?',
            'answer' => 'FAQ: Temperature control answer',
            'is_active' => true
        ]);

        $userMessage = 'I need help with temperature control';

        // Simulate the decision logic from the chat component
        $promptResponse = $this->promptService->findMatchingPrompt($userMessage);
        
        if ($promptResponse) {
            $responseSource = 'prompt';
            $responseContent = $promptResponse['content'];
        } else {
            $faqResponse = $this->faqService->findMatchingFaq($userMessage);
            if ($faqResponse) {
                $responseSource = 'faq';
                $responseContent = $faqResponse['answer'];
            } else {
                $responseSource = 'ai';
                $responseContent = 'AI fallback';
            }
        }

        // Should prioritize prompt over FAQ
        $this->assertEquals('prompt', $responseSource);
        $this->assertEquals($prompt->prompt_content, $responseContent);
    }

    /** @test */
    public function chatbot_handles_session_persistence()
    {
        // Simulate multiple interactions in the same session
        $session = $this->sessionService->initializeSession();
        
        // First interaction
        $this->sessionService->saveMessage($session->id, 'user', 'Hello');
        $this->sessionService->saveMessage($session->id, 'assistant', 'Hi there!');
        
        // Second interaction
        $this->sessionService->saveMessage($session->id, 'user', 'How are you?');
        $this->sessionService->saveMessage($session->id, 'assistant', 'I am doing well!');

        // Load history
        $history = $this->sessionService->loadHistory($session->id);
        
        $this->assertCount(4, $history);
        $this->assertEquals('Hello', $history[0]['text']);
        $this->assertEquals('Hi there!', $history[1]['text']);
        $this->assertEquals('How are you?', $history[2]['text']);
        $this->assertEquals('I am doing well!', $history[3]['text']);
    }

    /** @test */
    public function chatbot_handles_concurrent_sessions()
    {
        // Create multiple sessions
        $session1 = ChatSession::factory()->create(['session_id' => 'session_1']);
        $session2 = ChatSession::factory()->create(['session_id' => 'session_2']);

        // Add messages to each session
        $this->sessionService->saveMessage($session1->id, 'user', 'Message from session 1');
        $this->sessionService->saveMessage($session2->id, 'user', 'Message from session 2');

        // Verify isolation
        $history1 = $this->sessionService->loadHistory($session1->id);
        $history2 = $this->sessionService->loadHistory($session2->id);

        $this->assertCount(1, $history1);
        $this->assertCount(1, $history2);
        $this->assertEquals('Message from session 1', $history1[0]['text']);
        $this->assertEquals('Message from session 2', $history2[0]['text']);
    }

    /** @test */
    public function chatbot_tracks_prompt_usage_analytics()
    {
        $session = $this->sessionService->initializeSession();
        
        $prompt = Prompt::factory()->create([
            'trigger_phrase' => 'warranty information',
            'prompt_content' => 'Warranty details...',
            'usage_count' => 5,
            'is_active' => true
        ]);

        // Use the prompt multiple times
        for ($i = 0; $i < 3; $i++) {
            $response = $this->promptService->findMatchingPrompt('I need warranty information');
            $this->assertNotNull($response);
        }

        // Verify usage tracking
        $this->assertEquals(8, $prompt->fresh()->usage_count);
    }

    /** @test */
    public function chatbot_maintains_conversation_flow()
    {
        $session = $this->sessionService->initializeSession();
        
        // Simulate a conversation flow
        $conversation = [
            ['user', 'Hello'],
            ['assistant', 'Hello! How can I help you?'],
            ['user', 'I have a refrigerator problem'],
            ['assistant', 'What seems to be the issue with your refrigerator?'],
            ['user', 'It is not cooling'],
            ['assistant', 'Let me help you troubleshoot that.']
        ];

        foreach ($conversation as [$sender, $message]) {
            $this->sessionService->saveMessage($session->id, $sender, $message);
        }

        $history = $this->sessionService->loadHistory($session->id);
        
        $this->assertCount(6, $history);
        
        // Verify conversation flow structure
        $this->assertEquals('You', $history[0]['user']);
        $this->assertEquals('GAB', $history[1]['user']);
        $this->assertEquals('You', $history[2]['user']);
        $this->assertEquals('GAB', $history[3]['user']);
    }

    /** @test */
    public function chatbot_error_handling_preserves_session_state()
    {
        $session = $this->sessionService->initializeSession();
        
        // Simulate successful interaction
        $this->sessionService->saveMessage($session->id, 'user', 'Working message');
        $this->sessionService->saveMessage($session->id, 'assistant', 'Working response');

        // Simulate error scenario (mock API failure)
        Http::fake([
            'https://api.openai.com/*' => Http::response([], 500)
        ]);

        $errorResponse = $this->openAiService->generateResponse([
            ['role' => 'user', 'content' => 'This will fail']
        ]);

        // Save error response
        $this->sessionService->saveMessage($session->id, 'assistant', $errorResponse['content'], [
            'source' => 'error',
            'error' => true
        ]);

        // Verify session integrity
        $history = $this->sessionService->loadHistory($session->id);
        $this->assertCount(3, $history);
        
        // Session should still be functional
        $this->sessionService->saveMessage($session->id, 'user', 'Recovery message');
        $historyAfterRecovery = $this->sessionService->loadHistory($session->id);
        $this->assertCount(4, $historyAfterRecovery);
    }
}