<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'session_id' => ChatSession::factory(),
            'sender_type' => $this->faker->randomElement(['user', 'assistant']),
            'message_content' => $this->faker->paragraph(),
            'metadata' => [
                'source' => $this->faker->randomElement(['user', 'openai', 'prompt', 'faq']),
                'tokens' => $this->faker->numberBetween(10, 500),
                'model' => 'gpt-4o-mini'
            ],
            'sent_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ];
    }

    public function userMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_type' => 'user',
            'metadata' => [
                'source' => 'user',
                'input_method' => $this->faker->randomElement(['keyboard', 'voice'])
            ],
        ]);
    }

    public function assistantMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_type' => 'assistant',
            'metadata' => [
                'source' => $this->faker->randomElement(['openai', 'prompt', 'faq']),
                'tokens' => $this->faker->numberBetween(10, 500),
                'model' => 'gpt-4o-mini'
            ],
        ]);
    }

    public function promptResponse(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_type' => 'assistant',
            'metadata' => [
                'source' => 'prompt',
                'prompt_id' => $this->faker->numberBetween(1, 100)
            ],
        ]);
    }

    public function faqResponse(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_type' => 'assistant',
            'metadata' => [
                'source' => 'faq',
                'faq_id' => $this->faker->numberBetween(1, 50)
            ],
        ]);
    }

    public function aiResponse(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_type' => 'assistant',
            'metadata' => [
                'source' => 'openai',
                'tokens' => $this->faker->numberBetween(50, 500),
                'model' => 'gpt-4o-mini'
            ],
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'sent_at' => now()->subMinutes($this->faker->numberBetween(1, 30)),
        ]);
    }
}
