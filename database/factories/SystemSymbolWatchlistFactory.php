<?php

namespace Database\Factories;

use App\Models\SystemSymbolWatchlist;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemSymbolWatchlistFactory extends Factory
{
    protected $model = SystemSymbolWatchlist::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'symbol' => $this->faker->randomElement(['AAPL', 'AMZN', 'GOOG', 'MSFT', 'TSLA', 'SPY', 'QQQ']),
        ];
    }
}
