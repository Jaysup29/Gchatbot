<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PromptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NoMigrationPromptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Restore error handling to default state
        restore_error_handler();
        restore_exception_handler();
        // Do NOT call migrations at all
        $this->createOnlyPromptsTable();
    }

    protected function tearDown(): void
    {
        // Clean up our table
        Schema::dropIfExists('prompts');
        parent::tearDown();
    }

    private function createOnlyPromptsTable()
    {
        // Drop and recreate only the prompts table
        Schema::dropIfExists('prompts');
        
        // Create prompts table manually without any foreign keys
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

        // Add fulltext index
        try {
            DB::statement('ALTER TABLE prompts ADD FULLTEXT(trigger_phrase, prompt_content)');
        } catch (\Exception $e) {
            // Ignore if fulltext fails
        }
    }

    /** @test */
    public function prompt_service_finds_matching_prompts()
    {
        // Insert test data
        DB::table('prompts')->insert([
            'trigger_phrase' => 'refrigerator repair',
            'prompt_content' => 'Here are refrigerator repair instructions...',
            'prompt_type' => 'response',
            'priority' => 10,
            'is_active' => 1,
            'usage_count' => 0,
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('I need refrigerator repair help');

        $this->assertNotNull($result);
        $this->assertEquals('Here are refrigerator repair instructions...', $result['content']);
        
        // Check usage was incremented
        $prompt = DB::table('prompts')->where('trigger_phrase', 'refrigerator repair')->first();
        $this->assertEquals(1, $prompt->usage_count);
    }

    /** @test */
    public function prompt_service_returns_null_for_low_scores()
    {
        // Insert a very specific prompt
        DB::table('prompts')->insert([
            'trigger_phrase' => 'very specific technical term xyz',
            'prompt_content' => 'Very specific response',
            'prompt_type' => 'response',
            'priority' => 5,
            'is_active' => 1,
            'usage_count' => 0,
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('hello world');

        $this->assertNull($result);
    }

    /** @test */
    public function prompt_service_prioritizes_higher_priority_prompts()
    {
        // Insert low priority prompt
        DB::table('prompts')->insert([
            'trigger_phrase' => 'temperature control',
            'prompt_content' => 'Low priority temperature help',
            'priority' => 3,
            'is_active' => 1,
            'usage_count' => 0,
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Insert high priority prompt
        DB::table('prompts')->insert([
            'trigger_phrase' => 'temperature control',
            'prompt_content' => 'High priority temperature help',
            'priority' => 8,
            'is_active' => 1,
            'usage_count' => 0,
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('temperature control issues');

        $this->assertNotNull($result);
        $this->assertEquals('High priority temperature help', $result['content']);
    }

    /** @test */
    public function prompt_service_ignores_inactive_prompts()
    {
        // Insert inactive prompt with high priority
        DB::table('prompts')->insert([
            'trigger_phrase' => 'warranty information',
            'prompt_content' => 'Inactive warranty response',
            'priority' => 10,
            'is_active' => 0, // INACTIVE
            'usage_count' => 0,
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('warranty information needed');

        $this->assertNull($result);
    }

    /** @test */
    public function prompt_service_handles_case_insensitive_matching()
    {
        DB::table('prompts')->insert([
            'trigger_phrase' => 'energy efficiency',
            'prompt_content' => 'Energy saving tips and advice',
            'priority' => 5,
            'is_active' => 1,
            'usage_count' => 0,
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $promptService = new PromptService();
        
        $result1 = $promptService->findMatchingPrompt('ENERGY EFFICIENCY help');
        $result2 = $promptService->findMatchingPrompt('Energy Efficiency guide');
        
        $this->assertNotNull($result1);
        $this->assertNotNull($result2);
        $this->assertEquals('Energy saving tips and advice', $result1['content']);
        $this->assertEquals('Energy saving tips and advice', $result2['content']);
    }

    /** @test */
    public function prompt_service_handles_multiple_trigger_phrases()
    {
        DB::table('prompts')->insert([
            'trigger_phrase' => 'ice maker, ice machine, ice dispenser',
            'prompt_content' => 'Ice maker troubleshooting guide',
            'priority' => 7,
            'is_active' => 1,
            'usage_count' => 0,
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $promptService = new PromptService();
        
        $result1 = $promptService->findMatchingPrompt('ice maker problems');
        $result2 = $promptService->findMatchingPrompt('ice machine not working');
        $result3 = $promptService->findMatchingPrompt('ice dispenser issues');
        
        $this->assertNotNull($result1);
        $this->assertNotNull($result2);
        $this->assertNotNull($result3);
        
        $this->assertEquals('Ice maker troubleshooting guide', $result1['content']);
        $this->assertEquals('Ice maker troubleshooting guide', $result2['content']);
        $this->assertEquals('Ice maker troubleshooting guide', $result3['content']);
    }
}
