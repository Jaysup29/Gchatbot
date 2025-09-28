<?php

namespace Database\Factories;

use App\Models\FAQ;
use Illuminate\Database\Eloquent\Factories\Factory;

class FAQFactory extends Factory
{
    protected $model = FAQ::class;

    public function definition(): array
    {
        return [
            'question' => $this->faker->sentence() . '?',
            'answer' => $this->faker->paragraph(),
            'category' => $this->faker->randomElement(['general', 'technical', 'warranty', 'maintenance', 'troubleshooting']),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'view_count' => $this->faker->numberBetween(0, 1000),
            'helpful_count' => $this->faker->numberBetween(0, 100),
            'not_helpful_count' => $this->faker->numberBetween(0, 20),
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

    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'view_count' => $this->faker->numberBetween(500, 2000),
            'helpful_count' => $this->faker->numberBetween(50, 200),
        ]);
    }

    public function technical(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'technical',
            'question' => 'How do I ' . $this->faker->words(3, true) . '?',
        ]);
    }

    public function warranty(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'warranty',
            'question' => 'What is covered under ' . $this->faker->words(2, true) . ' warranty?',
        ]);
    }
}
