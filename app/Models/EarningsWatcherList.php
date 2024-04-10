<?php

namespace App\Models;

class EarningsWatcherList extends BaseModel
{
    public const CONDITION_AFTER_MARKET = 'AM';
    public const CONDITION_PRE_MARKET = 'PM';

    public $table = 'earnings_watcher_list';

    public $fillable = [
        'company',
        'symbol',
        'date',
        'time',
        'importance',
        'eod_price'
    ];

    public $dates = [
        'date'
    ];
}
