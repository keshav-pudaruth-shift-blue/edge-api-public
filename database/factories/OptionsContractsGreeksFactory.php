<?php

namespace Database\Factories;

use App\Models\OptionsContracts;
use App\Models\OptionsContractsGreeks;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OptionsContractsGreeks>
 */
class OptionsContractsGreeksFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'options_contracts_id' => OptionsContracts::factory(),
            'delta' => $this->faker->randomFloat(2, -1, 1),
            'gamma' => $this->faker->randomFloat(2, 0, 1),
            'theta' => $this->faker->randomFloat(2, -1, 1),
            'vega' => $this->faker->randomFloat(2, 0, 1),
            'open_interest' => $this->faker->numberBetween(0, 100000),
            'implied_volatility' => $this->faker->randomFloat(2, 0, 1),
            'volume' => $this->faker->numberBetween(0, 100000),
        ];
    }
}
