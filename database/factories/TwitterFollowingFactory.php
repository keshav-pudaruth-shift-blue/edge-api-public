<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
class TwitterFollowingFactory extends Factory
{
    protected $model = \App\Models\TwitterFollowing::class;

    public function definition(): array
    {
        return [
            'username' => $this->faker->userName,
            'interval' => $this->faker->randomDigitNotNull(),
            'last_tweet_datetime' => now()->subDay(),
            'is_active' => true
        ];
    }
}
