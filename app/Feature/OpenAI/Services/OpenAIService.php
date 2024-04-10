<?php

namespace App\Feature\OpenAI\Services;

class OpenAIService
{
    protected string $aiName = 'The Genie';

    protected \OpenAI\Client $client;

    public function __construct()
    {
        $this->client = \OpenAI::client(env('OPENAI_API_KEY'));
    }

    /**
     * @param $client
     * @return Object
     */
    public function setClient($client): Object
    {
        $this->client = $client;
        return $this;
    }
}
