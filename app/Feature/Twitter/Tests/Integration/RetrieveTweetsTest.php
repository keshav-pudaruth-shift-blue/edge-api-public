<?php

namespace App\Feature\Twitter\Tests\Integration;

use App\Feature\Twitter\Jobs\RetrieveTweetsFromUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RetrieveTweetsTest extends TestCase
{
    public function test_when_interval_meets_minute_job_must_be_dispatched()
    {
        Bus::fake(RetrieveTweetsFromUser::class);

        $interval = 5;
        Carbon::setTestNow(now()->minute($interval*($this->faker->numberBetween(1,11))));

        $twitterUser = \App\Models\TwitterFollowing::factory()->create([
            'interval' => $interval
        ]);

        dispatch(new \App\Feature\Twitter\Jobs\RetrieveTweets());

        Bus::assertDispatched(RetrieveTweetsFromUser::class, function ($job) use ($twitterUser) {
            return $job->twitterUser->id === $twitterUser->id;
        });
    }

    public function test_when_interval_doesnt_meet_job_must_not_be_dispatched()
    {
        Bus::fake(RetrieveTweetsFromUser::class);

        $interval = 5;
        Carbon::setTestNow(now()->minute(($interval+1)*($this->faker->numberBetween(1,9))));

        $twitterUser = \App\Models\TwitterFollowing::factory()->create([
            'interval' => $interval
        ]);

        dispatch(new \App\Feature\Twitter\Jobs\RetrieveTweets());

        Bus::assertNotDispatched(RetrieveTweetsFromUser::class);
    }

    public function test_when_mixed_intervals_found_must_be_dispatched_selectively()
    {
        Bus::fake(RetrieveTweetsFromUser::class);

        $interval = 5;
        Carbon::setTestNow(now()->minute($interval));

        $twitterUser = \App\Models\TwitterFollowing::factory()->create([
            'interval' => $interval
        ]);

        $fakeTwitterUser = \App\Models\TwitterFollowing::factory()->create([
            'interval' => $interval + 1
        ]);

        dispatch(new \App\Feature\Twitter\Jobs\RetrieveTweets());

        Bus::assertDispatched(RetrieveTweetsFromUser::class, function ($job) use ($twitterUser) {
            return $job->twitterUser->id === $twitterUser->id;
        });

        Bus::assertNotDispatched(RetrieveTweetsFromUser::class, function ($job) use ($fakeTwitterUser) {
            return $job->twitterUser->id === $fakeTwitterUser->id;
        });
    }
}
