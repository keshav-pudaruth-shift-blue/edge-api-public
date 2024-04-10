<?php

namespace App\Tests\Integration\Repositories;

use App\Models\OptionsContractsTradingHours;
use App\Models\OptionsContractsTypeEnum;
use Tests\TestCase;

class OptionsContractsRepositoryTest extends TestCase
{
    /**
     * @var \App\Repositories\OptionsContractsRepository
     */
    private $optionsContractsRepository;

    /**
     * @var \App\Repositories\OptionsContractsWithTradingHoursRepository
     */
    private $optionsContractsWithTradingHoursRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->optionsContractsRepository = app(\App\Repositories\OptionsContractsRepository::class);
        $this->optionsContractsWithTradingHoursRepository = app(\App\Repositories\OptionsContractsWithTradingHoursRepository::class);
    }

    public function test_insert_contracts_saves_successfully()
    {
        $contractList = [
            [
                'contract' => [
                    'conId' => 123,
                    'symbol' => 'AAPL',
                    'secType' => 'OPT',
                    'lastTradeDateOrContractMonth' => '20230917',
                    'strike' => 100,
                    'right' => 'C',
                    'multiplier' => 100,
                    'exchange' => 'SMART',
                    'currency' => 'USD',
                    'localSymbol' => 'AAPL  210917C00100000',
                    'tradingClass' => 'AAPL',
                ]
            ],
            [
                'contract' => [
                    'conId' => 456,
                    'symbol' => 'AAPL',
                    'secType' => 'OPT',
                    'lastTradeDateOrContractMonth' => '20230917',
                    'strike' => 200,
                    'right' => 'P',
                    'multiplier' => 100,
                    'exchange' => 'SMART',
                    'currency' => 'USD',
                    'localSymbol' => 'AAPL  210917P00100000',
                    'tradingClass' => 'AAPL',
                ]
            ],
        ];

        $tradingHours = OptionsContractsTradingHours::factory()->count(2)->create();

        $insertedContracts = $this->optionsContractsRepository->insertContracts('AAPL', now(), OptionsContractsTypeEnum::CALL, $contractList, $tradingHours);

        $this->assertDatabaseHas($this->optionsContractsRepository->getModel()->getTable(), [
            'contract_id' => 123,
            'strike_price' => 100,
            'option_type' => OptionsContractsTypeEnum::CALL,
        ]);

        $insertedContracts->each(function($insertedContract) use ($tradingHours) {
            $tradingHours->each(function($tradingHourRow) use ($insertedContract) {
                $this->assertDatabaseHas($this->optionsContractsWithTradingHoursRepository->getModel()->getTable(), [
                    'options_contracts_id' => $insertedContract->id,
                    'options_contracts_trading_hours_id' => $tradingHourRow->id,
                ]);
            });
        });
    }

    public function test_insert_contracts_save_twice_will_not_insert_duplicates()
    {
        $contractList = [
            [
                'contract' => [
                    'conId' => 123,
                    'symbol' => 'AAPL',
                    'secType' => 'OPT',
                    'lastTradeDateOrContractMonth' => '20230917',
                    'strike' => 100,
                    'right' => 'C',
                    'multiplier' => 100,
                    'exchange' => 'SMART',
                    'currency' => 'USD',
                    'localSymbol' => 'AAPL  210917C00100000',
                    'tradingClass' => 'AAPL',
                ]
            ]
        ];

        $tradingHours = OptionsContractsTradingHours::factory()->count(2)->create();

        $insertedContracts = $this->optionsContractsRepository->insertContracts('AAPL', now(), OptionsContractsTypeEnum::CALL, $contractList, $tradingHours);

        $this->assertDatabaseCount($this->optionsContractsRepository->getModel()->getTable(), 1);

        $this->assertDatabaseCount($this->optionsContractsWithTradingHoursRepository->getModel()->getTable(), 2);
    }
}
