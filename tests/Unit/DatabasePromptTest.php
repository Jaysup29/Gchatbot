<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Prompt;
use App\Services\PromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class DatabasePromptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create only the prompts table manually
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
            
            // Add fulltext index if using MySQL
            if (config('database.default') === 'mysql') {
                $table->fullText(['trigger_phrase', 'prompt_content']);
            }
        });
    }

    /** @test */
    public function prompt_service_finds_exact_matches()
    {
        // Create test prompt
        $prompt = Prompt::create([
            'trigger_phrase' => 'refrigerator repair',
            'prompt_content' => 'Here are the refrigerator repair steps...',
            'prompt_type' => 'response',
            'priority' => 10,
            'is_active' => true,
            'usage_count' => 0,
            'metadata' => []
        ]);

        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('I need refrigerator repair help');

        $this->assertNotNull($result);
        $this->assertEquals('Here are the refrigerator repair steps...', $result['content']);
        $this->assertEquals($prompt->id, $result['id']);
        
        // Verify usage count was incremented
        $this->assertEquals(1, $prompt->fresh()->usage_count);
    }

    /** @test */
    public function prompt_service_respects_priority_ordering()
    {
        // Create two prompts with different priorities
        $lowPriority = Prompt::create([
            'trigger_phrase' => 'freezer issue',
            'prompt_content' => 'Low priority freezer help',
            'priority' => 3,
            'is_active' => true,
            'metadata' => []
        ]);

        $highPriority = Prompt::create([
            'trigger_phrase' => 'freezer issue',
            'prompt_content' => 'High priority freezer help',
            'priority' => 8,
            'is_active' => true,
            'metadata' => []
        ]);

        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('I have a freezer issue');

        $this->assertNotNull($result);
        $this->assertEquals('High priority freezer help', $result['content']);
        $this->assertEquals($highPriority->id, $result['id']);
    }

    /** @test */
    public function prompt_service_ignores_inactive_prompts()
    {
        // Create active and inactive prompts
        $activePrompt = Prompt::create([
            'trigger_phrase' => 'warranty info',
            'prompt_content' => 'Active warranty information',
            'priority' => 5,
            'is_active' => true,
            'metadata' => []
        ]);

        $inactivePrompt = Prompt::create([
            'trigger_phrase' => 'warranty info',
            'prompt_content' => 'Inactive warranty information',
            'priority' => 10,
            'is_active' => false,
            'metadata' => []
        ]);

        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('I need warranty info');

        $this->assertNotNull($result);
        $this->assertEquals('Active warranty information', $result['content']);
        $this->assertEquals($activePrompt->id, $result['id']);
    }

    /** @test */
    public function prompt_service_handles_no_matches()
    {
        // Create a prompt that won't match
        Prompt::create([
            'trigger_phrase' => 'very specific technical term',
            'prompt_content' => 'Technical response',
            'priority' => 5,
            'is_active' => true,
            'metadata' => []
        ]);

        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('completely different topic');

        $this->assertNull($result);
    }
}
