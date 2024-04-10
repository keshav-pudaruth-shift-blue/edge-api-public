<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OptionsContractsHistoricalData extends BaseModel
{
    use HasFactory;

    //E.g: "20230322  16:05:00"
    public const DATETIME_FORMAT_USA = 'Ymd  H:i:s';

    public const INTERVAL_1_MIN = '1 min';
    public const INTERVAL_5_MIN = '5 mins';
    public const INTERVAL_15_MIN = '15 mins';
    public const INTERVAL_30_MIN = '30 mins';
    public const INTERVAL_1_HOUR = '1 hour';
    public const INTERVAL_1_DAY = '1 day';
    public const INTERVAL_1_WEEK = '1W';
    public const INTERVAL_1_MONTH = '1M';

    protected $table = 'options_contracts_historical_data';

    protected $fillable = [
        'options_contracts_id',
        'datetime',
        'open',
        'high',
        'low',
        'close',
        'volume',
    ];

    protected $dates = [
        'datetime',
    ];

    public function optionContract(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OptionsContracts::class, 'options_contracts_id', 'id');
    }
}
