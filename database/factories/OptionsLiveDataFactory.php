<?php

namespace Database\Factories;

use App\Models\OptionsLiveData;
use Illuminate\Database\Eloquent\Factories\Factory;

class OptionsLiveDataFactory extends Factory
{
    protected $model = OptionsLiveData::class;

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
            'strike' => $this->faker->randomFloat(2, 0, 1000),
            'type' => $this->faker->randomElement(['call', 'put']),
            'delta' => $this->faker->randomFloat(2, -1, 1),
            'gamma' => $this->faker->randomFloat(2, 0, 1),
            'open_interest' => $this->faker->numberBetween(0, 100000),
            'implied_volatility' => $this->faker->randomFloat(2, 0, 1),
            'bearish_volume' => $this->faker->numberBetween(0, 100000),
            'bullish_volume' => $this->faker->numberBetween(0, 100000),
            'current_volume' => $this->faker->numberBetween(0, 100000),
            'last_updated' => $this->faker->dateTimeBetween('-1 day','-1 minute'),
        ];
    }
}
