<?php

namespace App\Repositories;

use App\Models\OptionsContractsWithTradingHours;

class OptionsContractsWithTradingHoursRepository extends BaseRepository
{
    public function __construct(protected OptionsContractsWithTradingHours $model)
    {
    }
}
