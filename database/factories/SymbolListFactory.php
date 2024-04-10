<?php

namespace Database\Factories;

use App\Models\SymbolList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SymbolList>
 */
class SymbolListFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['AAPL', 'AMZN', 'GOOG', 'MSFT', 'TSLA', 'SPY', 'QQQ']),
            'company_name' => $this->faker->company,
            'has_options' => 1,
            'exchange' => $this->faker->randomElement(['CBOE', 'NYSE', 'CME']),
            'sync_options_greeks_enabled' => true,
            'sync_options_historical_data_delayed_enabled' => true,
            'sync_options_historical_data_live_0_dte_enabled' => true,
            'is_index' => $this->faker->boolean,
            'is_enabled' => true,
            'options_update_strike_range_0_dte' => 50,
            'options_update_strike_range_1_dte' => 50,
        ];
    }
}
