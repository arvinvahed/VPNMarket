<?php

namespace Database\Factories;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PromoCode>
 */
class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('????##')),
            'description' => fake()->sentence(),
            'discount_type' => fake()->randomElement(['percent', 'fixed']),
            'discount_value' => fake()->numberBetween(5, 50),
            'currency' => 'تومان',
            'max_uses' => fake()->optional()->numberBetween(10, 100),
            'max_uses_per_user' => fake()->optional()->numberBetween(1, 5),
            'uses_count' => 0,
            'start_at' => null,
            'expires_at' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'active' => true,
            'applies_to' => 'all',
            'plan_id' => null,
            'provider_id' => null,
            'created_by_admin_id' => null,
        ];
    }

    /**
     * Indicate that the promo code is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Indicate that the promo code is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    /**
     * Indicate that the promo code is for a specific plan.
     */
    public function forPlan(int $planId): static
    {
        return $this->state(fn (array $attributes) => [
            'applies_to' => 'plan',
            'plan_id' => $planId,
        ]);
    }
}
