<?php

namespace App\Repositories;

use App\Models\OptionsContracts;
use App\Models\OptionsContractsTradingHours;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class OptionsContractsTradingHoursRepository extends BaseRepository
{
    public function __construct(protected OptionsContractsTradingHours $model)
    {
    }

    public function firstOrCreateTradingHours($startDateTime, $endDateTime): OptionsContractsTradingHours
    {
        return $this->getModel()::firstOrCreate([
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'timezone' => OptionsContractsTradingHours::TIMEZONE_USA
        ]);
    }

    /**
     * @param Carbon $endDateTime
     * @return Collection
     */
    public function getContractsWhereEndDateTime(Carbon $endDateTime): \Illuminate\Database\Eloquent\Collection
    {
        return $this->getQuery()->where("end_datetime", '=', $endDateTime->toDateTimeString())->get();
    }

    /**
     * @param Carbon $startDateTime
     * @return Collection
     */
    public function getContractsWhereStartDateTime(Carbon $startDateTime): \Illuminate\Database\Eloquent\Collection
    {
        return $this->getQuery()->where("start_datetime", '=', $startDateTime->toDateTimeString())->get();
    }
}
