<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'pending',
            'source' => 'web',
            'payment_method' => null,
            'card_payment_receipt' => null,
            'nowpayments_payment_id' => null,
            'config_details' => null,
            'amount' => null,
            'expires_at' => null,
            'promo_code_id' => null,
            'discount_amount' => null,
            'original_amount' => null,
        ];
    }

    /**
     * Indicate that the order is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'payment_method' => 'wallet',
            'expires_at' => now()->addDays(30),
        ]);
    }
}
