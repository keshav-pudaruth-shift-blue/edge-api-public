<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:api'])->get('/auth/user', function (Request $request) {
    return $request->user();
});

Route::get(
    '/options/live-data/{symbol}',
    \App\Feature\OptionsDataSync\Http\Controllers\GetOptionsDataBySymbolAndDateController::class
)->middleware(['cacheResponse:600', 'auth:api'])
->name('get.options.live-data');

Route::get(
    '/options/live-data-earliest-expiry/{symbol}',
    \App\Feature\OptionsDataSync\Http\Controllers\GetOptionsDataBySymbolEarliestExpiryController::class
)->middleware('cacheResponse:600')
->name('get.options.live-data-earliest-expiry');

Route::get(
    '/options/watchlist',
    \App\Feature\SystemSymbolWatchlist\Http\Controllers\GetSystemWatchlistOptionsController::class
)->middleware(['cacheResponse:600'])
->name('get.options.watchlist');
