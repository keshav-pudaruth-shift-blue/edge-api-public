<?php

namespace App\Feature\Discord\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WebhookService
{
    /**
     * @var \Illuminate\Support\Facades\Http
     */
    private $httpClient;

    public function __construct()
    {
        $this->httpClient = Http::withOptions(
            [
                'base_uri' => 'https://discord.com/api/webhooks/',
            ]
        );
    }

    /**
     * @param string $webhookURL
     * @param array $data
     * @return Response
     */
    public function sendRaw(string $webhookURL, array $data): \Illuminate\Http\Client\Response
    {
        return $this->httpClient->post($webhookURL, $data);
    }

    /**
     * @param string $symbolName
     * @param array $data
     * @param bool $loungeAlert
     * @param bool $mojoAlert
     * @param bool $tradeAlert
     * @return Response
     */
    public function send(string $symbolName, array $data, bool $loungeAlert = false, bool $mojoAlert = false, bool $tradeAlert = false): \Illuminate\Http\Client\Response
    {
        $webhookURL = match ($symbolName) {
            'SPX', '^SPX' => "1093579080173109280/ThKoED2TsOF3Ss3BY5_wL5MG07BXpIzIBCYtD1Rv01HIxJOuIoa5RG5esjn9M4fkKXhG",
            'ES' => "1093583783447511081/AFZGzhbw2FGjU8PoJSbwVVh2hq_T2iq-FY0V_IRfm_80bRxfzeMbaFiVNl4WmnJNDOXy",
        };

        if($loungeAlert === true) {
            $webhookURL = "1110866519065362492/NLbJgddDQMmxr8VSlNhjyn8IZKjTr7VEi-AqNi8cwnC0NDgr0n-9VcM_9d1A83mLjE7d";
        }

        if($mojoAlert === true) {
            $webhookURL = "1116644121969430638/94iYKVWj05UN70-QRoQs-cxyeSjjAIk7BGYV2bEfLomM-fK5JhXvvPOnHD_lvKEL1BI8";
        }

        if($tradeAlert === true) {
            $webhookURL = "1162341558272659527/EOZsBsppmvCFMcJb4UHKH-iTAZMiX6WPrgbaRKwLkgWQ3iLhetZvyAbZf937aulmiyWD";
        }

        return $this->httpClient->post($webhookURL, $data);
    }
}
