<?php

namespace App\Feature\TOSAlerts\Listeners;

use App\Feature\Discord\Services\DiscordAlertService;
use App\Repositories\SymbolListRepository;
use App\Services\WsjAPI;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessNewAlertEmailReceived implements ShouldQueue
{
    /**
     * @var DiscordAlertService
     */
    private $discordService;

    /**
     * @var WsjAPI
     */
    private $wsjAPI;

    /**
     * @var SymbolListRepository
     */
    private $symbolListRepository;

    /**
     * @throws \Exception
     */
    public function handle(\App\Feature\TOSAlerts\Events\NewAlertEmailReceived $event): void
    {
        Log::info('ProcessNewAlertEmailReceived - New alert email received', [
            'message_id' => $event->messageId,
            'subject' => $event->subject,
        ]);
        $messageId = $event->messageId;
        $subject = $event->subject;

        preg_match("/^Alert: New symbols?:(.*) w.*$/", $subject, $contracts);

        $contracts = explode(", ", trim($contracts[1]));
        $contracts = array_map(function ($optionChainName) {
            preg_match_all("/^.([A-Z]{1,4})([0-9]{6})([C|P])(\\d+\\.?\\d*)$/", trim($optionChainName), $matches);
            list($symbol, $expirationDate, $callPut, $strikePrice) = array_slice($matches, 1);

            return [
                'name' => $optionChainName,
                'symbol' => strtoupper($symbol[0]),
                'expiration_date' => $expirationDate[0],
                'strike_price' => (float)$strikePrice[0],
                'type' => $callPut[0],
            ];
        }, $contracts);

        //Save to database


        //Publish to discord
        $this->publishToDiscord($contracts);

        Log::info('ProcessNewAlertEmailReceived - New alert email processed', [
            'message_id' => $messageId,
            'subject' => $subject,
            'contracts' => $contracts,
        ]);

    }

    /**
     * @throws \Exception
     */
    private function publishToDiscord($contracts)
    {
        $this->discordService = app(DiscordAlertService::class);
        $this->wsjAPI = app(WsjAPI::class);
        $this->symbolListRepository = app(SymbolListRepository::class);

        foreach ($contracts as $contractRow) {
            $lock = Cache::lock("mojo-discord-alert-".$contractRow['name'], 900);
            if($lock->get()) {
                try {
                    $underlyingPrice = $this->wsjAPI->getDelayedCurrentPrice($contractRow['symbol']);
                } catch(\Exception $e){
                    $underlyingPrice = 'N/A';
                    Log::warning('ProcessNewAlertEmailReceived - Unable to get underlying price for symbol', [
                        'symbol' => $contractRow['symbol'],
                        'exception' => $e->getMessage(),
                    ]);
                }
                $this->discordService->sendMojoAlert(
                    $contractRow['symbol'],
                    $contractRow['strike_price'],
                    $contractRow['type'],
                    $contractRow['expiration_date'],
                    $underlyingPrice
                );
            } else {
                Log::info('ProcessNewAlertEmailReceived - Lock already exists for symbol', [
                    'name' => $contractRow['name'],
                ]);
            }

        }
    }
}
