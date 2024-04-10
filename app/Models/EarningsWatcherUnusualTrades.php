<?php

namespace App\Models;

class EarningsWatcherUnusualTrades extends BaseModel
{
    public $table = 'earnings_watcher_unusual_trades';

    public $fillable = [
        'earnings_watcher_list_id',
        'strike',
        'type',
        'expiry',
        'price',
        'size',
        'premium',
        'price_action',
        'purchase_action',
        'trade_type',
        'trade_leg',
        'trade_action',
        'executed_at'
    ];

    public $dates = [
        'expiry',
        'executed_at'
    ];

    public function earningsWatcherList()
    {
        return $this->belongsTo(EarningsWatcherList::class);
    }
}
