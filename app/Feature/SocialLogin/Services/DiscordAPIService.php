<?php

namespace App\Feature\SocialLogin\Services;

use Illuminate\Support\Facades\Http;

class DiscordAPIService
{
    protected $client;

    public function __construct(string $accessToken = '')
    {
        $this->client = Http::withOptions([
            'base_uri' => 'https://discord.com/api/',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        if(!empty($accessToken)) {
            $this->client->withToken($accessToken);
        }
    }

    public function setAccessToken(string $accessToken): self
    {
        $this->client->withToken($accessToken);

        return $this;
    }

    public function getUserGuild(): \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response
    {
        return $this->client->get('users/@me/guilds');
    }
}
