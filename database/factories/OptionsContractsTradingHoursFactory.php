<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\OptionsContracts>
 */
class OptionsContractsTradingHoursFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'timezone' => $this->faker->timezone,
            'start_datetime' => $this->faker->dateTimeBetween('-2 days', '-1 day')->format('Y-m-d'),
            'end_datetime' => $this->faker->dateTimeBetween('+1 day', '+1 days')->format('Y-m-d'),
        ];
    }
}
