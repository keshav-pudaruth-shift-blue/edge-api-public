<?php

namespace App\Repositories;

use App\Models\OptionsContractsWithTradingHours;
use App\Models\OptionsContracts;
use App\Models\OptionsContractsTypeEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class OptionsContractsRepository extends BaseRepository
{
    public function __construct(protected OptionsContracts $model)
    {
    }

    /**
     * @return Collection
     */
    public function getOptionContractsWithinTradingHours(): Collection
    {
        return $this->queryOptionContractsWithinTradingHours()
            ->get();

    }

    /**
     * @param string $symbol
     * @param Carbon $expiry
     * @return Collection
     */
    public function getBySymbolAndExpiry(string $symbol, Carbon $expiry): Collection
    {
        return $this->getQuery()->where('symbol', $symbol)->where('expiry_date', $expiry->format('Y-m-d'))->get();
    }

    /**
     * @param int $contractId
     * @return OptionsContracts|null
     */
    public function getByContractId(int $contractId): ?OptionsContracts
    {
        return $this->getQuery()->where('contract_id', $contractId)->first();
    }

    /**
     * @param string $symbol
     * @param Carbon $expiry
     * @param float $startStrike
     * @param float $endStrike
     * @return Collection
     */
    public function getBySymbolAndExpiryAndStrikeRange(
        string $symbol,
        Carbon $expiry,
        float $startStrike,
        float $endStrike
    ): Collection {
        return $this->getQuery()->where('symbol', $symbol)
            ->where('expiry_date', $expiry->format('Y-m-d'))
            ->where(
            'strike', '>=', $startStrike
            )
            ->where(
            'strike', '<=', $endStrike
            )
            ->get();
    }

    /**
     * @param string $symbol
     * @param Carbon $expiry
     * @param OptionsContractsTypeEnum $contractType
     * @param array $contractList
     * @param \Illuminate\Support\Collection $tradingHours
     * @return array|Collection
     */
    public function insertContracts(string $symbol, Carbon $expiry, OptionsContractsTypeEnum $contractType, array $contractList, \Illuminate\Support\Collection $tradingHours): array|Collection
    {
        $contractListFormatted = array_map(function($strikeRow) use ($symbol, $expiry, $contractType, $tradingHours){
            return [
                'symbol' => $symbol,
                'expiry_date' => $expiry->toDateString(),
                'strike_price' => $strikeRow['contract']['strike'],
                'contract_id' => $strikeRow['contract']['conId'],
                'option_type' => $contractType,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }, $contractList);

        $this->getQuery()->upsert($contractListFormatted,['contract_id']);

        $listOfRecentlyInsertedContracts = $this->getQuery()->whereIn('contract_id', Arr::pluck($contractListFormatted, 'contract_id'))->get();

        $optionContractsWithTradingHours= [];

        foreach($tradingHours as $tradingHourRow) {
            foreach($listOfRecentlyInsertedContracts as $recentlyInsertedContract) {
                $optionContractsWithTradingHours[] = [
                    'options_contracts_id' => $recentlyInsertedContract->id,
                    'options_contracts_trading_hours_id' => $tradingHourRow->id
                ];
            }
        }

        app(OptionsContractsWithTradingHoursRepository::class)->getQuery()->upsert($optionContractsWithTradingHours, ['options_contracts_id', 'options_contracts_trading_hours_id']);

        return $listOfRecentlyInsertedContracts;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function queryOptionContractsWithinTradingHours(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->getQuery()
            ->with('tradingHours')
            ->whereHas('tradingHours', function($query) {
                $query->where('start_datetime', '<=', now()->seconds(0)->toDateTimeString())
                    ->where('end_datetime', '>=', now()->seconds(0)->toDateTimeString());
            });
    }
}
