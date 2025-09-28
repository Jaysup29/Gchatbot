<?php

namespace Database\Factories;

use App\Models\Prompt;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromptFactory extends Factory
{
    protected $model = Prompt::class;

    public function definition(): array
    {
        $types = ['system', 'response', 'instruction'];
        
        return [
            'trigger_phrase' => $this->faker->words(3, true),
            'prompt_content' => $this->faker->paragraph(),
            'prompt_type' => $this->faker->randomElement($types),
            'priority' => $this->faker->numberBetween(1, 10),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'metadata' => [
                'created_by' => 'system',
                'category' => $this->faker->word(),
                'tags' => $this->faker->words(2)
            ],
            'usage_count' => $this->faker->numberBetween(0, 100),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'prompt_type' => 'system',
        ]);
    }

    public function response(): static
    {
        return $this->state(fn (array $attributes) => [
            'prompt_type' => 'response',
        ]);
    }

    public function instruction(): static
    {
        return $this->state(fn (array $attributes) => [
            'prompt_type' => 'instruction',
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $this->faker->numberBetween(8, 10),
        ]);
    }

    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $this->faker->numberBetween(1, 3),
        ]);
    }

    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => $this->faker->numberBetween(50, 200),
        ]);
    }

    public function unused(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => 0,
        ]);
    }
}
