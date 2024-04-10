<?php

namespace App\Feature\Twitter\Jobs;

use App\Feature\Discord\Services\DiscordAlertService;
use App\Feature\OpenAI\Services\OpenAIChatService;
use App\Models\OpenAIChatPrompts;
use App\Models\TwitterFollowing;
use App\Repositories\TwitterFollowingRepository;
use App\Services\TwitterScrapperService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Spatie\ArtisanDispatchable\Jobs\ArtisanDispatchable;

class RetrieveTweetsFromUser implements ShouldQueue, ArtisanDispatchable
{
    use Queueable;

    public int $tries = 2;

    /**
     * @var TwitterScrapperService
     */
    private mixed $twitterScrapperService;

    /**
     * @var TwitterFollowingRepository
     */
    private mixed $twitterFollowingRepository;

    /**
     * @var OpenAIChatService
     */
    private mixed $openAIChatService;

    /**
     * @var DiscordAlertService
     */
    private mixed $discordAlertService;

    public function __construct(public TwitterFollowing $twitterUser)
    {
        $this->queue = 'retrieve-tweets';
    }

    public function handle(): void
    {
        $this->initializeServices();
        //Fetch tweets from og service
        $tweetsResponse = $this->twitterScrapperService->getTweetsByUsername($this->twitterUser->username);
        //Check if tweets are valid
        if ($tweetsResponse->failed()) {
            Log::error('RetrieveTweetsFromUser::handle - Error getting tweets from TwitterScrapperService', [
                'username' => $this->twitterUser->username,
                'response_code' => $tweetsResponse->status(),
                'response_body' => $tweetsResponse->body()
            ]);
            return;
        } else {
            $tweets = $tweetsResponse->json();
        }
        //Filter tweets that have already been processed
        $unreadTweets = collect($tweets)->map(function ($tweet) {
            $tweet['created_at'] = Carbon::createFromFormat(TwitterScrapperService::TWITTER_DATETIME_FORMAT,$tweet['created_at']);
            return $tweet;
        })->filter(function ($tweet) {
            //filter out tweets that have already been read
            return $this->twitterUser->last_tweet_datetime->lt($tweet['created_at']);
        })->sort(function($a,$b){
            //Sort by earliest first
            return $a['created_at']->lt($b['created_at']) ? -1 : 1;
        });
        //Filter them through AI
        if (!$unreadTweets->isEmpty()) {
            $lastTweetDatetime = now();
            foreach ($unreadTweets as $unreadTweetRow) {
                $openAiResponse = $this->openAIChatService->start($unreadTweetRow['full_text'], OpenAIChatPrompts::CONTEXT_TWITTER_TRADES);
                //If AI response is valid, post to discord
                if (str_contains($openAiResponse, 'No trades found') === false && str_contains($openAiResponse, "I am sorry") === false) {
                    Log::debug('RetrieveTweetsFromUser::handle - New trade tweet found', [
                        'username' => $this->twitterUser->username,
                        'tweet_id' => $unreadTweetRow['rest_id'],
                        'tweet_text' => $unreadTweetRow['full_text'],
                        'ai_response' => $openAiResponse
                    ]);

                    //If yes, post to discord
                    $this->discordAlertService->sendTradeAlert(
                        $openAiResponse,
                        $unreadTweetRow['url'],
                        $unreadTweetRow['full_text'],
                        $this->twitterUser->username,
                        $unreadTweetRow['created_at']->shiftTimezone('Indian/Mauritius')->format('H:i:s d/m')
                    );

                    $lastTweetDatetime = $unreadTweetRow['created_at'];
                } else {
                    Log::debug('RetrieveTweetsFromUser::handle - No new trade tweets found', [
                        'username' => $this->twitterUser->username,
                        'tweet_id' => $unreadTweetRow['rest_id'],
                        'tweet_text' => $unreadTweetRow['full_text'],
                        'ai_response' => $openAiResponse
                    ]);
                }
            }

            //Update last tweet datetime
            $this->twitterUser->last_tweet_datetime = $lastTweetDatetime;
            $this->twitterUser->save();
        } else {
            Log::debug('RetrieveTweetsFromUser::handle - No new tweets to process', [
                'username' => $this->twitterUser->username
            ]);
        }
    }

    private function initializeServices(): void
    {
        $this->twitterScrapperService = app(TwitterScrapperService::class);
        $this->twitterFollowingRepository = app(TwitterFollowingRepository::class);
        $this->openAIChatService = app(OpenAIChatService::class);
        $this->discordAlertService = app(DiscordAlertService::class);
    }

}
