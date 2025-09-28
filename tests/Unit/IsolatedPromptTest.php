<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class IsolatedPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPromptsTableOnly();
    }

    private function createPromptsTableOnly()
    {
        // Drop table if exists and create fresh
        Schema::dropIfExists('prompts');
        
        Schema::create('prompts', function (Blueprint $table) {
            $table->id();
            $table->text('trigger_phrase');
            $table->text('prompt_content');
            $table->string('prompt_type')->default('response');
            $table->integer('priority')->default(5);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->integer('usage_count')->default(0);
            $table->timestamps();
        });

        // Add fulltext index separately for MySQL
        if (config('database.default') === 'mysql') {
            try {
                DB::statement('ALTER TABLE prompts ADD FULLTEXT(trigger_phrase, prompt_content)');
            } catch (\Exception $e) {
                // Ignore if fulltext fails
            }
        }
    }

    /** @test */
    public function test_prompt_matching_works_end_to_end()
    {
        // Insert test data directly
        DB::table('prompts')->insert([
            'trigger_phrase' => 'refrigerator repair',
            'prompt_content' => 'Step 1: Check power connection...',
            'prompt_type' => 'response',
            'priority' => 10,
            'is_active' => 1,
            'usage_count' => 0,
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Test the service
        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('My refrigerator repair is needed');

        // Verify results
        $this->assertNotNull($result, 'Should find a matching prompt');
        $this->assertEquals('Step 1: Check power connection...', $result['content']);
        $this->assertIsInt($result['id']);

        // Verify usage count was incremented
        $prompt = DB::table('prompts')->where('id', $result['id'])->first();
        $this->assertEquals(1, $prompt->usage_count);
    }

    /** @test */
    public function test_no_match_returns_null()
    {
        // Insert unrelated prompt
        DB::table('prompts')->insert([
            'trigger_phrase' => 'washing machine problem',
            'prompt_content' => 'Washing machine help...',
            'prompt_type' => 'response',
            'priority' => 5,
            'is_active' => 1,
            'usage_count' => 0,
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('completely different topic');

        $this->assertNull($result, 'Should return null for unrelated queries');
    }

    /** @test */
    public function test_inactive_prompts_are_ignored()
    {
        // Insert inactive prompt
        DB::table('prompts')->insert([
            'trigger_phrase' => 'freezer temperature',
            'prompt_content' => 'Inactive freezer help...',
            'prompt_type' => 'response',
            'priority' => 10,
            'is_active' => 0, // Inactive
            'usage_count' => 0,
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('freezer temperature issues');

        $this->assertNull($result, 'Should ignore inactive prompts');
    }

    /** @test */
    public function test_priority_ordering_works()
    {
        // Insert low priority prompt
        DB::table('prompts')->insert([
            'trigger_phrase' => 'ice maker',
            'prompt_content' => 'Low priority ice maker help',
            'priority' => 3,
            'is_active' => 1,
            'usage_count' => 0,
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Insert high priority prompt
        DB::table('prompts')->insert([
            'trigger_phrase' => 'ice maker',
            'prompt_content' => 'High priority ice maker help',
            'priority' => 8,
            'is_active' => 1,
            'usage_count' => 0,
            'metadata' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('ice maker problems');

        $this->assertNotNull($result);
        $this->assertEquals('High priority ice maker help', $result['content']);
    }
}
