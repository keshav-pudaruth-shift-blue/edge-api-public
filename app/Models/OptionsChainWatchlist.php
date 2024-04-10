<?php

namespace App\Models;

class OptionsChainWatchlist extends BaseModel
{

    public const WATCHLIST_TYPE_0DTE = '0dte';

    public const WATCHLIST_TYPE_1DTE = '1dte';

    public const WATCHLIST_TYPE_OPEX = 'opex';

    /**
     * @var string
     */
    public $table = 'options_chain_watchlist';
}
