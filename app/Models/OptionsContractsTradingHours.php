<?php

namespace App\Models;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OptionsContractsTradingHours extends BaseModel
{
    use HasFactory;
    use Cachable;

    public const TIMEZONE_USA = 'US/Central';

    protected $table = 'options_contracts_trading_hours';

    protected $fillable = [
        'start_datetime',
        'end_datetime',
        'timezone'
    ];

    protected $dates = [
        'start_datetime',
        'end_datetime'
    ];
}
