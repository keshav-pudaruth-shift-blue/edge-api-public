<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        DB::statement(
            'CREATE VIEW options_live_data_latest AS
            SELECT
              id,
              symbol,
              type,
              strike,
              expiry_date,
              delta,
              gamma,
              open_interest,
              implied_volatility,
              bearish_volume,
              bullish_volume,
              current_volume,
              last_updated,
              created_at,
              updated_at
            FROM options_live_data JOIN (SELECT MAX(options_live_data.id) AS latest_id FROM options_live_data
                GROUP BY options_live_data.symbol,options_live_data.type,options_live_data.strike,options_live_data.expiry_date) options_live_data_latest
                ON ((options_live_data_latest.latest_id = options_live_data.id))'
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS options_live_data_latest');
    }
};
