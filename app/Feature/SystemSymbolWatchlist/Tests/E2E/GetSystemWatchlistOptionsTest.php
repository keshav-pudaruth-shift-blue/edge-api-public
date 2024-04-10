<?php

namespace App\Feature\SystemSymbolWatchlist\Tests\E2E;

use Tests\TestCase;

class GetSystemWatchlistOptionsTest extends TestCase
{
    public function test_get_system_watchlist_options()
    {
        $response = $this->get('/api/options/watchlist');

        $response->assertStatus(200);
    }

    public function test_get_system_watchlist_options_returns_correct_data_structure()
    {
        $response = $this->get('/api/options/watchlist');

        $response->assertJsonStructure([
            '*' => [
                'id',
                'symbol',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    public function test_get_system_watchlist_options_returns_correct_data()
    {
        $systemWatchlistOptions = \App\Models\SystemSymbolWatchlist::factory()->create();

        $response = $this->get('/api/options/watchlist');

        $response->assertJson([[
                'symbol' => $systemWatchlistOptions->symbol,
            ]
        ]);
    }
}
