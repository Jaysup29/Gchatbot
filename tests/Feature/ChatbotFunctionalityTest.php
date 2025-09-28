<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\Prompt;
use App\Models\FAQ;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Services\PromptService;
use App\Services\FaqService;
use App\Services\OpenAIService;
use App\Services\ChatSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class ChatbotFunctionalityTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Start a session for testing
        Session::start();
        
        // Mock HTTP responses for OpenAI
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Mocked AI response for testing'
                        ]
                    ]
                ],
                'usage' => [
                    'total_tokens' => 50
                ]
            ], 200)
        ]);
    }

    /** @test */
    public function chatbot_initializes_session_correctly()
    {
        $component = Livewire::test('chat');

        $component->assertSet('messages', []);
        $component->assertSet('newMessage', '');
        $component->assertSet('isTyping', false);
        $component->assertSet('isSubmitted', false);
        $component->assertSet('isListening', false);
        
        // Check that a chat session was created
        $this->assertDatabaseHas('chat_sessions', [
            'session_id' => session()->getId(),
            'is_authenticated' => false
        ]);
    }

    /** @test */
    public function chatbot_processes_message_and_finds_matching_prompt()
    {
        // Create a prompt that should match
        $prompt = Prompt::factory()->create([
            'trigger_phrase' => 'refrigerator repair',
            'prompt_content' => 'Here is how to repair your refrigerator...',
            'priority' => 10,
            'is_active' => true,
            'usage_count' => 0
        ]);

        $component = Livewire::test('chat');

        $component->set('newMessage', 'I need refrigerator repair help')
                 ->call('send');

        // Check that user message was added
        $component->assertCount('messages', 2); // User + Bot response
        
        // Check that the prompt response was used
        $component->assertSee('Here is how to repair your refrigerator...');
        
        // Verify message was saved to database
        $this->assertDatabaseHas('chat_messages', [
            'sender_type' => 'user',
            'message_content' => 'I need refrigerator repair help'
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'sender_type' => 'assistant',
            'message_content' => 'Here is how to repair your refrigerator...'
        ]);

        // Verify prompt usage count was incremented
        $this->assertEquals(1, $prompt->fresh()->usage_count);
    }

    /** @test */
    public function chatbot_falls_back_to_ai_when_no_prompt_or_faq_matches()
    {
        $component = Livewire::test('chat');

        $component->set('newMessage', 'What is the meaning of life?')
                 ->call('send');

        $component->assertCount('messages', 2);
        $component->assertSee('Mocked AI response for testing');

        // Verify AI metadata was saved
        $this->assertDatabaseHas('chat_messages', [
            'sender_type' => 'assistant',
            'message_content' => 'Mocked AI response for testing'
        ]);
    }

    /** @test */
    public function chatbot_handles_voice_recognition_toggle()
    {
        $component = Livewire::test('chat');

        $component->assertSet('isListening', false);
        
        $component->call('toggleVoice');
        $component->assertSet('isListening', true);
        
        $component->call('toggleVoice');
        $component->assertSet('isListening', false);
    }

    /** @test */
    public function chatbot_clears_chat_history()
    {
        // Create some chat messages first
        $component = Livewire::test('chat');
        $component->set('newMessage', 'Test message')
                 ->call('send');

        $this->assertNotEmpty($component->get('messages'));

        // Clear chat
        $component->call('clearChat');

        $component->assertSet('messages', []);
        
        // Verify messages were soft deleted in database
        $this->assertDatabaseHas('chat_messages', [
            'message_content' => 'Test message'
        ]);
    }

    /** @test */
    public function chatbot_prioritizes_prompts_over_faqs()
    {
        // Create both a prompt and FAQ that could match
        $prompt = Prompt::factory()->create([
            'trigger_phrase' => 'temperature control',
            'prompt_content' => 'Prompt response about temperature',
            'priority' => 5,
            'is_active' => true
        ]);

        $faq = FAQ::factory()->create([
            'question' => 'temperature control issues',
            'answer' => 'FAQ response about temperature',
            'is_active' => true
        ]);

        $component = Livewire::test('chat');

        $component->set('newMessage', 'I have temperature control problems')
                 ->call('send');

        // Should use prompt response, not FAQ
        $component->assertSee('Prompt response about temperature');
        $component->assertDontSee('FAQ response about temperature');
    }

    /** @test */
    public function chatbot_prevents_sending_empty_messages()
    {
        $component = Livewire::test('chat');

        $initialMessageCount = count($component->get('messages'));

        // Try to send empty message
        $component->set('newMessage', '')
                 ->call('send');

        $component->assertCount('messages', $initialMessageCount);

        // Try to send whitespace-only message
        $component->set('newMessage', '   ')
                 ->call('send');

        $component->assertHasErrors('newMessage');
    }
}
