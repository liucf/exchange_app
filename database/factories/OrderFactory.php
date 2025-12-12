<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'side' => fake()->randomElement(['buy', 'sell']),
            'price' => fake()->randomFloat(2, 1000, 50000),
            'amount' => fake()->randomFloat(8, 0.01, 5),
            'status' => 1, // default to open
        ];
    }
}
