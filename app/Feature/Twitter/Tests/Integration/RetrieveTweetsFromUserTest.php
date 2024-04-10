<?php

namespace App\Feature\Twitter\Tests\Integration;

use App\Models\TwitterFollowing;
use App\Services\TwitterScrapperService;
use Carbon\Carbon;
use Database\Seeders\OpenAIChatPrompts;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RetrieveTweetsFromUserTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->seed([
            OpenAIChatPrompts::class
        ]);
    }

    public function testSimpleBuyTradeTweetFromUser()
    {
        $twitterUser = TwitterFollowing::factory()->create();

        $tweetsHTTPResponse = [
            [
                'rest_id' => $this->faker->randomDigitNotNull,
                'created_at' => now()->format(TwitterScrapperService::TWITTER_DATETIME_FORMAT),
                'full_text' => 'I bought 100 contracts SPX 4500 calls @ 1.0',
                'url' => $this->faker->url
            ],
        ];

        Http::fake(
            [
                'tweets/'.$twitterUser->username => Http::response($tweetsHTTPResponse),
                'webhooks/*' => Http::response()
            ],
        );

        dispatch(new \App\Feature\Twitter\Jobs\RetrieveTweetsFromUser($twitterUser));

        $this->assertDatabaseHas('twitter_following', [
            'id' => $twitterUser->id,
            'last_tweet_datetime' => Carbon::parse($tweetsHTTPResponse[0]['created_at'])
        ]);
    }

    public function test_tweets_from_user_must_be_sent_in_datetime_earliest_order()
    {
        $twitterUser = TwitterFollowing::factory()->create();

        $tweetsHTTPResponse = [
            [
                'rest_id' => $this->faker->randomDigitNotNull,
                'created_at' => now()->format(TwitterScrapperService::TWITTER_DATETIME_FORMAT),
                'full_text' => 'I bought 100 contracts SPX 4500 calls @ 1.0',
                'url' => $this->faker->url
            ],
            [
                'rest_id' => $this->faker->randomDigitNotNull,
                'created_at' => now()->subMinute()->format(TwitterScrapperService::TWITTER_DATETIME_FORMAT),
                'full_text' => 'Close 100 contracts QQQ 350 calls @ 0.1',
                'url' => $this->faker->url
            ],
        ];

        Http::fake(
            [
                'tweets/'.$twitterUser->username => Http::response($tweetsHTTPResponse),
                'webhooks/*' => Http::response()
            ],
        );

        dispatch(new \App\Feature\Twitter\Jobs\RetrieveTweetsFromUser($twitterUser));

        $this->assertDatabaseHas('twitter_following', [
            'id' => $twitterUser->id,
            'last_tweet_datetime' => Carbon::parse($tweetsHTTPResponse[0]['created_at'])
        ]);
    }

    public function test_empty_tweet_response_must_not_fail()
    {
        $twitterUser = TwitterFollowing::factory()->create();

        Http::fake(
            [
                'tweets/'.$twitterUser->username => Http::response([]),
                //'webhooks/*' => Http::response()
            ],
        );

        dispatch(new \App\Feature\Twitter\Jobs\RetrieveTweetsFromUser($twitterUser));

        $this->assertDatabaseHas('twitter_following', [
            'id' => $twitterUser->id
        ]);
    }
}
