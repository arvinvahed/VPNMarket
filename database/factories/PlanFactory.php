<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'price' => fake()->numberBetween(20000, 100000),
            'currency' => 'تومان/ماهانه',
            'features' => "ترافیک نامحدود\nسرعت بالا\nپشتیبانی 24/7",
            'is_popular' => fake()->boolean(20),
            'is_active' => true,
            'volume_gb' => fake()->randomElement([30, 50, 100, 200]),
            'duration_days' => fake()->randomElement([30, 60, 90]),
        ];
    }
}
