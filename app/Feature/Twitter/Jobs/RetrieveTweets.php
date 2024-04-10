<?php

namespace App\Feature\Twitter\Jobs;

use App\Repositories\TwitterFollowingRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class RetrieveTweets implements ShouldQueue, ArtisanDispatchable
{
    use Queueable;

    /**
     * @var TwitterFollowingRepository
     */
    private mixed $twitterFollowingRepository;

    public function handle(): void
    {
        $this->twitterFollowingRepository = app(TwitterFollowingRepository::class);

        $twitterUsers = $this->twitterFollowingRepository->getActiveFollowing();

        foreach ($twitterUsers as $twitterUser) {
            if(now()->minute % $twitterUser->interval === 0) {
                dispatch(new RetrieveTweetsFromUser($twitterUser));
            }
        }
    }
}
