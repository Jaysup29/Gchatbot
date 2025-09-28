<?php

namespace Database\Factories;

use App\Models\ChatSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatSessionFactory extends Factory
{
    protected $model = ChatSession::class;

    public function definition(): array
    {
        return [
            'session_id' => $this->faker->uuid(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'is_authenticated' => $this->faker->boolean(30), // 30% chance of being authenticated
            'started_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'last_activity_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ];
    }

    public function authenticated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_authenticated' => true,
        ]);
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_authenticated' => false,
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => now()->subHours(2),
            'last_activity_at' => now()->subMinutes(5),
        ]);
    }

    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => now()->subDays(7),
            'last_activity_at' => now()->subDays(6),
        ]);
    }
}
