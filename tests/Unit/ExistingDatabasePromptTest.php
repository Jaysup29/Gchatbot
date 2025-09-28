<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PromptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExistingDatabasePromptTest extends TestCase
{
    /** @test */
    public function test_prompt_service_with_existing_database()
    {
        // Check if prompts table exists
        if (!Schema::hasTable('prompts')) {
            $this->markTestSkipped('Prompts table does not exist');
        }

        // Clear any existing test data
        DB::table('prompts')->where('trigger_phrase', 'LIKE', 'test_%')->delete();

        // Insert a test prompt
        $promptId = DB::table('prompts')->insertGetId([
            'trigger_phrase' => 'test_refrigerator_repair',
            'prompt_content' => 'Test: Check the power connection and temperature settings.',
            'prompt_type' => 'response',
            'priority' => 10,
            'is_active' => 1,
            'usage_count' => 0,
            'metadata' => json_encode(['test' => true]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        try {
            // Test the service
            $promptService = new PromptService();
            $result = $promptService->findMatchingPrompt('I need test refrigerator repair help');

            // Verify results
            $this->assertNotNull($result, 'Should find the test prompt');
            $this->assertStringContains('Test: Check the power', $result['content']);
            $this->assertEquals($promptId, $result['id']);

            // Verify usage count was incremented
            $prompt = DB::table('prompts')->where('id', $promptId)->first();
            $this->assertEquals(1, $prompt->usage_count);

        } finally {
            // Clean up test data
            DB::table('prompts')->where('id', $promptId)->delete();
        }
    }

    /** @test */
    public function test_scoring_threshold_enforcement()
    {
        if (!Schema::hasTable('prompts')) {
            $this->markTestSkipped('Prompts table does not exist');
        }

        // Insert a prompt with very specific trigger
        $promptId = DB::table('prompts')->insertGetId([
            'trigger_phrase' => 'very_specific_technical_term_xyz123',
            'prompt_content' => 'Very specific technical response',
            'prompt_type' => 'response',
            'priority' => 5,
            'is_active' => 1,
            'usage_count' => 0,
            'metadata' => json_encode(['test' => true]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        try {
            $promptService = new PromptService();
            
            // Test with unrelated query (should return null)
            $result = $promptService->findMatchingPrompt('hello world general greeting');
            $this->assertNull($result, 'Should return null for low-scoring matches');

        } finally {
            // Clean up
            DB::table('prompts')->where('id', $promptId)->delete();
        }
    }
}
