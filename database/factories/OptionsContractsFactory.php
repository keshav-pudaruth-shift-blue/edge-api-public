<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\OptionsContracts>
 */
class OptionsContractsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'symbol' => $this->faker->randomElement(['AAPL', 'AMZN', 'GOOG', 'MSFT', 'TSLA', 'SPY', 'QQQ']),
            'expiry_date' => $this->faker->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'strike_price' => $this->faker->randomFloat(2, 1, 1000),
            'option_type' => $this->faker->randomElement(['call', 'put']),
            'contract_id' => $this->faker->randomNumber(9),
        ];
    }
}
