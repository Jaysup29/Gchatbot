<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Prompt;
use App\Services\PromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class SimplePromptTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip user-related migrations if they cause issues
        $this->artisan('migrate', [
            '--path' => 'database/migrations',
            '--realpath' => true
        ])->run();
    }

    /** @test */
    public function prompt_service_finds_basic_matches()
    {
        // Create test data directly without factories if needed
        $prompt = new \App\Models\Prompt();
        $prompt->trigger_phrase = 'refrigerator repair';
        $prompt->prompt_content = 'Test repair instructions';
        $prompt->prompt_type = 'response';
        $prompt->priority = 10;
        $prompt->is_active = true;
        $prompt->usage_count = 0;
        $prompt->metadata = [];
        $prompt->save();

        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('I need refrigerator repair help');

        $this->assertNotNull($result);
        $this->assertEquals('Test repair instructions', $result['content']);
        $this->assertEquals($prompt->id, $result['id']);
    }

    /** @test */
    public function prompt_service_returns_null_for_no_matches()
    {
        $promptService = new PromptService();
        $result = $promptService->findMatchingPrompt('completely unrelated query');

        $this->assertNull($result);
    }

    /** @test */
    public function prompt_service_handles_case_insensitive_matching()
    {
        $prompt = new \App\Models\Prompt();
        $prompt->trigger_phrase = 'energy efficiency';
        $prompt->prompt_content = 'Energy saving tips';
        $prompt->prompt_type = 'response';
        $prompt->priority = 5;
        $prompt->is_active = true;
        $prompt->usage_count = 0;
        $prompt->metadata = [];
        $prompt->save();

        $promptService = new PromptService();
        
        $result1 = $promptService->findMatchingPrompt('ENERGY EFFICIENCY help');
        $result2 = $promptService->findMatchingPrompt('Energy Efficiency guide');
        
        $this->assertNotNull($result1);
        $this->assertNotNull($result2);
        $this->assertEquals('Energy saving tips', $result1['content']);
        $this->assertEquals('Energy saving tips', $result2['content']);
    }
}
