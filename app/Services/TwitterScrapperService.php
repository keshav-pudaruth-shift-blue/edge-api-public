<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TwitterScrapperService
{
    public const TWITTER_DATETIME_FORMAT = 'D M d H:i:s O Y';

    /**
     * @var \Illuminate\Http\Client\PendingRequest
     */
    private $httpClient;

    public function __construct()
    {
        $this->httpClient = Http::withOptions([
            'base_uri' => config('ib-gateway.base_uri').'og/twitter/',
            'timeout' => 60,
            'connect_timeout' => config('ib-gateway.connect_timeout'),
            'http_errors' => false
        ]);
    }

    public function getTweetsByUsername(string $username): \Illuminate\Http\Client\Response
    {
        return $this->httpClient->retry(3,1000)->get("tweets/$username");
    }
}
