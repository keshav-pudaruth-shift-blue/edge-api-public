<?php

namespace Database\Factories;

use App\Models\OptionsContracts;
use App\Models\OptionsContractsHistoricalData;
use Illuminate\Database\Eloquent\Factories\Factory;

class OptionsContractsHistoricalDataFactory extends Factory
{
    protected $model = OptionsContractsHistoricalData::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'options_contracts_id' => OptionsContracts::factory(),
            'datetime' => $this->faker->dateTimeBetween('-1 day','-1 minute'),
            'open' => $this->faker->randomFloat(2, 0.1, 1000),
            'high' => $this->faker->randomFloat(2, 0.1, 1000),
            'low' => $this->faker->randomFloat(2, 0.1, 1000),
            'close' => $this->faker->randomFloat(2, 0.1, 1000),
            'volume' => $this->faker->numberBetween(0, 10000),
        ];
    }
}
{

}
