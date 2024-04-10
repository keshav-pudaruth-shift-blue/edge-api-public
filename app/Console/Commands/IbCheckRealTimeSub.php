<?php

namespace App\Console\Commands;

use App\Feature\IBOptionsDataSync\Services\IBGatewayService;
use App\Repositories\OptionsContractsHistoricalDataRepository;
use App\Repositories\OptionsContractsRepository;
use Illuminate\Console\Command;

class IbCheckRealTimeSub extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ib:check-real-time';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if there are any real-time subscriptions';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $ibGatewayService = app(IBGatewayService::class);
        $optionContractRepository = app(OptionsContractsRepository::class);
        $optionsContractsHistoricalDataRepository=app(OptionsContractsHistoricalDataRepository::class);

        $this->info('Checking real-time subscriptions');
        $ibRealTimeSubs = $ibGatewayService->getListRealTimeSubscriptions();
        if(!empty($ibRealTimeSubs)) {
            $optionContractList = $optionContractRepository->getQuery()->whereIn('contract_id', $ibRealTimeSubs)->get([
                'contract_id',
                'symbol',
                'strike_price',
                'option_type',
                'expiry_date'
            ])->toArray();

            $parsedOptionContractList = array_map(function($row) use ($optionsContractsHistoricalDataRepository){
                $row['isLive'] = $optionsContractsHistoricalDataRepository->checkLivenessRealTimeDataByContractId($row['contract_id']);
                return $row;
            },$optionContractList);

            $this->info('Real-time subscriptions found');

            $this->table(['Contract ID', 'Symbol', 'Strike', 'Type', 'Expiry date', 'N/A', 'Is Live'],
                $parsedOptionContractList);
        } else {
            $this->info('No real-time subscriptions found');
        }
        $this->info('Done');

        return 0;
    }
}
