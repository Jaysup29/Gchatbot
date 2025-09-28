<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PromptService;
use App\Services\ChatSessionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChatbotWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        restore_error_handler();
        restore_exception_handler();
        $this->createMinimalTables();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('prompts');
        Schema::dropIfExists('chat_sessions');
        Schema::dropIfExists('chat_messages');
        parent::tearDown();
    }

    private function createMinimalTables()
    {
        // Create prompts table
        DB::statement('
            CREATE TABLE prompts (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                trigger_phrase text NOT NULL,
                prompt_content text NOT NULL,
                prompt_type varchar(255) NOT NULL DEFAULT "response",
                priority int NOT NULL DEFAULT 5,
                is_active tinyint(1) NOT NULL DEFAULT 1,
                metadata json DEFAULT NULL,
                usage_count int NOT NULL DEFAULT 0,
                created_at timestamp NULL DEFAULT NULL,
                updated_at timestamp NULL DEFAULT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');

        // Create chat_sessions table
        DB::statement('
            CREATE TABLE chat_sessions (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                session_id varchar(255) NOT NULL,
                ip_address varchar(45) DEFAULT NULL,
                user_agent text,
                is_authenticated tinyint(1) NOT NULL DEFAULT 0,
                started_at timestamp NULL DEFAULT NULL,
                last_activity_at timestamp NULL DEFAULT NULL,
                created_at timestamp NULL DEFAULT NULL,
                updated_at timestamp NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY chat_sessions_session_id_unique (session_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');

        // Create chat_messages table
        DB::statement('
            CREATE TABLE chat_messages (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                session_id bigint unsigned NOT NULL,
                sender_type enum("user","assistant") NOT NULL,
                message_content text NOT NULL,
                metadata json DEFAULT NULL,
                sent_at timestamp NULL DEFAULT NULL,
                created_at timestamp NULL DEFAULT NULL,
                updated_at timestamp NULL DEFAULT NULL,
                deleted_at timestamp NULL DEFAULT NULL,
                PRIMARY KEY (id),
                KEY chat_messages_session_id_foreign (session_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');

        try {
            DB::statement('ALTER TABLE prompts ADD FULLTEXT(trigger_phrase, prompt_content)');
        } catch (\Exception $e) {
            // Ignore fulltext errors
        }
    }

    /** @test */
    public function complete_chatbot_workflow_with_prompts()
    {
        // 1. Create test prompts
        DB::table('prompts')->insert([
            [
                'trigger_phrase' => 'refrigerator not cooling',
                'prompt_content' => 'Check power connection and temperature dial. Ensure vents are not blocked.',
                'priority' => 10,
                'is_active' => 1,
                'usage_count' => 0,
                'metadata' => '{"category": "cooling"}',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'trigger_phrase' => 'ice maker problems',
                'prompt_content' => 'Reset ice maker by unplugging for 5 minutes. Check water line connections.',
                'priority' => 8,
                'is_active' => 1,
                'usage_count' => 0,
                'metadata' => '{"category": "ice"}',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // 2. Test high-scoring prompt match
        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('My refrigerator not cooling properly');

        $this->assertNotNull($result);
        $this->assertStringContains('Check power connection', $result['content']);
        
        // 3. Verify usage analytics
        $prompt = DB::table('prompts')->where('trigger_phrase', 'refrigerator not cooling')->first();
        $this->assertEquals(1, $prompt->usage_count);

        // 4. Test different prompt
        $result2 = $promptService->findMatchingPrompt('ice maker problems today');
        $this->assertNotNull($result2);
        $this->assertStringContains('Reset ice maker', $result2['content']);

        // 5. Test no match scenario
        $result3 = $promptService->findMatchingPrompt('completely unrelated query about weather');
        $this->assertNull($result3);
    }

    /** @test */
    public function chat_session_workflow()
    {
        $sessionService = new ChatSessionService();
        
        // Create session
        $session = $sessionService->initializeSession();
        $this->assertNotNull($session);
        $this->assertDatabaseHas('chat_sessions', [
            'id' => $session->id,
            'is_authenticated' => false
        ]);

        // Save user message
        $userMessage = $sessionService->saveMessage($session->id, 'user', 'Hello, I need help');
        $this->assertDatabaseHas('chat_messages', [
            'session_id' => $session->id,
            'sender_type' => 'user',
            'message_content' => 'Hello, I need help'
        ]);

        // Save assistant response
        $assistantMessage = $sessionService->saveMessage($session->id, 'assistant', 'How can I help you?');
        $this->assertDatabaseHas('chat_messages', [
            'session_id' => $session->id,
            'sender_type' => 'assistant',
            'message_content' => 'How can I help you?'
        ]);

        // Load history
        $history = $sessionService->loadHistory($session->id);
        $this->assertCount(2, $history);
        $this->assertEquals('You', $history[0]['user']);
        $this->assertEquals('GAB', $history[1]['user']);
    }

    /** @test */
    public function end_to_end_chatbot_conversation_flow()
    {
        // Setup: Create prompt and session
        DB::table('prompts')->insert([
            'trigger_phrase' => 'warranty information',
            'prompt_content' => 'Your Glacier refrigerator comes with a 5-year warranty covering parts and labor.',
            'priority' => 9,
            'is_active' => 1,
            'usage_count' => 0,
            'metadata' => '{"category": "warranty"}',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $sessionService = new ChatSessionService();
        $promptService = new PromptService();

        // 1. User starts conversation
        $session = $sessionService->initializeSession();
        $userMessage = 'I need warranty information for my refrigerator';
        $sessionService->saveMessage($session->id, 'user', $userMessage);

        // 2. System finds matching prompt
        $promptResponse = $promptService->findMatchingPrompt($userMessage);
        $this->assertNotNull($promptResponse);
        $this->assertStringContains('5-year warranty', $promptResponse['content']);

        // 3. System saves response
        $sessionService->saveMessage($session->id, 'assistant', $promptResponse['content'], [
            'source' => 'prompt',
            'prompt_id' => $promptResponse['id']
        ]);

        // 4. Verify complete conversation
        $history = $sessionService->loadHistory($session->id);
        $this->assertCount(2, $history);
        $this->assertEquals($userMessage, $history[0]['text']);
        $this->assertStringContains('5-year warranty', $history[1]['text']);

        // 5. Verify analytics
        $prompt = DB::table('prompts')->where('trigger_phrase', 'warranty information')->first();
        $this->assertEquals(1, $prompt->usage_count);
    }

    /** @test */
    public function prompt_priority_system_works_in_practice()
    {
        // Create competing prompts
        DB::table('prompts')->insert([
            [
                'trigger_phrase' => 'temperature',
                'prompt_content' => 'Low priority temperature help',
                'priority' => 3,
                'is_active' => 1,
                'usage_count' => 0,
                'metadata' => '{}',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'trigger_phrase' => 'temperature control',
                'prompt_content' => 'High priority temperature control help',
                'priority' => 9,
                'is_active' => 1,
                'usage_count' => 0,
                'metadata' => '{}',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('temperature control issues');

        // Should get the high priority one
        $this->assertNotNull($result);
        $this->assertEquals('High priority temperature control help', $result['content']);
    }
}
