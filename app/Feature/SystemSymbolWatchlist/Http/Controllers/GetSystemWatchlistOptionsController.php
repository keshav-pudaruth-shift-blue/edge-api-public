<?php

namespace App\Feature\SystemSymbolWatchlist\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\SystemSymbolWatchlistRepository;

class GetSystemWatchlistOptionsController extends Controller
{
    public function __construct(protected SystemSymbolWatchlistRepository $systemSymbolWatchlistRepository)
    {
    }

    public function __invoke(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->systemSymbolWatchlistRepository->getSystemSymbolWatchlist();
    }

}
